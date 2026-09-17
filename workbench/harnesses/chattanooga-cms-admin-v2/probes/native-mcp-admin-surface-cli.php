<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_native_mcp_surface_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_native_mcp_surface_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_native_mcp_surface_fail( $message );
	}
}

function cmsa_native_mcp_surface_page( $cursor = '' ) {
	$params = array( '_meta' => array( 'io.modelcontextprotocol/protocolVersion' => '2026-07-28' ) );
	if ( '' !== $cursor ) {
		$params['cursor'] = $cursor;
	}
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', 'tools/list' );
	$request->set_body( wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 301, 'method' => 'tools/list', 'params' => $params ) ) );
	return rest_do_request( $request );
}

wp_set_current_user( 1 );

$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$request->set_header( 'content-type', 'application/json' );
$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
$request->set_header( 'Mcp-Method', 'tools/list' );
$request->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 301,
			'method'  => 'tools/list',
			'params'  => array(
				'_meta' => array(
					'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
					'io.modelcontextprotocol/clientInfo'      => array(
						'name'    => 'cmsa-admin-surface-probe',
						'version' => '1.0.0',
					),
				),
			),
		)
	)
);

$response = rest_do_request( $request );
cmsa_native_mcp_surface_assert( 200 === $response->get_status(), 'MCP tools/list failed.' );
$data  = $response->get_data();
$tools = $data['result']['tools'] ?? null;
cmsa_native_mcp_surface_assert( is_array( $tools ), 'MCP tools/list did not return a tools array.' );
$cursor = trim( (string) ( $data['result']['nextCursor'] ?? '' ) );
for ( $page = 0; '' !== $cursor && $page < 19; $page++ ) {
	$response = cmsa_native_mcp_surface_page( $cursor );
	cmsa_native_mcp_surface_assert( 200 === $response->get_status(), 'Paginated MCP tools/list failed.' );
	$data = $response->get_data();
	$page_tools = $data['result']['tools'] ?? null;
	cmsa_native_mcp_surface_assert( is_array( $page_tools ), 'Paginated MCP tools/list returned no tools array.' );
	$tools = array_merge( $tools, $page_tools );
	$cursor = trim( (string) ( $data['result']['nextCursor'] ?? '' ) );
}
cmsa_native_mcp_surface_assert( '' === $cursor, 'MCP tools/list pagination exceeded the cursor safety limit.' );

$names = array();
foreach ( $tools as $tool ) {
	if ( is_array( $tool ) && isset( $tool['name'] ) ) {
		$names[] = (string) $tool['name'];
	}
}

$required = array(
	'cmsa.catalog',
	'cmsa.read-bridge',
	'cmsa.write-bridge',
);

$missing = array_values( array_diff( $required, $names ) );
cmsa_native_mcp_surface_assert(
	empty( $missing ),
	'MCP site-operation surface is incomplete. Missing: ' . implode( ', ', $missing )
);

foreach ( $names as $name ) {
	$allowed = in_array( $name, $required, true )
		|| 1 === preg_match( '/^cmsa\\.(?:bridge|rest)-[a-f0-9]{24}$/', $name );
	cmsa_native_mcp_surface_assert( $allowed, 'Administrator/control-plane tool leaked into MCP: ' . $name );
}

echo 'cmsa-native-mcp-site-surface: PASS required=' . count( $required ) . ' exposed=' . count( $names ) . " administrator_surface=excluded typed_external_facades=exposed\n";
exit( 0 );
