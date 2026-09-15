<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Minimal_MCP_HTTP_Transport {
	private const REST_NAMESPACE   = 'minimal-mcp/v1';
	private const REST_ROUTE       = '/mcp';
	private const MAX_SAFE_INTEGER = 9007199254740991;

	public static function bootstrap(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	public static function register_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_request' ),
					'permission_callback' => array( __CLASS__, 'authorize_request' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE . ',' . WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'method_not_allowed' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public static function authorize_request( WP_REST_Request $request ) {
		$guard = self::validate_host_and_origin( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
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

	public static function method_not_allowed( WP_REST_Request $request ) {
		$guard = self::validate_host_and_origin( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$response = self::response( self::error_routed( null, -32000, 'Method not allowed.', 405 ) );
		$response->header( 'Allow', 'POST' );
		return $response;
	}

	public static function handle_request( WP_REST_Request $request ): WP_REST_Response {
		if ( ! self::is_json_content_type( (string) $request->get_header( 'content-type' ) ) ) {
			return self::response( self::error_routed( null, -32000, 'Unsupported Media Type: Content-Type must be application/json.', 415 ) );
		}

		if ( ! self::accepts_mcp_response_types( (string) $request->get_header( 'accept' ) ) ) {
			return self::response( self::error_routed( null, -32000, 'Not Acceptable: Accept must include application/json and text/event-stream.', 406 ) );
		}

		$payload = json_decode( (string) $request->get_body(), true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return self::response( self::error_routed( null, -32700, 'Parse error: invalid JSON.', 400 ) );
		}
		if ( ! is_array( $payload ) ) {
			return self::response( self::error_routed( null, -32600, 'Invalid JSON-RPC request.', 400 ) );
		}
		if ( self::is_list_array( $payload ) ) {
			return self::response( self::error_routed( null, -32600, 'JSON-RPC batching is not supported.', 400 ) );
		}
		if ( '2.0' !== ( $payload['jsonrpc'] ?? null ) ) {
			return self::response( self::error_routed( null, -32600, 'Invalid JSON-RPC request.', 400 ) );
		}

		$id = array_key_exists( 'id', $payload ) ? $payload['id'] : null;

		if ( ! array_key_exists( 'id', $payload ) ) {
			if ( self::is_valid_notification( $payload ) ) {
				return self::accepted_response();
			}
			return self::response( self::error_routed( null, -32600, 'Invalid JSON-RPC notification.', 400 ) );
		}

		if ( ! isset( $payload['method'] ) ) {
			return self::response( self::error_routed( $id, -32600, 'Client-to-server JSON-RPC responses are not permitted on this MCP transport.', 400 ) );
		}
		if ( ! is_string( $payload['method'] ) || '' === $payload['method'] ) {
			return self::response( self::error_routed( $id, -32600, 'Invalid JSON-RPC method.', 400 ) );
		}

		if ( array_key_exists( 'params', $payload ) && ( ! is_array( $payload['params'] ) || self::is_list_array( $payload['params'] ) ) ) {
			return self::response( self::error_routed( $id, -32602, 'Invalid params: params must be a JSON object.', 200 ) );
		}

		$method = $payload['method'];
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
		$header_version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
		$meta_present   = array_key_exists( '_meta', $params );
		$meta           = $meta_present && is_array( $params['_meta'] ) && ! self::is_list_array( $params['_meta'] ) ? $params['_meta'] : null;

		if ( $meta_present && null === $meta ) {
			return self::error_routed( $id, -32602, 'Invalid params: params._meta must be a JSON object.', 400 );
		}

		if ( null === $meta || ! array_key_exists( 'io.modelcontextprotocol/protocolVersion', $meta ) ) {
			if ( CMSA_Minimal_MCP_Request_Router::PROTOCOL_VERSION === $header_version ) {
				return self::error_routed( $id, -32602, 'Invalid params: params._meta protocolVersion is required for modern MCP requests.', 400 );
			}
			$data = array( 'supported' => array( CMSA_Minimal_MCP_Request_Router::PROTOCOL_VERSION ) );
			if ( '' !== $header_version ) {
				$data['requested'] = $header_version;
			}
			return self::error_routed( $id, -32022, 'Unsupported MCP protocol version.', 400, $data );
		}

		$body_version_value = $meta['io.modelcontextprotocol/protocolVersion'];
		if ( ! is_string( $body_version_value ) || '' === trim( $body_version_value ) ) {
			return self::error_routed( $id, -32602, 'Invalid params: protocolVersion must be a non-empty string.', 400 );
		}
		$body_version = trim( $body_version_value );

		if ( '' === $header_version ) {
			return self::error_routed( $id, -32020, 'MCP-Protocol-Version header is required.', 400 );
		}
		if ( $header_version !== $body_version ) {
			return self::error_routed( $id, -32020, 'MCP-Protocol-Version must match params._meta protocolVersion.', 400 );
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

		if ( ! array_key_exists( 'io.modelcontextprotocol/clientCapabilities', $meta ) || ! is_array( $meta['io.modelcontextprotocol/clientCapabilities'] ) || self::is_list_array( $meta['io.modelcontextprotocol/clientCapabilities'] ) ) {
			return self::error_routed( $id, -32602, 'Invalid params: clientCapabilities is required and must be a JSON object.', 400 );
		}
		if ( array_key_exists( 'io.modelcontextprotocol/clientInfo', $meta ) && ! self::valid_client_info( $meta['io.modelcontextprotocol/clientInfo'] ) ) {
			return self::error_routed( $id, -32602, 'Invalid params: clientInfo must contain non-empty string name and version fields.', 400 );
		}

		$header_method = trim( (string) $request->get_header( 'mcp-method' ) );
		if ( '' === $header_method || $method !== $header_method ) {
			return self::error_routed( $id, -32020, 'Mcp-Method must be present and match the JSON-RPC method.', 400 );
		}

		if ( 'tools/call' === $method ) {
			$name_value  = $params['name'] ?? null;
			$name        = is_string( $name_value ) ? $name_value : '';
			$header_name = (string) $request->get_header( 'mcp-name' );
			$decoded     = self::decode_header_value( $header_name );
			if ( is_wp_error( $decoded ) || '' === $name || '' === $header_name || $name !== $decoded ) {
				return self::error_routed( $id, -32020, 'Mcp-Name must be present, valid, and match params.name for tools/call.', 400 );
			}

			$arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) && ! self::is_list_array( $params['arguments'] ) ? $params['arguments'] : array();
			$parameter_error = self::validate_tool_parameter_headers( $request, $name, $arguments );
			if ( is_wp_error( $parameter_error ) ) {
				return self::error_routed( $id, -32020, $parameter_error->get_error_message(), 400 );
			}
		}

		return null;
	}

	/**
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
			if ( is_wp_error( $decoded ) || ! self::mirrored_value_matches( $value, $type, $expected, $decoded ) ) {
				return new WP_Error( 'minimal_mcp_parameter_header_mismatch', $header_name . ' must match the corresponding tool argument after MCP header decoding.' );
			}
		}
		return true;
	}

	/** @param mixed $body_value */
	private static function mirrored_value_matches( $body_value, string $type, string $expected, string $decoded ): bool {
		if ( 'integer' !== $type ) {
			return $expected === $decoded;
		}
		if ( ! is_int( $body_value ) || ! preg_match( '/^[+-]?(?:\d+\.?\d*|\.\d+)$/', $decoded ) ) {
			return false;
		}
		return (float) $body_value === (float) $decoded;
	}

	/** @param array<string,mixed> $arguments @param array<int,string> $path @return mixed */
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

	/** @param mixed $value @return string|WP_Error */
	private static function parameter_string_value( $value, string $type ) {
		switch ( $type ) {
			case 'string':
				return is_string( $value ) ? $value : new WP_Error( 'minimal_mcp_parameter_type_mismatch', 'Mirrored string argument has a non-string body value.' );
			case 'boolean':
				return is_bool( $value ) ? ( $value ? 'true' : 'false' ) : new WP_Error( 'minimal_mcp_parameter_type_mismatch', 'Mirrored boolean argument has a non-boolean body value.' );
			case 'integer':
				if ( ! is_int( $value ) || $value < -self::MAX_SAFE_INTEGER || $value > self::MAX_SAFE_INTEGER ) {
					return new WP_Error( 'minimal_mcp_parameter_type_mismatch', 'Mirrored integer argument must be an IEEE-754 safe integer.' );
				}
				return (string) $value;
		}
		return new WP_Error( 'minimal_mcp_parameter_type_mismatch', 'Unsupported mirrored parameter type.' );
	}

	/** @return string|WP_Error */
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
		if ( $value !== trim( $value, " \t" ) || preg_match( '/[^\x09\x20-\x7E]/', $value ) ) {
			return new WP_Error( 'minimal_mcp_invalid_header_encoding', 'MCP header values outside safe plain ASCII must use the Base64 sentinel encoding.' );
		}
		return $value;
	}

	private static function validate_host_and_origin( WP_REST_Request $request ) {
		$host = trim( (string) $request->get_header( 'host' ) );
		if ( '' === $host || ! self::host_is_allowed( $host ) ) {
			return new WP_Error( 'minimal_mcp_host_forbidden', 'The request Host is not permitted for this MCP endpoint.', array( 'status' => 403 ) );
		}
		$origin = trim( (string) $request->get_header( 'origin' ) );
		if ( '' !== $origin && ! self::origin_is_allowed( $origin ) ) {
			return new WP_Error( 'minimal_mcp_origin_forbidden', 'The request Origin is not permitted for this MCP endpoint.', array( 'status' => 403 ) );
		}
		return true;
	}

	private static function host_is_allowed( string $host_header ): bool {
		$hostname = self::hostname_from_host_header( $host_header );
		if ( null === $hostname ) {
			return false;
		}
		$allowed = array_filter( array( self::hostname_from_url( home_url( '/' ) ), self::hostname_from_url( site_url( '/' ) ) ) );
		$allowed = apply_filters( 'minimal_mcp_allowed_hosts', array_values( array_unique( $allowed ) ) );
		return is_array( $allowed ) && in_array( $hostname, array_map( 'strtolower', $allowed ), true );
	}

	private static function hostname_from_host_header( string $host_header ): ?string {
		if ( preg_match( '/[@\/\\\\,\s]/', $host_header ) ) {
			return null;
		}
		$parts = wp_parse_url( 'http://' . $host_header );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return null;
		}
		return strtolower( trim( (string) $parts['host'], '[]' ) );
	}

	private static function hostname_from_url( string $url ): ?string {
		$parts = wp_parse_url( $url );
		return is_array( $parts ) && ! empty( $parts['host'] ) ? strtolower( trim( (string) $parts['host'], '[]' ) ) : null;
	}

	private static function origin_is_allowed( string $origin ): bool {
		$normalized = self::normalize_origin( $origin );
		if ( null === $normalized ) {
			return false;
		}
		$allowed = array_filter( array( self::normalize_origin( home_url( '/' ) ), self::normalize_origin( site_url( '/' ) ) ) );
		$allowed = apply_filters( 'minimal_mcp_allowed_origins', array_values( array_unique( $allowed ) ) );
		return is_array( $allowed ) && in_array( $normalized, $allowed, true );
	}

	private static function normalize_origin( string $url ): ?string {
		if ( false !== strpos( $url, '@' ) ) {
			return null;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
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

	private static function is_json_content_type( string $value ): bool {
		return 'application/json' === self::media_type_essence( $value );
	}

	private static function accepts_mcp_response_types( string $value ): bool {
		$accepted = array();
		foreach ( explode( ',', $value ) as $range ) {
			$parts = array_map( 'trim', explode( ';', $range ) );
			$essence = strtolower( array_shift( $parts ) ?? '' );
			$q = 1.0;
			foreach ( $parts as $parameter ) {
				if ( 0 === stripos( $parameter, 'q=' ) ) {
					$q = (float) substr( $parameter, 2 );
				}
			}
			if ( '' !== $essence && $q > 0 ) {
				$accepted[] = $essence;
			}
		}
		return in_array( 'application/json', $accepted, true ) && in_array( 'text/event-stream', $accepted, true );
	}

	private static function media_type_essence( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$semicolon = strpos( $value, ';' );
		if ( false !== $semicolon ) {
			$value = substr( $value, 0, $semicolon );
		}
		return strtolower( trim( $value ) );
	}

	/** @param array<string,mixed> $payload */
	private static function is_valid_notification( array $payload ): bool {
		if ( '2.0' !== ( $payload['jsonrpc'] ?? null ) || ! isset( $payload['method'] ) || ! is_string( $payload['method'] ) || '' === $payload['method'] ) {
			return false;
		}
		return ! array_key_exists( 'params', $payload ) || ( is_array( $payload['params'] ) && ! self::is_list_array( $payload['params'] ) );
	}

	private static function valid_client_info( $value ): bool {
		return is_array( $value )
			&& ! self::is_list_array( $value )
			&& isset( $value['name'], $value['version'] )
			&& is_string( $value['name'] )
			&& is_string( $value['version'] )
			&& '' !== trim( $value['name'] )
			&& '' !== trim( $value['version'] );
	}

	private static function accepted_response(): WP_REST_Response {
		$response = new WP_REST_Response( null, 202 );
		$response->header( 'MCP-Protocol-Version', CMSA_Minimal_MCP_Request_Router::PROTOCOL_VERSION );
		return $response;
	}

	/** @param array<string,mixed> $routed */
	private static function response( array $routed ): WP_REST_Response {
		$response = new WP_REST_Response( $routed['body'] ?? array(), isset( $routed['http_status'] ) ? (int) $routed['http_status'] : 500 );
		$response->header( 'MCP-Protocol-Version', CMSA_Minimal_MCP_Request_Router::PROTOCOL_VERSION );
		return $response;
	}

	/** @param mixed $id @param array<string,mixed> $data @return array<string,mixed> */
	private static function error_routed( $id, int $code, string $message, int $status, array $data = array() ): array {
		$error = array( 'code' => $code, 'message' => $message );
		if ( ! empty( $data ) ) {
			$error['data'] = $data;
		}
		return array(
			'http_status' => $status,
			'body' => array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => $error ),
		);
	}

	private static function is_list_array( array $value ): bool {
		return array() !== $value && array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
