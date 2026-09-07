<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "member-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$expected = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/expected-member-abilities.json' ), true );
if ( ! is_array( $expected ) ) {
	fwrite( STDERR, "member-permission-cli: expected member manifest missing.\n" );
	exit( 1 );
}
$abilities = array();
foreach ( $expected as $name ) {
	$ability = wp_get_ability( $name );
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "member-permission-cli: missing ability {$name}.\n" );
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
		fwrite( STDERR, "member-permission-cli: anonymous access leaked for {$name}.\n" );
		exit( 1 );
	}
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "member-permission-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
foreach ( $abilities as $name => $ability ) {
	if ( ! $allowed( $ability ) ) {
		fwrite( STDERR, "member-permission-cli: administrator denied {$name}.\n" );
		exit( 1 );
	}
}

$login = 'cmsa_member_cap_' . strtolower( wp_generate_password( 8, false, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 20, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "member-permission-cli: limited user creation failed.\n" );
	exit( 1 );
}
$user = new WP_User( $user_id );
$user->set_role( 'subscriber' );
clean_user_cache( $user_id );
wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	if ( $allowed( $ability ) ) {
		fwrite( STDERR, "member-permission-cli: subscriber unexpectedly allowed {$name}.\n" );
		exit( 1 );
	}
}

$user->add_cap( 'list_users', true );
clean_user_cache( $user_id );
wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	if ( ! $allowed( $ability ) ) {
		fwrite( STDERR, "member-permission-cli: list_users capability denied {$name}.\n" );
		exit( 1 );
	}
}

wp_set_current_user( $admin->ID );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );

echo "member-permission-cli: PASS abilities=4 anonymous=denied admin=allowed list_users=required\n";
