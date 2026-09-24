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

wp_set_current_user( 1 );

cmsa_ingestion_assert( defined( 'CUA_VERSION' ) && '1.2.36' === CUA_VERSION, 'Chattanooga CMS Admin 1.2.36 did not load.' );
cmsa_ingestion_assert( class_exists( 'CUA_MCP_Diagnostics' ), 'MCP diagnostics class did not load.' );
cmsa_ingestion_assert( class_exists( 'CUA_MCP_Server' ), 'MCP server class did not load.' );
cmsa_ingestion_assert( class_exists( 'CUA_Audit' ), 'Audit class did not load.' );

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
cmsa_ingestion_assert( false !== strpos( (string) ( $stability['scope'] ?? '' ), 'cannot prove that ChatGPT' ), 'Stability tool did not state its client-registry limitation.' );

$canary_discover = cmsa_ingestion_modern_request( '/chattanooga-cms-admin/v1' . CUA_MCP_Diagnostics::CANARY_ROUTE, 'server/discover', array(), 703 );
cmsa_ingestion_assert( 200 === $canary_discover->get_status(), 'Canary server/discover failed.' );
cmsa_ingestion_assert(
	array( CUA_MCP_Server::PROTOCOL_VERSION ) === ( $canary_discover->get_data()['result']['supportedVersions'] ?? null ),
	'Canary discovery advertised the wrong protocol version.'
);

$canary_list = cmsa_ingestion_modern_request( '/chattanooga-cms-admin/v1' . CUA_MCP_Diagnostics::CANARY_ROUTE, 'tools/list', array(), 704 );
cmsa_ingestion_assert( 200 === $canary_list->get_status(), 'Canary tools/list failed.' );
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
