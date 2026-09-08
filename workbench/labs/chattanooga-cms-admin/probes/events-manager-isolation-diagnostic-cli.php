<?php

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'EM_Event' ) || ! class_exists( 'EM_Location' ) ) {
	fwrite( STDERR, "events-manager-isolation-diagnostic-cli: runtime unavailable.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin ? $admin->ID : 0 );
$reader = new CMSA_Events_Manager();
$mutations = new CMSA_Events_Manager_Mutations( $reader );
$token = strtolower( wp_generate_password( 8, false, false ) );

$location = $mutations->create_location(
	array(
		'location_name'     => 'CMSA Isolation Venue ' . $token,
		'post_status'       => 'draft',
		'location_address'  => '901 Isolation Street',
		'location_town'     => 'Chattanooga',
		'location_state'    => 'TN',
		'location_postcode' => '37403',
		'location_country'  => 'US',
	)
);
if ( is_wp_error( $location ) ) {
	fwrite( STDERR, 'events-manager-isolation-diagnostic-cli: location create failed=' . $location->get_error_code() . "\n" );
	exit( 1 );
}
$location_id = (int) $location['location']['id'];
$location_reread = $reader->get_location( $location_id );

$event = $mutations->create_event(
	array(
		'event_name'       => 'CMSA Isolation Event ' . $token,
		'post_status'      => 'draft',
		'event_start_date' => gmdate( 'Y-m-d', strtotime( '+8 days' ) ),
		'event_start_time' => '18:00',
		'event_end_time'   => '20:00',
		'event_timezone'   => 'America/New_York',
		'location_id'      => $location_id,
	)
);
if ( is_wp_error( $event ) ) {
	$loaded_location = $reader->load_location( $location_id );
	if ( ! is_wp_error( $loaded_location ) ) {
		$loaded_location->delete( true );
	}
	fwrite( STDERR, 'events-manager-isolation-diagnostic-cli: event create failed=' . $event->get_error_code() . "\n" );
	exit( 1 );
}
$event_id = (int) $event['event']['id'];
$event_reread = $reader->get_event( $event_id );

$payload = array(
	'location_created_token' => $location['location']['state_token'],
	'location_reread_token' => is_wp_error( $location_reread ) ? $location_reread->get_error_code() : $location_reread['location']['state_token'],
	'event_created_token' => $event['event']['state_token'],
	'event_reread_token' => is_wp_error( $event_reread ) ? $event_reread->get_error_code() : $event_reread['event']['state_token'],
	'event_created' => $event['event'],
	'event_reread' => is_wp_error( $event_reread ) ? null : $event_reread['event'],
);

$loaded_event = $reader->load_event( $event_id );
if ( ! is_wp_error( $loaded_event ) ) {
	$loaded_event->delete( true );
}
$loaded_location = $reader->load_location( $location_id );
if ( ! is_wp_error( $loaded_location ) ) {
	$loaded_location->delete( true );
}

echo 'events-manager-isolation-diagnostic-cli: ' . wp_json_encode( $payload ) . "\n";
if ( is_wp_error( $location_reread ) || is_wp_error( $event_reread ) ) {
	exit( 1 );
}
