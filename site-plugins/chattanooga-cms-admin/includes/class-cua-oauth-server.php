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
	const OFFLINE_SCOPE  = 'offline_access';
	const CLIENT_OPTION  = 'cua_oauth_clients';
	const DIAGNOSTIC_OPTION = 'cua_oauth_last_client_metadata_check';
	const REWRITE_VERSION_OPTION = 'cua_oauth_rewrite_version';
	const CODE_TTL       = 300;
	const ACCESS_TTL     = 3600;
	const REFRESH_TTL    = 2592000;

	public static function bootstrap() {
		add_action( 'parse_request', array( __CLASS__, 'serve_well_known_metadata' ), 0 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'mod_rewrite_rules', array( __CLASS__, 'inject_well_known_rewrite_rules' ) );
		add_action( 'init', array( __CLASS__, 'maybe_refresh_rewrite_rules' ), 99 );
		add_action( 'admin_post_cua_oauth_authorize', array( __CLASS__, 'authorize' ) );
		add_action( 'admin_post_nopriv_cua_oauth_authorize', array( __CLASS__, 'authorize' ) );
	}

	public static function is_enabled() {
		return ! class_exists( 'CUA_MCP_Settings_Page' ) || CUA_MCP_Settings_Page::is_enabled();
	}

	public static function is_oauth_enabled() {
		// OAuth discovery and token issuance stay available whenever the MCP endpoint
		// is enabled. Manual bearer authorization is an optional compatibility
		// fallback for clients that can supply a static Authorization header; it
		// must not disable the OAuth path required by ChatGPT.
		return self::is_enabled();
	}

	public static function register_routes() {
		if ( ! self::is_oauth_enabled() ) {
			return;
		}

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
		register_rest_route(
			self::REST_NAMESPACE,
			'/oauth/protected-resource',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_protected_resource_metadata' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/oauth/authorization-server',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_authorization_server_metadata' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/oauth/diagnostics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'diagnostics' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function rest_protected_resource_metadata( WP_REST_Request $request ) {
		return self::no_store_response( self::protected_resource_metadata() );
	}

	public static function rest_authorization_server_metadata( WP_REST_Request $request ) {
		return self::no_store_response( self::authorization_server_metadata() );
	}

	public static function diagnostics( WP_REST_Request $request ) {
		$targets = array(
			'protected_resource_rest'       => self::protected_resource_metadata_url(),
			'protected_resource_well_known' => self::root_protected_resource_metadata_url(),
			'protected_resource_path'       => self::path_protected_resource_metadata_url(),
			'authorization_server_rest'     => self::authorization_server_metadata_url(),
			'authorization_server_well_known' => self::authorization_server_well_known_url(),
		);
		$probes = array();
		foreach ( $targets as $name => $url ) {
			$probes[ $name ] = self::probe_url( $url );
		}
		$last_client_check = get_option( self::DIAGNOSTIC_OPTION, array() );
		if ( ! is_array( $last_client_check ) ) {
			$last_client_check = array();
		}
		return self::no_store_response(
			array(
				'pluginVersion'           => defined( 'CUA_VERSION' ) ? CUA_VERSION : 'unknown',
				'oauthEnabled'            => self::is_oauth_enabled(),
				'resource'                => self::canonical_resource(),
				'issuer'                  => self::canonical_issuer(),
				'challengeMetadataUrl'    => self::protected_resource_metadata_url(),
				'probes'                  => $probes,
				'lastClientMetadataCheck' => array_intersect_key( $last_client_check, array_flip( array( 'outcome', 'http_status', 'checked_at' ) ) ),
			)
		);
	}

	public static function inject_well_known_rewrite_rules( $rules ) {
		if ( ! self::is_oauth_enabled() ) {
			return $rules;
		}
		$resource_path = ltrim( (string) wp_parse_url( self::canonical_resource(), PHP_URL_PATH ), '/' );
		$resource_pattern = preg_quote( $resource_path, '#' );
		$prefix = "<IfModule mod_rewrite.c>\nRewriteEngine On\n";
		$prefix .= "RewriteRule ^\\.well-known/oauth-protected-resource/?$ index.php [QSA,L]\n";
		if ( '' !== $resource_pattern ) {
			$prefix .= 'RewriteRule ^\\.well-known/oauth-protected-resource/' . $resource_pattern . "/?$ index.php [QSA,L]\n";
		}
		$prefix .= "RewriteRule ^\\.well-known/oauth-authorization-server/?$ index.php [QSA,L]\n</IfModule>\n";
		return $prefix . $rules;
	}

	public static function maybe_refresh_rewrite_rules() {
		if ( ! defined( 'CUA_VERSION' ) || CUA_VERSION === get_option( self::REWRITE_VERSION_OPTION, '' ) ) {
			return;
		}
		flush_rewrite_rules( true );
		update_option( self::REWRITE_VERSION_OPTION, CUA_VERSION, false );
	}

	public static function serve_well_known_metadata() {
		if ( ! self::is_oauth_enabled() ) {
			return;
		}

		$path = untrailingslashit( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ) );
		$resource_paths = array(
			untrailingslashit( (string) wp_parse_url( self::root_protected_resource_metadata_url(), PHP_URL_PATH ) ),
			untrailingslashit( (string) wp_parse_url( self::path_protected_resource_metadata_url(), PHP_URL_PATH ) ),
		);
		$server_path = untrailingslashit( (string) wp_parse_url( self::authorization_server_well_known_url(), PHP_URL_PATH ) );
		if ( in_array( $path, $resource_paths, true ) ) {
			self::send_json( self::protected_resource_metadata() );
		}
		if ( $server_path === $path ) {
			self::send_json( self::authorization_server_metadata() );
		}
	}

	public static function protected_resource_metadata_url() {
		return rest_url( self::REST_NAMESPACE . '/oauth/protected-resource' );
	}

	public static function authorization_server_metadata_url() {
		return rest_url( self::REST_NAMESPACE . '/oauth/authorization-server' );
	}

	public static function root_protected_resource_metadata_url() {
		return home_url( '/.well-known/oauth-protected-resource' );
	}

	public static function path_protected_resource_metadata_url() {
		$resource_path = (string) wp_parse_url( self::canonical_resource(), PHP_URL_PATH );
		return home_url( '/.well-known/oauth-protected-resource' . $resource_path );
	}

	public static function authorization_server_well_known_url() {
		return home_url( '/.well-known/oauth-authorization-server' );
	}

	public static function protected_resource_metadata() {
		return array(
			'resource'              => self::canonical_resource(),
			'authorization_servers' => array( self::canonical_issuer() ),
			'scopes_supported'      => array( self::SCOPE ),
			'bearer_methods_supported' => array( 'header' ),
		);
	}

	public static function authorization_server_metadata() {
		return array(
			'issuer'                                => self::canonical_issuer(),
			'authorization_response_iss_parameter_supported' => true,
			'authorization_endpoint'                => admin_url( 'admin-post.php?action=cua_oauth_authorize' ),
			'token_endpoint'                        => rest_url( self::REST_NAMESPACE . '/oauth/token' ),
			'registration_endpoint'                 => rest_url( self::REST_NAMESPACE . '/oauth/register' ),
			'response_types_supported'               => array( 'code' ),
			'grant_types_supported'                  => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'       => array( 'S256' ),
			'token_endpoint_auth_methods_supported'  => array( 'none' ),
			'scopes_supported'                      => array( self::SCOPE, self::OFFLINE_SCOPE ),
			'client_id_metadata_document_supported' => true,
		);
	}

	public static function register_client( WP_REST_Request $request ) {
		if ( ! self::is_oauth_enabled() ) {
			return self::oauth_error( 'authorization_mode_disabled', 'Automatic OAuth authorization is disabled.', 404 );
		}
		if ( ! self::request_has_json_content_type( $request ) ) {
			return self::oauth_error( 'invalid_client_metadata', 'Client registration requires Content-Type: application/json.', 415 );
		}
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
		if ( ! self::is_oauth_enabled() ) {
			wp_die( esc_html__( 'The Chattanooga MCP endpoint is disabled.', 'chattanooga-cms-admin' ), '', array( 'response' => 404 ) );
		}
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
		$resource = isset( $params['resource'] ) ? esc_url_raw( (string) $params['resource'] ) : '';
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
		$scope = self::normalize_scope( $scope );
		if ( is_wp_error( $scope ) ) {
			self::authorization_redirect_error( $redirect_uri, 'invalid_scope', $scope->get_error_message(), $state );
		}
		if ( ! hash_equals( self::canonical_resource(), $resource ) ) {
			self::authorization_redirect_error( $redirect_uri, 'invalid_target', 'The requested resource is not supported.', $state );
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
				'scope'          => $scope,
				'resource'       => $resource,
				'code_challenge' => $challenge,
			),
			self::CODE_TTL
		);
		$url = add_query_arg( array_filter( array( 'code' => $code, 'state' => $state, 'resource' => $resource, 'iss' => self::canonical_issuer() ), 'strlen' ), $redirect_uri );
		wp_redirect( $url );
		exit;
	}

	public static function token( WP_REST_Request $request ) {
		if ( ! self::is_oauth_enabled() ) {
			return self::oauth_error( 'authorization_mode_disabled', 'Automatic OAuth authorization is disabled.', 404 );
		}
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
		if ( class_exists( 'CUA_MCP_Settings_Page' ) && CUA_MCP_Settings_Page::is_manual_auth() ) {
			$user_id = CUA_MCP_Settings_Page::authenticate_manual_token( $matches[1] );
			if ( $user_id ) {
				$user = get_user_by( 'id', (int) $user_id );
				if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
					return new WP_Error( 'cmsa_oauth_user_forbidden', 'The authorizing administrator no longer has the required authority.', array( 'status' => 403 ) );
				}
				wp_set_current_user( $user->ID );
				return true;
			}
			// A non-matching manual token may still be a valid OAuth access token.
			// Continue into OAuth validation instead of making manual mode exclusive.
		}

		$record = get_transient( self::transient_key( 'access', $matches[1] ) );
		if ( ! is_array( $record ) || empty( $record['user_id'] ) || self::canonical_resource() !== ( $record['resource'] ?? '' ) ) {
			return new WP_Error( 'cmsa_oauth_token_invalid', 'The Bearer access token is invalid or expired.', array( 'status' => 401 ) );
		}
		if ( ! self::scope_contains( $record['scope'] ?? '', self::SCOPE ) ) {
			return new WP_Error( 'cmsa_oauth_insufficient_scope', 'The Bearer access token does not grant the required administrator scope.', array( 'status' => 403 ) );
		}
		$user = get_user_by( 'id', (int) $record['user_id'] );
		if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'cmsa_oauth_user_forbidden', 'The authorizing administrator no longer has the required authority.', array( 'status' => 403 ) );
		}
		wp_set_current_user( $user->ID );
		return true;
	}

	public static function resource_challenge( $error_code = '' ) {
		$challenge = 'Bearer resource_metadata="' . esc_url_raw( self::protected_resource_metadata_url() ) . '", scope="' . self::SCOPE . '"';
		if ( 'cmsa_oauth_token_invalid' === $error_code ) {
			$challenge .= ', error="invalid_token", error_description="The access token is expired, revoked, or bound to an obsolete resource."';
		} elseif ( 'cmsa_oauth_insufficient_scope' === $error_code ) {
			$challenge .= ', error="insufficient_scope", error_description="The access token does not grant the required administrator scope."';
		}
		return $challenge;
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
		$resource = esc_url_raw( (string) $request->get_param( 'resource' ) );
		if ( ! hash_equals( (string) $record['client_id'], $client_id ) || ! hash_equals( (string) $record['redirect_uri'], $redirect_uri ) ) {
			return self::oauth_error( 'invalid_grant', 'The authorization code does not belong to this client or redirect URI.', 400 );
		}
		if ( ! preg_match( '/^[A-Za-z0-9._~-]{43,128}$/', $verifier ) || ! hash_equals( (string) $record['code_challenge'], self::pkce_challenge( $verifier ) ) ) {
			return self::oauth_error( 'invalid_grant', 'PKCE verification failed.', 400 );
		}
		if ( ! isset( $record['resource'] ) || ! hash_equals( (string) $record['resource'], $resource ) || ! hash_equals( self::canonical_resource(), $resource ) ) {
			return self::oauth_error( 'invalid_target', 'The resource does not match the authorization request.', 400 );
		}
		return self::issue_tokens( $record );
	}

	private static function exchange_refresh_token( WP_REST_Request $request ) {
		$refresh = trim( (string) $request->get_param( 'refresh_token' ) );
		$key = self::transient_key( 'refresh', $refresh );
		$record = get_transient( $key );
		if ( ! is_array( $record ) ) {
			return self::oauth_error( 'invalid_grant', 'The refresh token is invalid, expired, or already used.', 400 );
		}
		$client_id = trim( (string) $request->get_param( 'client_id' ) );
		$resource = esc_url_raw( (string) $request->get_param( 'resource' ) );
		if ( ! hash_equals( (string) $record['client_id'], $client_id ) ) {
			return self::oauth_error( 'invalid_grant', 'The refresh token does not belong to this client.', 400 );
		}
		if ( ! isset( $record['resource'] ) || ! hash_equals( (string) $record['resource'], $resource ) || ! hash_equals( self::canonical_resource(), $resource ) ) {
			return self::oauth_error( 'invalid_target', 'The resource does not match the refresh token.', 400 );
		}

		$response = self::issue_tokens( $record );
		if ( self::scope_contains( $record['scope'] ?? '', self::OFFLINE_SCOPE ) ) {
			// Modern offline-access grants rotate refresh tokens. A pre-1.2.3
			// refresh token has no offline_access scope; preserve that existing
			// token until its original TTL expires instead of consuming it
			// without returning a replacement.
			delete_transient( $key );
		}
		return $response;
	}

	private static function issue_tokens( array $record ) {
		$access = self::random_token( 32 );
		$refresh = self::random_token( 32 );
		$granted_scope = isset( $record['scope'] ) ? (string) $record['scope'] : self::SCOPE;
		$stored = array(
			'client_id' => (string) $record['client_id'],
			'user_id'   => (int) $record['user_id'],
			'scope'     => $granted_scope,
			'resource'  => (string) $record['resource'],
		);
		set_transient( self::transient_key( 'access', $access ), $stored, self::ACCESS_TTL );
		$body = array(
			'access_token' => $access,
			'token_type'   => 'Bearer',
			'expires_in'   => self::ACCESS_TTL,
			'scope'        => $granted_scope,
			'resource'     => (string) $record['resource'],
		);
		if ( self::scope_contains( $granted_scope, self::OFFLINE_SCOPE ) ) {
			$body['refresh_token'] = $refresh;
			set_transient( self::transient_key( 'refresh', $refresh ), $stored, self::REFRESH_TTL );
		}
		return self::no_store_response( $body );
	}

	private static function resolve_client( $client_id, $redirect_uri ) {
		if ( '' === $client_id || '' === $redirect_uri ) {
			return new WP_Error( 'cmsa_oauth_client_missing', 'client_id and redirect_uri are required.' );
		}
		$clients = get_option( self::CLIENT_OPTION, array() );
		$key = self::digest( $client_id );
		if ( is_array( $clients ) && isset( $clients[ $key ] ) && in_array( $redirect_uri, $clients[ $key ]['redirect_uris'], true ) ) {
			self::record_client_metadata_check( 'registered_client', 0 );
			return $clients[ $key ];
		}

		if ( ! self::is_client_metadata_url( $client_id ) ) {
			self::record_client_metadata_check( 'invalid_client_id', 0 );
			return new WP_Error( 'cmsa_oauth_client_invalid', 'The OAuth client is not registered and its client metadata URL is invalid.' );
		}
		$response = wp_safe_remote_get(
			$client_id,
			array(
				'timeout'            => 8,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
				'headers'            => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			self::record_client_metadata_check( 'transport_error', 0 );
			return new WP_Error( 'cmsa_oauth_client_unavailable', 'The OAuth client metadata document could not be verified.' );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			self::record_client_metadata_check( 'http_error', $status );
			return new WP_Error( 'cmsa_oauth_client_unavailable', 'The OAuth client metadata document could not be verified.' );
		}
		$metadata = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $metadata ) ) {
			self::record_client_metadata_check( 'invalid_json', $status );
			return new WP_Error( 'cmsa_oauth_client_metadata_invalid', 'The OAuth client metadata document is not valid JSON.' );
		}
		if ( isset( $metadata['client_id'] ) && ! hash_equals( $client_id, esc_url_raw( (string) $metadata['client_id'] ) ) ) {
			self::record_client_metadata_check( 'client_id_mismatch', $status );
			return new WP_Error( 'cmsa_oauth_client_metadata_invalid', 'The OAuth client metadata document identifies a different client.' );
		}
		$uris = self::validated_redirect_uris( isset( $metadata['redirect_uris'] ) ? $metadata['redirect_uris'] : null );
		if ( is_wp_error( $uris ) || ! in_array( $redirect_uri, $uris, true ) ) {
			self::record_client_metadata_check( 'redirect_mismatch', $status );
			return new WP_Error( 'cmsa_oauth_redirect_invalid', 'The redirect URI is not registered by the OAuth client.' );
		}
		self::record_client_metadata_check( 'ok', $status );
		return array(
			'client_id'     => $client_id,
			'redirect_uris' => $uris,
			'client_name'   => isset( $metadata['client_name'] ) ? sanitize_text_field( (string) $metadata['client_name'] ) : 'OAuth client',
		);
	}

	private static function is_client_metadata_url( $client_id ) {
		if ( strlen( (string) $client_id ) > 2048 ) {
			return false;
		}
		$parts = wp_parse_url( (string) $client_id );
		return is_array( $parts )
			&& 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
			&& ! empty( $parts['host'] )
			&& ! isset( $parts['fragment'] )
			&& ! isset( $parts['user'] )
			&& ! isset( $parts['pass'] );
	}

	private static function validated_redirect_uris( $uris ) {
		if ( ! is_array( $uris ) || empty( $uris ) || count( $uris ) > 5 ) {
			return new WP_Error( 'cmsa_oauth_redirects_invalid', 'One to five HTTPS redirect URIs are required.' );
		}
		$valid = array();
		foreach ( $uris as $uri ) {
			$uri = esc_url_raw( (string) $uri );
			$parts = wp_parse_url( $uri );
			$scheme = is_array( $parts ) ? strtolower( (string) ( $parts['scheme'] ?? '' ) ) : '';
			$host = is_array( $parts ) ? strtolower( (string) ( $parts['host'] ?? '' ) ) : '';
			$is_https = 'https' === $scheme && '' !== $host;
			$is_loopback = 'http' === $scheme && in_array( $host, array( '127.0.0.1', '::1', '[::1]' ), true );
			if ( ! is_array( $parts ) || ( ! $is_https && ! $is_loopback ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
				return new WP_Error( 'cmsa_oauth_redirect_invalid', 'Redirect URIs must use HTTPS, except native-client HTTP loopback redirects on 127.0.0.1 or ::1, and must not contain a fragment or user info.' );
			}
			$valid[] = $uri;
		}
		return array_values( array_unique( $valid ) );
	}

	private static function render_consent( array $client, array $params ) {
		$hidden = '';
		foreach ( array( 'client_id', 'redirect_uri', 'response_type', 'scope', 'resource', 'state', 'code_challenge', 'code_challenge_method' ) as $name ) {
			$hidden .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( isset( $params[ $name ] ) ? (string) $params[ $name ] : '' ) . '">';
		}
		$client_name = isset( $client['client_name'] ) && '' !== $client['client_name'] ? $client['client_name'] : 'ChatGPT';
		$redirect_destination = isset( $params['redirect_uri'] ) ? (string) $params['redirect_uri'] : '';
		$title = esc_html__( 'Connect Chattanooga CMS Admin', 'chattanooga-cms-admin' );
		$message = sprintf( esc_html__( '%s is requesting administrator access to the Chattanooga CMS tools. Actions will run as your current WordPress administrator account.', 'chattanooga-cms-admin' ), esc_html( $client_name ) );
		$destination = '<p><strong>' . esc_html__( 'Redirect destination:', 'chattanooga-cms-admin' ) . '</strong> <code>' . esc_html( $redirect_destination ) . '</code></p>';
		$nonce = wp_nonce_field( 'cua_oauth_authorize', '_wpnonce', true, false );
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . esc_html( $title ) . '</title></head><body style="font:16px system-ui;max-width:640px;margin:8vh auto;padding:24px;line-height:1.5"><h1>' . esc_html( $title ) . '</h1><p>' . $message . '</p>' . $destination . '<p><strong>' . esc_html__( 'Permission:', 'chattanooga-cms-admin' ) . '</strong> ' . esc_html__( 'Inspect and administer this WordPress site through the installed CMS abilities.', 'chattanooga-cms-admin' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php?action=cua_oauth_authorize' ) ) . '">' . $hidden . $nonce . '<button type="submit" name="approve" value="1" style="padding:10px 16px;margin-right:8px">' . esc_html__( 'Allow', 'chattanooga-cms-admin' ) . '</button><button type="submit" name="approve" value="0" style="padding:10px 16px">' . esc_html__( 'Deny', 'chattanooga-cms-admin' ) . '</button></form></body></html>';
		exit;
	}

	private static function authorization_redirect_error( $redirect_uri, $error, $description, $state ) {
		$url = add_query_arg( array_filter( array( 'error' => $error, 'error_description' => $description, 'state' => $state, 'iss' => self::canonical_issuer() ), 'strlen' ), $redirect_uri );
		wp_redirect( $url );
		exit;
	}

	private static function probe_url( $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'            => 5,
				'redirection'        => 2,
				'reject_unsafe_urls' => true,
				'headers'            => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'status' => 0,
				'error' => sanitize_key( $response->get_error_code() ),
			);
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$content_type = strtolower( trim( (string) wp_remote_retrieve_header( $response, 'content-type' ) ) );
		return array(
			'ok'           => 200 === $status && false !== strpos( $content_type, 'application/json' ),
			'status'       => $status,
			'content_type' => $content_type,
		);
	}

	private static function record_client_metadata_check( $outcome, $http_status ) {
		update_option(
			self::DIAGNOSTIC_OPTION,
			array(
				'outcome'     => sanitize_key( (string) $outcome ),
				'http_status' => (int) $http_status,
				'checked_at'  => gmdate( 'c' ),
			),
			false
		);
	}

	private static function request_has_json_content_type( WP_REST_Request $request ) {
		return (bool) preg_match( '#^application/json(?:\s*;|$)#i', trim( (string) $request->get_header( 'content-type' ) ) );
	}

	private static function normalize_scope( $scope ) {
		$scopes = preg_split( '/\s+/', trim( (string) $scope ), -1, PREG_SPLIT_NO_EMPTY );
		$scopes = array_values( array_unique( is_array( $scopes ) ? $scopes : array() ) );
		if ( ! in_array( self::SCOPE, $scopes, true ) ) {
			return new WP_Error( 'cmsa_oauth_scope_required', 'The mcp:admin scope is required.' );
		}
		foreach ( $scopes as $candidate ) {
			if ( ! in_array( $candidate, array( self::SCOPE, self::OFFLINE_SCOPE ), true ) ) {
				return new WP_Error( 'cmsa_oauth_scope_unsupported', 'The requested scope is not supported.' );
			}
		}
		$normalized = array( self::SCOPE );
		if ( in_array( self::OFFLINE_SCOPE, $scopes, true ) ) {
			$normalized[] = self::OFFLINE_SCOPE;
		}
		return implode( ' ', $normalized );
	}

	private static function scope_contains( $scope, $required ) {
		$scopes = preg_split( '/\s+/', trim( (string) $scope ), -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $scopes ) && in_array( (string) $required, $scopes, true );
	}

	private static function canonical_issuer() {
		return untrailingslashit( home_url( '/' ) );
	}

	private static function canonical_resource() {
		return rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE );
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
