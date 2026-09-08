<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "media-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$fixture = dirname( __DIR__ ) . '/fixtures/expected-media-abilities.json';
$expected = json_decode( (string) file_get_contents( $fixture ), true );
if ( ! is_array( $expected ) ) {
	fwrite( STDERR, "media-permission-cli: expected media ability fixture is unreadable.\n" );
	exit( 1 );
}

$abilities = array();
foreach ( $expected as $name ) {
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "media-permission-cli: missing registered ability {$name}.\n" );
		exit( 1 );
	}
	$abilities[ $name ] = $ability;
}

$allowed = static function ( $ability ) {
	$result = $ability->check_permissions();
	return true === $result;
};

wp_set_current_user( 0 );
foreach ( $abilities as $name => $ability ) {
	if ( $allowed( $ability ) ) {
		fwrite( STDERR, "media-permission-cli: anonymous access leaked for {$name}.\n" );
		exit( 1 );
	}
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "media-permission-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
foreach ( $abilities as $name => $ability ) {
	if ( ! $allowed( $ability ) ) {
		fwrite( STDERR, "media-permission-cli: administrator denied {$name}.\n" );
		exit( 1 );
	}
}

$login = 'cmsa_media_' . strtolower( wp_generate_password( 8, false, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 20, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "media-permission-cli: limited user creation failed.\n" );
	exit( 1 );
}
$limited = new WP_User( $user_id );
$limited->set_role( 'subscriber' );

wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	if ( $allowed( $ability ) ) {
		fwrite( STDERR, "media-permission-cli: subscriber unexpectedly allowed {$name}.\n" );
		exit( 1 );
	}
}

$limited->add_cap( 'upload_files', true );
$limited->add_cap( 'edit_posts', true );
clean_user_cache( $user_id );
wp_set_current_user( 0 );
wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	if ( ! $allowed( $ability ) ) {
		fwrite( STDERR, "media-permission-cli: upload_files capability did not open bounded media ability {$name}.\n" );
		exit( 1 );
	}
}

wp_set_current_user( $admin->ID );
$other_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'CMSA media other owner',
		'post_status'    => 'inherit',
		'post_author'    => $admin->ID,
	),
	false,
	0,
	true
);
$own_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'CMSA media limited owner',
		'post_status'    => 'inherit',
		'post_author'    => $user_id,
	),
	false,
	0,
	true
);
if ( is_wp_error( $other_id ) || is_wp_error( $own_id ) ) {
	fwrite( STDERR, "media-permission-cli: attachment fixtures failed.\n" );
	exit( 1 );
}

wp_set_current_user( $user_id );
$media = new CMSA_Media();
$other = $media->get_item( $other_id );
$own = $media->get_item( $own_id );
if ( ! is_wp_error( $other ) || 'cmsa_media_permission' !== $other->get_error_code() ) {
	fwrite( STDERR, "media-permission-cli: other-owner attachment was exposed.\n" );
	exit( 1 );
}
if ( is_wp_error( $own ) || (int) $own['item']['id'] !== (int) $own_id ) {
	fwrite( STDERR, "media-permission-cli: own attachment was not readable.\n" );
	exit( 1 );
}
$list = $media->list_items( array( 'search' => 'CMSA media', 'per_page' => 100 ) );
if ( is_wp_error( $list ) ) {
	fwrite( STDERR, "media-permission-cli: limited media list failed.\n" );
	exit( 1 );
}
$ids = array_map( static function ( $item ) { return (int) $item['id']; }, $list['items'] );
if ( ! in_array( (int) $own_id, $ids, true ) || in_array( (int) $other_id, $ids, true ) ) {
	fwrite( STDERR, "media-permission-cli: author-scoped media listing failed.\n" );
	exit( 1 );
}

wp_set_current_user( $admin->ID );
wp_delete_attachment( $other_id, true );
wp_delete_attachment( $own_id, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );

echo 'media-permission-cli: PASS abilities=' . count( $abilities ) . " object-scope=verified\n";
