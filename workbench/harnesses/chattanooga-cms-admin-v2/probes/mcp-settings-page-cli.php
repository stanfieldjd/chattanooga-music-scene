<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_mcp_settings_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_mcp_settings_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_mcp_settings_fail( $message );
	}
}

wp_set_current_user( 1 );
cmsa_mcp_settings_assert( class_exists( 'CUA_MCP_Settings_Page' ), 'MCP settings page class did not load.' );
CUA_MCP_Settings_Page::register_settings();
cmsa_mcp_settings_assert( true === CUA_MCP_Settings_Page::is_enabled(), 'MCP endpoint is not enabled by default.' );

$default_origins = CUA_MCP_Settings_Page::allowed_origins();
cmsa_mcp_settings_assert( ! empty( $default_origins ), 'No default MCP origins were generated.' );
cmsa_mcp_settings_assert( CUA_MCP_Settings_Page::is_origin_allowed( home_url( '/' ) ), 'The site origin is not allowed by default.' );

$sanitized = CUA_MCP_Settings_Page::sanitize_origins(
	array(
		'https://EXAMPLE.com/path',
		'https://example.com',
		'not-an-origin',
	)
);
cmsa_mcp_settings_assert( array( 'https://example.com' ) === $sanitized, 'Origin sanitization accepted an invalid or duplicate entry.' );

ob_start();
CUA_MCP_Settings_Page::render_page();
$page = ob_get_clean();
cmsa_mcp_settings_assert( false !== strpos( $page, 'Chattanooga MCP Settings' ), 'MCP settings page title is missing.' );
cmsa_mcp_settings_assert( false !== strpos( $page, esc_url( rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE ) ) ), 'MCP endpoint is missing from the settings page.' );
cmsa_mcp_settings_assert( false !== strpos( $page, CUA_MCP_Server::PROTOCOL_VERSION ), 'MCP protocol version is missing from the settings page.' );
cmsa_mcp_settings_assert( false !== strpos( $page, 'Allowed browser origins' ), 'MCP origin setting is missing from the settings page.' );

$oauth_metadata = CUA_OAuth_Server::authorization_server_metadata();
$resource_metadata = CUA_OAuth_Server::protected_resource_metadata();
cmsa_mcp_settings_assert( in_array( CUA_OAuth_Server::OFFLINE_SCOPE, $oauth_metadata['scopes_supported'] ?? array(), true ), 'OAuth discovery does not advertise offline_access.' );
cmsa_mcp_settings_assert( ! in_array( CUA_OAuth_Server::OFFLINE_SCOPE, $resource_metadata['scopes_supported'] ?? array(), true ), 'Protected-resource metadata incorrectly advertises offline_access as a resource requirement.' );
cmsa_mcp_settings_assert( in_array( CUA_OAuth_Server::SCOPE, $resource_metadata['scopes_supported'] ?? array(), true ), 'Protected-resource metadata does not advertise the administrator scope.' );
cmsa_mcp_settings_assert( true === ( $oauth_metadata['authorization_response_iss_parameter_supported'] ?? false ), 'OAuth discovery does not advertise authorization response issuer identification.' );
cmsa_mcp_settings_assert( true === ( $oauth_metadata['client_id_metadata_document_supported'] ?? false ), 'OAuth discovery does not advertise CIMD support.' );
cmsa_mcp_settings_assert( in_array( 'refresh_token', $oauth_metadata['grant_types_supported'] ?? array(), true ), 'OAuth discovery does not advertise refresh_token.' );

$original_auth_mode = get_option( CUA_MCP_Settings_Page::OPTION_AUTH_MODE, CUA_MCP_Settings_Page::AUTH_MODE_OAUTH );
update_option( CUA_MCP_Settings_Page::OPTION_AUTH_MODE, CUA_MCP_Settings_Page::AUTH_MODE_MANUAL, false );
cmsa_mcp_settings_assert( true === CUA_OAuth_Server::is_oauth_enabled(), 'Manual bearer fallback mode disabled the OAuth authorization server.' );
$challenge = CUA_OAuth_Server::resource_challenge();
cmsa_mcp_settings_assert( false !== strpos( $challenge, 'resource_metadata=' ), 'Authorization challenge does not advertise protected-resource metadata.' );
cmsa_mcp_settings_assert( false !== strpos( $challenge, 'scope="' . CUA_OAuth_Server::SCOPE . '"' ), 'Authorization challenge does not advertise the administrator scope.' );
cmsa_mcp_settings_assert( false !== strpos( $challenge, esc_url_raw( CUA_OAuth_Server::protected_resource_metadata_url() ) ), 'Authorization challenge does not use the REST metadata fallback.' );
cmsa_mcp_settings_assert( false === strpos( $challenge, 'error="invalid_token"' ), 'Missing-token challenge is incorrectly labeled invalid_token.' );
$invalid_challenge = CUA_OAuth_Server::resource_challenge( 'cmsa_oauth_token_invalid' );
cmsa_mcp_settings_assert( false !== strpos( $invalid_challenge, 'error="invalid_token"' ), 'Invalid-token challenge omits invalid_token.' );
$scope_challenge = CUA_OAuth_Server::resource_challenge( 'cmsa_oauth_insufficient_scope' );
cmsa_mcp_settings_assert( false !== strpos( $scope_challenge, 'error="insufficient_scope"' ), 'Insufficient-scope challenge omits insufficient_scope.' );

$rewrite_rules = CUA_OAuth_Server::inject_well_known_rewrite_rules( "ORIGINAL-WORDPRESS-RULES\n" );
cmsa_mcp_settings_assert( false !== strpos( $rewrite_rules, '^\\.well-known/oauth-protected-resource/?$' ), 'Root protected-resource rewrite rule is missing.' );
cmsa_mcp_settings_assert( false !== strpos( $rewrite_rules, 'oauth-protected-resource/wp-json/chattanooga-cms-admin/v1/mcp' ), 'Path-aware protected-resource rewrite rule is missing.' );
cmsa_mcp_settings_assert( false !== strpos( $rewrite_rules, '^\\.well-known/oauth-authorization-server/?$' ), 'Authorization-server rewrite rule is missing.' );
cmsa_mcp_settings_assert( strpos( $rewrite_rules, 'oauth-protected-resource' ) < strpos( $rewrite_rules, 'ORIGINAL-WORDPRESS-RULES' ), 'OAuth discovery rewrites are not ahead of WordPress file/directory bypass rules.' );

$routes = rest_get_server()->get_routes();
cmsa_mcp_settings_assert( isset( $routes['/chattanooga-cms-admin/v1/oauth/protected-resource'] ), 'REST protected-resource metadata route is missing.' );
cmsa_mcp_settings_assert( isset( $routes['/chattanooga-cms-admin/v1/oauth/authorization-server'] ), 'REST authorization-server metadata route is missing.' );
cmsa_mcp_settings_assert( isset( $routes['/chattanooga-cms-admin/v1/oauth/diagnostics'] ), 'OAuth diagnostics route is missing.' );

$original_clients = get_option( CUA_OAuth_Server::CLIENT_OPTION, array() );
$loopback_registration = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/oauth/register' );
$loopback_registration->set_header( 'Content-Type', 'application/json' );
$loopback_registration->set_body(
	wp_json_encode(
		array(
			'redirect_uris'              => array( 'http://127.0.0.1:49152/callback' ),
			'client_name'                => 'Native MCP test',
			'token_endpoint_auth_method' => 'none',
		)
	)
);
$loopback_response = CUA_OAuth_Server::register_client( $loopback_registration );
cmsa_mcp_settings_assert( 201 === $loopback_response->get_status(), 'Standards-compliant native loopback redirect was rejected.' );
update_option( CUA_OAuth_Server::CLIENT_OPTION, is_array( $original_clients ) ? $original_clients : array(), false );

$scope_token = 'cmsa-scope-test-' . wp_generate_password( 40, false, false );
$scope_key = 'cua_oauth_access_' . hash_hmac( 'sha256', $scope_token, wp_salt( 'auth' ) );
set_transient(
	$scope_key,
	array(
		'client_id' => 'cmsa-scope-test',
		'user_id'   => get_current_user_id(),
		'scope'     => CUA_OAuth_Server::OFFLINE_SCOPE,
		'resource'  => rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE ),
	),
	300
);
$scope_request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$scope_request->set_header( 'Authorization', 'Bearer ' . $scope_token );
$scope_result = CUA_OAuth_Server::authenticate_bearer( $scope_request );
cmsa_mcp_settings_assert( is_wp_error( $scope_result ) && 'cmsa_oauth_insufficient_scope' === $scope_result->get_error_code(), 'Underscoped OAuth token was not distinguished from an invalid token.' );
delete_transient( $scope_key );


$oauth_fallback_token = 'cmsa-oauth-fallback-' . wp_generate_password( 40, false, false );
$oauth_fallback_key = 'cua_oauth_access_' . hash_hmac( 'sha256', $oauth_fallback_token, wp_salt( 'auth' ) );
set_transient(
	$oauth_fallback_key,
	array(
		'client_id' => 'cmsa-oauth-fallback-client',
		'user_id'   => get_current_user_id(),
		'scope'     => CUA_OAuth_Server::SCOPE,
		'resource'  => rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE ),
	),
	300
);
$oauth_fallback_request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$oauth_fallback_request->set_header( 'Authorization', 'Bearer ' . $oauth_fallback_token );
cmsa_mcp_settings_assert( true === CUA_OAuth_Server::authenticate_bearer( $oauth_fallback_request ), 'Manual bearer fallback mode rejected a valid OAuth access token.' );
delete_transient( $oauth_fallback_key );
update_option( CUA_MCP_Settings_Page::OPTION_AUTH_MODE, $original_auth_mode, false );

function cmsa_oauth_test_key( $type, $token ) {
	return 'cua_oauth_' . $type . '_' . hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
}

function cmsa_oauth_refresh_request( $refresh_token, $client_id, $resource ) {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/oauth/token' );
	$request->set_param( 'grant_type', 'refresh_token' );
	$request->set_param( 'refresh_token', $refresh_token );
	$request->set_param( 'client_id', $client_id );
	$request->set_param( 'resource', $resource );
	return CUA_OAuth_Server::token( $request );
}

$oauth_resource = rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE );
$legacy_refresh = 'cmsa-legacy-refresh-' . wp_generate_password( 40, false, false );
$legacy_key = cmsa_oauth_test_key( 'refresh', $legacy_refresh );
set_transient(
	$legacy_key,
	array(
		'client_id' => 'cmsa-legacy-client',
		'user_id'   => get_current_user_id(),
		'scope'     => CUA_OAuth_Server::SCOPE,
		'resource'  => $oauth_resource,
	),
	300
);
$legacy_response = cmsa_oauth_refresh_request( $legacy_refresh, 'cmsa-legacy-client', $oauth_resource );
$legacy_data = $legacy_response->get_data();
cmsa_mcp_settings_assert( 200 === $legacy_response->get_status(), 'Legacy refresh-token migration failed.' );
cmsa_mcp_settings_assert( ! empty( $legacy_data['access_token'] ), 'Legacy refresh-token migration returned no access token.' );
cmsa_mcp_settings_assert( empty( $legacy_data['refresh_token'] ), 'Legacy refresh-token migration silently expanded offline access.' );
cmsa_mcp_settings_assert( is_array( get_transient( $legacy_key ) ), 'Legacy refresh token was consumed without a replacement.' );
if ( ! empty( $legacy_data['access_token'] ) ) {
	delete_transient( cmsa_oauth_test_key( 'access', $legacy_data['access_token'] ) );
}
delete_transient( $legacy_key );

$modern_refresh = 'cmsa-modern-refresh-' . wp_generate_password( 40, false, false );
$modern_key = cmsa_oauth_test_key( 'refresh', $modern_refresh );
set_transient(
	$modern_key,
	array(
		'client_id' => 'cmsa-modern-client',
		'user_id'   => get_current_user_id(),
		'scope'     => CUA_OAuth_Server::SCOPE . ' ' . CUA_OAuth_Server::OFFLINE_SCOPE,
		'resource'  => $oauth_resource,
	),
	300
);
$invalid_response = cmsa_oauth_refresh_request( $modern_refresh, 'wrong-client', $oauth_resource );
cmsa_mcp_settings_assert( 400 === $invalid_response->get_status(), 'Invalid refresh-token client binding was accepted.' );
cmsa_mcp_settings_assert( is_array( get_transient( $modern_key ) ), 'Invalid refresh attempt consumed a valid refresh token.' );

$modern_response = cmsa_oauth_refresh_request( $modern_refresh, 'cmsa-modern-client', $oauth_resource );
$modern_data = $modern_response->get_data();
cmsa_mcp_settings_assert( 200 === $modern_response->get_status(), 'Modern offline refresh-token rotation failed.' );
cmsa_mcp_settings_assert( ! empty( $modern_data['access_token'] ), 'Modern refresh-token rotation returned no access token.' );
cmsa_mcp_settings_assert( ! empty( $modern_data['refresh_token'] ) && $modern_refresh !== $modern_data['refresh_token'], 'Modern refresh token was not rotated.' );
cmsa_mcp_settings_assert( false === get_transient( $modern_key ), 'Rotated modern refresh token left the old token active.' );
$new_modern_key = cmsa_oauth_test_key( 'refresh', $modern_data['refresh_token'] ?? '' );
cmsa_mcp_settings_assert( is_array( get_transient( $new_modern_key ) ), 'Rotated modern refresh token was not persisted.' );
if ( ! empty( $modern_data['access_token'] ) ) {
	delete_transient( cmsa_oauth_test_key( 'access', $modern_data['access_token'] ) );
}
delete_transient( $new_modern_key );

echo "cmsa-mcp-settings-page: PASS enabled=default origin_sanitization=verified endpoint=visible protocol=visible oauth_metadata=chatgpt-compatible discovery_rewrite=verified rest_metadata_fallback=verified native_loopback=verified scope_semantics=verified manual_fallback=nonexclusive refresh_rotation=verified\n";
exit( 0 );
