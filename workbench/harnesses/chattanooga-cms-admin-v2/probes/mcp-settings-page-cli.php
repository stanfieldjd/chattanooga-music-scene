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
cmsa_mcp_settings_assert( false !== strpos( $page, 'Automatic OAuth authorization' ), 'Automatic OAuth authorization mode is missing from the settings page.' );
cmsa_mcp_settings_assert( false !== strpos( $page, 'Manual authorization token' ), 'Manual authorization mode is missing from the settings page.' );

cmsa_mcp_settings_assert( CUA_MCP_Settings_Page::AUTH_MODE_OAUTH === CUA_MCP_Settings_Page::auth_mode(), 'OAuth is not the default authorization mode.' );
cmsa_mcp_settings_assert( CUA_MCP_Settings_Page::DEFAULT_RATE_LIMIT === CUA_MCP_Settings_Page::rate_limit(), 'The default MCP rate limit is not configured.' );
cmsa_mcp_settings_assert( CUA_MCP_Settings_Page::MAX_RATE_LIMIT === CUA_MCP_Settings_Page::sanitize_rate_limit( CUA_MCP_Settings_Page::MAX_RATE_LIMIT + 1 ), 'MCP rate-limit sanitization did not cap the configured value.' );
cmsa_mcp_settings_assert( false !== strpos( $page, 'Requests per minute' ), 'MCP rate-limit setting is missing from the settings page.' );
cmsa_mcp_settings_assert( false === CUA_MCP_Settings_Page::is_read_only(), 'Read-only MCP mode is not disabled by default.' );
cmsa_mcp_settings_assert( false !== strpos( $page, 'Read-only mode' ), 'Read-only MCP setting is missing from the settings page.' );
cmsa_mcp_settings_assert( false !== strpos( $page, 'Environment identifier' ), 'Environment identifier setting is missing from the settings page.' );
cmsa_mcp_settings_assert( false !== strpos( $page, 'Enabled site capabilities' ), 'Per-site capability setting is missing from the settings page.' );
cmsa_mcp_settings_assert( '' !== CUA_MCP_Settings_Page::environment_id(), 'The MCP environment identifier is empty.' );
cmsa_mcp_settings_assert( 'site-test-1' === CUA_MCP_Settings_Page::sanitize_environment( ' Site TEST/1 ' ), 'Environment identifier sanitization failed.' );
$configured_tools = CUA_MCP_Settings_Page::sanitize_allowed_tools( array( 'cmsa.catalog', 'cmsa.write-bridge', 'cmsa.control-plane', 'cmsa.bridge-not-a-hash' ) );
cmsa_mcp_settings_assert( array( 'cmsa.catalog', 'cmsa.write-bridge' ) === $configured_tools, 'Per-site capability sanitization accepted a non-site capability.' );
update_option( CUA_MCP_Settings_Page::OPTION_ALLOWED_TOOLS, array( 'cmsa.catalog' ) );
cmsa_mcp_settings_assert( true === CUA_MCP_Settings_Page::is_tool_allowed( 'cmsa.catalog' ), 'Configured site capability was not allowed.' );
cmsa_mcp_settings_assert( false === CUA_MCP_Settings_Page::is_tool_allowed( 'cmsa.write-bridge' ), 'Unconfigured site capability remained allowed.' );
update_option( CUA_MCP_Settings_Page::OPTION_ALLOWED_TOOLS, array() );
update_option( CUA_MCP_Settings_Page::OPTION_AUTH_MODE, CUA_MCP_Settings_Page::AUTH_MODE_MANUAL );
wp_set_current_user( 1 );
$manual_token = str_repeat( 'manual-token-', 4 );
$manual_record = CUA_MCP_Settings_Page::sanitize_manual_token( $manual_token );
update_option( CUA_MCP_Settings_Page::OPTION_MANUAL_TOKEN, $manual_record );
cmsa_mcp_settings_assert( CUA_MCP_Settings_Page::is_manual_auth(), 'Manual authorization mode was not stored.' );
cmsa_mcp_settings_assert( true === CUA_MCP_Settings_Page::is_enabled(), 'Manual authorization mode unexpectedly disabled the MCP endpoint.' );
cmsa_mcp_settings_assert( false === CUA_OAuth_Server::is_oauth_enabled(), 'OAuth remained enabled in manual authorization mode.' );
cmsa_mcp_settings_assert( 1 === CUA_MCP_Settings_Page::authenticate_manual_token( $manual_token ), 'The configured manual authorization token was rejected.' );
cmsa_mcp_settings_assert( false === CUA_MCP_Settings_Page::authenticate_manual_token( $manual_token . '-wrong' ), 'An invalid manual authorization token was accepted.' );
$rotated_token = str_repeat( 'rotated-token-', 4 );
update_option( CUA_MCP_Settings_Page::OPTION_MANUAL_TOKEN, CUA_MCP_Settings_Page::sanitize_manual_token( $rotated_token ) );
cmsa_mcp_settings_assert( false === CUA_MCP_Settings_Page::authenticate_manual_token( $manual_token ), 'The previous manual authorization token remained valid after rotation.' );
cmsa_mcp_settings_assert( 1 === CUA_MCP_Settings_Page::authenticate_manual_token( $rotated_token ), 'The rotated manual authorization token was rejected.' );
update_option( CUA_MCP_Settings_Page::OPTION_AUTH_MODE, CUA_MCP_Settings_Page::AUTH_MODE_OAUTH );
cmsa_mcp_settings_assert( true === CUA_OAuth_Server::is_oauth_enabled(), 'OAuth did not restore after leaving manual authorization mode.' );

update_option( CUA_MCP_Settings_Page::OPTION_READ_ONLY, true );
cmsa_mcp_settings_assert( true === CUA_MCP_Settings_Page::is_read_only(), 'Read-only MCP mode was not stored.' );
update_option( CUA_MCP_Settings_Page::OPTION_READ_ONLY, false );
cmsa_mcp_settings_assert( false === CUA_MCP_Settings_Page::is_read_only(), 'Read-only MCP mode did not restore.' );

update_option( CUA_MCP_Settings_Page::OPTION_ENABLED, false );
cmsa_mcp_settings_assert( false === CUA_MCP_Settings_Page::is_enabled(), 'MCP endpoint disable state was not stored.' );
cmsa_mcp_settings_assert( false === CUA_OAuth_Server::is_enabled(), 'OAuth remained enabled after the MCP endpoint was disabled.' );
update_option( CUA_MCP_Settings_Page::OPTION_ENABLED, true );
cmsa_mcp_settings_assert( true === CUA_OAuth_Server::is_enabled(), 'OAuth did not restore with the MCP endpoint.' );

echo "cmsa-mcp-settings-page: PASS enabled=default disable_gate=verified oauth_gate=verified origin_sanitization=verified rate_limit=default_and_bounded read_only=toggle_verified endpoint=visible protocol=visible\n";
exit( 0 );
