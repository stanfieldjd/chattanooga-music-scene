<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility transport for clients built around the WordPress MCP Adapter
 * default-server contract.
 *
 * The native Chattanooga MCP transport remains authoritative. This class only
 * registers the standard adapter route when another component has not already
 * provided it.
 */
final class CUA_MCP_Adapter_Compat {
	const REST_NAMESPACE = 'mcp';
	const REST_ROUTE     = '/mcp-adapter-default-server';

	private static $supported_versions = array(
		'2024-11-05',
		'2025-03-26',
		'2025-06-18',
		'2025-11-25',
	);

	public static function register_route() {
		$route_path = '/' . self::REST_NAMESPACE . self::REST_ROUTE;
		$routes     = rest_get_server()->get_routes();

		// Coexist with the official WordPress MCP Adapter when it is installed.
		if ( isset( $routes[ $route_path ] ) ) {
			return;
		}

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
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'cmsa_mcp_adapter_authentication_required',
				'Authenticated WordPress administrator access is required.',
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'cmsa_mcp_adapter_forbidden',
				'The authenticated WordPress user does not have administrator authority.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	public static function handle_request( WP_REST_Request $request ) {
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

		if ( ! array_key_exists( 'id', $payload ) ) {
			if ( 'notifications/initialized' === $method ) {
				return new WP_REST_Response( null, 202 );
			}
			return self::protocol_error_response( null, -32600, 'Unsupported notification.', 400 );
		}

		switch ( $method ) {
			case 'initialize':
				$version = isset( $params['protocolVersion'] ) ? trim( (string) $params['protocolVersion'] ) : '';
				if ( ! in_array( $version, self::$supported_versions, true ) ) {
					return self::protocol_error_response(
						$id,
						-32602,
						'Unsupported MCP protocol version.',
						400,
						array( 'supportedVersions' => self::$supported_versions )
					);
				}

				$response = self::success_response( $id, self::initialize_result( $version ), $version );
				$response->header( 'Mcp-Session-Id', 'cmsa-' . wp_generate_uuid4() );
				return $response;

			case 'ping':
				return self::success_response( $id, array() );

			case 'tools/list':
				return self::success_response(
					$id,
					array( 'tools' => array_values( self::adapter_tools() ) )
				);

			case 'tools/call':
				$result = self::call_adapter_tool( $params );
				if ( is_wp_error( $result ) ) {
					$result = self::tool_error_result( $result );
				}
				return self::success_response( $id, $result );

			default:
				return self::protocol_error_response( $id, -32601, 'Method not found.', 404 );
		}
	}

	private static function adapter_tools() {
		return array(
			'mcp-adapter-discover-abilities' => array(
				'name'        => 'mcp-adapter-discover-abilities',
				'description' => 'Discover all available public WordPress abilities.',
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
			),
			'mcp-adapter-get-ability-info' => array(
				'name'        => 'mcp-adapter-get-ability-info',
				'description' => 'Get detailed information about a public WordPress ability.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'ability_name' => array(
							'type'        => 'string',
							'description' => 'The full name of the ability.',
						),
					),
					'required'   => array( 'ability_name' ),
				),
				'annotations' => array(
					'readOnlyHint'    => true,
					'destructiveHint' => false,
					'idempotentHint'  => true,
				),
			),
			'mcp-adapter-execute-ability' => array(
				'name'        => 'mcp-adapter-execute-ability',
				'description' => 'Execute a public WordPress ability with the provided parameters.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'ability_name' => array(
							'type'        => 'string',
							'description' => 'The full name of the ability to execute.',
						),
						'parameters' => array(
							'type'        => 'object',
							'description' => 'Parameters to pass to the ability.',
						),
					),
					'required'   => array( 'ability_name', 'parameters' ),
				),
				'annotations' => array(
					'readOnlyHint'    => false,
					'destructiveHint' => true,
					'idempotentHint'  => false,
					'openWorldHint'   => true,
				),
			),
		);
	}

	private static function call_adapter_tool( array $params ) {
		$name      = isset( $params['name'] ) ? trim( (string) $params['name'] ) : '';
		$arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		switch ( $name ) {
			case 'mcp-adapter-discover-abilities':
				return self::tool_result( self::discover_abilities() );

			case 'mcp-adapter-get-ability-info':
				return self::tool_result( self::get_ability_info( $arguments ) );

			case 'mcp-adapter-execute-ability':
				return self::tool_result( self::execute_ability( $arguments ) );

			default:
				return new WP_Error( 'cmsa_mcp_adapter_tool_not_found', 'The requested MCP Adapter tool is not available.' );
		}
	}

	private static function discover_abilities() {
		$items = array();
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array( 'abilities' => $items );
		}

		foreach ( wp_get_abilities() as $ability ) {
			if ( ! $ability instanceof WP_Ability || ! self::ability_is_public_tool( $ability ) ) {
				continue;
			}

			$items[] = array(
				'name'        => $ability->get_name(),
				'label'       => $ability->get_label(),
				'description' => $ability->get_description(),
			);
		}

		usort(
			$items,
			static function ( $left, $right ) {
				return strcmp( (string) $left['name'], (string) $right['name'] );
			}
		);

		return array( 'abilities' => $items );
	}

	private static function get_ability_info( array $arguments ) {
		$name = isset( $arguments['ability_name'] ) ? trim( (string) $arguments['ability_name'] ) : '';
		if ( '' === $name ) {
			return array( 'error' => 'Ability name is required' );
		}

		$ability = self::public_ability( $name );
		if ( ! $ability instanceof WP_Ability ) {
			return array( 'error' => "Ability '{$name}' not found or is not exposed through MCP" );
		}

		$info = array(
			'name'         => $ability->get_name(),
			'label'        => $ability->get_label(),
			'description'  => $ability->get_description(),
			'input_schema' => $ability->get_input_schema(),
		);

		$output_schema = $ability->get_output_schema();
		if ( ! empty( $output_schema ) ) {
			$info['output_schema'] = $output_schema;
		}

		$meta = $ability->get_meta();
		if ( ! empty( $meta ) ) {
			$info['meta'] = $meta;
		}

		return $info;
	}

	private static function execute_ability( array $arguments ) {
		$name       = isset( $arguments['ability_name'] ) ? trim( (string) $arguments['ability_name'] ) : '';
		$parameters = isset( $arguments['parameters'] ) && is_array( $arguments['parameters'] ) ? $arguments['parameters'] : array();

		if ( '' === $name ) {
			return array(
				'success' => false,
				'error'   => 'Ability name is required',
			);
		}

		$ability = self::public_ability( $name );
		if ( ! $ability instanceof WP_Ability ) {
			return array(
				'success' => false,
				'error'   => "Ability '{$name}' not found or is not exposed through MCP",
			);
		}

		$has_input  = is_array( $ability->get_input_schema() );
		$permission = $has_input ? $ability->check_permissions( $parameters ) : $ability->check_permissions();
		if ( is_wp_error( $permission ) ) {
			return array(
				'success' => false,
				'error'   => $permission->get_error_message(),
			);
		}
		if ( ! $permission ) {
			return array(
				'success' => false,
				'error'   => 'The selected WordPress ability denied this request.',
			);
		}

		try {
			$result = $has_input ? $ability->execute( $parameters ) : $ability->execute();
		} catch ( Throwable $error ) {
			return array(
				'success' => false,
				'error'   => $error->getMessage(),
			);
		}

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'error'   => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'data'    => $result,
		);
	}

	private static function public_ability( $name ) {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return null;
		}

		$ability = wp_get_ability( $name );
		if ( ! $ability instanceof WP_Ability || ! self::ability_is_public_tool( $ability ) ) {
			return null;
		}

		return $ability;
	}

	private static function ability_is_public_tool( WP_Ability $ability ) {
		$meta     = $ability->get_meta();
		$mcp_meta = $meta['mcp'] ?? array();

		if ( ! is_array( $mcp_meta ) ) {
			return false;
		}

		$public = isset( $mcp_meta['public'] ) ? (bool) $mcp_meta['public'] : true === ( $meta['public'] ?? false );
		if ( ! $public ) {
			return false;
		}

		$type = isset( $mcp_meta['type'] ) ? (string) $mcp_meta['type'] : 'tool';
		if ( ! in_array( $type, array( 'tool', 'resource', 'prompt' ), true ) ) {
			$type = 'tool';
		}

		return 'tool' === $type;
	}

	private static function initialize_result( $version ) {
		return array(
			'protocolVersion' => $version,
			'capabilities'    => array(
				'tools' => array(
					'listChanged' => false,
				),
			),
			'serverInfo'      => array(
				'name'    => 'mcp-adapter-default-server',
				'version' => defined( 'CUA_VERSION' ) ? CUA_VERSION : 'unknown',
			),
			'instructions'    => 'WordPress MCP Adapter compatibility transport provided by Chattanooga CMS Admin.',
		);
	}

	private static function tool_result( array $result ) {
		$response = array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => self::json_text( $result ),
				),
			),
			'isError'           => false,
			'structuredContent' => $result,
		);

		return $response;
	}

	private static function tool_error_result( WP_Error $error ) {
		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $error->get_error_message(),
				),
			),
			'isError' => true,
		);
	}

	private static function success_response( $id, array $result, $version = '' ) {
		$response = new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'result'  => $result,
			),
			200
		);

		if ( '' !== $version ) {
			$response->header( 'MCP-Protocol-Version', $version );
		}

		return $response;
	}

	private static function protocol_error_response( $id, $code, $message, $status, array $data = array() ) {
		$error = array(
			'code'    => (int) $code,
			'message' => (string) $message,
		);
		if ( ! empty( $data ) ) {
			$error['data'] = $data;
		}

		return new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => $error,
			),
			(int) $status
		);
	}

	private static function decode_request( WP_REST_Request $request ) {
		$body = (string) $request->get_body();
		if ( '' === trim( $body ) ) {
			return new WP_Error( 'cmsa_mcp_adapter_empty_body', 'A JSON request body is required.' );
		}

		$payload = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'cmsa_mcp_adapter_invalid_json', 'The request body is not valid JSON.' );
		}

		return $payload;
	}

	private static function json_text( $value ) {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return false !== $encoded ? $encoded : 'null';
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
