<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "events-manager-deletion-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$expected = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/expected-events-manager-deletion-abilities.json' ), true );
if ( ! is_array( $expected ) || 2 !== count( $expected ) ) {
	fwrite( STDERR, "events-manager-deletion-permission-cli: expected deletion ability fixture is invalid.\n" );
	exit( 1 );
}
$abilities = array();
foreach ( $expected as $name ) {
	$ability = wp_get_ability( $name );
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "events-manager-deletion-permission-cli: missing ability {$name}.\n" );
		exit( 1 );
	}
	$abilities[ $name ] = $ability;
}
$allowed = static function ( $ability ) {
	return true === $ability->check_permissions();
};

wp_set_current_user( 0 );
foreach ( $abilities as $name => $ability ) {
	if ( $allowed( $ability ) ) {
		fwrite( STDERR, "events-manager-deletion-permission-cli: anonymous access leaked for {$name}.\n" );
		exit( 1 );
	}
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "events-manager-deletion-permission-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
foreach ( $abilities as $name => $ability ) {
	if ( ! $allowed( $ability ) ) {
		fwrite( STDERR, "events-manager-deletion-permission-cli: administrator denied {$name}.\n" );
		exit( 1 );
	}
}

$login = 'cmsa_event_delete_limited_' . strtolower( wp_generate_password( 8, false, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 20, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "events-manager-deletion-permission-cli: limited user creation failed.\n" );
	exit( 1 );
}
$limited = new WP_User( $user_id );
$limited->set_role( 'subscriber' );
$limited->add_cap( 'edit_events' );
wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	if ( $allowed( $ability ) ) {
		fwrite( STDERR, "events-manager-deletion-permission-cli: edit_events alone unexpectedly grants {$name}.\n" );
		exit( 1 );
	}
}

$limited->add_cap( 'delete_events' );
wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	if ( ! $allowed( $ability ) ) {
		fwrite( STDERR, "events-manager-deletion-permission-cli: delete_events did not grant registrar access for {$name}.\n" );
		exit( 1 );
	}
}

wp_set_current_user( $admin->ID );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );

echo "events-manager-deletion-permission-cli: PASS abilities=2 anonymous=denied edit_events=insufficient delete_events=required administrator=allowed\n";
