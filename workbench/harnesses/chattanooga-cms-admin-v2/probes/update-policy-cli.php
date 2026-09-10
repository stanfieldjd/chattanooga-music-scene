<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$plugin_ability = wp_get_ability( 'chattanooga-cms-admin/set-plugin-auto-update' );
$theme_ability  = wp_get_ability( 'chattanooga-cms-admin/set-theme-auto-update' );
if ( ! $plugin_ability instanceof WP_Ability || ! $theme_ability instanceof WP_Ability ) {
	fwrite( STDERR, "Update policy abilities are missing.\n" );
	exit( 1 );
}

$plugin = 'orbit-ability/orbit-ability.php';
$theme  = get_stylesheet();
if ( ! isset( get_plugins()[ $plugin ] ) || ! wp_get_theme( $theme )->exists() ) {
	fwrite( STDERR, "Installed update-policy targets are missing.\n" );
	exit( 1 );
}

$plugin_before = array_values( array_unique( array_map( 'strval', (array) get_site_option( 'auto_update_plugins', array() ) ) ) );
$theme_before  = array_values( array_unique( array_map( 'strval', (array) get_site_option( 'auto_update_themes', array() ) ) ) );
$plugin_initial = in_array( $plugin, $plugin_before, true );
$theme_initial  = in_array( $theme, $theme_before, true );

$plugin_first = $plugin_ability->execute( array( 'plugin' => $plugin, 'enabled' => ! $plugin_initial ) );
if ( is_wp_error( $plugin_first ) || (bool) ( $plugin_first['enabled'] ?? $plugin_initial ) === $plugin_initial ) {
	fwrite( STDERR, "Plugin update policy did not change as requested.\n" );
	exit( 1 );
}
$plugin_restore = $plugin_ability->execute( array( 'plugin' => $plugin, 'enabled' => $plugin_initial ) );
if ( is_wp_error( $plugin_restore ) || (bool) ( $plugin_restore['enabled'] ?? ! $plugin_initial ) !== $plugin_initial ) {
	fwrite( STDERR, "Plugin update policy did not restore its original state.\n" );
	exit( 1 );
}

$theme_first = $theme_ability->execute( array( 'stylesheet' => $theme, 'enabled' => ! $theme_initial ) );
if ( is_wp_error( $theme_first ) || (bool) ( $theme_first['enabled'] ?? $theme_initial ) === $theme_initial ) {
	fwrite( STDERR, "Theme update policy did not change as requested.\n" );
	exit( 1 );
}
$theme_restore = $theme_ability->execute( array( 'stylesheet' => $theme, 'enabled' => $theme_initial ) );
if ( is_wp_error( $theme_restore ) || (bool) ( $theme_restore['enabled'] ?? ! $theme_initial ) !== $theme_initial ) {
	fwrite( STDERR, "Theme update policy did not restore its original state.\n" );
	exit( 1 );
}

$plugin_after = array_values( array_unique( array_map( 'strval', (array) get_site_option( 'auto_update_plugins', array() ) ) ) );
$theme_after  = array_values( array_unique( array_map( 'strval', (array) get_site_option( 'auto_update_themes', array() ) ) ) );
sort( $plugin_before );
sort( $plugin_after );
sort( $theme_before );
sort( $theme_after );
if ( $plugin_before !== $plugin_after || $theme_before !== $theme_after ) {
	fwrite( STDERR, "Update policy test left persistent state drift.\n" );
	exit( 1 );
}

$missing_plugin = $plugin_ability->execute( array( 'plugin' => 'not-installed/not-installed.php', 'enabled' => true ) );
$missing_theme  = $theme_ability->execute( array( 'stylesheet' => 'not-installed-theme', 'enabled' => true ) );
if ( ! is_wp_error( $missing_plugin ) || 'cmsa_asset_not_found' !== $missing_plugin->get_error_code()
	|| ! is_wp_error( $missing_theme ) || 'cmsa_asset_not_found' !== $missing_theme->get_error_code() ) {
	fwrite( STDERR, "Unknown component policy did not fail closed.\n" );
	exit( 1 );
}

$blocked = static function ( $new, $old ) {
	return $old;
};
add_filter( 'pre_update_site_option_auto_update_plugins', $blocked, 10, 2 );
$blocked_result = $plugin_ability->execute( array( 'plugin' => $plugin, 'enabled' => ! $plugin_initial ) );
remove_filter( 'pre_update_site_option_auto_update_plugins', $blocked, 10 );
if ( ! is_wp_error( $blocked_result ) || 'cmsa_auto_update_policy_verification_failed' !== $blocked_result->get_error_code() ) {
	fwrite( STDERR, "Failed policy persistence was not detected.\n" );
	exit( 1 );
}
$current_plugin_policy = array_values( array_unique( array_map( 'strval', (array) get_site_option( 'auto_update_plugins', array() ) ) ) );
sort( $current_plugin_policy );
if ( $current_plugin_policy !== $plugin_before ) {
	fwrite( STDERR, "Failed policy persistence changed stored state.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( false !== $plugin_ability->check_permissions( array( 'plugin' => $plugin, 'enabled' => true ) )
	|| false !== $theme_ability->check_permissions( array( 'stylesheet' => $theme, 'enabled' => true ) ) ) {
	fwrite( STDERR, "Anonymous update policy administration was not blocked.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
echo "cmsa-v2-update-policy: PASS plugin_policy=verified theme_policy=verified unknown_targets=closed persistence_failure=detected state_restored=verified admin_boundary=verified\n";
exit( 0 );
