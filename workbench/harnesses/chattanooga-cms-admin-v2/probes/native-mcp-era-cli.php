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

function cmsa_native_mcp_era_modern( $method, array $params = array(), $id = 201, array $headers = array() ) {
	$params['_meta'] = array(
		'io.modelcontextprotocol/protocolVersion'    => '2026-07-28',
		'io.modelcontextprotocol/clientCapabilities' => array(),
		'io.modelcontextprotocol/clientInfo'         => array(
			'name'    => 'cmsa-era-probe',
			'version' => '1.0.0',
		),
	);

	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', $method );

	if ( 'tools/call' === $method || 'prompts/get' === $method ) {
		$request->set_header( 'Mcp-Name', (string) ( $params['name'] ?? '' ) );
	} elseif ( 'resources/read' === $method ) {
		$request->set_header( 'Mcp-Name', (string) ( $params['uri'] ?? '' ) );
	}
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

wp_set_current_user( 1 );

$discover = cmsa_native_mcp_era_modern( 'server/discover' );
$discover_data = $discover->get_data();
$discover_headers = array_change_key_case( $discover->get_headers(), CASE_LOWER );
cmsa_native_mcp_era_assert( 200 === $discover->get_status(), 'Modern MCP discovery failed.' );
cmsa_native_mcp_era_assert( array( '2026-07-28', '2025-11-25' ) === ( $discover_data['result']['supportedVersions'] ?? null ), 'Discovery did not advertise both supported eras.' );
cmsa_native_mcp_era_assert( ! isset( $discover_headers['mcp-session-id'] ), 'Modern discovery emitted a session header.' );

$list = cmsa_native_mcp_era_modern( 'tools/list', array(), 202, array( 'Mcp-Session-Id' => 'ignored-modern-session' ) );
$list_data = $list->get_data();
$list_headers = array_change_key_case( $list->get_headers(), CASE_LOWER );
cmsa_native_mcp_era_assert( 200 === $list->get_status(), 'Modern tools/list did not run statelessly.' );
cmsa_native_mcp_era_assert( is_array( $list_data['result']['tools'] ?? null ) && ! empty( $list_data['result']['tools'] ), 'Modern tools/list returned no tools.' );
cmsa_native_mcp_era_assert( ! isset( $list_headers['mcp-session-id'] ), 'Modern tools/list echoed a session header.' );

$missing_method = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$missing_method->set_header( 'content-type', 'application/json' );
$missing_method->set_header( 'MCP-Protocol-Version', '2026-07-28' );
$missing_method->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 203,
			'method'  => 'tools/list',
			'params'  => array(
				'_meta' => array(
					'io.modelcontextprotocol/protocolVersion'    => '2026-07-28',
					'io.modelcontextprotocol/clientCapabilities' => array(),
				),
			),
		)
	)
);
$missing_method_response = rest_do_request( $missing_method );
cmsa_native_mcp_era_assert( 400 === $missing_method_response->get_status() && -32020 === ( $missing_method_response->get_data()['error']['code'] ?? null ), 'Modern request without Mcp-Method was accepted.' );

$initialize = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$initialize->set_header( 'content-type', 'application/json' );
$initialize->set_header( 'MCP-Protocol-Version', '2026-07-28' );
$initialize->set_header( 'Mcp-Method', 'initialize' );
$initialize->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 204,
			'method'  => 'initialize',
			'params'  => array(
				'protocolVersion' => '2026-07-28',
				'capabilities'    => array(),
				'clientInfo'      => array( 'name' => 'cmsa-era-probe', 'version' => '1.0.0' ),
			),
		)
	)
);
$initialize_response = rest_do_request( $initialize );
cmsa_native_mcp_era_assert( 400 === $initialize_response->get_status() && -32022 === ( $initialize_response->get_data()['error']['code'] ?? null ), 'Modern initialize was accepted.' );

$legacy = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$legacy->set_header( 'content-type', 'application/json' );
$legacy->set_header( 'MCP-Protocol-Version', '2025-11-25' );
$legacy->set_header( 'Mcp-Method', 'initialize' );
$legacy->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 205,
			'method'  => 'initialize',
			'params'  => array(
				'protocolVersion' => '2025-11-25',
				'capabilities'    => array(),
				'clientInfo'      => array( 'name' => 'cmsa-legacy-probe', 'version' => '1.0.0' ),
			),
		)
	)
);
$legacy_response = rest_do_request( $legacy );
$legacy_headers = array_change_key_case( $legacy_response->get_headers(), CASE_LOWER );
$legacy_session = trim( (string) ( $legacy_headers['mcp-session-id'] ?? '' ) );
cmsa_native_mcp_era_assert( 200 === $legacy_response->get_status(), 'Legacy initialize failed.' );
cmsa_native_mcp_era_assert( '2025-11-25' === ( $legacy_response->get_data()['result']['protocolVersion'] ?? '' ), 'Legacy initialize negotiated the wrong version.' );
cmsa_native_mcp_era_assert( '' !== $legacy_session, 'Legacy initialize did not establish a session.' );

echo "cmsa-native-mcp-era: PASS modern=stateless modern_headers=strict legacy=session-compatible\n";
exit( 0 );
