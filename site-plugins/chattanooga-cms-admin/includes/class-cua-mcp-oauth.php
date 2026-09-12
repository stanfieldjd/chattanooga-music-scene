<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native OAuth 2.1 authorization/resource-server support for Chattanooga CMS Admin MCP.
 *
 * This class deliberately keeps OAuth inside Chattanooga CMS Admin and has no dependency
 * on an external MCP transport, adapter, or proxy.
 */
final class CUA_MCP_OAuth {
	const SCOPE = 'mcp:admin';
	const ACCESS_TTL = HOUR_IN_SECONDS;
	const REFRESH_TTL = 30 * DAY_IN_SECONDS;
	const CODE_TTL = 5 * MINUTE_IN_SECONDS;
	const CLIENT_TTL = YEAR_IN_SECONDS;
	const TRANSIENT_ACCESS = 'cua_mcp_at_';
	const TRANSIENT_REFRESH = 'cua_mcp_rt_';
	const TRANSIENT_CODE = 'cua_mcp_ac_';
	const TRANSIENT_CLIENT = 'cua_mcp_client_';

	public static function bootstrap() {
		add_filter( 'determine_current_user', array( __CLASS__, 'authenticate_bearer' ), 25 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'add_authentication_challenge' ), 10, 3 );
		add_action( 'parse_request', array( __CLASS__, 'serve_well_known_metadata' ), 0 );
		add_action( 'admin_post_cmsa_mcp_oauth_authorize', array( __CLASS__, 'authorization_endpoint' ) );
		add_action( 'admin_post_nopriv_cmsa_mcp_oauth_authorize', array( __CLASS__, 'authorization_endpoint_login' ) );
	}

	public static function register_routes() {
		register_rest_route(
			CUA_MCP_Server::REST_NAMESPACE,
			'/oauth/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'register_client' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			CUA_MCP_Server::REST_NAMESPACE,
			'/oauth/token',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'token_endpoint' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function authenticate_bearer( $user_id ) {
		if ( $user_id || ! self::request_targets_mcp() ) {
			return $user_id;
		}

		$token = self::bearer_token();
		if ( '' === $token ) {
			return $user_id;
		}

		$record = get_transient( self::TRANSIENT_ACCESS . hash( 'sha256', $token ) );
		if ( ! is_array( $record ) || empty( $record['user_id'] ) || empty( $record['resource'] ) || empty( $record['expires_at'] ) ) {
			return $user_id;
		}
		if ( (int) $record['expires_at'] <= time() || ! hash_equals( self::mcp_resource(), (string) $record['resource'] ) ) {
			return $user_id;
		}
		if ( self::SCOPE !== (string) ( $record['scope'] ?? '' ) ) {
			return $user_id;
		}

		$user = get_user_by( 'id', (int) $record['user_id'] );
		return $user instanceof WP_User ? (int) $user->ID : $user_id;
	}

	public static function add_authentication_challenge( $response, $server, $request ) {
		if ( ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		if ( '/' . CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE !== $request->get_route() ) {
			return $response;
		}
		if ( 401 !== (int) $response->get_status() ) {
			return $response;
		}

		$response->header(
			'WWW-Authenticate',
			'Bearer resource_metadata="' . esc_url_raw( self::protected_resource_metadata_url() ) . '", scope="' . self::SCOPE . '"'
		);
		return $response;
	}

	public static function serve_well_known_metadata() {
		$path = self::request_path();
		if ( '' === $path ) {
			return;
		}

		$resource_path = self::metadata_url_path( self::protected_resource_metadata_url() );
		$server_path = self::metadata_url_path( self::authorization_server_metadata_url() );
		if ( $resource_path !== $path && $server_path !== $path ) {
			return;
		}

		if ( 'GET' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) && 'HEAD' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
			status_header( 405 );
			header( 'Allow: GET, HEAD' );
			exit;
		}

		$data = $resource_path === $path ? self::protected_resource_metadata() : self::authorization_server_metadata();
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
		if ( 'HEAD' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
			echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		exit;
	}

	public static function register_client( WP_REST_Request $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			return self::oauth_error( 'invalid_client_metadata', 'A JSON client metadata object is required.', 400 );
		}

		$redirects = self::validate_redirect_uris( $input['redirect_uris'] ?? null );
		if ( is_wp_error( $redirects ) ) {
			return self::oauth_error( 'invalid_redirect_uri', $redirects->get_error_message(), 400 );
		}

		$grant_types = isset( $input['grant_types'] ) ? array_values( (array) $input['grant_types'] ) : array( 'authorization_code', 'refresh_token' );
		if ( array_diff( $grant_types, array( 'authorization_code', 'refresh_token' ) ) || ! in_array( 'authorization_code', $grant_types, true ) ) {
			return self::oauth_error( 'invalid_client_metadata', 'Only authorization_code and refresh_token grant types are supported.', 400 );
		}

		$response_types = isset( $input['response_types'] ) ? array_values( (array) $input['response_types'] ) : array( 'code' );
		if ( array_diff( $response_types, array( 'code' ) ) || ! in_array( 'code', $response_types, true ) ) {
			return self::oauth_error( 'invalid_client_metadata', 'Only the code response type is supported.', 400 );
		}

		$auth_method = isset( $input['token_endpoint_auth_method'] ) ? (string) $input['token_endpoint_auth_method'] : 'none';
		if ( 'none' !== $auth_method ) {
			return self::oauth_error( 'invalid_client_metadata', 'This public-client authorization server supports token_endpoint_auth_method=none only.', 400 );
		}

		$client_token = self::random_token( 24 );
		if ( is_wp_error( $client_token ) ) {
			return self::oauth_error( 'server_error', 'A client identifier could not be generated.', 500 );
		}
		$client_id = 'cmsa_' . $client_token;

		$record = array(
			'client_id'                  => $client_id,
			'client_name'                => sanitize_text_field( (string) ( $input['client_name'] ?? 'MCP client' ) ),
			'redirect_uris'              => $redirects,
			'grant_types'                => $grant_types,
			'response_types'             => $response_types,
			'token_endpoint_auth_method' => 'none',
			'application_type'           => in_array( (string) ( $input['application_type'] ?? '' ), array( 'web', 'native' ), true ) ? (string) $input['application_type'] : 'web',
			'created_at'                 => time(),
		);
		set_transient( self::TRANSIENT_CLIENT . hash( 'sha256', $client_id ), $record, self::CLIENT_TTL );

		$response = new WP_REST_Response(
			array_merge(
				$record,
				array(
					'client_id_issued_at' => (int) $record['created_at'],
				)
			),
			201
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	public static function authorization_endpoint_login() {
		wp_safe_redirect( wp_login_url( self::current_request_url() ) );
		exit;
	}

	public static function authorization_endpoint() {
		if ( ! is_user_logged_in() ) {
			self::authorization_endpoint_login();
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator authority is required to authorize Chattanooga CMS Admin MCP.', 'chattanooga-cms-admin' ), '', array( 'response' => 403 ) );
		}

		$params = self::authorization_params( $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_wp_error( $params ) ) {
			wp_die( esc_html( $params->get_error_message() ), '', array( 'response' => 400 ) );
		}

		if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
			check_admin_referer( 'cmsa_mcp_oauth_authorize', '_cmsa_oauth_nonce' );
			$decision = isset( $_POST['cmsa_oauth_decision'] ) ? sanitize_key( wp_unslash( $_POST['cmsa_oauth_decision'] ) ) : '';
			if ( 'approve' !== $decision ) {
				self::authorization_redirect( $params['redirect_uri'], array( 'error' => 'access_denied', 'state' => $params['state'], 'iss' => self::issuer() ) );
			}

			$code = self::random_token( 32 );
			if ( is_wp_error( $code ) ) {
				wp_die( esc_html__( 'An authorization code could not be generated.', 'chattanooga-cms-admin' ), '', array( 'response' => 500 ) );
			}
			$record = array(
				'user_id'        => get_current_user_id(),
				'client_id'      => $params['client_id'],
				'redirect_uri'   => $params['redirect_uri'],
				'code_challenge' => $params['code_challenge'],
				'resource'       => $params['resource'],
				'scope'          => $params['scope'],
				'expires_at'     => time() + self::CODE_TTL,
			);
			set_transient( self::TRANSIENT_CODE . hash( 'sha256', $code ), $record, self::CODE_TTL );
			self::authorization_redirect( $params['redirect_uri'], array( 'code' => $code, 'state' => $params['state'], 'iss' => self::issuer() ) );
		}

		$user = wp_get_current_user();
		$client = $params['client'];
		$client_name = sanitize_text_field( (string) ( $client['client_name'] ?? $params['client_id'] ) );
		$action = esc_url( admin_url( 'admin-post.php' ) );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Authorize Chattanooga CMS Admin</title></head><body>';
		echo '<main style="max-width:640px;margin:4rem auto;font:16px/1.5 system-ui,sans-serif;padding:0 1rem">';
		echo '<h1>Authorize Chattanooga CMS Admin</h1>';
		echo '<p><strong>' . esc_html( $client_name ) . '</strong> is requesting administrator access to Chattanooga CMS Admin through its built-in MCP.</p>';
		echo '<p>WordPress account: <strong>' . esc_html( $user->user_login ) . '</strong></p>';
		echo '<p>Scope: <code>' . esc_html( $params['scope'] ) . '</code></p>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="cmsa_mcp_oauth_authorize">';
		foreach ( array( 'client_id', 'redirect_uri', 'response_type', 'code_challenge', 'code_challenge_method', 'resource', 'scope', 'state' ) as $key ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $params[ $key ] ) . '">';
		}
		wp_nonce_field( 'cmsa_mcp_oauth_authorize', '_cmsa_oauth_nonce' );
		echo '<p><button type="submit" name="cmsa_oauth_decision" value="approve">Authorize</button> <button type="submit" name="cmsa_oauth_decision" value="deny">Deny</button></p>';
		echo '</form></main></body></html>';
		exit;
	}

	public static function token_endpoint( WP_REST_Request $request ) {
		$params = $request->get_params();
		$grant_type = isset( $params['grant_type'] ) ? (string) $params['grant_type'] : '';
		if ( 'authorization_code' === $grant_type ) {
			return self::exchange_authorization_code( $params );
		}
		if ( 'refresh_token' === $grant_type ) {
			return self::exchange_refresh_token( $params );
		}
		return self::oauth_error( 'unsupported_grant_type', 'Only authorization_code and refresh_token grants are supported.', 400 );
	}

	private static function exchange_authorization_code( array $params ) {
		$code = isset( $params['code'] ) ? trim( (string) $params['code'] ) : '';
		$client_id = isset( $params['client_id'] ) ? trim( (string) $params['client_id'] ) : '';
		$redirect_uri = isset( $params['redirect_uri'] ) ? trim( (string) $params['redirect_uri'] ) : '';
		$verifier = isset( $params['code_verifier'] ) ? trim( (string) $params['code_verifier'] ) : '';
		$resource = isset( $params['resource'] ) ? trim( (string) $params['resource'] ) : '';
		if ( '' === $code || '' === $client_id || '' === $redirect_uri || '' === $verifier || '' === $resource ) {
			return self::oauth_error( 'invalid_request', 'code, client_id, redirect_uri, code_verifier and resource are required.', 400 );
		}
		if ( ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $verifier ) ) {
			return self::oauth_error( 'invalid_grant', 'The PKCE code verifier is invalid.', 400 );
		}

		$key = self::TRANSIENT_CODE . hash( 'sha256', $code );
		$record = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $record ) || (int) ( $record['expires_at'] ?? 0 ) <= time() ) {
			return self::oauth_error( 'invalid_grant', 'The authorization code is invalid or expired.', 400 );
		}
		if ( ! hash_equals( (string) $record['client_id'], $client_id ) || ! hash_equals( (string) $record['redirect_uri'], $redirect_uri ) || ! hash_equals( (string) $record['resource'], $resource ) ) {
			return self::oauth_error( 'invalid_grant', 'The authorization code binding does not match the token request.', 400 );
		}
		if ( ! hash_equals( self::pkce_challenge( $verifier ), (string) $record['code_challenge'] ) ) {
			return self::oauth_error( 'invalid_grant', 'PKCE verification failed.', 400 );
		}

		return self::issue_tokens( (int) $record['user_id'], $client_id, $resource, (string) $record['scope'] );
	}

	private static function exchange_refresh_token( array $params ) {
		$refresh = isset( $params['refresh_token'] ) ? trim( (string) $params['refresh_token'] ) : '';
		$client_id = isset( $params['client_id'] ) ? trim( (string) $params['client_id'] ) : '';
		$resource = isset( $params['resource'] ) ? trim( (string) $params['resource'] ) : '';
		if ( '' === $refresh || '' === $client_id || '' === $resource ) {
			return self::oauth_error( 'invalid_request', 'refresh_token, client_id and resource are required.', 400 );
		}

		$key = self::TRANSIENT_REFRESH . hash( 'sha256', $refresh );
		$record = get_transient( $key );
		if ( ! is_array( $record ) || (int) ( $record['expires_at'] ?? 0 ) <= time() ) {
			delete_transient( $key );
			return self::oauth_error( 'invalid_grant', 'The refresh token is invalid or expired.', 400 );
		}
		if ( ! hash_equals( (string) $record['client_id'], $client_id ) || ! hash_equals( (string) $record['resource'], $resource ) ) {
			return self::oauth_error( 'invalid_grant', 'The refresh token binding does not match the token request.', 400 );
		}

		delete_transient( $key );
		return self::issue_tokens( (int) $record['user_id'], $client_id, $resource, (string) $record['scope'] );
	}

	private static function issue_tokens( $user_id, $client_id, $resource, $scope ) {
		if ( ! hash_equals( self::mcp_resource(), (string) $resource ) || self::SCOPE !== (string) $scope ) {
			return self::oauth_error( 'invalid_target', 'The requested resource or scope is not valid for Chattanooga CMS Admin MCP.', 400 );
		}
		$user = get_user_by( 'id', (int) $user_id );
		if ( ! $user instanceof WP_User ) {
			return self::oauth_error( 'invalid_grant', 'The authorizing WordPress user no longer exists.', 400 );
		}
		wp_set_current_user( (int) $user->ID );
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::oauth_error( 'invalid_grant', 'The authorizing WordPress user no longer has administrator authority.', 400 );
		}

		$access = self::random_token( 32 );
		$refresh = self::random_token( 48 );
		if ( is_wp_error( $access ) || is_wp_error( $refresh ) ) {
			return self::oauth_error( 'server_error', 'OAuth tokens could not be generated.', 500 );
		}
		$now = time();
		$base = array(
			'user_id'   => (int) $user->ID,
			'client_id' => (string) $client_id,
			'resource'  => (string) $resource,
			'scope'     => self::SCOPE,
		);
		set_transient(
			self::TRANSIENT_ACCESS . hash( 'sha256', $access ),
			array_merge( $base, array( 'expires_at' => $now + self::ACCESS_TTL ) ),
			self::ACCESS_TTL
		);
		set_transient(
			self::TRANSIENT_REFRESH . hash( 'sha256', $refresh ),
			array_merge( $base, array( 'expires_at' => $now + self::REFRESH_TTL ) ),
			self::REFRESH_TTL
		);

		$response = new WP_REST_Response(
			array(
				'access_token'  => $access,
				'token_type'    => 'Bearer',
				'expires_in'    => self::ACCESS_TTL,
				'refresh_token' => $refresh,
				'scope'         => self::SCOPE,
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	private static function authorization_params( $source ) {
		$source = is_array( $source ) ? wp_unslash( $source ) : array();
		$params = array(
			'client_id'             => trim( (string) ( $source['client_id'] ?? '' ) ),
			'redirect_uri'          => trim( (string) ( $source['redirect_uri'] ?? '' ) ),
			'response_type'         => trim( (string) ( $source['response_type'] ?? '' ) ),
			'code_challenge'        => trim( (string) ( $source['code_challenge'] ?? '' ) ),
			'code_challenge_method' => trim( (string) ( $source['code_challenge_method'] ?? '' ) ),
			'resource'              => trim( (string) ( $source['resource'] ?? '' ) ),
			'scope'                 => trim( (string) ( $source['scope'] ?? self::SCOPE ) ),
			'state'                 => trim( (string) ( $source['state'] ?? '' ) ),
		);
		if ( '' === $params['client_id'] || '' === $params['redirect_uri'] || 'code' !== $params['response_type'] ) {
			return new WP_Error( 'cmsa_oauth_authorize_request', 'A valid client_id, redirect_uri and response_type=code are required.' );
		}
		if ( 'S256' !== $params['code_challenge_method'] || ! preg_match( '/^[A-Za-z0-9_-]{43,128}$/', $params['code_challenge'] ) ) {
			return new WP_Error( 'cmsa_oauth_pkce', 'PKCE using code_challenge_method=S256 is required.' );
		}
		if ( ! hash_equals( self::mcp_resource(), $params['resource'] ) ) {
			return new WP_Error( 'cmsa_oauth_resource', 'The OAuth resource must identify the Chattanooga CMS Admin MCP endpoint.' );
		}
		if ( self::SCOPE !== $params['scope'] ) {
			return new WP_Error( 'cmsa_oauth_scope', 'Only the mcp:admin scope is supported.' );
		}
		if ( strlen( $params['state'] ) > 2048 ) {
			return new WP_Error( 'cmsa_oauth_state', 'The OAuth state value is too long.' );
		}

		$client = self::resolve_client( $params['client_id'] );
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		if ( ! in_array( $params['redirect_uri'], (array) $client['redirect_uris'], true ) ) {
			return new WP_Error( 'cmsa_oauth_redirect', 'The redirect_uri is not registered for this OAuth client.' );
		}
		$params['client'] = $client;
		return $params;
	}

	private static function resolve_client( $client_id ) {
		if ( 0 === strpos( $client_id, 'https://' ) ) {
			return self::resolve_client_metadata_document( $client_id );
		}
		$client = get_transient( self::TRANSIENT_CLIENT . hash( 'sha256', $client_id ) );
		if ( ! is_array( $client ) || ! hash_equals( (string) ( $client['client_id'] ?? '' ), (string) $client_id ) ) {
			return new WP_Error( 'cmsa_oauth_client', 'The OAuth client is not registered.' );
		}
		return $client;
	}

	private static function resolve_client_metadata_document( $client_id ) {
		$parts = wp_parse_url( $client_id );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) || ! isset( $parts['path'] ) || '' === (string) $parts['path'] || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return new WP_Error( 'cmsa_oauth_cimd_url', 'The Client ID Metadata Document URL is invalid.' );
		}
		foreach ( explode( '/', (string) $parts['path'] ) as $segment ) {
			if ( '.' === $segment || '..' === $segment ) {
				return new WP_Error( 'cmsa_oauth_cimd_url', 'The Client ID Metadata Document URL must not contain dot path segments.' );
			}
		}

		$response = wp_safe_remote_get(
			$client_id,
			array(
				'timeout'     => 5,
				'redirection' => 0,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'cmsa_oauth_cimd_fetch', 'The Client ID Metadata Document could not be fetched.' );
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > 5120 ) {
			return new WP_Error( 'cmsa_oauth_cimd_size', 'The Client ID Metadata Document is too large.' );
		}
		$document = json_decode( $body, true );
		if ( ! is_array( $document ) || ! isset( $document['client_id'] ) || ! is_string( $document['client_id'] ) || ! hash_equals( $client_id, $document['client_id'] ) ) {
			return new WP_Error( 'cmsa_oauth_cimd_document', 'The Client ID Metadata Document is invalid or its client_id does not match its URL.' );
		}
		$client_name = isset( $document['client_name'] ) && is_string( $document['client_name'] ) ? sanitize_text_field( trim( $document['client_name'] ) ) : '';
		if ( '' === $client_name ) {
			return new WP_Error( 'cmsa_oauth_cimd_document', 'The Client ID Metadata Document must include a non-empty client_name.' );
		}
		if ( array_key_exists( 'client_secret', $document ) || array_key_exists( 'client_secret_expires_at', $document ) ) {
			return new WP_Error( 'cmsa_oauth_cimd_credentials', 'Client ID Metadata Documents must not contain shared client-secret material.' );
		}
		$redirects = self::validate_redirect_uris( $document['redirect_uris'] ?? null );
		if ( is_wp_error( $redirects ) ) {
			return $redirects;
		}

		$grant_types = isset( $document['grant_types'] ) ? array_values( (array) $document['grant_types'] ) : array( 'authorization_code' );
		if ( array_diff( $grant_types, array( 'authorization_code', 'refresh_token' ) ) || ! in_array( 'authorization_code', $grant_types, true ) ) {
			return new WP_Error( 'cmsa_oauth_cimd_grants', 'Only authorization_code and refresh_token grant types are supported for Client ID Metadata Documents.' );
		}
		$response_types = isset( $document['response_types'] ) ? array_values( (array) $document['response_types'] ) : array( 'code' );
		if ( array_diff( $response_types, array( 'code' ) ) || ! in_array( 'code', $response_types, true ) ) {
			return new WP_Error( 'cmsa_oauth_cimd_responses', 'Only the code response type is supported for Client ID Metadata Documents.' );
		}
		if ( isset( $document['token_endpoint_auth_method'] ) && 'none' !== (string) $document['token_endpoint_auth_method'] ) {
			return new WP_Error( 'cmsa_oauth_cimd_auth', 'Only public OAuth clients using token_endpoint_auth_method=none are supported.' );
		}
		return array(
			'client_id'                  => $client_id,
			'client_name'                => $client_name,
			'redirect_uris'              => $redirects,
			'grant_types'                => $grant_types,
			'response_types'             => $response_types,
			'token_endpoint_auth_method' => 'none',
			'application_type'           => 'web',
		);
	}

	private static function validate_redirect_uris( $value ) {
		if ( ! is_array( $value ) || empty( $value ) || count( $value ) > 10 ) {
			return new WP_Error( 'cmsa_oauth_redirects', 'One to ten redirect_uris are required.' );
		}
		$result = array();
		foreach ( $value as $uri ) {
			$uri = trim( (string) $uri );
			if ( '' === $uri || strlen( $uri ) > 2048 || false !== strpos( $uri, '#' ) ) {
				return new WP_Error( 'cmsa_oauth_redirect_uri', 'A redirect URI is invalid.' );
			}
			$parts = wp_parse_url( $uri );
			if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
				return new WP_Error( 'cmsa_oauth_redirect_uri', 'A redirect URI must be an absolute URI.' );
			}
			$scheme = strtolower( (string) $parts['scheme'] );
			$host = strtolower( trim( (string) $parts['host'], '[]' ) );
			$loopback = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
			if ( 'https' !== $scheme && ! ( 'http' === $scheme && $loopback ) ) {
				return new WP_Error( 'cmsa_oauth_redirect_uri', 'Redirect URIs must use HTTPS, except HTTP loopback redirects.' );
			}
			$result[] = $uri;
		}
		return array_values( array_unique( $result ) );
	}

	private static function protected_resource_metadata() {
		return array(
			'resource'                 => self::mcp_resource(),
			'authorization_servers'    => array( self::issuer() ),
			'scopes_supported'         => array( self::SCOPE ),
			'bearer_methods_supported' => array( 'header' ),
			'resource_name'            => 'Chattanooga CMS Admin MCP',
		);
	}

	private static function authorization_server_metadata() {
		return array(
			'issuer'                                => self::issuer(),
			'authorization_endpoint'                => admin_url( 'admin-post.php?action=cmsa_mcp_oauth_authorize' ),
			'token_endpoint'                        => rest_url( CUA_MCP_Server::REST_NAMESPACE . '/oauth/token' ),
			'registration_endpoint'                 => rest_url( CUA_MCP_Server::REST_NAMESPACE . '/oauth/register' ),
			'response_types_supported'              => array( 'code' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'token_endpoint_auth_methods_supported' => array( 'none' ),
			'scopes_supported'                      => array( self::SCOPE ),
			'client_id_metadata_document_supported' => true,
			'authorization_response_iss_parameter_supported' => true,
		);
	}

	private static function oauth_error( $code, $description, $status ) {
		$response = new WP_REST_Response(
			array(
				'error'             => (string) $code,
				'error_description' => (string) $description,
			),
			(int) $status
		);
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	private static function authorization_redirect( $redirect_uri, array $params ) {
		$params = array_filter(
			$params,
			static function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);
		$target = add_query_arg( $params, $redirect_uri );
		wp_redirect( $target, 302, 'Chattanooga CMS Admin OAuth' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	private static function random_token( $bytes ) {
		try {
			return self::base64url_encode( random_bytes( (int) $bytes ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'cmsa_oauth_random', 'Cryptographically secure random bytes are unavailable.' );
		}
	}

	private static function pkce_challenge( $verifier ) {
		return self::base64url_encode( hash( 'sha256', (string) $verifier, true ) );
	}

	private static function base64url_encode( $bytes ) {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	private static function bearer_token() {
		$header = '';
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$header = trim( (string) $_SERVER[ $key ] );
				break;
			}
		}
		if ( '' === $header && function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( is_array( $headers ) ) {
				foreach ( $headers as $name => $value ) {
					if ( 'authorization' === strtolower( (string) $name ) ) {
						$header = trim( (string) $value );
						break;
					}
			}
		}
		return preg_match( '/^Bearer\s+([^\s]+)$/i', $header, $matches ) ? (string) $matches[1] : '';
	}

	private static function request_targets_mcp() {
		$expected = '/' . CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE;
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		if ( $expected === $route ) {
			return true;
		}
		$query_route = isset( $_GET['rest_route'] ) ? wp_unslash( (string) $_GET['rest_route'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $expected === $query_route ) {
			return true;
		}
		$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$rest_path = (string) wp_parse_url( self::mcp_resource(), PHP_URL_PATH );
		return '' !== $path && '' !== $rest_path && untrailingslashit( $path ) === untrailingslashit( $rest_path );
	}

	private static function request_path() {
		$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		return is_string( $path ) ? untrailingslashit( $path ) : '';
	}

	private static function metadata_url_path( $url ) {
		$path = wp_parse_url( (string) $url, PHP_URL_PATH );
		return is_string( $path ) ? untrailingslashit( $path ) : '';
	}

	private static function current_request_url() {
		$scheme = is_ssl() ? 'https' : 'http';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? preg_replace( '/[^A-Za-z0-9.\-:\[\]]/', '', (string) $_SERVER['HTTP_HOST'] ) : '';
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
		return $scheme . '://' . $host . $uri;
	}

	private static function issuer() {
		return untrailingslashit( home_url( '/' ) );
	}

	private static function protected_resource_metadata_url() {
		return self::well_known_url( self::mcp_resource(), 'oauth-protected-resource' );
	}

	private static function authorization_server_metadata_url() {
		return self::well_known_url( self::issuer(), 'oauth-authorization-server' );
	}

	private static function well_known_url( $identifier, $suffix ) {
		$parts = wp_parse_url( (string) $identifier );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || isset( $parts['fragment'] ) ) {
			return '';
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host = (string) $parts['host'];
		if ( false !== strpos( $host, ':' ) && '[' !== substr( $host, 0, 1 ) ) {
			$host = '[' . $host . ']';
		}

		$url = $scheme . '://' . $host;
		if ( isset( $parts['port'] ) ) {
			$url .= ':' . (int) $parts['port'];
		}
		$url .= '/.well-known/' . trim( (string) $suffix, '/' );
		if ( isset( $parts['path'] ) && '' !== (string) $parts['path'] && '/' !== (string) $parts['path'] ) {
			$url .= '/' . ltrim( (string) $parts['path'], '/' );
		}
		if ( isset( $parts['query'] ) && '' !== (string) $parts['query'] ) {
			$url .= '?' . (string) $parts['query'];
		}
		return $url;
	}

	private static function mcp_resource() {
		return untrailingslashit( rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE ) );
	}
}
