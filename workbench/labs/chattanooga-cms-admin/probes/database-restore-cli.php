<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "database-restore-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

global $wpdb;
$key = 'cmsa_lab_database_sentinel';
$before = 'before-' . wp_generate_password( 10, false, false );
$after = 'after-' . wp_generate_password( 10, false, false );

update_option( $key, $before, false );
$persisted_before = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
if ( $persisted_before !== $before ) {
	fwrite( STDERR, "database-restore-cli: initial sentinel did not persist.\n" );
	exit( 1 );
}

$backups = new CMSA_Backups();
$backup = $backups->create_backup( 'database', 'database-restore-sentinel' );
if ( is_wp_error( $backup ) || empty( $backup['id'] ) ) {
	fwrite( STDERR, "database-restore-cli: database backup creation failed.\n" );
	exit( 1 );
}
$verification = $backups->verify_backup( $backup['id'] );
if ( is_wp_error( $verification ) || empty( $verification['valid'] ) ) {
	fwrite( STDERR, "database-restore-cli: database backup verification failed.\n" );
	exit( 1 );
}

update_option( $key, $after, false );
$persisted_after = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
if ( $persisted_after !== $after ) {
	fwrite( STDERR, "database-restore-cli: mutated sentinel did not persist.\n" );
	exit( 1 );
}

$restore = $backups->restore_database_backup( $backup['id'] );
if ( is_wp_error( $restore ) || empty( $restore['restored'] ) ) {
	$message = is_wp_error( $restore ) ? $restore->get_error_message() : 'unknown restore failure';
	fwrite( STDERR, "database-restore-cli: restore failed: {$message}\n" );
	exit( 1 );
}

$wpdb->flush();
$restored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
if ( $restored !== $before ) {
	fwrite( STDERR, "database-restore-cli: restored database sentinel does not match backup state.\n" );
	exit( 1 );
}

printf( "database-restore-cli: PASS backup=%s\n", $backup['id'] );
