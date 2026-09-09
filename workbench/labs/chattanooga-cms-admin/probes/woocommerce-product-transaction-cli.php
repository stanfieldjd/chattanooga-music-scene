<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "woocommerce-product-transaction-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "woocommerce-product-transaction-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

$service = new CMSA_WooCommerce_Products();
$product_ids = static function () {
	$ids = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private', 'trash' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	$ids = array_map( 'intval', $ids );
	sort( $ids, SORT_NUMERIC );
	return $ids;
};
$fail = static function ( $message ) {
	fwrite( STDERR, 'woocommerce-product-transaction-cli: ' . $message . "\n" );
	exit( 1 );
};
$order_snapshot = static function ( $order ) {
	return array(
		'id'          => (int) $order->get_id(),
		'status'      => (string) $order->get_status(),
		'customer_id' => (int) $order->get_customer_id(),
		'total'       => (string) $order->get_total(),
		'item_count'  => count( $order->get_items() ),
	);
};

$category = wp_insert_term( 'CMSA Woo Category ' . wp_generate_password( 6, false, false ), 'product_cat' );
$tag = wp_insert_term( 'CMSA Woo Tag ' . wp_generate_password( 6, false, false ), 'product_tag' );
if ( is_wp_error( $category ) || is_wp_error( $tag ) ) {
	$fail( 'taxonomy fixtures failed.' );
}
$category_id = (int) $category['term_id'];
$tag_id = (int) $tag['term_id'];

$image_one = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'CMSA Woo image one',
		'post_status'    => 'inherit',
		'post_author'    => $admin->ID,
	),
	false,
	0,
	true
);
$image_two = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => 'CMSA Woo image two',
		'post_status'    => 'inherit',
		'post_author'    => $admin->ID,
	),
	false,
	0,
	true
);
if ( is_wp_error( $image_one ) || is_wp_error( $image_two ) ) {
	$fail( 'media fixtures failed.' );
}
$image_one = (int) $image_one;
$image_two = (int) $image_two;

$control_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'CMSA Woo control post',
		'post_content' => 'Preserve this ordinary post.',
		'post_author'  => $admin->ID,
	),
	true
);
if ( is_wp_error( $control_post_id ) ) {
	$fail( 'ordinary post control fixture failed.' );
}
$control_post_id = (int) $control_post_id;

register_post_type( 'event', array( 'public' => false, 'show_ui' => false ) );
$control_event_id = wp_insert_post(
	array(
		'post_type'    => 'event',
		'post_status'  => 'draft',
		'post_title'   => 'CMSA Woo control event',
		'post_content' => 'Preserve this unrelated event fixture.',
		'post_author'  => $admin->ID,
	),
	true
);
if ( is_wp_error( $control_event_id ) ) {
	$fail( 'event control fixture failed.' );
}
$control_event_id = (int) $control_event_id;

$customer_login = 'cmsa_wc_customer_' . strtolower( wp_generate_password( 8, false, false ) );
$customer_id = wp_create_user( $customer_login, wp_generate_password( 24, true, true ), $customer_login . '@example.invalid' );
if ( is_wp_error( $customer_id ) ) {
	$fail( 'customer fixture failed.' );
}
$customer = new WP_User( $customer_id );
$customer->set_role( 'customer' );

if ( ! function_exists( 'wc_create_order' ) || ! function_exists( 'wc_get_order' ) ) {
	$fail( 'WooCommerce order fixture helpers are unavailable.' );
}
$order = wc_create_order( array( 'customer_id' => (int) $customer_id ) );
if ( is_wp_error( $order ) || ! is_object( $order ) ) {
	$fail( 'order control fixture failed.' );
}
$order_id = (int) $order->get_id();
$persisted_order = wc_get_order( $order_id );
if ( ! $persisted_order ) {
	$fail( 'persisted order control fixture could not be reloaded.' );
}
$order_before = $order_snapshot( $persisted_order );

$control_product = new WC_Product_Simple();
$control_product->set_name( 'CMSA Woo unrelated product' );
$control_product->set_status( 'draft' );
$control_product->set_regular_price( '8.00' );
$control_product_id = (int) $control_product->save();
if ( $control_product_id < 1 ) {
	$fail( 'unrelated product control fixture failed.' );
}
$control_product_read = $service->get_item( $control_product_id );
if ( is_wp_error( $control_product_read ) ) {
	$fail( 'unrelated product control could not be read.' );
}
$control_product_token = (string) $control_product_read['product']['state_token'];

if ( ! class_exists( 'WC_Product_Variable' ) ) {
	$fail( 'variable-product read fixture class is unavailable.' );
}
$variable = new WC_Product_Variable();
$variable->set_name( 'CMSA Woo variable read control' );
$variable->set_status( 'draft' );
$variable_id = (int) $variable->save();
if ( $variable_id < 1 ) {
	$fail( 'variable product fixture failed.' );
}
$variable_read = $service->get_item( $variable_id );
if ( is_wp_error( $variable_read ) || 'variable' !== $variable_read['product']['product_type'] ) {
	$fail( 'bounded read did not preserve native variable-product type.' );
}
$variable_update = $service->update_simple(
	array(
		'id'                   => $variable_id,
		'expected_state_token' => $variable_read['product']['state_token'],
		'name'                 => 'Must not mutate variable product',
	)
);
if ( ! is_wp_error( $variable_update ) || 'cmsa_woocommerce_product_type' !== $variable_update->get_error_code() ) {
	$fail( 'non-simple product mutation did not fail closed.' );
}

$control_post_before = get_post( $control_post_id );
$control_event_before = get_post( $control_event_id );
$image_one_before = get_post( $image_one );
$image_two_before = get_post( $image_two );
$customer_before = get_userdata( $customer_id );
$customer_snapshot = array(
	'display_name' => (string) $customer_before->display_name,
	'user_email'   => (string) $customer_before->user_email,
	'roles'        => array_values( (array) $customer_before->roles ),
);

$sku = 'cmsa-' . strtolower( wp_generate_password( 12, false, false ) );
$created = $service->create_simple_draft(
	array(
		'name'                => 'CMSA Woo product transaction',
		'description'         => '<p>Long catalog description.</p>',
		'short_description'   => '<strong>Short catalog description.</strong>',
		'sku'                 => $sku,
		'regular_price'       => '19.95',
		'sale_price'          => '14.95',
		'catalog_visibility'  => 'hidden',
		'manage_stock'        => true,
		'stock_quantity'      => 7,
		'stock_status'        => 'instock',
		'category_ids'        => array( $category_id ),
		'tag_ids'             => array( $tag_id ),
		'image_id'            => $image_one,
		'gallery_image_ids'   => array( $image_two ),
	)
);
if ( is_wp_error( $created ) || empty( $created['created'] ) ) {
	$fail( 'valid simple-product draft creation failed.' );
}
$product = $created['product'];
$product_id = (int) $product['id'];
if ( $product_id < 1 || 'draft' !== $product['status'] || 'simple' !== $product['product_type'] || 64 !== strlen( $product['state_token'] ) ) {
	$fail( 'created product identity/status/type/state-token contract failed.' );
}

$expected_detail_keys = array(
	'id', 'name', 'status', 'product_type', 'sku', 'regular_price', 'sale_price', 'catalog_visibility',
	'manage_stock', 'stock_quantity', 'stock_status', 'category_ids', 'tag_ids', 'image_id', 'modified_gmt',
	'state_token', 'description', 'short_description', 'gallery_image_ids',
);
$actual_detail_keys = array_keys( $product );
sort( $expected_detail_keys );
sort( $actual_detail_keys );
if ( $expected_detail_keys !== $actual_detail_keys ) {
	$fail( 'detailed product output escaped the explicit allowlist.' );
}
if ( $product['category_ids'] !== array( $category_id ) || $product['tag_ids'] !== array( $tag_id ) || (int) $product['image_id'] !== $image_one || $product['gallery_image_ids'] !== array( $image_two ) ) {
	$fail( 'created product relationships did not match requested state.' );
}

$list = $service->list_items( array( 'search' => 'CMSA Woo product transaction', 'per_page' => 100 ) );
if ( is_wp_error( $list ) ) {
	$fail( 'bounded product list failed.' );
}
$listed = null;
foreach ( $list['items'] as $item ) {
	if ( (int) $item['id'] === $product_id ) {
		$listed = $item;
		break;
	}
}
if ( ! is_array( $listed ) ) {
	$fail( 'created product was absent from bounded product list.' );
}
$expected_list_keys = array(
	'id', 'name', 'status', 'product_type', 'sku', 'regular_price', 'sale_price', 'catalog_visibility',
	'manage_stock', 'stock_quantity', 'stock_status', 'category_ids', 'tag_ids', 'image_id', 'modified_gmt', 'state_token',
);
$actual_list_keys = array_keys( $listed );
sort( $expected_list_keys );
sort( $actual_list_keys );
if ( $expected_list_keys !== $actual_list_keys ) {
	$fail( 'product list output escaped the explicit allowlist.' );
}

update_post_meta( $product_id, '_cmsa_control_extension_meta', 'preserve-extension-state' );
$initial_token = (string) $product['state_token'];
$updated = $service->update_simple(
	array(
		'id'                   => $product_id,
		'expected_state_token' => $initial_token,
		'name'                 => 'CMSA Woo product transaction updated',
		'regular_price'        => '21.50',
		'sale_price'           => '',
		'catalog_visibility'   => 'catalog',
		'manage_stock'         => true,
		'stock_quantity'       => 11,
		'gallery_image_ids'    => array( $image_two, $image_one ),
	)
);
if ( is_wp_error( $updated ) || empty( $updated['updated'] ) ) {
	$fail( 'valid exact-state simple-product update failed.' );
}
$current = $updated['product'];
if ( 'CMSA Woo product transaction updated' !== $current['name'] || '21.50' !== $current['regular_price'] || '' !== $current['sale_price'] || 11 !== $current['stock_quantity'] || 'catalog' !== $current['catalog_visibility'] ) {
	$fail( 'updated product did not match requested semantic state.' );
}
if ( 'preserve-extension-state' !== (string) get_post_meta( $product_id, '_cmsa_control_extension_meta', true ) ) {
	$fail( 'allowlisted product update changed unrelated extension metadata.' );
}

$stale = $service->update_simple(
	array(
		'id'                   => $product_id,
		'expected_state_token' => $initial_token,
		'name'                 => 'Stale update must fail',
	)
);
if ( ! is_wp_error( $stale ) || 'cmsa_woocommerce_product_conflict' !== $stale->get_error_code() ) {
	$fail( 'stale product update was not rejected.' );
}

$no_change = $service->update_simple(
	array(
		'id'                   => $product_id,
		'expected_state_token' => $current['state_token'],
		'name'                 => $current['name'],
	)
);
if ( ! is_wp_error( $no_change ) || 'cmsa_woocommerce_product_no_change' !== $no_change->get_error_code() ) {
	$fail( 'no-change product update was not rejected.' );
}

$before_fault = $service->get_item( $product_id );
if ( is_wp_error( $before_fault ) ) {
	$fail( 'pre-fault product read failed.' );
}
$before_fault = $before_fault['product'];
$force_update_failure = static function ( $force, $operation ) {
	return 'update' === $operation ? true : (bool) $force;
};
add_filter( 'cmsa_woocommerce_product_force_verify_failure', $force_update_failure, 10, 5 );
$fault_update = $service->update_simple(
	array(
		'id'                   => $product_id,
		'expected_state_token' => $before_fault['state_token'],
		'short_description'    => '<em>This injected verification failure must roll back.</em>',
	)
);
remove_filter( 'cmsa_woocommerce_product_force_verify_failure', $force_update_failure, 10 );
if ( ! is_wp_error( $fault_update ) || 'cmsa_woocommerce_product_update_verify' !== $fault_update->get_error_code() ) {
	$fail( 'injected update verification failure did not fail closed.' );
}
$fault_data = $fault_update->get_error_data();
if ( ! is_array( $fault_data ) || empty( $fault_data['rolled_back'] ) ) {
	$fail( 'injected update verification failure did not report successful rollback.' );
}
$after_fault = $service->get_item( $product_id );
if ( is_wp_error( $after_fault ) || ! hash_equals( (string) $before_fault['state_token'], (string) $after_fault['product']['state_token'] ) ) {
	$fail( 'update rollback did not restore the exact bounded product state token.' );
}
if ( 'preserve-extension-state' !== (string) get_post_meta( $product_id, '_cmsa_control_extension_meta', true ) ) {
	$fail( 'update rollback changed unrelated extension metadata.' );
}

$count_before_invalid = $product_ids();
$invalid_term = $service->create_simple_draft(
	array(
		'name'         => 'CMSA invalid term product',
		'category_ids' => array( 2147483647 ),
	)
);
if ( ! is_wp_error( $invalid_term ) || 'cmsa_woocommerce_product_term_not_found' !== $invalid_term->get_error_code() || $count_before_invalid !== $product_ids() ) {
	$fail( 'invalid product term input did not fail before persistence.' );
}
$invalid_image = $service->create_simple_draft(
	array(
		'name'     => 'CMSA invalid image product',
		'image_id' => $control_post_id,
	)
);
if ( ! is_wp_error( $invalid_image ) || 'cmsa_woocommerce_product_image' !== $invalid_image->get_error_code() || $count_before_invalid !== $product_ids() ) {
	$fail( 'invalid product image input did not fail before persistence.' );
}
$invalid_price = $service->create_simple_draft(
	array(
		'name'          => 'CMSA invalid price product',
		'regular_price' => '-1.00',
	)
);
if ( ! is_wp_error( $invalid_price ) || 'cmsa_woocommerce_product_price' !== $invalid_price->get_error_code() || $count_before_invalid !== $product_ids() ) {
	$fail( 'invalid product price input did not fail before persistence.' );
}

$duplicate_count = $product_ids();
$duplicate_sku = $service->create_simple_draft(
	array(
		'name' => 'CMSA duplicate SKU product',
		'sku'  => $sku,
	)
);
if ( ! is_wp_error( $duplicate_sku ) || $duplicate_count !== $product_ids() ) {
	$fail( 'duplicate SKU rejection left a persisted product or unexpectedly succeeded.' );
}

$force_create_failure = static function ( $force, $operation ) {
	return 'create' === $operation ? true : (bool) $force;
};
$before_forced_create = $product_ids();
add_filter( 'cmsa_woocommerce_product_force_verify_failure', $force_create_failure, 10, 5 );
$fault_create = $service->create_simple_draft(
	array(
		'name'          => 'CMSA forced-create rollback',
		'sku'           => 'cmsa-fault-' . strtolower( wp_generate_password( 10, false, false ) ),
		'regular_price' => '5.00',
	)
);
remove_filter( 'cmsa_woocommerce_product_force_verify_failure', $force_create_failure, 10 );
if ( ! is_wp_error( $fault_create ) || 'cmsa_woocommerce_product_create_verify' !== $fault_create->get_error_code() ) {
	$fail( 'injected create verification failure did not fail closed.' );
}
$fault_create_data = $fault_create->get_error_data();
if ( ! is_array( $fault_create_data ) || empty( $fault_create_data['rolled_back'] ) || $before_forced_create !== $product_ids() ) {
	$fail( 'injected create verification failure did not remove only the new product.' );
}

$control_product_after = $service->get_item( $control_product_id );
if ( is_wp_error( $control_product_after ) || ! hash_equals( $control_product_token, (string) $control_product_after['product']['state_token'] ) ) {
	$fail( 'unrelated product state changed during product transactions.' );
}
$control_post_after = get_post( $control_post_id );
$control_event_after = get_post( $control_event_id );
$image_one_after = get_post( $image_one );
$image_two_after = get_post( $image_two );
if ( ! $control_post_after || $control_post_after->post_title !== $control_post_before->post_title || $control_post_after->post_content !== $control_post_before->post_content || $control_post_after->post_status !== $control_post_before->post_status ) {
	$fail( 'ordinary WordPress post changed during product transactions.' );
}
if ( ! $control_event_after || $control_event_after->post_title !== $control_event_before->post_title || $control_event_after->post_content !== $control_event_before->post_content || $control_event_after->post_status !== $control_event_before->post_status ) {
	$fail( 'unrelated event fixture changed during product transactions.' );
}
foreach ( array( array( $image_one_before, $image_one_after ), array( $image_two_before, $image_two_after ) ) as $pair ) {
	if ( ! $pair[1] || $pair[1]->post_title !== $pair[0]->post_title || $pair[1]->post_mime_type !== $pair[0]->post_mime_type || $pair[1]->post_parent !== $pair[0]->post_parent ) {
		$fail( 'media attachment changed during product transactions.' );
	}
}
$customer_after = get_userdata( $customer_id );
$customer_after_snapshot = array(
	'display_name' => (string) $customer_after->display_name,
	'user_email'   => (string) $customer_after->user_email,
	'roles'        => array_values( (array) $customer_after->roles ),
);
if ( $customer_snapshot !== $customer_after_snapshot ) {
	$fail( 'customer fixture changed during product transactions.' );
}
$order_after = wc_get_order( $order_id );
if ( ! $order_after ) {
	$fail( 'unrelated order fixture disappeared during product transactions.' );
}
$order_after_snapshot = $order_snapshot( $order_after );
if ( $order_before !== $order_after_snapshot ) {
	$fail( 'order fixture changed during product transactions.' );
}

$all_expected = array();
foreach ( glob( dirname( __DIR__ ) . '/fixtures/expected*abilities.json' ) as $ability_fixture ) {
	$names = json_decode( (string) file_get_contents( $ability_fixture ), true );
	if ( is_array( $names ) ) {
		$all_expected = array_merge( $all_expected, $names );
	}
}
foreach ( array_unique( $all_expected ) as $ability_name ) {
	if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( $ability_name ) ) {
		$fail( 'existing candidate ability disappeared during WooCommerce transactions: ' . $ability_name );
	}
}

foreach ( array( $product_id, $control_product_id, $variable_id ) as $cleanup_id ) {
	$cleanup = wc_get_product( $cleanup_id );
	if ( $cleanup ) {
		$cleanup->delete( true );
	}
}
wp_delete_attachment( $image_one, true );
wp_delete_attachment( $image_two, true );
wp_delete_post( $control_post_id, true );
wp_delete_post( $control_event_id, true );
wp_delete_term( $category_id, 'product_cat' );
wp_delete_term( $tag_id, 'product_tag' );
$order_cleanup = wc_get_order( $order_id );
if ( $order_cleanup ) {
	$order_cleanup->delete( true );
}
if ( wc_get_order( $order_id ) ) {
	$fail( 'disposable order fixture cleanup failed.' );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $customer_id );

echo 'woocommerce-product-transaction-cli: PASS create=get=list=update exact-state=verified rollback=verified isolation=verified\n';
