<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "woocommerce-order-baseline-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

if ( ! function_exists( 'wc_create_order' ) || ! function_exists( 'wc_get_order' ) ) {
	fwrite( STDERR, "woocommerce-order-baseline-cli: WooCommerce order helpers are unavailable.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "woocommerce-order-baseline-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

$snapshot = static function ( $order ) {
	return array(
		'id'          => (int) $order->get_id(),
		'status'      => (string) $order->get_status(),
		'customer_id' => (int) $order->get_customer_id(),
		'total'       => (string) $order->get_total(),
		'item_count'  => count( $order->get_items() ),
	);
};

$login = 'cmsa_wc_order_baseline_' . strtolower( wp_generate_password( 8, false, false ) );
$user_id = wp_create_user( $login, wp_generate_password( 24, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "woocommerce-order-baseline-cli: customer fixture failed.\n" );
	exit( 1 );
}
$customer = new WP_User( $user_id );
$customer->set_role( 'customer' );

$order = wc_create_order( array( 'customer_id' => (int) $user_id ) );
if ( is_wp_error( $order ) || ! is_object( $order ) ) {
	fwrite( STDERR, "woocommerce-order-baseline-cli: order fixture failed.\n" );
	exit( 1 );
}
$order_id = (int) $order->get_id();
$in_memory = $snapshot( $order );
$persisted_order = wc_get_order( $order_id );
if ( ! $persisted_order ) {
	fwrite( STDERR, "woocommerce-order-baseline-cli: persisted order could not be reloaded.\n" );
	exit( 1 );
}
$persisted = $snapshot( $persisted_order );

$result = array(
	'in_memory' => $in_memory,
	'persisted' => $persisted,
	'matches'   => $in_memory === $persisted,
);
echo 'woocommerce-order-baseline-cli: ' . wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) . "\n";

$deleted = $persisted_order->delete( true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );
if ( false === $deleted || wc_get_order( $order_id ) ) {
	fwrite( STDERR, "woocommerce-order-baseline-cli: disposable order cleanup failed.\n" );
	exit( 1 );
}

echo "woocommerce-order-baseline-cli: PASS diagnostic captured and disposable fixture removed\n";
