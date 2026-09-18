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
cmsa_mcp_settings_assert( in_array( CUA_OAuth_Server::OFFLINE_SCOPE, $resource_metadata['scopes_supported'] ?? array(), true ), 'Protected-resource metadata does not advertise offline_access.' );
cmsa_mcp_settings_assert( in_array( 'refresh_token', $oauth_metadata['grant_types_supported'] ?? array(), true ), 'OAuth discovery does not advertise refresh_token.' );

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

echo "cmsa-mcp-settings-page: PASS enabled=default origin_sanitization=verified endpoint=visible protocol=visible\n";
exit( 0 );
