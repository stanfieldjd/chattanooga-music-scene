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
	$location->delete( true );
	fwrite( STDERR, "events-manager-delete-model-cli: disposable event save failed.\n" );
	exit( 1 );
}
$event_id = (int) $event->event_id;
$post_id = isset( $event->post_id ) ? (int) $event->post_id : 0;
if ( $post_id < 1 || ! get_post( $post_id ) instanceof WP_Post ) {
	$event->delete( true );
	$location->delete( true );
	fwrite( STDERR, "events-manager-delete-model-cli: disposable event backing post missing.\n" );
	exit( 1 );
}

$can_delete = method_exists( $event, 'can_manage' ) ? $event->can_manage( 'delete_events', 'delete_others_events' ) : null;
$soft_deleted = $event->delete();

$event_table = $GLOBALS['wpdb']->prefix . 'em_events';
$soft_row_exists = (bool) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT event_id FROM {$event_table} WHERE event_id = %d", $event_id ) );
$soft_post = get_post( $post_id );
$soft_post_exists = $soft_post instanceof WP_Post;
$soft_post_status = $soft_post_exists ? (string) $soft_post->post_status : '';
$soft_reload = new EM_Event( $event_id, 'event_id' );
$soft_event_exists = $soft_reload instanceof EM_Event && ! empty( $soft_reload->event_id ) && (int) $soft_reload->event_id === $event_id;

$location_after_soft = new EM_Location( $location_id, 'location_id' );
$location_soft_exists = $location_after_soft instanceof EM_Location && ! empty( $location_after_soft->location_id );
$location_soft_post_exists = $location_post_id > 0 ? get_post( $location_post_id ) instanceof WP_Post : true;

$forced_deleted = $soft_event_exists ? $soft_reload->delete( true ) : false;
$hard_row_exists = (bool) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT event_id FROM {$event_table} WHERE event_id = %d", $event_id ) );
$hard_post_exists = get_post( $post_id ) instanceof WP_Post;
$hard_reload = new EM_Event( $event_id, 'event_id' );
$hard_event_exists = $hard_reload instanceof EM_Event && ! empty( $hard_reload->event_id ) && (int) $hard_reload->event_id === $event_id;

$location_after_hard = new EM_Location( $location_id, 'location_id' );
$location_hard_exists = $location_after_hard instanceof EM_Location && ! empty( $location_after_hard->location_id );
$location_hard_post_exists = $location_post_id > 0 ? get_post( $location_post_id ) instanceof WP_Post : true;

$result = array(
	'delete_method' => array(
		'parameter_count' => $delete_method->getNumberOfParameters(),
		'required_count'  => $delete_method->getNumberOfRequiredParameters(),
		'parameters'      => $parameters,
	),
	'capabilities' => $capabilities,
	'can_manage_delete' => $can_delete,
	'soft_delete' => array(
		'return'               => $soft_deleted,
		'event_row_exists'     => $soft_row_exists,
		'event_object_exists'  => $soft_event_exists,
		'event_post_exists'    => $soft_post_exists,
		'event_post_status'    => $soft_post_status,
		'location_exists'      => $location_soft_exists,
		'location_post_exists' => $location_soft_post_exists,
	),
	'forced_delete' => array(
		'return'               => $forced_deleted,
		'event_row_exists'     => $hard_row_exists,
		'event_object_exists'  => $hard_event_exists,
		'event_post_exists'    => $hard_post_exists,
		'location_exists'      => $location_hard_exists,
		'location_post_exists' => $location_hard_post_exists,
	),
);

echo wp_json_encode( $result, JSON_PRETTY_PRINT ) . "\n";

if ( 1 !== $delete_method->getNumberOfParameters() || 0 !== $delete_method->getNumberOfRequiredParameters() || empty( $parameters[0]['has_default'] ) || false !== $parameters[0]['default'] ) {
	fwrite( STDERR, "events-manager-delete-model-cli: EM_Event delete signature is not the expected optional force_delete=false contract.\n" );
	exit( 1 );
}
if ( true !== $soft_deleted || ! $soft_row_exists || ! $soft_event_exists || ! $soft_post_exists || 'trash' !== $soft_post_status ) {
	fwrite( STDERR, "events-manager-delete-model-cli: default EM_Event delete did not produce the expected retained-row trash state.\n" );
	exit( 1 );
}
if ( ! $location_soft_exists || ! $location_soft_post_exists ) {
	fwrite( STDERR, "events-manager-delete-model-cli: soft event deletion changed the referenced location.\n" );
	exit( 1 );
}
if ( true !== $forced_deleted || $hard_row_exists || $hard_event_exists || $hard_post_exists ) {
	fwrite( STDERR, "events-manager-delete-model-cli: forced EM_Event delete did not remove event row, object identity, and backing post.\n" );
	exit( 1 );
}
if ( ! $location_hard_exists || ! $location_hard_post_exists ) {
	fwrite( STDERR, "events-manager-delete-model-cli: forced event deletion changed the referenced location.\n" );
	exit( 1 );
}

$location_after_hard->delete( true );
echo "events-manager-delete-model-cli: PASS delete=false=>trash delete=true=>permanent location=preserved\n";
