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
$modern->set_header( 'Mcp-Method', 'server/discover' );
$modern->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 201,
			'method'  => 'server/discover',
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
cmsa_native_mcp_era_assert( 200 === $modern_response->get_status(), 'Current MCP protocol was not accepted.' );
cmsa_native_mcp_era_assert( 'complete' === ( $modern_data['result']['resultType'] ?? '' ), 'Current MCP discovery did not complete.' );
cmsa_native_mcp_era_assert(
	array( '2026-07-28' ) === ( $modern_data['result']['supportedVersions'] ?? null ),
	'Current MCP discovery advertised compatibility versions.'
);

$initialize = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$initialize->set_header( 'content-type', 'application/json' );
$initialize->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 201,
			'method'  => 'initialize',
			'params'  => array(
				'protocolVersion' => '2026-07-28',
				'capabilities'    => array(),
				'clientInfo'      => array(
					'name'    => 'cmsa-standard-probe',
					'version' => '1.0.0',
				),
			),
		)
	)
);
$initialize_response = rest_do_request( $initialize );
$initialize_data     = $initialize_response->get_data();
cmsa_native_mcp_era_assert( 200 === $initialize_response->get_status(), 'Standard MCP initialize was not accepted.' );
cmsa_native_mcp_era_assert( '2026-07-28' === ( $initialize_data['result']['protocolVersion'] ?? '' ), 'Standard initialize returned the wrong protocol version.' );
cmsa_native_mcp_era_assert( 'chattanooga-cms-admin' === ( $initialize_data['result']['serverInfo']['name'] ?? '' ), 'Standard initialize omitted server identity.' );

$initialized = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$initialized->set_header( 'content-type', 'application/json' );
$initialized->set_body( wp_json_encode( array( 'jsonrpc' => '2.0', 'method' => 'notifications/initialized' ) ) );
$initialized_response = rest_do_request( $initialized );
cmsa_native_mcp_era_assert( 202 === $initialized_response->get_status(), 'Standard initialized notification was not accepted.' );

$standard_list = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$standard_list->set_header( 'content-type', 'application/json' );
$standard_list->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 203,
			'method'  => 'tools/list',
			'params'  => array(),
		)
	)
);
$standard_list_response = rest_do_request( $standard_list );
$standard_list_data     = $standard_list_response->get_data();
cmsa_native_mcp_era_assert( 200 === $standard_list_response->get_status(), 'Standard tools/list was not accepted without custom headers.' );
cmsa_native_mcp_era_assert( is_array( $standard_list_data['result']['tools'] ?? null ), 'Standard tools/list did not return tools.' );

$legacy = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$legacy->set_header( 'content-type', 'application/json' );
$legacy->set_header( 'MCP-Protocol-Version', '2025-11-25' );
$legacy->set_header( 'Mcp-Method', 'initialize' );
$legacy->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 202,
			'method'  => 'initialize',
			'params'  => array(
				'protocolVersion' => '2025-11-25',
				'capabilities'    => array(),
				'clientInfo'      => array(
					'name'    => 'cmsa-legacy-probe',
					'version' => '1.0.0',
				),
				'_meta' => array(
					'io.modelcontextprotocol/protocolVersion' => '2025-11-25',
				),
			),
		)
	)
);
$legacy_response = rest_do_request( $legacy );
$legacy_data     = $legacy_response->get_data();
cmsa_native_mcp_era_assert( 400 === $legacy_response->get_status(), 'Legacy MCP protocol remained accepted.' );
cmsa_native_mcp_era_assert( -32022 === ( $legacy_data['error']['code'] ?? null ), 'Legacy MCP protocol did not fail version validation.' );
cmsa_native_mcp_era_assert(
	array( '2026-07-28' ) === ( $legacy_data['error']['data']['supportedVersions'] ?? null ),
	'Legacy rejection advertised compatibility versions.'
);

echo "cmsa-native-mcp-era: PASS protocol=2026-07-28 standard_lifecycle=verified legacy_compatibility=removed\n";
exit( 0 );
