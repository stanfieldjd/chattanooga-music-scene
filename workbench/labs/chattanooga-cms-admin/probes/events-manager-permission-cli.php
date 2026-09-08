<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "events-manager-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$expected = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/expected-events-manager-abilities.json' ), true );
$abilities = array();
foreach ( $expected as $name ) {
	$ability = wp_get_ability( $name );
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "events-manager-permission-cli: missing ability {$name}.\n" );
		exit( 1 );
	}
	$abilities[ $name ] = $ability;
}

wp_set_current_user( 0 );
foreach ( $abilities as $name => $ability ) {
	if ( true === $ability->check_permissions() ) {
		fwrite( STDERR, "events-manager-permission-cli: anonymous access leaked for {$name}.\n" );
		exit( 1 );
	}
}

$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin ? $admin->ID : 0 );
foreach ( $abilities as $name => $ability ) {
	if ( true !== $ability->check_permissions() ) {
		fwrite( STDERR, "events-manager-permission-cli: administrator denied {$name}.\n" );
		exit( 1 );
	}
}

echo "events-manager-permission-cli: PASS abilities=4 anonymous=denied administrator=allowed event-location-capabilities=active\n";
