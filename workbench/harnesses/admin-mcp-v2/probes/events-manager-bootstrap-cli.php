<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_v2_em_bootstrap_fail( $message ) {
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

if ( ! defined( 'EM_VERSION' ) || '7.4.3' !== (string) EM_VERSION ) {
	cmsa_v2_em_bootstrap_fail( 'Events Manager 7.4.3 is not active during bootstrap.' );
}

$plugin_dir = WP_PLUGIN_DIR . '/events-manager';
$classes_dir = $plugin_dir . '/classes';
$required = array(
	$classes_dir . '/em-admin-notice.php',
	$classes_dir . '/em-admin-notices.php',
	$plugin_dir . '/em-install.php',
);
foreach ( $required as $path ) {
	if ( ! is_file( $path ) ) {
		cmsa_v2_em_bootstrap_fail( 'Required Events Manager bootstrap file is missing: ' . $path );
	}
}

$prior_cwd = getcwd();
if ( false === chdir( $classes_dir ) ) {
	cmsa_v2_em_bootstrap_fail( 'Could not enter Events Manager classes directory.' );
}
require_once $classes_dir . '/em-admin-notices.php';
if ( false !== $prior_cwd ) {
	chdir( $prior_cwd );
}

if ( ! class_exists( 'EM_Admin_Notice' ) || ! class_exists( 'EM_Admin_Notices' ) ) {
	cmsa_v2_em_bootstrap_fail( 'Events Manager admin notice dependencies did not load.' );
}

require_once $plugin_dir . '/em-install.php';
if ( ! function_exists( 'em_install' ) ) {
	cmsa_v2_em_bootstrap_fail( 'Events Manager installer function is unavailable.' );
}

em_install();

if ( '7.4.3' !== (string) get_option( 'dbem_version', '' ) ) {
	cmsa_v2_em_bootstrap_fail( 'Events Manager installer did not persist dbem_version 7.4.3.' );
}

global $wpdb;
$tables = array(
	$wpdb->prefix . 'em_events',
	$wpdb->prefix . 'em_locations',
	$wpdb->prefix . 'em_bookings',
	$wpdb->prefix . 'em_tickets',
	$wpdb->prefix . 'em_tickets_bookings',
	$wpdb->prefix . 'em_meta',
);
foreach ( $tables as $table ) {
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $found !== $table ) {
		cmsa_v2_em_bootstrap_fail( 'Events Manager schema table was not created: ' . $table );
	}
}

echo "cmsa-v2-events-manager-bootstrap: PASS version=7.4.3 vendor_installer=executed schema=verified\n";
exit( 0 );
