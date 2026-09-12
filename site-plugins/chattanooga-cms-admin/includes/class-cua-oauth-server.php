<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OAuth 2.1 authorization server for remote MCP clients.
 *
 * The implementation deliberately supports public clients only. Every grant uses
 * authorization code + PKCE (S256); no reusable client secret is stored or shown.
 */
final class CUA_OAuth_Server {
	const REST_NAMESPACE = 'chattanooga-cms-admin/v1';
	const SCOPE          = 'mcp:admin';
	const CLIENT_OPTION  = 'cua_oauth_clients';
	const CODE_TTL       = 300;
	const ACCESS_TTL     = 3600;
	const REFRESH_TTL    = 2592000;

	public static function bootstrap() {
		add_action( 'parse_request', array( __CLASS__, 'serve_well_known_metadata' ), 0 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'admin_post_cua_oauth_authorize', array( __CLASS__, 'authorize' ) );
		add_action( 'admin_post_nopriv_cua_oauth_authorize', array( __CLASS__, 'authorize' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/oauth/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'register_client' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/oauth/token',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'token' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function serve_well_known_metadata() {
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		$resource_path = wp_parse_url( home_url( '/.well-known/oauth-protected-resource' ), PHP_URL_PATH );
		$server_path = wp_parse_url( home_url( '/.well-known/oauth-authorization-server' ), PHP_URL_PATH );
		if ( untrailingslashit( (string) $resource_path ) === untrailingslashit( (string) $path ) ) {
			self::send_json( self::protected_resource_metadata() );
		}
		if ( untrailingslashit( (string) $server_path ) === untrailingslashit( (string) $path ) ) {
			self::send_json( self::authorization_server_metadata() );
		}
	}

	public static function protected_resource_metadata() {
		return array(
			'resource'              => rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE ),
			'authorization_servers' => array( home_url( '/' ) ),
			'scopes_supported'      => array( self::SCOPE ),
			'bearer_methods_supported' => array( 'header' ),
		);
	}

	public static function authorization_server_metadata() {
		return array(
			'issuer'                                => home_url( '/' ),
			'authorization_endpoint'                => admin_url( 'admin-post.php?action=cua_oauth_authorize' ),
			'token_endpoint'                        => rest_url( self::REST_NAMESPACE . '/oauth/token' ),
			'registration_endpoint'                 => rest_url( self::REST_NAMESPACE . '/oauth/register' ),
			'response_types_supported'               => array( 'code' ),
			'grant_types_supported'                  => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'       => array( 'S256' ),
			'token_endpoint_auth_methods_supported'  => array( 'none' ),
			'scopes_supported'                      => array( self::SCOPE ),
		);
	}

	public static function register_client( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return self::oauth_error( 'invalid_client_metadata', 'A JSON client metadata object is required.', 400 );
		}

		$redirect_uris = self::validated_redirect_uris( isset( $body['redirect_uris'] ) ? $body['redirect_uris'] : null );
		if ( is_wp_error( $redirect_uris ) ) {
			return self::oauth_error( 'invalid_redirect_uri', $redirect_uris->get_error_message(), 400 );
		}
		if ( isset( $body['token_endpoint_auth_method'] ) && 'none' !== $body['token_endpoint_auth_method'] ) {
			return self::oauth_error( 'invalid_client_metadata', 'Only public clients using token_endpoint_auth_method none are supported.', 400 );
		}

		$client_id = 'cmsa_' . self::random_token( 24 );
		$clients = get_option( self::CLIENT_OPTION, array() );
		$clients = is_array( $clients ) ? $clients : array();
		$clients[ self::digest( $client_id ) ] = array(
			'client_id'     => $client_id,
			'redirect_uris' => $redirect_uris,
			'client_name'   => isset( $body['client_name'] ) ? sanitize_text_field( (string) $body['client_name'] ) : 'ChatGPT',
			'created_at'    => time(),
		);
		if ( count( $clients ) > 100 ) {
			uasort(
				$clients,
				static function ( $left, $right ) {
					return (int) ( $right['created_at'] ?? 0 ) <=> (int) ( $left['created_at'] ?? 0 );
				}
			);
			$clients = array_slice( $clients, 0, 100, true );
		}
		update_option( self::CLIENT_OPTION, $clients, false );

		return self::no_store_response(
			array(
				'client_id'                  => $client_id,
				'client_id_issued_at'        => time(),
				'redirect_uris'              => $redirect_uris,
				'token_endpoint_auth_method' => 'none',
				'grant_types'                => array( 'authorization_code', 'refresh_token' ),
				'response_types'             => array( 'code' ),
			),
			201
		);
	}

	public static function authorize() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator authority is required.', 'chattanooga-cms-admin' ), '', array( 'response' => 403 ) );
		}

		$params = 'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET' ) ? $_POST : $_GET;
		$params = wp_unslash( $params );
		$client_id = isset( $params['client_id'] ) ? sanitize_text_field( (string) $params['client_id'] ) : '';
		$redirect_uri = isset( $params['redirect_uri'] ) ? esc_url_raw( (string) $params['redirect_uri'] ) : '';
		$state = isset( $params['state'] ) ? (string) $params['state'] : '';
		$response_type = isset( $params['response_type'] ) ? (string) $params['response_type'] : '';
		$scope = isset( $params['scope'] ) ? trim( (string) $params['scope'] ) : '';
		$challenge = isset( $params['code_challenge'] ) ? (string) $params['code_challenge'] : '';
		$challenge_method = isset( $params['code_challenge_method'] ) ? (string) $params['code_challenge_method'] : '';
		if ( strlen( $state ) > 2048 || preg_match( '/[\r\n]/', $state ) ) {
			wp_die( esc_html__( 'The OAuth state value is invalid.', 'chattanooga-cms-admin' ), '', array( 'response' => 400 ) );
		}

		$client = self::resolve_client( $client_id, $redirect_uri );
		if ( is_wp_error( $client ) ) {
			wp_die( esc_html( $client->get_error_message() ), '', array( 'response' => 400 ) );
		}
		if ( 'code' !== $response_type || 'S256' !== $challenge_method || ! preg_match( '/^[A-Za-z0-9_-]{43,128}$/', $challenge ) ) {
			self::authorization_redirect_error( $redirect_uri, 'invalid_request', 'Authorization code flow with PKCE S256 is required.', $state );
		}
		if ( ! self::scope_is_valid( $scope ) ) {
			self::authorization_redirect_error( $redirect_uri, 'invalid_scope', 'The requested scope is not supported.', $state );
		}

		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET' ) ) {
			self::render_consent( $client, $params );
		}

		check_admin_referer( 'cua_oauth_authorize' );
		if ( ! isset( $params['approve'] ) || '1' !== (string) $params['approve'] ) {
			self::authorization_redirect_error( $redirect_uri, 'access_denied', 'The administrator denied access.', $state );
		}

		$code = self::random_token( 32 );
		set_transient(
			self::transient_key( 'code', $code ),
			array(
				'client_id'      => $client_id,
				'redirect_uri'   => $redirect_uri,
				'user_id'        => get_current_user_id(),
				'scope'          => self::SCOPE,
				'code_challenge' => $challenge,
			),
			self::CODE_TTL
		);
		$url = add_query_arg( array_filter( array( 'code' => $code, 'state' => $state ), 'strlen' ), $redirect_uri );
		wp_redirect( $url );
		exit;
	}

	public static function token( WP_REST_Request $request ) {
		$grant_type = trim( (string) $request->get_param( 'grant_type' ) );
		if ( 'authorization_code' === $grant_type ) {
			return self::exchange_authorization_code( $request );
		}
		if ( 'refresh_token' === $grant_type ) {
			return self::exchange_refresh_token( $request );
		}
		return self::oauth_error( 'unsupported_grant_type', 'Supported grant types are authorization_code and refresh_token.', 400 );
	}

	public static function authenticate_bearer( WP_REST_Request $request ) {
		$authorization = trim( (string) $request->get_header( 'authorization' ) );
		if ( ! preg_match( '/^Bearer\s+([^\s]+)$/i', $authorization, $matches ) ) {
			return new WP_Error( 'cmsa_oauth_token_missing', 'A Bearer access token is required.', array( 'status' => 401 ) );
		}
		$record = get_transient( self::transient_key( 'access', $matches[1] ) );
		if ( ! is_array( $record ) || empty( $record['user_id'] ) || self::SCOPE !== ( $record['scope'] ?? '' ) ) {
			return new WP_Error( 'cmsa_oauth_token_invalid', 'The Bearer access token is invalid or expired.', array( 'status' => 401 ) );
		}
		$user = get_user_by( 'id', (int) $record['user_id'] );
		if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'cmsa_oauth_user_forbidden', 'The authorizing administrator no longer has the required authority.', array( 'status' => 403 ) );
		}
		wp_set_current_user( $user->ID );
		return true;
	}

	public static function resource_challenge() {
		return 'Bearer resource_metadata="' . esc_url_raw( home_url( '/.well-known/oauth-protected-resource' ) ) . '", scope="' . self::SCOPE . '"';
	}

	private static function exchange_authorization_code( WP_REST_Request $request ) {
		$code = trim( (string) $request->get_param( 'code' ) );
		$record = get_transient( self::transient_key( 'code', $code ) );
		delete_transient( self::transient_key( 'code', $code ) );
		if ( ! is_array( $record ) ) {
			return self::oauth_error( 'invalid_grant', 'The authorization code is invalid, expired, or already used.', 400 );
		}

		$client_id = trim( (string) $request->get_param( 'client_id' ) );
		$redirect_uri = esc_url_raw( (string) $request->get_param( 'redirect_uri' ) );
		$verifier = trim( (string) $request->get_param( 'code_verifier' ) );
		if ( ! hash_equals( (string) $record['client_id'], $client_id ) || ! hash_equals( (string) $record['redirect_uri'], $redirect_uri ) ) {
			return self::oauth_error( 'invalid_grant', 'The authorization code does not belong to this client or redirect URI.', 400 );
		}
		if ( ! preg_match( '/^[A-Za-z0-9._~-]{43,128}$/', $verifier ) || ! hash_equals( (string) $record['code_challenge'], self::pkce_challenge( $verifier ) ) ) {
			return self::oauth_error( 'invalid_grant', 'PKCE verification failed.', 400 );
		}
		return self::issue_tokens( $record );
	}

	private static function exchange_refresh_token( WP_REST_Request $request ) {
		$refresh = trim( (string) $request->get_param( 'refresh_token' ) );
		$key = self::transient_key( 'refresh', $refresh );
		$record = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $record ) ) {
			return self::oauth_error( 'invalid_grant', 'The refresh token is invalid, expired, or already used.', 400 );
		}
		$client_id = trim( (string) $request->get_param( 'client_id' ) );
		if ( ! hash_equals( (string) $record['client_id'], $client_id ) ) {
			return self::oauth_error( 'invalid_grant', 'The refresh token does not belong to this client.', 400 );
		}
		return self::issue_tokens( $record );
	}

	private static function issue_tokens( array $record ) {
		$access = self::random_token( 32 );
		$refresh = self::random_token( 32 );
		$stored = array(
			'client_id' => (string) $record['client_id'],
			'user_id'   => (int) $record['user_id'],
			'scope'     => self::SCOPE,
		);
		set_transient( self::transient_key( 'access', $access ), $stored, self::ACCESS_TTL );
		set_transient( self::transient_key( 'refresh', $refresh ), $stored, self::REFRESH_TTL );
		return self::no_store_response(
			array(
				'access_token'  => $access,
				'token_type'    => 'Bearer',
				'expires_in'    => self::ACCESS_TTL,
				'refresh_token' => $refresh,
				'scope'         => self::SCOPE,
			)
		);
	}

	private static function resolve_client( $client_id, $redirect_uri ) {
		if ( '' === $client_id || '' === $redirect_uri ) {
			return new WP_Error( 'cmsa_oauth_client_missing', 'client_id and redirect_uri are required.' );
		}
		$clients = get_option( self::CLIENT_OPTION, array() );
		$key = self::digest( $client_id );
		if ( is_array( $clients ) && isset( $clients[ $key ] ) && in_array( $redirect_uri, $clients[ $key ]['redirect_uris'], true ) ) {
			return $clients[ $key ];
		}

		$parts = wp_parse_url( $client_id );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || 'chatgpt.com' !== strtolower( (string) ( $parts['host'] ?? '' ) ) ) {
			return new WP_Error( 'cmsa_oauth_client_invalid', 'The OAuth client is not registered.' );
		}
		$response = wp_safe_remote_get( $client_id, array( 'timeout' => 8, 'redirection' => 0 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'cmsa_oauth_client_unavailable', 'The ChatGPT client metadata document could not be verified.' );
		}
		$metadata = json_decode( wp_remote_retrieve_body( $response ), true );
		$uris = self::validated_redirect_uris( is_array( $metadata ) && isset( $metadata['redirect_uris'] ) ? $metadata['redirect_uris'] : null );
		if ( is_wp_error( $uris ) || ! in_array( $redirect_uri, $uris, true ) ) {
			return new WP_Error( 'cmsa_oauth_redirect_invalid', 'The redirect URI is not registered by the ChatGPT client.' );
		}
		return array( 'client_id' => $client_id, 'redirect_uris' => $uris, 'client_name' => 'ChatGPT' );
	}

	private static function validated_redirect_uris( $uris ) {
		if ( ! is_array( $uris ) || empty( $uris ) || count( $uris ) > 5 ) {
			return new WP_Error( 'cmsa_oauth_redirects_invalid', 'One to five HTTPS redirect URIs are required.' );
		}
		$valid = array();
		foreach ( $uris as $uri ) {
			$uri = esc_url_raw( (string) $uri );
			$parts = wp_parse_url( $uri );
			if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) || isset( $parts['fragment'] ) ) {
				return new WP_Error( 'cmsa_oauth_redirect_invalid', 'Every redirect URI must be an absolute HTTPS URL without a fragment.' );
			}
			$valid[] = $uri;
		}
		return array_values( array_unique( $valid ) );
	}

	private static function render_consent( array $client, array $params ) {
		$hidden = '';
		foreach ( array( 'client_id', 'redirect_uri', 'response_type', 'scope', 'state', 'code_challenge', 'code_challenge_method' ) as $name ) {
			$hidden .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( isset( $params[ $name ] ) ? (string) $params[ $name ] : '' ) . '">';
		}
		$client_name = isset( $client['client_name'] ) && '' !== $client['client_name'] ? $client['client_name'] : 'ChatGPT';
		$title = esc_html__( 'Connect Chattanooga CMS Admin', 'chattanooga-cms-admin' );
		$message = sprintf( esc_html__( '%s is requesting administrator access to the Chattanooga CMS tools. Actions will run as your current WordPress administrator account.', 'chattanooga-cms-admin' ), esc_html( $client_name ) );
		$nonce = wp_nonce_field( 'cua_oauth_authorize', '_wpnonce', true, false );
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . esc_html( $title ) . '</title></head><body style="font:16px system-ui;max-width:640px;margin:8vh auto;padding:24px;line-height:1.5"><h1>' . esc_html( $title ) . '</h1><p>' . $message . '</p><p><strong>' . esc_html__( 'Permission:', 'chattanooga-cms-admin' ) . '</strong> ' . esc_html__( 'Inspect and administer this WordPress site through the installed CMS abilities.', 'chattanooga-cms-admin' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php?action=cua_oauth_authorize' ) ) . '">' . $hidden . $nonce . '<button type="submit" name="approve" value="1" style="padding:10px 16px;margin-right:8px">' . esc_html__( 'Allow', 'chattanooga-cms-admin' ) . '</button><button type="submit" name="approve" value="0" style="padding:10px 16px">' . esc_html__( 'Deny', 'chattanooga-cms-admin' ) . '</button></form></body></html>';
		exit;
	}

	private static function authorization_redirect_error( $redirect_uri, $error, $description, $state ) {
		$url = add_query_arg( array_filter( array( 'error' => $error, 'error_description' => $description, 'state' => $state ), 'strlen' ), $redirect_uri );
		wp_redirect( $url );
		exit;
	}

	private static function scope_is_valid( $scope ) {
		$scopes = preg_split( '/\s+/', trim( (string) $scope ) );
		return array( self::SCOPE ) === $scopes;
	}

	private static function pkce_challenge( $verifier ) {
		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	}

	private static function random_token( $bytes ) {
		return rtrim( strtr( base64_encode( random_bytes( (int) $bytes ) ), '+/', '-_' ), '=' );
	}

	private static function digest( $value ) {
		return hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) );
	}

	private static function transient_key( $type, $token ) {
		return 'cua_oauth_' . $type . '_' . self::digest( $token );
	}

	private static function oauth_error( $code, $description, $status ) {
		return self::no_store_response( array( 'error' => $code, 'error_description' => $description ), $status );
	}

	private static function no_store_response( array $body, $status = 200 ) {
		$response = new WP_REST_Response( $body, (int) $status );
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	private static function send_json( array $body ) {
		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( $body, JSON_UNESCAPED_SLASHES );
		exit;
	}
}
