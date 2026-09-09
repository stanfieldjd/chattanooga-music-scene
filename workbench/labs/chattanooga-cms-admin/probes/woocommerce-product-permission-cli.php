<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "woocommerce-product-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$fixture = dirname( __DIR__ ) . '/fixtures/expected-woocommerce-product-abilities.json';
$expected = json_decode( (string) file_get_contents( $fixture ), true );
if ( ! is_array( $expected ) || 4 !== count( $expected ) ) {
	fwrite( STDERR, "woocommerce-product-permission-cli: expected ability fixture is unreadable or incomplete.\n" );
	exit( 1 );
}

$abilities = array();
foreach ( $expected as $name ) {
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "woocommerce-product-permission-cli: missing registered ability {$name}.\n" );
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
		fwrite( STDERR, "woocommerce-product-permission-cli: anonymous access leaked for {$name}.\n" );
		exit( 1 );
	}
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "woocommerce-product-permission-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
foreach ( $abilities as $name => $ability ) {
	if ( ! $allowed( $ability ) ) {
		fwrite( STDERR, "woocommerce-product-permission-cli: administrator denied {$name}.\n" );
		exit( 1 );
	}
}

$login = 'cmsa_wc_perm_' . strtolower( wp_generate_password( 8, false, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 24, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "woocommerce-product-permission-cli: limited user creation failed.\n" );
	exit( 1 );
}
$limited = new WP_User( $user_id );
$limited->set_role( 'subscriber' );

wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	if ( $allowed( $ability ) ) {
		fwrite( STDERR, "woocommerce-product-permission-cli: subscriber unexpectedly allowed {$name}.\n" );
		exit( 1 );
	}
}

$limited->add_cap( 'manage_woocommerce', true );
clean_user_cache( $user_id );
wp_set_current_user( 0 );
wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	if ( $allowed( $ability ) ) {
		fwrite( STDERR, "woocommerce-product-permission-cli: manage_woocommerce alone opened {$name}.\n" );
		exit( 1 );
	}
}

$limited->remove_cap( 'manage_woocommerce' );
$limited->add_cap( 'edit_products', true );
clean_user_cache( $user_id );
wp_set_current_user( 0 );
wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	if ( $allowed( $ability ) ) {
		fwrite( STDERR, "woocommerce-product-permission-cli: edit_products alone opened {$name}.\n" );
		exit( 1 );
	}
}

$limited->add_cap( 'manage_woocommerce', true );
clean_user_cache( $user_id );
wp_set_current_user( 0 );
wp_set_current_user( $user_id );
foreach ( $abilities as $name => $ability ) {
	if ( ! $allowed( $ability ) ) {
		fwrite( STDERR, "woocommerce-product-permission-cli: bounded catalog capabilities did not open {$name}.\n" );
		exit( 1 );
	}
}

wp_set_current_user( $admin->ID );
$own_product = new WC_Product_Simple();
$own_product->set_name( 'CMSA permission product own' );
$own_product->set_status( 'draft' );
$own_id = (int) $own_product->save();
$other_product = new WC_Product_Simple();
$other_product->set_name( 'CMSA permission product other' );
$other_product->set_status( 'draft' );
$other_id = (int) $other_product->save();
if ( $own_id < 1 || $other_id < 1 ) {
	fwrite( STDERR, "woocommerce-product-permission-cli: product fixtures failed.\n" );
	exit( 1 );
}
$author_update = wp_update_post( array( 'ID' => $own_id, 'post_author' => $user_id ), true );
if ( is_wp_error( $author_update ) ) {
	fwrite( STDERR, "woocommerce-product-permission-cli: own-product author fixture failed.\n" );
	exit( 1 );
}

wp_set_current_user( $user_id );
$service = new CMSA_WooCommerce_Products();
$own = $service->get_item( $own_id );
$other = $service->get_item( $other_id );
if ( is_wp_error( $own ) || (int) $own['product']['id'] !== $own_id ) {
	fwrite( STDERR, "woocommerce-product-permission-cli: owner-scoped product was not readable.\n" );
	exit( 1 );
}
if ( ! is_wp_error( $other ) || 'cmsa_woocommerce_product_permission' !== $other->get_error_code() ) {
	fwrite( STDERR, "woocommerce-product-permission-cli: other-owner product was exposed without edit_others_products.\n" );
	exit( 1 );
}
$list = $service->list_items( array( 'search' => 'CMSA permission product', 'per_page' => 100 ) );
if ( is_wp_error( $list ) ) {
	fwrite( STDERR, "woocommerce-product-permission-cli: owner-scoped product list failed.\n" );
	exit( 1 );
}
$list_ids = array_map( static function ( $item ) { return (int) $item['id']; }, $list['items'] );
if ( ! in_array( $own_id, $list_ids, true ) || in_array( $other_id, $list_ids, true ) ) {
	fwrite( STDERR, "woocommerce-product-permission-cli: owner-scoped product listing failed.\n" );
	exit( 1 );
}

wp_set_current_user( $admin->ID );
foreach ( array( $own_id, $other_id ) as $product_id ) {
	$product = wc_get_product( $product_id );
	if ( $product ) {
		$product->delete( true );
	}
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );

echo 'woocommerce-product-permission-cli: PASS abilities=' . count( $abilities ) . " primitive-gate=verified object-scope=verified\n";
