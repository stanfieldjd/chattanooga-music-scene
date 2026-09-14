<?php
/**
 * Plugin Name: Minimal MCP Tunnel
 * Description: Workbench-only MCP transport proof for Chattanooga Music Scene.
 * Version: 0.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Minimal_MCP_Tunnel {
	private const PROTOCOL_VERSION = '2026-07-28';
	private const REST_NAMESPACE   = 'minimal-mcp/v1';
	private const REST_ROUTE       = '/mcp';
	private const TOOL_NAME        = 'probe.site';

	public static function bootstrap(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	public static function register_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_request' ),
				'permission_callback' => array( __CLASS__, 'authorize_request' ),
			)
		);
	}

	public static function authorize_request() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'minimal_mcp_authentication_required',
				'Authenticated WordPress administrator access is required.',
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'minimal_mcp_forbidden',
				'Administrator authority is required.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	public static function handle_request( WP_REST_Request $request ): WP_REST_Response {
		$payload = json_decode( (string) $request->get_body(), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $payload ) || self::is_list_array( $payload ) ) {
			return self::error_response( null, -32700, 'A single valid JSON-RPC object is required.', 400 );
		}

		$id = array_key_exists( 'id', $payload ) ? $payload['id'] : null;
		if ( '2.0' !== ( $payload['jsonrpc'] ?? null ) || ! isset( $payload['method'] ) || ! is_string( $payload['method'] ) || '' === $payload['method'] ) {
			return self::error_response( $id, -32600, 'Invalid JSON-RPC request.', 400 );
		}

		if ( ! array_key_exists( 'id', $payload ) ) {
			return self::error_response( null, -32600, 'Notifications are not used by this proof of concept.', 400 );
		}

		$method = $payload['method'];
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();

		$header_error = self::validate_headers( $request, $method, $params );
		if ( is_wp_error( $header_error ) ) {
			return self::error_response( $id, -32020, $header_error->get_error_message(), 400 );
		}

		switch ( $method ) {
			case 'server/discover':
				return self::success_response(
					$id,
					array(
						'supportedVersions' => array( self::PROTOCOL_VERSION ),
						'capabilities'      => array(
							'tools' => array( 'listChanged' => false ),
						),
						'instructions'      => 'Minimal MCP transport proof. The only tool is a read-only site probe.',
					)
				);

			case 'tools/list':
				return self::success_response(
					$id,
					array(
						'tools'      => array( self::tool_definition() ),
						'ttlMs'      => 30000,
						'cacheScope' => 'private',
					)
				);

			case 'tools/call':
				return self::success_response( $id, self::call_tool( $params ) );

			default:
				return self::error_response( $id, -32601, 'Method not found.', 404 );
		}
	}

	private static function validate_headers( WP_REST_Request $request, string $method, array $params ) {
		$version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
		if ( self::PROTOCOL_VERSION !== $version ) {
			return new WP_Error( 'minimal_mcp_version_mismatch', 'MCP-Protocol-Version must be 2026-07-28.' );
		}

		$header_method = trim( (string) $request->get_header( 'mcp-method' ) );
		if ( '' === $header_method || $method !== $header_method ) {
			return new WP_Error( 'minimal_mcp_method_mismatch', 'Mcp-Method must match the JSON-RPC method.' );
		}

		if ( 'tools/call' === $method ) {
			$name        = isset( $params['name'] ) ? trim( (string) $params['name'] ) : '';
			$header_name = trim( (string) $request->get_header( 'mcp-name' ) );
			if ( '' === $name || '' === $header_name || $name !== $header_name ) {
				return new WP_Error( 'minimal_mcp_name_mismatch', 'Mcp-Name must match params.name for tools/call.' );
			}
		}

		return true;
	}

	private static function tool_definition(): array {
		return array(
			'name'        => self::TOOL_NAME,
			'title'       => 'Probe WordPress Site',
			'description' => 'Read-only proof that the MCP tunnel reached the WordPress runtime.',
			'inputSchema' => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'annotations' => array(
				'readOnlyHint'    => true,
				'destructiveHint' => false,
				'idempotentHint'  => true,
			),
		);
	}

	private static function call_tool( array $params ): array {
		$name = isset( $params['name'] ) ? trim( (string) $params['name'] ) : '';
		if ( self::TOOL_NAME !== $name ) {
			return array(
				'content' => array(
					array( 'type' => 'text', 'text' => 'Unknown tool.' ),
				),
				'isError' => true,
			);
		}

		$arguments = isset( $params['arguments'] ) ? $params['arguments'] : array();
		if ( ! is_array( $arguments ) || ( ! empty( $arguments ) && self::is_list_array( $arguments ) ) || ! empty( $arguments ) ) {
			return array(
				'content' => array(
					array( 'type' => 'text', 'text' => 'probe.site accepts an empty JSON object.' ),
				),
				'isError' => true,
			);
		}

		global $wp_version;
		$result = array(
			'ok'               => true,
			'siteTitle'        => get_bloginfo( 'name' ),
			'homeUrl'          => home_url( '/' ),
			'wordpressVersion' => (string) $wp_version,
		);

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => wp_json_encode( $result, JSON_UNESCAPED_SLASHES ),
				),
			),
			'structuredContent' => $result,
			'isError'           => false,
		);
	}

	private static function success_response( $id, array $result ): WP_REST_Response {
		$result = array_merge(
			array(
				'resultType' => 'complete',
				'_meta'      => array(
					'io.modelcontextprotocol/serverInfo' => array(
						'name'    => 'minimal-mcp-tunnel',
						'version' => '0.0.1',
					),
				),
			),
			$result
		);

		$response = new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'result'  => $result,
			),
			200
		);
		$response->header( 'MCP-Protocol-Version', self::PROTOCOL_VERSION );
		return $response;
	}

	private static function error_response( $id, int $code, string $message, int $status ): WP_REST_Response {
		$response = new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => array(
					'code'    => $code,
					'message' => $message,
				),
			),
			$status
		);
		$response->header( 'MCP-Protocol-Version', self::PROTOCOL_VERSION );
		return $response;
	}

	private static function is_list_array( array $value ): bool {
		if ( array() === $value ) {
			return false;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}

CMSA_Minimal_MCP_Tunnel::bootstrap();
