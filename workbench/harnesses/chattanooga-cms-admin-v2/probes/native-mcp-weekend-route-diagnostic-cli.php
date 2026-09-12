<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$request->set_header( 'content-type', 'application/json' );
$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
$request->set_header( 'Mcp-Method', 'tools/call' );
$request->set_header( 'Mcp-Name', 'cmsa.catalog' );
$request->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 1199,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'cmsa.catalog',
				'arguments' => array(),
				'_meta'     => array(
					'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
					'io.modelcontextprotocol/clientInfo'      => array(
						'name'    => 'cmsa-weekend-route-diagnostic',
						'version' => '1.0.0',
					),
				),
			),
		)
	)
);

$response = rest_do_request( $request );
if ( ! $response instanceof WP_REST_Response || 200 !== $response->get_status() ) {
	fwrite( STDERR, "FAIL: catalog request failed.\n" );
	exit( 1 );
}

$data  = $response->get_data();
$items = $data['result']['structuredContent']['items'] ?? array();
$count = 0;
foreach ( $items as $item ) {
	if ( ! is_array( $item ) || 'rest' !== ( $item['contract'] ?? '' ) ) {
		continue;
	}
	$route = (string) ( $item['route'] ?? '' );
	if ( false === strpos( $route, 'cms_weekend_feature' ) ) {
		continue;
	}
	++$count;
	echo 'weekend-route method=' . (string) ( $item['method'] ?? '' ) . ' route=' . $route . ' bridge=' . (string) ( $item['bridge'] ?? '' ) . "\n";
}

echo 'weekend-route-count=' . $count . "\n";
exit( 0 );
