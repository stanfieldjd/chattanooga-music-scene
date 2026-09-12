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

function cmsa_native_version_request( $header_version, $body_version, $id ) {
	$params = array();
	if ( '' !== $body_version ) {
		$params['_meta'] = array(
			'io.modelcontextprotocol/protocolVersion' => $body_version,
			'io.modelcontextprotocol/clientInfo'      => array(
				'name'    => 'cmsa-native-version-boundary',
				'version' => '1.0.0',
			),
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

function cmsa_native_version_assert_rejected( WP_REST_Response $response, $label ) {
	cmsa_native_version_assert( 400 === $response->get_status(), $label . ' did not return HTTP 400.' );
	$data = $response->get_data();
	cmsa_native_version_assert( -32022 === ( $data['error']['code'] ?? null ), $label . ' did not return the protocol-version error code.' );
	cmsa_native_version_assert( ! isset( $data['result'] ), $label . ' incorrectly returned a successful MCP result.' );
	$supported = $data['error']['data']['supportedVersions'] ?? array();
	cmsa_native_version_assert( is_array( $supported ), $label . ' did not return supportedVersions.' );
	foreach ( array( '2026-07-28', '2025-11-25', '2025-06-18', '2025-03-26' ) as $version ) {
		cmsa_native_version_assert( in_array( $version, $supported, true ), $label . ' omitted supported version ' . $version . '.' );
	}
}

wp_set_current_user( 1 );

cmsa_native_version_assert_rejected(
	cmsa_native_version_request( '2099-01-01', '2099-01-01', 601 ),
	'Matching unsupported header/body declarations'
);
cmsa_native_version_assert_rejected(
	cmsa_native_version_request( '2099-01-01', '', 602 ),
	'Unsupported header-only declaration'
);
cmsa_native_version_assert_rejected(
	cmsa_native_version_request( '', '2099-01-01', 603 ),
	'Unsupported body-only declaration'
);

$valid = cmsa_native_version_request( '2026-07-28', '2026-07-28', 604 );
cmsa_native_version_assert( 200 === $valid->get_status(), 'Valid modern tools/list regressed after declaration validation.' );
$valid_data = $valid->get_data();
cmsa_native_version_assert( 'complete' === ( $valid_data['result']['resultType'] ?? '' ), 'Valid modern tools/list did not return a complete result.' );
cmsa_native_version_assert( ! empty( $valid_data['result']['tools'] ), 'Valid modern tools/list returned no tools.' );

echo "cmsa-native-mcp-version-boundary: PASS unsupported_matching=rejected unsupported_header=rejected unsupported_body=rejected modern_valid=preserved\n";
exit( 0 );
