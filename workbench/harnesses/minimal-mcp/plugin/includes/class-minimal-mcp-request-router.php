<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Minimal_MCP_Request_Router {
	public const PROTOCOL_VERSION = '2026-07-28';
	public const SERVER_NAME      = 'minimal-mcp-tunnel';
	public const SERVER_VERSION   = '0.0.5';

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	public static function route( array $payload ): array {
		$id = array_key_exists( 'id', $payload ) ? $payload['id'] : null;
		if ( '2.0' !== ( $payload['jsonrpc'] ?? null ) || ! isset( $payload['method'] ) || ! is_string( $payload['method'] ) || '' === $payload['method'] ) {
			return self::protocol_error( $id, -32600, 'Invalid JSON-RPC request.', 400 );
		}
		if ( ! array_key_exists( 'id', $payload ) ) {
			return self::protocol_error( null, -32600, 'Notifications must be handled by the transport layer.', 400 );
		}

		$method = $payload['method'];
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();

		switch ( $method ) {
			case 'server/discover':
				return self::success(
					$id,
					array(
						'supportedVersions' => array( self::PROTOCOL_VERSION ),
						'capabilities'      => array( 'tools' => array( 'listChanged' => false ) ),
						'instructions'      => 'Minimal MCP transport proof. Tools are supplied through a validated WordPress registry.',
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
				return self::success( $id, CMSA_Minimal_MCP_Tool_Registry::call( $name, $arguments ) );

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
