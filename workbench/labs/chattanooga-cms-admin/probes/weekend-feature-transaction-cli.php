<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "weekend-feature-transaction-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
if ( ! class_exists( 'CMS_Weekend_Posts' ) || ! defined( 'CMS_CORE_VERSION' ) || '0.2.2' !== CMS_CORE_VERSION ) {
	fwrite( STDERR, "weekend-feature-transaction-cli: source Weekend Feature 0.2.2 is not active.\n" );
	exit( 1 );
}
if ( ! class_exists( 'EM_Events' ) || ! class_exists( 'EM_Event' ) || ! class_exists( 'EM_Location' ) ) {
	fwrite( STDERR, "weekend-feature-transaction-cli: Events Manager model missing.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "weekend-feature-transaction-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

$weekend = new CMSA_Weekend_Feature();
$reader = new CMSA_Events_Manager();
$mutations = new CMSA_Events_Manager_Mutations( $reader );
$created_event_ids = array();
$created_location_ids = array();
$created_post_ids = array();
$initial_option_exists = false !== get_option( CMS_Weekend_Posts::OPTION_SETTINGS, false );
$initial_option = get_option( CMS_Weekend_Posts::OPTION_SETTINGS, array() );
$initial_cron = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( CMS_Weekend_Posts::CRON_HOOK ) : false;

$cleanup = static function () use ( &$created_event_ids, &$created_location_ids, &$created_post_ids, $reader, $initial_option_exists, $initial_option, $initial_cron ) {
	foreach ( array_reverse( $created_post_ids ) as $id ) {
		if ( get_post( $id ) ) {
			wp_delete_post( $id, true );
		}
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
	wp_clear_scheduled_hook( CMS_Weekend_Posts::CRON_HOOK );
	if ( $initial_option_exists ) {
		update_option( CMS_Weekend_Posts::OPTION_SETTINGS, $initial_option, false );
	} else {
		delete_option( CMS_Weekend_Posts::OPTION_SETTINGS );
	}
	wp_clear_scheduled_hook( CMS_Weekend_Posts::CRON_HOOK );
	if ( $initial_cron ) {
		wp_schedule_event( (int) $initial_cron->timestamp, (string) $initial_cron->schedule, CMS_Weekend_Posts::CRON_HOOK, isset( $initial_cron->args ) ? (array) $initial_cron->args : array() );
	}
};

$fail = static function ( $message ) use ( $cleanup ) {
	$cleanup();
	fwrite( STDERR, "weekend-feature-transaction-cli: {$message}\n" );
	exit( 1 );
};

wp_clear_scheduled_hook( CMS_Weekend_Posts::CRON_HOOK );
delete_option( CMS_Weekend_Posts::OPTION_SETTINGS );
CMS_Weekend_Posts::instance()->settings_updated();

$initial = $weekend->get_status();
if ( is_wp_error( $initial ) || empty( $initial['weekend']['key'] ) || null !== $initial['feature'] ) {
	$fail( 'initial bounded status read failed.' );
}
$week_key = (string) $initial['weekend']['key'];
$event_date = substr( (string) $initial['weekend']['start'], 0, 10 );

$location_result = $mutations->create_location(
	array(
		'location_name'     => 'CMSA Weekend Fixture Venue ' . strtolower( wp_generate_password( 6, false, false ) ),
		'post_status'       => 'publish',
		'location_address'  => '100 Weekend Test Way',
		'location_town'     => 'Chattanooga',
		'location_state'    => 'TN',
		'location_postcode' => '37402',
		'location_country'  => 'US',
	)
);
if ( is_wp_error( $location_result ) || empty( $location_result['created'] ) ) {
	$fail( 'fixture location creation failed.' );
}
$location_id = (int) $location_result['location']['id'];
$created_location_ids[] = $location_id;

$event_result = $mutations->create_event(
	array(
		'event_name'       => 'CMSA Weekend Fixture Event ' . strtolower( wp_generate_password( 6, false, false ) ),
		'content'          => '<p>Disposable Weekend Feature event fixture.</p>',
		'post_status'      => 'publish',
		'event_start_date' => $event_date,
		'event_end_date'   => $event_date,
		'event_start_time' => '19:00',
		'event_end_time'   => '21:00',
		'event_timezone'   => 'America/New_York',
		'location_id'      => $location_id,
	)
);
if ( is_wp_error( $event_result ) || empty( $event_result['created'] ) ) {
	$fail( 'fixture event creation failed.' );
}
$event_id = (int) $event_result['event']['id'];
$created_event_ids[] = $event_id;
$event_state = $event_result['event']['state_token'];

$control_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'CMSA Weekend Unrelated Control',
		'post_content' => 'Must remain unchanged.',
	),
	true
);
if ( is_wp_error( $control_post_id ) ) {
	$fail( 'unrelated control post creation failed.' );
}
$control_post_id = (int) $control_post_id;
$created_post_ids[] = $control_post_id;
$control_before = get_post( $control_post_id );
$control_semantic = array( $control_before->post_status, $control_before->post_title, $control_before->post_content, $control_before->post_modified_gmt );

$status = $weekend->get_status();
if ( is_wp_error( $status ) || 1 !== (int) $status['event_count'] || null !== $status['feature'] ) {
	$fail( 'Weekend Feature status did not see exactly the disposable event and absent guide.' );
}
$initial_settings_token = $status['settings_state_token'];

$stale_settings = $weekend->update_settings(
	array(
		'expected_settings_state_token' => str_repeat( '0', 64 ),
		'enabled'                       => true,
		'publish_time'                  => '08:00',
		'post_author'                   => $admin->ID,
		'introduction'                  => 'Stale request must not persist.',
		'closing'                       => 'Stale request must not persist.',
	)
);
if ( ! is_wp_error( $stale_settings ) || 'cmsa_weekend_settings_conflict' !== $stale_settings->get_error_code() ) {
	$fail( 'stale settings mutation did not fail closed.' );
}
if ( $initial_settings_token !== $weekend->get_status()['settings_state_token'] ) {
	$fail( 'stale settings mutation changed state.' );
}

$invalid_enabled = $weekend->update_settings(
	array(
		'expected_settings_state_token' => $initial_settings_token,
		'enabled'                       => true,
		'publish_time'                  => '',
		'post_author'                   => $admin->ID,
		'introduction'                  => 'Invalid enabled configuration.',
		'closing'                       => 'Invalid enabled configuration.',
	)
);
if ( ! is_wp_error( $invalid_enabled ) || 'cmsa_weekend_settings_enabled' !== $invalid_enabled->get_error_code() ) {
	$fail( 'invalid enabled settings did not fail closed.' );
}

$settings_update = $weekend->update_settings(
	array(
		'expected_settings_state_token' => $initial_settings_token,
		'enabled'                       => true,
		'publish_time'                  => '08:00',
		'post_author'                   => $admin->ID,
		'introduction'                  => 'Disposable Weekend Feature introduction.',
		'closing'                       => 'Disposable Weekend Feature closing.',
	)
);
if ( is_wp_error( $settings_update ) || empty( $settings_update['updated'] ) || empty( $settings_update['schedule']['scheduled'] ) || 'cms_weekly' !== $settings_update['schedule']['schedule'] ) {
	$fail( 'valid settings mutation or schedule verification failed.' );
}
$settings_state = $weekend->get_status();
if ( '08:00' !== $settings_state['settings']['publish_time'] || 1 !== (int) $settings_state['settings']['enabled'] || (int) $admin->ID !== (int) $settings_state['settings']['post_author'] ) {
	$fail( 'settings readback mismatch.' );
}

$no_change = $weekend->update_settings(
	array(
		'expected_settings_state_token' => $settings_state['settings_state_token'],
		'enabled'                       => true,
		'publish_time'                  => '08:00',
		'post_author'                   => $admin->ID,
		'introduction'                  => 'Disposable Weekend Feature introduction.',
		'closing'                       => 'Disposable Weekend Feature closing.',
	)
);
if ( ! is_wp_error( $no_change ) || 'cmsa_weekend_settings_no_change' !== $no_change->get_error_code() ) {
	$fail( 'settings no-change guard failed.' );
}

$settings_fault = static function () { return false; };
add_filter( 'cmsa_weekend_feature_verify_settings', $settings_fault, 10, 4 );
$fault_result = $weekend->update_settings(
	array(
		'expected_settings_state_token' => $settings_state['settings_state_token'],
		'enabled'                       => true,
		'publish_time'                  => '08:00',
		'post_author'                   => $admin->ID,
		'introduction'                  => 'Injected settings verification failure.',
		'closing'                       => 'Disposable Weekend Feature closing.',
	)
);
remove_filter( 'cmsa_weekend_feature_verify_settings', $settings_fault, 10 );
if ( ! is_wp_error( $fault_result ) || 'cmsa_weekend_settings_verify' !== $fault_result->get_error_code() || true !== (bool) $fault_result->get_error_data()['rolled_back'] ) {
	$fail( 'settings verification fault did not report successful rollback.' );
}
$status_after_settings_fault = $weekend->get_status();
if ( $settings_state['settings_state_token'] !== $status_after_settings_fault['settings_state_token'] ) {
	$fail( 'settings rollback did not restore exact settings/schedule state token.' );
}

$stale_week = $weekend->generate(
	array(
		'expected_week_key'            => '2000-01-01',
		'expected_feature_id'          => 0,
		'expected_feature_state_token' => '',
	),
	'draft'
);
if ( ! is_wp_error( $stale_week ) || 'cmsa_weekend_week_conflict' !== $stale_week->get_error_code() ) {
	$fail( 'stale weekend window did not fail closed.' );
}

$new_fault = static function () { return false; };
add_filter( 'cmsa_weekend_feature_verify_generation', $new_fault, 10, 4 );
$new_fault_result = $weekend->generate(
	array(
		'expected_week_key'            => $week_key,
		'expected_feature_id'          => 0,
		'expected_feature_state_token' => '',
	),
	'draft'
);
remove_filter( 'cmsa_weekend_feature_verify_generation', $new_fault, 10 );
if ( ! is_wp_error( $new_fault_result ) || 'cmsa_weekend_generate_verify' !== $new_fault_result->get_error_code() || true !== (bool) $new_fault_result->get_error_data()['rolled_back'] ) {
	$fail( 'new-feature verification fault did not remove the new target.' );
}
if ( null !== $weekend->get_status()['feature'] ) {
	$fail( 'new-feature rollback left an orphaned Weekend Feature.' );
}

$draft = $weekend->generate(
	array(
		'expected_week_key'            => $week_key,
		'expected_feature_id'          => 0,
		'expected_feature_state_token' => '',
	),
	'draft'
);
if ( is_wp_error( $draft ) || empty( $draft['generated'] ) || ! empty( $draft['published'] ) || 'draft' !== $draft['feature']['status'] || $week_key !== $draft['feature']['week_key'] ) {
	$fail( 'draft generation/readback failed.' );
}
$feature_id = (int) $draft['feature']['id'];
$created_post_ids[] = $feature_id;
$draft_before_fault = $draft['feature'];

$existing_fault = static function () { return false; };
add_filter( 'cmsa_weekend_feature_verify_generation', $existing_fault, 10, 4 );
$existing_fault_result = $weekend->generate(
	array(
		'expected_week_key'            => $week_key,
		'expected_feature_id'          => $feature_id,
		'expected_feature_state_token' => $draft_before_fault['state_token'],
	),
	'draft'
);
remove_filter( 'cmsa_weekend_feature_verify_generation', $existing_fault, 10 );
if ( ! is_wp_error( $existing_fault_result ) || 'cmsa_weekend_generate_verify' !== $existing_fault_result->get_error_code() || true !== (bool) $existing_fault_result->get_error_data()['rolled_back'] ) {
	$fail( 'existing-draft verification fault did not report successful rollback.' );
}
$after_existing_fault = $weekend->get_status()['feature'];
if ( ! $after_existing_fault || $draft_before_fault['state_token'] !== $after_existing_fault['state_token'] ) {
	$fail( 'existing-draft rollback did not restore exact semantic state.' );
}

$published = $weekend->generate(
	array(
		'expected_week_key'            => $week_key,
		'expected_feature_id'          => $feature_id,
		'expected_feature_state_token' => $after_existing_fault['state_token'],
	),
	'publish'
);
if ( is_wp_error( $published ) || empty( $published['published'] ) || 'publish' !== $published['feature']['status'] || $week_key !== $published['feature']['week_key'] ) {
	$fail( 'disposable immediate publication/readback failed.' );
}

$repeat_publish = $weekend->generate(
	array(
		'expected_week_key'            => $week_key,
		'expected_feature_id'          => $feature_id,
		'expected_feature_state_token' => $published['feature']['state_token'],
	),
	'publish'
);
if ( ! is_wp_error( $repeat_publish ) || 'cmsa_weekend_feature_published' !== $repeat_publish->get_error_code() ) {
	$fail( 'already-published overwrite guard failed.' );
}

$control_after = get_post( $control_post_id );
$control_after_semantic = array( $control_after->post_status, $control_after->post_title, $control_after->post_content, $control_after->post_modified_gmt );
if ( $control_semantic !== $control_after_semantic ) {
	$fail( 'unrelated WordPress control post changed.' );
}
$event_after = $reader->get_event( $event_id );
if ( is_wp_error( $event_after ) || $event_state !== $event_after['event']['state_token'] ) {
	$fail( 'source event changed during Weekend Feature administration.' );
}

$cleanup();
echo "weekend-feature-transaction-cli: PASS status settings stale no-change schedule rollback draft publish generation-rollback isolation\n";
