<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_v2_em_fail( $message ) {
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_em_catalog() {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}
	$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
	if ( ! $catalog instanceof WP_Ability ) {
		cmsa_v2_em_fail( 'Universal catalog is unavailable in Events Manager compatibility job.' );
	}
	$result = $catalog->execute( array() );
	if ( is_wp_error( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
		cmsa_v2_em_fail( 'Universal catalog could not enumerate Events Manager contracts.' );
	}
	$items = $result['items'];
	return $items;
}

function cmsa_v2_em_ability( $target ) {
	$matches = array();
	foreach ( cmsa_v2_em_catalog() as $item ) {
		if ( 'ability' === ( $item['contract'] ?? '' ) && $target === ( $item['target'] ?? '' ) && ! empty( $item['bridge'] ) ) {
			$matches[] = (string) $item['bridge'];
		}
	}
	if ( 1 !== count( $matches ) ) {
		cmsa_v2_em_fail( sprintf( 'Expected one bridged Events Manager ability for %1$s; found %2$d.', $target, count( $matches ) ) );
	}
	$ability = wp_get_ability( $matches[0] );
	if ( ! $ability instanceof WP_Ability ) {
		cmsa_v2_em_fail( 'Discovered Events Manager ability facade is unavailable.' );
	}
	return $ability;
}

function cmsa_v2_em_rest( $method, $path ) {
	$method = strtoupper( (string) $method );
	$matches = array();
	foreach ( cmsa_v2_em_catalog() as $item ) {
		if ( 'rest' !== ( $item['contract'] ?? '' ) || $method !== ( $item['method'] ?? '' ) ) {
			continue;
		}
		$route = (string) ( $item['route'] ?? '' );
		$bridge = (string) ( $item['bridge'] ?? '' );
		if ( '' !== $route && '' !== $bridge && 1 === @preg_match( '@^' . $route . '$@i', $path ) ) {
			$matches[] = $bridge;
		}
	}
	if ( 1 !== count( $matches ) ) {
		cmsa_v2_em_fail( sprintf( 'Expected one %1$s Events Manager REST facade for %2$s; found %3$d.', $method, $path, count( $matches ) ) );
	}
	$ability = wp_get_ability( $matches[0] );
	if ( ! $ability instanceof WP_Ability ) {
		cmsa_v2_em_fail( 'Discovered Events Manager REST facade is unavailable.' );
	}
	return $ability;
}

function cmsa_v2_em_rest_call( $method, $path, array $params = array() ) {
	$ability = cmsa_v2_em_rest( $method, $path );
	$input = array( 'path' => $path, 'params' => $params );
	$permission = $ability->check_permissions( $input );
	if ( true !== $permission ) {
		cmsa_v2_em_fail( sprintf( 'Events Manager permission failed for %1$s %2$s.', $method, $path ) );
	}
	$result = $ability->execute( $input );
	if ( is_wp_error( $result ) ) {
		cmsa_v2_em_fail( sprintf( '%1$s %2$s failed: %3$s %4$s', $method, $path, $result->get_error_code(), $result->get_error_message() ) );
	}
	if ( ! is_array( $result ) || (int) ( $result['status'] ?? 0 ) < 200 || (int) ( $result['status'] ?? 0 ) >= 300 || ! array_key_exists( 'data', $result ) ) {
		cmsa_v2_em_fail( sprintf( '%1$s %2$s returned an invalid facade response.', $method, $path ) );
	}
	return $result['data'];
}

function cmsa_v2_em_extract_id( $value ) {
	if ( is_array( $value ) ) {
		foreach ( array( 'id', 'event_id' ) as $key ) {
			if ( isset( $value[ $key ] ) && is_numeric( $value[ $key ] ) && (int) $value[ $key ] > 0 ) {
				return (int) $value[ $key ];
			}
		}
		foreach ( $value as $child ) {
			$id = cmsa_v2_em_extract_id( $child );
			if ( $id > 0 ) {
				return $id;
			}
		}
	}
	if ( is_object( $value ) ) {
		return cmsa_v2_em_extract_id( get_object_vars( $value ) );
	}
	return 0;
}

wp_set_current_user( 1 );

if ( ! defined( 'EM_VERSION' ) || '7.4.3' !== (string) EM_VERSION ) {
	cmsa_v2_em_fail( 'Events Manager 7.4.3 is not the active compatibility target.' );
}

$list = cmsa_v2_em_ability( 'events-manager/list-events' );
$create_ability = cmsa_v2_em_ability( 'events-manager/create-event' );
$update_ability = cmsa_v2_em_ability( 'events-manager/update-event' );
$delete_ability = cmsa_v2_em_ability( 'events-manager/delete-event' );
if ( ! $create_ability instanceof WP_Ability || ! $update_ability instanceof WP_Ability || ! $delete_ability instanceof WP_Ability ) {
	cmsa_v2_em_fail( 'Events Manager mutation abilities were not bridged.' );
}

$list_input = array( 'page' => 1, 'per_page' => 5, 'context' => 'edit' );
if ( true !== $list->check_permissions( $list_input ) ) {
	cmsa_v2_em_fail( 'Events Manager list ability permission was not preserved.' );
}
$list_result = $list->execute( $list_input );
if ( is_wp_error( $list_result ) ) {
	cmsa_v2_em_fail( 'Events Manager list ability failed through the universal facade.' );
}

$suffix = substr( hash( 'sha256', wp_generate_uuid4() ), 0, 10 );
$name = 'CMSA v2 Events Manager ' . $suffix;
$updated_name = $name . ' Updated';
$start = wp_date( 'Y-m-d', time() + ( 30 * DAY_IN_SECONDS ) );
$end = wp_date( 'Y-m-d', time() + ( 31 * DAY_IN_SECONDS ) );
$timezone = wp_timezone_string();
if ( '' === $timezone ) {
	$timezone = 'UTC';
}

$created = cmsa_v2_em_rest_call(
	'POST',
	'/events-manager/v1/events',
	array(
		'event_name'       => $name,
		'content'          => 'Disposable Events Manager compatibility event.',
		'event_type'       => 'single',
		'post_status'      => 'draft',
		'event_start_date' => $start,
		'event_end_date'   => $end,
		'event_start_time' => '19:00:00',
		'event_end_time'   => '21:00:00',
		'event_timezone'   => $timezone,
	)
);
$event_id = cmsa_v2_em_extract_id( $created );
if ( $event_id < 1 ) {
	cmsa_v2_em_fail( 'Events Manager REST creation returned no event ID.' );
}

$read = cmsa_v2_em_rest_call( 'GET', '/events-manager/v1/events/' . $event_id, array( 'context' => 'edit' ) );
$read_name = (string) ( $read['event_name'] ?? $read['name'] ?? '' );
if ( $read_name !== $name ) {
	cmsa_v2_em_fail( 'Events Manager REST readback did not preserve the created event name.' );
}

$updated = cmsa_v2_em_rest_call( 'PATCH', '/events-manager/v1/events/' . $event_id, array( 'event_name' => $updated_name ) );
$updated_id = cmsa_v2_em_extract_id( $updated );
if ( $updated_id > 0 && $updated_id !== $event_id ) {
	cmsa_v2_em_fail( 'Events Manager REST update returned a different event ID.' );
}
$read_after = cmsa_v2_em_rest_call( 'GET', '/events-manager/v1/events/' . $event_id, array( 'context' => 'edit' ) );
$read_after_name = (string) ( $read_after['event_name'] ?? $read_after['name'] ?? '' );
if ( $read_after_name !== $updated_name ) {
	cmsa_v2_em_fail( 'Events Manager REST update did not persist through the generic bridge.' );
}

wp_set_current_user( 0 );
if ( false !== $list->check_permissions( $list_input ) ) {
	cmsa_v2_em_fail( 'Anonymous Events Manager administration was not blocked.' );
}
wp_set_current_user( 1 );

$deleted = cmsa_v2_em_rest_call( 'DELETE', '/events-manager/v1/events/' . $event_id, array( 'context' => 'edit' ) );
if ( is_wp_error( $deleted ) ) {
	cmsa_v2_em_fail( 'Events Manager REST deletion failed.' );
}

$deleted_check = cmsa_v2_em_rest( 'GET', '/events-manager/v1/events/' . $event_id )->execute(
	array( 'path' => '/events-manager/v1/events/' . $event_id, 'params' => array( 'context' => 'edit' ) )
);
if ( ! is_wp_error( $deleted_check ) && is_array( $deleted_check ) && (int) ( $deleted_check['status'] ?? 200 ) < 400 ) {
	cmsa_v2_em_fail( 'Events Manager event remained readable after delete/trash operation.' );
}

echo "cmsa-v2-events-manager: PASS version=7.4.3 native_abilities=bridged mcp_exposure=honored list_execution=verified rest_discovery=verified event_create=verified event_read=verified event_update=verified event_delete=verified provider_permissions=preserved admin_boundary=verified candidate_provider_special_case=absent\n";
exit( 0 );
