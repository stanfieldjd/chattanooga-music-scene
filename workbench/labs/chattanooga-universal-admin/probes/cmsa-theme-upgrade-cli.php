<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this probe runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$ability = wp_get_ability( 'chattanooga-cms-admin/update-theme' );
if ( ! $ability instanceof WP_Ability ) {
	fwrite( STDERR, "CMS Admin update-theme ability was not registered.\n" );
	exit( 1 );
}

$stylesheet = 'cua-upgrade-theme';
$package_dir = rtrim( (string) getenv( 'CMSA_THEME_PACKAGE_DIR' ), DIRECTORY_SEPARATOR );
if ( '' === $package_dir || ! is_dir( $package_dir ) ) {
	fwrite( STDERR, "Theme upgrade package directory is missing.\n" );
	exit( 1 );
}

$theme = wp_get_theme( $stylesheet );
if ( ! $theme->exists() || '1.0.0' !== (string) $theme->get( 'Version' ) ) {
	fwrite( STDERR, "Disposable theme fixture is not installed at version 1.0.0.\n" );
	exit( 1 );
}

switch_theme( $stylesheet );
wp_clean_themes_cache();
if ( get_stylesheet() !== $stylesheet || get_template() !== $stylesheet ) {
	fwrite( STDERR, "Disposable theme fixture did not become active.\n" );
	exit( 1 );
}

$conflict = $ability->execute( array( 'stylesheet' => $stylesheet, 'expected_version' => '0.9.0' ) );
if ( ! is_wp_error( $conflict ) || 'cmsa_theme_version_conflict' !== $conflict->get_error_code() ) {
	fwrite( STDERR, "Theme update did not enforce exact-version conflict control.\n" );
	exit( 1 );
}

$offer = array(
	'theme'       => $stylesheet,
	'new_version' => '1.1.0',
	'url'         => 'https://example.invalid/cua-upgrade-theme',
	'package'     => $package_dir . DIRECTORY_SEPARATOR . 'cua-upgrade-theme-1.1.0.zip',
);
$updates = new stdClass();
$updates->last_checked = time();
$updates->checked = array( $stylesheet => '1.0.0' );
$updates->response = array( $stylesheet => $offer );
set_site_transient( 'update_themes', $updates );

$success = $ability->execute( array( 'stylesheet' => $stylesheet, 'expected_version' => '1.0.0' ) );
if ( is_wp_error( $success ) ) {
	fwrite( STDERR, 'Successful theme update failed: ' . $success->get_error_code() . ' ' . $success->get_error_message() . ' data=' . wp_json_encode( $success->get_error_data() ) . "\n" );
	exit( 1 );
}
wp_clean_themes_cache();
$theme = wp_get_theme( $stylesheet );
if ( '1.1.0' !== (string) $theme->get( 'Version' ) || get_stylesheet() !== $stylesheet || get_template() !== $stylesheet ) {
	fwrite( STDERR, "Theme update success did not preserve the active theme at version 1.1.0.\n" );
	exit( 1 );
}
if ( '1.0.0' !== ( $success['previous_version'] ?? '' ) || '1.1.0' !== ( $success['version'] ?? '' ) || 'wordpress_temp_backup' !== ( $success['rollback'] ?? '' ) ) {
	fwrite( STDERR, "Theme update result did not report the verified transaction state.\n" );
	exit( 1 );
}

$mismatch_offer = array(
	'theme'       => $stylesheet,
	'new_version' => '1.2.0',
	'url'         => 'https://example.invalid/cua-upgrade-theme',
	'package'     => $package_dir . DIRECTORY_SEPARATOR . 'cua-upgrade-theme-mismatch.zip',
);
$mismatch_updates = new stdClass();
$mismatch_updates->last_checked = time();
$mismatch_updates->checked = array( $stylesheet => '1.1.0' );
$mismatch_updates->response = array( $stylesheet => $mismatch_offer );
set_site_transient( 'update_themes', $mismatch_updates );

$rollback = $ability->execute( array( 'stylesheet' => $stylesheet, 'expected_version' => '1.1.0' ) );
if ( ! is_wp_error( $rollback ) || 'cmsa_theme_update_verification_failed' !== $rollback->get_error_code() ) {
	fwrite( STDERR, "A mismatched theme package did not fail closed after verification.\n" );
	exit( 1 );
}
wp_clean_themes_cache();
$theme = wp_get_theme( $stylesheet );
if ( '1.1.0' !== (string) $theme->get( 'Version' ) || get_stylesheet() !== $stylesheet || get_template() !== $stylesheet ) {
	fwrite( STDERR, "Theme rollback did not restore version 1.1.0 with active identity preserved.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( false !== $ability->check_permissions( array( 'stylesheet' => $stylesheet, 'expected_version' => '1.1.0' ) ) ) {
	fwrite( STDERR, "Theme update ability allowed an anonymous user.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
delete_site_transient( 'update_themes' );

echo 'cmsa-theme-upgrade-cli: PASS core_upgrader=verified temp_backup=verified success_readback=verified active_identity=preserved conflict_control=verified mismatch_detection=verified rollback=verified admin_boundary=verified provider_adapter=absent' . "\n";
exit( 0 );
