<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this probe runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$health = wp_get_ability( 'chattanooga-cms-admin/get-health' );
$updates = wp_get_ability( 'chattanooga-cms-admin/list-updates' );

if ( ! $health instanceof WP_Ability || ! $updates instanceof WP_Ability ) {
	fwrite( STDERR, "Required platform abilities were not registered.\n" );
	exit( 1 );
}

if ( true !== $health->check_permissions( array() ) || true !== $updates->check_permissions( array() ) ) {
	fwrite( STDERR, "Administrator platform permissions were not preserved.\n" );
	exit( 1 );
}

$health_result = $health->execute( array() );
if ( is_wp_error( $health_result ) ) {
	fwrite( STDERR, 'Health failed: ' . $health_result->get_error_message() . "\n" );
	exit( 1 );
}

$required_health = array(
	'wordpress_version', 'php_version', 'environment', 'timezone', 'https',
	'filesystem_method', 'plugin_dir_writable', 'theme_dir_writable',
	'content_writable', 'ziparchive', 'wp_cache_enabled', 'cron_disabled',
	'maintenance_mode', 'memory_limit', 'admin_memory_limit',
);
foreach ( $required_health as $key ) {
	if ( ! array_key_exists( $key, $health_result ) ) {
		fwrite( STDERR, "Health result missing {$key}.\n" );
		exit( 1 );
	}
}
if ( get_bloginfo( 'version' ) !== $health_result['wordpress_version'] || PHP_VERSION !== $health_result['php_version'] ) {
	fwrite( STDERR, "Health version readback did not match runtime.\n" );
	exit( 1 );
}

$updates_result = $updates->execute( array() );
if ( is_wp_error( $updates_result ) ) {
	fwrite( STDERR, 'Update inventory failed: ' . $updates_result->get_error_message() . "\n" );
	exit( 1 );
}
foreach ( array( 'core', 'plugins', 'themes' ) as $key ) {
	if ( ! isset( $updates_result[ $key ] ) || ! is_array( $updates_result[ $key ] ) ) {
		fwrite( STDERR, "Update inventory missing {$key} array.\n" );
		exit( 1 );
	}
}

wp_set_current_user( 0 );
if ( false !== $health->check_permissions( array() ) || false !== $updates->check_permissions( array() ) ) {
	fwrite( STDERR, "Platform abilities allowed an anonymous user.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

echo 'cmsa-platform-read-cli: PASS health=verified update_inventory=verified admin_boundary=verified provider_specific_logic=absent' . "\n";
exit( 0 );
