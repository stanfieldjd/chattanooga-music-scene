<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$cmsa_isolation_monitor = array(
	'event_id' => 0,
	'event_token' => '',
	'event_before' => null,
	'location_id' => 0,
	'location_token' => '',
	'location_before' => null,
	'first_change' => null,
);

$cmsa_isolation_shape_event = static function ( CMSA_Events_Manager $reader, $id ) {
	$event = $reader->load_event( (int) $id );
	if ( is_wp_error( $event ) ) {
		return array( 'error' => $event->get_error_code() );
	}
	$post = ! empty( $event->post_id ) ? get_post( (int) $event->post_id ) : null;
	return array(
		'id' => (int) $event->event_id,
		'post_id' => isset( $event->post_id ) ? (int) $event->post_id : 0,
		'name' => (string) $event->event_name,
		'post_status' => $post instanceof WP_Post ? (string) $post->post_status : null,
		'event_status' => isset( $event->event_status ) ? (int) $event->event_status : null,
		'active_status' => isset( $event->event_active_status ) ? (int) $event->event_active_status : null,
		'private' => isset( $event->event_private ) ? (int) $event->event_private : null,
		'archetype' => isset( $event->event_archetype ) ? (string) $event->event_archetype : null,
		'type' => isset( $event->event_type ) ? (string) $event->event_type : null,
		'start_date' => (string) $event->event_start_date,
		'end_date' => (string) $event->event_end_date,
		'start_time' => (string) $event->event_start_time,
		'end_time' => (string) $event->event_end_time,
		'all_day' => ! empty( $event->event_all_day ),
		'timezone' => isset( $event->event_timezone ) ? (string) $event->event_timezone : null,
		'location_id' => isset( $event->location_id ) ? (int) $event->location_id : 0,
		'state_token' => $reader->event_state( $event ),
	);
};

$cmsa_isolation_shape_location = static function ( CMSA_Events_Manager $reader, $id ) {
	$location = $reader->load_location( (int) $id );
	if ( is_wp_error( $location ) ) {
		return array( 'error' => $location->get_error_code() );
	}
	$post = ! empty( $location->post_id ) ? get_post( (int) $location->post_id ) : null;
	return array(
		'id' => (int) $location->location_id,
		'post_id' => isset( $location->post_id ) ? (int) $location->post_id : 0,
		'name' => (string) $location->location_name,
		'post_status' => $post instanceof WP_Post ? (string) $post->post_status : null,
		'address' => (string) $location->location_address,
		'town' => (string) $location->location_town,
		'state' => (string) $location->location_state,
		'postcode' => (string) $location->location_postcode,
		'region' => (string) $location->location_region,
		'country' => (string) $location->location_country,
		'latitude' => isset( $location->location_latitude ) ? (string) $location->location_latitude : null,
		'longitude' => isset( $location->location_longitude ) ? (string) $location->location_longitude : null,
		'state_token' => $reader->location_state( $location ),
	);
};

$cmsa_isolation_check = static function ( $operation ) use ( &$cmsa_isolation_monitor, $cmsa_isolation_shape_event, $cmsa_isolation_shape_location ) {
	if ( null !== $cmsa_isolation_monitor['first_change'] ) {
		return;
	}
	$reader = new CMSA_Events_Manager();
	if ( $cmsa_isolation_monitor['event_id'] > 0 ) {
		$after = $cmsa_isolation_shape_event( $reader, $cmsa_isolation_monitor['event_id'] );
		if ( isset( $after['state_token'] ) && $after['state_token'] !== $cmsa_isolation_monitor['event_token'] ) {
			$cmsa_isolation_monitor['first_change'] = array(
				'object' => 'event',
				'after_operation' => $operation,
				'before' => $cmsa_isolation_monitor['event_before'],
				'after' => $after,
			);
			return;
		}
	}
	if ( $cmsa_isolation_monitor['location_id'] > 0 ) {
		$after = $cmsa_isolation_shape_location( $reader, $cmsa_isolation_monitor['location_id'] );
		if ( isset( $after['state_token'] ) && $after['state_token'] !== $cmsa_isolation_monitor['location_token'] ) {
			$cmsa_isolation_monitor['first_change'] = array(
				'object' => 'location',
				'after_operation' => $operation,
				'before' => $cmsa_isolation_monitor['location_before'],
				'after' => $after,
			);
		}
	}
};

add_action(
	'cmsa_events_manager_event_written',
	static function ( $id, $operation ) use ( &$cmsa_isolation_monitor, $cmsa_isolation_shape_event, $cmsa_isolation_check ) {
		$reader = new CMSA_Events_Manager();
		$shape = $cmsa_isolation_shape_event( $reader, $id );
		if ( 'create' === $operation && isset( $shape['name'] ) && 0 === strpos( $shape['name'], 'CMSA Unrelated Event ' ) ) {
			$cmsa_isolation_monitor['event_id'] = (int) $id;
			$cmsa_isolation_monitor['event_token'] = $shape['state_token'];
			$cmsa_isolation_monitor['event_before'] = $shape;
		}
		$cmsa_isolation_check( 'event:' . $operation . ':' . (int) $id );
	},
	99,
	2
);

add_action(
	'cmsa_events_manager_location_written',
	static function ( $id, $operation ) use ( &$cmsa_isolation_monitor, $cmsa_isolation_shape_location, $cmsa_isolation_check ) {
		$reader = new CMSA_Events_Manager();
		$shape = $cmsa_isolation_shape_location( $reader, $id );
		if ( 'create' === $operation && isset( $shape['name'] ) && 0 === strpos( $shape['name'], 'CMSA Unrelated Venue ' ) ) {
			$cmsa_isolation_monitor['location_id'] = (int) $id;
			$cmsa_isolation_monitor['location_token'] = $shape['state_token'];
			$cmsa_isolation_monitor['location_before'] = $shape;
		}
		$cmsa_isolation_check( 'location:' . $operation . ':' . (int) $id );
	},
	99,
	2
);

add_action(
	'shutdown',
	static function () use ( &$cmsa_isolation_monitor, $cmsa_isolation_check ) {
		$cmsa_isolation_check( 'shutdown' );
		fwrite( STDERR, 'cmsa-isolation-monitor: ' . wp_json_encode( $cmsa_isolation_monitor ) . "\n" );
	},
	PHP_INT_MAX
);
