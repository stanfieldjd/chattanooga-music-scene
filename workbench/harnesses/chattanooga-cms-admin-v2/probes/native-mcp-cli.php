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
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	foreach ( $headers as $name => $value ) {
		$request->set_header( $name, $value );
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

function cmsa_native_mcp_modern( $method, array $params = array(), $id = 1, array $extra_headers = array() ) {
	$params['_meta'] = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
	$params['_meta']['io.modelcontextprotocol/protocolVersion'] = '2026-07-28';
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

	if ( 'tools/call' === $method && isset( $params['name'] ) ) {
		$headers['Mcp-Name'] = (string) $params['name'];
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
	$all_tools = array();
	$cursor = '';
	for ( $page = 0; $page < 20; $page++ ) {
		$params = '' === $cursor ? array() : array( 'cursor' => $cursor );
		$response = cmsa_native_mcp_modern( 'tools/list', $params, 110 + $page );
		cmsa_native_mcp_assert( 200 === $response->get_status(), 'Paginated tools/list did not return HTTP 200.' );
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
cmsa_native_mcp_assert( defined( 'CUA_VERSION' ) && '1.1.0' === CUA_VERSION, 'Chattanooga CMS Admin 1.1.0 did not load.' );
cmsa_native_mcp_assert( class_exists( 'CUA_MCP_Server' ), 'Native MCP server class did not load.' );

$server = rest_get_server();
$routes = $server->get_routes();
cmsa_native_mcp_assert( isset( $routes['/chattanooga-cms-admin/v1/mcp'] ), 'Native MCP REST route is not registered.' );

// This server uses stateless JSON responses rather than server-to-client SSE.
$get_request = new WP_REST_Request( 'GET', '/chattanooga-cms-admin/v1/mcp' );
$get_response = rest_do_request( $get_request );
cmsa_native_mcp_assert( 405 === $get_response->get_status(), 'MCP GET fallback did not return HTTP 405.' );
$get_headers = array_change_key_case( $get_response->get_headers(), CASE_LOWER );
cmsa_native_mcp_assert( 'POST' === ( $get_headers['allow'] ?? '' ), 'MCP GET fallback did not advertise Allow: POST.' );

// Discovery: the single supported protocol, capabilities, server identity, and private cache policy.
$discover = cmsa_native_mcp_modern( 'server/discover', array(), 101 );
cmsa_native_mcp_assert( 200 === $discover->get_status(), 'server/discover did not return HTTP 200.' );
$discover_data = $discover->get_data();
cmsa_native_mcp_assert( '2.0' === ( $discover_data['jsonrpc'] ?? '' ), 'Discovery did not return JSON-RPC 2.0.' );
cmsa_native_mcp_assert( 101 === ( $discover_data['id'] ?? null ), 'Discovery returned the wrong request id.' );
cmsa_native_mcp_assert( 'complete' === ( $discover_data['result']['resultType'] ?? '' ), 'Discovery omitted complete resultType.' );
cmsa_native_mcp_assert(
	array( '2026-07-28' ) === ( $discover_data['result']['supportedVersions'] ?? null ),
	'Discovery advertised an unexpected MCP protocol version.'
);
cmsa_native_mcp_assert(
	false === ( $discover_data['result']['capabilities']['tools']['listChanged'] ?? null ),
	'Discovery returned the wrong tools capability.'
);
cmsa_native_mcp_assert( 30000 === ( $discover_data['result']['ttlMs'] ?? null ), 'Discovery cache TTL is incorrect.' );
cmsa_native_mcp_assert( 'private' === ( $discover_data['result']['cacheScope'] ?? '' ), 'Discovery cache scope is not private.' );
cmsa_native_mcp_assert(
	'chattanooga-cms-admin' === ( $discover_data['result']['_meta']['io.modelcontextprotocol/serverInfo']['name'] ?? '' ),
	'Discovery did not identify the Chattanooga CMS Admin server.'
);

// tools/list: deterministic names, bounded public ability surface, and correct read/write annotations.
$list = cmsa_native_mcp_modern( 'tools/list', array(), 102 );
cmsa_native_mcp_assert( 200 === $list->get_status(), 'tools/list did not return HTTP 200.' );
$list_data = $list->get_data();
$tools = cmsa_native_mcp_all_tools();
cmsa_native_mcp_assert( is_array( $tools ) && ! empty( $tools ), 'tools/list returned no tools.' );

$names = array();
foreach ( $tools as $tool ) {
	cmsa_native_mcp_assert( is_array( $tool ) && isset( $tool['name'] ), 'A listed MCP tool is malformed.' );
	$names[] = (string) $tool['name'];
}
$sorted_names = $names;
sort( $sorted_names, SORT_STRING );
cmsa_native_mcp_assert( $names === $sorted_names, 'MCP tools/list is not deterministic.' );

foreach ( array( 'cmsa.catalog', 'cmsa.read-bridge', 'cmsa.write-bridge' ) as $required_tool ) {
	cmsa_native_mcp_assert( in_array( $required_tool, $names, true ), 'Required MCP tool is missing: ' . $required_tool );
}
foreach ( $names as $name ) {
	$allowed = in_array( $name, array( 'cmsa.catalog', 'cmsa.read-bridge', 'cmsa.write-bridge' ), true )
		|| 1 === preg_match( '/^cmsa\\.(?:bridge|rest)-[a-f0-9]{24}$/', $name );
	cmsa_native_mcp_assert( $allowed, 'Administrator/control-plane tool leaked into tools/list: ' . $name );
}

$catalog_tool = cmsa_native_mcp_tool( $tools, 'cmsa.catalog' );
$write_tool  = cmsa_native_mcp_tool( $tools, 'cmsa.write-bridge' );
cmsa_native_mcp_assert( true === ( $catalog_tool['annotations']['readOnlyHint'] ?? null ), 'catalog is not annotated read-only.' );
cmsa_native_mcp_assert( false === ( $write_tool['annotations']['readOnlyHint'] ?? null ), 'write-bridge is incorrectly annotated read-only.' );
cmsa_native_mcp_assert( true === ( $write_tool['annotations']['destructiveHint'] ?? null ), 'write-bridge is not annotated as mutating/destructive.' );

// Execute one real site-operation catalog call through MCP.
$catalog = cmsa_native_mcp_modern(
	'tools/call',
	array(
		'name'      => 'cmsa.catalog',
		'arguments' => array(),
	),
	103
);
cmsa_native_mcp_assert( 200 === $catalog->get_status(), 'MCP catalog tool call did not return HTTP 200.' );
$catalog_data = $catalog->get_data();
cmsa_native_mcp_assert( 'complete' === ( $catalog_data['result']['resultType'] ?? '' ), 'MCP tool call omitted complete resultType.' );
cmsa_native_mcp_assert( false === ( $catalog_data['result']['isError'] ?? true ), 'MCP catalog returned a tool error.' );
cmsa_native_mcp_assert( is_array( $catalog_data['result']['structuredContent']['items'] ?? null ), 'MCP catalog did not return bridgeable site-operation items.' );

// Tool errors stay inside a successful tools/call result instead of becoming protocol transport failures.
$missing = cmsa_native_mcp_modern(
	'tools/call',
	array(
		'name'      => 'cmsa.not-a-real-tool',
		'arguments' => array(),
	),
	104
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
cmsa_native_mcp_assert( -32020 === ( $mismatch_data['error']['code'] ?? null ), 'MCP header/body mismatch did not return -32020.' );

// Origin validation blocks browser-origin requests from unrelated sites.
$origin_request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$origin_request->set_header( 'content-type', 'application/json' );
$origin_request->set_header( 'origin', 'https://attacker.invalid' );
$origin_request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
$origin_request->set_header( 'Mcp-Method', 'server/discover' );
$origin_request->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 106,
			'method'  => 'server/discover',
			'params'  => array(
				'_meta' => array(
					'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
				),
			),
		)
	)
);
$origin = rest_do_request( $origin_request );
cmsa_native_mcp_assert( 403 === $origin->get_status(), 'Untrusted Origin was not rejected.' );

// REST permission callback rejects an unauthenticated caller before MCP method handling.
wp_set_current_user( 0 );
$anonymous = cmsa_native_mcp_modern( 'server/discover', array(), 107 );
cmsa_native_mcp_assert( 401 === $anonymous->get_status(), 'Anonymous MCP access was not rejected with HTTP 401.' );

wp_set_current_user( 1 );
echo "cmsa-native-mcp: PASS version=1.1.0 protocol=2026-07-28 compatibility_versions=none route=verified admin_boundary=verified origin_guard=verified tools_list=deterministic read_call=verified private_bridges=hidden header_validation=verified\n";
exit( 0 );
