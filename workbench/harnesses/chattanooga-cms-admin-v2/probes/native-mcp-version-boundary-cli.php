<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_native_version_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_native_version_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_native_version_fail( $message );
	}
}

function cmsa_native_version_request( $header_version, $body_version, $id, $include_meta = true, $include_capabilities = true, $capabilities = null ) {
	$params = array();
	if ( $include_meta ) {
		$params['_meta'] = array();
		if ( '' !== $body_version ) {
			$params['_meta']['io.modelcontextprotocol/protocolVersion'] = $body_version;
		}
		if ( $include_capabilities ) {
			$params['_meta']['io.modelcontextprotocol/clientCapabilities'] = null === $capabilities ? (object) array() : $capabilities;
		}
		$params['_meta']['io.modelcontextprotocol/clientInfo'] = array(
			'name'    => 'cmsa-native-version-boundary',
			'version' => '1.0.0',
		);
	}

	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	if ( '' !== $header_version ) {
		$request->set_header( 'MCP-Protocol-Version', $header_version );
	}
	$request->set_header( 'Mcp-Method', 'tools/list' );
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => 'tools/list',
				'params'  => $params,
			)
		)
	);

	return rest_do_request( $request );
}

function cmsa_native_version_assert_error( WP_REST_Response $response, $expected_code, $label ) {
	cmsa_native_version_assert( 400 === $response->get_status(), $label . ' did not return HTTP 400.' );
	$data = $response->get_data();
	cmsa_native_version_assert( $expected_code === ( $data['error']['code'] ?? null ), $label . ' returned the wrong protocol error code.' );
	cmsa_native_version_assert( ! isset( $data['result'] ), $label . ' incorrectly returned a successful MCP result.' );
	cmsa_native_version_assert( ! isset( $data['_meta'] ), $label . ' leaked non-schema top-level _meta.' );
	return $data;
}

function cmsa_native_version_assert_unsupported( WP_REST_Response $response, $requested, $label ) {
	$data = cmsa_native_version_assert_error( $response, -32022, $label );
	cmsa_native_version_assert(
		array( '2026-07-28' ) === ( $data['error']['data']['supported'] ?? null ),
		$label . ' did not return the exact modern supported-version list.'
	);
	cmsa_native_version_assert(
		$requested === ( $data['error']['data']['requested'] ?? null ),
		$label . ' did not echo the requested unsupported version.'
	);
	cmsa_native_version_assert( ! isset( $data['error']['data']['supportedVersions'] ), $label . ' retained the obsolete supportedVersions field.' );
}

wp_set_current_user( 1 );

cmsa_native_version_assert_unsupported(
	cmsa_native_version_request( '2099-01-01', '2099-01-01', 601 ),
	'2099-01-01',
	'Matching unsupported declarations'
);

cmsa_native_version_assert_error(
	cmsa_native_version_request( '2026-07-28', '2099-01-01', 602 ),
	-32020,
	'Modern header/body version mismatch'
);
cmsa_native_version_assert_error(
	cmsa_native_version_request( '2099-01-01', '2026-07-28', 603 ),
	-32020,
	'Unknown header/body version mismatch'
);
cmsa_native_version_assert_error(
	cmsa_native_version_request( '', '2026-07-28', 604 ),
	-32020,
	'Missing MCP-Protocol-Version header'
);

cmsa_native_version_assert_error(
	cmsa_native_version_request( '2026-07-28', '', 605, false ),
	-32602,
	'Missing modern _meta envelope'
);
cmsa_native_version_assert_error(
	cmsa_native_version_request( '2026-07-28', '2026-07-28', 606, true, false ),
	-32602,
	'Missing clientCapabilities'
);
cmsa_native_version_assert_error(
	cmsa_native_version_request( '2026-07-28', '2026-07-28', 607, true, true, array( 'invalid-list-shape' ) ),
	-32602,
	'Malformed clientCapabilities'
);

$valid = cmsa_native_version_request( '2026-07-28', '2026-07-28', 608 );
cmsa_native_version_assert( 200 === $valid->get_status(), 'Valid modern tools/list regressed after declaration validation.' );
$valid_data = $valid->get_data();
cmsa_native_version_assert( 'complete' === ( $valid_data['result']['resultType'] ?? '' ), 'Valid modern tools/list did not return a complete result.' );
cmsa_native_version_assert( ! empty( $valid_data['result']['tools'] ), 'Valid modern tools/list returned no tools.' );

echo "cmsa-native-mcp-version-boundary: PASS unsupported_shape=verified header_mismatch=verified missing_headers=verified envelope_required=verified client_capabilities=verified modern_valid=preserved\n";
exit( 0 );
