<?php

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'EM_Event' ) || ! class_exists( 'EM_Location' ) ) {
	fwrite( STDERR, "events-manager-event-preservation-diagnostic-cli: runtime unavailable.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin ? $admin->ID : 0 );
$reader = new CMSA_Events_Manager();
$mutations = new CMSA_Events_Manager_Mutations( $reader );
$token = strtolower( wp_generate_password( 8, false, false ) );

$location_result = $mutations->create_location(
	array(
		'location_name'     => 'CMSA Preservation Venue ' . $token,
		'post_status'       => 'publish',
		'location_address'  => '778 Diagnostic Street',
		'location_town'     => 'Chattanooga',
		'location_state'    => 'TN',
		'location_postcode' => '37402',
		'location_country'  => 'US',
	)
);
if ( is_wp_error( $location_result ) ) {
	fwrite( STDERR, 'events-manager-event-preservation-diagnostic-cli: venue setup failed code=' . $location_result->get_error_code() . "\n" );
	exit( 1 );
}
$location_id = (int) $location_result['location']['id'];

$event = new EM_Event();
$event->event_archetype = 'event';
$event->event_type = 'single';
$event->event_active_status = 0;
$event->event_rsvp = 1;
$event->event_owner = (int) $admin->ID;
$event->event_name = 'CMSA Preservation Diagnostic ' . $token;
$event->event_start_date = gmdate( 'Y-m-d', strtotime( '+9 days' ) );
$event->event_end_date = $event->event_start_date;
$event->event_start_time = '17:00:00';
$event->event_end_time = '18:00:00';
$event->event_timezone = 'America/New_York';
$event->location_id = $location_id;
if ( ! $event->save() || empty( $event->event_id ) ) {
	fwrite( STDERR, "events-manager-event-preservation-diagnostic-cli: event setup failed.\n" );
	exit( 1 );
}
$event_id = (int) $event->event_id;

$shape = static function ( $event ) {
	$post = $event instanceof EM_Event && ! empty( $event->post_id ) ? get_post( (int) $event->post_id ) : null;
	return array(
		'event_status' => $event instanceof EM_Event && isset( $event->event_status ) ? (int) $event->event_status : null,
		'active_status' => $event instanceof EM_Event && isset( $event->event_active_status ) ? (int) $event->event_active_status : null,
		'rsvp' => $event instanceof EM_Event && isset( $event->event_rsvp ) ? (int) $event->event_rsvp : null,
		'post_status' => $post instanceof WP_Post ? (string) $post->post_status : null,
	);
};

$before_object = $reader->load_event( $event_id );
$before = is_wp_error( $before_object ) ? array( 'load_error' => $before_object->get_error_code() ) : $shape( $before_object );
$before_read = $reader->get_event( $event_id );
if ( is_wp_error( $before_read ) ) {
	fwrite( STDERR, 'events-manager-event-preservation-diagnostic-cli: read setup failed code=' . $before_read->get_error_code() . "\n" );
	exit( 1 );
}

$result = $mutations->update_event(
	array(
		'id' => $event_id,
		'expected_state_token' => $before_read['event']['state_token'],
		'event_name' => 'CMSA Preservation Diagnostic Updated ' . $token,
	)
);
$after_object = $reader->load_event( $event_id );
$after = is_wp_error( $after_object ) ? array( 'load_error' => $after_object->get_error_code() ) : $shape( $after_object );

if ( ! is_wp_error( $after_object ) ) {
	$after_object->delete( true );
}
$location = $reader->load_location( $location_id );
if ( ! is_wp_error( $location ) ) {
	$location->delete( true );
}

$payload = array(
	'before' => $before,
	'after' => $after,
	'update_error' => is_wp_error( $result ) ? $result->get_error_code() : null,
);

echo 'events-manager-event-preservation-diagnostic-cli: ' . wp_json_encode( $payload ) . "\n";
if ( is_wp_error( $result ) ) {
	exit( 1 );
}
