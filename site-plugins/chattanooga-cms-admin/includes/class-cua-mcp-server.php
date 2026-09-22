<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_MCP_Server {
	private static $active_sessions = array();
	const REST_NAMESPACE = 'chattanooga-cms-admin/v1';
	const REST_ROUTE     = '/mcp';
	const ABILITY_PREFIX = 'chattanooga-cms-admin/';
	const TOOL_PREFIX    = 'cmsa.';
	const RESOURCE_DISCOVERY_URI = 'chattanooga://mcp-discovery';
	const RESOURCE_CATALOG_URI = 'chattanooga://site-operation-catalog';
	const PROMPT_SITE_OPERATION = 'site-operation-guide';
	const PROTOCOL_VERSION = '2026-07-28';
	const LEGACY_PROTOCOL_VERSION = '2025-11-25';
	const TOOL_PAGE_SIZE = 50;
	/**
	 * Immutable MCP-facing gateway ABI. The complete admin operation catalog is
	 * discovered through these gateways and remains executable by name for
	 * backward compatibility, but it is not expanded into tools/list.
	 */
	const STABLE_GATEWAY_TOOL_NAMES = array(
		'cmsa.discovery',
		'cmsa.stability-check',
		'cmsa.read-bridge',
		'cmsa.write-bridge',
	);
	const SESSION_TTL = HOUR_IN_SECONDS;
	const SESSION_HEADER = 'Mcp-Session-Id';

	public static function register_route() {
		if ( ! CUA_MCP_Settings_Page::is_enabled() ) {
			return;
		}

		self::register_rest_endpoint( self::REST_ROUTE );
	}

	private static function register_rest_endpoint( $route ) {
		register_rest_route(
			self::REST_NAMESPACE,
			$route,
			array(
				// Streamable HTTP permits a server to omit server-to-client SSE. In
				// that mode GET remains a defined MCP endpoint and returns 405 with
				// the allowed method instead of falling through to a WordPress 404.
				'methods'             => array( WP_REST_Server::CREATABLE, WP_REST_Server::READABLE, WP_REST_Server::DELETABLE ),
				'callback'            => array( __CLASS__, 'handle_request' ),
				// Authentication is handled inside the callback so bearer failures can
				// return the MCP WWW-Authenticate challenge and JSON-RPC metadata.
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function authorize_request( WP_REST_Request $request ) {
		$authorization_header = trim( (string) $request->get_header( 'authorization' ) );
		if ( ( '' === $authorization_header || preg_match( '/^Basic\\s/i', $authorization_header ) ) && is_user_logged_in() ) {
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
					'The request Origin is not permitted for cookie-authenticated MCP access.',
					array( 'status' => 403 )
				);
			}
			return true;
		}

		if ( '' !== $authorization_header && ! preg_match( '/^Bearer\\s/i', $authorization_header ) ) {
			return new WP_Error(
				'cmsa_mcp_authentication_required',
				'Only WordPress Basic authentication or the configured Bearer token is accepted.',
				array( 'status' => 401 )
			);
		}

		$authorized = CUA_OAuth_Server::authenticate_bearer( $request );
		if ( is_wp_error( $authorized ) ) {
			return $authorized;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'cmsa_mcp_forbidden',
				'The authenticated WordPress user does not have administrator authority.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	public static function handle_request( WP_REST_Request $request ) {
		$started = microtime( true );
		$http_method = strtoupper( $request->get_method() );
		$session_id  = self::request_session_id( $request );

		if ( 'DELETE' === $http_method || 'GET' === $http_method ) {
			$authorization = self::authorize_with_trace( $request, '' );
			if ( is_wp_error( $authorization ) ) {
				self::audit_request( $request, '', '', 'authentication_failed', self::error_status( $authorization ), $authorization->get_error_code(), $started );
				return self::authentication_error_response( $authorization );
			}
		}

		if ( 'DELETE' === $http_method ) {
			if ( ! self::session_is_valid( $session_id ) ) {
				self::audit_request( $request, '', '', 'protocol_error', 400, 'cmsa_mcp_session_required', $started );
				return self::protocol_error_response( null, -32001, 'A valid MCP session is required to close this endpoint session.', 400 );
			}
			unset( self::$active_sessions[ $session_id ] );
			delete_transient( self::session_key( $session_id ) );
			$response = new WP_REST_Response( null, 204 );
			$response->header( self::SESSION_HEADER, $session_id );
			self::audit_request( $request, 'DELETE', '', 'success', 204, '', $started );
			return $response;
		}

		if ( 'GET' === $http_method ) {
			$response = new WP_REST_Response( null, 405 );
			$response->header( 'Allow', 'POST, DELETE' );
			self::audit_request( $request, 'GET', '', 'method_not_allowed', 405, 'cmsa_mcp_method_not_allowed', $started );
			return $response;
		}

		$payload = self::decode_request( $request );
		if ( is_wp_error( $payload ) ) {
			self::audit_request( $request, '', '', 'protocol_error', 400, $payload->get_error_code(), $started );
			return self::protocol_error_response( null, -32700, $payload->get_error_message(), 400 );
		}

		if ( ! is_array( $payload ) || self::is_list_array( $payload ) ) {
			self::audit_request( $request, '', '', 'protocol_error', 400, 'cmsa_mcp_invalid_request', $started );
			return self::protocol_error_response( null, -32600, 'MCP requests must be a single JSON-RPC object.', 400 );
		}

		$id = array_key_exists( 'id', $payload ) ? $payload['id'] : null;
		if ( '2.0' !== ( $payload['jsonrpc'] ?? null ) || ! isset( $payload['method'] ) || ! is_string( $payload['method'] ) || '' === $payload['method'] ) {
			self::audit_request( $request, '', '', 'protocol_error', 400, 'cmsa_mcp_invalid_request', $started, $payload['id'] ?? null );
			return self::protocol_error_response( $id, -32600, 'Invalid JSON-RPC request.', 400 );
		}

		$method = $payload['method'];
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();
		$tool = 'tools/call' === $method && isset( $params['name'] ) ? (string) $params['name'] : '';
		self::audit_request( $request, $method, $tool, 'received', 0, '', $started, $id, $params );
		if ( class_exists( 'CUA_Audit' ) ) {
			CUA_Audit::log_oauth_trace(
				array(
					'stage'            => 'mcp_request',
					'outcome'          => 'received',
					'http_status'      => 0,
					'method'           => strtoupper( $request->get_method() ),
					'path'             => (string) wp_parse_url( $request->get_route(), PHP_URL_PATH ),
					'protocol_version' => trim( (string) $request->get_header( 'mcp-protocol-version' ) ),
					'mcp_method'       => $method,
				)
			);
		}

		$transport_error = self::validate_transport_headers( $request );
		if ( is_wp_error( $transport_error ) ) {
			$status = 'cmsa_mcp_accept_not_supported' === $transport_error->get_error_code() ? 406 : 415;
			return self::protocol_error_response( $id, -32020, $transport_error->get_error_message(), $status );
		}

		$version_error = self::validate_version( $request, $method, $params );
		if ( is_wp_error( $version_error ) ) {
			return self::protocol_error_response(
				$id,
				-32022,
				$version_error->get_error_message(),
				400,
				array( 'supportedVersions' => self::supported_protocol_versions() )
			);
		}

		$protocol_version = self::request_protocol_version( $request, $params );
		if ( self::PROTOCOL_VERSION === $protocol_version && in_array( $method, array( 'initialize', 'notifications/initialized' ), true ) ) {
			return self::protocol_error_response(
				$id,
				-32022,
				'The 2026-07-28 protocol is stateless and does not use initialize or initialized.',
				400,
				array( 'supportedVersions' => self::supported_protocol_versions() )
			);
		}

		$header_error = self::validate_headers( $request, $method, $params, $protocol_version );
		if ( is_wp_error( $header_error ) ) {
			return self::protocol_error_response( $id, -32020, $header_error->get_error_message(), 400 );
		}

		$meta_error = self::validate_request_meta( $params, $protocol_version );
		if ( is_wp_error( $meta_error ) ) {
			return self::protocol_error_response( $id, -32020, $meta_error->get_error_message(), 400 );
		}

		$is_modern_public_discovery = self::PROTOCOL_VERSION === $protocol_version
			&& in_array( $method, array( 'server/discover', 'tools/list' ), true );

		if ( $is_modern_public_discovery ) {
			if ( class_exists( 'CUA_Audit' ) ) {
				CUA_Audit::log_oauth_trace(
					array(
						'stage'            => 'mcp_authentication',
						'outcome'          => 'public_discovery',
						'http_status'      => 200,
						'method'           => strtoupper( $request->get_method() ),
						'path'             => (string) wp_parse_url( $request->get_route(), PHP_URL_PATH ),
						'protocol_version' => $protocol_version,
						'mcp_method'       => $method,
					)
				);
			}
		} else {
			$authorization = self::authorize_with_trace( $request, $method );
			if ( is_wp_error( $authorization ) ) {
				if ( self::PROTOCOL_VERSION === $protocol_version && 'tools/call' === $method && array_key_exists( 'id', $payload ) ) {
					self::audit_request( $request, $method, $tool, 'authentication_required', 200, $authorization->get_error_code(), $started, $id, $params );
					if ( class_exists( 'CUA_Audit' ) ) {
						CUA_Audit::log_oauth_trace(
							array(
								'stage'            => 'mcp_tool_auth_challenge',
								'outcome'          => 'served',
								'http_status'      => 200,
								'error_code'       => $authorization->get_error_code(),
								'protocol_version' => $protocol_version,
								'mcp_method'       => $method,
							)
						);
					}
					return self::tool_authentication_response( $id, $authorization, $protocol_version, $session_id );
				}
				self::audit_request( $request, $method, $tool, 'authentication_failed', self::error_status( $authorization ), $authorization->get_error_code(), $started, $id, $params );
				return self::authentication_error_response( $authorization );
			}
		}

		$declared_protocol_version = self::declared_protocol_version( $request, $params );
		$is_legacy = self::LEGACY_PROTOCOL_VERSION === $protocol_version;
		$session_required = $is_legacy && ! in_array( $method, array( 'initialize', 'server/discover', 'notifications/initialized', 'tools/list', 'resources/list', 'resources/read', 'prompts/list', 'prompts/get' ), true );
		if ( $session_required && ! self::session_is_valid( $session_id ) ) {
			return self::protocol_error_response( $id, -32001, 'A valid MCP session is required for this legacy MCP method.', 400 );
		}
		$session_supplied = $is_legacy && '' !== $session_id && self::session_is_valid( $session_id );
		if ( $session_supplied && '' !== $declared_protocol_version && ! self::session_protocol_matches( $session_id, $declared_protocol_version ) ) {
			return self::protocol_error_response(
				$id,
				-32022,
				'The request protocol version does not match the negotiated MCP session version.',
				400,
				array( 'supportedVersions' => self::supported_protocol_versions() )
			);
		}

		if ( 'notifications/initialized' === $method && ! array_key_exists( 'id', $payload ) ) {
			return self::notification_response( $session_id );
		}

		if ( ! array_key_exists( 'id', $payload ) ) {
			return self::protocol_error_response( null, -32600, 'A request id is required for this MCP method.', 400 );
		}

		switch ( $method ) {
			case 'initialize':
				$session_id = self::create_session( self::LEGACY_PROTOCOL_VERSION );
				return self::success_response( $id, self::initialize_result( self::LEGACY_PROTOCOL_VERSION ), self::LEGACY_PROTOCOL_VERSION, $session_id );

			case 'server/discover':
				$discovery = self::discover_result( $params );
				if ( is_wp_error( $discovery ) ) {
					$response = self::protocol_error_response( $id, -32603, $discovery->get_error_message(), 500 );
					if ( class_exists( 'CUA_MCP_Diagnostics' ) ) {
						CUA_MCP_Diagnostics::record_exchange( $request, $payload, $response, 'main' );
					}
					return $response;
				}
				$response = self::success_response( $id, $discovery, $protocol_version, $session_id );
				if ( class_exists( 'CUA_MCP_Diagnostics' ) ) {
					CUA_MCP_Diagnostics::record_exchange( $request, $payload, $response, 'main' );
				}
				return $response;

			case 'tools/list':
				$tools = self::list_tools_result( $params );
				if ( is_wp_error( $tools ) ) {
					if ( class_exists( 'CUA_Audit' ) ) {
						CUA_Audit::log_oauth_trace( array( 'stage' => 'mcp_tools_list', 'outcome' => 'failed', 'http_status' => 400, 'error_code' => $tools->get_error_code(), 'mcp_method' => 'tools/list', 'protocol_version' => $protocol_version ) );
					}
					$response = self::protocol_error_response( $id, -32602, $tools->get_error_message(), 400 );
					if ( class_exists( 'CUA_MCP_Diagnostics' ) ) {
						CUA_MCP_Diagnostics::record_exchange( $request, $payload, $response, 'main' );
					}
					return $response;
				}
				if ( class_exists( 'CUA_Audit' ) ) {
					CUA_Audit::log_oauth_trace( array( 'stage' => 'mcp_tools_list', 'outcome' => 'accepted', 'http_status' => 200, 'mcp_method' => 'tools/list', 'protocol_version' => $protocol_version ) );
				}
				$response = self::success_response( $id, $tools, $protocol_version, $session_id );
				if ( class_exists( 'CUA_MCP_Diagnostics' ) ) {
					CUA_MCP_Diagnostics::record_exchange( $request, $payload, $response, 'main' );
				}
				return $response;

			case 'tools/call':
				$call = self::call_tool( $params );
				if ( is_wp_error( $call ) ) {
					self::audit_request( $request, $method, $tool, 'tool_error', 200, $call->get_error_code(), $started, $id, $params );
					return self::success_response( $id, self::tool_error_result( $call ), $protocol_version, $session_id );
				}
				self::audit_request( $request, $method, $tool, 'success', 200, '', $started, $id, $params );
				return self::success_response( $id, $call, $protocol_version, $session_id );

			case 'resources/list':
				return self::success_response( $id, self::list_resources_result(), $protocol_version, $session_id );

			case 'resources/read':
				$resource = self::read_resource_result( $params );
				if ( is_wp_error( $resource ) ) {
					return self::protocol_error_response( $id, -32602, $resource->get_error_message(), 400 );
				}
				return self::success_response( $id, $resource, $protocol_version, $session_id );

			case 'prompts/list':
				return self::success_response( $id, self::list_prompts_result(), $protocol_version, $session_id );

			case 'prompts/get':
				$prompt = self::get_prompt_result( $params );
				if ( is_wp_error( $prompt ) ) {
					return self::protocol_error_response( $id, -32602, $prompt->get_error_message(), 400 );
				}
				return self::success_response( $id, $prompt, $protocol_version, $session_id );

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
			if ( '' !== $header_version && '' !== $meta_version && $header_version !== $meta_version ) {
				return new WP_Error( 'cmsa_mcp_version_mismatch', 'MCP protocol version declarations do not match.' );
			}
			if ( '' !== $body_version && '' !== $meta_version && $body_version !== $meta_version ) {
				return new WP_Error( 'cmsa_mcp_version_mismatch', 'MCP protocol version declarations do not match.' );
			}
			if ( '' === $body_version ) {
				$body_version = $meta_version;
			}
		}
		if ( '' !== $header_version && '' !== $body_version && $header_version !== $body_version ) {
			return new WP_Error( 'cmsa_mcp_version_mismatch', 'MCP protocol version declarations do not match.' );
		}
		if ( 'initialize' === $method && '' === $body_version ) {
			return new WP_Error( 'cmsa_mcp_version_missing', 'initialize requires params.protocolVersion.' );
		}

		$supported = self::supported_protocol_versions();
		if ( '' !== $header_version && ! in_array( $header_version, $supported, true ) ) {
			return new WP_Error(
				'cmsa_mcp_version_mismatch',
				'MCP protocol version is not supported.'
			);
		}
		if ( '' !== $body_version && ! in_array( $body_version, $supported, true ) ) {
			return new WP_Error( 'cmsa_mcp_version_mismatch', 'MCP protocol version is not supported.' );
		}
		return true;
	}

	private static function supported_protocol_versions() {
		return array( self::PROTOCOL_VERSION, self::LEGACY_PROTOCOL_VERSION );
	}

	private static function request_session_id( WP_REST_Request $request ) {
		return trim( (string) $request->get_header( self::SESSION_HEADER ) );
	}

	private static function session_key( $session_id ) {
		return 'cmsa_mcp_session_' . hash( 'sha256', (string) $session_id );
	}

	private static function create_session( $protocol_version ) {
		$session_id = wp_generate_uuid4();
		$session = array(
			'user_id'         => get_current_user_id(),
			'protocolVersion' => (string) $protocol_version,
			'createdAt'       => time(),
		);
		self::$active_sessions[ $session_id ] = $session;
		set_transient(
			self::session_key( $session_id ),
			$session,
			self::SESSION_TTL
		);
		return $session_id;
	}

	private static function session_is_valid( $session_id ) {
		if ( '' === (string) $session_id ) {
			return false;
		}

		$session = isset( self::$active_sessions[ $session_id ] ) ? self::$active_sessions[ $session_id ] : get_transient( self::session_key( $session_id ) );
		if ( ! is_array( $session ) || (int) ( $session['user_id'] ?? 0 ) !== (int) get_current_user_id() ) {
			return false;
		}

		self::$active_sessions[ $session_id ] = $session;
		set_transient( self::session_key( $session_id ), $session, self::SESSION_TTL );
		return true;
	}

	private static function request_protocol_version( WP_REST_Request $request, array $params ) {
		$header_version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
		if ( '' !== $header_version ) {
			return $header_version;
		}
		if ( isset( $params['protocolVersion'] ) && '' !== trim( (string) $params['protocolVersion'] ) ) {
			return trim( (string) $params['protocolVersion'] );
		}
		if ( isset( $params['_meta']['io.modelcontextprotocol/protocolVersion'] ) ) {
			return trim( (string) $params['_meta']['io.modelcontextprotocol/protocolVersion'] );
		}
		$session_id = self::request_session_id( $request );
		if ( '' !== $session_id ) {
			$session = isset( self::$active_sessions[ $session_id ] ) ? self::$active_sessions[ $session_id ] : get_transient( self::session_key( $session_id ) );
			if ( is_array( $session ) && isset( $session['protocolVersion'] ) ) {
				return (string) $session['protocolVersion'];
			}
		}
		return self::LEGACY_PROTOCOL_VERSION;
	}

	private static function declared_protocol_version( WP_REST_Request $request, array $params ) {
		$header_version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
		if ( '' !== $header_version ) {
			return $header_version;
		}
		if ( isset( $params['protocolVersion'] ) && '' !== trim( (string) $params['protocolVersion'] ) ) {
			return trim( (string) $params['protocolVersion'] );
		}
		if ( isset( $params['_meta']['io.modelcontextprotocol/protocolVersion'] ) ) {
			return trim( (string) $params['_meta']['io.modelcontextprotocol/protocolVersion'] );
		}
		return '';
	}

	private static function session_protocol_matches( $session_id, $protocol_version ) {
		$session = isset( self::$active_sessions[ $session_id ] ) ? self::$active_sessions[ $session_id ] : get_transient( self::session_key( $session_id ) );
		return is_array( $session ) && hash_equals( (string) ( $session['protocolVersion'] ?? '' ), (string) $protocol_version );
	}

	private static function validate_headers( WP_REST_Request $request, $method, array $params, $protocol_version ) {
		$header_method = trim( (string) $request->get_header( 'mcp-method' ) );
		$header_name   = trim( (string) $request->get_header( 'mcp-name' ) );
		if ( self::PROTOCOL_VERSION === $protocol_version ) {
			if ( self::PROTOCOL_VERSION !== trim( (string) $request->get_header( 'mcp-protocol-version' ) ) ) {
				return new WP_Error( 'cmsa_mcp_protocol_header_required', 'Modern MCP requests require MCP-Protocol-Version: 2026-07-28.' );
			}
			if ( '' === $header_method ) {
				return new WP_Error( 'cmsa_mcp_method_header_required', 'Modern MCP requests require Mcp-Method.' );
			}
		}
		if ( '' !== $header_method && $method !== $header_method ) {
			return new WP_Error( 'cmsa_mcp_method_header_mismatch', 'Mcp-Method does not match the JSON-RPC method.' );
		}
		$mirrored_name = '';
		if ( 'tools/call' === $method || 'prompts/get' === $method ) {
			$mirrored_name = isset( $params['name'] ) ? (string) $params['name'] : '';
		} elseif ( 'resources/read' === $method ) {
			$mirrored_name = isset( $params['uri'] ) ? (string) $params['uri'] : '';
		}
		if ( self::PROTOCOL_VERSION === $protocol_version && '' !== $mirrored_name && '' === $header_name ) {
			return new WP_Error( 'cmsa_mcp_name_header_required', 'Modern MCP requests for this method require Mcp-Name.' );
		}
		if ( '' !== $header_name && $mirrored_name !== $header_name ) {
			return new WP_Error( 'cmsa_mcp_name_header_mismatch', 'Mcp-Name does not match the mirrored request parameter.' );
		}
		return true;
	}

	private static function validate_request_meta( array $params, $protocol_version ) {
		if ( self::PROTOCOL_VERSION !== $protocol_version ) {
			return true;
		}
		$meta = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : null;
		if ( ! is_array( $meta ) ) {
			return new WP_Error( 'cmsa_mcp_meta_required', 'Modern MCP requests require a _meta envelope.' );
		}
		if ( self::PROTOCOL_VERSION !== trim( (string) ( $meta['io.modelcontextprotocol/protocolVersion'] ?? '' ) ) ) {
			return new WP_Error( 'cmsa_mcp_meta_version_required', 'Modern MCP requests require the 2026-07-28 protocol version in _meta.' );
		}
		if ( ! isset( $meta['io.modelcontextprotocol/clientCapabilities'] ) || ! is_array( $meta['io.modelcontextprotocol/clientCapabilities'] ) ) {
			return new WP_Error( 'cmsa_mcp_client_capabilities_required', 'Modern MCP requests require clientCapabilities in _meta.' );
		}
		if ( isset( $meta['io.modelcontextprotocol/clientInfo'] ) && ! is_array( $meta['io.modelcontextprotocol/clientInfo'] ) ) {
			return new WP_Error( 'cmsa_mcp_client_info_invalid', 'Modern MCP clientInfo must be an object when supplied.' );
		}
		return true;
	}

	private static function validate_transport_headers( WP_REST_Request $request ) {
		$content_type = strtolower( trim( (string) $request->get_header( 'content-type' ) ) );
		$content_type = '' !== $content_type ? trim( strtok( $content_type, ';' ) ) : '';
		if ( 'application/json' !== $content_type ) {
			return new WP_Error( 'cmsa_mcp_content_type_required', 'MCP POST requests require Content-Type: application/json.' );
		}

		$accept = trim( (string) $request->get_header( 'accept' ) );
		if ( '' !== $accept ) {
			$accepted = array_map(
				static function ( $value ) {
					return trim( strtolower( strtok( trim( $value ), ';' ) ) );
				},
				explode( ',', $accept )
			);
			if ( ! in_array( 'application/json', $accepted, true ) && ! in_array( 'text/event-stream', $accepted, true ) && ! in_array( '*/*', $accepted, true ) ) {
				return new WP_Error( 'cmsa_mcp_accept_not_supported', 'MCP clients must accept application/json or text/event-stream.' );
			}
		}

		return true;
	}

	private static function discover_result( array $params = array() ) {
		$manifest = self::discovery_manifest( $params );
		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}
		return array(
			'supportedVersions' => self::supported_protocol_versions(),
			'serverInfo'        => self::server_info(),
			'capabilities'      => array(
				'tools' => array(
					'listChanged' => false,
				),
				'resources' => array(
					'listChanged' => false,
					'subscribe'   => false,
				),
				'prompts' => array(
					'listChanged' => false,
				),
			),
			'discovery'         => $manifest,
			'instructions'      => 'Authenticated WordPress site-operation tools. Use read-only tools for inspection and mutating tools only for explicitly authorized site changes.',
			'ttlMs'             => 30000,
			'cacheScope'        => 'private',
		);
	}

	private static function initialize_result( $protocol_version = self::PROTOCOL_VERSION ) {
		return array(
			'protocolVersion' => $protocol_version,
			'capabilities'    => array(
				'tools' => array(
					'listChanged' => false,
				),
				'resources' => array(
					'listChanged' => false,
					'subscribe'   => false,
				),
				'prompts' => array(
					'listChanged' => false,
				),
			),
			'serverInfo'      => self::server_info(),
			'instructions'    => 'Authenticated WordPress site-operation tools. Use read-only tools for inspection and mutating tools only for explicitly authorized site changes.',
		);
	}

	private static function list_tools_result( array $params ) {
		$all_tools = array_values( self::adapter_tools() );
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

	private static function list_resources_result() {
		return array(
			'resources' => array(
				array(
					'uri'         => self::RESOURCE_DISCOVERY_URI,
					'name'        => 'mcp-discovery-manifest',
					'title'       => 'MCP discovery manifest',
					'description' => 'Small stable manifest describing the initial tool set and the paginated site-operation catalog.',
					'mimeType'    => 'application/json',
				),
				array(
					'uri'         => self::RESOURCE_CATALOG_URI,
					'name'        => 'site-operation-catalog',
					'title'       => 'Site operation catalog',
					'description' => 'Current read and write site-operation bridges discovered from public WordPress contracts.',
					'mimeType'    => 'application/json',
				),
			),
			'ttlMs'      => 30000,
			'cacheScope' => 'private',
		);
	}

	private static function read_resource_result( array $params ) {
		$uri = isset( $params['uri'] ) ? trim( (string) $params['uri'] ) : '';
		if ( self::RESOURCE_DISCOVERY_URI === $uri ) {
			return array(
				'contents' => array(
					array(
						'uri'      => self::RESOURCE_DISCOVERY_URI,
						'mimeType' => 'application/json',
						'text'     => self::json_text( self::discovery_manifest( $params ) ),
					),
				),
				'ttlMs'      => 30000,
				'cacheScope' => 'private',
			);
		}
		if ( self::RESOURCE_CATALOG_URI !== $uri ) {
			return new WP_Error( 'cmsa_mcp_resource_not_found', 'The requested MCP resource is not available.' );
		}

		$catalog_input = array();
		if ( isset( $params['cursor'] ) ) { $catalog_input['cursor'] = max( 0, (int) $params['cursor'] ); }
		if ( isset( $params['limit'] ) ) { $catalog_input['limit'] = min( 100, max( 1, (int) $params['limit'] ) ); }
		if ( isset( $params['snapshot'] ) ) { $catalog_input['snapshot'] = trim( (string) $params['snapshot'] ); }
		$catalog = class_exists( 'CUA_Ability_Bridge' ) ? CUA_Ability_Bridge::catalog( $catalog_input ) : array( 'count' => 0, 'items' => array(), 'nextCursor' => null );
		if ( is_wp_error( $catalog ) ) {
			return $catalog;
		}

		return array(
			'contents' => array(
				array(
					'uri'      => self::RESOURCE_CATALOG_URI,
					'mimeType' => 'application/json',
					'text'     => self::json_text( $catalog ),
				),
			),
			'ttlMs'      => 30000,
			'cacheScope' => 'private',
		);
	}

	public static function discovery_manifest( $input = array() ) {
		$catalog_input = array();
		if ( is_array( $input ) ) {
			if ( isset( $input['cursor'] ) ) { $catalog_input['cursor'] = max( 0, (int) $input['cursor'] ); }
			if ( isset( $input['limit'] ) ) { $catalog_input['limit'] = min( 100, max( 1, (int) $input['limit'] ) ); }
			if ( isset( $input['snapshot'] ) ) { $catalog_input['snapshot'] = trim( (string) $input['snapshot'] ); }
		}
		if ( ! class_exists( 'CUA_Ability_Bridge' ) ) {
			return new WP_Error( 'cmsa_discovery_catalog_unavailable', 'The WordPress capability catalog is unavailable.' );
		}
		$catalog = CUA_Ability_Bridge::catalog( $catalog_input );
		if ( is_wp_error( $catalog ) ) {
			return $catalog;
		}
		$manifest = array(
			'schemaVersion' => '1',
			'initialToolSet' => array( 'count' => count( self::adapter_tools() ), 'method' => 'tools/list', 'pageSize' => self::TOOL_PAGE_SIZE ),
			'catalog' => array( 'resourceUri' => self::RESOURCE_CATALOG_URI, 'method' => 'resources/read', 'pageSize' => 100, 'description' => 'Read catalog pages to discover public site-operation contracts.' ),
			'catalogGateway' => array( 'count' => (int) ( $catalog['count'] ?? count( (array) ( $catalog['items'] ?? array() ) ) ), 'cursor' => (int) ( $catalog['cursor'] ?? 0 ), 'pageSize' => (int) ( $catalog['pageSize'] ?? 100 ), 'nextCursor' => isset( $catalog['nextCursor'] ) ? $catalog['nextCursor'] : null, 'snapshot' => (string) ( $catalog['snapshot'] ?? '' ), 'items' => array_values( (array) ( $catalog['items'] ?? array() ) ), 'authority' => 'WordPress public abilities and registered REST contracts at call time.' ),
			'nextSteps' => array( 'Read this manifest first.', 'Use catalogGateway.nextCursor as cursor in the next discovery call until it is null.', 'Use the returned bridge identifier with cmsa.read-bridge or cmsa.write-bridge.' ),
		);
		return $manifest;
	}

	private static function list_prompts_result() {
		return array(
			'prompts' => array(
				array(
					'name'        => self::PROMPT_SITE_OPERATION,
					'title'       => 'Site operation guide',
					'description' => 'Guides a caller from the public site-operation catalog to the least-privilege MCP tool.',
					'arguments'   => array(
						array(
							'name'        => 'request',
							'description' => 'The site operation the caller wants to perform.',
							'required'    => true,
						),
					),
				),
			),
			'ttlMs'      => 30000,
			'cacheScope' => 'private',
		);
	}

	private static function get_prompt_result( array $params ) {
		$name = isset( $params['name'] ) ? trim( (string) $params['name'] ) : '';
		if ( self::PROMPT_SITE_OPERATION !== $name ) {
			return new WP_Error( 'cmsa_mcp_prompt_not_found', 'The requested MCP prompt is not available.' );
		}

		$arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
		$request = isset( $arguments['request'] ) ? trim( (string) $arguments['request'] ) : '';
		if ( '' === $request ) {
			return new WP_Error( 'cmsa_mcp_prompt_argument_required', 'The site-operation prompt requires a request argument.' );
		}

		return array(
			'description' => 'Use the public site-operation catalog and select the least-privilege read or write tool for the requested operation.',
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => array(
						'type' => 'text',
						'text' => 'Requested site operation: ' . $request . '. Read chattanooga://site-operation-catalog, preserve the target contract permissions, and use a read-only tool unless an explicitly authorized mutation is required.',
					),
				),
			),
		);
	}

	/**
	 * Return the exact deterministic MCP tool descriptors for diagnostics and CI.
	 *
	 * This does not execute any ability and does not bypass tool permissions.
	 *
	 * @return array MCP tool descriptors.
	 */
	public static function diagnostic_tools() {
		return array_values( self::adapter_tools() );
	}

	/**
	 * Return a deterministic SHA-256 fingerprint of the exact advertised core tool descriptors.
	 *
	 * @return string Lowercase 64-character SHA-256, or an empty string if encoding fails.
	 */
	public static function tool_fingerprint() {
		$encoded = wp_json_encode( self::diagnostic_tools(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return false === $encoded ? '' : hash( 'sha256', (string) $encoded );
	}

	public static function register_adapter_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) { return; }
		$abilities = array(
			'chattanooga-cms-admin/mcp-discover-abilities' => array( 'label' => 'Discover WordPress abilities', 'description' => 'Discover public WordPress abilities and Chattanooga site-operation bridges available to this authenticated MCP client.', 'input_schema' => array( 'type' => 'object', 'properties' => array(), 'additionalProperties' => false ), 'execute_callback' => array( __CLASS__, 'discover_adapter_abilities' ), 'meta' => array( 'public' => true, 'mcp' => array( 'public' => true ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true, 'open_world' => false ) ) ),
			'chattanooga-cms-admin/mcp-get-ability-info' => array( 'label' => 'Get WordPress ability information', 'description' => 'Get the schema, permissions metadata, and execution identity for one discovered WordPress ability or site-operation bridge.', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'name' => array( 'type' => 'string' ) ), 'required' => array( 'name' ), 'additionalProperties' => false ), 'execute_callback' => array( __CLASS__, 'get_adapter_ability_info' ), 'meta' => array( 'public' => true, 'mcp' => array( 'public' => true ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true, 'open_world' => false ) ) ),
			'chattanooga-cms-admin/mcp-execute-ability' => array( 'label' => 'Execute a WordPress ability', 'description' => 'Execute one previously discovered WordPress ability or site-operation bridge while retaining its own permission callback and Chattanooga control-plane guard.', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'name' => array( 'type' => 'string' ), 'input' => array( 'type' => 'object' ) ), 'required' => array( 'name' ), 'additionalProperties' => false ), 'execute_callback' => array( __CLASS__, 'execute_adapter_ability' ), 'meta' => array( 'public' => true, 'mcp' => array( 'public' => true ), 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false, 'open_world' => true ) ) ),
		);
		foreach ( $abilities as $name => $args ) { if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( $name ) instanceof WP_Ability ) { wp_register_ability( $name, $args ); } }
	}

	private static function adapter_ability_names() { return array( 'chattanooga-cms-admin/mcp-discover-abilities', 'chattanooga-cms-admin/mcp-get-ability-info', 'chattanooga-cms-admin/mcp-execute-ability' ); }

	private static function adapter_ability_name( $tool_name ) {
		$map = array( 'cmsa.discover-abilities' => 'chattanooga-cms-admin/mcp-discover-abilities', 'cmsa.get-ability-info' => 'chattanooga-cms-admin/mcp-get-ability-info', 'cmsa.execute-ability' => 'chattanooga-cms-admin/mcp-execute-ability' );
		return $map[ $tool_name ] ?? '';
	}

	private static function adapter_tools() {
		// This is the complete startup ABI. It is deliberately independent of
		// wp_get_abilities(), so a partial or late WordPress registry cannot make
		// the host ingest a different tool list.
		return self::stable_gateway_tool_definitions();
	}

	private static function stable_gateway_tool_definitions() {
		$bridge_schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'bridge' => array( 'type' => 'string', 'pattern' => '^chattanooga-cms-admin/(?:bridge|rest)-[a-f0-9]{24}$' ),
				'input'  => array( 'type' => 'object' ),
			),
			'required'             => array( 'bridge' ),
			'additionalProperties' => false,
		);
		$security_schemes = self::auth_security_schemes();
		$tools = array(
			'cmsa.discovery' => self::stable_gateway_descriptor('cmsa.discovery', 'Discover Chattanooga capabilities', 'Return the current paginated catalog of public WordPress abilities and REST contracts. Use returned bridge identifiers with cmsa.read-bridge or cmsa.write-bridge.', array('type'=>'object','properties'=>array('cursor'=>array('type'=>'integer','minimum'=>0,'default'=>0),'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>100,'default'=>100),'snapshot'=>array('type'=>'string','minLength'=>64,'maxLength'=>64)),'additionalProperties'=>false), array('readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false), $security_schemes),
			'cmsa.stability-check' => self::stable_gateway_descriptor('cmsa.stability-check', 'Check MCP stability', 'Return server-side MCP stability evidence, the immutable startup-tool fingerprint, descriptor health, and recent secret-free diagnostics.', array('type'=>'object','properties'=>array('limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>50,'default'=>20)),'additionalProperties'=>false), array('readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false), $security_schemes),
			'cmsa.read-bridge' => self::stable_gateway_descriptor('cmsa.read-bridge', 'Execute read-only Chattanooga bridge', 'Execute one catalog-discovered read-only WordPress operation using its bridge identifier and arguments.', $bridge_schema, array('readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>false,'openWorldHint'=>true), $security_schemes),
			'cmsa.write-bridge' => self::stable_gateway_descriptor('cmsa.write-bridge', 'Execute authorized Chattanooga bridge', 'Execute one catalog-discovered mutating WordPress operation using its bridge identifier and arguments. Use only for explicitly authorized changes.', $bridge_schema, array('readOnlyHint'=>false,'destructiveHint'=>true,'idempotentHint'=>false,'openWorldHint'=>true), $security_schemes),
		);
		$ordered = array();
		foreach ( self::STABLE_GATEWAY_TOOL_NAMES as $name ) { if ( isset( $tools[ $name ] ) ) { $ordered[ $name ] = $tools[ $name ]; } }
		return $ordered;
	}

	private static function stable_gateway_descriptor( $name, $title, $description, array $schema, array $annotations, array $security_schemes ) {
		return array(
			'name'          => $name,
			'title'         => $title,
			'description'   => $description,
			'inputSchema'   => $schema,
			// Gateway responses are object envelopes; dynamic catalog entries
			// remain described by the discovery payload rather than tools/list.
			'outputSchema'  => array( 'type' => 'object', 'additionalProperties' => true ),
			'annotations'  => $annotations,
			'securitySchemes' => $security_schemes,
			'_meta'         => array( 'securitySchemes' => $security_schemes ),
		);
	}

	private static function call_adapter_tool( $name, array $arguments ) {
		$ability_name = self::adapter_ability_name( $name );
		$ability = '' !== $ability_name && function_exists( 'wp_get_ability' ) ? wp_get_ability( $ability_name ) : null;
		if ( ! $ability instanceof WP_Ability || ! self::ability_is_mcp_public( $ability ) ) { return new WP_Error( 'cmsa_adapter_tool_not_found', 'The requested adapter tool is not available.' ); }
		try {
			$permission = $ability->check_permissions( $arguments );
		} catch ( Throwable $error ) {
			return new WP_Error( 'cmsa_adapter_permission_exception', 'The selected adapter ability permission check failed.' );
		}
		if ( is_wp_error( $permission ) ) { return $permission; }
		if ( ! $permission ) { return new WP_Error( 'cmsa_mcp_tool_forbidden', 'The selected adapter ability denied this request.' ); }
		try {
			$result = $ability->execute( $arguments );
		} catch ( Throwable $error ) {
			return new WP_Error( 'cmsa_adapter_tool_exception', 'The selected adapter ability failed during execution.' );
		}
		return is_wp_error( $result ) ? $result : self::tool_success_result( $result );
	}

	public static function discover_adapter_abilities( $input = array() ) {
		$items = array();
		if ( function_exists( 'wp_get_abilities' ) ) { foreach ( wp_get_abilities() as $ability ) { if ( $ability instanceof WP_Ability && self::ability_is_mcp_public( $ability ) && ! in_array( $ability->get_name(), self::adapter_ability_names(), true ) ) { $items[] = self::ability_info( $ability ); } } }
		usort( $items, static function ( $left, $right ) { return strcmp( (string) $left['name'], (string) $right['name'] ); } );
		$catalog = class_exists( 'CUA_Ability_Bridge' ) ? CUA_Ability_Bridge::catalog() : array( 'count' => 0, 'items' => array() );
		return array( 'abilities' => $items, 'bridges' => $catalog );
	}

	public static function get_adapter_ability_info( $input = array() ) {
		$name = is_array( $input ) && isset( $input['name'] ) ? trim( (string) $input['name'] ) : '';
		return self::resolve_ability_info( $name );
	}

	public static function execute_adapter_ability( $input = array() ) {
		$name = is_array( $input ) && isset( $input['name'] ) ? trim( (string) $input['name'] ) : '';
		$arguments = is_array( $input ) && isset( $input['input'] ) && is_array( $input['input'] ) ? $input['input'] : array();
		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
		if ( $ability instanceof WP_Ability && self::ability_is_mcp_public( $ability ) && ! in_array( $name, self::adapter_ability_names(), true ) ) {
			try {
				$permission = $ability->check_permissions( $arguments );
			} catch ( Throwable $error ) {
				return new WP_Error( 'cmsa_adapter_permission_exception', 'The selected WordPress ability permission check failed.' );
			}
			if ( is_wp_error( $permission ) ) { return $permission; }
			if ( ! $permission ) { return new WP_Error( 'cmsa_mcp_tool_forbidden', 'The selected WordPress ability denied this request.' ); }
			try {
				return $ability->execute( $arguments );
			} catch ( Throwable $error ) {
				return new WP_Error( 'cmsa_adapter_tool_exception', 'The selected WordPress ability failed during execution.' );
			}
		}
		$catalog = class_exists( 'CUA_Ability_Bridge' ) ? CUA_Ability_Bridge::catalog() : array();
		foreach ( (array) ( $catalog['items'] ?? array() ) as $item ) { if ( is_array( $item ) && $name === (string) ( $item['bridge'] ?? '' ) ) { $readonly = true === ( $item['annotations']['readonly'] ?? null ); return CUA_Bridge_Gateway::execute( array( 'bridge' => $name, 'input' => $arguments ), $readonly ); } }
		return new WP_Error( 'cmsa_adapter_ability_not_found', 'The requested discovered ability or bridge is not available.' );
	}

	private static function resolve_ability_info( $name ) {
		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
		if ( $ability instanceof WP_Ability && self::ability_is_mcp_public( $ability ) && ! in_array( $name, self::adapter_ability_names(), true ) ) { return self::ability_info( $ability ); }
		$catalog = class_exists( 'CUA_Ability_Bridge' ) ? CUA_Ability_Bridge::catalog() : array();
		foreach ( (array) ( $catalog['items'] ?? array() ) as $item ) { if ( is_array( $item ) && $name === (string) ( $item['bridge'] ?? '' ) ) { return $item; } }
		return new WP_Error( 'cmsa_adapter_ability_not_found', 'The requested discovered ability or bridge is not available.' );
	}

	private static function ability_info( WP_Ability $ability ) {
		$input = $ability->get_input_schema(); $output = $ability->get_output_schema();
		return array( 'name' => $ability->get_name(), 'label' => $ability->get_label(), 'description' => $ability->get_description(), 'inputSchema' => is_array( $input ) ? self::normalize_json_schema_for_transport( $input ) : null, 'outputSchema' => is_array( $output ) ? self::normalize_json_schema_for_transport( $output ) : null, 'annotations' => self::tool_annotations( $ability ) );
	}

	private static function tool_success_result( $result ) {
		$response = array( 'content' => array( array( 'type' => 'text', 'text' => self::json_text( $result ) ) ), 'isError' => false );
		if ( is_array( $result ) || is_object( $result ) ) { $response['structuredContent'] = $result; }
		return $response;
	}
	private static function tools() {
		$tools = array();
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return $tools;
		}

		foreach ( wp_get_abilities() as $ability ) {
			if ( ! $ability instanceof WP_Ability ) {
				continue;
			}

			$ability_name = $ability->get_name();
			if ( 0 !== strpos( $ability_name, self::ABILITY_PREFIX ) || ! self::ability_is_mcp_public( $ability ) ) {
				continue;
			}

			$tool_name = self::tool_name( $ability_name );
			$schema = $ability->get_input_schema();
			if ( ! is_array( $schema ) ) {
				$schema = array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				);
			}
			$schema = self::normalize_json_schema_for_transport( $schema );

			$security_schemes = self::auth_security_schemes();
			$tool = array(
				'name'        => $tool_name,
				'title'       => $ability->get_label(),
				'description' => $ability->get_description(),
				'inputSchema' => $schema,
				'annotations' => self::tool_annotations( $ability ),
				'securitySchemes' => $security_schemes,
				'_meta'       => array( 'securitySchemes' => $security_schemes ),
			);

			$output_schema = $ability->get_output_schema();
			if ( is_array( $output_schema ) ) {
				$tool['outputSchema'] = self::normalize_json_schema_for_transport( $output_schema );
			}

			$tools[ $tool_name ] = $tool;
		}

		ksort( $tools, SORT_STRING );

		// Legacy descriptor builder retained for diagnostics and backward
		// compatibility. The MCP tools/list response uses adapter_tools(), which
		// intentionally exposes only the immutable gateway ABI above.
		foreach ( self::direct_tool_names() as $tool_name ) {
			if ( isset( $tools[ $tool_name ] ) || ! function_exists( 'wp_get_ability' ) ) {
				continue;
			}

			$short = substr( $tool_name, strlen( self::TOOL_PREFIX ) );
			$ability = wp_get_ability( self::ABILITY_PREFIX . $short );
			if ( $ability instanceof WP_Ability && self::ability_is_mcp_public( $ability ) ) {
				$tools[ $tool_name ] = self::tool_descriptor( $ability, $tool_name );
			}
		}

		$ordered_tools = array();
		foreach ( self::direct_tool_names() as $tool_name ) {
			if ( isset( $tools[ $tool_name ] ) ) {
				$ordered_tools[ $tool_name ] = $tools[ $tool_name ];
			}
		}
		return $ordered_tools;
	}

	private static function direct_tool_names() {
		return array(
			'cmsa.discovery',
			'cmsa.stability-check',
			'cmsa.activate-plugin',
			'cmsa.catalog',
			'cmsa.clear-cache',
			'cmsa.create-backup',
			'cmsa.deactivate-plugin',
			'cmsa.delete-plugin',
			'cmsa.delete-theme',
			'cmsa.get-audit-log',
			'cmsa.get-health',
			'cmsa.get-registered-setting',
			'cmsa.install-plugin',
			'cmsa.install-plugin-package',
			'cmsa.install-theme',
			'cmsa.list-backups',
			'cmsa.list-plugins',
			'cmsa.list-registered-settings',
			'cmsa.list-themes',
			'cmsa.list-updates',
			'cmsa.read-bridge',
			'cmsa.restore-component-backup',
			'cmsa.restore-core-backup',
			'cmsa.restore-database-backup',
			'cmsa.set-plugin-auto-update',
			'cmsa.set-theme-auto-update',
			'cmsa.switch-theme',
			'cmsa.uninstall-plugin',
			'cmsa.update-core',
			'cmsa.update-plugin',
			'cmsa.update-registered-setting',
			'cmsa.update-theme',
			'cmsa.verify-backup',
			'cmsa.write-bridge',
		);
	}

	private static function tool_descriptor( WP_Ability $ability, $tool_name ) {
		$schema = $ability->get_input_schema();
		if ( ! is_array( $schema ) ) {
			$schema = array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
			);
		}
		$schema = self::normalize_json_schema_for_transport( $schema );
		$security_schemes = self::auth_security_schemes();
		$tool = array(
			'name'            => $tool_name,
			'title'           => $ability->get_label(),
			'description'     => $ability->get_description(),
			'inputSchema'     => $schema,
			'annotations'     => self::tool_annotations( $ability ),
			'securitySchemes' => $security_schemes,
			'_meta'           => array( 'securitySchemes' => $security_schemes ),
		);
		$output_schema = $ability->get_output_schema();
		if ( is_array( $output_schema ) ) {
			$tool['outputSchema'] = self::normalize_json_schema_for_transport( $output_schema );
		}
		return $tool;
	}

	private static function call_tool( array $params ) {
		$name = isset( $params['name'] ) ? trim( (string) $params['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'cmsa_mcp_tool_name_required', 'A tool name is required.' );
		}

		if ( in_array( $name, array( 'cmsa.discover-abilities', 'cmsa.get-ability-info', 'cmsa.execute-ability' ), true ) ) {
			$arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
			if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'cmsa_mcp_tool_forbidden', 'Administrator authority is required to call this tool.' ); }
			return self::call_adapter_tool( $name, $arguments );
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

		try {
			$has_input = is_array( $ability->get_input_schema() );
			$permission = $has_input ? $ability->check_permissions( $arguments ) : $ability->check_permissions();
		} catch ( Throwable $error ) {
			return new WP_Error( 'cmsa_mcp_permission_exception', 'The selected WordPress ability permission check failed.' );
		}
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
		if ( isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && array_key_exists( 'public', $meta['mcp'] ) && null !== $meta['mcp']['public'] ) {
			return true === $meta['mcp']['public'];
		}
		return true === ( $meta['public'] ?? false );
	}

	private static function tool_name( $ability_name ) {
		$short = substr( $ability_name, strlen( self::ABILITY_PREFIX ) );
		return self::TOOL_PREFIX . preg_replace( '/[^A-Za-z0-9_.-]/', '-', $short );
	}

	/**
	 * Preserve JSON Schema object-valued keywords when PHP represents an empty
	 * map as array(). Without this normalization wp_json_encode() emits [] for
	 * empty properties/$defs maps, which is invalid JSON Schema 2020-12.
	 *
	 * @param mixed  $value Current schema value.
	 * @param string $parent_key Parent schema keyword.
	 * @return mixed Transport-safe schema value.
	 */
	private static function normalize_json_schema_for_transport( $value, $parent_key = '' ) {
		$object_keywords = array(
			'$defs',
			'$vocabulary',
			'definitions',
			'dependentRequired',
			'dependentSchemas',
			'patternProperties',
			'properties',
		);

		if ( is_array( $value ) ) {
			if ( empty( $value ) && in_array( (string) $parent_key, $object_keywords, true ) ) {
				return new stdClass();
			}

			$normalized = array();
			foreach ( $value as $key => $item ) {
				$normalized[ $key ] = self::normalize_json_schema_for_transport( $item, is_string( $key ) ? $key : '' );
			}
			return $normalized;
		}

		return $value;
	}

	private static function tool_annotations( WP_Ability $ability ) {
		$meta = $ability->get_meta();
		$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();

		// ChatGPT requires these three annotation booleans on every tool
		// descriptor. Unknown future public abilities fail conservatively open
		// rather than understating their possible external reach.
		$result = array(
			'readOnlyHint'    => array_key_exists( 'readonly', $annotations ) && null !== $annotations['readonly'] ? (bool) $annotations['readonly'] : false,
			'destructiveHint' => array_key_exists( 'destructive', $annotations ) && null !== $annotations['destructive'] ? (bool) $annotations['destructive'] : false,
			'openWorldHint'   => array_key_exists( 'open_world', $annotations ) && null !== $annotations['open_world'] ? (bool) $annotations['open_world'] : true,
		);
		if ( array_key_exists( 'idempotent', $annotations ) && null !== $annotations['idempotent'] ) {
			$result['idempotentHint'] = (bool) $annotations['idempotent'];
		}
		return $result;
	}

	private static function success_response( $id, array $result, $protocol_version = self::PROTOCOL_VERSION, $session_id = '' ) {
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
		$response->header( 'MCP-Protocol-Version', $protocol_version );
		if ( self::LEGACY_PROTOCOL_VERSION === $protocol_version && '' !== (string) $session_id ) {
			$response->header( self::SESSION_HEADER, $session_id );
		}
		return $response;
	}

	private static function notification_response( $session_id = '' ) {
		$response = new WP_REST_Response( null, 202 );
		if ( '' !== (string) $session_id ) {
			$response->header( self::SESSION_HEADER, $session_id );
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

	private static function auth_security_schemes() {
		// ChatGPT currently understands tool-level noauth/oauth2 declarations.
		// A configured manual bearer token remains a server-side compatibility
		// fallback, but the MCP tool contract always advertises the OAuth path.
		return array(
			array(
				'type'   => 'oauth2',
				'scopes' => array( CUA_OAuth_Server::SCOPE ),
			),
		);
	}

	private static function authorize_with_trace( WP_REST_Request $request, $mcp_method = '' ) {
		$authorization = self::authorize_request( $request );
		if ( class_exists( 'CUA_Audit' ) ) {
			$authorization_header = trim( (string) $request->get_header( 'authorization' ) );
			CUA_Audit::log_oauth_trace(
				array(
					'stage'            => 'mcp_authentication',
					'outcome'          => is_wp_error( $authorization ) ? 'failed' : 'accepted',
					'http_status'      => is_wp_error( $authorization ) ? self::error_status( $authorization ) : 200,
					'error_code'       => is_wp_error( $authorization ) ? $authorization->get_error_code() : '',
					'method'           => strtoupper( $request->get_method() ),
					'path'             => (string) wp_parse_url( $request->get_route(), PHP_URL_PATH ),
					'auth_mode'        => preg_match( '/^Bearer\s/i', $authorization_header ) ? 'bearer' : ( is_user_logged_in() ? 'wordpress' : 'missing' ),
					'mcp_method'       => (string) $mcp_method,
				)
			);
		}
		return $authorization;
	}

	private static function tool_authentication_response( $id, WP_Error $error, $protocol_version, $session_id = '' ) {
		$challenge = CUA_OAuth_Server::tool_resource_challenge( (string) $error->get_error_code() );
		$message = $error->get_error_message();
		if ( '' === (string) $message ) {
			$message = 'Authentication is required to call this tool.';
		}

		return self::success_response(
			$id,
			array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => $message,
					),
				),
				'isError' => true,
				'_meta'   => array(
					'mcp/www_authenticate'             => array( $challenge ),
					'chattanooga-cms-admin/errorCode' => (string) $error->get_error_code(),
				),
			),
			$protocol_version,
			$session_id
		);
	}

	private static function authentication_error_response( WP_Error $error ) {
		$data = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 401;
		$error_code = (string) $error->get_error_code();
		$challenge = CUA_OAuth_Server::resource_challenge( $error_code );
		$response = self::protocol_error_response( null, -32001, $error->get_error_message(), $status );
		$body = $response->get_data();
		$body['_meta']['mcp/www_authenticate'] = array( $challenge );
		$body['error']['data'] = isset( $body['error']['data'] ) && is_array( $body['error']['data'] ) ? $body['error']['data'] : array();
		$body['error']['data']['_meta'] = array( 'mcp/www_authenticate' => array( $challenge ) );
		$response->set_data( $body );
		$response->header( 'WWW-Authenticate', $challenge );
		return $response;
	}

	private static function error_status( WP_Error $error ) {
		$data = $error->get_error_data();
		return is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 401;
	}

	private static function audit_request( WP_REST_Request $request, $method, $tool, $outcome, $http_status, $error_code, $started, $request_id = null, array $params = array() ) {
		if ( ! class_exists( 'CUA_Audit' ) ) {
			return;
		}

		$session_id = trim( (string) $request->get_header( self::SESSION_HEADER ) );
		$authorization = trim( (string) $request->get_header( 'authorization' ) );
		if ( '' === $authorization ) {
			$auth_mode = is_user_logged_in() ? 'wordpress' : 'missing';
		} elseif ( preg_match( '/^Basic\s/i', $authorization ) ) {
			$auth_mode = 'basic';
		} elseif ( preg_match( '/^Bearer\s/i', $authorization ) ) {
			$auth_mode = CUA_MCP_Settings_Page::is_manual_auth() ? 'manual' : 'oauth';
		} else {
			$auth_mode = 'invalid';
		}

		$entry = array(
			'time'              => gmdate( 'c' ),
			'user_id'           => get_current_user_id(),
			'method'            => (string) $method,
			'tool'              => (string) $tool,
			'auth_mode'         => $auth_mode,
			'outcome'           => (string) $outcome,
			'http_status'       => (int) $http_status,
			'error_code'        => (string) $error_code,
			'duration_ms'       => max( 0, (int) round( ( microtime( true ) - (float) $started ) * 1000 ) ),
		);
		$protocol_version = isset( $params['protocolVersion'] ) && is_scalar( $params['protocolVersion'] )
			? (string) $params['protocolVersion']
			: trim( (string) $request->get_header( 'mcp-protocol-version' ) );
		if ( self::LEGACY_PROTOCOL_VERSION === $protocol_version && '' !== $session_id ) {
			$entry['session_sha256'] = hash( 'sha256', $session_id );
		}
		if ( null !== $request_id && is_scalar( $request_id ) ) {
			$entry['request_id_sha256'] = hash( 'sha256', (string) $request_id );
		}
		if ( isset( $params['protocolVersion'] ) && is_scalar( $params['protocolVersion'] ) ) {
			$entry['protocol_version'] = (string) $params['protocolVersion'];
		} else {
			$header_version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
			if ( '' !== $header_version ) {
				$entry['protocol_version'] = $header_version;
			}
		}
		CUA_Audit::log_mcp_request( $entry );
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
