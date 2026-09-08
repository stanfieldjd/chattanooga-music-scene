<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "events-manager-mutation-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$expected = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/expected-events-manager-mutation-abilities.json' ), true );
$abilities = array();
foreach ( $expected as $name ) {
	$ability = wp_get_ability( $name );
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "events-manager-mutation-permission-cli: missing ability {$name}.\n" );
		exit( 1 );
	}
	$abilities[ $name ] = $ability;
}

wp_set_current_user( 0 );
foreach ( $abilities as $name => $ability ) {
	if ( true === $ability->check_permissions() ) {
		fwrite( STDERR, "events-manager-mutation-permission-cli: anonymous access leaked for {$name}.\n" );
		exit( 1 );
	}
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "events-manager-mutation-permission-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
foreach ( $abilities as $name => $ability ) {
	if ( true !== $ability->check_permissions() ) {
		fwrite( STDERR, "events-manager-mutation-permission-cli: administrator denied {$name}.\n" );
		exit( 1 );
	}
}

$login = 'cmsa-em-limited-' . strtolower( wp_generate_password( 8, false, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 24, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "events-manager-mutation-permission-cli: limited user creation failed.\n" );
	exit( 1 );
}
$limited = new WP_User( $user_id );
$limited->set_role( 'subscriber' );
$limited->add_cap( 'edit_events' );
wp_set_current_user( 0 );
wp_set_current_user( $user_id );

foreach ( array( 'chattanooga-cms-admin/create-event', 'chattanooga-cms-admin/update-event' ) as $name ) {
	if ( true !== $abilities[ $name ]->check_permissions() ) {
		fwrite( STDERR, "events-manager-mutation-permission-cli: edit_events did not grant {$name}.\n" );
		exit( 1 );
	}
}
foreach ( array( 'chattanooga-cms-admin/create-location', 'chattanooga-cms-admin/update-location' ) as $name ) {
	if ( true === $abilities[ $name ]->check_permissions() ) {
		fwrite( STDERR, "events-manager-mutation-permission-cli: edit_events leaked location mutation {$name}.\n" );
		exit( 1 );
	}
}

$limited = new WP_User( $user_id );
$limited->remove_cap( 'edit_events' );
$limited->add_cap( 'edit_locations' );
wp_set_current_user( 0 );
wp_set_current_user( $user_id );

foreach ( array( 'chattanooga-cms-admin/create-location', 'chattanooga-cms-admin/update-location' ) as $name ) {
	if ( true !== $abilities[ $name ]->check_permissions() ) {
		fwrite( STDERR, "events-manager-mutation-permission-cli: edit_locations did not grant {$name}.\n" );
		exit( 1 );
	}
}
foreach ( array( 'chattanooga-cms-admin/create-event', 'chattanooga-cms-admin/update-event' ) as $name ) {
	if ( true === $abilities[ $name ]->check_permissions() ) {
		fwrite( STDERR, "events-manager-mutation-permission-cli: edit_locations leaked event mutation {$name}.\n" );
		exit( 1 );
	}
}

wp_set_current_user( $admin->ID );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );

echo "events-manager-mutation-permission-cli: PASS abilities=4 anonymous=denied administrator=allowed event-location=isolated\n";
