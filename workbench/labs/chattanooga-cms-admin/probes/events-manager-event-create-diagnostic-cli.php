<?php

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'EM_Event' ) || ! class_exists( 'EM_Location' ) ) {
	fwrite( STDERR, "events-manager-event-create-diagnostic-cli: runtime unavailable.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin ? $admin->ID : 0 );
$reader = new CMSA_Events_Manager();
$mutations = new CMSA_Events_Manager_Mutations( $reader );
$token = strtolower( wp_generate_password( 8, false, false ) );

$location_result = $mutations->create_location(
	array(
		'location_name'     => 'CMSA Event Diagnostic Venue ' . $token,
		'post_status'       => 'publish',
		'location_address'  => '777 Diagnostic Street',
		'location_town'     => 'Chattanooga',
		'location_state'    => 'TN',
		'location_postcode' => '37402',
		'location_country'  => 'US',
	)
);
if ( is_wp_error( $location_result ) ) {
	fwrite( STDERR, 'events-manager-event-create-diagnostic-cli: venue setup failed code=' . $location_result->get_error_code() . "\n" );
	exit( 1 );
}
$location_id = (int) $location_result['location']['id'];
$captured = null;
$capture = function ( $id, $operation ) use ( &$captured, $reader ) {
	if ( 'create' !== $operation ) {
		return;
	}
	$event = $reader->load_event( (int) $id );
	if ( is_wp_error( $event ) ) {
		$captured = array( 'load_error' => $event->get_error_code() );
		return;
	}
	$post = ! empty( $event->post_id ) ? get_post( (int) $event->post_id ) : null;
	$captured = array(
		'id' => (int) $event->event_id,
		'name' => (string) $event->event_name,
		'post_content' => $post instanceof WP_Post ? (string) $post->post_content : '',
		'post_status' => $post instanceof WP_Post ? (string) $post->post_status : null,
		'start_date' => (string) $event->event_start_date,
		'end_date' => (string) $event->event_end_date,
		'start_time' => (string) $event->event_start_time,
		'end_time' => (string) $event->event_end_time,
		'all_day' => ! empty( $event->event_all_day ),
		'timezone' => isset( $event->event_timezone ) ? (string) $event->event_timezone : null,
		'location_id' => isset( $event->location_id ) ? (int) $event->location_id : null,
		'active_status' => isset( $event->event_active_status ) ? (int) $event->event_active_status : null,
		'rsvp' => isset( $event->event_rsvp ) ? (int) $event->event_rsvp : null,
		'private' => isset( $event->event_private ) ? (int) $event->event_private : null,
		'archetype' => isset( $event->event_archetype ) ? (string) $event->event_archetype : null,
		'type' => isset( $event->event_type ) ? (string) $event->event_type : null,
	);
};
add_action( 'cmsa_events_manager_event_written', $capture, 10, 2 );
$start_date = gmdate( 'Y-m-d', strtotime( '+7 days' ) );
$result = $mutations->create_event(
	array(
		'event_name'       => 'CMSA Event Diagnostic ' . $token,
		'content'          => '<p>Disposable event diagnostic.</p>',
		'post_status'      => 'publish',
		'event_start_date' => $start_date,
		'event_end_date'   => $start_date,
		'event_start_time' => '19:00',
		'event_end_time'   => '21:00',
		'event_timezone'   => 'America/New_York',
		'location_id'      => $location_id,
	)
);
remove_action( 'cmsa_events_manager_event_written', $capture, 10 );

if ( ! is_wp_error( $result ) && ! empty( $result['event']['id'] ) ) {
	$event = $reader->load_event( (int) $result['event']['id'] );
	if ( ! is_wp_error( $event ) ) {
		$event->delete( true );
	}
}
$location = $reader->load_location( $location_id );
if ( ! is_wp_error( $location ) ) {
	$location->delete( true );
}

if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'events-manager-event-create-diagnostic-cli: ERROR code=' . $result->get_error_code() . ' captured=' . wp_json_encode( $captured ) . "\n" );
	exit( 1 );
}

echo 'events-manager-event-create-diagnostic-cli: PASS captured=' . wp_json_encode( $captured ) . "\n";
