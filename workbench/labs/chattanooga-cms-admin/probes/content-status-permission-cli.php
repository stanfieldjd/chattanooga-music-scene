<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "content-status-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$post_ability = wp_get_ability( 'chattanooga-cms-admin/set-post-status' );
$page_ability = wp_get_ability( 'chattanooga-cms-admin/set-page-status' );
if ( ! $post_ability || ! $page_ability || ! method_exists( $post_ability, 'check_permissions' ) || ! method_exists( $page_ability, 'check_permissions' ) ) {
	fwrite( STDERR, "content-status-permission-cli: status abilities missing.\n" );
	exit( 1 );
}
$allowed = static function ( $ability ) {
	return true === $ability->check_permissions();
};

wp_set_current_user( 0 );
if ( $allowed( $post_ability ) || $allowed( $page_ability ) ) {
	fwrite( STDERR, "content-status-permission-cli: anonymous access leaked.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "content-status-permission-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
if ( ! $allowed( $post_ability ) || ! $allowed( $page_ability ) ) {
	fwrite( STDERR, "content-status-permission-cli: administrator denied.\n" );
	exit( 1 );
}

$login = 'cmsa_status_cap_' . strtolower( wp_generate_password( 8, false, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 20, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "content-status-permission-cli: limited user creation failed.\n" );
	exit( 1 );
}
$user = new WP_User( $user_id );
$user->set_role( 'subscriber' );
$user->add_cap( 'edit_posts', true );
clean_user_cache( $user_id );
wp_set_current_user( $user_id );
if ( ! $allowed( $post_ability ) || $allowed( $page_ability ) ) {
	fwrite( STDERR, "content-status-permission-cli: post/page coarse capability isolation failed.\n" );
	exit( 1 );
}

wp_set_current_user( $admin->ID );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );

echo "content-status-permission-cli: PASS anonymous=denied admin=post,page limited=post-only\n";
