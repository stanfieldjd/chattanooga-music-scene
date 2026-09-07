<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
$updates = new CMSA_Updates();

$install = $updates->install_plugin( 'classic-widgets' );
if ( is_wp_error( $install ) || empty( $install['installed'] ) || empty( $install['plugin'] ) ) {
	$message = is_wp_error( $install ) ? $install->get_error_message() : 'plugin install did not report success';
	fwrite( STDERR, "wordpress-org-package-cli: plugin installation failed: {$message}\n" );
	exit( 1 );
}
$installed_plugin = $install['plugin'];
if ( ! isset( get_plugins()[ $installed_plugin ] ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: installed plugin not found after install.\n" );
	exit( 1 );
}

$activate = $updates->activate_plugin( $installed_plugin );
if ( is_wp_error( $activate ) || empty( $activate['activated'] ) || ! is_plugin_active( $installed_plugin ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: plugin activation failed.\n" );
	exit( 1 );
}

$deactivate = $updates->deactivate_plugin( $installed_plugin );
if ( is_wp_error( $deactivate ) || empty( $deactivate['deactivated'] ) || is_plugin_active( $installed_plugin ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: plugin deactivation failed.\n" );
	exit( 1 );
}

$theme_install = $updates->install_theme( 'twentytwentyone' );
if ( is_wp_error( $theme_install ) || empty( $theme_install['installed'] ) || empty( $theme_install['theme'] ) ) {
	$message = is_wp_error( $theme_install ) ? $theme_install->get_error_message() : 'theme install did not report success';
	fwrite( STDERR, "wordpress-org-package-cli: theme installation failed: {$message}\n" );
	exit( 1 );
}
if ( ! wp_get_theme( $theme_install['theme'] )->exists() ) {
	fwrite( STDERR, "wordpress-org-package-cli: installed theme not found after install.\n" );
	exit( 1 );
}

$legacy_plugin = 'classic-editor/classic-editor.php';
$plugins = get_plugins();
if ( ! isset( $plugins[ $legacy_plugin ] ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: preinstalled legacy Classic Editor fixture is missing.\n" );
	exit( 1 );
}
$before = $plugins[ $legacy_plugin ]['Version'];
if ( '1.6' !== $before ) {
	fwrite( STDERR, "wordpress-org-package-cli: expected Classic Editor 1.6 precondition, found {$before}.\n" );
	exit( 1 );
}

$updated = $updates->update_plugin( $legacy_plugin, '1.6' );
if ( is_wp_error( $updated ) || empty( $updated['updated'] ) || empty( $updated['version'] ) ) {
	$message = is_wp_error( $updated ) ? $updated->get_error_message() : 'plugin update did not report success';
	fwrite( STDERR, "wordpress-org-package-cli: controlled plugin update failed: {$message}\n" );
	exit( 1 );
}
if ( version_compare( $updated['version'], '1.6', '<=' ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: plugin version did not advance.\n" );
	exit( 1 );
}
if ( empty( $updated['backup_id'] ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: update completed without rollback backup id.\n" );
	exit( 1 );
}

printf(
	"wordpress-org-package-cli: PASS plugin=%s theme=%s classic-editor=%s->%s backup=%s\n",
	$installed_plugin,
	$theme_install['theme'],
	$before,
	$updated['version'],
	$updated['backup_id']
);
