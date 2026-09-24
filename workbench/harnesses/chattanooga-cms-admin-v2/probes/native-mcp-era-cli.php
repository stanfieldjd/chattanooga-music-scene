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
cmsa_native_mcp_era_assert( array( '2026-07-28', '2025-11-25', '2025-06-18' ) === ( $discover_data['result']['supportedVersions'] ?? null ), 'Discovery did not advertise the supported protocol compatibility set.' );
cmsa_native_mcp_era_assert( ! isset( $discover_headers['mcp-session-id'] ), 'Modern discovery emitted a session header.' );

$list = cmsa_native_mcp_era_modern( 'tools/list', array(), 202, array( 'Mcp-Session-Id' => 'ignored-modern-session' ) );
$list_data = $list->get_data();
$list_headers = array_change_key_case( $list->get_headers(), CASE_LOWER );
cmsa_native_mcp_era_assert( 200 === $list->get_status(), 'Modern tools/list did not run statelessly.' );
cmsa_native_mcp_era_assert( is_array( $list_data['result']['tools'] ?? null ) && ! empty( $list_data['result']['tools'] ), 'Modern tools/list returned no tools.' );
cmsa_native_mcp_era_assert( 'complete' === ( $list_data['result']['resultType'] ?? null ), 'Modern tools/list omitted the 2026 resultType.' );
cmsa_native_mcp_era_assert( array_key_exists( 'ttlMs', $list_data['result'] ?? array() ), 'Modern tools/list omitted ttlMs.' );
cmsa_native_mcp_era_assert( array_key_exists( 'cacheScope', $list_data['result'] ?? array() ), 'Modern tools/list omitted cacheScope.' );
cmsa_native_mcp_era_assert( is_array( $list_data['result']['_meta']['io.modelcontextprotocol/serverInfo'] ?? null ), 'Modern tools/list omitted serverInfo metadata.' );
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

function cmsa_native_mcp_era_legacy_request( $version, $method, array $params, $id, $session_id = '' ) {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'accept', 'application/json, text/event-stream' );
	$request->set_header( 'MCP-Protocol-Version', $version );
	$request->set_header( 'Mcp-Method', $method );
	if ( '' !== $session_id ) {
		$request->set_header( 'Mcp-Session-Id', $session_id );
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

$legacy_versions = array( '2025-11-25', '2025-06-18' );
$legacy_fingerprint = null;
$id = 205;

foreach ( $legacy_versions as $legacy_version ) {
	for ( $iteration = 0; $iteration < 10; ++$iteration ) {
		wp_cache_flush();
		$legacy_response = cmsa_native_mcp_era_legacy_request(
			$legacy_version,
			'initialize',
			array(
				'protocolVersion' => $legacy_version,
				'capabilities'    => array(),
				'clientInfo'      => array(
					'name'    => 'cmsa-wordpress-compat-probe',
					'version' => '1.0.0',
				),
			),
			$id++
		);
		$legacy_headers = array_change_key_case( $legacy_response->get_headers(), CASE_LOWER );
		$legacy_session = trim( (string) ( $legacy_headers['mcp-session-id'] ?? '' ) );
		$legacy_data = $legacy_response->get_data();

		cmsa_native_mcp_era_assert( 200 === $legacy_response->get_status(), $legacy_version . ' initialize failed.' );
		cmsa_native_mcp_era_assert( $legacy_version === ( $legacy_data['result']['protocolVersion'] ?? '' ), $legacy_version . ' initialize was rewritten to a different version.' );
		cmsa_native_mcp_era_assert( $legacy_version === ( $legacy_headers['mcp-protocol-version'] ?? '' ), $legacy_version . ' initialize emitted the wrong protocol header.' );
		cmsa_native_mcp_era_assert( '' !== $legacy_session, $legacy_version . ' initialize did not establish a session.' );
		cmsa_native_mcp_era_assert( ! array_key_exists( 'resultType', $legacy_data['result'] ?? array() ), $legacy_version . ' initialize leaked 2026 resultType.' );
		cmsa_native_mcp_era_assert( ! array_key_exists( 'ttlMs', $legacy_data['result'] ?? array() ), $legacy_version . ' initialize leaked ttlMs.' );
		cmsa_native_mcp_era_assert( ! array_key_exists( 'cacheScope', $legacy_data['result'] ?? array() ), $legacy_version . ' initialize leaked cacheScope.' );

		$initialized = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
		$initialized->set_header( 'content-type', 'application/json' );
		$initialized->set_header( 'accept', 'application/json, text/event-stream' );
		$initialized->set_header( 'MCP-Protocol-Version', $legacy_version );
		$initialized->set_header( 'Mcp-Method', 'notifications/initialized' );
		$initialized->set_header( 'Mcp-Session-Id', $legacy_session );
		$initialized->set_body(
			wp_json_encode(
				array(
					'jsonrpc' => '2.0',
					'method'  => 'notifications/initialized',
					'params'  => array(),
				)
			)
		);
		$initialized_response = rest_do_request( $initialized );
		cmsa_native_mcp_era_assert( 202 === $initialized_response->get_status(), $legacy_version . ' initialized notification failed.' );

		wp_cache_flush();
		$legacy_list_response = cmsa_native_mcp_era_legacy_request( $legacy_version, 'tools/list', array(), $id++, $legacy_session );
		$legacy_list_headers = array_change_key_case( $legacy_list_response->get_headers(), CASE_LOWER );
		$legacy_list_data = $legacy_list_response->get_data();
		$legacy_tools = $legacy_list_data['result']['tools'] ?? null;
		cmsa_native_mcp_era_assert( 200 === $legacy_list_response->get_status(), $legacy_version . ' tools/list failed after cache flush.' );
		cmsa_native_mcp_era_assert( $legacy_version === ( $legacy_list_headers['mcp-protocol-version'] ?? '' ), $legacy_version . ' tools/list emitted the wrong protocol header.' );
		cmsa_native_mcp_era_assert( is_array( $legacy_tools ) && 4 === count( $legacy_tools ), $legacy_version . ' tools/list did not expose the stable four-tool catalog.' );
		cmsa_native_mcp_era_assert( ! array_key_exists( 'resultType', $legacy_list_data['result'] ?? array() ), $legacy_version . ' tools/list leaked 2026 resultType.' );
		cmsa_native_mcp_era_assert( ! array_key_exists( 'ttlMs', $legacy_list_data['result'] ?? array() ), $legacy_version . ' tools/list leaked ttlMs.' );
		cmsa_native_mcp_era_assert( ! array_key_exists( 'cacheScope', $legacy_list_data['result'] ?? array() ), $legacy_version . ' tools/list leaked cacheScope.' );

		$fingerprint = hash( 'sha256', (string) wp_json_encode( $legacy_tools ) );
		if ( null === $legacy_fingerprint ) {
			$legacy_fingerprint = $fingerprint;
		} else {
			cmsa_native_mcp_era_assert( $legacy_fingerprint === $fingerprint, $legacy_version . ' produced a different startup tool fingerprint.' );
		}
	}
}

$compat_initialize = cmsa_native_mcp_era_legacy_request(
	'2025-06-18',
	'initialize',
	array(
		'protocolVersion' => '2025-06-18',
		'capabilities'    => array(),
		'clientInfo'      => array( 'name' => 'cmsa-version-mismatch-probe', 'version' => '1.0.0' ),
	),
	$id++
);
$compat_headers = array_change_key_case( $compat_initialize->get_headers(), CASE_LOWER );
$compat_session = trim( (string) ( $compat_headers['mcp-session-id'] ?? '' ) );
cmsa_native_mcp_era_assert( '' !== $compat_session, '2025-06-18 mismatch probe did not establish a session.' );

$cross_version = cmsa_native_mcp_era_legacy_request( '2025-11-25', 'tools/list', array(), $id++, $compat_session );
cmsa_native_mcp_era_assert( 400 === $cross_version->get_status(), 'A 2025-06-18 session accepted a 2025-11-25 follow-up.' );
cmsa_native_mcp_era_assert( -32022 === ( $cross_version->get_data()['error']['code'] ?? null ), 'Cross-version legacy session mismatch returned the wrong error.' );

$without_session = cmsa_native_mcp_era_legacy_request( '2025-06-18', 'tools/list', array(), $id++ );
cmsa_native_mcp_era_assert( 400 === $without_session->get_status(), '2025-06-18 tools/list without a session was accepted.' );
cmsa_native_mcp_era_assert( -32001 === ( $without_session->get_data()['error']['code'] ?? null ), '2025-06-18 missing-session request returned the wrong error.' );

$legacy_discover = cmsa_native_mcp_era_legacy_request( '2025-06-18', 'server/discover', array(), $id++, $compat_session );
cmsa_native_mcp_era_assert( 404 === $legacy_discover->get_status(), '2025-06-18 server/discover was accepted.' );
cmsa_native_mcp_era_assert( -32601 === ( $legacy_discover->get_data()['error']['code'] ?? null ), '2025-06-18 server/discover returned the wrong protocol error.' );

$unsupported = cmsa_native_mcp_era_legacy_request(
	'2025-03-26',
	'initialize',
	array(
		'protocolVersion' => '2025-03-26',
		'capabilities'    => array(),
		'clientInfo'      => array( 'name' => 'cmsa-unsupported-probe', 'version' => '1.0.0' ),
	),
	$id++
);
cmsa_native_mcp_era_assert( 400 === $unsupported->get_status(), 'Unsupported 2025-03-26 initialize was accepted.' );
cmsa_native_mcp_era_assert( -32022 === ( $unsupported->get_data()['error']['code'] ?? null ), 'Unsupported protocol returned the wrong error.' );

echo "cmsa-native-mcp-era: PASS modern=stateless legacy_2025_11=session-required legacy_2025_06=session-required reconnect_cycles=20 fingerprint=stable negotiation=exact cross_version=blocked unsupported_2025_03=blocked\n";
exit( 0 );
