<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "events-manager-read-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
if ( ! class_exists( 'EM_Event' ) || ! class_exists( 'EM_Location' ) ) {
	fwrite( STDERR, "events-manager-read-cli: Events Manager model missing.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "events-manager-read-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
$token = strtolower( wp_generate_password( 8, false, false ) );

$location = new EM_Location();
$location->location_name = 'CMSA Venue ' . $token;
$location->location_address = '123 Test Street';
$location->location_town = 'Chattanooga';
$location->location_state = 'TN';
$location->location_postcode = '37402';
$location->location_country = 'US';
$location->location_owner = $admin->ID;
if ( ! $location->save() || empty( $location->location_id ) ) {
	fwrite( STDERR, "events-manager-read-cli: location fixture save failed.\n" );
	exit( 1 );
}

$event = new EM_Event();
$event->event_name = 'CMSA Event ' . $token;
$event->event_start_date = gmdate( 'Y-m-d', strtotime( '+7 days' ) );
$event->event_end_date = $event->event_start_date;
$event->event_start_time = '19:00:00';
$event->event_end_time = '21:00:00';
$event->event_status = 1;
$event->event_owner = $admin->ID;
$event->location_id = $location->location_id;
if ( ! $event->save() || empty( $event->event_id ) ) {
	fwrite( STDERR, "events-manager-read-cli: event fixture save failed.\n" );
	exit( 1 );
}

$adapter = new CMSA_Events_Manager();
$event_list = $adapter->list_events( array( 'search' => $token, 'page' => 1, 'per_page' => 1 ) );
if ( is_wp_error( $event_list ) || 1 !== count( $event_list['items'] ) || (int) $event_list['items'][0]['id'] !== (int) $event->event_id ) {
	fwrite( STDERR, "events-manager-read-cli: bounded event list/search failed.\n" );
	exit( 1 );
}
$event_detail = $adapter->get_event( $event->event_id );
if ( is_wp_error( $event_detail ) || (int) $event_detail['event']['id'] !== (int) $event->event_id || $event->event_name !== $event_detail['event']['name'] ) {
	fwrite( STDERR, "events-manager-read-cli: event detail failed.\n" );
	exit( 1 );
}
$event_keys = array_keys( $event_detail['event'] );
sort( $event_keys, SORT_STRING );
$expected_event_keys = array( 'end_date', 'end_time', 'id', 'location_id', 'name', 'post_id', 'start_date', 'start_time', 'status' );
sort( $expected_event_keys, SORT_STRING );
if ( $event_keys !== $expected_event_keys ) {
	fwrite( STDERR, "events-manager-read-cli: event field allowlist changed unexpectedly.\n" );
	exit( 1 );
}

$location_list = $adapter->list_locations( array( 'search' => $token, 'page' => 1, 'per_page' => 1 ) );
if ( is_wp_error( $location_list ) || 1 !== count( $location_list['items'] ) || (int) $location_list['items'][0]['id'] !== (int) $location->location_id ) {
	fwrite( STDERR, "events-manager-read-cli: bounded location list/search failed.\n" );
	exit( 1 );
}
$location_detail = $adapter->get_location( $location->location_id );
if ( is_wp_error( $location_detail ) || (int) $location_detail['location']['id'] !== (int) $location->location_id || $location->location_name !== $location_detail['location']['name'] ) {
	fwrite( STDERR, "events-manager-read-cli: location detail failed.\n" );
	exit( 1 );
}
$location_keys = array_keys( $location_detail['location'] );
sort( $location_keys, SORT_STRING );
$expected_location_keys = array( 'address', 'country', 'id', 'name', 'post_id', 'postcode', 'region', 'state', 'town' );
sort( $expected_location_keys, SORT_STRING );
if ( $location_keys !== $expected_location_keys ) {
	fwrite( STDERR, "events-manager-read-cli: location field allowlist changed unexpectedly.\n" );
	exit( 1 );
}

$missing_event = $adapter->get_event( 2147483647 );
$missing_location = $adapter->get_location( 2147483647 );
if ( ! is_wp_error( $missing_event ) || 'cmsa_event_not_found' !== $missing_event->get_error_code() || ! is_wp_error( $missing_location ) || 'cmsa_location_not_found' !== $missing_location->get_error_code() ) {
	fwrite( STDERR, "events-manager-read-cli: missing resource did not fail closed.\n" );
	exit( 1 );
}

$event->delete( true );
$location->delete( true );

echo "events-manager-read-cli: PASS event=list-search-get-allowlist location=list-search-get-allowlist missing=fail-closed\n";
