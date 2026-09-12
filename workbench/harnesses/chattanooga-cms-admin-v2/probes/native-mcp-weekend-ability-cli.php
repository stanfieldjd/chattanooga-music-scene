<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_weekend_mcp_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_weekend_mcp_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_weekend_mcp_fail( $message );
	}
}

function cmsa_weekend_mcp_call( $tool, array $arguments ) {
	static $id = 900;
	++$id;

	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', 'tools/call' );
	$request->set_header( 'Mcp-Name', $tool );
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => $tool,
					'arguments' => $arguments,
					'_meta'     => array(
						'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
						'io.modelcontextprotocol/clientInfo'      => array(
							'name'    => 'cmsa-weekend-ability-probe',
							'version' => '1.0.0',
						),
					),
				),
			)
		)
	);

	$response = rest_do_request( $request );
	cmsa_weekend_mcp_assert( $response instanceof WP_REST_Response && 200 === $response->get_status(), 'Native MCP tool call failed at the transport layer: ' . $tool );
	$data = $response->get_data();
	$result = is_array( $data ) ? ( $data['result'] ?? null ) : null;
	cmsa_weekend_mcp_assert( is_array( $result ) && false === ( $result['isError'] ?? true ), 'Native MCP tool returned an error: ' . $tool );
	return $result;
}

function cmsa_weekend_mcp_catalog() {
	$result = cmsa_weekend_mcp_call( 'cmsa.catalog', array() );
	$catalog = $result['structuredContent'] ?? null;
	cmsa_weekend_mcp_assert( is_array( $catalog ) && isset( $catalog['items'] ) && is_array( $catalog['items'] ), 'MCP catalog is unavailable.' );
	return $catalog['items'];
}

function cmsa_weekend_mcp_find_ability( array $catalog, $target ) {
	$matches = array();
	foreach ( $catalog as $item ) {
		if ( is_array( $item ) && 'ability' === ( $item['contract'] ?? '' ) && $target === ( $item['target'] ?? '' ) && ! empty( $item['bridge'] ) ) {
			$matches[] = $item;
		}
	}
	cmsa_weekend_mcp_assert( 1 === count( $matches ), sprintf( 'Expected one ability bridge for %s; found %d.', $target, count( $matches ) ) );
	return $matches[0];
}

function cmsa_weekend_mcp_find_rest( array $catalog, $method, $path ) {
	$method = strtoupper( (string) $method );
	$matches = array();
	foreach ( $catalog as $item ) {
		if ( ! is_array( $item ) || 'rest' !== ( $item['contract'] ?? '' ) || $method !== ( $item['method'] ?? '' ) ) {
			continue;
		}
		$route = (string) ( $item['route'] ?? '' );
		if ( '' !== $route && ! empty( $item['bridge'] ) && 1 === @preg_match( '@^' . $route . '$@i', $path ) ) {
			$matches[] = $item;
		}
	}
	cmsa_weekend_mcp_assert( 1 === count( $matches ), sprintf( 'Expected one REST bridge for %s %s; found %d.', $method, $path, count( $matches ) ) );
	return $matches[0];
}

function cmsa_weekend_mcp_gateway( array $item, array $input ) {
	$tool = true === ( $item['annotations']['readonly'] ?? null ) ? 'cmsa.read-bridge' : 'cmsa.write-bridge';
	$result = cmsa_weekend_mcp_call(
		$tool,
		array(
			'bridge' => (string) $item['bridge'],
			'input'  => $input,
		)
	);
	$structured = $result['structuredContent'] ?? null;
	cmsa_weekend_mcp_assert( is_array( $structured ) && array_key_exists( 'result', $structured ), 'Bridge gateway returned no target result.' );
	return $structured['result'];
}

function cmsa_weekend_mcp_extract_id( $value ) {
	if ( is_array( $value ) ) {
		foreach ( array( 'id', 'event_id' ) as $key ) {
			if ( isset( $value[ $key ] ) && is_numeric( $value[ $key ] ) && (int) $value[ $key ] > 0 ) {
				return (int) $value[ $key ];
			}
		}
		foreach ( $value as $child ) {
			$id = cmsa_weekend_mcp_extract_id( $child );
			if ( $id > 0 ) {
				return $id;
			}
		}
	}
	return 0;
}

wp_set_current_user( 1 );

cmsa_weekend_mcp_assert( class_exists( 'CMS_Weekend_Abilities' ), 'Weekend Feature ability provider is not loaded.' );

$catalog = cmsa_weekend_mcp_catalog();
$generate_item = cmsa_weekend_mcp_find_ability( $catalog, 'chattanooga-music-scene/generate-weekend-feature' );
cmsa_weekend_mcp_assert( false === ( $generate_item['annotations']['readonly'] ?? true ), 'Weekend generator ability was not classified as mutating.' );

$timezone = wp_timezone();
$now = new DateTimeImmutable( 'now', $timezone );
$friday = $now->modify( 'friday this week' )->setTime( 0, 0, 0 );
$sunday = $friday->modify( '+2 days' )->setTime( 23, 59, 59 );
if ( $now > $sunday ) {
	$friday = $friday->modify( '+1 week' );
}

$event_name = 'CMSA MCP Weekend Ability ' . substr( hash( 'sha256', wp_generate_uuid4() ), 0, 10 );
$create_item = cmsa_weekend_mcp_find_rest( $catalog, 'POST', '/events-manager/v1/events' );
$created = cmsa_weekend_mcp_gateway(
	$create_item,
	array(
		'path'   => '/events-manager/v1/events',
		'params' => array(
			'event_name'       => $event_name,
			'content'          => 'Disposable event for Weekend Feature MCP ability verification.',
			'event_type'       => 'single',
			'post_status'      => 'publish',
			'event_start_date' => $friday->format( 'Y-m-d' ),
			'event_end_date'   => $friday->format( 'Y-m-d' ),
			'event_start_time' => '20:00:00',
			'event_end_time'   => '22:00:00',
			'event_timezone'   => wp_timezone_string() ?: 'UTC',
		),
	)
);
$event_id = cmsa_weekend_mcp_extract_id( $created['data'] ?? array() );
cmsa_weekend_mcp_assert( $event_id > 0, 'MCP could not create the disposable Weekend Feature event.' );

$generated = cmsa_weekend_mcp_gateway( $generate_item, array( 'status' => 'draft' ) );
cmsa_weekend_mcp_assert( is_array( $generated ), 'Weekend Feature ability returned no result through MCP.' );
$feature_id = (int) ( $generated['post_id'] ?? 0 );
cmsa_weekend_mcp_assert( $feature_id > 0, 'Weekend Feature ability returned no post ID through MCP.' );
cmsa_weekend_mcp_assert( (int) ( $generated['event_count'] ?? 0 ) >= 1, 'Weekend Feature ability did not consume the MCP-created event.' );

$feature = get_post( $feature_id );
cmsa_weekend_mcp_assert( $feature instanceof WP_Post && 'draft' === $feature->post_status, 'Weekend Feature MCP ability did not create a draft.' );
cmsa_weekend_mcp_assert( false !== strpos( $feature->post_content, $event_name ), 'Weekend Feature MCP ability output does not contain the MCP-created event.' );

$event_path = '/events-manager/v1/events/' . $event_id;
$delete_item = cmsa_weekend_mcp_find_rest( $catalog, 'DELETE', $event_path );
$deleted = cmsa_weekend_mcp_gateway( $delete_item, array( 'path' => $event_path, 'params' => array( 'context' => 'edit' ) ) );
cmsa_weekend_mcp_assert( is_array( $deleted ) && (int) ( $deleted['status'] ?? 0 ) >= 200 && (int) ( $deleted['status'] ?? 0 ) < 300, 'MCP event rollback failed.' );

wp_delete_post( $feature_id, true );
cmsa_weekend_mcp_assert( false === get_post_status( $feature_id ), 'Weekend Feature rollback failed.' );

echo "cmsa-native-mcp-weekend-ability: PASS ability_discovery=verified mcp_generator_call=verified published_event_consumption=verified draft_generation=verified event_cleanup=verified feature_cleanup=verified\n";
exit( 0 );
