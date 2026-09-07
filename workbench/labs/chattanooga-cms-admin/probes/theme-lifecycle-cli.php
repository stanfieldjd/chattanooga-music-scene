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
$original_stylesheet = get_stylesheet();
if ( $stylesheet === $original_stylesheet || ! wp_get_theme( $original_stylesheet )->exists() ) {
	fwrite( STDERR, "theme-lifecycle-cli: original active theme is unsuitable for switch/return verification.\n" );
	exit( 1 );
}

$initial_auto_updates = (array) get_site_option( 'auto_update_themes', array() );
$initial_auto_update = in_array( $stylesheet, $initial_auto_updates, true );

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

$updates = new CMSA_Updates();
$switched = $updates->switch_theme( $stylesheet );
if ( is_wp_error( $switched ) || get_stylesheet() !== $stylesheet ) {
	fwrite( STDERR, "theme-lifecycle-cli: candidate-controlled theme switch failed.\n" );
	exit( 1 );
}

$enabled = $lifecycle->set_theme_auto_update( $stylesheet, true );
if ( is_wp_error( $enabled ) || empty( $enabled['auto_update'] ) || ! in_array( $stylesheet, (array) get_site_option( 'auto_update_themes', array() ), true ) ) {
	fwrite( STDERR, "theme-lifecycle-cli: theme auto-update enable did not persist.\n" );
	exit( 1 );
}

$disabled = $lifecycle->set_theme_auto_update( $stylesheet, false );
if ( is_wp_error( $disabled ) || ! empty( $disabled['auto_update'] ) || in_array( $stylesheet, (array) get_site_option( 'auto_update_themes', array() ), true ) ) {
	fwrite( STDERR, "theme-lifecycle-cli: theme auto-update disable did not persist.\n" );
	exit( 1 );
}

$returned = $updates->switch_theme( $original_stylesheet );
if ( is_wp_error( $returned ) || get_stylesheet() !== $original_stylesheet ) {
	fwrite( STDERR, "theme-lifecycle-cli: return to original theme failed.\n" );
	exit( 1 );
}

$restored_auto = $lifecycle->set_theme_auto_update( $stylesheet, $initial_auto_update );
$current_auto_update = in_array( $stylesheet, (array) get_site_option( 'auto_update_themes', array() ), true );
if ( is_wp_error( $restored_auto ) || $current_auto_update !== $initial_auto_update ) {
	fwrite( STDERR, "theme-lifecycle-cli: original theme auto-update state was not restored.\n" );
	exit( 1 );
}

printf(
	"theme-lifecycle-cli: PASS backup=%s switch=%s return=%s auto_update=enable-disable restored=%s\n",
	$deleted['backup_id'],
	$stylesheet,
	$original_stylesheet,
	$initial_auto_update ? 'enabled' : 'disabled'
);
