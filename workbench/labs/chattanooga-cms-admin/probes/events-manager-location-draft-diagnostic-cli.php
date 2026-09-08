<?php

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'EM_Location' ) ) {
	fwrite( STDERR, "events-manager-location-draft-diagnostic-cli: runtime unavailable.\n" );
	exit( 1 );
}
$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin ? $admin->ID : 0 );
$reader = new CMSA_Events_Manager();
$mutations = new CMSA_Events_Manager_Mutations( $reader );
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
	fwrite( STDERR, 'events-manager-location-draft-diagnostic-cli: ERROR code=' . $result->get_error_code() . ' data=' . wp_json_encode( $result->get_error_data() ) . "\n" );
	exit( 1 );
}
$id = isset( $result['location']['id'] ) ? (int) $result['location']['id'] : 0;
$location = $reader->load_location( $id );
$post = ! is_wp_error( $location ) && ! empty( $location->post_id ) ? get_post( (int) $location->post_id ) : null;
$shape = array(
	'id' => $id,
	'post_status' => $post instanceof WP_Post ? $post->post_status : null,
	'location_status' => ! is_wp_error( $location ) ? $location->location_status : null,
	'location_private' => ! is_wp_error( $location ) && isset( $location->location_private ) ? $location->location_private : null,
);
if ( ! is_wp_error( $location ) ) {
	$location->delete( true );
}
echo 'events-manager-location-draft-diagnostic-cli: PASS ' . wp_json_encode( $shape ) . "\n";
