<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "content-taxonomy-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}
if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$map = array(
	'chattanooga-cms-admin/list-categories'        => 'edit_posts',
	'chattanooga-cms-admin/get-category'           => 'edit_posts',
	'chattanooga-cms-admin/create-category'        => 'manage_categories',
	'chattanooga-cms-admin/update-category'        => 'manage_categories',
	'chattanooga-cms-admin/list-tags'              => 'edit_posts',
	'chattanooga-cms-admin/get-tag'                => 'edit_posts',
	'chattanooga-cms-admin/create-tag'             => 'manage_categories',
	'chattanooga-cms-admin/update-tag'             => 'manage_categories',
	'chattanooga-cms-admin/assign-post-categories' => 'edit_posts',
	'chattanooga-cms-admin/remove-post-categories' => 'edit_posts',
	'chattanooga-cms-admin/assign-post-tags'       => 'edit_posts',
	'chattanooga-cms-admin/remove-post-tags'       => 'edit_posts',
);
$expected = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/expected-content-taxonomy-abilities.json' ), true );
$mapped = array_keys( $map );
sort( $expected );
sort( $mapped );
if ( ! is_array( $expected ) || $expected !== $mapped ) {
	fwrite( STDERR, "content-taxonomy-permission-cli: taxonomy capability map mismatch.\n" );
	exit( 1 );
}

$abilities = array();
foreach ( $map as $name => $capability ) {
	$ability = wp_get_ability( $name );
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "content-taxonomy-permission-cli: missing ability {$name}.\n" );
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
		fwrite( STDERR, "content-taxonomy-permission-cli: anonymous access leaked for {$name}.\n" );
		exit( 1 );
	}
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "content-taxonomy-permission-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
foreach ( $abilities as $name => $ability ) {
	if ( ! $allowed( $ability ) ) {
		fwrite( STDERR, "content-taxonomy-permission-cli: administrator denied {$name}.\n" );
		exit( 1 );
	}
}

$login = 'cmsa_tax_cap_' . strtolower( wp_generate_password( 8, false, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 20, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "content-taxonomy-permission-cli: limited user creation failed.\n" );
	exit( 1 );
}
$user = new WP_User( $user_id );
$user->set_role( 'subscriber' );
$user->add_cap( 'edit_posts', true );
clean_user_cache( $user_id );
wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	$should_allow = 'edit_posts' === $map[ $name ];
	if ( $allowed( $ability ) !== $should_allow ) {
		fwrite( STDERR, "content-taxonomy-permission-cli: limited capability mismatch {$name}.\n" );
		exit( 1 );
	}
}

wp_set_current_user( $admin->ID );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );

echo "content-taxonomy-permission-cli: PASS abilities=12 anonymous=denied admin=all limited=read-and-relations-only\n";
