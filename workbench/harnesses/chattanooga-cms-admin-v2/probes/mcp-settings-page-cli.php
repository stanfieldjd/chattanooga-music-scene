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

echo "cmsa-mcp-settings-page: PASS enabled=default origin_sanitization=verified endpoint=visible protocol=visible\n";
exit( 0 );
