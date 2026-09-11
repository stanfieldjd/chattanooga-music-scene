<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_native_mcp_era_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_native_mcp_era_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_native_mcp_era_fail( $message );
	}
}

wp_set_current_user( 1 );

$modern = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$modern->set_header( 'content-type', 'application/json' );
$modern->set_header( 'MCP-Protocol-Version', '2026-07-28' );
$modern->set_header( 'Mcp-Method', 'ping' );
$modern->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 201,
			'method'  => 'ping',
			'params'  => array(
				'_meta' => array(
					'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
					'io.modelcontextprotocol/clientInfo'      => array(
						'name'    => 'cmsa-era-probe',
						'version' => '1.0.0',
					),
				),
			),
		)
	)
);
$modern_response = rest_do_request( $modern );
$modern_data     = $modern_response->get_data();
cmsa_native_mcp_era_assert( 400 === $modern_response->get_status(), 'Modern ping was not rejected.' );
cmsa_native_mcp_era_assert( -32601 === ( $modern_data['error']['code'] ?? null ), 'Modern ping did not return method-not-found.' );

$legacy = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$legacy->set_header( 'content-type', 'application/json' );
$legacy->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 202,
			'method'  => 'ping',
			'params'  => array(),
		)
	)
);
$legacy_response = rest_do_request( $legacy );
$legacy_data     = $legacy_response->get_data();
cmsa_native_mcp_era_assert( 200 === $legacy_response->get_status(), 'Legacy ping did not remain available.' );
cmsa_native_mcp_era_assert( array() === ( $legacy_data['result'] ?? null ), 'Legacy ping returned a non-empty result.' );

echo "cmsa-native-mcp-era: PASS modern_ping=method_not_found legacy_ping=verified\n";
exit( 0 );
