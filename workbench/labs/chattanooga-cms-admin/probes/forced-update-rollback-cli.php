<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "forced-update-rollback-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/update.php';

$plugin = 'classic-editor/classic-editor.php';
$expected_version = '1.6';
wp_clean_plugins_cache( true );
$plugins = get_plugins();
if ( ! isset( $plugins[ $plugin ] ) ) {
	fwrite( STDERR, "forced-update-rollback-cli: Classic Editor fixture is missing.\n" );
	exit( 1 );
}
if ( $expected_version !== $plugins[ $plugin ]['Version'] ) {
	fwrite( STDERR, "forced-update-rollback-cli: expected Classic Editor {$expected_version}, found {$plugins[$plugin]['Version']}.\n" );
	exit( 1 );
}

$plugin_file = trailingslashit( WP_PLUGIN_DIR ) . $plugin;
if ( ! is_file( $plugin_file ) ) {
	fwrite( STDERR, "forced-update-rollback-cli: Classic Editor main file is missing.\n" );
	exit( 1 );
}
$before_hash = hash_file( 'sha256', $plugin_file );

wp_update_plugins();
$offered = get_site_transient( 'update_plugins' );
if ( ! is_object( $offered ) || empty( $offered->response[ $plugin ] ) || empty( $offered->response[ $plugin ]->package ) ) {
	fwrite( STDERR, "forced-update-rollback-cli: WordPress.org update metadata is unavailable for the fixture.\n" );
	exit( 1 );
}
$real_target = (string) $offered->response[ $plugin ]->new_version;
if ( version_compare( $real_target, $expected_version, '<=' ) ) {
	fwrite( STDERR, "forced-update-rollback-cli: fixture has no newer WordPress.org package to install.\n" );
	exit( 1 );
}

$forced_target = '99.99.99-cmsa-validation-probe';
$filter = static function ( $value ) use ( $plugin, $forced_target ) {
	if ( ! is_object( $value ) || empty( $value->response[ $plugin ] ) ) {
		return $value;
	}

	$value = clone $value;
	$entry = clone $value->response[ $plugin ];
	$entry->new_version = $forced_target;
	$value->response[ $plugin ] = $entry;
	return $value;
};
add_filter( 'site_transient_update_plugins', $filter, PHP_INT_MAX );

$updates = new CMSA_Updates();
$result = $updates->update_plugin( $plugin, $expected_version );
remove_filter( 'site_transient_update_plugins', $filter, PHP_INT_MAX );

if ( ! is_wp_error( $result ) ) {
	fwrite( STDERR, "forced-update-rollback-cli: forced post-update validation mismatch unexpectedly reported success.\n" );
	exit( 1 );
}
if ( 'cmsa_plugin_version_verify' !== $result->get_error_code() ) {
	fwrite( STDERR, "forced-update-rollback-cli: unexpected error code {$result->get_error_code()}.\n" );
	exit( 1 );
}

$data = $result->get_error_data();
if ( ! is_array( $data ) || empty( $data['backup_id'] ) || empty( $data['rolled_back'] ) ) {
	fwrite( STDERR, "forced-update-rollback-cli: validation failure did not report successful automatic rollback.\n" );
	exit( 1 );
}

wp_clean_plugins_cache( true );
$plugins_after = get_plugins();
$after_version = isset( $plugins_after[ $plugin ] ) ? $plugins_after[ $plugin ]['Version'] : '';
$after_hash = is_file( $plugin_file ) ? hash_file( 'sha256', $plugin_file ) : '';
if ( $expected_version !== $after_version ) {
	fwrite( STDERR, "forced-update-rollback-cli: rollback version mismatch; expected {$expected_version}, found {$after_version}.\n" );
	exit( 1 );
}
if ( ! hash_equals( $before_hash, $after_hash ) ) {
	fwrite( STDERR, "forced-update-rollback-cli: rollback byte-fidelity check failed.\n" );
	exit( 1 );
}

printf(
	"forced-update-rollback-cli: PASS plugin=%s package=%s forced_target=%s rollback=%s backup=%s\n",
	$plugin,
	$real_target,
	$forced_target,
	$after_version,
	$data['backup_id']
);
