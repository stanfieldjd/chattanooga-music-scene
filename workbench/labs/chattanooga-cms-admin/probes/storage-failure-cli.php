<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "storage-failure-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$custom = trailingslashit( dirname( ABSPATH ) ) . 'cmsa-unwritable-custom';
$primary = trailingslashit( dirname( ABSPATH ) ) . 'chattanooga-cms-admin-backups';
$content = trailingslashit( WP_CONTENT_DIR ) . 'chattanooga-cms-admin-backups';
$paths = array( $custom, $primary, $content );
$created = array();
$original_modes = array();

foreach ( $paths as $path ) {
	if ( is_dir( $path ) ) {
		$original_modes[ $path ] = fileperms( $path ) & 0777;
	} else {
		if ( ! wp_mkdir_p( $path ) ) {
			fwrite( STDERR, "storage-failure-cli: could not create disposable storage path.\n" );
			exit( 1 );
		}
		$created[ $path ] = true;
		$original_modes[ $path ] = 0755;
	}
}

$cleanup = static function () use ( $paths, $created, $original_modes ) {
	foreach ( $paths as $path ) {
		if ( isset( $original_modes[ $path ] ) && is_dir( $path ) ) {
			@chmod( $path, $original_modes[ $path ] );
		}
	}
	foreach ( array_reverse( $paths ) as $path ) {
		if ( ! empty( $created[ $path ] ) && is_dir( $path ) ) {
			@rmdir( $path );
		}
	}
};
register_shutdown_function( $cleanup );

if ( ! defined( 'CMSA_BACKUP_DIR' ) ) {
	define( 'CMSA_BACKUP_DIR', $custom );
}

foreach ( $paths as $path ) {
	if ( ! chmod( $path, 0555 ) ) {
		fwrite( STDERR, "storage-failure-cli: could not make disposable storage path read-only.\n" );
		exit( 1 );
	}
	clearstatcache( true, $path );
	if ( is_writable( $path ) ) {
		fwrite( STDERR, "storage-failure-cli: test runtime still reports a read-only storage path writable.\n" );
		exit( 1 );
	}
}

$directory = CMSA_Backups::get_storage_directory();
if ( ! is_wp_error( $directory ) || 'cmsa_backup_directory' !== $directory->get_error_code() ) {
	fwrite( STDERR, "storage-failure-cli: unavailable storage did not return cmsa_backup_directory.\n" );
	exit( 1 );
}

$backups = new CMSA_Backups();
$result = $backups->create_backup( 'database', 'unwritable-storage-probe' );
if ( ! is_wp_error( $result ) || 'cmsa_backup_directory' !== $result->get_error_code() ) {
	fwrite( STDERR, "storage-failure-cli: backup creation did not fail closed when all storage paths were unavailable.\n" );
	exit( 1 );
}

$cleanup();
echo "storage-failure-cli: PASS all-backup-paths=unwritable backup=not-created\n";
