<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this probe runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$delete_plugin = wp_get_ability( 'chattanooga-cms-admin/delete-plugin' );
$delete_theme = wp_get_ability( 'chattanooga-cms-admin/delete-theme' );
$restore = wp_get_ability( 'chattanooga-cms-admin/restore-component-backup' );
if ( ! $delete_plugin instanceof WP_Ability || ! $delete_theme instanceof WP_Ability || ! $restore instanceof WP_Ability ) {
	fwrite( STDERR, "Component lifecycle abilities were not registered.\n" );
	exit( 1 );
}

$plugin = 'cua-upgrade-fixture/cua-upgrade-fixture.php';
$plugins = get_plugins();
$plugin_version = isset( $plugins[ $plugin ]['Version'] ) ? (string) $plugins[ $plugin ]['Version'] : '';
if ( '1.1.0' !== $plugin_version || ! is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "Updated plugin fixture is not ready for deletion proof.\n" );
	exit( 1 );
}

$self_delete = $delete_plugin->execute(
	array(
		'plugin' => 'chattanooga-cms-admin/chattanooga-cms-admin.php',
		'expected_version' => '0.0.1-replacement-lab',
		'confirm_delete' => true,
	)
);
if ( ! is_wp_error( $self_delete ) || 'cmsa_self_delete_forbidden' !== $self_delete->get_error_code() || ! is_plugin_active( 'chattanooga-cms-admin/chattanooga-cms-admin.php' ) ) {
	fwrite( STDERR, "Component lifecycle did not protect the control plane from direct self-deletion.\n" );
	exit( 1 );
}

$plugin_delete = $delete_plugin->execute(
	array(
		'plugin' => $plugin,
		'expected_version' => '1.1.0',
		'confirm_delete' => true,
	)
);
if ( is_wp_error( $plugin_delete ) || empty( $plugin_delete['deleted'] ) || empty( $plugin_delete['rollback_id'] ) ) {
	fwrite( STDERR, 'Plugin reversible delete failed: ' . ( is_wp_error( $plugin_delete ) ? $plugin_delete->get_error_code() . ' ' . $plugin_delete->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}
wp_clean_plugins_cache( false );
if ( isset( get_plugins()[ $plugin ] ) || is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "Deleted plugin remained installed or active.\n" );
	exit( 1 );
}

$tampered = (string) $plugin_delete['rollback_id'];
$tampered[0] = 'x' === $tampered[0] ? 'y' : 'x';
$tampered_result = $restore->execute( array( 'id' => $tampered ) );
if ( ! is_wp_error( $tampered_result ) || 'cmsa_invalid_backup_id' !== $tampered_result->get_error_code() ) {
	fwrite( STDERR, "Tampered component rollback identifier was not rejected.\n" );
	exit( 1 );
}

$plugin_restore = $restore->execute( array( 'id' => $plugin_delete['rollback_id'] ) );
if ( is_wp_error( $plugin_restore ) ) {
	fwrite( STDERR, 'Plugin restore failed: ' . $plugin_restore->get_error_code() . ' ' . $plugin_restore->get_error_message() . "\n" );
	exit( 1 );
}
wp_clean_plugins_cache( false );
$plugins = get_plugins();
if ( '1.1.0' !== (string) ( $plugins[ $plugin ]['Version'] ?? '' ) || ! is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "Plugin restore did not recover version and active state.\n" );
	exit( 1 );
}

$theme = 'cua-upgrade-theme';
wp_clean_themes_cache();
$theme_object = wp_get_theme( $theme );
if ( ! $theme_object->exists() || '1.1.0' !== (string) $theme_object->get( 'Version' ) || get_stylesheet() === $theme || get_template() === $theme ) {
	fwrite( STDERR, "Updated theme fixture is not ready as an inactive deletion target.\n" );
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

$theme_delete = $delete_theme->execute(
	array(
		'stylesheet' => $theme,
		'expected_version' => '1.1.0',
		'confirm_delete' => true,
	)
);
if ( is_wp_error( $theme_delete ) || empty( $theme_delete['deleted'] ) || empty( $theme_delete['rollback_id'] ) ) {
	fwrite( STDERR, 'Theme reversible delete failed: ' . ( is_wp_error( $theme_delete ) ? $theme_delete->get_error_code() . ' ' . $theme_delete->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}
wp_clean_themes_cache();
if ( wp_get_theme( $theme )->exists() ) {
	fwrite( STDERR, "Deleted theme remained installed.\n" );
	exit( 1 );
}

$theme_restore = $restore->execute( array( 'id' => $theme_delete['rollback_id'] ) );
if ( is_wp_error( $theme_restore ) ) {
	fwrite( STDERR, 'Theme restore failed: ' . $theme_restore->get_error_code() . ' ' . $theme_restore->get_error_message() . "\n" );
	exit( 1 );
}
wp_clean_themes_cache();
$theme_object = wp_get_theme( $theme );
if ( ! $theme_object->exists() || '1.1.0' !== (string) $theme_object->get( 'Version' ) || get_stylesheet() !== $active_stylesheet ) {
	fwrite( STDERR, "Theme restore did not recover the deleted theme without changing the active theme.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( false !== $delete_plugin->check_permissions( array( 'plugin' => $plugin, 'expected_version' => '1.1.0', 'confirm_delete' => true ) )
	|| false !== $delete_theme->check_permissions( array( 'stylesheet' => $theme, 'expected_version' => '1.1.0', 'confirm_delete' => true ) )
	|| false !== $restore->check_permissions( array( 'id' => $plugin_delete['rollback_id'] ) ) ) {
	fwrite( STDERR, "Component lifecycle ability allowed an anonymous user.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
echo 'cmsa-component-lifecycle-cli: PASS plugin_delete=verified plugin_restore=verified activation=preserved self_delete=blocked theme_delete=verified theme_restore=verified active_theme_delete=blocked signed_rollback_id=verified tamper=blocked admin_boundary=verified direct_filesystem_path_input=absent' . "\n";
exit( 0 );
