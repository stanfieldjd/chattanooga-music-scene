<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "corrupt-backup-rejection-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$backups = new CMSA_Backups();
$storage = CMSA_Backups::get_storage_directory();
if ( is_wp_error( $storage ) ) {
	fwrite( STDERR, "corrupt-backup-rejection-cli: backup storage is unavailable.\n" );
	exit( 1 );
}

$fixture_dir = WP_PLUGIN_DIR . '/cmsa-lab-fixture';
$fixture_state = $fixture_dir . '/state.txt';
if ( ! is_file( $fixture_state ) ) {
	fwrite( STDERR, "corrupt-backup-rejection-cli: fixture state is missing.\n" );
	exit( 1 );
}

$component = $backups->create_component_backup( 'plugin', 'cmsa-lab-fixture/cmsa-lab-fixture.php', $fixture_dir );
if ( is_wp_error( $component ) || empty( $component['id'] ) || empty( $component['files'][0]['name'] ) ) {
	fwrite( STDERR, "corrupt-backup-rejection-cli: component backup creation failed.\n" );
	exit( 1 );
}
$component_archive = trailingslashit( $storage ) . basename( $component['files'][0]['name'] );
file_put_contents( $fixture_state, "survivor\n" );
file_put_contents( $component_archive, "cmsa-corruption-probe", FILE_APPEND );
$component_verify = $backups->verify_backup( $component['id'] );
if ( is_wp_error( $component_verify ) || ! empty( $component_verify['valid'] ) ) {
	fwrite( STDERR, "corrupt-backup-rejection-cli: corrupted component archive was not rejected by verification.\n" );
	exit( 1 );
}
$component_restore = $backups->restore_component_backup( $component['id'] );
if ( ! is_wp_error( $component_restore ) || 'cmsa_restore_verify' !== $component_restore->get_error_code() ) {
	fwrite( STDERR, "corrupt-backup-rejection-cli: corrupted component restore did not fail closed.\n" );
	exit( 1 );
}
if ( "survivor\n" !== file_get_contents( $fixture_state ) ) {
	fwrite( STDERR, "corrupt-backup-rejection-cli: component target changed after rejected restore.\n" );
	exit( 1 );
}
file_put_contents( $fixture_state, "baseline\n" );

$missing_component = $backups->create_component_backup( 'plugin', 'cmsa-lab-fixture/cmsa-lab-fixture.php', $fixture_dir );
if ( is_wp_error( $missing_component ) || empty( $missing_component['id'] ) || empty( $missing_component['files'][0]['name'] ) ) {
	fwrite( STDERR, "corrupt-backup-rejection-cli: missing-file component backup creation failed.\n" );
	exit( 1 );
}
$missing_archive = trailingslashit( $storage ) . basename( $missing_component['files'][0]['name'] );
if ( ! unlink( $missing_archive ) ) {
	fwrite( STDERR, "corrupt-backup-rejection-cli: could not remove disposable component archive.\n" );
	exit( 1 );
}
$missing_restore = $backups->restore_component_backup( $missing_component['id'] );
if ( ! is_wp_error( $missing_restore ) || 'cmsa_restore_verify' !== $missing_restore->get_error_code() ) {
	fwrite( STDERR, "corrupt-backup-rejection-cli: missing component archive did not fail closed.\n" );
	exit( 1 );
}

$sentinel = 'cmsa_corrupt_backup_rejection';
update_option( $sentinel, 'before-backup', false );
$database = $backups->create_backup( 'database', 'corrupt-rejection-probe' );
if ( is_wp_error( $database ) || empty( $database['id'] ) || empty( $database['files'][0]['name'] ) ) {
	delete_option( $sentinel );
	fwrite( STDERR, "corrupt-backup-rejection-cli: database backup creation failed.\n" );
	exit( 1 );
}
update_option( $sentinel, 'after-backup', false );
$database_file = trailingslashit( $storage ) . basename( $database['files'][0]['name'] );
file_put_contents( $database_file, "\n-- cmsa-corruption-probe\n", FILE_APPEND );
$database_verify = $backups->verify_backup( $database['id'] );
if ( is_wp_error( $database_verify ) || ! empty( $database_verify['valid'] ) ) {
	delete_option( $sentinel );
	fwrite( STDERR, "corrupt-backup-rejection-cli: corrupted database snapshot was not rejected by verification.\n" );
	exit( 1 );
}
$database_restore = $backups->restore_database_backup( $database['id'] );
if ( ! is_wp_error( $database_restore ) || 'cmsa_restore_verify' !== $database_restore->get_error_code() ) {
	delete_option( $sentinel );
	fwrite( STDERR, "corrupt-backup-rejection-cli: corrupted database restore did not fail closed.\n" );
	exit( 1 );
}
if ( 'after-backup' !== get_option( $sentinel ) ) {
	delete_option( $sentinel );
	fwrite( STDERR, "corrupt-backup-rejection-cli: database changed after rejected restore.\n" );
	exit( 1 );
}
delete_option( $sentinel );

printf(
	"corrupt-backup-rejection-cli: PASS component=checksum-rejected missing=fail-closed database=checksum-rejected target=unchanged\n"
);
