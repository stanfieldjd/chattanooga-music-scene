<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Secret-free diagnostics for the MCP ingestion boundary.
 *
 * The diagnostics intentionally retain hashes, lengths, classifications, and
 * descriptor verdicts only. Raw MCP request/response bodies, bearer tokens,
 * authorization codes, PKCE values, state, and credentials are never stored.
 */
final class CUA_MCP_Diagnostics {
	const CANARY_ROUTE = '/mcp-canary';
	const REPORT_ROUTE = '/mcp/diagnostics';
	const CANARY_TOOL  = 'cmsa.diagnostic-canary';
	const MAX_RECENT   = 50;

	public static function register_routes() {
		if ( ! CUA_MCP_Settings_Page::is_enabled() ) {
			return;
		}

		register_rest_route(
			CUA_MCP_Server::REST_NAMESPACE,
			self::CANARY_ROUTE,
			array(
				'methods'             => array( WP_REST_Server::CREATABLE, WP_REST_Server::READABLE ),
				'callback'            => array( __CLASS__, 'handle_canary_request' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			CUA_MCP_Server::REST_NAMESPACE,
			self::REPORT_ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_report' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function rest_report( WP_REST_Request $request ) {
		$authorization = CUA_MCP_Server::authorize_request( $request );
		if ( is_wp_error( $authorization ) ) {
			return $authorization;
		}

		$response = new WP_REST_Response( self::admin_report(), 200 );
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	public static function public_summary() {
		$catalog = self::catalog_report( false );
		return array(
			'endpoint'             => rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE ),
			'canaryEndpoint'       => rest_url( CUA_MCP_Server::REST_NAMESPACE . self::CANARY_ROUTE ),
			'toolCount'            => (int) ( $catalog['toolCount'] ?? 0 ),
			'pageSize'             => (int) ( $catalog['pageSize'] ?? CUA_MCP_Server::TOOL_PAGE_SIZE ),
			'pageCount'            => (int) ( $catalog['pageCount'] ?? 0 ),
			'catalogJsonBytes'     => (int) ( $catalog['catalogJsonBytes'] ?? 0 ),
			'catalogSha256'        => (string) ( $catalog['catalogSha256'] ?? '' ),
			'descriptorPass'       => (int) ( $catalog['descriptorSummary']['pass'] ?? 0 ),
			'descriptorFail'       => (int) ( $catalog['descriptorSummary']['fail'] ?? 0 ),
			'canaryTool'           => self::CANARY_TOOL,
			'canarySecurityScheme' => 'noauth',
		);
	}

	public static function admin_report() {
		$recent = class_exists( 'CUA_Audit' ) ? CUA_Audit::read_mcp_diagnostics( self::MAX_RECENT ) : array( 'entries' => array() );
		if ( is_wp_error( $recent ) ) {
			$recent = array( 'entries' => array() );
		}

		return array(
			'pluginVersion'       => defined( 'CUA_VERSION' ) ? CUA_VERSION : 'unknown',
			'generatedAt'         => gmdate( 'c' ),
			'protocolVersion'     => CUA_MCP_Server::PROTOCOL_VERSION,
			'endpoint'            => rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE ),
			'canaryEndpoint'      => rest_url( CUA_MCP_Server::REST_NAMESPACE . self::CANARY_ROUTE ),
			'diagnosticsEndpoint' => rest_url( CUA_MCP_Server::REST_NAMESPACE . self::REPORT_ROUTE ),
			'catalog'             => self::catalog_report( true ),
			'canary'              => array(
				'tool'       => self::canary_tool_descriptor(),
				'descriptor' => self::validate_descriptor( self::canary_tool_descriptor() ),
			),
			'recentExchanges'     => $recent['entries'] ?? array(),
		);
	}

	public static function catalog_report( $include_descriptors = true ) {
		$tools = class_exists( 'CUA_MCP_Server' ) ? CUA_MCP_Server::diagnostic_tools() : array();
		$encoded = self::json_text( $tools );
		$names = array();
		$descriptors = array();
		$pass = 0;
		$fail = 0;

		foreach ( $tools as $tool ) {
			if ( ! is_array( $tool ) ) {
				++$fail;
				$descriptors[] = array(
					'name'   => '',
					'pass'   => false,
					'issues' => array( 'descriptor_not_object' ),
				);
				continue;
			}
			$names[] = (string) ( $tool['name'] ?? '' );
			$verdict = self::validate_descriptor( $tool );
			if ( $verdict['pass'] ) {
				++$pass;
			} else {
				++$fail;
			}
			$descriptors[] = $verdict;
		}

		$report = array(
			'toolCount'        => count( $tools ),
			'pageSize'         => CUA_MCP_Server::TOOL_PAGE_SIZE,
			'pageCount'        => empty( $tools ) ? 0 : (int) ceil( count( $tools ) / CUA_MCP_Server::TOOL_PAGE_SIZE ),
			'catalogJsonBytes' => strlen( $encoded ),
			'catalogSha256'    => hash( 'sha256', $encoded ),
			'names'            => $names,
			'descriptorSummary' => array(
				'pass' => $pass,
				'fail' => $fail,
			),
		);

		if ( $include_descriptors ) {
			$report['descriptors'] = $descriptors;
		}
		return $report;
	}

	public static function validate_descriptor( array $tool ) {
		$issues = array();
		$name = isset( $tool['name'] ) ? trim( (string) $tool['name'] ) : '';

		if ( '' === $name || ! preg_match( '/^[A-Za-z0-9_.-]+$/', $name ) ) {
			$issues[] = 'invalid_name';
		}
		if ( ! isset( $tool['title'] ) || '' === trim( (string) $tool['title'] ) ) {
			$issues[] = 'missing_title';
		}
		if ( ! isset( $tool['description'] ) || '' === trim( (string) $tool['description'] ) ) {
			$issues[] = 'missing_description';
		}

		$input_schema = $tool['inputSchema'] ?? null;
		if ( ! is_array( $input_schema ) || 'object' !== ( $input_schema['type'] ?? null ) ) {
			$issues[] = 'invalid_input_schema';
		} elseif ( ! self::json_encodable( $input_schema ) ) {
			$issues[] = 'input_schema_not_json';
		}

		$output_schema = $tool['outputSchema'] ?? null;
		if ( ! is_array( $output_schema ) ) {
			$issues[] = 'missing_output_schema';
		} elseif ( ! self::json_encodable( $output_schema ) ) {
			$issues[] = 'output_schema_not_json';
		}

		$annotations = $tool['annotations'] ?? null;
		if ( ! is_array( $annotations ) ) {
			$issues[] = 'missing_annotations';
		} else {
			foreach ( array( 'readOnlyHint', 'destructiveHint', 'openWorldHint' ) as $annotation ) {
				if ( ! array_key_exists( $annotation, $annotations ) || ! is_bool( $annotations[ $annotation ] ) ) {
					$issues[] = 'invalid_annotation_' . $annotation;
				}
			}
			if ( array_key_exists( 'idempotentHint', $annotations ) && ! is_bool( $annotations['idempotentHint'] ) ) {
				$issues[] = 'invalid_annotation_idempotentHint';
			}
		}

		$schemes = $tool['securitySchemes'] ?? null;
		$mirrored = is_array( $tool['_meta'] ?? null ) ? ( $tool['_meta']['securitySchemes'] ?? null ) : null;
		if ( ! is_array( $schemes ) || empty( $schemes ) ) {
			$issues[] = 'missing_security_schemes';
		} else {
			foreach ( $schemes as $scheme ) {
				if ( ! is_array( $scheme ) || ! in_array( (string) ( $scheme['type'] ?? '' ), array( 'oauth2', 'noauth' ), true ) ) {
					$issues[] = 'invalid_security_scheme';
					continue;
				}
				if ( 'oauth2' === ( $scheme['type'] ?? '' ) ) {
					$scopes = $scheme['scopes'] ?? null;
					if ( ! is_array( $scopes ) || ! in_array( CUA_OAuth_Server::SCOPE, $scopes, true ) ) {
						$issues[] = 'oauth_scope_missing';
					}
				}
			}
		}
		if ( $schemes !== $mirrored ) {
			$issues[] = 'security_schemes_meta_mismatch';
		}

		$descriptor_json = self::json_text( $tool );
		$input_json = is_array( $input_schema ) ? self::json_text( $input_schema ) : '';
		$output_json = is_array( $output_schema ) ? self::json_text( $output_schema ) : '';

		return array(
			'name'               => $name,
			'pass'               => empty( $issues ),
			'issues'             => array_values( array_unique( $issues ) ),
			'descriptorSha256'   => hash( 'sha256', $descriptor_json ),
			'inputSchemaSha256'  => '' === $input_json ? '' : hash( 'sha256', $input_json ),
			'outputSchemaSha256' => '' === $output_json ? '' : hash( 'sha256', $output_json ),
		);
	}

	public static function record_exchange( WP_REST_Request $request, array $payload, WP_REST_Response $response, $mcp_surface = 'main' ) {
		if ( ! class_exists( 'CUA_Audit' ) ) {
			return;
		}

		$method = isset( $payload['method'] ) ? (string) $payload['method'] : '';
		$request_body = (string) $request->get_body();
		$response_json = self::json_text( $response->get_data() );
		$request_sha = hash( 'sha256', $request_body );
		$response_sha = hash( 'sha256', $response_json );
		$response_data = $response->get_data();
		$result = is_array( $response_data ) && is_array( $response_data['result'] ?? null ) ? $response_data['result'] : array();
		$tools = is_array( $result['tools'] ?? null ) ? $result['tools'] : array();
		$descriptor_pass = 0;
		$descriptor_fail = 0;
		foreach ( $tools as $tool ) {
			$verdict = is_array( $tool ) ? self::validate_descriptor( $tool ) : array( 'pass' => false );
			if ( ! empty( $verdict['pass'] ) ) {
				++$descriptor_pass;
			} else {
				++$descriptor_fail;
			}
		}

		$error_code = '';
		if ( is_array( $response_data ) && is_array( $response_data['error'] ?? null ) ) {
			$error_code = isset( $response_data['error']['code'] ) ? (string) $response_data['error']['code'] : '';
		}

		CUA_Audit::log_mcp_diagnostic(
			array(
				'time'                => gmdate( 'c' ),
				'mcp_surface'         => (string) $mcp_surface,
				'mcp_method'          => $method,
				'protocol_version'    => trim( (string) $request->get_header( 'mcp-protocol-version' ) ),
				'http_status'         => (int) $response->get_status(),
				'client_class'        => self::client_class( (string) $request->get_header( 'user-agent' ) ),
				'authorization_present' => '' !== trim( (string) $request->get_header( 'authorization' ) ),
				'request_bytes'       => strlen( $request_body ),
				'request_sha256'      => $request_sha,
				'response_bytes'      => strlen( $response_json ),
				'response_sha256'     => $response_sha,
				'correlation_sha256'  => hash( 'sha256', $request_sha . '|' . $response_sha . '|' . $method ),
				'tool_count'          => count( $tools ),
				'next_cursor_present' => isset( $result['nextCursor'] ) && '' !== trim( (string) $result['nextCursor'] ),
				'descriptor_pass'     => $descriptor_pass,
				'descriptor_fail'     => $descriptor_fail,
				'result_type'         => isset( $result['resultType'] ) ? (string) $result['resultType'] : '',
				'error_code'          => $error_code,
			)
		);
	}

	public static function handle_canary_request( WP_REST_Request $request ) {
		if ( 'GET' === strtoupper( $request->get_method() ) ) {
			$response = new WP_REST_Response( null, 405 );
			$response->header( 'Allow', 'POST' );
			return $response;
		}

		$content_type = strtolower( trim( (string) $request->get_header( 'content-type' ) ) );
		$content_type = '' !== $content_type ? trim( strtok( $content_type, ';' ) ) : '';
		if ( 'application/json' !== $content_type ) {
			return self::canary_error_response( null, -32020, 'Diagnostic canary POST requests require Content-Type: application/json.', 415 );
		}

		$payload = json_decode( (string) $request->get_body(), true );
		if ( ! is_array( $payload ) || self::is_list_array( $payload ) ) {
			return self::canary_error_response( null, -32700, 'The diagnostic canary request body is not valid JSON-RPC.', 400 );
		}
		$id = array_key_exists( 'id', $payload ) ? $payload['id'] : null;
		$method = isset( $payload['method'] ) && is_string( $payload['method'] ) ? $payload['method'] : '';
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();

		if ( '2.0' !== ( $payload['jsonrpc'] ?? null ) || '' === $method || ! array_key_exists( 'id', $payload ) ) {
			return self::canary_error_response( $id, -32600, 'Invalid diagnostic canary JSON-RPC request.', 400 );
		}
		if ( CUA_MCP_Server::PROTOCOL_VERSION !== trim( (string) $request->get_header( 'mcp-protocol-version' ) ) ) {
			return self::canary_error_response( $id, -32022, 'The diagnostic canary requires MCP-Protocol-Version: ' . CUA_MCP_Server::PROTOCOL_VERSION . '.', 400 );
		}
		if ( $method !== trim( (string) $request->get_header( 'mcp-method' ) ) ) {
			return self::canary_error_response( $id, -32020, 'Mcp-Method must match the diagnostic canary JSON-RPC method.', 400 );
		}
		$meta = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : null;
		if ( ! is_array( $meta )
			|| CUA_MCP_Server::PROTOCOL_VERSION !== trim( (string) ( $meta['io.modelcontextprotocol/protocolVersion'] ?? '' ) )
			|| ! isset( $meta['io.modelcontextprotocol/clientCapabilities'] )
			|| ! is_array( $meta['io.modelcontextprotocol/clientCapabilities'] )
		) {
			return self::canary_error_response( $id, -32020, 'The diagnostic canary requires the modern MCP _meta envelope.', 400 );
		}

		if ( 'server/discover' === $method ) {
			$response = self::canary_success_response(
				$id,
				array(
					'supportedVersions' => array( CUA_MCP_Server::PROTOCOL_VERSION ),
					'capabilities'      => array( 'tools' => array( 'listChanged' => false ) ),
					'instructions'      => 'Read-only Chattanooga MCP ingestion canary. It exposes no site content and performs no site mutations.',
					'ttlMs'             => 30000,
					'cacheScope'        => 'private',
				)
			);
			self::record_exchange( $request, $payload, $response, 'canary' );
			return $response;
		}

		if ( 'tools/list' === $method ) {
			$response = self::canary_success_response(
				$id,
				array(
					'tools'      => array( self::canary_tool_descriptor() ),
					'ttlMs'      => 30000,
					'cacheScope' => 'private',
				)
			);
			self::record_exchange( $request, $payload, $response, 'canary' );
			return $response;
		}

		if ( 'tools/call' === $method ) {
			$name = isset( $params['name'] ) ? trim( (string) $params['name'] ) : '';
			if ( self::CANARY_TOOL !== $name || self::CANARY_TOOL !== trim( (string) $request->get_header( 'mcp-name' ) ) ) {
				$response = self::canary_error_response( $id, -32602, 'The diagnostic canary tool name is invalid.', 400 );
				self::record_exchange( $request, $payload, $response, 'canary' );
				return $response;
			}
			$arguments = isset( $params['arguments'] ) ? $params['arguments'] : array();
			if ( ! is_array( $arguments ) || ( ! empty( $arguments ) && self::is_list_array( $arguments ) ) ) {
				$response = self::canary_error_response( $id, -32602, 'The diagnostic canary arguments must be a JSON object.', 400 );
				self::record_exchange( $request, $payload, $response, 'canary' );
				return $response;
			}
			$structured = array(
				'ok'              => true,
				'pluginVersion'   => defined( 'CUA_VERSION' ) ? CUA_VERSION : 'unknown',
				'protocolVersion' => CUA_MCP_Server::PROTOCOL_VERSION,
				'diagnostic'      => 'ingestion-canary',
			);
			$response = self::canary_success_response(
				$id,
				array(
					'content' => array(
						array(
							'type' => 'text',
							'text' => 'Chattanooga MCP diagnostic canary passed.',
						),
					),
					'structuredContent' => $structured,
					'isError'           => false,
				)
			);
			self::record_exchange( $request, $payload, $response, 'canary' );
			return $response;
		}

		$response = self::canary_error_response( $id, -32601, 'Diagnostic canary method not found.', 404 );
		self::record_exchange( $request, $payload, $response, 'canary' );
		return $response;
	}

	public static function canary_tool_descriptor() {
		$security = array( array( 'type' => 'noauth' ) );
		return array(
			'name'        => self::CANARY_TOOL,
			'title'       => 'Chattanooga MCP diagnostic canary',
			'description' => 'Returns a fixed, read-only protocol health result. It reads no site content and changes no site state.',
			'inputSchema' => array(
				'type'                 => 'object',
				'properties'           => new stdClass(),
				'additionalProperties' => false,
			),
			'outputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'ok'              => array( 'type' => 'boolean' ),
					'pluginVersion'   => array( 'type' => 'string' ),
					'protocolVersion' => array( 'type' => 'string' ),
					'diagnostic'      => array( 'type' => 'string' ),
				),
				'required'             => array( 'ok', 'pluginVersion', 'protocolVersion', 'diagnostic' ),
				'additionalProperties' => false,
			),
			'annotations' => array(
				'readOnlyHint'    => true,
				'destructiveHint' => false,
				'idempotentHint'  => true,
				'openWorldHint'   => false,
			),
			'securitySchemes' => $security,
			'_meta'           => array( 'securitySchemes' => $security ),
		);
	}

	private static function canary_success_response( $id, array $result ) {
		$result['resultType'] = 'complete';
		$result['_meta'] = array(
			'io.modelcontextprotocol/serverInfo' => array(
				'name'    => 'chattanooga-cms-admin-canary',
				'title'   => 'Chattanooga CMS Admin Diagnostic Canary',
				'version' => defined( 'CUA_VERSION' ) ? CUA_VERSION : 'unknown',
			),
		);
		$response = new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'result'  => $result,
			),
			200
		);
		$response->header( 'MCP-Protocol-Version', CUA_MCP_Server::PROTOCOL_VERSION );
		return $response;
	}

	private static function canary_error_response( $id, $code, $message, $status ) {
		$response = new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => array(
					'code'    => (int) $code,
					'message' => (string) $message,
				),
				'_meta'   => array(
					'io.modelcontextprotocol/serverInfo' => array(
						'name'    => 'chattanooga-cms-admin-canary',
						'title'   => 'Chattanooga CMS Admin Diagnostic Canary',
						'version' => defined( 'CUA_VERSION' ) ? CUA_VERSION : 'unknown',
					),
				),
			),
			(int) $status
		);
		$response->header( 'MCP-Protocol-Version', CUA_MCP_Server::PROTOCOL_VERSION );
		return $response;
	}

	private static function client_class( $user_agent ) {
		$user_agent = strtolower( trim( (string) $user_agent ) );
		if ( '' === $user_agent ) {
			return 'unknown';
		}
		if ( false !== strpos( $user_agent, 'chatgpt' ) || false !== strpos( $user_agent, 'openai' ) ) {
			return 'chatgpt';
		}
		if ( false !== strpos( $user_agent, 'curl' ) ) {
			return 'curl';
		}
		if ( false !== strpos( $user_agent, 'wordpress' ) || false !== strpos( $user_agent, 'wp-' ) ) {
			return 'wordpress';
		}
		if ( false !== strpos( $user_agent, 'mozilla' ) ) {
			return 'browser';
		}
		return 'other';
	}

	private static function json_encodable( $value ) {
		return false !== wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private static function json_text( $value ) {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return false === $encoded ? 'null' : (string) $encoded;
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
