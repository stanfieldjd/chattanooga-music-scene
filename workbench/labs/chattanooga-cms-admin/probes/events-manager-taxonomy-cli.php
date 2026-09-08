<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$expected = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/expected-events-manager-taxonomy-abilities.json' ), true );
$abilities = array();
foreach ( $expected as $name ) {
	$ability = wp_get_ability( $name );
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "events-manager-taxonomy-cli: missing ability {$name}.\n" );
		exit( 1 );
	}
	$abilities[ $name ] = $ability;
}

wp_set_current_user( 0 );
foreach ( $abilities as $name => $ability ) {
	if ( true === $ability->check_permissions() ) {
		fwrite( STDERR, "events-manager-taxonomy-cli: anonymous access leaked for {$name}.\n" );
		exit( 1 );
	}
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
foreach ( $abilities as $name => $ability ) {
	if ( true !== $ability->check_permissions() ) {
		fwrite( STDERR, "events-manager-taxonomy-cli: administrator denied {$name}.\n" );
		exit( 1 );
	}
}

$token = strtolower( wp_generate_password( 8, false, false ) );
$location = new EM_Location();
$location->location_name = 'CMSA Taxonomy Transaction Venue ' . $token;
$location->location_address = '1 Classification Way';
$location->location_town = 'Chattanooga';
$location->location_state = 'TN';
$location->location_postcode = '37402';
$location->location_country = 'US';
$location->location_owner = $admin->ID;
if ( ! $location->save() || empty( $location->location_id ) ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: disposable location save failed.\n" );
	exit( 1 );
}

$make_event = function ( $name ) use ( $admin, $location ) {
	$event = new EM_Event();
	$event->event_name = $name;
	$event->event_owner = $admin->ID;
	$event->event_start_date = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS * 30 );
	$event->event_end_date = $event->event_start_date;
	$event->event_start_time = '19:00:00';
	$event->event_end_time = '21:00:00';
	$event->event_status = 1;
	$event->location_id = (int) $location->location_id;
	return $event->save() && ! empty( $event->event_id ) && ! empty( $event->post_id ) ? $event : null;
};
$event = $make_event( 'CMSA Taxonomy Transaction Event ' . $token );
$control = $make_event( 'CMSA Taxonomy Control Event ' . $token );
if ( ! $event instanceof EM_Event || ! $control instanceof EM_Event ) {
	if ( $event instanceof EM_Event ) {
		$event->delete( true );
	}
	if ( $control instanceof EM_Event ) {
		$control->delete( true );
	}
	$location->delete( true );
	fwrite( STDERR, "events-manager-taxonomy-cli: disposable event save failed.\n" );
	exit( 1 );
}

$category_a = wp_insert_term( 'CMSA Festival ' . $token, 'event-categories' );
$category_b = wp_insert_term( 'CMSA Live Music ' . $token, 'event-categories' );
$tag_a = wp_insert_term( 'CMSA Verified ' . $token, 'event-tags' );
if ( is_wp_error( $category_a ) || is_wp_error( $category_b ) || is_wp_error( $tag_a ) ) {
	$event->delete( true );
	$control->delete( true );
	$location->delete( true );
	fwrite( STDERR, "events-manager-taxonomy-cli: disposable term creation failed.\n" );
	exit( 1 );
}
$category_a_id = (int) $category_a['term_id'];
$category_b_id = (int) $category_b['term_id'];
$tag_a_id = (int) $tag_a['term_id'];

if ( is_wp_error( wp_set_object_terms( (int) $event->post_id, array( $category_a_id ), 'event-categories', false ) )
	|| is_wp_error( wp_set_object_terms( (int) $event->post_id, array( $tag_a_id ), 'event-tags', false ) )
	|| is_wp_error( wp_set_object_terms( (int) $control->post_id, array( $category_a_id ), 'event-categories', false ) ) ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: initial relationship setup failed.\n" );
	exit( 1 );
}

$events = new CMSA_Events_Manager();
$taxonomy = new CMSA_Events_Manager_Taxonomy( $events );
$event_before = $events->load_event( (int) $event->event_id );
$location_before = $events->load_location( (int) $location->location_id );
$event_state_before = $events->event_state( $event_before );
$location_state_before = $events->location_state( $location_before );

$list = $taxonomy->list_terms( 'event-categories', array( 'search' => 'CMSA ' ) );
if ( is_wp_error( $list ) ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: taxonomy list failed.\n" );
	exit( 1 );
}
$list_ids = array_map( function ( $item ) { return (int) $item['id']; }, $list['items'] );
if ( ! in_array( $category_a_id, $list_ids, true ) || ! in_array( $category_b_id, $list_ids, true ) ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: bounded taxonomy list omitted fixture terms.\n" );
	exit( 1 );
}

$current_category = $taxonomy->get_event_terms( 'event-categories', (int) $event->event_id );
if ( is_wp_error( $current_category ) || array( $category_a_id ) !== $current_category['term_ids'] ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: initial category relationship read failed.\n" );
	exit( 1 );
}
$replace = $taxonomy->set_event_terms(
	'event-categories',
	array(
		'id'                         => (int) $event->event_id,
		'expected_event_state_token' => $current_category['event_state_token'],
		'expected_term_ids'          => array( $category_a_id ),
		'term_ids'                   => array( $category_b_id ),
	)
);
if ( is_wp_error( $replace ) || array( $category_b_id ) !== $replace['term_ids'] ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: exact category replacement failed.\n" );
	exit( 1 );
}

$stale = $taxonomy->set_event_terms(
	'event-categories',
	array(
		'id'                         => (int) $event->event_id,
		'expected_event_state_token' => $current_category['event_state_token'],
		'expected_term_ids'          => array( $category_a_id ),
		'term_ids'                   => array( $category_a_id ),
	)
);
if ( ! is_wp_error( $stale ) || 'cmsa_event_taxonomy_relationship_conflict' !== $stale->get_error_code() ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: stale relationship state was not rejected.\n" );
	exit( 1 );
}

$current_category = $taxonomy->get_event_terms( 'event-categories', (int) $event->event_id );
$no_change = $taxonomy->set_event_terms(
	'event-categories',
	array(
		'id'                         => (int) $event->event_id,
		'expected_event_state_token' => $current_category['event_state_token'],
		'expected_term_ids'          => $current_category['term_ids'],
		'term_ids'                   => $current_category['term_ids'],
	)
);
if ( ! is_wp_error( $no_change ) || 'cmsa_event_taxonomy_no_change' !== $no_change->get_error_code() ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: no-change relationship write was not rejected.\n" );
	exit( 1 );
}

$current_tag = $taxonomy->get_event_terms( 'event-tags', (int) $event->event_id );
$clear_tag = $taxonomy->set_event_terms(
	'event-tags',
	array(
		'id'                         => (int) $event->event_id,
		'expected_event_state_token' => $current_tag['event_state_token'],
		'expected_term_ids'          => array( $tag_a_id ),
		'term_ids'                   => array(),
	)
);
if ( is_wp_error( $clear_tag ) || array() !== $clear_tag['term_ids'] || ! get_term( $tag_a_id, 'event-tags' ) instanceof WP_Term ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: relationship-only tag clear failed.\n" );
	exit( 1 );
}

$rollback_armed = true;
$corrupt_relationship = function ( $written_event_id, $written_taxonomy ) use ( &$rollback_armed, $event, $category_b_id ) {
	if ( $rollback_armed && (int) $written_event_id === (int) $event->event_id && 'event-categories' === $written_taxonomy ) {
		$rollback_armed = false;
		wp_set_object_terms( (int) $event->post_id, array( $category_b_id ), 'event-categories', false );
	}
};
add_action( 'cmsa_events_manager_taxonomy_written', $corrupt_relationship, 10, 2 );
$current_category = $taxonomy->get_event_terms( 'event-categories', (int) $event->event_id );
$forced_verify_failure = $taxonomy->set_event_terms(
	'event-categories',
	array(
		'id'                         => (int) $event->event_id,
		'expected_event_state_token' => $current_category['event_state_token'],
		'expected_term_ids'          => array( $category_b_id ),
		'term_ids'                   => array( $category_a_id ),
	)
);
remove_action( 'cmsa_events_manager_taxonomy_written', $corrupt_relationship, 10 );
$rollback_data = is_wp_error( $forced_verify_failure ) ? $forced_verify_failure->get_error_data() : array();
$category_after_rollback = $taxonomy->get_event_terms( 'event-categories', (int) $event->event_id );
if ( ! is_wp_error( $forced_verify_failure )
	|| 'cmsa_event_taxonomy_verify' !== $forced_verify_failure->get_error_code()
	|| empty( $rollback_data['rolled_back'] )
	|| is_wp_error( $category_after_rollback )
	|| array( $category_b_id ) !== $category_after_rollback['term_ids'] ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: verification-failure rollback was not exact.\n" );
	exit( 1 );
}

$control_ids = wp_get_object_terms( (int) $control->post_id, 'event-categories', array( 'fields' => 'ids' ) );
$control_ids = array_map( 'intval', is_wp_error( $control_ids ) ? array() : $control_ids );
sort( $control_ids, SORT_NUMERIC );
$event_after = $events->load_event( (int) $event->event_id );
$location_after = $events->load_location( (int) $location->location_id );
if ( is_wp_error( $event_after ) || ! hash_equals( $event_state_before, $events->event_state( $event_after ) ) ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: event core state changed during taxonomy transactions.\n" );
	exit( 1 );
}
if ( is_wp_error( $location_after ) || ! hash_equals( $location_state_before, $events->location_state( $location_after ) ) ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: referenced location changed during taxonomy transactions.\n" );
	exit( 1 );
}
if ( array( $category_a_id ) !== $control_ids ) {
	fwrite( STDERR, "events-manager-taxonomy-cli: unrelated control event taxonomy changed.\n" );
	exit( 1 );
}

wp_delete_term( $category_a_id, 'event-categories' );
wp_delete_term( $category_b_id, 'event-categories' );
wp_delete_term( $tag_a_id, 'event-tags' );
$event_after->delete( true );
$control->delete( true );
$location_after->delete( true );

echo "events-manager-taxonomy-cli: PASS abilities=3 anonymous=denied administrator=allowed taxonomy=bounded exact-replace=verified clear=relationship-only stale=blocked no-change=blocked rollback=exact event-location-control=unchanged\n";
