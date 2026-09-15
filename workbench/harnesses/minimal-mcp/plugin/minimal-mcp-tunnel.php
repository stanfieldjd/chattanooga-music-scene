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

// WP_REST_Server::dispatch() calls rest_pre_dispatch before
// WP_REST_Request::has_valid_params() parses JSON. Intercept malformed JSON
// for this MCP route at that boundary so the wire response remains JSON-RPC
// instead of WordPress's rest_invalid_json envelope. Run the same transport
// authorization first because WordPress skips permission_callback when JSON
// validation itself has already failed.
add_filter(
	'rest_pre_dispatch',
	static function ( $result, $server, $request ) {
		unset( $server );
		if (
		null !== $result
			|| ! $request instanceof WP_REST_Request
			|| '/minimal-mcp/v1/mcp' !== $request->get_route()
			|| 'POST' !== $request->get_method()
		) {
			return $result;
		}

		$content_type = strtolower( trim( strtok( (string) $request->get_header( 'content-type' ), ';' ) ?: '' ) );
		if ( 'application/json' !== $content_type ) {
			return $result;
		}

		$body = (string) $request->get_body();
		if ( '' === $body ) {
			return $result;
		}

		json_decode( $body, true );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			return $result;
		}

		$authorization = CMSA_Minimal_MCP_HTTP_Transport::authorize_request( $request );
		if ( is_wp_error( $authorization ) ) {
			return $authorization;
		}

		$response = new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => null,
				'error'   => array(
					'code'    => -32700,
					'message' => 'Parse error: invalid JSON.',
				),
			),
			400
		);
		$response->header( 'MCP-Protocol-Version', CMSA_Minimal_MCP_Request_Router::PROTOCOL_VERSION );
		return $response;
	},
	5,
	3
);

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
