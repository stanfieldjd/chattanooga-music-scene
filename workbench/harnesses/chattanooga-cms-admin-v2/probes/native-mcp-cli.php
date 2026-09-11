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

wp_set_current_user( 1 );
cmsa_native_mcp_assert( defined( 'CUA_VERSION' ) && '1.1.0' === CUA_VERSION, 'Chattanooga CMS Admin 1.1.0 did not load.' );
cmsa_native_mcp_assert( class_exists( 'CUA_MCP_Server' ), 'Native MCP server class did not load.' );

$server = rest_get_server();
$routes = $server->get_routes();
cmsa_native_mcp_assert( isset( $routes['/chattanooga-cms-admin/v1/mcp'] ), 'Native MCP REST route is not registered.' );

// Modern discovery: protocol version, capabilities, server identity, and private cache policy.
$discover = cmsa_native_mcp_modern( 'server/discover', array(), 101 );
cmsa_native_mcp_assert( 200 === $discover->get_status(), 'Modern server/discover did not return HTTP 200.' );
$discover_data = $discover->get_data();
cmsa_native_mcp_assert( '2.0' === ( $discover_data['jsonrpc'] ?? '' ), 'Modern discovery did not return JSON-RPC 2.0.' );
cmsa_native_mcp_assert( 101 === ( $discover_data['id'] ?? null ), 'Modern discovery returned the wrong request id.' );
cmsa_native_mcp_assert( 'complete' === ( $discover_data['result']['resultType'] ?? '' ), 'Modern discovery omitted complete resultType.' );
cmsa_native_mcp_assert(
	in_array( '2026-07-28', $discover_data['result']['supportedVersions'] ?? array(), true ),
	'Modern discovery did not advertise MCP 2026-07-28.'
);
cmsa_native_mcp_assert(
	false === ( $discover_data['result']['capabilities']['tools']['listChanged'] ?? null ),
	'Modern discovery returned the wrong tools capability.'
);
cmsa_native_mcp_assert( 30000 === ( $discover_data['result']['ttlMs'] ?? null ), 'Modern discovery cache TTL is incorrect.' );
cmsa_native_mcp_assert( 'private' === ( $discover_data['result']['cacheScope'] ?? '' ), 'Modern discovery cache scope is not private.' );
cmsa_native_mcp_assert(
	'chattanooga-cms-admin' === ( $discover_data['result']['_meta']['io.modelcontextprotocol/serverInfo']['name'] ?? '' ),
	'Modern discovery did not identify the Chattanooga CMS Admin server.'
);

// Modern tools/list: deterministic names, bounded public ability surface, and correct read/write annotations.
$list = cmsa_native_mcp_modern( 'tools/list', array(), 102 );
cmsa_native_mcp_assert( 200 === $list->get_status(), 'Modern tools/list did not return HTTP 200.' );
$list_data = $list->get_data();
$tools = $list_data['result']['tools'] ?? null;
cmsa_native_mcp_assert( is_array( $tools ) && ! empty( $tools ), 'Modern tools/list returned no tools.' );

$names = array();
foreach ( $tools as $tool ) {
	cmsa_native_mcp_assert( is_array( $tool ) && isset( $tool['name'] ), 'A listed MCP tool is malformed.' );
	$names[] = (string) $tool['name'];
}
$sorted_names = $names;
sort( $sorted_names, SORT_STRING );
cmsa_native_mcp_assert( $names === $sorted_names, 'MCP tools/list is not deterministic.' );

foreach ( array( 'cmsa.catalog', 'cmsa.get-health', 'cmsa.read-bridge', 'cmsa.write-bridge' ) as $required_tool ) {
	cmsa_native_mcp_assert( in_array( $required_tool, $names, true ), 'Required MCP tool is missing: ' . $required_tool );
}
foreach ( $names as $name ) {
	cmsa_native_mcp_assert( 0 !== strpos( $name, 'cmsa.bridge-' ), 'Private dynamic ability bridge leaked into tools/list.' );
	cmsa_native_mcp_assert( 0 !== strpos( $name, 'cmsa.rest-' ), 'Private dynamic REST bridge leaked into tools/list.' );
}

$health_tool = cmsa_native_mcp_tool( $tools, 'cmsa.get-health' );
$write_tool  = cmsa_native_mcp_tool( $tools, 'cmsa.write-bridge' );
cmsa_native_mcp_assert( true === ( $health_tool['annotations']['readOnlyHint'] ?? null ), 'get-health is not annotated read-only.' );
cmsa_native_mcp_assert( false === ( $write_tool['annotations']['readOnlyHint'] ?? null ), 'write-bridge is incorrectly annotated read-only.' );
cmsa_native_mcp_assert( true === ( $write_tool['annotations']['destructiveHint'] ?? null ), 'write-bridge is not annotated as mutating/destructive.' );

// Execute one real read-only administrator tool through MCP.
$health = cmsa_native_mcp_modern(
	'tools/call',
	array(
		'name'      => 'cmsa.get-health',
		'arguments' => array(),
	),
	103
);
cmsa_native_mcp_assert( 200 === $health->get_status(), 'MCP get-health tool call did not return HTTP 200.' );
$health_data = $health->get_data();
cmsa_native_mcp_assert( 'complete' === ( $health_data['result']['resultType'] ?? '' ), 'MCP tool call omitted complete resultType.' );
cmsa_native_mcp_assert( false === ( $health_data['result']['isError'] ?? true ), 'MCP get-health returned a tool error.' );
cmsa_native_mcp_assert(
	get_bloginfo( 'version' ) === ( $health_data['result']['structuredContent']['wordpress_version'] ?? '' ),
	'MCP get-health returned the wrong WordPress version.'
);

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

// SEP-2243 header mismatch is rejected as a protocol error before dispatch.
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

// Legacy handshake-era clients remain usable on the same route without sessions.
$legacy = cmsa_native_mcp_post(
	'initialize',
	array(
		'protocolVersion' => '2025-11-25',
		'capabilities'    => array(),
		'clientInfo'      => array(
			'name'    => 'cmsa-legacy-probe',
			'version' => '1.0.0',
		),
	),
	array(),
	107
);
cmsa_native_mcp_assert( 200 === $legacy->get_status(), 'Legacy initialize did not return HTTP 200.' );
$legacy_data = $legacy->get_data();
cmsa_native_mcp_assert( '2025-11-25' === ( $legacy_data['result']['protocolVersion'] ?? '' ), 'Legacy initialize negotiated the wrong version.' );
cmsa_native_mcp_assert(
	'chattanooga-cms-admin' === ( $legacy_data['result']['serverInfo']['name'] ?? '' ),
	'Legacy initialize returned the wrong server identity.'
);
cmsa_native_mcp_assert( ! isset( $legacy_data['result']['resultType'] ), 'Legacy initialize leaked modern resultType.' );

// REST permission callback rejects an unauthenticated caller before MCP method handling.
wp_set_current_user( 0 );
$anonymous = cmsa_native_mcp_modern( 'server/discover', array(), 108 );
cmsa_native_mcp_assert( 401 === $anonymous->get_status(), 'Anonymous MCP access was not rejected with HTTP 401.' );

wp_set_current_user( 1 );
echo "cmsa-native-mcp: PASS version=1.1.0 modern=2026-07-28 legacy=2025-11-25 route=verified admin_boundary=verified origin_guard=verified tools_list=deterministic read_call=verified private_bridges=hidden header_validation=verified\n";
exit( 0 );
