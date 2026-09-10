<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$delete_plugin = wp_get_ability( 'chattanooga-cms-admin/delete-plugin' );
$delete_theme  = wp_get_ability( 'chattanooga-cms-admin/delete-theme' );
$restore       = wp_get_ability( 'chattanooga-cms-admin/restore-component-backup' );
if ( ! $delete_plugin instanceof WP_Ability || ! $delete_theme instanceof WP_Ability || ! $restore instanceof WP_Ability ) {
	fwrite( STDERR, "Component lifecycle abilities are missing.\n" );
	exit( 1 );
}

$plugin = 'saturn-lifecycle/saturn-lifecycle.php';
$theme  = 'meteor-lifecycle';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$plugins = get_plugins();
if ( ! isset( $plugins[ $plugin ] ) || '1.0.0' !== (string) $plugins[ $plugin ]['Version'] || ! is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "Lifecycle plugin fixture is not installed and active at version 1.0.0.\n" );
	exit( 1 );
}
search_theme_directories( true );
$themes = wp_get_themes( array( 'errors' => null ) );
if ( ! isset( $themes[ $theme ] ) || '1.0.0' !== (string) $themes[ $theme ]->get( 'Version' ) || get_stylesheet() === $theme ) {
	fwrite( STDERR, "Lifecycle theme fixture is not installed and inactive at version 1.0.0.\n" );
	exit( 1 );
}

$plugin_conflict = $delete_plugin->execute( array( 'plugin' => $plugin, 'expected_version' => '0.9.0', 'confirm_delete' => true ) );
if ( ! is_wp_error( $plugin_conflict ) || 'cmsa_plugin_version_conflict' !== $plugin_conflict->get_error_code() ) {
	fwrite( STDERR, "Plugin lifecycle did not enforce exact-version conflict control.\n" );
	exit( 1 );
}
$plugin_unconfirmed = $delete_plugin->execute( array( 'plugin' => $plugin, 'expected_version' => '1.0.0', 'confirm_delete' => false ) );
if ( ! is_wp_error( $plugin_unconfirmed ) || 'cmsa_delete_not_confirmed' !== $plugin_unconfirmed->get_error_code() ) {
	fwrite( STDERR, "Plugin lifecycle did not require explicit deletion confirmation.\n" );
	exit( 1 );
}

$self_plugin = plugin_basename( CUA_DIR . 'chattanooga-cms-admin.php' );
$self_data = get_plugins()[ $self_plugin ] ?? null;
$self_delete = $delete_plugin->execute(
	array(
		'plugin' => $self_plugin,
		'expected_version' => is_array( $self_data ) ? (string) $self_data['Version'] : CUA_VERSION,
		'confirm_delete' => true,
	)
);
if ( ! is_wp_error( $self_delete ) || 'cmsa_self_delete_forbidden' !== $self_delete->get_error_code() || ! is_plugin_active( $self_plugin ) ) {
	fwrite( STDERR, "Control-plane self deletion was not blocked.\n" );
	exit( 1 );
}

$plugin_delete = $delete_plugin->execute( array( 'plugin' => $plugin, 'expected_version' => '1.0.0', 'confirm_delete' => true ) );
if ( is_wp_error( $plugin_delete ) || empty( $plugin_delete['deleted'] ) || empty( $plugin_delete['rollback_id'] ) ) {
	fwrite( STDERR, 'Plugin reversible deletion failed: ' . ( is_wp_error( $plugin_delete ) ? $plugin_delete->get_error_code() . ' ' . $plugin_delete->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}
wp_clean_plugins_cache( false );
if ( isset( get_plugins()[ $plugin ] ) || is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "Plugin remained installed or active after deletion.\n" );
	exit( 1 );
}

$tampered = $plugin_delete['rollback_id'] . 'x';
$tampered_result = $restore->execute( array( 'id' => $tampered ) );
if ( ! is_wp_error( $tampered_result ) || 'cmsa_invalid_backup_id' !== $tampered_result->get_error_code() ) {
	fwrite( STDERR, "Tampered rollback identifier was accepted.\n" );
	exit( 1 );
}

$plugin_restore = $restore->execute( array( 'id' => $plugin_delete['rollback_id'] ) );
if ( is_wp_error( $plugin_restore ) ) {
	fwrite( STDERR, 'Plugin restore failed: ' . $plugin_restore->get_error_code() . ' ' . $plugin_restore->get_error_message() . "\n" );
	exit( 1 );
}
wp_clean_plugins_cache( false );
$plugins = get_plugins();
if ( ! isset( $plugins[ $plugin ] ) || '1.0.0' !== (string) $plugins[ $plugin ]['Version'] || ! is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "Plugin restore did not recover version and activation state.\n" );
	exit( 1 );
}

$theme_conflict = $delete_theme->execute( array( 'stylesheet' => $theme, 'expected_version' => '0.9.0', 'confirm_delete' => true ) );
if ( ! is_wp_error( $theme_conflict ) || 'cmsa_theme_version_conflict' !== $theme_conflict->get_error_code() ) {
	fwrite( STDERR, "Theme lifecycle did not enforce exact-version conflict control.\n" );
	exit( 1 );
}

$active_stylesheet = get_stylesheet();
$active_theme = wp_get_theme( $active_stylesheet );
$active_delete = $delete_theme->execute(
	array(
		'stylesheet' => $active_stylesheet,
		'expected_version' => (string) $active_theme->get( 'Version' ),
		'confirm_delete' => true,
	)
);
if ( ! is_wp_error( $active_delete ) || 'cmsa_active_theme_delete_forbidden' !== $active_delete->get_error_code() ) {
	fwrite( STDERR, "Active theme deletion was not blocked.\n" );
	exit( 1 );
}

$theme_delete = $delete_theme->execute( array( 'stylesheet' => $theme, 'expected_version' => '1.0.0', 'confirm_delete' => true ) );
if ( is_wp_error( $theme_delete ) || empty( $theme_delete['deleted'] ) || empty( $theme_delete['rollback_id'] ) ) {
	fwrite( STDERR, 'Theme reversible deletion failed: ' . ( is_wp_error( $theme_delete ) ? $theme_delete->get_error_code() . ' ' . $theme_delete->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}
search_theme_directories( true );
$themes = wp_get_themes( array( 'errors' => null ) );
if ( isset( $themes[ $theme ] ) ) {
	fwrite( STDERR, "Theme remained installed after deletion.\n" );
	exit( 1 );
}

$theme_restore = $restore->execute( array( 'id' => $theme_delete['rollback_id'] ) );
if ( is_wp_error( $theme_restore ) ) {
	fwrite( STDERR, 'Theme restore failed: ' . $theme_restore->get_error_code() . ' ' . $theme_restore->get_error_message() . "\n" );
	exit( 1 );
}
search_theme_directories( true );
$themes = wp_get_themes( array( 'errors' => null ) );
if ( ! isset( $themes[ $theme ] ) || '1.0.0' !== (string) $themes[ $theme ]->get( 'Version' ) || get_stylesheet() !== $active_stylesheet ) {
	fwrite( STDERR, "Theme restore did not recover the inactive theme without changing the active theme.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( false !== $delete_plugin->check_permissions( array( 'plugin' => $plugin, 'expected_version' => '1.0.0', 'confirm_delete' => true ) )
	|| false !== $delete_theme->check_permissions( array( 'stylesheet' => $theme, 'expected_version' => '1.0.0', 'confirm_delete' => true ) )
	|| false !== $restore->check_permissions( array( 'id' => $plugin_delete['rollback_id'] ) ) ) {
	fwrite( STDERR, "Anonymous component lifecycle administration was not blocked.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
echo "cmsa-v2-component-lifecycle: PASS plugin_delete=verified plugin_restore=verified activation=preserved exact_version=verified confirmation=required self_delete=blocked signed_rollback=verified tamper=blocked theme_delete=verified theme_restore=verified active_theme_delete=blocked final_state=restored admin_boundary=verified\n";
exit( 0 );
