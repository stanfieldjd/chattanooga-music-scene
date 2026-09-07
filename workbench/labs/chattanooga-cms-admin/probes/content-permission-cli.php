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

$capability_map = array(
	'chattanooga-cms-admin/list-posts'            => 'edit_posts',
	'chattanooga-cms-admin/get-post'              => 'edit_posts',
	'chattanooga-cms-admin/create-post-draft'     => 'edit_posts',
	'chattanooga-cms-admin/update-post'            => 'edit_posts',
	'chattanooga-cms-admin/trash-post'             => 'delete_posts',
	'chattanooga-cms-admin/restore-post'           => 'delete_posts',
	'chattanooga-cms-admin/restore-post-revision'  => 'edit_posts',
	'chattanooga-cms-admin/list-pages'             => 'edit_pages',
	'chattanooga-cms-admin/get-page'               => 'edit_pages',
	'chattanooga-cms-admin/create-page-draft'      => 'edit_pages',
	'chattanooga-cms-admin/update-page'             => 'edit_pages',
	'chattanooga-cms-admin/trash-page'              => 'delete_pages',
	'chattanooga-cms-admin/restore-page'            => 'delete_pages',
	'chattanooga-cms-admin/restore-page-revision'   => 'edit_pages',
);

$lab = dirname( __DIR__ );
$expected = json_decode( file_get_contents( $lab . '/fixtures/expected-content-abilities.json' ), true );
$mapped = array_keys( $capability_map );
sort( $expected );
sort( $mapped );
if ( ! is_array( $expected ) || $expected !== $mapped ) {
	fwrite( STDERR, "content-permission-cli: content capability map does not match fixture.\n" );
	exit( 1 );
}

$abilities = array();
foreach ( $capability_map as $name => $capability ) {
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "content-permission-cli: missing content ability {$name}.\n" );
		exit( 1 );
	}
	$abilities[ $name ] = $ability;
}

$allowed = static function ( $ability ) {
	$result = $ability->check_permissions();
	return ! is_wp_error( $result ) && true === $result;
};

wp_set_current_user( 0 );
foreach ( $abilities as $name => $ability ) {
	if ( $allowed( $ability ) ) {
		fwrite( STDERR, "content-permission-cli: anonymous user unexpectedly passed {$name}.\n" );
		exit( 1 );
	}
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "content-permission-cli: disposable administrator missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
foreach ( $abilities as $name => $ability ) {
	if ( ! $allowed( $ability ) ) {
		fwrite( STDERR, "content-permission-cli: administrator unexpectedly denied {$name}.\n" );
		exit( 1 );
}

$limited_login = 'cmsa_content_limited_' . strtolower( wp_generate_password( 8, false, false ) );
$limited_id = wp_create_user( $limited_login, wp_generate_password( 24, true, true ), $limited_login . '@example.invalid' );
if ( is_wp_error( $limited_id ) ) {
	fwrite( STDERR, "content-permission-cli: could not create limited probe user.\n" );
	exit( 1 );
}
$limited = new WP_User( $limited_id );
$limited->set_role( 'subscriber' );
$limited->add_cap( 'edit_posts', true );
$limited->add_cap( 'delete_posts', true );
clean_user_cache( $limited_id );

$admin_post_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => 'CMSA admin-owned permission fixture',
		'post_author' => $admin->ID,
	),
	true
);
$own_post_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => 'CMSA limited-owned permission fixture',
		'post_author' => $limited_id,
	),
	true
);
$page_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'CMSA page permission fixture',
		'post_author' => $limited_id,
	),
	true
);
if ( is_wp_error( $admin_post_id ) || is_wp_error( $own_post_id ) || is_wp_error( $page_id ) ) {
	fwrite( STDERR, "content-permission-cli: could not create content fixtures.\n" );
	exit( 1 );
}

wp_set_current_user( $limited_id );
foreach ( $abilities as $name => $ability ) {
	$capability = $capability_map[ $name ];
	$should_allow = in_array( $capability, array( 'edit_posts', 'delete_posts' ), true );
	if ( $allowed( $ability ) !== $should_allow ) {
		fwrite( STDERR, "content-permission-cli: limited capability mismatch {$name}.\n" );
		exit( 1 );
	}
}

$content = new CMSA_Content();
$other = $content->get_item( 'post', $admin_post_id );
if ( ! is_wp_error( $other ) || 'cmsa_content_permission' !== $other->get_error_code() ) {
	fwrite( STDERR, "content-permission-cli: limited user could inspect another author's post.\n" );
	exit( 1 );
}
$own = $content->get_item( 'post', $own_post_id );
if ( is_wp_error( $own ) || (int) $own['item']['id'] !== (int) $own_post_id ) {
	fwrite( STDERR, "content-permission-cli: limited user could not inspect own post.\n" );
	exit( 1 );
}
$list = $content->list_items( 'post', array( 'search' => 'CMSA', 'per_page' => 100 ) );
if ( is_wp_error( $list ) ) {
	fwrite( STDERR, "content-permission-cli: limited post list failed.\n" );
	exit( 1 );
}
$list_ids = array();
foreach ( $list['items'] as $item ) {
	$list_ids[] = (int) $item['id'];
}
if ( ! in_array( (int) $own_post_id, $list_ids, true ) || in_array( (int) $admin_post_id, $list_ids, true ) ) {
	fwrite( STDERR, "content-permission-cli: author scoping failed.\n" );
	exit( 1 );
}
$page = $content->get_item( 'page', $page_id );
if ( ! is_wp_error( $page ) || 'cmsa_content_permission' !== $page->get_error_code() ) {
	fwrite( STDERR, "content-permission-cli: edit_pages boundary failed.\n" );
	exit( 1 );
}

wp_set_current_user( $admin->ID );
wp_delete_post( $admin_post_id, true );
wp_delete_post( $own_post_id, true );
wp_delete_post( $page_id, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $limited_id );

echo 'content-permission-cli: PASS abilities=' . count( $abilities ) . " limited=own-post-only pages=denied object-scope=verified\n";
