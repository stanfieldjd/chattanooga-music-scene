<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "lifecycle-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$plugin = 'cmsa-lab-fixture/cmsa-lab-fixture.php';
$plugins = get_plugins();
if ( ! isset( $plugins[ $plugin ] ) ) {
	fwrite( STDERR, "lifecycle-cli: fixture plugin is not installed.\n" );
	exit( 1 );
}

$lifecycle = new CMSA_Lifecycle();

$enable = $lifecycle->set_plugin_auto_update( $plugin, true );
if ( is_wp_error( $enable ) || empty( $enable['auto_update'] ) ) {
	fwrite( STDERR, "lifecycle-cli: enabling plugin auto-update failed.\n" );
	exit( 1 );
}

$disable = $lifecycle->set_plugin_auto_update( $plugin, false );
if ( is_wp_error( $disable ) || ! empty( $disable['auto_update'] ) ) {
	fwrite( STDERR, "lifecycle-cli: disabling plugin auto-update failed.\n" );
	exit( 1 );
}

$state_file = WP_PLUGIN_DIR . '/cmsa-lab-fixture/state.txt';
if ( ! is_file( $state_file ) ) {
	fwrite( STDERR, "lifecycle-cli: fixture state file is missing.\n" );
	exit( 1 );
}
$original_hash = hash_file( 'sha256', $state_file );

$deleted = $lifecycle->delete_plugin( $plugin );
if ( is_wp_error( $deleted ) || empty( $deleted['deleted'] ) || empty( $deleted['backup_id'] ) ) {
	fwrite( STDERR, "lifecycle-cli: controlled plugin deletion failed.\n" );
	exit( 1 );
}

wp_clean_plugins_cache( true );
if ( isset( get_plugins()[ $plugin ] ) ) {
	fwrite( STDERR, "lifecycle-cli: fixture plugin still exists after deletion.\n" );
	exit( 1 );
}

$backups = new CMSA_Backups();
$restore = $backups->restore_component_backup( $deleted['backup_id'] );
if ( is_wp_error( $restore ) || empty( $restore['restored'] ) ) {
	fwrite( STDERR, "lifecycle-cli: rollback restore failed.\n" );
	exit( 1 );
}

wp_clean_plugins_cache( true );
if ( ! isset( get_plugins()[ $plugin ] ) ) {
	fwrite( STDERR, "lifecycle-cli: fixture plugin was not restored.\n" );
	exit( 1 );
}

if ( ! is_file( $state_file ) || ! hash_equals( $original_hash, hash_file( 'sha256', $state_file ) ) ) {
	fwrite( STDERR, "lifecycle-cli: restored fixture content does not match the pre-delete state.\n" );
	exit( 1 );
}

printf( "lifecycle-cli: PASS backup=%s\n", $deleted['backup_id'] );
