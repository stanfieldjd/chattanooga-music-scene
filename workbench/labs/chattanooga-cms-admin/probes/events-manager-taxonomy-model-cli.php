<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "events-manager-taxonomy-model-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "events-manager-taxonomy-model-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

$taxonomy_names = array( 'event-categories', 'event-tags' );
$taxonomy_model = array();
foreach ( $taxonomy_names as $taxonomy_name ) {
	$taxonomy = get_taxonomy( $taxonomy_name );
	if ( ! $taxonomy instanceof WP_Taxonomy ) {
		fwrite( STDERR, "events-manager-taxonomy-model-cli: taxonomy missing: {$taxonomy_name}.\n" );
		exit( 1 );
	}
	$taxonomy_model[ $taxonomy_name ] = array(
		'hierarchical' => (bool) $taxonomy->hierarchical,
		'object_type'  => array_values( (array) $taxonomy->object_type ),
		'capabilities' => array(
			'manage_terms' => (string) $taxonomy->cap->manage_terms,
			'edit_terms'   => (string) $taxonomy->cap->edit_terms,
			'delete_terms' => (string) $taxonomy->cap->delete_terms,
			'assign_terms' => (string) $taxonomy->cap->assign_terms,
		),
		'admin_can' => array(
			'manage_terms' => current_user_can( $taxonomy->cap->manage_terms ),
			'edit_terms'   => current_user_can( $taxonomy->cap->edit_terms ),
			'delete_terms' => current_user_can( $taxonomy->cap->delete_terms ),
			'assign_terms' => current_user_can( $taxonomy->cap->assign_terms ),
		),
	);
	if ( ! in_array( 'event', (array) $taxonomy->object_type, true ) ) {
		fwrite( STDERR, "events-manager-taxonomy-model-cli: {$taxonomy_name} is not attached to the event post type.\n" );
		exit( 1 );
	}
}

$token = strtolower( wp_generate_password( 8, false, false ) );
$location = new EM_Location();
$location->location_name = 'CMSA Taxonomy Model Venue ' . $token;
$location->location_address = '1 Taxonomy Way';
$location->location_town = 'Chattanooga';
$location->location_state = 'TN';
$location->location_postcode = '37402';
$location->location_country = 'US';
$location->location_owner = $admin->ID;
if ( ! $location->save() || empty( $location->location_id ) ) {
	fwrite( STDERR, "events-manager-taxonomy-model-cli: disposable location save failed.\n" );
	exit( 1 );
}

$event = new EM_Event();
$event->event_name = 'CMSA Taxonomy Model Event ' . $token;
$event->event_owner = $admin->ID;
$event->event_start_date = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS * 30 );
$event->event_end_date = $event->event_start_date;
$event->event_start_time = '19:00:00';
$event->event_end_time = '21:00:00';
$event->event_status = 1;
$event->location_id = (int) $location->location_id;
if ( ! $event->save() || empty( $event->event_id ) || empty( $event->post_id ) ) {
	$location->delete( true );
	fwrite( STDERR, "events-manager-taxonomy-model-cli: disposable event save failed.\n" );
	exit( 1 );
}
$event_id = (int) $event->event_id;
$post_id = (int) $event->post_id;
$location_id = (int) $location->location_id;

$category = wp_insert_term( 'CMSA Category ' . $token, 'event-categories' );
$tag = wp_insert_term( 'CMSA Tag ' . $token, 'event-tags' );
if ( is_wp_error( $category ) || is_wp_error( $tag ) ) {
	$event->delete( true );
	$location->delete( true );
	fwrite( STDERR, "events-manager-taxonomy-model-cli: disposable term creation failed.\n" );
	exit( 1 );
}
$category_id = (int) $category['term_id'];
$tag_id = (int) $tag['term_id'];

$category_set = wp_set_object_terms( $post_id, array( $category_id ), 'event-categories', false );
$tag_set = wp_set_object_terms( $post_id, array( $tag_id ), 'event-tags', false );
if ( is_wp_error( $category_set ) || is_wp_error( $tag_set ) ) {
	wp_delete_term( $category_id, 'event-categories' );
	wp_delete_term( $tag_id, 'event-tags' );
	$event->delete( true );
	$location->delete( true );
	fwrite( STDERR, "events-manager-taxonomy-model-cli: term relationship assignment failed.\n" );
	exit( 1 );
}

$category_ids = array_map( 'intval', wp_get_object_terms( $post_id, 'event-categories', array( 'fields' => 'ids' ) ) );
$tag_ids = array_map( 'intval', wp_get_object_terms( $post_id, 'event-tags', array( 'fields' => 'ids' ) ) );
sort( $category_ids, SORT_NUMERIC );
sort( $tag_ids, SORT_NUMERIC );

$event_after_assignment = new EM_Event( $event_id, 'event_id' );
$location_after_assignment = new EM_Location( $location_id, 'location_id' );
$assignment_preserved_event = $event_after_assignment instanceof EM_Event && (int) $event_after_assignment->event_id === $event_id;
$assignment_preserved_location = $location_after_assignment instanceof EM_Location && (int) $location_after_assignment->location_id === $location_id;

$category_cleared = wp_set_object_terms( $post_id, array(), 'event-categories', false );
$tag_cleared = wp_set_object_terms( $post_id, array(), 'event-tags', false );
$category_ids_after_clear = array_map( 'intval', wp_get_object_terms( $post_id, 'event-categories', array( 'fields' => 'ids' ) ) );
$tag_ids_after_clear = array_map( 'intval', wp_get_object_terms( $post_id, 'event-tags', array( 'fields' => 'ids' ) ) );
$category_term_survives = get_term( $category_id, 'event-categories' ) instanceof WP_Term;
$tag_term_survives = get_term( $tag_id, 'event-tags' ) instanceof WP_Term;

$result = array(
	'taxonomies' => $taxonomy_model,
	'relationships' => array(
		'category_assignment' => $category_ids,
		'tag_assignment'      => $tag_ids,
		'event_preserved'     => $assignment_preserved_event,
		'location_preserved'  => $assignment_preserved_location,
		'category_clear'      => is_wp_error( $category_cleared ) ? false : true,
		'tag_clear'           => is_wp_error( $tag_cleared ) ? false : true,
		'category_after_clear'=> $category_ids_after_clear,
		'tag_after_clear'     => $tag_ids_after_clear,
		'category_term_survives' => $category_term_survives,
		'tag_term_survives'      => $tag_term_survives,
	),
);
echo wp_json_encode( $result, JSON_PRETTY_PRINT ) . "\n";

foreach ( $taxonomy_model as $taxonomy_name => $model ) {
	if ( empty( $model['admin_can']['assign_terms'] ) ) {
		fwrite( STDERR, "events-manager-taxonomy-model-cli: administrator lacks native assign_terms authority for {$taxonomy_name}.\n" );
		exit( 1 );
	}
}
if ( array( $category_id ) !== $category_ids || array( $tag_id ) !== $tag_ids ) {
	fwrite( STDERR, "events-manager-taxonomy-model-cli: event taxonomy assignment did not round-trip exactly.\n" );
	exit( 1 );
}
if ( ! $assignment_preserved_event || ! $assignment_preserved_location ) {
	fwrite( STDERR, "events-manager-taxonomy-model-cli: taxonomy assignment changed event or location identity.\n" );
	exit( 1 );
}
if ( is_wp_error( $category_cleared ) || is_wp_error( $tag_cleared ) || $category_ids_after_clear || $tag_ids_after_clear ) {
	fwrite( STDERR, "events-manager-taxonomy-model-cli: taxonomy relationship clear did not round-trip exactly.\n" );
	exit( 1 );
}
if ( ! $category_term_survives || ! $tag_term_survives ) {
	fwrite( STDERR, "events-manager-taxonomy-model-cli: clearing event relationships deleted taxonomy terms.\n" );
	exit( 1 );
}

wp_delete_term( $category_id, 'event-categories' );
wp_delete_term( $tag_id, 'event-tags' );
$event_after_assignment->delete( true );
$location_after_assignment->delete( true );
echo "events-manager-taxonomy-model-cli: PASS taxonomies=event-categories,event-tags assignment=exact clear=relationship-only event-location=preserved\n";
