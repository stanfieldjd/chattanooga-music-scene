<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "theme-lifecycle-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$stylesheet = 'cmsa-lab-theme';
$theme = wp_get_theme( $stylesheet );
if ( ! $theme->exists() ) {
	fwrite( STDERR, "theme-lifecycle-cli: fixture theme is not installed.\n" );
	exit( 1 );
}

$state_file = trailingslashit( $theme->get_stylesheet_directory() ) . 'state.txt';
if ( ! is_file( $state_file ) ) {
	fwrite( STDERR, "theme-lifecycle-cli: fixture state file is missing.\n" );
	exit( 1 );
}
$original_hash = hash_file( 'sha256', $state_file );

$lifecycle = new CMSA_Lifecycle();
$deleted = $lifecycle->delete_theme( $stylesheet );
if ( is_wp_error( $deleted ) || empty( $deleted['deleted'] ) || empty( $deleted['backup_id'] ) ) {
	fwrite( STDERR, "theme-lifecycle-cli: controlled theme deletion failed.\n" );
	exit( 1 );
}

wp_clean_themes_cache( true );
if ( wp_get_theme( $stylesheet )->exists() ) {
	fwrite( STDERR, "theme-lifecycle-cli: fixture theme still exists after deletion.\n" );
	exit( 1 );
}

$backups = new CMSA_Backups();
$restore = $backups->restore_component_backup( $deleted['backup_id'] );
if ( is_wp_error( $restore ) || empty( $restore['restored'] ) ) {
	fwrite( STDERR, "theme-lifecycle-cli: rollback restore failed.\n" );
	exit( 1 );
}

wp_clean_themes_cache( true );
$restored_theme = wp_get_theme( $stylesheet );
if ( ! $restored_theme->exists() ) {
	fwrite( STDERR, "theme-lifecycle-cli: fixture theme was not restored.\n" );
	exit( 1 );
}

$restored_state = trailingslashit( $restored_theme->get_stylesheet_directory() ) . 'state.txt';
if ( ! is_file( $restored_state ) || ! hash_equals( $original_hash, hash_file( 'sha256', $restored_state ) ) ) {
	fwrite( STDERR, "theme-lifecycle-cli: restored theme content does not match the pre-delete state.\n" );
	exit( 1 );
}

printf( "theme-lifecycle-cli: PASS backup=%s\n", $deleted['backup_id'] );
