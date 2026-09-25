<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_native_mcp_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_native_mcp_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_native_mcp_fail( $message );
	}
}

function cmsa_native_mcp_post( $method, array $params = array(), array $headers = array(), $id = 1 ) {
	global $cmsa_native_mcp_session_id;
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	if ( ! empty( $cmsa_native_mcp_session_id ) ) {
		$request->set_header( 'Mcp-Session-Id', $cmsa_native_mcp_session_id );
	}
	foreach ( $headers as $name => $value ) {
		$request->set_header( $name, $value );
	}
	$request->set_body(
		wp_json_encode(
			array_filter(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => $method,
				'params'  => $params,
			),
			static function ( $value, $key ) {
				return 'id' !== $key || null !== $value;
			},
			ARRAY_FILTER_USE_BOTH
			)
		)
	);
	return rest_do_request( $request );
}

function cmsa_native_mcp_modern( $method, array $params = array(), $id = 1, array $extra_headers = array() ) {
	global $cmsa_native_mcp_session_id;
	$params['_meta'] = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
	$params['_meta']['io.modelcontextprotocol/protocolVersion'] = '2026-07-28';
	$params['_meta']['io.modelcontextprotocol/clientCapabilities'] = array();
	$params['_meta']['io.modelcontextprotocol/clientInfo'] = array(
		'name'    => 'cmsa-native-mcp-probe',
		'version' => '1.0.0',
	);

	$headers = array_merge(
		array(
			'MCP-Protocol-Version' => '2026-07-28',
			'Mcp-Method'           => $method,
		),
		$extra_headers
	);

	if ( ( 'tools/call' === $method || 'prompts/get' === $method ) && isset( $params['name'] ) ) {
		$headers['Mcp-Name'] = (string) $params['name'];
	} elseif ( 'resources/read' === $method && isset( $params['uri'] ) ) {
		$headers['Mcp-Name'] = (string) $params['uri'];
	}
	if ( '' !== (string) $cmsa_native_mcp_session_id ) {
		$headers['Mcp-Session-Id'] = $cmsa_native_mcp_session_id;
	}

	return cmsa_native_mcp_post( $method, $params, $headers, $id );
}

function cmsa_native_mcp_tool( array $tools, $name ) {
	foreach ( $tools as $tool ) {
		if ( is_array( $tool ) && $name === ( $tool['name'] ?? '' ) ) {
			return $tool;
		}
	}
	return null;
}

function cmsa_native_mcp_all_tools() {
	global $cmsa_native_mcp_session_id;
	$all_tools = array();
	$cursor = '';
	for ( $page = 0; $page < 20; $page++ ) {
		$params = '' === $cursor ? array() : array( 'cursor' => $cursor );
		$response = cmsa_native_mcp_modern( 'tools/list', $params, 110 + $page );
		cmsa_native_mcp_assert( 200 === $response->get_status(), 'Paginated tools/list did not return HTTP 200: ' . $response->get_status() . ' ' . wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$page_tools = $data['result']['tools'] ?? null;
		cmsa_native_mcp_assert( is_array( $page_tools ), 'Paginated tools/list returned no tools array.' );
		$all_tools = array_merge( $all_tools, $page_tools );
		$cursor = trim( (string) ( $data['result']['nextCursor'] ?? '' ) );
		if ( '' === $cursor ) {
			return $all_tools;
		}
	}
	cmsa_native_mcp_fail( 'Paginated tools/list exceeded the cursor safety limit.' );
}

wp_set_current_user( 1 );
cmsa_native_mcp_assert( defined( 'CUA_VERSION' ) && '1.2.45' === CUA_VERSION, 'Chattanooga CMS Admin 1.2.45 did not load.' );
cmsa_native_mcp_assert( class_exists( 'CUA_MCP_Server' ), 'Chattanooga MCP server class did not load.' );

$server = rest_get_server();
$routes = $server->get_routes();
cmsa_native_mcp_assert( isset( $routes['/chattanooga-cms-admin/v1/mcp'] ), 'Chattanooga MCP REST route is not registered.' );

// This server uses stateless JSON responses rather than server-to-client SSE.
$get_request = new WP_REST_Request( 'GET', '/chattanooga-cms-admin/v1/mcp' );
$get_response = rest_do_request( $get_request );
cmsa_native_mcp_assert( 405 === $get_response->get_status(), 'MCP GET fallback did not return HTTP 405.' );
$get_headers = array_change_key_case( $get_response->get_headers(), CASE_LOWER );
cmsa_native_mcp_assert( 'POST, DELETE' === ( $get_headers['allow'] ?? '' ), 'MCP GET fallback did not advertise the allowed MCP methods.' );

$bad_content_type = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$bad_content_type->set_header( 'content-type', 'text/plain' );
$bad_content_type->set_body( '{"jsonrpc":"2.0","id":108,"method":"server/discover","params":{}}' );
$bad_content_type_response = rest_do_request( $bad_content_type );
cmsa_native_mcp_assert( 415 === $bad_content_type_response->get_status(), 'Invalid MCP Content-Type was not rejected with HTTP 415.' );

$bad_accept = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$bad_accept->set_header( 'content-type', 'application/json' );
$bad_accept->set_header( 'accept', 'text/html' );
$bad_accept->set_body( '{"jsonrpc":"2.0","id":109,"method":"server/discover","params":{}}' );
$bad_accept_response = rest_do_request( $bad_accept );
cmsa_native_mcp_assert( 406 === $bad_accept_response->get_status(), 'Invalid MCP Accept was not rejected with HTTP 406.' );

$mismatched_versions = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$mismatched_versions->set_header( 'content-type', 'application/json' );
$mismatched_versions->set_header( 'MCP-Protocol-Version', '2026-07-28' );
$mismatched_versions->set_header( 'Mcp-Method', 'server/discover' );
$mismatched_versions->set_body( wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 110, 'method' => 'server/discover', 'params' => array( '_meta' => array( 'io.modelcontextprotocol/protocolVersion' => '2025-11-25' ) ) ) ) );
$mismatched_versions_response = rest_do_request( $mismatched_versions );
cmsa_native_mcp_assert( 400 === $mismatched_versions_response->get_status() && -32022 === ( $mismatched_versions_response->get_data()['error']['code'] ?? null ), 'Conflicting MCP protocol version declarations were accepted.' );

// Discovery: the single supported protocol, capabilities, server identity, and private cache policy.
$discover = cmsa_native_mcp_modern( 'server/discover', array(), 101 );
cmsa_native_mcp_assert( 200 === $discover->get_status(), 'server/discover did not return HTTP 200.' );
$discover_data = $discover->get_data();
$discover_headers = array_change_key_case( $discover->get_headers(), CASE_LOWER );
cmsa_native_mcp_assert( '2.0' === ( $discover_data['jsonrpc'] ?? '' ), 'Discovery did not return JSON-RPC 2.0.' );
cmsa_native_mcp_assert( 101 === ( $discover_data['id'] ?? null ), 'Discovery returned the wrong request id.' );
cmsa_native_mcp_assert( 'complete' === ( $discover_data['result']['resultType'] ?? '' ), 'Discovery omitted complete resultType.' );
cmsa_native_mcp_assert(
	array( '2026-07-28', '2025-11-25', '2025-06-18' ) === ( $discover_data['result']['supportedVersions'] ?? null ),
	'Discovery advertised an unexpected MCP protocol version.'
);
cmsa_native_mcp_assert(
	false === ( $discover_data['result']['capabilities']['tools']['listChanged'] ?? null ),
	'Discovery returned the wrong tools capability.'
);
cmsa_native_mcp_assert( 0 === ( $discover_data['result']['ttlMs'] ?? null ), 'Discovery cache TTL is incorrect.' );
cmsa_native_mcp_assert( 'private' === ( $discover_data['result']['cacheScope'] ?? '' ), 'Discovery cache scope is not private.' );
cmsa_native_mcp_assert( false !== strpos( (string) ( $discover_headers['cache-control'] ?? '' ), 'no-store' ), 'Discovery HTTP response is cacheable.' );
cmsa_native_mcp_assert( false !== strpos( (string) ( $discover_headers['cache-control'] ?? '' ), 'max-age=0' ), 'Discovery HTTP response lacks max-age=0.' );
cmsa_native_mcp_assert( 'no-cache' === strtolower( trim( (string) ( $discover_headers['pragma'] ?? '' ) ) ), 'Discovery HTTP response lacks Pragma: no-cache.' );
cmsa_native_mcp_assert( ! array_key_exists( 'discovery', $discover_data['result'] ?? array() ), 'server/discover embedded the dynamic operation catalog.' );
cmsa_native_mcp_assert( strlen( (string) wp_json_encode( $discover_data ) ) < 8192, 'server/discover bootstrap exceeded the 8 KiB ingestion budget.' );
cmsa_native_mcp_assert(
	'chattanooga-cms-admin' === ( $discover_data['result']['_meta']['io.modelcontextprotocol/serverInfo']['name'] ?? '' ),
	'Discovery did not identify the Chattanooga CMS Admin server.'
);
cmsa_native_mcp_assert( false === ( $discover_data['result']['capabilities']['resources']['subscribe'] ?? true ), 'Discovery returned the wrong resources capability.' );

$stateless_list = cmsa_native_mcp_modern( 'tools/list', array(), 108, array( 'Mcp-Session-Id' => 'ignored-modern-session' ) );
cmsa_native_mcp_assert( 200 === $stateless_list->get_status(), 'Modern tools/list did not run without initialization.' );
$stateless_headers = array_change_key_case( $stateless_list->get_headers(), CASE_LOWER );
cmsa_native_mcp_assert( false !== strpos( (string) ( $stateless_headers['cache-control'] ?? '' ), 'no-store' ), 'Modern tools/list HTTP response is cacheable.' );
cmsa_native_mcp_assert( false !== strpos( (string) ( $stateless_headers['cache-control'] ?? '' ), 'max-age=0' ), 'Modern tools/list HTTP response lacks max-age=0.' );
cmsa_native_mcp_assert( ! isset( $stateless_headers['mcp-session-id'] ), 'Modern tools/list echoed a session header.' );

$resources = cmsa_native_mcp_modern( 'resources/list', array(), 109 );
cmsa_native_mcp_assert( 200 === $resources->get_status(), 'resources/list did not return HTTP 200: ' . $resources->get_status() . ' ' . wp_json_encode( $resources->get_data() ) );
$resources_data = $resources->get_data();
cmsa_native_mcp_assert( is_array( $resources_data['result']['resources'] ?? null ), 'resources/list did not return resources.' );
$catalog_resource = null;
foreach ( $resources_data['result']['resources'] as $resource ) {
	if ( is_array( $resource ) && CUA_MCP_Server::RESOURCE_CATALOG_URI === ( $resource['uri'] ?? '' ) ) {
		$catalog_resource = $resource;
		break;
	}
}
cmsa_native_mcp_assert( is_array( $catalog_resource ), 'Site-operation catalog resource was not listed.' );

$resource_read = cmsa_native_mcp_modern( 'resources/read', array( 'uri' => CUA_MCP_Server::RESOURCE_CATALOG_URI ), 110 );
cmsa_native_mcp_assert( 200 === $resource_read->get_status(), 'resources/read did not return HTTP 200: ' . $resource_read->get_status() . ' ' . wp_json_encode( $resource_read->get_data() ) );
$resource_read_data = $resource_read->get_data();
cmsa_native_mcp_assert( 'application/json' === ( $resource_read_data['result']['contents'][0]['mimeType'] ?? '' ), 'Site-operation resource returned the wrong MIME type.' );
cmsa_native_mcp_assert( false !== strpos( (string) ( $resource_read_data['result']['contents'][0]['text'] ?? '' ), 'items' ), 'Site-operation resource did not return catalog content.' );

$prompts = cmsa_native_mcp_modern( 'prompts/list', array(), 111 );
cmsa_native_mcp_assert( 200 === $prompts->get_status(), 'prompts/list did not return HTTP 200.' );
$prompts_data = $prompts->get_data();
cmsa_native_mcp_assert( CUA_MCP_Server::PROMPT_SITE_OPERATION === ( $prompts_data['result']['prompts'][0]['name'] ?? '' ), 'Site-operation prompt was not listed.' );

$prompt_get = cmsa_native_mcp_modern(
	'prompts/get',
	array(
		'name'      => CUA_MCP_Server::PROMPT_SITE_OPERATION,
		'arguments' => array( 'request' => 'inspect the current public site-operation catalog' ),
	),
	112,
	array( 'Mcp-Session-Id' => $cmsa_native_mcp_session_id )
);
cmsa_native_mcp_assert( 200 === $prompt_get->get_status(), 'prompts/get did not return HTTP 200.' );
$prompt_get_data = $prompt_get->get_data();
cmsa_native_mcp_assert( 'user' === ( $prompt_get_data['result']['messages'][0]['role'] ?? '' ), 'Site-operation prompt returned the wrong message role.' );
cmsa_native_mcp_assert( false !== strpos( (string) ( $prompt_get_data['result']['messages'][0]['content']['text'] ?? '' ), 'site-operation catalog' ), 'Site-operation prompt returned incomplete guidance.' );

// tools/list: deterministic names, bounded public ability surface, and correct read/write annotations.
$list = cmsa_native_mcp_modern( 'tools/list', array(), 102 );
cmsa_native_mcp_assert( 200 === $list->get_status(), 'tools/list did not return HTTP 200.' );
$list_data = $list->get_data();
$tools = cmsa_native_mcp_all_tools();
cmsa_native_mcp_assert( is_array( $tools ) && ! empty( $tools ), 'tools/list returned no tools.' );

$names = array();
foreach ( $tools as $tool ) {
	cmsa_native_mcp_assert( is_array( $tool ) && isset( $tool['name'] ), 'A listed MCP tool is malformed.' );
	cmsa_native_mcp_assert( '' !== trim( (string) ( $tool['title'] ?? '' ) ), 'A listed MCP tool is missing its title.' );
	cmsa_native_mcp_assert( '' !== trim( (string) ( $tool['description'] ?? '' ) ), 'A listed MCP tool is missing its description.' );
	cmsa_native_mcp_assert( is_array( $tool['inputSchema'] ?? null ) && 'object' === ( $tool['inputSchema']['type'] ?? null ), 'A listed MCP tool is missing an object inputSchema: ' . (string) ( $tool['name'] ?? '' ) . ' ' . wp_json_encode( $tool['inputSchema'] ?? null ) );
	cmsa_native_mcp_assert( is_array( $tool['outputSchema'] ?? null ), 'A listed MCP tool is missing outputSchema: ' . (string) ( $tool['name'] ?? '' ) );
	$annotations = $tool['annotations'] ?? null;
	cmsa_native_mcp_assert( is_array( $annotations ), 'A listed MCP tool is missing annotations.' );
	foreach ( array( 'readOnlyHint', 'destructiveHint', 'openWorldHint' ) as $required_annotation ) {
		cmsa_native_mcp_assert( array_key_exists( $required_annotation, $annotations ) && is_bool( $annotations[ $required_annotation ] ), 'A listed MCP tool is missing required ChatGPT annotation: ' . $required_annotation );
	}
	cmsa_native_mcp_assert( is_array( $tool['securitySchemes'] ?? null ) && ! empty( $tool['securitySchemes'] ), 'A listed MCP tool is missing securitySchemes.' );
	cmsa_native_mcp_assert( ( $tool['securitySchemes'] ?? null ) === ( $tool['_meta']['securitySchemes'] ?? null ), 'A listed MCP tool does not mirror securitySchemes into _meta.' );
	$names[] = (string) $tool['name'];
}
cmsa_native_mcp_assert( count( $names ) === 4, 'MCP tools/list returned an unexpected stable tool count.' );

$expected_names = array(
	'cmsa.discovery',
	'cmsa.stability-check',
	'cmsa.read-bridge',
	'cmsa.write-bridge',
);
cmsa_native_mcp_assert( $expected_names === $names, 'MCP tools/list does not match the bounded deterministic core tool set: ' . wp_json_encode( $names ) );
foreach ( $names as $name ) {
	cmsa_native_mcp_assert( ! preg_match( '/^cmsa\\.(?:bridge|rest)-[a-f0-9]{24}$/', $name ), 'Generated universal facade leaked into MCP tools/list: ' . $name );
}
$discovery_tool = cmsa_native_mcp_tool( $tools, 'cmsa.discovery' );
$stability_tool = cmsa_native_mcp_tool( $tools, 'cmsa.stability-check' );
$read_bridge_tool = cmsa_native_mcp_tool( $tools, 'cmsa.read-bridge' );
$write_bridge_tool = cmsa_native_mcp_tool( $tools, 'cmsa.write-bridge' );
foreach ( array( $discovery_tool, $stability_tool, $read_bridge_tool, $write_bridge_tool ) as $gateway_tool ) {
	cmsa_native_mcp_assert( is_array( $gateway_tool ), 'A stable gateway tool is missing from bounded tools/list.' );
	cmsa_native_mcp_assert( ( $gateway_tool['securitySchemes'] ?? null ) === ( $gateway_tool['_meta']['securitySchemes'] ?? null ), 'Tool security schemes are not mirrored into _meta.' );
}
cmsa_native_mcp_assert( true === ( $discovery_tool['annotations']['readOnlyHint'] ?? null ), 'discovery is not annotated read-only.' );
cmsa_native_mcp_assert( false === ( $discovery_tool['annotations']['openWorldHint'] ?? null ), 'discovery is incorrectly annotated open-world.' );
cmsa_native_mcp_assert( true === ( $stability_tool['annotations']['readOnlyHint'] ?? null ), 'stability-check is not annotated read-only.' );
cmsa_native_mcp_assert( false === ( $stability_tool['annotations']['destructiveHint'] ?? null ), 'stability-check is incorrectly destructive.' );
cmsa_native_mcp_assert( false === ( $stability_tool['annotations']['openWorldHint'] ?? null ), 'stability-check is incorrectly annotated open-world.' );
$catalog_security = $discovery_tool['securitySchemes'][0] ?? null;
cmsa_native_mcp_assert( is_array( $catalog_security ) && 'oauth2' === ( $catalog_security['type'] ?? '' ), 'MCP tools do not advertise OAuth 2.0 to ChatGPT.' );
cmsa_native_mcp_assert( array( CUA_OAuth_Server::SCOPE ) === ( $catalog_security['scopes'] ?? null ), 'MCP OAuth tool scope is not the administrator scope.' );
cmsa_native_mcp_assert( 0 === ( $resources_data['result']['ttlMs'] ?? null ) && 'private' === ( $resources_data['result']['cacheScope'] ?? '' ), 'resources/list cache hints are incomplete.' );
cmsa_native_mcp_assert( 0 === ( $resource_read_data['result']['ttlMs'] ?? null ) && 'private' === ( $resource_read_data['result']['cacheScope'] ?? '' ), 'resources/read cache hints are incomplete.' );
cmsa_native_mcp_assert( 0 === ( $prompts_data['result']['ttlMs'] ?? null ) && 'private' === ( $prompts_data['result']['cacheScope'] ?? '' ), 'prompts/list cache hints are incomplete.' );

// Execute one real site-operation catalog call through MCP.
$catalog = cmsa_native_mcp_modern(
	'tools/call',
	array(
		'name'      => 'cmsa.discovery',
		'arguments' => array(),
	),
	103,
	array( 'Mcp-Session-Id' => $cmsa_native_mcp_session_id )
);
cmsa_native_mcp_assert( 200 === $catalog->get_status(), 'MCP catalog tool call did not return HTTP 200.' );
$catalog_data = $catalog->get_data();
cmsa_native_mcp_assert( 'complete' === ( $catalog_data['result']['resultType'] ?? '' ), 'MCP tool call omitted complete resultType.' );
cmsa_native_mcp_assert( false === ( $catalog_data['result']['isError'] ?? true ), 'MCP catalog returned a tool error.' );
cmsa_native_mcp_assert( is_array( $catalog_data['result']['structuredContent']['catalogGateway']['items'] ?? null ), 'MCP catalog did not return bridgeable site-operation items.' );

// The MCP boundary is explicitly audited without retaining request bodies,
// tool arguments, responses, credentials, or raw session identifiers.
$audit_ability = wp_get_ability( 'chattanooga-cms-admin/get-audit-log' );
cmsa_native_mcp_assert( $audit_ability instanceof WP_Ability, 'MCP audit ability is unavailable for verification.' );
$audit_result = $audit_ability->execute( array( 'limit' => 100 ) );
cmsa_native_mcp_assert( is_array( $audit_result['entries'] ?? null ), 'MCP audit log did not return entries.' );
$mcp_audit = null;
foreach ( $audit_result['entries'] as $entry ) {
	if ( is_array( $entry ) && 'mcp' === ( $entry['surface'] ?? '' ) && 'tools/call' === ( $entry['method'] ?? '' ) && 'cmsa.discovery' === ( $entry['tool'] ?? '' ) && 'success' === ( $entry['outcome'] ?? '' ) ) {
		$mcp_audit = $entry;
		break;
	}
}
cmsa_native_mcp_assert( is_array( $mcp_audit ), 'Successful MCP tools/call was not recorded at the MCP boundary.' );
cmsa_native_mcp_assert( 200 === (int) ( $mcp_audit['http_status'] ?? 0 ), 'MCP audit entry did not record the completed HTTP status.' );
cmsa_native_mcp_assert( ! array_key_exists( 'session_sha256', $mcp_audit ), 'Modern MCP audit retained a session identifier.' );
foreach ( array( 'authorization', 'token', 'arguments', 'input', 'output', 'response', 'session_id' ) as $forbidden_key ) {
	cmsa_native_mcp_assert( ! array_key_exists( $forbidden_key, $mcp_audit ), 'MCP audit entry retained a sensitive raw field: ' . $forbidden_key );
}

// Tool errors stay inside a successful tools/call result instead of becoming protocol transport failures.
$missing = cmsa_native_mcp_modern(
	'tools/call',
	array(
		'name'      => 'cmsa.not-a-real-tool',
		'arguments' => array(),
	),
	104,
	array( 'Mcp-Session-Id' => $cmsa_native_mcp_session_id )
);
cmsa_native_mcp_assert( 200 === $missing->get_status(), 'Missing tool did not return an MCP tool result.' );
$missing_data = $missing->get_data();
cmsa_native_mcp_assert( true === ( $missing_data['result']['isError'] ?? false ), 'Missing tool was not marked as an MCP tool error.' );

// Header mismatch is rejected as a protocol error before dispatch.
$mismatch = cmsa_native_mcp_modern(
	'server/discover',
	array(),
	105,
	array( 'Mcp-Method' => 'tools/list' )
);
cmsa_native_mcp_assert( 400 === $mismatch->get_status(), 'MCP header/body mismatch was not rejected with HTTP 400.' );
$mismatch_data = $mismatch->get_data();
$mismatch_headers = array_change_key_case( $mismatch->get_headers(), CASE_LOWER );
cmsa_native_mcp_assert( false !== strpos( (string) ( $mismatch_headers['cache-control'] ?? '' ), 'no-store' ), 'MCP protocol error response is cacheable.' );
cmsa_native_mcp_assert( -32020 === ( $mismatch_data['error']['code'] ?? null ), 'MCP header/body mismatch did not return -32020.' );

// Origin validation still blocks protected browser-origin requests from unrelated sites.
$origin_request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$origin_request->set_header( 'content-type', 'application/json' );
$origin_request->set_header( 'origin', 'https://attacker.invalid' );
$origin_request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
$origin_request->set_header( 'Mcp-Method', 'resources/list' );
$origin_request->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 106,
			'method'  => 'resources/list',
			'params'  => array(
				'_meta' => array(
					'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
					'io.modelcontextprotocol/clientCapabilities' => array(),
				),
			),
		)
	)
);
$origin = rest_do_request( $origin_request );
cmsa_native_mcp_assert( 403 === $origin->get_status(), 'Untrusted Origin was not rejected for a protected MCP method.' );

// Modern discovery is intentionally public so ChatGPT can learn tool OAuth policy
// before it has an access token. Tool execution remains protected.
wp_set_current_user( 0 );

$anonymous_discover = cmsa_native_mcp_modern( 'server/discover', array(), 107 );
cmsa_native_mcp_assert( 200 === $anonymous_discover->get_status(), 'Anonymous modern server/discover was not available for OAuth discovery.' );

$anonymous_list = cmsa_native_mcp_modern( 'tools/list', array(), 108 );
cmsa_native_mcp_assert( 200 === $anonymous_list->get_status(), 'Anonymous modern tools/list was not available for OAuth tool discovery.' );
$anonymous_list_data = $anonymous_list->get_data();
$anonymous_tools = $anonymous_list_data['result']['tools'] ?? array();
cmsa_native_mcp_assert( is_array( $anonymous_tools ) && ! empty( $anonymous_tools ), 'Anonymous tools/list returned no tool metadata.' );
$anonymous_catalog_tool = cmsa_native_mcp_tool( $anonymous_tools, 'cmsa.discovery' );
cmsa_native_mcp_assert( is_array( $anonymous_catalog_tool ), 'Anonymous tools/list did not expose the catalog tool descriptor.' );
cmsa_native_mcp_assert( 'oauth2' === ( $anonymous_catalog_tool['securitySchemes'][0]['type'] ?? '' ), 'Anonymous tools/list did not advertise OAuth 2.0.' );
cmsa_native_mcp_assert( array( CUA_OAuth_Server::SCOPE ) === ( $anonymous_catalog_tool['securitySchemes'][0]['scopes'] ?? null ), 'Anonymous tools/list advertised the wrong OAuth scope.' );

$anonymous_call = cmsa_native_mcp_modern(
	'tools/call',
	array(
		'name'      => 'cmsa.discovery',
		'arguments' => array(),
	),
	109
);
cmsa_native_mcp_assert( 200 === $anonymous_call->get_status(), 'Anonymous protected tools/call did not return a CallToolResult OAuth challenge.' );
$anonymous_call_data = $anonymous_call->get_data();
cmsa_native_mcp_assert( true === ( $anonymous_call_data['result']['isError'] ?? false ), 'Anonymous protected tools/call was not marked as an MCP tool error.' );
cmsa_native_mcp_assert( empty( $anonymous_call_data['result']['structuredContent'] ?? null ), 'Anonymous protected tools/call executed the underlying tool.' );
$anonymous_challenges = $anonymous_call_data['result']['_meta']['mcp/www_authenticate'] ?? array();
cmsa_native_mcp_assert( is_array( $anonymous_challenges ) && 1 === count( $anonymous_challenges ), 'Anonymous tools/call omitted mcp/www_authenticate.' );
$anonymous_challenge = (string) $anonymous_challenges[0];
cmsa_native_mcp_assert( false !== strpos( $anonymous_challenge, 'resource_metadata=' ), 'Tool OAuth challenge omitted resource metadata.' );
cmsa_native_mcp_assert( false !== strpos( $anonymous_challenge, 'error=' ), 'Tool OAuth challenge omitted OAuth error.' );
cmsa_native_mcp_assert( false !== strpos( $anonymous_challenge, 'error_description=' ), 'Tool OAuth challenge omitted OAuth error_description.' );

$anonymous_protected = cmsa_native_mcp_modern( 'resources/list', array(), 110 );
cmsa_native_mcp_assert( 401 === $anonymous_protected->get_status(), 'Anonymous non-discovery MCP access was not rejected with HTTP 401.' );

wp_set_current_user( 1 );

$legacy_initialize = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$legacy_initialize->set_header( 'content-type', 'application/json' );
$legacy_initialize->set_header( 'MCP-Protocol-Version', '2025-11-25' );
$legacy_initialize->set_header( 'Mcp-Method', 'initialize' );
$legacy_initialize->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 120,
			'method'  => 'initialize',
			'params'  => array(
				'protocolVersion' => '2025-11-25',
				'capabilities'    => array(),
				'clientInfo'      => array( 'name' => 'cmsa-native-mcp-legacy-probe', 'version' => '1.0.0' ),
			),
		)
	)
);
$legacy_initialize_response = rest_do_request( $legacy_initialize );
$legacy_initialize_headers = array_change_key_case( $legacy_initialize_response->get_headers(), CASE_LOWER );
$legacy_session_id = trim( (string) ( $legacy_initialize_headers['mcp-session-id'] ?? '' ) );
cmsa_native_mcp_assert( 200 === $legacy_initialize_response->get_status() && '' !== $legacy_session_id, 'Legacy MCP initialization/session compatibility failed.' );

$close_session = new WP_REST_Request( 'DELETE', '/chattanooga-cms-admin/v1/mcp' );
$close_session->set_header( 'Mcp-Session-Id', $legacy_session_id );
$close_session_response = rest_do_request( $close_session );
cmsa_native_mcp_assert( 204 === $close_session_response->get_status(), 'Legacy MCP DELETE did not close the session.' );

echo "cmsa-native-mcp: PASS version=1.2.45 protocol=2026-07-28 route=verified administrator_surface=bounded-public origin_guard=verified tools_list=bounded-deterministic oauth_scheme=verified read_call=verified header_validation=verified\n";
exit( 0 );

