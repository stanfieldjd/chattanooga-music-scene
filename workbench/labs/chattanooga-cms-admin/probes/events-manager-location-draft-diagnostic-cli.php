<?php

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'EM_Location' ) ) {
	fwrite( STDERR, "events-manager-location-draft-diagnostic-cli: runtime unavailable.\n" );
	exit( 1 );
}
$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin ? $admin->ID : 0 );
$reader = new CMSA_Events_Manager();
$mutations = new CMSA_Events_Manager_Mutations( $reader );
$captured = null;
add_action(
	'cmsa_events_manager_location_written',
	function ( $id, $mode ) use ( &$captured, $reader ) {
		$location = $reader->load_location( (int) $id );
		if ( is_wp_error( $location ) ) {
			$captured = array( 'load_error' => $location->get_error_code() );
			return;
		}
		$post = ! empty( $location->post_id ) ? get_post( (int) $location->post_id ) : null;
		$captured = array(
			'mode'               => $mode,
			'id'                 => (int) $location->location_id,
			'name'               => (string) $location->location_name,
			'post_content'       => $post instanceof WP_Post ? (string) $post->post_content : null,
			'post_status'        => $post instanceof WP_Post ? (string) $post->post_status : null,
			'address'            => (string) $location->location_address,
			'town'               => (string) $location->location_town,
			'state'              => (string) $location->location_state,
			'postcode'           => (string) $location->location_postcode,
			'region'             => (string) $location->location_region,
			'country'            => (string) $location->location_country,
			'latitude'           => isset( $location->location_latitude ) ? (string) $location->location_latitude : null,
			'longitude'          => isset( $location->location_longitude ) ? (string) $location->location_longitude : null,
			'location_status'    => isset( $location->location_status ) ? $location->location_status : null,
			'location_private'   => isset( $location->location_private ) ? $location->location_private : null,
		);
	},
	10,
	2
);
$token = strtolower( wp_generate_password( 8, false, false ) );
$result = $mutations->create_location(
	array(
		'location_name'     => 'CMSA Draft Diagnostic ' . $token,
		'post_status'       => 'draft',
		'location_address'  => '456 Diagnostic Street',
		'location_town'     => 'Chattanooga',
		'location_state'    => 'TN',
		'location_postcode' => '37403',
		'location_country'  => 'US',
	)
);
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'events-manager-location-draft-diagnostic-cli: ERROR code=' . $result->get_error_code() . ' data=' . wp_json_encode( $result->get_error_data() ) . ' captured=' . wp_json_encode( $captured ) . "\n" );
	exit( 1 );
}
$id = isset( $result['location']['id'] ) ? (int) $result['location']['id'] : 0;
$location = $reader->load_location( $id );
if ( ! is_wp_error( $location ) ) {
	$location->delete( true );
}
echo 'events-manager-location-draft-diagnostic-cli: PASS captured=' . wp_json_encode( $captured ) . "\n";
