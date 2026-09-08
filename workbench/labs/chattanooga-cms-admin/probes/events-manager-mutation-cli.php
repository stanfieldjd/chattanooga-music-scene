<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "events-manager-mutation-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
if ( ! class_exists( 'EM_Event' ) || ! class_exists( 'EM_Location' ) ) {
	fwrite( STDERR, "events-manager-mutation-cli: Events Manager model missing.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "events-manager-mutation-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
$token = strtolower( wp_generate_password( 8, false, false ) );
$reader = new CMSA_Events_Manager();
$mutations = new CMSA_Events_Manager_Mutations( $reader );
$created_event_ids = array();
$created_location_ids = array();

$fail = static function ( $message ) use ( &$created_event_ids, &$created_location_ids, $reader ) {
	foreach ( array_reverse( $created_event_ids ) as $id ) {
		$event = $reader->load_event( $id );
		if ( ! is_wp_error( $event ) ) {
			$event->delete( true );
		}
	}
	foreach ( array_reverse( $created_location_ids ) as $id ) {
		$location = $reader->load_location( $id );
		if ( ! is_wp_error( $location ) ) {
			$location->delete( true );
		}
	}
	fwrite( STDERR, "events-manager-mutation-cli: {$message}\n" );
	exit( 1 );
};

$primary_location = $mutations->create_location(
	array(
		'location_name'      => 'CMSA Mutation Venue ' . $token,
		'content'            => '<p>Disposable venue fixture.</p>',
		'post_status'        => 'publish',
		'location_address'   => '123 Test Street',
		'location_town'      => 'Chattanooga',
		'location_state'     => 'TN',
		'location_postcode'  => '37402',
		'location_country'   => 'US',
		'location_latitude'  => 35.0456,
		'location_longitude' => -85.3097,
	)
);
if ( is_wp_error( $primary_location ) || empty( $primary_location['created'] ) ) {
	$fail( 'location create failed: ' . ( is_wp_error( $primary_location ) ? $primary_location->get_error_code() : 'unexpected-result' ) );
}
$primary_location_id = (int) $primary_location['location']['id'];
$created_location_ids[] = $primary_location_id;
$location_before = $primary_location['location'];

$unrelated_location = $mutations->create_location(
	array(
		'location_name'     => 'CMSA Unrelated Venue ' . $token,
		'post_status'       => 'draft',
		'location_address'  => '456 Other Street',
		'location_town'     => 'Chattanooga',
		'location_state'    => 'TN',
		'location_postcode' => '37403',
		'location_country'  => 'US',
	)
);
if ( is_wp_error( $unrelated_location ) || empty( $unrelated_location['created'] ) ) {
	$fail( 'unrelated location create failed.' );
}
$unrelated_location_id = (int) $unrelated_location['location']['id'];
$created_location_ids[] = $unrelated_location_id;
$unrelated_location_state = $unrelated_location['location']['state_token'];

$stale_location = $mutations->update_location(
	array(
		'id'                   => $primary_location_id,
		'expected_state_token' => str_repeat( '0', 64 ),
		'location_town'        => 'Should Not Persist',
	)
);
if ( ! is_wp_error( $stale_location ) || 'cmsa_location_conflict' !== $stale_location->get_error_code() ) {
	$fail( 'stale location update did not fail closed.' );
}
$location_after_stale = $reader->get_location( $primary_location_id );
if ( is_wp_error( $location_after_stale ) || $location_before['state_token'] !== $location_after_stale['location']['state_token'] ) {
	$fail( 'stale location update changed state.' );
}

$location_update = $mutations->update_location(
	array(
		'id'                   => $primary_location_id,
		'expected_state_token' => $location_before['state_token'],
		'location_name'        => 'CMSA Mutation Venue Updated ' . $token,
		'location_address'     => '125 Test Street',
		'location_latitude'    => 35.046,
		'location_longitude'   => -85.31,
	)
);
if ( is_wp_error( $location_update ) || empty( $location_update['updated'] ) || $location_update['location']['state_token'] === $location_before['state_token'] ) {
	$fail( 'location update/readback failed: ' . ( is_wp_error( $location_update ) ? $location_update->get_error_code() : 'unexpected-result' ) );
}

$location_fault_before = $location_update['location'];
$location_fault = null;
$location_fault = function ( $id, $operation ) use ( &$location_fault, $primary_location_id ) {
	if ( 'update' !== $operation || (int) $id !== $primary_location_id ) {
		return;
	}
	remove_action( 'cmsa_events_manager_location_written', $location_fault, 10 );
	$corrupt = new EM_Location( (int) $id, 'location_id' );
	$corrupt->location_town = 'Injected Corruption';
	$corrupt->save();
};
add_action( 'cmsa_events_manager_location_written', $location_fault, 10, 2 );
$location_fault_result = $mutations->update_location(
	array(
		'id'                   => $primary_location_id,
		'expected_state_token' => $location_fault_before['state_token'],
		'location_postcode'    => '37404',
	)
);
remove_action( 'cmsa_events_manager_location_written', $location_fault, 10 );
if ( ! is_wp_error( $location_fault_result ) || 'cmsa_location_update_verify' !== $location_fault_result->get_error_code() || true !== (bool) $location_fault_result->get_error_data()['rolled_back'] ) {
	$fail( 'location verification fault did not trigger successful rollback.' );
}
$location_after_fault = $reader->get_location( $primary_location_id );
if ( is_wp_error( $location_after_fault ) || $location_fault_before['state_token'] !== $location_after_fault['location']['state_token'] ) {
	$fail( 'location rollback did not restore exact managed state.' );
}

$start_date = gmdate( 'Y-m-d', strtotime( '+7 days' ) );
$primary_event = $mutations->create_event(
	array(
		'event_name'       => 'CMSA Mutation Event ' . $token,
		'content'          => '<p>Disposable event fixture.</p>',
		'post_status'      => 'publish',
		'event_start_date' => $start_date,
		'event_end_date'   => $start_date,
		'event_start_time' => '19:00',
		'event_end_time'   => '21:00',
		'event_timezone'   => 'America/New_York',
		'location_id'      => $primary_location_id,
	)
);
if ( is_wp_error( $primary_event ) || empty( $primary_event['created'] ) ) {
	$fail( 'event create failed: ' . ( is_wp_error( $primary_event ) ? $primary_event->get_error_code() : 'unexpected-result' ) );
}
$primary_event_id = (int) $primary_event['event']['id'];
$created_event_ids[] = $primary_event_id;
$event_before = $primary_event['event'];
$primary_event_object = $reader->load_event( $primary_event_id );
if ( is_wp_error( $primary_event_object ) || 'event' !== (string) $primary_event_object->event_archetype || 'single' !== (string) $primary_event_object->event_type || 1 !== (int) $primary_event_object->event_active_status || ! empty( $primary_event_object->event_rsvp ) ) {
	$fail( 'created event was not canonical ordinary single/non-booking state.' );
}

$unrelated_event = $mutations->create_event(
	array(
		'event_name'       => 'CMSA Unrelated Event ' . $token,
		'post_status'      => 'draft',
		'event_start_date' => gmdate( 'Y-m-d', strtotime( '+8 days' ) ),
		'event_start_time' => '18:00',
		'event_end_time'   => '20:00',
		'event_timezone'   => 'America/New_York',
		'location_id'      => $unrelated_location_id,
	)
);
if ( is_wp_error( $unrelated_event ) || empty( $unrelated_event['created'] ) ) {
	$fail( 'unrelated event create failed.' );
}
$unrelated_event_id = (int) $unrelated_event['event']['id'];
$created_event_ids[] = $unrelated_event_id;
$unrelated_event_state = $unrelated_event['event']['state_token'];

$stale_event = $mutations->update_event(
	array(
		'id'                   => $primary_event_id,
		'expected_state_token' => str_repeat( 'f', 64 ),
		'event_name'           => 'Should Not Persist',
	)
);
if ( ! is_wp_error( $stale_event ) || 'cmsa_event_conflict' !== $stale_event->get_error_code() ) {
	$fail( 'stale event update did not fail closed.' );
}
$event_after_stale = $reader->get_event( $primary_event_id );
if ( is_wp_error( $event_after_stale ) || $event_before['state_token'] !== $event_after_stale['event']['state_token'] ) {
	$fail( 'stale event update changed state.' );
}

$event_update = $mutations->update_event(
	array(
		'id'                   => $primary_event_id,
		'expected_state_token' => $event_before['state_token'],
		'event_name'           => 'CMSA Mutation Event Updated ' . $token,
		'content'              => '<p>Updated disposable event fixture.</p>',
		'event_start_time'     => '19:30',
		'event_end_time'       => '21:30',
	)
);
if ( is_wp_error( $event_update ) || empty( $event_update['updated'] ) || $event_update['event']['state_token'] === $event_before['state_token'] ) {
	$fail( 'event update/readback failed: ' . ( is_wp_error( $event_update ) ? $event_update->get_error_code() : 'unexpected-result' ) );
}

$event_fault_before = $event_update['event'];
$event_fault = null;
$event_fault = function ( $id, $operation ) use ( &$event_fault, $primary_event_id ) {
	if ( 'update' !== $operation || (int) $id !== $primary_event_id ) {
		return;
	}
	remove_action( 'cmsa_events_manager_event_written', $event_fault, 10 );
	$corrupt = new EM_Event( (int) $id, 'event_id' );
	$corrupt->event_name = 'Injected Corruption';
	$corrupt->save();
};
add_action( 'cmsa_events_manager_event_written', $event_fault, 10, 2 );
$event_fault_result = $mutations->update_event(
	array(
		'id'                   => $primary_event_id,
		'expected_state_token' => $event_fault_before['state_token'],
		'event_end_time'       => '22:00',
	)
);
remove_action( 'cmsa_events_manager_event_written', $event_fault, 10 );
if ( ! is_wp_error( $event_fault_result ) || 'cmsa_event_update_verify' !== $event_fault_result->get_error_code() || true !== (bool) $event_fault_result->get_error_data()['rolled_back'] ) {
	$fail( 'event verification fault did not trigger successful rollback.' );
}
$event_after_fault = $reader->get_event( $primary_event_id );
if ( is_wp_error( $event_after_fault ) || $event_fault_before['state_token'] !== $event_after_fault['event']['state_token'] ) {
	$fail( 'event rollback did not restore exact managed state.' );
}

$preserved = new EM_Event();
$preserved->event_archetype = 'event';
$preserved->event_type = 'single';
$preserved->event_rsvp = 1;
$preserved->event_owner = $admin->ID;
$preserved->event_name = 'CMSA Preserve Flags ' . $token;
$preserved->event_start_date = gmdate( 'Y-m-d', strtotime( '+9 days' ) );
$preserved->event_end_date = $preserved->event_start_date;
$preserved->event_start_time = '17:00:00';
$preserved->event_end_time = '18:00:00';
$preserved->location_id = $primary_location_id;
if ( ! $preserved->save() || empty( $preserved->event_id ) ) {
	$fail( 'flag-preservation fixture save failed.' );
}
$preserved_id = (int) $preserved->event_id;
$created_event_ids[] = $preserved_id;
$preserved_before = $reader->load_event( $preserved_id );
if ( is_wp_error( $preserved_before ) ) {
	$fail( 'flag-preservation fixture load failed.' );
}
$preserved_active_before = isset( $preserved_before->event_active_status ) ? (int) $preserved_before->event_active_status : null;
$preserved_rsvp_before = isset( $preserved_before->event_rsvp ) ? (int) $preserved_before->event_rsvp : null;
$preserved_private_before = isset( $preserved_before->event_private ) ? (int) $preserved_before->event_private : null;
$preserved_read = $reader->get_event( $preserved_id );
if ( is_wp_error( $preserved_read ) ) {
	$fail( 'flag-preservation fixture read failed.' );
}
$preserve_update = $mutations->update_event(
	array(
		'id'                   => $preserved_id,
		'expected_state_token' => $preserved_read['event']['state_token'],
		'event_name'           => 'CMSA Preserve Flags Updated ' . $token,
	)
);
if ( is_wp_error( $preserve_update ) ) {
	$fail( 'flag-preservation metadata update failed: ' . $preserve_update->get_error_code() );
}
$preserved_after = $reader->load_event( $preserved_id );
if ( is_wp_error( $preserved_after ) ) {
	$fail( 'flag-preservation fixture reread failed.' );
}
$preserved_active_after = isset( $preserved_after->event_active_status ) ? (int) $preserved_after->event_active_status : null;
$preserved_rsvp_after = isset( $preserved_after->event_rsvp ) ? (int) $preserved_after->event_rsvp : null;
$preserved_private_after = isset( $preserved_after->event_private ) ? (int) $preserved_after->event_private : null;
if ( $preserved_active_before !== $preserved_active_after || $preserved_rsvp_before !== $preserved_rsvp_after || $preserved_private_before !== $preserved_private_after ) {
	$fail( 'event metadata update changed active/booking/private state outside its contract.' );
}

$limited_login = 'cmsa-em-publish-' . strtolower( wp_generate_password( 8, false, false ) );
$limited_id = wp_create_user( $limited_login, wp_generate_password( 24, true, true ), $limited_login . '@example.invalid' );
if ( is_wp_error( $limited_id ) ) {
	$fail( 'limited publish user creation failed.' );
}
$limited = new WP_User( $limited_id );
$limited->set_role( 'subscriber' );
$limited->add_cap( 'edit_events' );
$limited->add_cap( 'edit_locations' );
wp_set_current_user( 0 );
wp_set_current_user( $limited_id );
$publish_event_denied = $mutations->create_event(
	array(
		'event_name'       => 'CMSA Unauthorized Publish ' . $token,
		'post_status'      => 'publish',
		'event_start_date' => gmdate( 'Y-m-d', strtotime( '+10 days' ) ),
	)
);
$publish_location_denied = $mutations->create_location(
	array(
		'location_name'    => 'CMSA Unauthorized Venue ' . $token,
		'post_status'      => 'publish',
		'location_address' => '999 Denied Street',
		'location_town'    => 'Chattanooga',
		'location_country' => 'US',
	)
);
$owned_event_denied = $mutations->update_event(
	array(
		'id'                   => $primary_event_id,
		'expected_state_token' => $event_fault_before['state_token'],
		'event_name'           => 'Unauthorized Edit',
	)
);
$owned_location_denied = $mutations->update_location(
	array(
		'id'                   => $primary_location_id,
		'expected_state_token' => $location_fault_before['state_token'],
		'location_name'        => 'Unauthorized Edit',
	)
);
wp_set_current_user( $admin->ID );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $limited_id );
if ( ! is_wp_error( $publish_event_denied ) || 'cmsa_event_publish_permission' !== $publish_event_denied->get_error_code() || ! is_wp_error( $publish_location_denied ) || 'cmsa_location_publish_permission' !== $publish_location_denied->get_error_code() ) {
	$fail( 'publish authority was not enforced.' );
}
if ( ! is_wp_error( $owned_event_denied ) || 'cmsa_event_update_permission' !== $owned_event_denied->get_error_code() || ! is_wp_error( $owned_location_denied ) || 'cmsa_location_update_permission' !== $owned_location_denied->get_error_code() ) {
	$fail( 'object-level event/location authority was not enforced.' );
}

$unrelated_event_after = $reader->get_event( $unrelated_event_id );
$unrelated_location_after = $reader->get_location( $unrelated_location_id );
if ( is_wp_error( $unrelated_event_after ) || $unrelated_event_state !== $unrelated_event_after['event']['state_token'] || is_wp_error( $unrelated_location_after ) || $unrelated_location_state !== $unrelated_location_after['location']['state_token'] ) {
	$fail( 'unrelated event/location state changed.' );
}

foreach ( array_reverse( $created_event_ids ) as $id ) {
	$event = $reader->load_event( $id );
	if ( ! is_wp_error( $event ) ) {
		$event->delete( true );
	}
}
foreach ( array_reverse( $created_location_ids ) as $id ) {
	$location = $reader->load_location( $id );
	if ( ! is_wp_error( $location ) ) {
		$location->delete( true );
	}
}

echo "events-manager-mutation-cli: PASS event=create-update-conflict-rollback location=create-update-conflict-rollback permissions=publish-object isolation=unchanged flags=preserved\n";
