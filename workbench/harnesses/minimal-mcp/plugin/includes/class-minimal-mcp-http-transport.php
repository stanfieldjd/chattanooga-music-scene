<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Minimal_MCP_HTTP_Transport {
	private const REST_NAMESPACE = 'minimal-mcp/v1';
	private const REST_ROUTE     = '/mcp';

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
			return self::response(
				array(
					'http_status' => 400,
					'body'        => array(
						'jsonrpc' => '2.0',
						'id'      => null,
						'error'   => array(
							'code'    => -32700,
							'message' => 'A single valid JSON-RPC object is required.',
						),
					),
				)
			);
		}

		$id     = array_key_exists( 'id', $payload ) ? $payload['id'] : null;
		$method = isset( $payload['method'] ) && is_string( $payload['method'] ) ? $payload['method'] : '';
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();

		$header_error = self::validate_headers( $request, $method, $params );
		if ( is_wp_error( $header_error ) ) {
			return self::response(
				array(
					'http_status' => 400,
					'body'        => array(
						'jsonrpc' => '2.0',
						'id'      => $id,
						'error'   => array(
							'code'    => -32020,
							'message' => $header_error->get_error_message(),
						),
					),
				)
			);
		}

		return self::response( CMSA_Minimal_MCP_Request_Router::route( $payload ) );
	}

	/**
	 * @param array<string,mixed> $params Request parameters.
	 */
	private static function validate_headers( WP_REST_Request $request, string $method, array $params ) {
		$version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
		if ( CMSA_Minimal_MCP_Request_Router::PROTOCOL_VERSION !== $version ) {
			return new WP_Error( 'minimal_mcp_version_mismatch', 'MCP-Protocol-Version must be 2026-07-28.' );
		}

		$header_method = trim( (string) $request->get_header( 'mcp-method' ) );
		if ( '' === $method || '' === $header_method || $method !== $header_method ) {
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

	/**
	 * @param array<string,mixed> $routed Routed result.
	 */
	private static function response( array $routed ): WP_REST_Response {
		$response = new WP_REST_Response(
			$routed['body'] ?? array(),
			isset( $routed['http_status'] ) ? (int) $routed['http_status'] : 500
		);
		$response->header( 'MCP-Protocol-Version', CMSA_Minimal_MCP_Request_Router::PROTOCOL_VERSION );
		return $response;
	}

	private static function is_list_array( array $value ): bool {
		if ( array() === $value ) {
			return false;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
