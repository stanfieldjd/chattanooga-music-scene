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
	const CANARY_ROUTE       = '/mcp-canary';
	const REPORT_ROUTE       = '/mcp/diagnostics';
	const HEARTBEAT_ROUTE    = '/mcp-heartbeat';
	const CANARY_TOOL        = 'cmsa.diagnostic-canary';
	const STABILITY_ABILITY  = 'chattanooga-cms-admin/stability-check';
	const STABILITY_TOOL     = 'cmsa.stability-check';
	const MAX_RECENT         = 50;

	public static function register_ability() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::STABILITY_ABILITY,
			array(
				'label'               => __( 'Check MCP stability', 'chattanooga-cms-admin' ),
				'description'         => __( 'Returns read-only server-side MCP stability evidence: plugin/protocol identity, deterministic core-tool catalog fingerprint and descriptor health, plus recent secret-free discovery diagnostics. It cannot inspect ChatGPT private runtime registry state.', 'chattanooga-cms-admin' ),
				'category'            => 'chattanooga-cms-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_RECENT, 'default' => 20 ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'stability_report' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true, 'open_world' => false ),
				),
			)
		);
	}

	/**
	 * Register a stable callable discovery ability in addition to the protocol
	 * server/discover method, so clients that ingest tools only can bootstrap.
	 */
	public static function register_discovery_ability() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'chattanooga-cms-admin/discovery',
			array(
				'label'               => __( 'Discover Chattanooga MCP tools', 'chattanooga-cms-admin' ),
				'description'         => __( 'Returns the stable Chattanooga MCP tool manifest, fingerprint, catalog entry point, and next discovery steps.', 'chattanooga-cms-admin' ),
				'category'            => 'chattanooga-cms-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'cursor' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
						'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 100 ),
						'snapshot' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( 'CUA_MCP_Server', 'discovery_manifest' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true, 'open_world' => false ),
				),
			)
		);
	}

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

		register_rest_route(
			CUA_MCP_Server::REST_NAMESPACE,
			self::HEARTBEAT_ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'heartbeat_report' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return sanitized out-of-band evidence for the MCP ingestion boundary.
	 * This endpoint deliberately lives outside tools/list and tools/call.
	 */
	public static function heartbeat_report( WP_REST_Request $request ) {
		if ( ! self::allow_public_request( 'heartbeat', 120 ) ) {
			return self::rate_limited_response();
		}
		$catalog = self::catalog_report( false );
		$recent = class_exists( 'CUA_Audit' ) ? CUA_Audit::read_mcp_diagnostics( 25 ) : array( 'entries' => array() );
		$entries = is_wp_error( $recent ) ? array() : (array) ( $recent['entries'] ?? array() );
		$latest = null;
		$latest_discover = null;
		$latest_tools_list = null;
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			if ( null === $latest ) {
				$latest = self::heartbeat_entry( $entry );
			}
			if ( 'server/discover' === ( $entry['mcp_method'] ?? '' ) && null === $latest_discover ) {
				$latest_discover = self::heartbeat_entry( $entry );
			}
			if ( 'tools/list' === ( $entry['mcp_method'] ?? '' ) && null === $latest_tools_list ) {
				$latest_tools_list = self::heartbeat_entry( $entry );
			}
		}
		$observed = null !== $latest;
		$result = array(
			'state'              => $observed ? 'server_request_observed' : 'no_mcp_request_observed',
			'pluginVersion'      => defined( 'CUA_VERSION' ) ? CUA_VERSION : 'unknown',
			'protocolVersion'    => CUA_MCP_Server::PROTOCOL_VERSION,
			'checkedAt'          => gmdate( 'c' ),
			'serverToolCount'    => (int) ( $catalog['toolCount'] ?? 0 ),
			'toolFingerprint'    => (string) ( $catalog['toolFingerprint'] ?? '' ),
			'descriptorPass'     => (int) ( $catalog['descriptorSummary']['pass'] ?? 0 ),
			'descriptorFail'     => (int) ( $catalog['descriptorSummary']['fail'] ?? 0 ),
			'lastRequest'        => $latest,
			'lastDiscovery'      => $latest_discover,
			'lastToolsList'      => $latest_tools_list,
			'scope'              => 'Sanitized server-side evidence only. no_mcp_request_observed means this endpoint has no recorded MCP request; it does not identify why the host omitted the app.',
		);
		$response = new WP_REST_Response( $result, 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	private static function heartbeat_entry( array $entry ) {
		$keys = array( 'time', 'mcp_surface', 'mcp_method', 'protocol_version', 'http_status', 'client_class', 'request_bytes', 'response_bytes', 'tool_count', 'descriptor_pass', 'descriptor_fail', 'result_type', 'error_code' );
		$result = array();
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $entry ) ) {
				$result[ $key ] = $entry[ $key ];
			}
		}
		return $result;
	}

	public static function stability_report( $input = array() ) {
		$limit = is_array( $input ) && isset( $input['limit'] ) ? (int) $input['limit'] : 20;
		$limit = max( 1, min( self::MAX_RECENT, $limit ) );
		$catalog = self::catalog_report( false );
		$recent = class_exists( 'CUA_Audit' ) ? CUA_Audit::read_mcp_diagnostics( $limit ) : new WP_Error( 'cmsa_stability_audit_unavailable', 'MCP diagnostic audit storage is unavailable.' );
		$request_trace = class_exists( 'CUA_Audit' ) ? CUA_Audit::read_mcp_request_trace( $limit ) : new WP_Error( 'cmsa_request_trace_unavailable', 'MCP request trace storage is unavailable.' );
		$audit_readable = ! is_wp_error( $recent );
		$entries = $audit_readable && is_array( $recent['entries'] ?? null ) ? $recent['entries'] : array();
		$request_trace_readable = ! is_wp_error( $request_trace );
		$request_entries = $request_trace_readable && is_array( $request_trace['entries'] ?? null ) ? $request_trace['entries'] : array();
		$latest_main_list = null;
		$latest_canary_list = null;
		$counts = array( 'mainToolsList' => 0, 'canaryToolsList' => 0, 'mainDiscover' => 0, 'canaryDiscover' => 0 );
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) { continue; }
			$surface = (string) ( $entry['mcp_surface'] ?? '' );
			$method = (string) ( $entry['mcp_method'] ?? '' );
			if ( 'main' === $surface && 'tools/list' === $method ) { ++$counts['mainToolsList']; if ( null === $latest_main_list ) { $latest_main_list = $entry; } }
			elseif ( 'canary' === $surface && 'tools/list' === $method ) { ++$counts['canaryToolsList']; if ( null === $latest_canary_list ) { $latest_canary_list = $entry; } }
			if ( 'server/discover' === $method ) { if ( 'main' === $surface ) { ++$counts['mainDiscover']; } elseif ( 'canary' === $surface ) { ++$counts['canaryDiscover']; } }
		}
		$tool_count = (int) ( $catalog['toolCount'] ?? 0 );
		$descriptor_pass = (int) ( $catalog['descriptorSummary']['pass'] ?? 0 );
		$descriptor_fail = (int) ( $catalog['descriptorSummary']['fail'] ?? 0 );
		$tool_fingerprint = (string) ( $catalog['toolFingerprint'] ?? '' );
		$catalog_healthy = 0 < $tool_count && 0 === $descriptor_fail && $tool_count === $descriptor_pass && 1 === preg_match( '/^[a-f0-9]{64}$/', $tool_fingerprint );
		$latest_main_matches = null;
		if ( is_array( $latest_main_list ) ) {
			$latest_main_matches = 200 === (int) ( $latest_main_list['http_status'] ?? 0 ) && $tool_count === (int) ( $latest_main_list['tool_count'] ?? -1 ) && $tool_fingerprint === (string) ( $latest_main_list['tool_fingerprint'] ?? '' ) && 0 === (int) ( $latest_main_list['descriptor_fail'] ?? -1 );
		}
		$latest_canary_healthy = null;
		if ( is_array( $latest_canary_list ) ) {
			$latest_canary_healthy = 200 === (int) ( $latest_canary_list['http_status'] ?? 0 ) && 1 === (int) ( $latest_canary_list['tool_count'] ?? -1 ) && 0 === (int) ( $latest_canary_list['descriptor_fail'] ?? -1 );
		}
		/*
		 * A historical tools/list record can legitimately belong to an older
		 * immutable startup ABI after a plugin update. Keep that evidence visible,
		 * but do not misclassify the current server catalog as broken because the
		 * historical fingerprint is stale. A failed observed Canary exchange is
		 * still degraded; an unobserved Canary exchange remains insufficient
		 * history.
		 */
		$history_state = 'insufficient_history';
		if ( false === $latest_main_matches || false === $latest_canary_healthy ) {
			$history_state = 'stale_or_failed_observation';
		} elseif ( true === $latest_main_matches && true === $latest_canary_healthy ) {
			$history_state = 'verified';
		}
		if ( ! $catalog_healthy || false === $latest_canary_healthy ) {
			$state = 'degraded';
		} elseif ( false === $latest_main_matches ) {
			$state = 'stale_history';
		} elseif ( true === $latest_main_matches && true === $latest_canary_healthy ) {
			$state = 'healthy';
		} else {
			$state = 'insufficient_history';
		}
		return array(
			'state' => $state,
			'pluginVersion' => defined( 'CUA_VERSION' ) ? CUA_VERSION : 'unknown',
			'protocolVersion' => CUA_MCP_Server::PROTOCOL_VERSION,
			'generatedAt' => gmdate( 'c' ),
			'serverCatalogHealthy' => $catalog_healthy,
			'serverToolCount' => $tool_count,
			'serverToolFingerprint' => $tool_fingerprint,
			'catalogSha256' => (string) ( $catalog['catalogSha256'] ?? '' ),
			'descriptorPass' => $descriptor_pass,
			'descriptorFail' => $descriptor_fail,
			'auditReadable' => $audit_readable,
			'latestMainDiscoveryMatches' => $latest_main_matches,
			'latestCanaryDiscoveryHealthy' => $latest_canary_healthy,
			'historyState' => $history_state,
			'observationCounts' => $counts,
			'latestMainToolsList' => $latest_main_list,
			'latestCanaryToolsList' => $latest_canary_list,
			'recentDiagnostics' => $entries,
			'requestTraceReadable' => $request_trace_readable,
			'requestTraceCount' => count( $request_entries ),
			'recentRequests' => $request_entries,
			'scope' => 'Each recentRequests entry is recorded at the WordPress MCP handler after it returns, including rejected requests. Presence proves the request reached this handler; absence does not distinguish host registry behavior from network, proxy, or earlier server-layer rejection.',
		);
	}

	/** Record one secret-free terminal trace for every request reaching the MCP handler. */
	public static function record_request_trace( WP_REST_Request $request, $response, $started ) {
		if ( ! class_exists( 'CUA_Audit' ) ) {
			return false;
		}
		$body = (string) $request->get_body();
		$payload = json_decode( $body, true );
		$payload = is_array( $payload ) ? $payload : array();
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();
		$meta = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
		$client_info = isset( $meta['io.modelcontextprotocol/clientInfo'] ) && is_array( $meta['io.modelcontextprotocol/clientInfo'] )
			? $meta['io.modelcontextprotocol/clientInfo']
			: ( isset( $params['clientInfo'] ) && is_array( $params['clientInfo'] ) ? $params['clientInfo'] : array() );
		$meta_protocol = isset( $meta['io.modelcontextprotocol/protocolVersion'] ) && is_scalar( $meta['io.modelcontextprotocol/protocolVersion'] )
			? (string) $meta['io.modelcontextprotocol/protocolVersion']
			: '';
		$response_data = array();
		if ( is_wp_error( $response ) ) {
			$error_code = (string) $response->get_error_code();
			$error_data = $response->get_error_data();
			$status = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 500;
		} else {
			$error_code = '';
			$status = is_object( $response ) && method_exists( $response, 'get_status' ) ? (int) $response->get_status() : 500;
			$response_data = is_object( $response ) && method_exists( $response, 'get_data' ) ? $response->get_data() : array();
		}
		$response_error = is_array( $response_data ) && isset( $response_data['error'] ) && is_array( $response_data['error'] ) ? $response_data['error'] : array();
		$jsonrpc_error_code = isset( $response_error['code'] ) && is_scalar( $response_error['code'] ) ? (string) $response_error['code'] : '';
		$response_result = is_array( $response_data ) && isset( $response_data['result'] ) && is_array( $response_data['result'] ) ? $response_data['result'] : array();
		$mcp_tool_error = true === ( $response_result['isError'] ?? false );
		if ( '' === $error_code && '' !== $jsonrpc_error_code ) {
			$error_code = 'mcp_jsonrpc_error';
		} elseif ( '' === $error_code && $mcp_tool_error ) {
			$error_code = 'mcp_tool_error';
		}
		$response_headers = is_object( $response ) && method_exists( $response, 'get_headers' ) ? array_change_key_case( (array) $response->get_headers(), CASE_LOWER ) : array();
		$authorization = trim( (string) $request->get_header( 'authorization' ) );
		$auth_scheme = '' === $authorization ? 'none' : ( preg_match( '/^Bearer\s/i', $authorization ) ? 'bearer' : ( preg_match( '/^Basic\s/i', $authorization ) ? 'basic' : 'other' ) );
		$entry = array(
			'time' => gmdate( 'c' ),
			'http_method' => strtoupper( (string) $request->get_method() ),
			'mcp_method' => isset( $payload['method'] ) && is_scalar( $payload['method'] ) ? (string) $payload['method'] : '',
			'mcp_method_header' => (string) $request->get_header( 'mcp-method' ),
			'mcp_name_header' => (string) $request->get_header( 'mcp-name' ),
			'tool' => 'tools/call' === ( $payload['method'] ?? '' ) && isset( $params['name'] ) && is_scalar( $params['name'] ) ? (string) $params['name'] : '',
			'protocol_version_header' => trim( (string) $request->get_header( 'mcp-protocol-version' ) ),
			'protocol_version_body' => isset( $params['protocolVersion'] ) && is_scalar( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '',
			'protocol_version_meta' => $meta_protocol,
			'response_protocol_version' => isset( $response_headers['mcp-protocol-version'] ) ? (string) $response_headers['mcp-protocol-version'] : '',
			'response_session_present' => isset( $response_headers[ strtolower( CUA_MCP_Server::SESSION_HEADER ) ] ) && '' !== trim( (string) $response_headers[ strtolower( CUA_MCP_Server::SESSION_HEADER ) ] ),
			'client_info_name' => isset( $client_info['name'] ) && is_scalar( $client_info['name'] ) ? (string) $client_info['name'] : '',
			'client_info_version' => isset( $client_info['version'] ) && is_scalar( $client_info['version'] ) ? (string) $client_info['version'] : '',
			'client_class' => self::client_class( (string) $request->get_header( 'user-agent' ) ),
			'user_agent' => (string) $request->get_header( 'user-agent' ),
			'origin' => (string) $request->get_header( 'origin' ),
			'content_type' => (string) $request->get_header( 'content-type' ),
			'accept' => (string) $request->get_header( 'accept' ),
			'authorization_present' => '' !== $authorization,
			'auth_scheme' => $auth_scheme,
			'request_bytes' => strlen( $body ),
			'request_sha256' => hash( 'sha256', $body ),
			'request_id_sha256' => array_key_exists( 'id', $payload ) && is_scalar( $payload['id'] ) ? hash( 'sha256', (string) $payload['id'] ) : '',
			'http_status' => $status,
			'outcome' => $status >= 400 ? 'http_error' : ( '' !== $jsonrpc_error_code ? 'jsonrpc_error' : ( $mcp_tool_error ? 'tool_error' : 'response' ) ),
			'error_code' => $error_code,
			'jsonrpc_error_code' => $jsonrpc_error_code,
			'duration_ms' => max( 0, (int) round( ( microtime( true ) - (float) $started ) * 1000 ) ),
		);
		return CUA_Audit::log_mcp_request_trace( $entry );
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
			'heartbeatEndpoint'    => rest_url( CUA_MCP_Server::REST_NAMESPACE . self::HEARTBEAT_ROUTE ),
			'toolCount'            => (int) ( $catalog['toolCount'] ?? 0 ),
			'toolFingerprint'      => (string) ( $catalog['toolFingerprint'] ?? '' ),
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
			'toolFingerprint'  => class_exists( 'CUA_MCP_Server' ) ? CUA_MCP_Server::tool_fingerprint() : '',
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
				'tool_fingerprint'    => isset( $result['toolFingerprint'] ) ? (string) $result['toolFingerprint'] : ( class_exists( 'CUA_MCP_Server' ) ? CUA_MCP_Server::tool_fingerprint() : '' ),
				'next_cursor_present' => isset( $result['nextCursor'] ) && '' !== trim( (string) $result['nextCursor'] ),
				'descriptor_pass'     => $descriptor_pass,
				'descriptor_fail'     => $descriptor_fail,
				'result_type'         => isset( $result['resultType'] ) ? (string) $result['resultType'] : '',
				'error_code'          => $error_code,
			)
		);
	}

	public static function handle_canary_request( WP_REST_Request $request ) {
		if ( ! self::allow_public_request( 'canary', 240 ) ) {
			return self::rate_limited_response();
		}
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
					'ttlMs'      => 0,
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
					'ttlMs'      => 0,
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
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
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
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
		return $response;
	}

	private static function allow_public_request( $bucket, $limit ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'cua_public_rate_' . substr( hash( 'sha256', (string) $bucket . '|' . $ip ), 0, 40 );
		$lock_key = $key . '_lock';
		$lock_acquired = false;

		// Persistent object caches can provide an atomic short lock across
		// concurrent requests. Without one, retain the transient fallback but
		// report only best-effort rate limiting rather than implying strictness.
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_add' ) ) {
			$lock_acquired = wp_cache_add( $lock_key, 1, 'chattanooga-cms-admin-rate', 5 );
			if ( ! $lock_acquired ) {
				return false;
			}
		}

		try {
			$record = get_transient( $key );
			$record = is_array( $record ) ? $record : array( 'count' => 0 );
			if ( (int) ( $record['count'] ?? 0 ) >= (int) $limit ) {
				return false;
			}
			$record['count'] = (int) ( $record['count'] ?? 0 ) + 1;
			set_transient( $key, $record, MINUTE_IN_SECONDS );
			return true;
		} finally {
			if ( $lock_acquired && function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( $lock_key, 'chattanooga-cms-admin-rate' );
			}
		}
	}

	private static function rate_limited_response() {
		$response = new WP_REST_Response(
			array(
				'code'    => 'cmsa_public_rate_limited',
				'message' => 'The public diagnostic endpoint rate limit has been exceeded.',
			),
			429
		);
		$response->header( 'Retry-After', '60' );
		$response->header( 'Cache-Control', 'no-store' );
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
