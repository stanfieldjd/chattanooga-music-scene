<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "member-mutation-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$expected = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/expected-member-mutation-abilities.json' ), true );
if ( ! is_array( $expected ) || 2 !== count( $expected ) ) {
	fwrite( STDERR, "member-mutation-permission-cli: expected manifest missing.\n" );
	exit( 1 );
}
$abilities = array();
foreach ( $expected as $name ) {
	$ability = wp_get_ability( $name );
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "member-mutation-permission-cli: missing ability {$name}.\n" );
		exit( 1 );
	}
	$abilities[ $name ] = $ability;
}
$allowed = static function ( $name ) use ( $abilities ) {
	return true === $abilities[ $name ]->check_permissions();
};
$profile_name = 'chattanooga-cms-admin/update-member-profile';
$roles_name = 'chattanooga-cms-admin/set-member-roles';

wp_set_current_user( 0 );
if ( $allowed( $profile_name ) || $allowed( $roles_name ) ) {
	fwrite( STDERR, "member-mutation-permission-cli: anonymous mutation access leaked.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "member-mutation-permission-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
if ( ! $allowed( $profile_name ) || ! $allowed( $roles_name ) ) {
	fwrite( STDERR, "member-mutation-permission-cli: administrator denied.\n" );
	exit( 1 );
}

$login = 'cmsa_member_mut_cap_' . strtolower( wp_generate_password( 8, false, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 20, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "member-mutation-permission-cli: limited user creation failed.\n" );
	exit( 1 );
}
$user = new WP_User( $user_id );
$user->set_role( 'subscriber' );
clean_user_cache( $user_id );
wp_set_current_user( 0 );
wp_set_current_user( $user_id );
if ( $allowed( $profile_name ) || $allowed( $roles_name ) ) {
	fwrite( STDERR, "member-mutation-permission-cli: subscriber unexpectedly allowed.\n" );
	exit( 1 );
}

$user = new WP_User( $user_id );
$user->add_cap( 'edit_users', true );
clean_user_cache( $user_id );
wp_set_current_user( 0 );
wp_set_current_user( $user_id );
if ( ! $allowed( $profile_name ) || $allowed( $roles_name ) ) {
	fwrite( STDERR, "member-mutation-permission-cli: edit_users isolation failed.\n" );
	exit( 1 );
}

$user = new WP_User( $user_id );
$user->add_cap( 'promote_users', true );
clean_user_cache( $user_id );
wp_set_current_user( 0 );
wp_set_current_user( $user_id );
if ( ! $allowed( $profile_name ) || ! $allowed( $roles_name ) ) {
	fwrite( STDERR, "member-mutation-permission-cli: promote_users grant failed.\n" );
	exit( 1 );
}

wp_set_current_user( $admin->ID );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );

echo "member-mutation-permission-cli: PASS anonymous=denied admin=both edit_users=profile-only promote_users=roles\n";
