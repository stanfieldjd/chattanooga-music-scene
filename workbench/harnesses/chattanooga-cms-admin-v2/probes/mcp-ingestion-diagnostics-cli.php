<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_ingestion_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_ingestion_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_ingestion_fail( $message );
	}
}

function cmsa_ingestion_modern_request( $route, $method, array $params = array(), $id = 701 ) {
	$params['_meta'] = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
	$params['_meta']['io.modelcontextprotocol/protocolVersion'] = CUA_MCP_Server::PROTOCOL_VERSION;
	$params['_meta']['io.modelcontextprotocol/clientCapabilities'] = array();
	$params['_meta']['io.modelcontextprotocol/clientInfo'] = array(
		'name'    => 'cmsa-ingestion-diagnostic-probe',
		'version' => '1.0.0',
	);

	$request = new WP_REST_Request( 'POST', $route );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'user-agent', 'cmsa-ingestion-probe/1.0' );
	$request->set_header( 'MCP-Protocol-Version', CUA_MCP_Server::PROTOCOL_VERSION );
	$request->set_header( 'Mcp-Method', $method );
	if ( 'tools/call' === $method && isset( $params['name'] ) ) {
		$request->set_header( 'Mcp-Name', (string) $params['name'] );
	}
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => $method,
				'params'  => $params,
			)
		)
	);
	return rest_do_request( $request );
}

function cmsa_ingestion_rejected_initialize_request() {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'user-agent', 'ChatGPT StandardProbe/1.0' );
	$request->set_header( 'MCP-Protocol-Version', '2025-01-01' );
	$request->set_header( 'Mcp-Method', 'initialize' );
	$request->set_header( 'authorization', 'Bearer trace-secret-sentinel' );
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => 799,
				'method'  => 'initialize',
				'params'  => array(
					'protocolVersion' => '2025-01-01',
					'_meta' => array(
						'io.modelcontextprotocol/clientInfo' => array( 'name' => 'standard-probe', 'version' => '9.9' ),
					),
				),
			)
		)
	);
	return rest_do_request( $request );
}

wp_set_current_user( 1 );

cmsa_ingestion_assert( defined( 'CUA_VERSION' ) && '1.2.48' === CUA_VERSION, 'Chattanooga CMS Admin 1.2.48 did not load.' );
cmsa_ingestion_assert( class_exists( 'CUA_MCP_Diagnostics' ), 'MCP diagnostics class did not load.' );
cmsa_ingestion_assert( class_exists( 'CUA_MCP_Server' ), 'MCP server class did not load.' );
cmsa_ingestion_assert( class_exists( 'CUA_Audit' ), 'Audit class did not load.' );
cmsa_ingestion_assert( class_exists( 'CUA_Control_Plane_Guard' ), 'Control-plane guard did not load.' );

$blocked_routes = array(
	array( 'POST', '/wp/v2/users/me/application-passwords' ),
	array( 'POST', '/wp/v2/users/1/application-passwords' ),
	array( 'DELETE', '/wp/v2/users/me/application-passwords/01234567-89ab-cdef-0123-456789abcdef' ),
	array( 'DELETE', '/wp/v2/users/me' ),
);
foreach ( $blocked_routes as $blocked_route ) {
	$request = new WP_REST_Request( $blocked_route[0], $blocked_route[1] );
	$decision = CUA_Control_Plane_Guard::validate_rest_request( $request, $blocked_route[0] );
	cmsa_ingestion_assert( is_wp_error( $decision ), 'High-impact REST route was not denied: ' . $blocked_route[0] . ' ' . $blocked_route[1] );
	cmsa_ingestion_assert( 'cmsa_high_impact_route_blocked' === $decision->get_error_code(), 'High-impact REST route returned the wrong denial code.' );
}
$allowed_password_read = new WP_REST_Request( 'GET', '/wp/v2/users/me/application-passwords' );
cmsa_ingestion_assert( true === CUA_Control_Plane_Guard::validate_rest_request( $allowed_password_read, 'GET' ), 'Read-only application-password listing was unexpectedly blocked.' );
$allowed_post_write = new WP_REST_Request( 'POST', '/wp/v2/posts' );
cmsa_ingestion_assert( true === CUA_Control_Plane_Guard::validate_rest_request( $allowed_post_write, 'POST' ), 'Ordinary REST writes were unexpectedly blocked by the high-impact policy.' );

$catalog = CUA_MCP_Diagnostics::catalog_report( true );
cmsa_ingestion_assert( (int) ( $catalog['toolCount'] ?? 0 ) > 0, 'Diagnostic catalog is empty.' );
cmsa_ingestion_assert( 0 === (int) ( $catalog['descriptorSummary']['fail'] ?? -1 ), 'Built-in descriptor conformance reported a failure.' );
cmsa_ingestion_assert( (int) ( $catalog['descriptorSummary']['pass'] ?? 0 ) === (int) ( $catalog['toolCount'] ?? -1 ), 'Descriptor PASS count does not equal tool count.' );
cmsa_ingestion_assert( preg_match( '/^[a-f0-9]{64}$/', (string) ( $catalog['catalogSha256'] ?? '' ) ), 'Catalog SHA-256 is missing or malformed.' );
cmsa_ingestion_assert( (int) ( $catalog['catalogJsonBytes'] ?? 0 ) > 0, 'Catalog JSON byte count is empty.' );
cmsa_ingestion_assert( is_array( $catalog['names'] ?? null ) && count( $catalog['names'] ) === (int) $catalog['toolCount'], 'Catalog tool names are incomplete.' );

$main = cmsa_ingestion_modern_request( '/chattanooga-cms-admin/v1/mcp', 'tools/list', array(), 702 );
cmsa_ingestion_assert( 200 === $main->get_status(), 'Main MCP tools/list failed.' );
$main_data = $main->get_data();
$main_tools = $main_data['result']['tools'] ?? null;
cmsa_ingestion_assert( is_array( $main_tools ) && ! empty( $main_tools ), 'Main MCP tools/list returned no tools.' );
$stability_descriptor = null;
foreach ( $main_tools as $tool ) { if ( is_array( $tool ) && CUA_MCP_Diagnostics::STABILITY_TOOL === ( $tool['name'] ?? '' ) ) { $stability_descriptor = $tool; break; } }
cmsa_ingestion_assert( is_array( $stability_descriptor ), 'Main MCP tools/list omitted the stability tool.' );
cmsa_ingestion_assert( true === ( $stability_descriptor['annotations']['readOnlyHint'] ?? null ), 'Stability tool is not read-only.' );
cmsa_ingestion_assert( false === ( $stability_descriptor['annotations']['destructiveHint'] ?? null ), 'Stability tool is incorrectly destructive.' );
cmsa_ingestion_assert( false === ( $stability_descriptor['annotations']['openWorldHint'] ?? null ), 'Stability tool is incorrectly open-world.' );
$stability_call = cmsa_ingestion_modern_request( '/chattanooga-cms-admin/v1/mcp', 'tools/call', array( 'name' => CUA_MCP_Diagnostics::STABILITY_TOOL, 'arguments' => array( 'limit' => 20 ) ), 706 );
cmsa_ingestion_assert( 200 === $stability_call->get_status(), 'Stability tools/call failed.' );
$stability = $stability_call->get_data()['result']['structuredContent'] ?? null;
cmsa_ingestion_assert( is_array( $stability ) && true === ( $stability['serverCatalogHealthy'] ?? false ), 'Stability tool reported an unhealthy server catalog.' );
cmsa_ingestion_assert( (int) $catalog['toolCount'] === (int) ( $stability['serverToolCount'] ?? -1 ), 'Stability tool reported the wrong core tool count.' );
cmsa_ingestion_assert( (string) $catalog['toolFingerprint'] === (string) ( $stability['serverToolFingerprint'] ?? '' ), 'Stability tool reported the wrong core tool fingerprint.' );
cmsa_ingestion_assert( true === ( $stability['latestMainDiscoveryMatches'] ?? false ), 'Stability tool did not match the latest main tools/list observation.' );
cmsa_ingestion_assert( false !== strpos( (string) ( $stability['scope'] ?? '' ), 'Presence proves the request reached this handler' ), 'Stability tool did not explain what request traces prove.' );

$rejected_initialize = cmsa_ingestion_rejected_initialize_request();
cmsa_ingestion_assert( 400 === $rejected_initialize->get_status(), 'Unsupported initialize request was not rejected as expected.' );
$rejected_data = $rejected_initialize->get_data();
cmsa_ingestion_assert( '-32022' === (string) ( $rejected_data['error']['code'] ?? '' ), 'Unsupported initialize request returned the wrong MCP error.' );
$request_trace = CUA_Audit::read_mcp_request_trace( 20 );
cmsa_ingestion_assert( ! is_wp_error( $request_trace ), 'MCP request trace could not be read.' );
$request_entries = $request_trace['entries'] ?? array();
$rejected_entry = null;
foreach ( $request_entries as $entry ) {
	if ( is_array( $entry ) && 'standard-probe' === ( $entry['client_info_name'] ?? '' ) ) {
		$rejected_entry = $entry;
		break;
	}
}
cmsa_ingestion_assert( is_array( $rejected_entry ), 'Rejected initialize request is missing from the request trace.' );
cmsa_ingestion_assert( 'initialize' === ( $rejected_entry['mcp_method'] ?? '' ), 'Trace did not capture the MCP method.' );
cmsa_ingestion_assert( 'initialize' === ( $rejected_entry['mcp_method_header'] ?? '' ), 'Trace did not capture the MCP-Method header.' );
cmsa_ingestion_assert( '2025-01-01' === ( $rejected_entry['protocol_version_header'] ?? '' ), 'Trace did not capture the protocol header.' );
cmsa_ingestion_assert( '2025-01-01' === ( $rejected_entry['protocol_version_body'] ?? '' ), 'Trace did not capture the protocol body version.' );
cmsa_ingestion_assert( CUA_MCP_Server::PROTOCOL_VERSION === ( $rejected_entry['response_protocol_version'] ?? '' ), 'Trace did not capture the server protocol response version.' );
cmsa_ingestion_assert( false === ( $rejected_entry['response_session_present'] ?? true ), 'Rejected initialize request incorrectly appeared to establish a session.' );
cmsa_ingestion_assert( 400 === (int) ( $rejected_entry['http_status'] ?? 0 ), 'Trace did not capture the rejected HTTP status.' );
cmsa_ingestion_assert( '-32022' === (string) ( $rejected_entry['jsonrpc_error_code'] ?? '' ), 'Trace did not capture the MCP error code.' );
cmsa_ingestion_assert( 'ChatGPT StandardProbe/1.0' === ( $rejected_entry['user_agent'] ?? '' ), 'Trace did not capture the client user-agent.' );
cmsa_ingestion_assert( true === ( $rejected_entry['authorization_present'] ?? false ), 'Trace did not record that authorization was present.' );
$serialized_trace = wp_json_encode( $request_entries );
cmsa_ingestion_assert( false === strpos( (string) $serialized_trace, 'trace-secret-sentinel' ), 'Trace retained an authorization secret.' );
cmsa_ingestion_assert( ! array_key_exists( 'request_body', $rejected_entry ) && ! array_key_exists( 'response_body', $rejected_entry ), 'Trace retained a raw request or response body.' );
$gateway_discovery_call = cmsa_ingestion_modern_request(
	'/chattanooga-cms-admin/v1/mcp',
	'tools/call',
	array( 'name' => 'cmsa.discovery', 'arguments' => array( 'cursor' => 0, 'limit' => 100 ) ),
	707
);
cmsa_ingestion_assert( 200 === $gateway_discovery_call->get_status(), 'Gateway discovery tools/call failed.' );
$gateway_discovery = $gateway_discovery_call->get_data()['result']['structuredContent'] ?? null;
cmsa_ingestion_assert( is_array( $gateway_discovery ), 'Gateway discovery did not return structured catalog data.' );
$environment_bridge = '';
foreach ( $gateway_discovery['catalogGateway']['items'] ?? array() as $item ) {
	if ( is_array( $item ) && 'core/get-environment-info' === ( $item['target'] ?? '' ) ) {
		$environment_bridge = (string) ( $item['bridge'] ?? '' );
		break;
	}
}
cmsa_ingestion_assert( '' !== $environment_bridge, 'Read-only environment bridge was not found in gateway discovery.' );
$gateway_read_call = cmsa_ingestion_modern_request(
	'/chattanooga-cms-admin/v1/mcp',
	'tools/call',
	array( 'name' => 'cmsa.read-bridge', 'arguments' => array( 'bridge' => $environment_bridge, 'input' => array() ) ),
	708
);
cmsa_ingestion_assert( 200 === $gateway_read_call->get_status(), 'Gateway read-bridge tools/call failed.' );
cmsa_ingestion_assert( false === ( $gateway_read_call->get_data()['result']['isError'] ?? true ), 'Gateway read-bridge returned a tool error.' );

$request_stability = CUA_MCP_Diagnostics::stability_report( array( 'limit' => 20 ) );
cmsa_ingestion_assert( true === ( $request_stability['requestTraceReadable'] ?? false ), 'Stability report cannot read the request trace.' );
cmsa_ingestion_assert( (int) ( $request_stability['requestTraceCount'] ?? 0 ) > 0, 'Stability report omitted all request traces.' );
cmsa_ingestion_assert( is_array( $request_stability['recentRequests'] ?? null ), 'Stability report omitted the recent request list.' );
$observation_counts = $request_stability['observationCounts'] ?? array();
cmsa_ingestion_assert( (int) ( $observation_counts['mainToolsList'] ?? 0 ) >= 1, 'Main tools/list count omitted actual tools/list exchanges.' );
cmsa_ingestion_assert( false !== strpos( (string) ( $request_stability['observationCountsScope'] ?? '' ), 'do not prove a client requested or ingested tools/list' ), 'Stability scope did not distinguish tool calls from tools/list ingestion.' );
cmsa_ingestion_assert( (int) ( $request_stability['observationCountsWindow']['requestTraceEntries'] ?? 0 ) > 0, 'Stability report omitted the request-trace observation window.' );
cmsa_ingestion_assert( 1 === (int) ( $observation_counts['mainDiscoveryCalls'] ?? 0 ), 'Main discovery tool call was not counted from the request trace.' );
cmsa_ingestion_assert( 1 === (int) ( $observation_counts['mainReadBridgeCalls'] ?? 0 ), 'Main read-bridge tool call was not counted from the request trace.' );
cmsa_ingestion_assert( (int) ( $observation_counts['mainStabilityCheckCalls'] ?? 0 ) >= 1, 'Main stability-check tool call was not counted from the request trace.' );
cmsa_ingestion_assert( 0 === (int) ( $observation_counts['mainWriteBridgeCalls'] ?? -1 ), 'A write-bridge call was counted even though none was issued.' );
cmsa_ingestion_assert( (int) ( $observation_counts['mainSuccessfulToolCalls'] ?? 0 ) >= 3, 'Successful MCP tool calls were not distinguished in the request trace.' );

$canary_discover = cmsa_ingestion_modern_request( '/chattanooga-cms-admin/v1' . CUA_MCP_Diagnostics::CANARY_ROUTE, 'server/discover', array(), 703 );
cmsa_ingestion_assert( 200 === $canary_discover->get_status(), 'Canary server/discover failed.' );
$canary_discover_headers = array_change_key_case( $canary_discover->get_headers(), CASE_LOWER );
cmsa_ingestion_assert( false !== strpos( (string) ( $canary_discover_headers['cache-control'] ?? '' ), 'no-store' ), 'Canary server/discover HTTP response is cacheable.' );
cmsa_ingestion_assert(
	array( CUA_MCP_Server::PROTOCOL_VERSION ) === ( $canary_discover->get_data()['result']['supportedVersions'] ?? null ),
	'Canary discovery advertised the wrong protocol version.'
);

$canary_list = cmsa_ingestion_modern_request( '/chattanooga-cms-admin/v1' . CUA_MCP_Diagnostics::CANARY_ROUTE, 'tools/list', array(), 704 );
cmsa_ingestion_assert( 200 === $canary_list->get_status(), 'Canary tools/list failed.' );
$canary_list_headers = array_change_key_case( $canary_list->get_headers(), CASE_LOWER );
cmsa_ingestion_assert( false !== strpos( (string) ( $canary_list_headers['cache-control'] ?? '' ), 'no-store' ), 'Canary tools/list HTTP response is cacheable.' );
$canary_tools = $canary_list->get_data()['result']['tools'] ?? null;
cmsa_ingestion_assert( is_array( $canary_tools ) && 1 === count( $canary_tools ), 'Canary did not expose exactly one tool.' );
cmsa_ingestion_assert( CUA_MCP_Diagnostics::CANARY_TOOL === ( $canary_tools[0]['name'] ?? '' ), 'Canary exposed the wrong tool name.' );
cmsa_ingestion_assert( 'noauth' === ( $canary_tools[0]['securitySchemes'][0]['type'] ?? '' ), 'Canary tool is not noauth.' );
cmsa_ingestion_assert( true === ( $canary_tools[0]['annotations']['readOnlyHint'] ?? null ), 'Canary tool is not read-only.' );
cmsa_ingestion_assert( false === ( $canary_tools[0]['annotations']['destructiveHint'] ?? null ), 'Canary tool is incorrectly destructive.' );
cmsa_ingestion_assert( false === ( $canary_tools[0]['annotations']['openWorldHint'] ?? null ), 'Canary tool is incorrectly open-world.' );

$canary_call = cmsa_ingestion_modern_request(
	'/chattanooga-cms-admin/v1' . CUA_MCP_Diagnostics::CANARY_ROUTE,
	'tools/call',
	array(
		'name'      => CUA_MCP_Diagnostics::CANARY_TOOL,
		'arguments' => array(),
	),
	705
);
cmsa_ingestion_assert( 200 === $canary_call->get_status(), 'Canary tools/call failed.' );
$canary_call_data = $canary_call->get_data();
cmsa_ingestion_assert( false === ( $canary_call_data['result']['isError'] ?? true ), 'Canary tools/call returned an error.' );
cmsa_ingestion_assert( true === ( $canary_call_data['result']['structuredContent']['ok'] ?? false ), 'Canary tools/call did not report ok.' );
cmsa_ingestion_assert( 'ingestion-canary' === ( $canary_call_data['result']['structuredContent']['diagnostic'] ?? '' ), 'Canary tools/call returned the wrong diagnostic identity.' );

$diagnostics = CUA_Audit::read_mcp_diagnostics( 20 );
cmsa_ingestion_assert( ! is_wp_error( $diagnostics ), 'MCP diagnostic trace could not be read.' );
$entries = $diagnostics['entries'] ?? array();
cmsa_ingestion_assert( is_array( $entries ) && ! empty( $entries ), 'MCP diagnostic trace contains no entries.' );

$main_list_entry = null;
$canary_list_entry = null;
foreach ( $entries as $entry ) {
	if ( ! is_array( $entry ) ) {
		continue;
	}
	if ( 'main' === ( $entry['mcp_surface'] ?? '' ) && 'tools/list' === ( $entry['mcp_method'] ?? '' ) && null === $main_list_entry ) {
		$main_list_entry = $entry;
	}
	if ( 'canary' === ( $entry['mcp_surface'] ?? '' ) && 'tools/list' === ( $entry['mcp_method'] ?? '' ) && null === $canary_list_entry ) {
		$canary_list_entry = $entry;
	}
}

cmsa_ingestion_assert( is_array( $main_list_entry ), 'Main tools/list diagnostic entry is missing.' );
cmsa_ingestion_assert( is_array( $canary_list_entry ), 'Canary tools/list diagnostic entry is missing.' );
cmsa_ingestion_assert( count( $main_tools ) === (int) ( $main_list_entry['tool_count'] ?? -1 ), 'Main tools/list diagnostic tool count is incorrect.' );
cmsa_ingestion_assert( 0 === (int) ( $main_list_entry['descriptor_fail'] ?? -1 ), 'Main tools/list diagnostic reports descriptor failures.' );
cmsa_ingestion_assert( 1 === (int) ( $canary_list_entry['tool_count'] ?? -1 ), 'Canary diagnostic tool count is incorrect.' );

foreach ( array( $main_list_entry, $canary_list_entry ) as $entry ) {
	foreach ( array( 'request_sha256', 'response_sha256', 'correlation_sha256' ) as $hash_key ) {
		cmsa_ingestion_assert( preg_match( '/^[a-f0-9]{64}$/', (string) ( $entry[ $hash_key ] ?? '' ) ), 'Diagnostic hash is malformed: ' . $hash_key );
	}
	cmsa_ingestion_assert( (int) ( $entry['request_bytes'] ?? 0 ) > 0, 'Diagnostic request byte count is missing.' );
	cmsa_ingestion_assert( (int) ( $entry['response_bytes'] ?? 0 ) > 0, 'Diagnostic response byte count is missing.' );
	foreach ( array( 'authorization', 'bearer', 'token', 'code', 'pkce', 'state', 'request_body', 'response_body', 'arguments', 'credential', 'password' ) as $forbidden_key ) {
		cmsa_ingestion_assert( ! array_key_exists( $forbidden_key, $entry ), 'Diagnostic trace retained forbidden raw field: ' . $forbidden_key );
	}
}

$report = array(
	'pluginVersion'   => CUA_VERSION,
	'generatedAt'     => gmdate( 'c' ),
	'protocolVersion' => CUA_MCP_Server::PROTOCOL_VERSION,
	'catalog'         => $catalog,
	'tools'           => CUA_MCP_Server::diagnostic_tools(),
	'canaryTool'      => CUA_MCP_Diagnostics::canary_tool_descriptor(),
	'recentDiagnostics' => $entries,
);

$path = getenv( 'CMSA_DIAGNOSTIC_REPORT' );
if ( is_string( $path ) && '' !== trim( $path ) ) {
	$encoded = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	cmsa_ingestion_assert( is_string( $encoded ) && false !== file_put_contents( $path, $encoded . "\n" ), 'Diagnostic report could not be written.' );
}

echo 'cmsa-mcp-ingestion-diagnostics: PASS tools=' . (int) $catalog['toolCount']
	. ' descriptor_pass=' . (int) $catalog['descriptorSummary']['pass']
	. ' descriptor_fail=' . (int) $catalog['descriptorSummary']['fail']
	. ' canary=verified'
	. ' trace=secret-free'
	. "\n";
exit( 0 );

