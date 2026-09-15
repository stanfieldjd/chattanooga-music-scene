<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Minimal_MCP_Request_Router {
	public const PROTOCOL_VERSION = '2026-07-28';
	public const SERVER_NAME      = 'robust-mcp-server';
	public const SERVER_VERSION   = '0.1.0';

	/**
	 * @param array<string,mixed> $payload Associative payload used by the PHP router.
	 * @param mixed               $native_payload Native json_decode() payload preserving object/list distinctions.
	 * @return array<string,mixed>
	 */
	public static function route( array $payload, $native_payload = null ): array {
		$id = array_key_exists( 'id', $payload ) ? $payload['id'] : null;
		if ( '2.0' !== ( $payload['jsonrpc'] ?? null ) || ! isset( $payload['method'] ) || ! is_string( $payload['method'] ) || '' === $payload['method'] ) {
			return self::protocol_error( $id, -32600, 'Invalid JSON-RPC request.', 400 );
		}
		if ( ! array_key_exists( 'id', $payload ) ) {
			return self::protocol_error( null, -32600, 'Notifications must be handled by the transport layer.', 400 );
		}

		$method = $payload['method'];
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();

		$registry_health = CMSA_Minimal_MCP_Tool_Registry::health();
		if ( is_wp_error( $registry_health ) ) {
			return self::protocol_error( $id, -32603, 'MCP tool registry is unavailable.', 500 );
		}

		switch ( $method ) {
			case 'server/discover':
				return self::success(
					$id,
					array(
						'supportedVersions' => array( self::PROTOCOL_VERSION ),
						'capabilities'      => array( 'tools' => array( 'listChanged' => false ) ),
						'instructions'      => 'Robust WordPress-hosted MCP server. Tool contracts are centrally validated against JSON Schema 2020-12.',
						'ttlMs'             => 30000,
						'cacheScope'        => 'private',
					)
				);

			case 'tools/list':
				return self::success(
					$id,
					array(
						'tools'      => CMSA_Minimal_MCP_Tool_Registry::all(),
						'ttlMs'      => 30000,
						'cacheScope' => 'private',
					)
				);

			case 'tools/call':
				$name = $params['name'] ?? null;
				if ( ! is_string( $name ) || '' === $name ) {
					return self::protocol_error( $id, -32602, 'Invalid params: tools/call requires a non-empty string name.', 200 );
				}
				if ( null === CMSA_Minimal_MCP_Tool_Registry::get( $name ) ) {
					return self::protocol_error( $id, -32602, 'Unknown tool: ' . $name, 200 );
				}

				$arguments = array();
				if ( array_key_exists( 'arguments', $params ) ) {
					if ( ! is_array( $params['arguments'] ) || self::is_list_array( $params['arguments'] ) ) {
						return self::protocol_error( $id, -32602, 'Invalid params: tool arguments must be a JSON object.', 200 );
					}
					$arguments = $params['arguments'];
				}

				$native_arguments = new stdClass();
				if (
					is_object( $native_payload )
					&& isset( $native_payload->params )
					&& is_object( $native_payload->params )
					&& property_exists( $native_payload->params, 'arguments' )
				) {
					$native_arguments = $native_payload->params->arguments;
				}

				$input_error = CMSA_Minimal_MCP_Tool_Registry::validate_input( $name, $native_arguments );
				if ( is_wp_error( $input_error ) ) {
					return self::protocol_error( $id, -32602, 'Invalid params: tool arguments do not match inputSchema.', 200 );
				}

				return self::success( $id, CMSA_Minimal_MCP_Tool_Registry::call( $name, $arguments, $native_arguments ) );

			default:
				return self::protocol_error( $id, -32601, 'Method not found.', 404 );
		}
	}

	/** @param mixed $id @param array<string,mixed> $result @return array<string,mixed> */
	private static function success( $id, array $result ): array {
		$result = array_merge(
			array(
				'resultType' => 'complete',
				'_meta'      => array(
					'io.modelcontextprotocol/serverInfo' => array(
						'name'    => self::SERVER_NAME,
						'version' => self::SERVER_VERSION,
					),
				),
			),
			$result
		);
		return array(
			'http_status' => 200,
			'body'        => array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ),
		);
	}

	/** @param mixed $id @return array<string,mixed> */
	private static function protocol_error( $id, int $code, string $message, int $status ): array {
		return array(
			'http_status' => $status,
			'body'        => array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => array( 'code' => $code, 'message' => $message ),
			),
		);
	}

	private static function is_list_array( array $value ): bool {
		return array() !== $value && array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
