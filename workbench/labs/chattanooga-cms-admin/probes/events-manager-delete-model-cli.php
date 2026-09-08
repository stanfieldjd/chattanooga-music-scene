<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "events-manager-delete-model-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "events-manager-delete-model-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

if ( ! class_exists( 'EM_Event' ) || ! class_exists( 'EM_Location' ) ) {
	fwrite( STDERR, "events-manager-delete-model-cli: Events Manager event/location model missing.\n" );
	exit( 1 );
}

$delete_method = new ReflectionMethod( 'EM_Event', 'delete' );
$parameters = array();
foreach ( $delete_method->getParameters() as $parameter ) {
	$parameters[] = array(
		'name'        => $parameter->getName(),
		'optional'    => $parameter->isOptional(),
		'has_default' => $parameter->isDefaultValueAvailable(),
		'default'     => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
	);
}

$capabilities = array();
foreach ( array( 'edit_events', 'delete_events', 'delete_others_events', 'delete_published_events' ) as $capability ) {
	$capabilities[ $capability ] = current_user_can( $capability );
}

$token = strtolower( wp_generate_password( 8, false, false ) );
$location = new EM_Location();
$location->location_name = 'CMSA Delete Model Venue ' . $token;
$location->location_address = '1 Disposable Way';
$location->location_town = 'Chattanooga';
$location->location_state = 'TN';
$location->location_postcode = '37402';
$location->location_country = 'US';
$location->location_owner = $admin->ID;
if ( ! $location->save() || empty( $location->location_id ) ) {
	fwrite( STDERR, "events-manager-delete-model-cli: disposable location save failed.\n" );
	exit( 1 );
}
$location_id = (int) $location->location_id;
$location_post_id = isset( $location->post_id ) ? (int) $location->post_id : 0;

$event = new EM_Event();
$event->event_name = 'CMSA Delete Model Event ' . $token;
$event->event_owner = $admin->ID;
$event->event_start_date = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS * 30 );
$event->event_end_date = $event->event_start_date;
$event->event_start_time = '19:00:00';
$event->event_end_time = '21:00:00';
$event->event_status = 1;
$event->location_id = $location_id;
if ( ! $event->save() || empty( $event->event_id ) ) {
	$location->delete();
	fwrite( STDERR, "events-manager-delete-model-cli: disposable event save failed.\n" );
	exit( 1 );
}
$event_id = (int) $event->event_id;
$post_id = isset( $event->post_id ) ? (int) $event->post_id : 0;
if ( $post_id < 1 || ! get_post( $post_id ) instanceof WP_Post ) {
	$event->delete();
	$location->delete();
	fwrite( STDERR, "events-manager-delete-model-cli: disposable event backing post missing.\n" );
	exit( 1 );
}

$can_delete = method_exists( $event, 'can_manage' ) ? $event->can_manage( 'delete_events', 'delete_others_events' ) : null;
$deleted = $event->delete();

$event_row_exists = false;
global $wpdb;
if ( isset( $wpdb->prefix ) ) {
	$table = $wpdb->prefix . 'em_events';
	$event_row_exists = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$table} WHERE event_id = %d", $event_id ) );
}
$post_exists = get_post( $post_id ) instanceof WP_Post;
$location_post_exists = $location_post_id > 0 ? get_post( $location_post_id ) instanceof WP_Post : true;
$location_reload = function_exists( 'em_get_location' ) ? em_get_location( $location_id ) : null;
$location_exists = $location_reload instanceof EM_Location && ! empty( $location_reload->location_id );

$result = array(
	'delete_method' => array(
		'parameter_count' => $delete_method->getNumberOfParameters(),
		'required_count'  => $delete_method->getNumberOfRequiredParameters(),
		'parameters'      => $parameters,
	),
	'capabilities'        => $capabilities,
	'can_manage_delete'   => $can_delete,
	'delete_return'       => $deleted,
	'event_row_exists'    => $event_row_exists,
	'event_post_exists'   => $post_exists,
	'location_exists'     => $location_exists,
	'location_post_exists'=> $location_post_exists,
);

echo wp_json_encode( $result, JSON_PRETTY_PRINT ) . "\n";

if ( true !== $deleted ) {
	fwrite( STDERR, "events-manager-delete-model-cli: native EM_Event delete did not report success.\n" );
	exit( 1 );
}
if ( $event_row_exists || $post_exists ) {
	fwrite( STDERR, "events-manager-delete-model-cli: native delete did not remove both event row and backing post.\n" );
	exit( 1 );
}
if ( ! $location_exists || ! $location_post_exists ) {
	fwrite( STDERR, "events-manager-delete-model-cli: native event deletion changed the referenced location.\n" );
	exit( 1 );
}

if ( $location_reload instanceof EM_Location ) {
	$location_reload->delete();
} else {
	$location->delete();
}

echo "events-manager-delete-model-cli: PASS native-delete=event-row+post location=preserved\n";
