<?php
/**
 * Plugin Name: Robust MCP Server
 * Description: Production-oriented MCP 2026-07-28 server transport for WordPress.
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cmsa_robust_mcp_autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $cmsa_robust_mcp_autoload ) ) {
	require_once $cmsa_robust_mcp_autoload;
}
unset( $cmsa_robust_mcp_autoload );

require_once __DIR__ . '/includes/class-robust-mcp-schema-validator.php';
require_once __DIR__ . '/includes/class-minimal-mcp-tool-registry.php';
require_once __DIR__ . '/includes/class-minimal-mcp-request-router.php';
require_once __DIR__ . '/includes/class-minimal-mcp-http-transport.php';

CMSA_Minimal_MCP_HTTP_Transport::bootstrap();

// Optional manual bearer mode. There is deliberately no endpoint that mints
// or rotates this credential. Robust constants are primary; the earlier
// workbench constants remain accepted on this branch so checkpoint fixtures
// can prove backward compatibility while the server is being promoted.
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

		$robust_pair = defined( 'CMSA_ROBUST_MCP_BEARER_TOKEN' ) || defined( 'CMSA_ROBUST_MCP_BEARER_USER_ID' );
		$legacy_pair = defined( 'CMSA_MINIMAL_MCP_BEARER_TOKEN' ) || defined( 'CMSA_MINIMAL_MCP_BEARER_USER_ID' );
		if ( ! $robust_pair && ! $legacy_pair ) {
			return $result;
		}
		if ( $robust_pair && $legacy_pair ) {
			return new WP_Error( 'robust_mcp_bearer_ambiguous', 'Configure only one MCP bearer credential pair.', array( 'status' => 500 ) );
		}

		$token_name = $robust_pair ? 'CMSA_ROBUST_MCP_BEARER_TOKEN' : 'CMSA_MINIMAL_MCP_BEARER_TOKEN';
		$user_name  = $robust_pair ? 'CMSA_ROBUST_MCP_BEARER_USER_ID' : 'CMSA_MINIMAL_MCP_BEARER_USER_ID';
		if ( ! defined( $token_name ) || ! defined( $user_name ) ) {
			return new WP_Error( 'robust_mcp_bearer_misconfigured', 'MCP bearer authentication is incompletely configured.', array( 'status' => 500 ) );
		}

		$token   = constant( $token_name );
		$user_id = constant( $user_name );
		if ( ! is_string( $token ) || strlen( $token ) < 32 || ! is_int( $user_id ) || $user_id <= 0 ) {
			return new WP_Error( 'robust_mcp_bearer_misconfigured', 'MCP bearer authentication configuration is invalid.', array( 'status' => 500 ) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User || ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'robust_mcp_bearer_misconfigured', 'MCP bearer authentication identity is not an administrator.', array( 'status' => 500 ) );
		}

		$authorization = (string) $request->get_header( 'authorization' );
		$matches       = array();
		$valid_header  = 1 === preg_match( '/^Bearer[ \t]+([^\s]+)$/i', $authorization, $matches );
		$provided      = $valid_header ? $matches[1] : '';
		if ( '' === $provided || ! hash_equals( $token, $provided ) ) {
			return new WP_Error( 'robust_mcp_bearer_required', 'A valid manually configured MCP bearer token is required.', array( 'status' => 401 ) );
		}

		wp_set_current_user( $user_id );
		return $result;
	},
	1,
	3
);

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
