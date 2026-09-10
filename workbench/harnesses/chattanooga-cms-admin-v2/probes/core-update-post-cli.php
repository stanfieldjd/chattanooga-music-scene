<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

$state_path = getenv( 'CMSA_V2_CORE_UPDATE_STATE' );
if ( ! is_string( $state_path ) || ! is_file( $state_path ) ) {
	fwrite( STDERR, "Synthetic core update state file is missing.\n" );
	exit( 1 );
}
$state = json_decode( (string) file_get_contents( $state_path ), true );
if ( ! is_array( $state ) || empty( $state['target'] ) || empty( $state['baseline'] ) || empty( $state['rollback_backup_id'] ) || empty( $state['marker_key'] ) ) {
	fwrite( STDERR, "Synthetic core update state file is invalid.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
if ( wp_get_wp_version() !== $state['target'] ) {
	fwrite( STDERR, "Fresh WordPress process did not load the synthetic target core version.\n" );
	exit( 1 );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_plugin_active( 'chattanooga-cms-admin/chattanooga-cms-admin.php' ) ) {
	fwrite( STDERR, "Replacement Chattanooga CMS Admin did not remain active after the core transition.\n" );
	exit( 1 );
}

$health = wp_get_ability( 'chattanooga-cms-admin/get-health' );
$restore = wp_get_ability( 'chattanooga-cms-admin/restore-core-backup' );
$verify = wp_get_ability( 'chattanooga-cms-admin/verify-backup' );
if ( ! $health instanceof WP_Ability || ! $restore instanceof WP_Ability || ! $verify instanceof WP_Ability ) {
	fwrite( STDERR, "Replacement abilities were not available in the fresh synthetic-target process.\n" );
	exit( 1 );
}
$health_result = $health->execute();
if ( is_wp_error( $health_result ) ) {
	fwrite( STDERR, "Replacement health ability failed after the synthetic core transition.\n" );
	exit( 1 );
}
$verified = $verify->execute( array( 'id' => $state['rollback_backup_id'] ) );
if ( is_wp_error( $verified ) || empty( $verified['valid'] ) ) {
	fwrite( STDERR, "Pre-update rollback snapshot was not valid in the fresh target process.\n" );
	exit( 1 );
}

$restore_result = $restore->execute( array( 'id' => $state['rollback_backup_id'] ) );
if ( is_wp_error( $restore_result ) || empty( $restore_result['restored'] ) || empty( $restore_result['rollback_backup_id'] ) || ( $restore_result['version'] ?? '' ) !== $state['baseline'] ) {
	fwrite( STDERR, 'Restoring the pre-update core snapshot failed: ' . ( is_wp_error( $restore_result ) ? $restore_result->get_error_code() . ' ' . $restore_result->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}

$version_contents = @file_get_contents( ABSPATH . 'wp-includes/version.php' );
if ( ! is_string( $version_contents ) || ! preg_match( '/\$wp_version\s*=\s*[\'\"]7\.1[\'\"]\s*;/', $version_contents ) ) {
	fwrite( STDERR, "Rollback did not restore WordPress 7.1 on disk.\n" );
	exit( 1 );
}
if ( get_option( $state['marker_key'] ) !== 'pre-update-database' ) {
	fwrite( STDERR, "Rollback did not restore the pre-update database marker.\n" );
	exit( 1 );
}

$state['post_update_health'] = true;
$state['restore_checkpoint_id'] = $restore_result['rollback_backup_id'];
$state['restored_disk_version'] = $state['baseline'];
$payload = wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $payload ) || false === file_put_contents( $state_path, $payload, LOCK_EX ) ) {
	fwrite( STDERR, "Could not persist post-update rollback verification state.\n" );
	exit( 1 );
}

echo "cmsa-v2-core-update-post: PASS fresh_target_boot=verified cms_admin_active=verified ability_health=verified rollback_snapshot=verified core_restore=verified database_restore=verified disk_version=7.1\n";
exit( 0 );
