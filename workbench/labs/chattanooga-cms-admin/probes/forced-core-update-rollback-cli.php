<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/update.php';

global $wpdb;

$read_disk_version = static function () {
	$file = ABSPATH . WPINC . '/version.php';
	$contents = is_file( $file ) ? file_get_contents( $file ) : '';
	if ( preg_match( '/\\$wp_version\\s*=\\s*[\'\"]([^\'\"]+)[\'\"]/', (string) $contents, $matches ) ) {
		return $matches[1];
	}
	return '';
};

$before_version = $read_disk_version();
if ( '7.0' !== $before_version ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: expected disposable core fixture 7.0, found {$before_version}.\n" );
	exit( 1 );
}

wp_version_check();
$offered_version = '';
foreach ( get_core_updates( array( 'dismissed' => false ) ) as $update ) {
	if ( isset( $update->response, $update->version ) && 'upgrade' === $update->response ) {
		$offered_version = (string) $update->version;
		break;
	}
}
if ( ! $offered_version || version_compare( $offered_version, $before_version, '<=' ) ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: no newer official core update is available for the fixture.\n" );
	exit( 1 );
}

$core_file = ABSPATH . WPINC . '/version.php';
$root_file = ABSPATH . 'readme.html';
$config_file = ABSPATH . 'wp-config.php';
if ( ! is_file( $core_file ) || ! is_file( $root_file ) || ! is_file( $config_file ) ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: required pre-update files are missing.\n" );
	exit( 1 );
}

$before_core_hash = hash_file( 'sha256', $core_file );
$before_root_hash = hash_file( 'sha256', $root_file );
$before_config_hash = hash_file( 'sha256', $config_file );

$content_sentinel = trailingslashit( WP_CONTENT_DIR ) . 'cmsa-core-forced-rollback-sentinel.txt';
$content_value = 'content-' . wp_generate_password( 20, false, false );
if ( false === file_put_contents( $content_sentinel, $content_value ) ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: could not create wp-content sentinel.\n" );
	exit( 1 );
}
$before_content_hash = hash_file( 'sha256', $content_sentinel );

$option_key = 'cmsa_core_forced_rollback_sentinel';
$option_value = 'before-' . wp_generate_password( 20, false, false );
$mutated_option_value = 'after-update-' . wp_generate_password( 20, false, false );
update_option( $option_key, $option_value, false );

$injected = false;
$injection_error = '';
$hook = static function ( $upgrader, $options ) use ( &$injected, &$injection_error, $core_file, $option_key, $mutated_option_value ) {
	if ( ! is_array( $options ) || 'update' !== ( $options['action'] ?? '' ) || 'core' !== ( $options['type'] ?? '' ) ) {
		return;
	}

	$contents = is_file( $core_file ) ? file_get_contents( $core_file ) : false;
	if ( false === $contents ) {
		$injection_error = 'could not read post-update version.php';
		return;
	}

	$count = 0;
	$modified = preg_replace(
		'/\\$wp_version\\s*=\\s*[\'\"][^\'\"]+[\'\"]\\s*;/',
		'\$wp_version = \'99.99.99-cmsa-corrupt\';',
		$contents,
		1,
		$count
	);
	if ( 1 !== $count || ! is_string( $modified ) || false === file_put_contents( $core_file, $modified ) ) {
		$injection_error = 'could not inject the post-update core version mismatch';
		return;
	}

	update_option( $option_key, $mutated_option_value, false );
	$injected = true;
};
add_action( 'upgrader_process_complete', $hook, PHP_INT_MAX, 2 );

$updates = new CMSA_Updates();
$result = $updates->update_core();
remove_action( 'upgrader_process_complete', $hook, PHP_INT_MAX );

if ( ! $injected ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: failure injection did not execute: {$injection_error}\n" );
	exit( 1 );
}
if ( ! is_wp_error( $result ) ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: forced post-update core validation mismatch unexpectedly reported success.\n" );
	exit( 1 );
}
if ( 'cmsa_core_version_verify' !== $result->get_error_code() ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: unexpected error code {$result->get_error_code()}.\n" );
	exit( 1 );
}

$data = $result->get_error_data();
if ( ! is_array( $data ) || empty( $data['backup_id'] ) || empty( $data['rolled_back'] ) ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: core validation failure did not report successful automatic rollback.\n" );
	exit( 1 );
}

$backups = new CMSA_Backups();
$backup_verification = $backups->verify_backup( $data['backup_id'] );
if ( is_wp_error( $backup_verification ) || empty( $backup_verification['valid'] ) ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: rollback snapshot no longer verifies.\n" );
	exit( 1 );
}

$after_version = $read_disk_version();
$after_core_hash = is_file( $core_file ) ? hash_file( 'sha256', $core_file ) : '';
$after_root_hash = is_file( $root_file ) ? hash_file( 'sha256', $root_file ) : '';
$after_config_hash = is_file( $config_file ) ? hash_file( 'sha256', $config_file ) : '';
$after_content_hash = is_file( $content_sentinel ) ? hash_file( 'sha256', $content_sentinel ) : '';
$database_value = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
		$option_key
	)
);

if ( $before_version !== $after_version ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: rollback version mismatch; expected {$before_version}, found {$after_version}.\n" );
	exit( 1 );
}
if ( ! hash_equals( $before_core_hash, $after_core_hash ) || ! hash_equals( $before_root_hash, $after_root_hash ) ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: core file byte-fidelity check failed.\n" );
	exit( 1 );
}
if ( ! hash_equals( $before_config_hash, $after_config_hash ) ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: wp-config.php changed during failed core update/rollback.\n" );
	exit( 1 );
}
if ( ! hash_equals( $before_content_hash, $after_content_hash ) ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: wp-content sentinel changed during failed core update/rollback.\n" );
	exit( 1 );
}
if ( $option_value !== $database_value ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: database sentinel was not restored to the pre-update value.\n" );
	exit( 1 );
}
if ( ! is_plugin_active( 'chattanooga-cms-admin/chattanooga-cms-admin.php' ) ) {
	fwrite( STDERR, "forced-core-update-rollback-cli: Chattanooga CMS Admin was not active after automatic rollback.\n" );
	exit( 1 );
}

@unlink( $content_sentinel );
delete_option( $option_key );

printf(
	"forced-core-update-rollback-cli: PASS attempted=%s rollback=%s backup=%s core=exact database=restored config=unchanged wp-content=unchanged plugin=active\n",
	$offered_version,
	$after_version,
	$data['backup_id']
);
