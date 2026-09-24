<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

function cmsa_official_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

function cmsa_official_request( $method, array $params, $id, $session = '', $protocol = '2025-06-18' ) {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'accept', 'application/json, text/event-stream' );
	if ( 'initialize' !== $method ) {
		$request->set_header( 'MCP-Protocol-Version', $protocol );
	}
	if ( '' !== $session ) {
		$request->set_header( 'Mcp-Session-Id', $session );
	}
	$body = array(
		'jsonrpc' => '2.0',
		'id'      => $id,
		'method'  => $method,
		'params'  => $params,
	);
	$request->set_body( wp_json_encode( $body ) );
	return rest_do_request( $request );
}

function cmsa_official_initialize( $id ) {
	$response = cmsa_official_request(
		'initialize',
		array(
			'protocolVersion' => '2025-06-18',
			'capabilities'    => array(),
			'clientInfo'      => array( 'name' => 'cmsa-official-adapter-probe', 'version' => '1.0.0' ),
		),
		$id
	);
	cmsa_official_assert( 200 === $response->get_status(), 'Official adapter initialize failed: ' . wp_json_encode( $response->get_data() ) );
	$data = $response->get_data();
	cmsa_official_assert( '2025-06-18' === ( $data['result']['protocolVersion'] ?? '' ), 'Official adapter did not negotiate 2025-06-18.' );
	$headers = array_change_key_case( $response->get_headers(), CASE_LOWER );
	$session = trim( (string) ( $headers['mcp-session-id'] ?? '' ) );
	cmsa_official_assert( '' !== $session, 'Official adapter initialize did not issue Mcp-Session-Id.' );
	return $session;
}

wp_set_current_user( 1 );

cmsa_official_assert( defined( 'CUA_VERSION' ) && '1.2.39' === CUA_VERSION, 'Chattanooga CMS Admin 1.2.39 did not load.' );
cmsa_official_assert( class_exists( '\\WP\\MCP\\Core\\McpAdapter' ), 'Official WordPress MCP Adapter is not loaded.' );
cmsa_official_assert( '0.6.1' === \WP\MCP\Core\McpAdapter::VERSION, 'Unexpected WordPress MCP Adapter version.' );
cmsa_official_assert( class_exists( 'CUA_MCP_Official_Transport' ), 'Chattanooga official transport provider did not load.' );

$routes = rest_get_server()->get_routes();
$route = $routes['/chattanooga-cms-admin/v1/mcp'] ?? null;
if ( ! is_array( $route ) || empty( $route ) ) {
	$registration_error = CUA_MCP_Official_Transport::registration_error();
	$message = 'Official Chattanooga MCP route is not registered.';
	if ( is_wp_error( $registration_error ) ) {
		$message .= ' create_server=' . $registration_error->get_error_code() . ': ' . $registration_error->get_error_message();
	}
	cmsa_official_assert( false, $message );
}
$official_callback = false;
foreach ( $route as $definition ) {
	$callback = is_array( $definition ) ? ( $definition['callback'] ?? null ) : null;
	if ( is_array( $callback ) && isset( $callback[0] ) && is_object( $callback[0] ) && $callback[0] instanceof \WP\MCP\Transport\HttpTransport ) {
		$official_callback = true;
		break;
	}
}
cmsa_official_assert( $official_callback, 'Chattanooga MCP endpoint is not owned by the official HttpTransport.' );

$expected_names = array( 'cmsa.discovery', 'cmsa.stability-check', 'cmsa.read-bridge', 'cmsa.write-bridge' );
$expected_fingerprint = '';
for ( $iteration = 0; $iteration < 20; ++$iteration ) {
	$session = cmsa_official_initialize( 100 + $iteration );
	if ( 10 === $iteration ) {
		wp_cache_flush();
	}
	$list = cmsa_official_request( 'tools/list', array(), 200 + $iteration, $session );
	cmsa_official_assert( 200 === $list->get_status(), 'Official adapter tools/list failed on reconnect ' . $iteration . ': ' . wp_json_encode( $list->get_data() ) );
	$data = $list->get_data();
	$tools = $data['result']['tools'] ?? null;
	cmsa_official_assert( is_array( $tools ), 'Official adapter tools/list did not return tools.' );
	$names = array_values( array_map( static function ( $tool ) { return (string) ( $tool['name'] ?? '' ); }, $tools ) );
	cmsa_official_assert( $expected_names === $names, 'Official adapter tool names changed: ' . wp_json_encode( $names ) );
	$fingerprint = hash( 'sha256', wp_json_encode( $tools, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	if ( '' === $expected_fingerprint ) {
		$expected_fingerprint = $fingerprint;
	} else {
		cmsa_official_assert( hash_equals( $expected_fingerprint, $fingerprint ), 'Official adapter descriptor fingerprint changed between fresh sessions.' );
	}
}

$session = cmsa_official_initialize( 401 );
wp_cache_flush();
$list_after_flush = cmsa_official_request( 'tools/list', array(), 402, $session );
cmsa_official_assert( 200 === $list_after_flush->get_status(), 'Official adapter session did not survive object-cache flush.' );

$call = cmsa_official_request(
	'tools/call',
	array(
		'name'      => 'cmsa.discovery',
		'arguments' => array( 'limit' => 1 ),
	),
	403,
	$session
);
cmsa_official_assert( 200 === $call->get_status(), 'Official adapter discovery tool call failed: ' . wp_json_encode( $call->get_data() ) );
$call_data = $call->get_data();
cmsa_official_assert( empty( $call_data['error'] ), 'Official adapter discovery call returned a protocol error.' );
cmsa_official_assert( is_array( $call_data['result'] ?? null ), 'Official adapter discovery call returned no result.' );

wp_set_current_user( 0 );
$anonymous = cmsa_official_request(
	'initialize',
	array(
		'protocolVersion' => '2025-06-18',
		'capabilities' => array(),
		'clientInfo' => array( 'name' => 'anonymous-probe', 'version' => '1' ),
	),
	500
);
cmsa_official_assert( in_array( $anonymous->get_status(), array( 401, 403 ), true ), 'Unauthenticated official adapter initialize was not denied.' );

echo 'cmsa-official-mcp: PASS version=1.2.39 adapter=0.6.1 protocol=2025-06-18 reconnects=20 tools=4 fingerprint=' . $expected_fingerprint . ' route=official-http-transport cache_flush=session-survived auth=transport-gated' . PHP_EOL;
exit( 0 );
