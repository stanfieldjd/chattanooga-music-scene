<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "events-manager-deletion-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "events-manager-deletion-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

$events = new CMSA_Events_Manager();
$deletion = new CMSA_Events_Manager_Deletion( $events );
$token = strtolower( wp_generate_password( 8, false, false ) );
$event_ids = array();
$location_ids = array();
$user_ids = array();
$booking_ids = array();

$cleanup = static function () use ( &$event_ids, &$location_ids, &$user_ids, &$booking_ids, $admin ) {
	wp_set_current_user( $admin->ID );
	global $wpdb;
	if ( defined( 'EM_BOOKINGS_TABLE' ) ) {
		foreach ( $booking_ids as $booking_id ) {
			$wpdb->delete( EM_BOOKINGS_TABLE, array( 'booking_id' => (int) $booking_id ), array( '%d' ) );
		}
	}
	foreach ( array_reverse( $event_ids ) as $event_id ) {
		$event = new EM_Event( (int) $event_id, 'event_id' );
		if ( $event instanceof EM_Event && ! empty( $event->event_id ) ) {
			$event->delete( true );
		}
	}
	foreach ( array_reverse( $location_ids ) as $location_id ) {
		$location = new EM_Location( (int) $location_id, 'location_id' );
		if ( $location instanceof EM_Location && ! empty( $location->location_id ) ) {
			$location->delete( true );
		}
	}
	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}
	foreach ( array_reverse( $user_ids ) as $user_id ) {
		wp_delete_user( (int) $user_id );
	}
};
$fail = static function ( $message ) use ( $cleanup ) {
	$cleanup();
	fwrite( STDERR, "events-manager-deletion-cli: {$message}\n" );
	exit( 1 );
};

$location = new EM_Location();
$location->location_name = 'CMSA Delete Venue ' . $token;
$location->location_address = '1 Delete Gate Way';
$location->location_town = 'Chattanooga';
$location->location_state = 'TN';
$location->location_postcode = '37402';
$location->location_country = 'US';
$location->location_owner = $admin->ID;
if ( ! $location->save() || empty( $location->location_id ) ) {
	$fail( 'location fixture save failed.' );
}
$location_id = (int) $location->location_id;
$location_ids[] = $location_id;
$location_before = $events->get_location( $location_id );
if ( is_wp_error( $location_before ) ) {
	$fail( 'location fixture read failed.' );
}
$location_state = $location_before['location']['state_token'];

$make_event = static function ( $name, $owner_id, $location_id ) {
	$event = new EM_Event();
	$event->event_name = $name;
	$event->event_owner = (int) $owner_id;
	$event->event_start_date = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS * 30 );
	$event->event_end_date = $event->event_start_date;
	$event->event_start_time = '19:00:00';
	$event->event_end_time = '21:00:00';
	$event->event_status = 1;
	$event->location_id = (int) $location_id;
	return $event->save() ? $event : false;
};

$target = $make_event( 'CMSA Delete Target ' . $token, $admin->ID, $location_id );
$control = $make_event( 'CMSA Delete Control ' . $token, $admin->ID, $location_id );
if ( ! $target || ! $control ) {
	$fail( 'event fixture save failed.' );
}
$target_id = (int) $target->event_id;
$control_id = (int) $control->event_id;
$event_ids[] = $target_id;
$event_ids[] = $control_id;
$target_read = $events->get_event( $target_id );
$control_read = $events->get_event( $control_id );
if ( is_wp_error( $target_read ) || is_wp_error( $control_read ) ) {
	$fail( 'event fixture read failed.' );
}
$control_state = $control_read['event']['state_token'];

$stale_trash = $deletion->trash_event( array( 'id' => $target_id, 'expected_state_token' => str_repeat( '0', 64 ) ) );
if ( ! is_wp_error( $stale_trash ) || 'cmsa_event_delete_conflict' !== $stale_trash->get_error_code() ) {
	$fail( 'stale event trash did not fail closed.' );
}

$trashed = $deletion->trash_event( array( 'id' => $target_id, 'expected_state_token' => $target_read['event']['state_token'] ) );
if ( is_wp_error( $trashed ) || empty( $trashed['trashed'] ) || 'trash' !== $trashed['event']['post_status'] ) {
	$fail( 'event trash/readback failed.' );
}
if ( ! get_post( (int) $trashed['event']['post_id'] ) instanceof WP_Post ) {
	$fail( 'event trash unexpectedly removed backing post.' );
}
$after_trash_location = $events->get_location( $location_id );
$after_trash_control = $events->get_event( $control_id );
if ( is_wp_error( $after_trash_location ) || $location_state !== $after_trash_location['location']['state_token'] || is_wp_error( $after_trash_control ) || $control_state !== $after_trash_control['event']['state_token'] ) {
	$fail( 'event trash changed referenced venue or unrelated event.' );
}
$already_trashed = $deletion->trash_event( array( 'id' => $target_id, 'expected_state_token' => $trashed['event']['state_token'] ) );
if ( ! is_wp_error( $already_trashed ) || 'cmsa_event_already_trashed' !== $already_trashed->get_error_code() ) {
	$fail( 'repeat event trash did not fail closed.' );
}

$control_delete = $deletion->delete_event(
	array(
		'id'                       => $control_id,
		'expected_state_token'     => $control_state,
		'confirm_permanent_delete' => true,
	)
);
if ( ! is_wp_error( $control_delete ) || 'cmsa_event_delete_requires_trash' !== $control_delete->get_error_code() ) {
	$fail( 'permanent deletion of non-trashed event did not fail closed.' );
}

$stale_delete = $deletion->delete_event(
	array(
		'id'                       => $target_id,
		'expected_state_token'     => str_repeat( 'f', 64 ),
		'confirm_permanent_delete' => true,
	)
);
if ( ! is_wp_error( $stale_delete ) || 'cmsa_event_delete_conflict' !== $stale_delete->get_error_code() ) {
	$fail( 'stale permanent event deletion did not fail closed.' );
}
$unconfirmed_delete = $deletion->delete_event(
	array(
		'id'                       => $target_id,
		'expected_state_token'     => $trashed['event']['state_token'],
		'confirm_permanent_delete' => false,
	)
);
if ( ! is_wp_error( $unconfirmed_delete ) || 'cmsa_event_delete_confirmation' !== $unconfirmed_delete->get_error_code() ) {
	$fail( 'unconfirmed permanent event deletion did not fail closed.' );
}

$limited_login = 'cmsa_event_object_delete_' . $token;
$limited_id = wp_create_user( $limited_login, wp_generate_password( 20, true, true ), $limited_login . '@example.invalid' );
if ( is_wp_error( $limited_id ) ) {
	$fail( 'limited deletion user creation failed.' );
}
$limited_id = (int) $limited_id;
$user_ids[] = $limited_id;
$limited = new WP_User( $limited_id );
$limited->set_role( 'subscriber' );
$limited->add_cap( 'delete_events' );
wp_set_current_user( $limited_id );
$object_denied = $deletion->delete_event(
	array(
		'id'                       => $target_id,
		'expected_state_token'     => $trashed['event']['state_token'],
		'confirm_permanent_delete' => true,
	)
);
if ( ! is_wp_error( $object_denied ) || 'cmsa_event_delete_permission' !== $object_denied->get_error_code() ) {
	$fail( 'object-level delete_others_events authority was not enforced.' );
}
wp_set_current_user( $admin->ID );

$deleted = $deletion->delete_event(
	array(
		'id'                       => $target_id,
		'expected_state_token'     => $trashed['event']['state_token'],
		'confirm_permanent_delete' => true,
	)
);
if ( is_wp_error( $deleted ) ) {
	fwrite( STDERR, 'events-manager-deletion-cli diagnostic: ' . wp_json_encode( array( 'code' => $deleted->get_error_code(), 'message' => $deleted->get_error_message(), 'data' => $deleted->get_error_data() ) ) . "\n" );
}
if ( is_wp_error( $deleted ) || empty( $deleted['deleted'] ) || 0 !== (int) $deleted['booking_count'] ) {
	$fail( 'permanent event deletion failed.' );
}
if ( ! is_wp_error( $events->load_event( $target_id ) ) || get_post( (int) $target_read['event']['post_id'] ) instanceof WP_Post ) {
	$fail( 'permanent event deletion did not remove event identity and backing post.' );
}
$event_ids = array_values( array_diff( $event_ids, array( $target_id ) ) );
$after_delete_location = $events->get_location( $location_id );
$after_delete_control = $events->get_event( $control_id );
if ( is_wp_error( $after_delete_location ) || $location_state !== $after_delete_location['location']['state_token'] || is_wp_error( $after_delete_control ) || $control_state !== $after_delete_control['event']['state_token'] ) {
	$fail( 'permanent event deletion changed referenced venue or unrelated event.' );
}

$booked = $make_event( 'CMSA Booked Delete Guard ' . $token, $admin->ID, $location_id );
if ( ! $booked ) {
	$fail( 'booked event fixture save failed.' );
}
$booked_id = (int) $booked->event_id;
$event_ids[] = $booked_id;
$booked_read = $events->get_event( $booked_id );
if ( is_wp_error( $booked_read ) ) {
	$fail( 'booked event fixture read failed.' );
}
$booked_trashed = $deletion->trash_event( array( 'id' => $booked_id, 'expected_state_token' => $booked_read['event']['state_token'] ) );
if ( is_wp_error( $booked_trashed ) ) {
	$fail( 'booked event trash failed.' );
}
global $wpdb;
$inserted = $wpdb->insert(
	EM_BOOKINGS_TABLE,
	array(
		'event_id'        => $booked_id,
		'person_id'       => $admin->ID,
		'booking_spaces'  => 1,
		'booking_comment' => 'CMSA disposable deletion guard',
		'booking_status'  => 1,
		'booking_price'   => 0,
		'booking_meta'    => serialize( array() ),
	),
	array( '%d', '%d', '%d', '%s', '%d', '%f', '%s' )
);
if ( false === $inserted ) {
	$fail( 'booking guard fixture insert failed.' );
}
$booking_id = (int) $wpdb->insert_id;
$booking_ids[] = $booking_id;
$booked_delete = $deletion->delete_event(
	array(
		'id'                       => $booked_id,
		'expected_state_token'     => $booked_trashed['event']['state_token'],
		'confirm_permanent_delete' => true,
	)
);
if ( ! is_wp_error( $booked_delete ) || 'cmsa_event_delete_has_bookings' !== $booked_delete->get_error_code() || 1 !== (int) $booked_delete->get_error_data()['booking_count'] ) {
	$fail( 'event with booking was not refused by permanent deletion guard.' );
}
if ( ! ( get_post( (int) $booked_trashed['event']['post_id'] ) instanceof WP_Post ) || ! $wpdb->get_var( $wpdb->prepare( 'SELECT booking_id FROM ' . EM_BOOKINGS_TABLE . ' WHERE booking_id = %d', $booking_id ) ) ) {
	$fail( 'booking guard refusal changed the event or booking.' );
}

$cleanup();
echo "events-manager-deletion-cli: PASS trash=conflict-readback-idempotence-venue-isolation permanent=trash-prerequisite-conflict-confirmation-object-authority-absence booking-guard=refused-preserved unrelated=unchanged\n";
