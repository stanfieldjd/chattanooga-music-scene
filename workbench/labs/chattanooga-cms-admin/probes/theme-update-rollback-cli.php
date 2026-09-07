<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "theme-update-rollback-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$stylesheet = 'twentytwentyone';
$expected_version = '1.8';
$theme = wp_get_theme( $stylesheet );
if ( ! $theme->exists() ) {
	fwrite( STDERR, "theme-update-rollback-cli: legacy Twenty Twenty-One fixture is missing.\n" );
	exit( 1 );
}

$before_version = $theme->get( 'Version' );
if ( $expected_version !== $before_version ) {
	fwrite( STDERR, "theme-update-rollback-cli: expected {$expected_version}, found {$before_version}.\n" );
	exit( 1 );
}

$style_path = $theme->get_stylesheet_directory() . '/style.css';
if ( ! is_file( $style_path ) ) {
	fwrite( STDERR, "theme-update-rollback-cli: style.css fixture file is missing.\n" );
	exit( 1 );
}
$before_hash = hash_file( 'sha256', $style_path );

$updates = new CMSA_Updates();
$backups = new CMSA_Backups();
$updated = $updates->update_theme( $stylesheet, $expected_version );
if ( is_wp_error( $updated ) || empty( $updated['updated'] ) || empty( $updated['version'] ) || empty( $updated['backup_id'] ) ) {
	$message = is_wp_error( $updated ) ? $updated->get_error_message() : 'theme update did not report a verified result';
	fwrite( STDERR, "theme-update-rollback-cli: update failed: {$message}\n" );
	exit( 1 );
}
if ( version_compare( $updated['version'], $expected_version, '<=' ) ) {
	fwrite( STDERR, "theme-update-rollback-cli: theme version did not advance.\n" );
	exit( 1 );
}

$verification = $backups->verify_backup( $updated['backup_id'] );
if ( is_wp_error( $verification ) || empty( $verification['valid'] ) ) {
	fwrite( STDERR, "theme-update-rollback-cli: rollback archive did not verify.\n" );
	exit( 1 );
}

$restored = $backups->restore_component_backup( $updated['backup_id'] );
if ( is_wp_error( $restored ) || empty( $restored['restored'] ) ) {
	$message = is_wp_error( $restored ) ? $restored->get_error_message() : 'rollback did not report success';
	fwrite( STDERR, "theme-update-rollback-cli: rollback failed: {$message}\n" );
	exit( 1 );
}

wp_clean_themes_cache( true );
$restored_theme = wp_get_theme( $stylesheet );
$after_version = $restored_theme->get( 'Version' );
$after_style_path = $restored_theme->get_stylesheet_directory() . '/style.css';
$after_hash = is_file( $after_style_path ) ? hash_file( 'sha256', $after_style_path ) : '';
if ( $expected_version !== $after_version ) {
	fwrite( STDERR, "theme-update-rollback-cli: rollback version mismatch; expected {$expected_version}, found {$after_version}.\n" );
	exit( 1 );
}
if ( ! hash_equals( $before_hash, $after_hash ) ) {
	fwrite( STDERR, "theme-update-rollback-cli: rollback byte-fidelity check failed.\n" );
	exit( 1 );
}

printf(
	"theme-update-rollback-cli: PASS theme=%s updated=%s rollback=%s backup=%s\n",
	$stylesheet,
	$updated['version'],
	$after_version,
	$updated['backup_id']
);
