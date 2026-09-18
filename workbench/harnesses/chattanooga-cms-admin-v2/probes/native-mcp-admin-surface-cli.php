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

function cmsa_native_mcp_expected_tool_name( $ability_name ) {
	$prefix = 'chattanooga-cms-admin/';
	$short  = substr( (string) $ability_name, strlen( $prefix ) );
	return 'cmsa.' . preg_replace( '/[^A-Za-z0-9_.-]/', '-', $short );
}

wp_set_current_user( 1 );

$expected = array();
if ( ! function_exists( 'wp_get_abilities' ) ) {
	cmsa_native_mcp_surface_fail( 'WordPress Abilities API is unavailable.' );
}

foreach ( wp_get_abilities() as $ability ) {
	if ( ! $ability instanceof WP_Ability ) {
		continue;
	}
	$ability_name = $ability->get_name();
	if ( 0 !== strpos( $ability_name, 'chattanooga-cms-admin/' ) ) {
		continue;
	}
	$expected[] = cmsa_native_mcp_expected_tool_name( $ability_name );
}

sort( $expected, SORT_STRING );
cmsa_native_mcp_surface_assert( ! empty( $expected ), 'No Chattanooga administrator abilities were registered.' );

$names  = array();
$cursor = '';
$id     = 301;

do {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', 'tools/list' );

	$params = array(
		'_meta' => array(
			'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
			'io.modelcontextprotocol/clientCapabilities' => array(),
			'io.modelcontextprotocol/clientInfo'      => array(
				'name'    => 'cmsa-admin-surface-probe',
				'version' => '1.0.0',
			),
		),
	);
	if ( '' !== $cursor ) {
		$params['cursor'] = $cursor;
	}

	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id++,
				'method'  => 'tools/list',
				'params'  => $params,
			)
		)
	);

	$response = rest_do_request( $request );
	cmsa_native_mcp_surface_assert( 200 === $response->get_status(), 'MCP tools/list failed.' );
	$data  = $response->get_data();
	$tools = $data['result']['tools'] ?? null;
	cmsa_native_mcp_surface_assert( is_array( $tools ), 'MCP tools/list did not return a tools array.' );

	foreach ( $tools as $tool ) {
		if ( is_array( $tool ) && isset( $tool['name'] ) ) {
			$names[] = (string) $tool['name'];
		}
	}

	$cursor = isset( $data['result']['nextCursor'] ) ? (string) $data['result']['nextCursor'] : '';
} while ( '' !== $cursor );

$names = array_values( array_unique( $names ) );
sort( $names, SORT_STRING );

$missing    = array_values( array_diff( $expected, $names ) );
$unexpected = array_values( array_diff( $names, $expected ) );

cmsa_native_mcp_surface_assert(
	empty( $missing ),
	'MCP surface is incomplete. Missing abilities: ' . implode( ', ', $missing )
);
cmsa_native_mcp_surface_assert(
	empty( $unexpected ),
	'MCP surface contains tools outside the Chattanooga ability namespace: ' . implode( ', ', $unexpected )
);
cmsa_native_mcp_surface_assert(
	$names === $expected,
	'MCP tool list does not exactly match the registered Chattanooga administrator abilities.'
);

echo 'cmsa-native-mcp-admin-surface: PASS registered=' . count( $expected ) . ' exposed=' . count( $names ) . " administrator_surface=exposed\n";
exit( 0 );
