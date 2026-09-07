<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this test runs.\n" );
	exit( 1 );
}

$fixture_dir = WP_PLUGIN_DIR . '/cmsa-lab-fixture';
$fixture_state = $fixture_dir . '/state.txt';
if ( ! is_file( $fixture_state ) ) {
	fwrite( STDERR, "Fixture state file is missing.\n" );
	exit( 1 );
}

$backups = new CMSA_Backups();

$database = $backups->create_backup( 'database', 'github-workbench-database' );
if ( is_wp_error( $database ) || empty( $database['id'] ) ) {
	fwrite( STDERR, "Database backup creation failed.\n" );
	if ( is_wp_error( $database ) ) {
		fwrite( STDERR, $database->get_error_message() . "\n" );
	}
	exit( 1 );
}

$database_verify = $backups->verify_backup( $database['id'] );
if ( is_wp_error( $database_verify ) || empty( $database_verify['valid'] ) ) {
	fwrite( STDERR, "Database backup checksum verification failed.\n" );
	exit( 1 );
}

echo 'database-backup: PASS (' . $database['id'] . ")\n";

$component = $backups->create_component_backup( 'plugin', 'cmsa-lab-fixture/cmsa-lab-fixture.php', $fixture_dir );
if ( is_wp_error( $component ) || empty( $component['id'] ) ) {
	fwrite( STDERR, "Component backup creation failed.\n" );
	if ( is_wp_error( $component ) ) {
		fwrite( STDERR, $component->get_error_message() . "\n" );
	}
	exit( 1 );
}

$component_verify = $backups->verify_backup( $component['id'] );
if ( is_wp_error( $component_verify ) || empty( $component_verify['valid'] ) ) {
	fwrite( STDERR, "Component backup checksum verification failed.\n" );
	exit( 1 );
}

file_put_contents( $fixture_state, "mutated\n" );
if ( "mutated\n" !== file_get_contents( $fixture_state ) ) {
	fwrite( STDERR, "Could not mutate disposable fixture before rollback test.\n" );
	exit( 1 );
}

$restored = $backups->restore_component_backup( $component['id'] );
if ( is_wp_error( $restored ) || empty( $restored['restored'] ) ) {
	fwrite( STDERR, "Component rollback failed.\n" );
	if ( is_wp_error( $restored ) ) {
		fwrite( STDERR, $restored->get_error_message() . "\n" );
	}
	exit( 1 );
}

if ( "baseline\n" !== file_get_contents( $fixture_state ) ) {
	fwrite( STDERR, "Rollback completed but fixture contents were not restored exactly.\n" );
	exit( 1 );
}

echo 'component-backup-verify-restore: PASS (' . $component['id'] . ")\n";
