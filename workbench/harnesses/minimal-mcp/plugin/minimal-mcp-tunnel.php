<?php
/**
 * Plugin Name: Minimal MCP Tunnel
 * Description: Workbench-only MCP transport proof for Chattanooga Music Scene.
 * Version: 0.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-minimal-mcp-tool-registry.php';
require_once __DIR__ . '/includes/class-minimal-mcp-request-router.php';
require_once __DIR__ . '/includes/class-minimal-mcp-http-transport.php';

CMSA_Minimal_MCP_HTTP_Transport::bootstrap();

// WordPress recomputes Allow from registered REST handlers after dispatch.
// GET/DELETE exist only to return the modern MCP-required 405 response, so
// correct that WordPress-generated header after its default priority-10 filter.
add_filter(
	'rest_post_dispatch',
	static function ( $response, $server, $request ) {
		unset( $server );
		if (
			$response instanceof WP_REST_Response
			&& $request instanceof WP_REST_Request
			&& '/minimal-mcp/v1/mcp' === $request->get_route()
			&& 405 === $response->get_status()
			&& in_array( $request->get_method(), array( 'GET', 'DELETE' ), true )
		) {
			$response->header( 'Allow', 'POST' );
		}
		return $response;
	},
	99,
	3
);
