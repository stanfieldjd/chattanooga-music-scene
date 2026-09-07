<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "core-update-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

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
	fwrite( STDERR, "core-update-cli: expected disposable core fixture 7.0, found {$before_version}.\n" );
	exit( 1 );
}

$config_file = ABSPATH . 'wp-config.php';
if ( ! is_file( $config_file ) ) {
	fwrite( STDERR, "core-update-cli: wp-config.php is missing.\n" );
	exit( 1 );
}
$config_hash = hash_file( 'sha256', $config_file );

$content_sentinel = trailingslashit( WP_CONTENT_DIR ) . 'cmsa-core-update-sentinel.txt';
$content_value = 'cmsa-core-update-' . wp_generate_password( 20, false, false );
if ( false === file_put_contents( $content_sentinel, $content_value ) ) {
	fwrite( STDERR, "core-update-cli: could not create wp-content sentinel.\n" );
	exit( 1 );
}
$content_hash = hash_file( 'sha256', $content_sentinel );

$option_key = 'cmsa_core_update_sentinel';
$option_value = 'db-' . wp_generate_password( 20, false, false );
update_option( $option_key, $option_value, false );

$updates = new CMSA_Updates();
$backups = new CMSA_Backups();
$result = $updates->update_core();
if ( is_wp_error( $result ) ) {
	$data = $result->get_error_data();
	fwrite( STDERR, "core-update-cli: core update failed code={$result->get_error_code()} message={$result->get_error_message()} data=" . wp_json_encode( $data ) . "\n" );
	@unlink( $content_sentinel );
	delete_option( $option_key );
	exit( 1 );
}
if ( empty( $result['updated'] ) || empty( $result['version'] ) || empty( $result['backup_id'] ) ) {
	fwrite( STDERR, "core-update-cli: core update did not return a complete verified result.\n" );
	exit( 1 );
}
if ( $before_version !== $result['previous_version'] || version_compare( $result['version'], $before_version, '<=' ) ) {
	fwrite( STDERR, "core-update-cli: core version did not advance from the expected fixture.\n" );
	exit( 1 );
}

$verification = $backups->verify_backup( $result['backup_id'] );
if ( is_wp_error( $verification ) || empty( $verification['valid'] ) ) {
	fwrite( STDERR, "core-update-cli: pre-update rollback snapshot did not verify after the update.\n" );
	exit( 1 );
}

$after_version = $read_disk_version();
if ( $result['version'] !== $after_version ) {
	fwrite( STDERR, "core-update-cli: on-disk core version mismatch after update; result={$result['version']} disk={$after_version}.\n" );
	exit( 1 );
}
if ( ! is_file( $config_file ) || ! hash_equals( $config_hash, hash_file( 'sha256', $config_file ) ) ) {
	fwrite( STDERR, "core-update-cli: wp-config.php changed during core update.\n" );
	exit( 1 );
}
if ( ! is_file( $content_sentinel ) || ! hash_equals( $content_hash, hash_file( 'sha256', $content_sentinel ) ) ) {
	fwrite( STDERR, "core-update-cli: wp-content sentinel changed during core update.\n" );
	exit( 1 );
}
if ( $option_value !== get_option( $option_key ) ) {
	fwrite( STDERR, "core-update-cli: database sentinel changed during core update.\n" );
	exit( 1 );
}
if ( ! is_plugin_active( 'chattanooga-cms-admin/chattanooga-cms-admin.php' ) ) {
	fwrite( STDERR, "core-update-cli: Chattanooga CMS Admin was not active after core update.\n" );
	exit( 1 );
}

@unlink( $content_sentinel );
delete_option( $option_key );

printf(
	"core-update-cli: PASS from=%s to=%s backup=%s config=unchanged wp-content=unchanged database=unchanged plugin=active\n",
	$before_version,
	$after_version,
	$result['backup_id']
);
