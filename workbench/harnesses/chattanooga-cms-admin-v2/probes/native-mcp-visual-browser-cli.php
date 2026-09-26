<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_visual_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_visual_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_visual_fail( $message );
	}
}

function cmsa_visual_mcp_call( $name, array $arguments, $id ) {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', 'tools/call' );
	$request->set_header( 'Mcp-Name', $name );
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => $name,
					'arguments' => $arguments,
					'_meta'     => array(
						'io.modelcontextprotocol/protocolVersion'   => '2026-07-28',
						'io.modelcontextprotocol/clientCapabilities' => array(),
						'io.modelcontextprotocol/clientInfo'         => array(
							'name'    => 'cmsa-visual-browser-probe',
							'version' => '1.0.0',
						),
					),
				),
			)
		)
	);
	return rest_do_request( $request );
}

function cmsa_visual_tools_list() {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', 'tools/list' );
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => 880,
				'method'  => 'tools/list',
				'params'  => array(
					'_meta' => array(
						'io.modelcontextprotocol/protocolVersion'   => '2026-07-28',
						'io.modelcontextprotocol/clientCapabilities' => array(),
						'io.modelcontextprotocol/clientInfo'         => array( 'name' => 'cmsa-visual-browser-probe', 'version' => '1.0.0' ),
					),
				),
			)
		)
	);
	$response = rest_do_request( $request );
	cmsa_visual_assert( 200 === $response->get_status(), 'tools/list did not return HTTP 200.' );
	return $response->get_data()['result']['tools'] ?? array();
}

function cmsa_visual_catalog_item( $target ) {
	$cursor = 0;
	$snapshot = '';
	for ( $page = 0; $page < 100; ++$page ) {
		$input = array( 'cursor' => $cursor, 'limit' => 100 );
		if ( '' !== $snapshot ) {
			$input['snapshot'] = $snapshot;
		}
		$catalog = CUA_Ability_Bridge::catalog( $input );
		cmsa_visual_assert( ! is_wp_error( $catalog ), 'Catalog lookup failed for ' . $target . '.' );
		if ( '' === $snapshot ) {
			$snapshot = (string) ( $catalog['snapshot'] ?? '' );
		}
		foreach ( $catalog['items'] ?? array() as $item ) {
			if ( is_array( $item ) && 'ability' === ( $item['contract'] ?? '' ) && $target === ( $item['target'] ?? '' ) ) {
				return $item;
			}
		}
		$next = $catalog['nextCursor'] ?? null;
		if ( null === $next ) {
			break;
		}
		cmsa_visual_assert( is_numeric( $next ) && (int) $next > $cursor, 'Catalog cursor failed to advance.' );
		$cursor = (int) $next;
	}
	cmsa_visual_fail( 'Catalog did not expose ' . $target . '.' );
}

function cmsa_visual_result( WP_REST_Response $response, $label, $expect_image = true ) {
	cmsa_visual_assert( 200 === $response->get_status(), $label . ' did not return HTTP 200.' );
	$data = $response->get_data();
	cmsa_visual_assert( is_array( $data ), $label . ' returned a malformed response.' );
	$result = $data['result'] ?? array();
	cmsa_visual_assert( false === ( $result['isError'] ?? true ), $label . ' returned an MCP tool error: ' . wp_json_encode( $result ) );
	$structured = $result['structuredContent']['result'] ?? null;
	cmsa_visual_assert( is_array( $structured ), $label . ' returned no bridge result.' );
	cmsa_visual_assert( ! array_key_exists( '__mcp_visual', $structured ), $label . ' leaked the internal image transport field.' );
	if ( $expect_image ) {
		$image = null;
		foreach ( $result['content'] ?? array() as $content ) {
			if ( is_array( $content ) && 'image' === ( $content['type'] ?? '' ) ) {
				$image = $content;
				break;
			}
		}
		cmsa_visual_assert( is_array( $image ), $label . ' returned no MCP image content.' );
		cmsa_visual_assert( 'image/png' === ( $image['mimeType'] ?? '' ), $label . ' returned the wrong image MIME type.' );
		$bytes = base64_decode( (string) ( $image['data'] ?? '' ), true );
		cmsa_visual_assert( false !== $bytes && strlen( $bytes ) > 8 && "\x89PNG\r\n\x1a\n" === substr( $bytes, 0, 8 ), $label . ' did not return a valid PNG.' );
	}
	return $structured;
}

wp_set_current_user( 1 );

cmsa_visual_assert( defined( 'CUA_VERSION' ) && '1.2.51' === CUA_VERSION, 'Admin MCP 1.2.51 did not load.' );
cmsa_visual_assert( class_exists( 'CUA_Visual_Browser' ), 'Visual browser ability layer did not load.' );
cmsa_visual_assert( class_exists( 'CUA_Visual_Browser_Runtime' ), 'Visual browser runtime did not load.' );
cmsa_visual_assert( class_exists( 'CUA_Visual_CDP' ), 'Visual CDP transport did not load.' );

$tools = cmsa_visual_tools_list();
$tool_names = array_map( static function ( $tool ) { return (string) ( $tool['name'] ?? '' ); }, $tools );
cmsa_visual_assert(
	array( 'cmsa.discovery', 'cmsa.stability-check', 'cmsa.read-bridge', 'cmsa.write-bridge' ) === $tool_names,
	'Visual browser work changed the stable four-tool MCP ABI: ' . wp_json_encode( $tool_names )
);

$capture_item = cmsa_visual_catalog_item( 'chattanooga-cms-admin/capture-page-screenshot' );
$session_item = cmsa_visual_catalog_item( 'chattanooga-cms-admin/browser-session' );
foreach ( array( $capture_item, $session_item ) as $item ) {
	cmsa_visual_assert( true === ( $item['annotations']['readonly'] ?? null ), 'Visual ability is not cataloged read-only.' );
	cmsa_visual_assert( false === ( $item['annotations']['destructive'] ?? null ), 'Visual ability is incorrectly cataloged destructive.' );
}
$session_actions = $session_item['inputSchema']['properties']['action']['enum'] ?? array();
cmsa_visual_assert( ! in_array( 'click', $session_actions, true ) && ! in_array( 'submit', $session_actions, true ), 'Visual session exposed click or submit behavior.' );

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Admin MCP Visual Probe',
		'post_name'    => 'admin-mcp-visual-probe',
		'post_content' => '<div style="height:2600px"><h1>Admin MCP Visual Probe</h1><p>Rendered browser pixel acceptance fixture.</p></div>',
	),
	true
);
cmsa_visual_assert( ! is_wp_error( $page_id ) && $page_id > 0, 'Could not create disposable visual test page.' );

$session_id = '';
try {
	$fallback = cmsa_visual_mcp_call(
		'cmsa.read-bridge',
		array(
			'bridge' => (string) $capture_item['bridge'],
			'input'  => array( 'path' => '/admin-mcp-visual-probe/', 'width' => 900, 'height' => 600, 'wait_ms' => 300 ),
		),
		881
	);
	$fallback_result = cmsa_visual_result( $fallback, 'Fallback screenshot' );
	$fallback_meta = $fallback_result['metadata'] ?? array();
	cmsa_visual_assert( 'fallback' === ( $fallback_meta['mode'] ?? '' ), 'Fallback screenshot returned the wrong mode.' );
	cmsa_visual_assert( 900 === (int) ( $fallback_meta['width'] ?? 0 ) && 600 === (int) ( $fallback_meta['height'] ?? 0 ), 'Fallback screenshot returned the wrong viewport metadata.' );

	$full = cmsa_visual_mcp_call(
		'cmsa.read-bridge',
		array(
			'bridge' => (string) $capture_item['bridge'],
			'input'  => array( 'path' => '/admin-mcp-visual-probe/', 'width' => 900, 'height' => 600, 'full_page' => true, 'wait_ms' => 300 ),
		),
		882
	);
	$full_result = cmsa_visual_result( $full, 'Full-page screenshot' );
	$full_meta = $full_result['metadata'] ?? array();
	cmsa_visual_assert( true === ( $full_meta['full_page'] ?? false ), 'Full-page screenshot did not report full_page=true.' );
	cmsa_visual_assert( (int) ( $full_meta['capture_height'] ?? 0 ) > 600, 'Full-page screenshot did not exceed the viewport height.' );

	$opened = cmsa_visual_mcp_call(
		'cmsa.read-bridge',
		array(
			'bridge' => (string) $session_item['bridge'],
			'input'  => array( 'action' => 'open', 'path' => '/admin-mcp-visual-probe/', 'width' => 800, 'height' => 500, 'wait_ms' => 300 ),
		),
		883
	);
	$opened_result = cmsa_visual_result( $opened, 'Live browser open' );
	$opened_meta = $opened_result['metadata'] ?? array();
	$session_id = (string) ( $opened_meta['session_id'] ?? '' );
	cmsa_visual_assert( 1 === preg_match( '/^[a-f0-9]{32}$/', $session_id ), 'Live browser did not return a valid session ID.' );
	cmsa_visual_assert( 'live' === ( $opened_meta['mode'] ?? '' ), 'Live browser returned the wrong mode.' );

	$scrolled = cmsa_visual_mcp_call(
		'cmsa.read-bridge',
		array( 'bridge' => (string) $session_item['bridge'], 'input' => array( 'action' => 'scroll', 'session_id' => $session_id, 'scroll_y' => 700, 'wait_ms' => 100 ) ),
		884
	);
	cmsa_visual_result( $scrolled, 'Live browser scroll' );

	$resized = cmsa_visual_mcp_call(
		'cmsa.read-bridge',
		array( 'bridge' => (string) $session_item['bridge'], 'input' => array( 'action' => 'resize', 'session_id' => $session_id, 'width' => 1024, 'height' => 700 ) ),
		885
	);
	$resized_result = cmsa_visual_result( $resized, 'Live browser resize' );
	$resized_meta = $resized_result['metadata'] ?? array();
	cmsa_visual_assert( 1024 === (int) ( $resized_meta['width'] ?? 0 ) && 700 === (int) ( $resized_meta['height'] ?? 0 ), 'Live browser resize metadata did not update.' );

	$refreshed = cmsa_visual_mcp_call(
		'cmsa.read-bridge',
		array( 'bridge' => (string) $session_item['bridge'], 'input' => array( 'action' => 'refresh', 'session_id' => $session_id, 'wait_ms' => 200 ) ),
		886
	);
	cmsa_visual_result( $refreshed, 'Live browser refresh' );

	$closed = cmsa_visual_mcp_call(
		'cmsa.read-bridge',
		array( 'bridge' => (string) $session_item['bridge'], 'input' => array( 'action' => 'close', 'session_id' => $session_id ) ),
		887
	);
	$closed_result = cmsa_visual_result( $closed, 'Live browser close', false );
	cmsa_visual_assert( true === ( $closed_result['closed'] ?? false ), 'Live browser close did not report closed=true.' );
	$session_id = '';

	$cross_origin = cmsa_visual_mcp_call(
		'cmsa.read-bridge',
		array(
			'bridge' => (string) $capture_item['bridge'],
			'input'  => array( 'url' => 'https://example.com/' ),
		),
		888
	);
	cmsa_visual_assert( 200 === $cross_origin->get_status(), 'Cross-origin rejection did not return an MCP CallToolResult.' );
	$cross_data = $cross_origin->get_data()['result'] ?? array();
	cmsa_visual_assert( true === ( $cross_data['isError'] ?? false ), 'Cross-origin visual request was not rejected.' );
	foreach ( $cross_data['content'] ?? array() as $content ) {
		cmsa_visual_assert( 'image' !== ( $content['type'] ?? '' ), 'Cross-origin rejection returned image content.' );
	}
} finally {
	if ( '' !== $session_id ) {
		$session = CUA_Visual_Browser_Runtime::load( $session_id );
		if ( is_array( $session ) ) {
			CUA_Visual_Browser_Runtime::close( $session );
			CUA_Visual_Browser_Runtime::delete_record( $session_id );
		}
	}
	wp_delete_post( $page_id, true );
}

echo "cmsa-native-mcp-visual-browser: PASS fallback_png=verified full_page=verified live_session=verified scroll=verified resize=verified refresh=verified close=verified cross_origin=blocked stable_gateway=preserved\n";
exit( 0 );
