<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "weekend-feature-settings-diagnostic-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
$admin = get_user_by( 'login', 'admin' );
if ( ! $admin || ! class_exists( 'CMS_Weekend_Posts' ) ) {
	fwrite( STDERR, "weekend-feature-settings-diagnostic-cli: required fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
wp_clear_scheduled_hook( CMS_Weekend_Posts::CRON_HOOK );
delete_option( CMS_Weekend_Posts::OPTION_SETTINGS );
CMS_Weekend_Posts::instance()->settings_updated();
$adapter = new CMSA_Weekend_Feature();
$before = $adapter->get_status();
$verification_capture = null;
$capture = static function ( $verified, $requested, $after ) use ( &$verification_capture ) {
	$verification_capture = array(
		'verified_before_filter' => (bool) $verified,
		'requested'              => $requested,
		'after'                  => $after,
	);
	return $verified;
};
add_filter( 'cmsa_weekend_feature_verify_settings', $capture, 10, 3 );
$result = $adapter->update_settings(
	array(
		'expected_settings_state_token' => $before['settings_state_token'],
		'enabled'                       => true,
		'publish_time'                  => '08:00',
		'post_author'                   => $admin->ID,
		'introduction'                  => 'Diagnostic introduction.',
		'closing'                       => 'Diagnostic closing.',
	)
);
remove_filter( 'cmsa_weekend_feature_verify_settings', $capture, 10 );
$event = wp_get_scheduled_event( CMS_Weekend_Posts::CRON_HOOK );
$payload = array(
	'result_error'         => is_wp_error( $result ) ? $result->get_error_code() : '',
	'error_data'           => is_wp_error( $result ) ? $result->get_error_data() : null,
	'result'               => is_wp_error( $result ) ? null : $result,
	'pre_rollback_capture' => $verification_capture,
	'raw_option'           => get_option( CMS_Weekend_Posts::OPTION_SETTINGS, array() ),
	'cron'                 => $event ? array( 'timestamp' => (int) $event->timestamp, 'schedule' => (string) $event->schedule, 'local' => wp_date( DATE_ATOM, (int) $event->timestamp, wp_timezone() ) ) : null,
	'available_schedules'  => array_keys( wp_get_schedules() ),
);
echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
wp_clear_scheduled_hook( CMS_Weekend_Posts::CRON_HOOK );
delete_option( CMS_Weekend_Posts::OPTION_SETTINGS );
CMS_Weekend_Posts::instance()->settings_updated();
if ( is_wp_error( $result ) ) {
	exit( 1 );
}
echo "weekend-feature-settings-diagnostic-cli: PASS\n";
