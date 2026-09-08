<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "content-deletion-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$map = array(
	'chattanooga-cms-admin/delete-post-permanently' => 'delete_posts',
	'chattanooga-cms-admin/delete-page-permanently' => 'delete_pages',
);
$expected = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/expected-content-deletion-abilities.json' ), true );
if ( ! is_array( $expected ) ) {
	fwrite( STDERR, "content-deletion-permission-cli: expected fixture is unreadable.\n" );
	exit( 1 );
}
$mapped = array_keys( $map );
sort( $expected );
sort( $mapped );
if ( $expected !== $mapped ) {
	fwrite( STDERR, "content-deletion-permission-cli: capability map does not match fixture.\n" );
	exit( 1 );
}

$abilities = array();
foreach ( $map as $name => $capability ) {
	$ability = wp_get_ability( $name );
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "content-deletion-permission-cli: missing ability {$name}.\n" );
		exit( 1 );
	}
	$abilities[ $name ] = $ability;
}
$permission = static function ( $ability ) {
	return true === $ability->check_permissions();
};

wp_set_current_user( 0 );
foreach ( $abilities as $name => $ability ) {
	if ( $permission( $ability ) ) {
		fwrite( STDERR, "content-deletion-permission-cli: anonymous access leaked for {$name}.\n" );
		exit( 1 );
	}
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "content-deletion-permission-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
foreach ( $abilities as $name => $ability ) {
	if ( ! $permission( $ability ) ) {
		fwrite( STDERR, "content-deletion-permission-cli: administrator denied {$name}.\n" );
		exit( 1 );
	}
}

$login = 'cmsa_delete_limited_' . strtolower( wp_generate_password( 8, false, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 20, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "content-deletion-permission-cli: limited user creation failed.\n" );
	exit( 1 );
}
$limited = new WP_User( $user_id );
$limited->set_role( 'subscriber' );
$limited->add_cap( 'delete_posts', true );
clean_user_cache( $user_id );

wp_set_current_user( $admin->ID );
$other_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => 'CMSA delete other owner',
		'post_author' => $admin->ID,
	),
	true
);
$own_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => 'CMSA delete limited owner',
		'post_author' => $user_id,
	),
	true
);
$page_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'CMSA delete limited page',
		'post_author' => $user_id,
	),
	true
);
if ( is_wp_error( $other_id ) || is_wp_error( $own_id ) || is_wp_error( $page_id ) ) {
	fwrite( STDERR, "content-deletion-permission-cli: content fixture creation failed.\n" );
	exit( 1 );
}
wp_trash_post( $other_id );
wp_trash_post( $own_id );
wp_trash_post( $page_id );
$other = get_post( $other_id );
$own = get_post( $own_id );

wp_set_current_user( $user_id );
if ( ! $permission( $abilities['chattanooga-cms-admin/delete-post-permanently'] ) || $permission( $abilities['chattanooga-cms-admin/delete-page-permanently'] ) ) {
	fwrite( STDERR, "content-deletion-permission-cli: coarse delete capability boundary failed.\n" );
	exit( 1 );
}

$deletion = new CMSA_Content_Deletion();
$other_result = $deletion->delete_permanently(
	'post',
	array(
		'id'                       => (int) $other_id,
		'expected_modified_gmt'    => $other instanceof WP_Post ? $other->post_modified_gmt : '',
		'confirm_permanent_delete' => true,
	)
);
if ( ! is_wp_error( $other_result ) || 'cmsa_content_delete_permission' !== $other_result->get_error_code() || ! get_post( $other_id ) ) {
	fwrite( STDERR, "content-deletion-permission-cli: other-author hard-delete boundary failed.\n" );
	exit( 1 );
}

$own_result = $deletion->delete_permanently(
	'post',
	array(
		'id'                       => (int) $own_id,
		'expected_modified_gmt'    => $own instanceof WP_Post ? $own->post_modified_gmt : '',
		'confirm_permanent_delete' => false,
	)
);
if ( ! is_wp_error( $own_result ) || 'cmsa_content_delete_confirmation' !== $own_result->get_error_code() || ! get_post( $own_id ) ) {
	fwrite( STDERR, "content-deletion-permission-cli: own-post object authority/confirmation boundary failed.\n" );
	exit( 1 );
}

wp_set_current_user( $admin->ID );
wp_delete_post( $other_id, true );
wp_delete_post( $own_id, true );
wp_delete_post( $page_id, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );

echo "content-deletion-permission-cli: PASS abilities=2 anonymous=denied administrator=allowed limited=post-only object-scope=verified\n";
