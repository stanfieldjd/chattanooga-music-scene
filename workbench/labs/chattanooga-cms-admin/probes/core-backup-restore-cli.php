<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "core-backup-restore-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

global $wpdb;

$core_file = ABSPATH . 'wp-includes/version.php';
$root_file = ABSPATH . 'readme.html';
if ( ! is_file( $core_file ) || ! is_file( $root_file ) ) {
	fwrite( STDERR, "core-backup-restore-cli: expected core fixture files are missing.\n" );
	exit( 1 );
}

$before_core_hash = hash_file( 'sha256', $core_file );
$before_root_hash = hash_file( 'sha256', $root_file );
$sentinel_key = 'cmsa_core_restore_sentinel';
$sentinel_value = 'backup-' . wp_generate_password( 16, false, false );
$mutated_value = 'mutated-' . wp_generate_password( 16, false, false );
update_option( $sentinel_key, $sentinel_value, false );

$backups = new CMSA_Backups();
$backup = $backups->create_core_backup();
if ( is_wp_error( $backup ) || empty( $backup['id'] ) ) {
	$message = is_wp_error( $backup ) ? $backup->get_error_message() : 'core backup did not return an id';
	fwrite( STDERR, "core-backup-restore-cli: backup failed: {$message}\n" );
	exit( 1 );
}

$verification = $backups->verify_backup( $backup['id'] );
if ( is_wp_error( $verification ) || empty( $verification['valid'] ) ) {
	fwrite( STDERR, "core-backup-restore-cli: backup verification failed.\n" );
	exit( 1 );
}

if ( false === file_put_contents( $core_file, "\n/* cmsa core rollback probe */\n", FILE_APPEND ) ) {
	fwrite( STDERR, "core-backup-restore-cli: could not mutate core fixture.\n" );
	exit( 1 );
}
if ( false === file_put_contents( $root_file, "\n<!-- cmsa core rollback probe -->\n", FILE_APPEND ) ) {
	fwrite( STDERR, "core-backup-restore-cli: could not mutate root fixture.\n" );
	exit( 1 );
}
update_option( $sentinel_key, $mutated_value, false );

if ( hash_equals( $before_core_hash, hash_file( 'sha256', $core_file ) ) || hash_equals( $before_root_hash, hash_file( 'sha256', $root_file ) ) ) {
	fwrite( STDERR, "core-backup-restore-cli: file mutation precondition was not established.\n" );
	exit( 1 );
}

$restored = $backups->restore_core_backup( $backup['id'] );
if ( is_wp_error( $restored ) || empty( $restored['restored'] ) || empty( $restored['core'] ) || empty( $restored['database'] ) ) {
	$message = is_wp_error( $restored ) ? $restored->get_error_message() : 'core restore did not report complete restoration';
	fwrite( STDERR, "core-backup-restore-cli: restore failed: {$message}\n" );
	exit( 1 );
}

$after_core_hash = is_file( $core_file ) ? hash_file( 'sha256', $core_file ) : '';
$after_root_hash = is_file( $root_file ) ? hash_file( 'sha256', $root_file ) : '';
if ( ! hash_equals( $before_core_hash, $after_core_hash ) ) {
	fwrite( STDERR, "core-backup-restore-cli: wp-includes rollback fidelity failed.\n" );
	exit( 1 );
}
if ( ! hash_equals( $before_root_hash, $after_root_hash ) ) {
	fwrite( STDERR, "core-backup-restore-cli: root-file rollback fidelity failed.\n" );
	exit( 1 );
}

$database_value = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
		$sentinel_key
	)
);
if ( $sentinel_value !== $database_value ) {
	fwrite( STDERR, "core-backup-restore-cli: database rollback fidelity failed.\n" );
	exit( 1 );
}

delete_option( $sentinel_key );

printf(
	"core-backup-restore-cli: PASS backup=%s core_sha=%s root_sha=%s database=restored\n",
	$backup['id'],
	$after_core_hash,
	$after_root_hash
);
