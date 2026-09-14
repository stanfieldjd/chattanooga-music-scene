<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Minimal_MCP_HTTP_Transport {
	private const REST_NAMESPACE = 'minimal-mcp/v1';
	private const REST_ROUTE     = '/mcp';
	private const MAX_SAFE_INTEGER = 9007199254740991;

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

	public static function authorize_request( WP_REST_Request $request ) {
		$origin = trim( (string) $request->get_header( 'origin' ) );
		if ( '' !== $origin && ! self::origin_is_allowed( $origin ) ) {
			return new WP_Error(
				'minimal_mcp_origin_forbidden',
				'The request Origin is not permitted for this MCP endpoint.',
				array( 'status' => 403 )
			);
		}

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
			return self::response( self::error_routed( null, -32700, 'A single valid JSON-RPC object is required.', 400 ) );
		}

		$id     = array_key_exists( 'id', $payload ) ? $payload['id'] : null;
		$method = isset( $payload['method'] ) && is_string( $payload['method'] ) ? $payload['method'] : '';
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();

		$validation_error = self::validate_request_metadata( $request, $id, $method, $params );
		if ( null !== $validation_error ) {
			return self::response( $validation_error );
		}

		return self::response( CMSA_Minimal_MCP_Request_Router::route( $payload ) );
	}

	/**
	 * @param mixed               $id JSON-RPC id.
	 * @param array<string,mixed> $params Request parameters.
	 * @return array<string,mixed>|null
	 */
	private static function validate_request_metadata( WP_REST_Request $request, $id, string $method, array $params ): ?array {
		$meta = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
		$header_version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
		$body_version   = isset( $meta['io.modelcontextprotocol/protocolVersion'] )
			? trim( (string) $meta['io.modelcontextprotocol/protocolVersion'] )
			: '';

		if ( '' === $header_version || '' === $body_version || $header_version !== $body_version ) {
			return self::error_routed( $id, -32020, 'MCP-Protocol-Version must be present and match params._meta protocolVersion.', 400 );
		}

		if ( CMSA_Minimal_MCP_Request_Router::PROTOCOL_VERSION !== $body_version ) {
			return self::error_routed(
				$id,
				-32022,
				'Unsupported MCP protocol version.',
				400,
				array(
					'supported' => array( CMSA_Minimal_MCP_Request_Router::PROTOCOL_VERSION ),
					'requested' => $body_version,
				)
			);
		}

		if ( ! array_key_exists( 'io.modelcontextprotocol/clientCapabilities', $meta ) || ! is_array( $meta['io.modelcontextprotocol/clientCapabilities'] ) ) {
			return self::error_routed( $id, -32602, 'params._meta clientCapabilities is required and must be an object.', 400 );
		}

		$header_method = trim( (string) $request->get_header( 'mcp-method' ) );
		if ( '' === $method || '' === $header_method || $method !== $header_method ) {
			return self::error_routed( $id, -32020, 'Mcp-Method must be present and match the JSON-RPC method.', 400 );
		}

		if ( 'tools/call' === $method ) {
			$name        = isset( $params['name'] ) ? (string) $params['name'] : '';
			$header_name = (string) $request->get_header( 'mcp-name' );
			$decoded     = self::decode_header_value( $header_name );
			if ( is_wp_error( $decoded ) || '' === $name || '' === $header_name || $name !== $decoded ) {
				return self::error_routed( $id, -32020, 'Mcp-Name must be present, valid, and match params.name for tools/call.', 400 );
			}

			$arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
			$parameter_error = self::validate_tool_parameter_headers( $request, $name, $arguments );
			if ( is_wp_error( $parameter_error ) ) {
				return self::error_routed( $id, -32020, $parameter_error->get_error_message(), 400 );
			}
		}

		return null;
	}

	/**
	 * Validate all recognized Mcp-Param-* mirrors against the tools/call body.
	 *
	 * @param array<string,mixed> $arguments Tool call arguments.
	 * @return true|WP_Error
	 */
	private static function validate_tool_parameter_headers( WP_REST_Request $request, string $tool_name, array $arguments ) {
		foreach ( CMSA_Minimal_MCP_Tool_Registry::header_mirrors( $tool_name ) as $mirror ) {
			$path   = isset( $mirror['path'] ) && is_array( $mirror['path'] ) ? $mirror['path'] : array();
			$type   = isset( $mirror['type'] ) ? (string) $mirror['type'] : '';
			$suffix = isset( $mirror['suffix'] ) ? (string) $mirror['suffix'] : '';
			if ( empty( $path ) || '' === $suffix ) {
				continue;
			}

			$found = false;
			$value = self::argument_at_path( $arguments, $path, $found );
			$header_name  = 'Mcp-Param-' . $suffix;
			$header_value = $request->get_header( $header_name );
			$has_header   = null !== $header_value;

			if ( ! $found || null === $value ) {
				if ( $has_header ) {
					return new WP_Error( 'minimal_mcp_unexpected_parameter_header', $header_name . ' must be omitted when the corresponding argument is missing or null.' );
				}
				continue;
			}

			if ( ! $has_header ) {
				return new WP_Error( 'minimal_mcp_missing_parameter_header', $header_name . ' is required because the corresponding tool argument is present.' );
			}

			$expected = self::parameter_string_value( $value, $type );
			if ( is_wp_error( $expected ) ) {
				return $expected;
			}

			$decoded = self::decode_header_value( (string) $header_value );
			if ( is_wp_error( $decoded ) || $expected !== $decoded ) {
				return new WP_Error( 'minimal_mcp_parameter_header_mismatch', $header_name . ' must exactly match the corresponding tool argument after MCP header decoding.' );
			}
		}

		return true;
	}

	/**
	 * @param array<string,mixed> $arguments Tool arguments.
	 * @param array<int,string>   $path Property path.
	 * @param bool                $found Whether path exists.
	 * @return mixed
	 */
	private static function argument_at_path( array $arguments, array $path, bool &$found ) {
		$current = $arguments;
		foreach ( $path as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				$found = false;
				return null;
			}
			$current = $current[ $segment ];
		}
		$found = true;
		return $current;
	}

	/**
	 * Convert an annotated primitive into its MCP HTTP mirror string.
	 *
	 * @param mixed $value Body value.
	 * @return string|WP_Error
	 */
	private static function parameter_string_value( $value, string $type ) {
		switch ( $type ) {
			case 'string':
				return is_string( $value )
					? $value
					: new WP_Error( 'minimal_mcp_parameter_type_mismatch', 'Mirrored string argument has a non-string body value.' );

			case 'boolean':
				return is_bool( $value )
					? ( $value ? 'true' : 'false' )
					: new WP_Error( 'minimal_mcp_parameter_type_mismatch', 'Mirrored boolean argument has a non-boolean body value.' );

			case 'integer':
				if ( ! is_int( $value ) || $value < -self::MAX_SAFE_INTEGER || $value > self::MAX_SAFE_INTEGER ) {
					return new WP_Error( 'minimal_mcp_parameter_type_mismatch', 'Mirrored integer argument must be an IEEE-754 safe integer.' );
				}
				return (string) $value;
		}

		return new WP_Error( 'minimal_mcp_parameter_type_mismatch', 'Unsupported mirrored parameter type.' );
	}

	/**
	 * @return string|WP_Error
	 */
	private static function decode_header_value( string $value ) {
		if ( 0 === strpos( $value, '=?base64?' ) ) {
			if ( ! preg_match( '/^=\?base64\?([A-Za-z0-9+\/=]*)\?=$/', $value, $matches ) ) {
				return new WP_Error( 'minimal_mcp_invalid_header_encoding', 'Invalid MCP Base64 header encoding.' );
			}
			$decoded = base64_decode( $matches[1], true );
			if ( false === $decoded || 1 !== preg_match( '//u', $decoded ) ) {
				return new WP_Error( 'minimal_mcp_invalid_header_encoding', 'MCP Base64 header payload must decode to valid UTF-8.' );
			}
			return $decoded;
		}
		return $value;
	}

	private static function origin_is_allowed( string $origin ): bool {
		$normalized = self::normalize_origin( $origin );
		if ( null === $normalized ) {
			return false;
		}

		$allowed = array_filter(
			array(
				self::normalize_origin( home_url( '/' ) ),
				self::normalize_origin( site_url( '/' ) ),
			)
		);
		$allowed = apply_filters( 'minimal_mcp_allowed_origins', array_values( array_unique( $allowed ) ) );
		return is_array( $allowed ) && in_array( $normalized, $allowed, true );
	}

	private static function normalize_origin( string $url ): ?string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return null;
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( (string) $parts['host'] );
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : null;
		if ( ( 'http' === $scheme && 80 === $port ) || ( 'https' === $scheme && 443 === $port ) ) {
			$port = null;
		}

		return $scheme . '://' . $host . ( null !== $port ? ':' . $port : '' );
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

	/**
	 * @param mixed               $id JSON-RPC id.
	 * @param array<string,mixed> $data Optional error data.
	 * @return array<string,mixed>
	 */
	private static function error_routed( $id, int $code, string $message, int $status, array $data = array() ): array {
		$error = array(
			'code'    => $code,
			'message' => $message,
		);
		if ( ! empty( $data ) ) {
			$error['data'] = $data;
		}

		return array(
			'http_status' => $status,
			'body'        => array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => $error,
			),
		);
	}

	private static function is_list_array( array $value ): bool {
		if ( array() === $value ) {
			return false;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
