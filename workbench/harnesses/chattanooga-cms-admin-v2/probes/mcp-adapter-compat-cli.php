<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_adapter_compat_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_adapter_compat_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_adapter_compat_fail( $message );
	}
}

function cmsa_adapter_compat_post( $method, array $params = array(), $id = 1, array $headers = array() ) {
	$request = new WP_REST_Request( 'POST', '/mcp/mcp-adapter-default-server' );
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

function cmsa_adapter_compat_header( WP_REST_Response $response, $wanted ) {
	foreach ( $response->get_headers() as $name => $value ) {
		if ( 0 === strcasecmp( (string) $name, (string) $wanted ) ) {
			return (string) $value;
		}
	}
	return '';
}

function cmsa_adapter_compat_tool( array $tools, $name ) {
	foreach ( $tools as $tool ) {
		if ( is_array( $tool ) && $name === ( $tool['name'] ?? '' ) ) {
			return $tool;
		}
	}
	return null;
}

function cmsa_adapter_compat_tool_payload( WP_REST_Response $response ) {
	$data = $response->get_data();
	$result = $data['result'] ?? array();
	if ( isset( $result['structuredContent'] ) && is_array( $result['structuredContent'] ) ) {
		return $result['structuredContent'];
	}
	$text = $result['content'][0]['text'] ?? '';
	$decoded = json_decode( (string) $text, true );
	return is_array( $decoded ) ? $decoded : array();
}

wp_set_current_user( 1 );
cmsa_adapter_compat_assert( class_exists( 'CUA_MCP_Adapter_Compat' ), 'MCP Adapter compatibility class did not load.' );

$routes = rest_get_server()->get_routes();
cmsa_adapter_compat_assert( isset( $routes['/mcp/mcp-adapter-default-server'] ), 'MCP Adapter compatibility route is not registered.' );

$initialize = cmsa_adapter_compat_post(
	'initialize',
	array(
		'protocolVersion' => '2024-11-05',
		'capabilities'    => array(),
		'clientInfo'      => array(
			'name'    => 'wordpress-mcp-proxy',
			'version' => '1.0.0',
		),
	),
	201
);
cmsa_adapter_compat_assert( 200 === $initialize->get_status(), 'Adapter initialize did not return HTTP 200.' );
$initialize_data = $initialize->get_data();
cmsa_adapter_compat_assert( '2024-11-05' === ( $initialize_data['result']['protocolVersion'] ?? '' ), 'Adapter initialize did not negotiate MCP 2024-11-05.' );
cmsa_adapter_compat_assert( 'mcp-adapter-default-server' === ( $initialize_data['result']['serverInfo']['name'] ?? '' ), 'Adapter initialize returned the wrong server identity.' );
$session_id = cmsa_adapter_compat_header( $initialize, 'Mcp-Session-Id' );
cmsa_adapter_compat_assert( '' !== $session_id, 'Adapter initialize did not return Mcp-Session-Id.' );

$session_headers = array( 'Mcp-Session-Id' => $session_id );
$list = cmsa_adapter_compat_post( 'tools/list', array(), 202, $session_headers );
cmsa_adapter_compat_assert( 200 === $list->get_status(), 'Adapter tools/list did not return HTTP 200.' );
$list_data = $list->get_data();
$tools = $list_data['result']['tools'] ?? array();
cmsa_adapter_compat_assert( 3 === count( $tools ), 'Adapter tools/list did not expose exactly three meta-tools.' );
foreach ( array( 'mcp-adapter-discover-abilities', 'mcp-adapter-get-ability-info', 'mcp-adapter-execute-ability' ) as $tool_name ) {
	cmsa_adapter_compat_assert( is_array( cmsa_adapter_compat_tool( $tools, $tool_name ) ), 'Adapter meta-tool is missing: ' . $tool_name );
}

$discover = cmsa_adapter_compat_post(
	'tools/call',
	array(
		'name'      => 'mcp-adapter-discover-abilities',
		'arguments' => array(),
	),
	203,
	$session_headers
);
cmsa_adapter_compat_assert( 200 === $discover->get_status(), 'Adapter discover-abilities call did not return HTTP 200.' );
$discover_payload = cmsa_adapter_compat_tool_payload( $discover );
$abilities = $discover_payload['abilities'] ?? array();
$ability_names = array();
foreach ( $abilities as $ability ) {
	if ( is_array( $ability ) && isset( $ability['name'] ) ) {
		$ability_names[] = (string) $ability['name'];
	}
}
cmsa_adapter_compat_assert( in_array( 'chattanooga-cms-admin/get-health', $ability_names, true ), 'Adapter discovery did not expose Chattanooga get-health.' );

$info = cmsa_adapter_compat_post(
	'tools/call',
	array(
		'name'      => 'mcp-adapter-get-ability-info',
		'arguments' => array( 'ability_name' => 'chattanooga-cms-admin/get-health' ),
	),
	204,
	$session_headers
);
cmsa_adapter_compat_assert( 200 === $info->get_status(), 'Adapter get-ability-info call did not return HTTP 200.' );
$info_payload = cmsa_adapter_compat_tool_payload( $info );
cmsa_adapter_compat_assert( 'chattanooga-cms-admin/get-health' === ( $info_payload['name'] ?? '' ), 'Adapter get-ability-info returned the wrong ability.' );
cmsa_adapter_compat_assert( array_key_exists( 'input_schema', $info_payload ), 'Adapter get-ability-info omitted input_schema.' );

$execute = cmsa_adapter_compat_post(
	'tools/call',
	array(
		'name'      => 'mcp-adapter-execute-ability',
		'arguments' => array(
			'ability_name' => 'chattanooga-cms-admin/get-health',
			'parameters'   => array(),
		),
	),
	205,
	$session_headers
);
cmsa_adapter_compat_assert( 200 === $execute->get_status(), 'Adapter execute-ability call did not return HTTP 200.' );
$execute_payload = cmsa_adapter_compat_tool_payload( $execute );
cmsa_adapter_compat_assert( true === ( $execute_payload['success'] ?? false ), 'Adapter execute-ability did not report success.' );
cmsa_adapter_compat_assert( get_bloginfo( 'version' ) === ( $execute_payload['data']['wordpress_version'] ?? '' ), 'Adapter execute-ability returned the wrong WordPress version.' );

wp_set_current_user( 0 );
$anonymous = cmsa_adapter_compat_post( 'tools/list', array(), 206 );
cmsa_adapter_compat_assert( 401 === $anonymous->get_status(), 'Anonymous Adapter access was not rejected with HTTP 401.' );

wp_set_current_user( 1 );
echo "cmsa-mcp-adapter-compat: PASS protocol=2024-11-05 session=verified meta_tools=3 discover=verified info=verified execute=verified admin_boundary=verified\n";
exit( 0 );
