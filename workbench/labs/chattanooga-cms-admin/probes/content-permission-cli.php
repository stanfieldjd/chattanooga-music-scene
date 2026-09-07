<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "content-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$map = array(
	'chattanooga-cms-admin/list-posts'           => 'edit_posts',
	'chattanooga-cms-admin/get-post'             => 'edit_posts',
	'chattanooga-cms-admin/create-post-draft'    => 'edit_posts',
	'chattanooga-cms-admin/update-post'           => 'edit_posts',
	'chattanooga-cms-admin/trash-post'            => 'delete_posts',
	'chattanooga-cms-admin/restore-post'          => 'delete_posts',
	'chattanooga-cms-admin/restore-post-revision' => 'edit_posts',
	'chattanooga-cms-admin/list-pages'            => 'edit_pages',
	'chattanooga-cms-admin/get-page'              => 'edit_pages',
	'chattanooga-cms-admin/create-page-draft'     => 'edit_pages',
	'chattanooga-cms-admin/update-page'            => 'edit_pages',
	'chattanooga-cms-admin/trash-page'             => 'delete_pages',
	'chattanooga-cms-admin/restore-page'           => 'delete_pages',
	'chattanooga-cms-admin/restore-page-revision'  => 'edit_pages',
);

$expected = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/expected-content-abilities.json' ), true );
if ( ! is_array( $expected ) ) {
	fwrite( STDERR, "content-permission-cli: expected-content fixture is unreadable.\n" );
	exit( 1 );
}
$mapped = array_keys( $map );
sort( $expected );
sort( $mapped );
if ( $expected !== $mapped ) {
	fwrite( STDERR, "content-permission-cli: capability map does not match content fixture.\n" );
	exit( 1 );
}

$abilities = array();
foreach ( $map as $name => $capability ) {
	$ability = wp_get_ability( $name );
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "content-permission-cli: missing ability {$name}.\n" );
		exit( 1 );
	}
	$abilities[ $name ] = $ability;
}

$permission = static function ( $ability ) {
	$result = $ability->check_permissions();
	return true === $result;
};

wp_set_current_user( 0 );
foreach ( $abilities as $name => $ability ) {
	if ( $permission( $ability ) ) {
		fwrite( STDERR, "content-permission-cli: anonymous access leaked for {$name}.\n" );
		exit( 1 );
	}
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "content-permission-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
foreach ( $abilities as $name => $ability ) {
	if ( ! $permission( $ability ) ) {
		fwrite( STDERR, "content-permission-cli: administrator denied {$name}.\n" );
		exit( 1 );
	}
}

$login = 'cmsa_limited_' . strtolower( wp_generate_password( 8, false, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 20, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "content-permission-cli: limited user creation failed.\n" );
	exit( 1 );
}
$limited = new WP_User( $user_id );
$limited->set_role( 'subscriber' );
$limited->add_cap( 'edit_posts', true );
$limited->add_cap( 'delete_posts', true );
clean_user_cache( $user_id );

wp_set_current_user( $admin->ID );
$other_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'CMSA other owner', 'post_author' => $admin->ID ), true );
$own_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'CMSA limited owner', 'post_author' => $user_id ), true );
$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'CMSA limited page', 'post_author' => $user_id ), true );
if ( is_wp_error( $other_id ) || is_wp_error( $own_id ) || is_wp_error( $page_id ) ) {
	fwrite( STDERR, "content-permission-cli: content fixtures failed.\n" );
	exit( 1 );
}

wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	$should_allow = in_array( $map[ $name ], array( 'edit_posts', 'delete_posts' ), true );
	if ( $permission( $ability ) !== $should_allow ) {
		fwrite( STDERR, "content-permission-cli: limited capability mismatch {$name}.\n" );
		exit( 1 );
	}
}

$content = new CMSA_Content();
$other = $content->get_item( 'post', $other_id );
$own = $content->get_item( 'post', $own_id );
$page = $content->get_item( 'page', $page_id );
if ( ! is_wp_error( $other ) || 'cmsa_content_permission' !== $other->get_error_code() ) {
	fwrite( STDERR, "content-permission-cli: other-author post was exposed.\n" );
	exit( 1 );
}
if ( is_wp_error( $own ) || (int) $own['item']['id'] !== (int) $own_id ) {
	fwrite( STDERR, "content-permission-cli: own post was not readable.\n" );
	exit( 1 );
}
if ( ! is_wp_error( $page ) || 'cmsa_content_permission' !== $page->get_error_code() ) {
	fwrite( STDERR, "content-permission-cli: page capability boundary failed.\n" );
	exit( 1 );
}

$list = $content->list_items( 'post', array( 'search' => 'CMSA', 'per_page' => 100 ) );
if ( is_wp_error( $list ) ) {
	fwrite( STDERR, "content-permission-cli: limited list failed.\n" );
	exit( 1 );
}
$ids = array_map(
	static function ( $item ) { return (int) $item['id']; },
	$list['items']
);
if ( ! in_array( (int) $own_id, $ids, true ) || in_array( (int) $other_id, $ids, true ) ) {
	fwrite( STDERR, "content-permission-cli: author-scoped listing failed.\n" );
	exit( 1 );
}

wp_set_current_user( $admin->ID );
wp_delete_post( $other_id, true );
wp_delete_post( $own_id, true );
wp_delete_post( $page_id, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );

echo 'content-permission-cli: PASS abilities=' . count( $abilities ) . " limited=post-only object-scope=verified\n";
