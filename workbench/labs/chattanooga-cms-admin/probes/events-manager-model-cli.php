<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "events-manager-model-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$plugin_file = 'events-manager/events-manager.php';
if ( ! is_plugin_active( $plugin_file ) ) {
	fwrite( STDERR, "events-manager-model-cli: Events Manager is not active.\n" );
	exit( 1 );
}

$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );
$classes = array( 'EM_Event', 'EM_Events', 'EM_Location', 'EM_Locations', 'EM_Ticket', 'EM_Tickets', 'EM_Booking', 'EM_Bookings' );
$functions = array( 'em_get_event', 'em_get_events', 'em_get_location', 'em_get_locations' );
$class_map = array();
foreach ( $classes as $class ) {
	$class_map[ $class ] = class_exists( $class );
}
$function_map = array();
foreach ( $functions as $function ) {
	$function_map[ $function ] = function_exists( $function );
}

$post_types = array();
foreach ( array( 'event', 'event-recurring', 'location' ) as $post_type ) {
	$post_types[ $post_type ] = post_type_exists( $post_type );
}
$taxonomies = array();
foreach ( array( 'event-categories', 'event-tags' ) as $taxonomy ) {
	$taxonomies[ $taxonomy ] = taxonomy_exists( $taxonomy );
}

$result = array(
	'plugin' => array(
		'version' => isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '',
		'file'    => $plugin_file,
	),
	'classes'    => $class_map,
	'functions'  => $function_map,
	'post_types' => $post_types,
	'taxonomies' => $taxonomies,
);

echo wp_json_encode( $result, JSON_PRETTY_PRINT ) . "\n";

foreach ( array( 'EM_Event', 'EM_Events', 'EM_Location', 'EM_Locations' ) as $required ) {
	if ( empty( $class_map[ $required ] ) ) {
		fwrite( STDERR, "events-manager-model-cli: required class missing: {$required}.\n" );
		exit( 1 );
	}
}
if ( empty( $post_types['event'] ) || empty( $post_types['location'] ) ) {
	fwrite( STDERR, "events-manager-model-cli: required event/location post types missing.\n" );
	exit( 1 );
}

echo "events-manager-model-cli: PASS version=" . $result['plugin']['version'] . " event-location-model=present\n";
