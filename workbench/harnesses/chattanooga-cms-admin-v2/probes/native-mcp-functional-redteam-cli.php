<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_mcp_redteam_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_mcp_redteam_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_mcp_redteam_fail( $message );
	}
}

function cmsa_mcp_redteam_call( $method, array $params, $tool_name = '' ) {
	static $id = 700;
	++$id;

	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', $method );
	if ( '' !== $tool_name ) {
		$request->set_header( 'Mcp-Name', $tool_name );
	}

	$params['_meta'] = array(
		'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
		'io.modelcontextprotocol/clientInfo'      => array(
			'name'    => 'cmsa-functional-redteam',
			'version' => '1.0.0',
		),
	);

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

	$response = rest_do_request( $request );
	cmsa_mcp_redteam_assert( $response instanceof WP_REST_Response, 'Native MCP did not return a REST response.' );
	cmsa_mcp_redteam_assert( 200 === $response->get_status(), sprintf( 'Native MCP %s returned HTTP %d.', $method, $response->get_status() ) );

	$data = $response->get_data();
	cmsa_mcp_redteam_assert( is_array( $data ) && empty( $data['error'] ), 'Native MCP returned a JSON-RPC error.' );
	cmsa_mcp_redteam_assert( isset( $data['result'] ) && is_array( $data['result'] ), 'Native MCP returned no structured result.' );
	return $data['result'];
}

function cmsa_mcp_redteam_tool( $name, array $arguments = array() ) {
	$result = cmsa_mcp_redteam_call(
		'tools/call',
		array(
			'name'      => $name,
			'arguments' => $arguments,
		),
		$name
	);

	cmsa_mcp_redteam_assert( false === ( $result['isError'] ?? true ), 'MCP tool failed: ' . $name );
	return $result;
}

function cmsa_mcp_redteam_catalog() {
	$result = cmsa_mcp_redteam_tool( 'cmsa.catalog', array() );
	$catalog = $result['structuredContent'] ?? null;
	cmsa_mcp_redteam_assert( is_array( $catalog ) && isset( $catalog['items'] ) && is_array( $catalog['items'] ), 'MCP catalog returned no items.' );
	return $catalog['items'];
}

function cmsa_mcp_redteam_find_ability( array $catalog, $target ) {
	$matches = array();
	foreach ( $catalog as $item ) {
		if ( is_array( $item ) && 'ability' === ( $item['contract'] ?? '' ) && $target === ( $item['target'] ?? '' ) && ! empty( $item['bridge'] ) ) {
			$matches[] = $item;
		}
	}
	cmsa_mcp_redteam_assert( 1 === count( $matches ), sprintf( 'Expected one ability bridge for %s; found %d.', $target, count( $matches ) ) );
	return $matches[0];
}

function cmsa_mcp_redteam_find_rest( array $catalog, $method, $path ) {
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
	cmsa_mcp_redteam_assert( 1 === count( $matches ), sprintf( 'Expected one REST bridge for %s %s; found %d.', $method, $path, count( $matches ) ) );
	return $matches[0];
}

function cmsa_mcp_redteam_gateway( array $item, array $input = array() ) {
	$readonly = true === ( $item['annotations']['readonly'] ?? null );
	$name = $readonly ? 'cmsa.read-bridge' : 'cmsa.write-bridge';
	$arguments = array( 'bridge' => (string) $item['bridge'] );
	if ( ! empty( $input ) ) {
		$arguments['input'] = $input;
	}
	$result = cmsa_mcp_redteam_tool( $name, $arguments );
	$structured = $result['structuredContent'] ?? null;
	cmsa_mcp_redteam_assert( is_array( $structured ) && array_key_exists( 'result', $structured ), 'Bridge gateway returned no target result.' );
	return $structured['result'];
}

function cmsa_mcp_redteam_extract_id( $value ) {
	if ( is_array( $value ) ) {
		foreach ( array( 'id', 'event_id' ) as $key ) {
			if ( isset( $value[ $key ] ) && is_numeric( $value[ $key ] ) && (int) $value[ $key ] > 0 ) {
				return (int) $value[ $key ];
			}
		}
		foreach ( $value as $child ) {
			$id = cmsa_mcp_redteam_extract_id( $child );
			if ( $id > 0 ) {
				return $id;
			}
		}
	}
	if ( is_object( $value ) ) {
		return cmsa_mcp_redteam_extract_id( get_object_vars( $value ) );
	}
	return 0;
}

wp_set_current_user( 1 );

cmsa_mcp_redteam_assert( defined( 'CUA_VERSION' ) && '1.1.0' === CUA_VERSION, 'Chattanooga CMS Admin 1.1.0 is not active.' );
cmsa_mcp_redteam_assert( defined( 'EM_VERSION' ) && '7.4.3' === (string) EM_VERSION, 'Events Manager 7.4.3 is not active.' );
cmsa_mcp_redteam_assert( defined( 'CMS_CORE_VERSION' ) && '0.2.1' === CMS_CORE_VERSION, 'Weekend Feature 0.2.1 is not active.' );
cmsa_mcp_redteam_assert( class_exists( 'CMS_Weekend_Posts' ), 'Weekend Feature generator is unavailable.' );

$tools_result = cmsa_mcp_redteam_call( 'tools/list', array() );
$tool_names = array();
foreach ( $tools_result['tools'] ?? array() as $tool ) {
	if ( is_array( $tool ) && isset( $tool['name'] ) ) {
		$tool_names[] = (string) $tool['name'];
	}
}
foreach ( array( 'cmsa.catalog', 'cmsa.read-bridge', 'cmsa.write-bridge' ) as $required_tool ) {
	cmsa_mcp_redteam_assert( in_array( $required_tool, $tool_names, true ), 'Required MCP tool is missing: ' . $required_tool );
}

$catalog = cmsa_mcp_redteam_catalog();

// Functional test 1: create a real Events Manager event through the MCP write gateway.
$timezone = wp_timezone();
$now = new DateTimeImmutable( 'now', $timezone );
$friday = $now->modify( 'friday this week' )->setTime( 0, 0, 0 );
$sunday = $friday->modify( '+2 days' )->setTime( 23, 59, 59 );
if ( $now > $sunday ) {
	$friday = $friday->modify( '+1 week' );
}

$suffix = substr( hash( 'sha256', wp_generate_uuid4() ), 0, 10 );
$event_name = 'CMSA MCP Functional Red Team ' . $suffix;
$event_updated_name = $event_name . ' Updated';
$event_post_id = 0;
$event_id = 0;
$feature_id = 0;

$create_item = cmsa_mcp_redteam_find_rest( $catalog, 'POST', '/events-manager/v1/events' );
$created = cmsa_mcp_redteam_gateway(
	$create_item,
	array(
		'path'   => '/events-manager/v1/events',
		'params' => array(
			'event_name'       => $event_name,
			'content'          => 'Disposable MCP functional red-team event.',
			'event_type'       => 'single',
			'post_status'      => 'publish',
			'event_start_date' => $friday->format( 'Y-m-d' ),
			'event_end_date'   => $friday->format( 'Y-m-d' ),
			'event_start_time' => '19:00:00',
			'event_end_time'   => '21:00:00',
			'event_timezone'   => wp_timezone_string() ?: 'UTC',
		),
	)
);
cmsa_mcp_redteam_assert( is_array( $created ) && (int) ( $created['status'] ?? 0 ) >= 200 && (int) ( $created['status'] ?? 0 ) < 300, 'Events Manager create did not return success through MCP.' );
$event_id = cmsa_mcp_redteam_extract_id( $created['data'] ?? array() );
cmsa_mcp_redteam_assert( $event_id > 0, 'Events Manager create returned no event ID through MCP.' );

$read_path = '/events-manager/v1/events/' . $event_id;
$read_item = cmsa_mcp_redteam_find_rest( $catalog, 'GET', $read_path );
$read = cmsa_mcp_redteam_gateway( $read_item, array( 'path' => $read_path, 'params' => array( 'context' => 'edit' ) ) );
cmsa_mcp_redteam_assert( is_array( $read ) && 200 === (int) ( $read['status'] ?? 0 ), 'Events Manager readback failed through MCP.' );
$event_data = $read['data'] ?? array();
cmsa_mcp_redteam_assert( $event_name === (string) ( $event_data['event_name'] ?? $event_data['name'] ?? '' ), 'Events Manager MCP readback changed the event identity.' );
$event_post_id = isset( $event_data['post_id'] ) ? (int) $event_data['post_id'] : 0;
cmsa_mcp_redteam_assert( $event_post_id > 0, 'Events Manager MCP readback returned no event post ID.' );

$patch_item = cmsa_mcp_redteam_find_rest( $catalog, 'PATCH', $read_path );
$patched = cmsa_mcp_redteam_gateway( $patch_item, array( 'path' => $read_path, 'params' => array( 'event_name' => $event_updated_name ) ) );
cmsa_mcp_redteam_assert( is_array( $patched ) && (int) ( $patched['status'] ?? 0 ) >= 200 && (int) ( $patched['status'] ?? 0 ) < 300, 'Events Manager update failed through MCP.' );
$read_after = cmsa_mcp_redteam_gateway( $read_item, array( 'path' => $read_path, 'params' => array( 'context' => 'edit' ) ) );
$read_after_data = $read_after['data'] ?? array();
cmsa_mcp_redteam_assert( $event_updated_name === (string) ( $read_after_data['event_name'] ?? $read_after_data['name'] ?? '' ), 'Events Manager MCP update did not persist.' );

// Functional test 2: exercise Rank Math read and write abilities through the MCP gateways, then restore the mutation.
$get_settings_item = cmsa_mcp_redteam_find_ability( $catalog, 'rank-math/get-settings' );
$set_global_item = cmsa_mcp_redteam_find_ability( $catalog, 'rank-math/set-global-seo-settings' );
$settings_before = cmsa_mcp_redteam_gateway( $get_settings_item, array( 'sections' => array( 'titles' ) ) );
cmsa_mcp_redteam_assert( is_array( $settings_before ) && isset( $settings_before['titles'] ) && array_key_exists( 'title_separator', $settings_before['titles'] ), 'Rank Math settings read failed through MCP.' );
$original_separator = (string) $settings_before['titles']['title_separator'];
$alternate_separator = '|' === $original_separator ? '-' : '|';

cmsa_mcp_redteam_gateway( $set_global_item, array( 'title_separator' => $alternate_separator ) );
$settings_changed = cmsa_mcp_redteam_gateway( $get_settings_item, array( 'sections' => array( 'titles' ) ) );
if ( $alternate_separator !== (string) ( $settings_changed['titles']['title_separator'] ?? '' ) ) {
	cmsa_mcp_redteam_gateway( $set_global_item, array( 'title_separator' => $original_separator ) );
	cmsa_mcp_redteam_fail( 'Rank Math global SEO mutation did not persist through MCP.' );
}
cmsa_mcp_redteam_gateway( $set_global_item, array( 'title_separator' => $original_separator ) );
$settings_restored = cmsa_mcp_redteam_gateway( $get_settings_item, array( 'sections' => array( 'titles' ) ) );
cmsa_mcp_redteam_assert( $original_separator === (string) ( $settings_restored['titles']['title_separator'] ?? '' ), 'Rank Math global SEO rollback failed through MCP.' );

// Functional test 3: verify the real Weekend Feature generator consumes the MCP-created event.
$feature = CMS_Weekend_Posts::instance()->generate( 'draft' );
if ( is_wp_error( $feature ) ) {
	cmsa_mcp_redteam_fail( 'Weekend Feature generation failed after MCP event creation: ' . $feature->get_error_message() );
}
$feature_id = (int) ( $feature['post_id'] ?? 0 );
cmsa_mcp_redteam_assert( $feature_id > 0, 'Weekend Feature generation returned no post ID.' );
cmsa_mcp_redteam_assert( (int) ( $feature['event_count'] ?? 0 ) >= 1, 'Weekend Feature generation did not consume the published MCP event.' );
$feature_post = get_post( $feature_id );
cmsa_mcp_redteam_assert( $feature_post instanceof WP_Post && 'draft' === $feature_post->post_status, 'Weekend Feature generator did not create a draft feature.' );
cmsa_mcp_redteam_assert( false !== strpos( $feature_post->post_content, $event_updated_name ), 'Weekend Feature content does not contain the MCP-updated event.' );

$feature_path = '/wp/v2/cms_weekend_feature/' . $feature_id;
$feature_read_item = cmsa_mcp_redteam_find_rest( $catalog, 'GET', $feature_path );
$feature_read = cmsa_mcp_redteam_gateway( $feature_read_item, array( 'path' => $feature_path, 'params' => array( 'context' => 'edit' ) ) );
cmsa_mcp_redteam_assert( is_array( $feature_read ) && 200 === (int) ( $feature_read['status'] ?? 0 ), 'Weekend Feature REST read failed through MCP.' );
$feature_data = $feature_read['data'] ?? array();
$feature_content = '';
if ( isset( $feature_data['content'] ) && is_array( $feature_data['content'] ) ) {
	$feature_content = (string) ( $feature_data['content']['raw'] ?? $feature_data['content']['rendered'] ?? '' );
}
cmsa_mcp_redteam_assert( false !== strpos( $feature_content, $event_updated_name ), 'Weekend Feature MCP readback does not contain the event.' );

// Roll back all disposable content through the same bridge where possible.
$delete_item = cmsa_mcp_redteam_find_rest( $catalog, 'DELETE', $read_path );
$deleted = cmsa_mcp_redteam_gateway( $delete_item, array( 'path' => $read_path, 'params' => array( 'context' => 'edit' ) ) );
cmsa_mcp_redteam_assert( is_array( $deleted ) && (int) ( $deleted['status'] ?? 0 ) >= 200 && (int) ( $deleted['status'] ?? 0 ) < 300, 'Events Manager cleanup failed through MCP.' );
clean_post_cache( $event_post_id );
$event_status = get_post_status( $event_post_id );
cmsa_mcp_redteam_assert( false === $event_status || 'trash' === $event_status, 'Events Manager disposable event remains after MCP cleanup.' );

wp_delete_post( $feature_id, true );
cmsa_mcp_redteam_assert( false === get_post_status( $feature_id ), 'Disposable Weekend Feature was not removed.' );

wp_set_current_user( 0 );
$anonymous = cmsa_mcp_redteam_call( 'tools/list', array() );
// If execution reaches this line, authorization failed open. The REST permission callback must reject before handle_request.
cmsa_mcp_redteam_fail( 'Anonymous native MCP access was not blocked.' );
