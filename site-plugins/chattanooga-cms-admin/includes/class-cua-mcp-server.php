<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_MCP_Server {
	const REST_NAMESPACE = 'chattanooga-cms-admin/v1';
	const REST_ROUTE     = '/mcp';
	const ABILITY_PREFIX = 'chattanooga-cms-admin/';
	const TOOL_PREFIX    = 'cmsa.';
	const PROTOCOL_VERSION = '2026-07-28';
	const TOOL_PAGE_SIZE = 50;

	public static function register_route() {
		if ( ! CUA_MCP_Settings_Page::is_enabled() ) {
			return;
		}

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				// Streamable HTTP permits a server to omit server-to-client SSE. In
				// that mode GET remains a defined MCP endpoint and returns 405 with
				// the allowed method instead of falling through to a WordPress 404.
				'methods'             => array( WP_REST_Server::CREATABLE, WP_REST_Server::READABLE ),
				'callback'            => array( __CLASS__, 'handle_request' ),
				'permission_callback' => array( __CLASS__, 'authorize_request' ),
			)
		);
	}

	public static function authorize_request( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'cmsa_mcp_authentication_required',
				'Authenticated WordPress administrator access is required.',
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'cmsa_mcp_forbidden',
				'The authenticated WordPress user does not have administrator authority.',
				array( 'status' => 403 )
			);
		}

		$origin = trim( (string) $request->get_header( 'origin' ) );
		if ( '' !== $origin && ! CUA_MCP_Settings_Page::is_origin_allowed( $origin ) ) {
			return new WP_Error(
				'cmsa_mcp_origin_forbidden',
				'The request Origin is not permitted for this MCP endpoint.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	public static function handle_request( WP_REST_Request $request ) {
		if ( 'GET' === strtoupper( $request->get_method() ) ) {
			$response = new WP_REST_Response( null, 405 );
			$response->header( 'Allow', 'POST' );
			return $response;
		}

		$payload = self::decode_request( $request );
		if ( is_wp_error( $payload ) ) {
			return self::protocol_error_response( null, -32700, $payload->get_error_message(), 400 );
		}

		if ( ! is_array( $payload ) || self::is_list_array( $payload ) ) {
			return self::protocol_error_response( null, -32600, 'MCP requests must be a single JSON-RPC object.', 400 );
		}

		$id = array_key_exists( 'id', $payload ) ? $payload['id'] : null;
		if ( '2.0' !== ( $payload['jsonrpc'] ?? null ) || ! isset( $payload['method'] ) || ! is_string( $payload['method'] ) || '' === $payload['method'] ) {
			return self::protocol_error_response( $id, -32600, 'Invalid JSON-RPC request.', 400 );
		}

		$method = $payload['method'];
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();

		$header_error = self::validate_headers( $request, $method, $params );
		if ( is_wp_error( $header_error ) ) {
			return self::protocol_error_response( $id, -32020, $header_error->get_error_message(), 400 );
		}

		$version_error = self::validate_version( $request, $method, $params );
		if ( is_wp_error( $version_error ) ) {
			return self::protocol_error_response(
				$id,
				-32022,
				$version_error->get_error_message(),
				400,
				array( 'supportedVersions' => array( self::PROTOCOL_VERSION ) )
			);
		}

		if ( 'notifications/initialized' === $method && ! array_key_exists( 'id', $payload ) ) {
			return self::notification_response();
		}

		if ( ! array_key_exists( 'id', $payload ) ) {
			return self::protocol_error_response( null, -32600, 'A request id is required for this MCP method.', 400 );
		}

		switch ( $method ) {
			case 'initialize':
				return self::success_response( $id, self::initialize_result() );

			case 'server/discover':
				return self::success_response( $id, self::discover_result() );

			case 'tools/list':
				$tools = self::list_tools_result( $params );
				if ( is_wp_error( $tools ) ) {
					return self::protocol_error_response( $id, -32602, $tools->get_error_message(), 400 );
				}
				return self::success_response( $id, $tools );

			case 'tools/call':
				$call = self::call_tool( $params );
				if ( is_wp_error( $call ) ) {
					return self::success_response( $id, self::tool_error_result( $call ) );
				}
				return self::success_response( $id, $call );

			default:
				return self::protocol_error_response( $id, -32601, 'Method not found.', 404 );
		}
	}

	private static function decode_request( WP_REST_Request $request ) {
		$body = (string) $request->get_body();
		if ( '' === trim( $body ) ) {
			return new WP_Error( 'cmsa_mcp_empty_body', 'A JSON request body is required.' );
		}

		$payload = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'cmsa_mcp_invalid_json', 'The request body is not valid JSON.' );
		}
		return $payload;
	}

	private static function validate_version( WP_REST_Request $request, $method, array $params ) {
		$header_version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
		$body_version   = '';
		if ( isset( $params['protocolVersion'] ) ) {
			$body_version = trim( (string) $params['protocolVersion'] );
		}
		if ( isset( $params['_meta'] ) && is_array( $params['_meta'] ) ) {
			$meta_version = isset( $params['_meta']['io.modelcontextprotocol/protocolVersion'] )
				? trim( (string) $params['_meta']['io.modelcontextprotocol/protocolVersion'] )
				: '';
			if ( '' !== $body_version && '' !== $meta_version && $body_version !== $meta_version ) {
				return new WP_Error( 'cmsa_mcp_version_mismatch', 'MCP protocol version declarations do not match.' );
			}
		if ( '' === $body_version ) {
				$body_version = $meta_version;
			}
		}
		if ( 'initialize' === $method && '' === $body_version ) {
			return new WP_Error( 'cmsa_mcp_version_missing', 'initialize requires params.protocolVersion.' );
		}

		if ( '' !== $header_version && self::PROTOCOL_VERSION !== $header_version ) {
			return new WP_Error(
				'cmsa_mcp_version_mismatch',
				'MCP protocol version ' . self::PROTOCOL_VERSION . ' is required.'
			);
		}
		if ( '' !== $body_version && self::PROTOCOL_VERSION !== $body_version ) {
			return new WP_Error( 'cmsa_mcp_version_mismatch', 'MCP protocol version ' . self::PROTOCOL_VERSION . ' is required.' );
		}
		return true;
	}

	private static function validate_headers( WP_REST_Request $request, $method, array $params ) {
		$header_method = trim( (string) $request->get_header( 'mcp-method' ) );
		if ( '' !== $header_method && $method !== $header_method ) {
			return new WP_Error( 'cmsa_mcp_method_header_mismatch', 'Mcp-Method does not match the JSON-RPC method.' );
		}

		if ( 'tools/call' === $method ) {
			$name = isset( $params['name'] ) ? (string) $params['name'] : '';
			$header_name = trim( (string) $request->get_header( 'mcp-name' ) );
			if ( '' !== $header_name && $name !== $header_name ) {
				return new WP_Error( 'cmsa_mcp_name_header_mismatch', 'Mcp-Name does not match params.name for tools/call.' );
			}
		}

		return true;
	}

	private static function discover_result() {
		return array(
			'supportedVersions' => array( self::PROTOCOL_VERSION ),
			'capabilities'      => array(
				'tools' => array(
					'listChanged' => false,
				),
			),
			'instructions'      => 'Authenticated WordPress site-operation tools. Use read-only tools for inspection and mutating tools only for explicitly authorized site changes.',
			'ttlMs'             => 30000,
			'cacheScope'        => 'private',
		);
	}

	private static function initialize_result() {
		return array(
			'protocolVersion' => self::PROTOCOL_VERSION,
			'capabilities'    => array(
				'tools' => array(
					'listChanged' => false,
				),
			),
			'serverInfo'      => self::server_info(),
			'instructions'    => 'Authenticated WordPress site-operation tools. Use read-only tools for inspection and mutating tools only for explicitly authorized site changes.',
		);
	}

	private static function list_tools_result( array $params ) {
		$all_tools = array_values( self::tools() );
		$offset    = 0;
		$cursor    = isset( $params['cursor'] ) ? trim( (string) $params['cursor'] ) : '';

		if ( '' !== $cursor ) {
			$decoded = base64_decode( strtr( $cursor, '-_', '+/' ), true );
			if ( false === $decoded || ! preg_match( '/^cmsa-tools:(\\d+)$/', $decoded, $matches ) ) {
				return new WP_Error( 'cmsa_mcp_invalid_cursor', 'The tools/list cursor is invalid.' );
			}
			$offset = (int) $matches[1];
			if ( $offset < 0 || $offset > count( $all_tools ) ) {
				return new WP_Error( 'cmsa_mcp_invalid_cursor', 'The tools/list cursor is outside the current tool set.' );
			}
		}

		$result = array(
			'tools'      => array_slice( $all_tools, $offset, self::TOOL_PAGE_SIZE ),
			'ttlMs'      => 30000,
			'cacheScope' => 'private',
		);
		$next_offset = $offset + count( $result['tools'] );
		if ( $next_offset < count( $all_tools ) ) {
			$result['nextCursor'] = rtrim( strtr( base64_encode( 'cmsa-tools:' . $next_offset ), '+/', '-_' ), '=' );
		}
		return $result;
	}

	private static function tools() {
		$tools = array();
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return $tools;
		}

		foreach ( wp_get_abilities() as $ability ) {
			if ( ! $ability instanceof WP_Ability || ! self::ability_is_mcp_public( $ability ) ) {
				continue;
			}

			$ability_name = $ability->get_name();
			if ( 0 !== strpos( $ability_name, self::ABILITY_PREFIX ) ) {
				continue;
			}

			$tool_name = self::tool_name( $ability_name );
			if ( ! self::is_site_surface_tool( $tool_name ) ) {
				continue;
			}
			$schema = $ability->get_input_schema();
			if ( ! is_array( $schema ) ) {
				$schema = array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				);
			}

			$tool = array(
				'name'        => $tool_name,
				'title'       => $ability->get_label(),
				'description' => $ability->get_description(),
				'inputSchema' => $schema,
				'annotations' => self::tool_annotations( $ability ),
			);

			$output_schema = $ability->get_output_schema();
			if ( is_array( $output_schema ) ) {
				$tool['outputSchema'] = $output_schema;
			}

			$tools[ $tool_name ] = $tool;
		}

		ksort( $tools, SORT_STRING );
		return $tools;
	}

	private static function call_tool( array $params ) {
		$name = isset( $params['name'] ) ? trim( (string) $params['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'cmsa_mcp_tool_name_required', 'A tool name is required.' );
		}

		$ability = self::ability_for_tool( $name );
		if ( ! $ability instanceof WP_Ability ) {
			return new WP_Error( 'cmsa_mcp_tool_not_found', 'The requested MCP tool is not available.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'cmsa_mcp_tool_forbidden', 'Administrator authority is required to call this tool.' );
		}

		$arguments = isset( $params['arguments'] ) ? $params['arguments'] : array();
		if ( ! is_array( $arguments ) || ( ! empty( $arguments ) && self::is_list_array( $arguments ) ) ) {
			return new WP_Error( 'cmsa_mcp_invalid_tool_arguments', 'Tool arguments must be a JSON object.' );
		}

		$has_input = is_array( $ability->get_input_schema() );
		$permission = $has_input ? $ability->check_permissions( $arguments ) : $ability->check_permissions();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( ! $permission ) {
			return new WP_Error( 'cmsa_mcp_tool_forbidden', 'The selected WordPress ability denied this request.' );
		}

		try {
			$result = $has_input ? $ability->execute( $arguments ) : $ability->execute();
		} catch ( Throwable $error ) {
			return new WP_Error( 'cmsa_mcp_tool_exception', 'The selected WordPress ability failed during execution.' );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => self::json_text( $result ),
				),
			),
			'isError' => false,
		);

		if ( is_array( $result ) || is_object( $result ) ) {
			$response['structuredContent'] = $result;
		}

		return $response;
	}

	private static function tool_error_result( WP_Error $error ) {
		$message = $error->get_error_message();
		$code    = $error->get_error_code();

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => '' !== $message ? $message : 'The WordPress administration tool returned an error.',
				),
			),
			'isError' => true,
			'_meta'   => array(
				'chattanooga-cms-admin/errorCode' => (string) $code,
			),
		);
	}

	private static function ability_for_tool( $tool_name ) {
		if ( 0 !== strpos( $tool_name, self::TOOL_PREFIX ) || ! function_exists( 'wp_get_ability' ) ) {
			return null;
		}

		// Only generic site operations and runtime-discovered external facades are
		// exposed through MCP. Chattanooga's administrator/control-plane abilities
		// remain WordPress-internal capabilities.
		if ( ! self::is_site_surface_tool( $tool_name ) ) {
			return null;
		}

		$short = substr( $tool_name, strlen( self::TOOL_PREFIX ) );
		if ( '' === $short || ! preg_match( '/^[A-Za-z0-9_.-]+$/', $short ) ) {
			return null;
		}

		$ability = wp_get_ability( self::ABILITY_PREFIX . $short );
		if ( ! $ability instanceof WP_Ability || ! self::ability_is_mcp_public( $ability ) ) {
			return null;
		}
		return $ability;
	}

	private static function ability_is_mcp_public( WP_Ability $ability ) {
		$meta = $ability->get_meta();
		return isset( $meta['mcp'] )
			&& is_array( $meta['mcp'] )
			&& true === ( $meta['mcp']['public'] ?? false );
	}

	private static function is_site_surface_tool( $tool_name ) {
		if ( in_array(
			$tool_name,
			array(
				self::TOOL_PREFIX . 'catalog',
				self::TOOL_PREFIX . 'read-bridge',
				self::TOOL_PREFIX . 'write-bridge',
			),
			true
		) ) {
			return true;
		}

		return 1 === preg_match( '/^cmsa\\.(?:bridge|rest)-[a-f0-9]{24}$/', (string) $tool_name );
	}

	private static function tool_name( $ability_name ) {
		$short = substr( $ability_name, strlen( self::ABILITY_PREFIX ) );
		return self::TOOL_PREFIX . preg_replace( '/[^A-Za-z0-9_.-]/', '-', $short );
	}

	private static function tool_annotations( WP_Ability $ability ) {
		$meta = $ability->get_meta();
		$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();

		$result = array();
		if ( array_key_exists( 'readonly', $annotations ) && null !== $annotations['readonly'] ) {
			$result['readOnlyHint'] = (bool) $annotations['readonly'];
		}
		if ( array_key_exists( 'destructive', $annotations ) && null !== $annotations['destructive'] ) {
			$result['destructiveHint'] = (bool) $annotations['destructive'];
		}
		if ( array_key_exists( 'idempotent', $annotations ) && null !== $annotations['idempotent'] ) {
			$result['idempotentHint'] = (bool) $annotations['idempotent'];
		}
		return $result;
	}

	private static function success_response( $id, array $result ) {
		$result['resultType'] = 'complete';
		$result['_meta'] = isset( $result['_meta'] ) && is_array( $result['_meta'] ) ? $result['_meta'] : array();
		$result['_meta']['io.modelcontextprotocol/serverInfo'] = self::server_info();

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

	private static function notification_response() {
		return new WP_REST_Response( null, 202 );
	}

	private static function protocol_error_response( $id, $code, $message, $status, array $data = array() ) {
		$error = array(
			'code'    => (int) $code,
			'message' => (string) $message,
		);
		if ( ! empty( $data ) ) {
			$error['data'] = $data;
		}

		$response = new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => $error,
				'_meta'   => array(
					'io.modelcontextprotocol/serverInfo' => self::server_info(),
				),
			),
			(int) $status
		);
		$response->header( 'MCP-Protocol-Version', self::PROTOCOL_VERSION );
		return $response;
	}

	private static function server_info() {
		return array(
			'name'       => 'chattanooga-cms-admin',
			'title'      => 'Chattanooga CMS Admin',
			'version'    => defined( 'CUA_VERSION' ) ? CUA_VERSION : 'unknown',
			'websiteUrl' => home_url( '/' ),
		);
	}

	private static function json_text( $value ) {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false !== $encoded ) {
			return $encoded;
		}
		return is_scalar( $value ) ? (string) $value : 'null';
	}

	private static function is_list_array( array $value ) {
		$index = 0;
		foreach ( $value as $key => $_item ) {
			if ( $index !== $key ) {
				return false;
			}
			++$index;
		}
		return true;
	}
}
