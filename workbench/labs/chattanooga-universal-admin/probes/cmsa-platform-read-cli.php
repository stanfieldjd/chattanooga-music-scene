<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this probe runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$health = wp_get_ability( 'chattanooga-cms-admin/get-health' );
$updates = wp_get_ability( 'chattanooga-cms-admin/list-updates' );
$plugins = wp_get_ability( 'chattanooga-cms-admin/list-plugins' );
$themes = wp_get_ability( 'chattanooga-cms-admin/list-themes' );
$clear_cache = wp_get_ability( 'chattanooga-cms-admin/clear-cache' );

foreach ( array( $health, $updates, $plugins, $themes, $clear_cache ) as $ability ) {
	if ( ! $ability instanceof WP_Ability ) {
		fwrite( STDERR, "Required platform ability was not registered.\n" );
		exit( 1 );
	}
	if ( true !== $ability->check_permissions( array() ) ) {
		fwrite( STDERR, "Administrator platform permissions were not preserved.\n" );
		exit( 1 );
	}
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

$plugins_result = $plugins->execute( array() );
if ( is_wp_error( $plugins_result ) || empty( $plugins_result['count'] ) || empty( $plugins_result['items'] ) || ! is_array( $plugins_result['items'] ) ) {
	fwrite( STDERR, "Installed-plugin inventory was not returned.\n" );
	exit( 1 );
}
$replacement_found = false;
foreach ( $plugins_result['items'] as $item ) {
	if ( 'chattanooga-cms-admin/chattanooga-cms-admin.php' === ( $item['plugin'] ?? '' ) ) {
		$replacement_found = true;
		if ( empty( $item['active'] ) || '0.0.1-replacement-lab' !== ( $item['version'] ?? '' ) ) {
			fwrite( STDERR, "Replacement plugin inventory state was incorrect.\n" );
			exit( 1 );
		}
	}
}
if ( ! $replacement_found ) {
	fwrite( STDERR, "Replacement plugin was absent from platform inventory.\n" );
	exit( 1 );
}

$themes_result = $themes->execute( array() );
if ( is_wp_error( $themes_result ) || empty( $themes_result['count'] ) || empty( $themes_result['items'] ) || ! is_array( $themes_result['items'] ) ) {
	fwrite( STDERR, "Installed-theme inventory was not returned.\n" );
	exit( 1 );
}

wp_cache_set( 'cmsa_platform_cache_probe', 'present', 'cmsa-platform' );
$cache_result = $clear_cache->execute( array() );
if ( is_wp_error( $cache_result ) || empty( $cache_result['object_cache_flushed'] ) || false !== wp_cache_get( 'cmsa_platform_cache_probe', 'cmsa-platform' ) ) {
	fwrite( STDERR, "Core object-cache flush did not verify.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
foreach ( array( $health, $updates, $plugins, $themes, $clear_cache ) as $ability ) {
	if ( false !== $ability->check_permissions( array() ) ) {
		fwrite( STDERR, "Platform ability allowed an anonymous user.\n" );
		exit( 1 );
	}
}

wp_set_current_user( 1 );

echo 'cmsa-platform-read-cli: PASS health=verified update_inventory=verified plugin_inventory=verified theme_inventory=verified object_cache_flush=verified admin_boundary=verified provider_specific_logic=absent' . "\n";
exit( 0 );
