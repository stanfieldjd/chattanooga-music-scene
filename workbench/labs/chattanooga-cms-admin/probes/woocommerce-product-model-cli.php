<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "woocommerce-product-model-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "woocommerce-product-model-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

$required_classes = array(
	'WooCommerce',
	'WC_Product',
	'WC_Product_Simple',
	'WC_Product_Query',
	'WC_Data_Store',
);
$required_functions = array(
	'wc_get_product',
	'wc_get_products',
	'wc_get_product_types',
);
$required_methods = array(
	'get_id',
	'get_name',
	'set_name',
	'get_status',
	'set_status',
	'get_description',
	'set_description',
	'get_short_description',
	'set_short_description',
	'get_sku',
	'set_sku',
	'get_regular_price',
	'set_regular_price',
	'get_sale_price',
	'set_sale_price',
	'get_catalog_visibility',
	'set_catalog_visibility',
	'get_manage_stock',
	'set_manage_stock',
	'get_stock_quantity',
	'set_stock_quantity',
	'get_stock_status',
	'set_stock_status',
	'get_category_ids',
	'set_category_ids',
	'get_tag_ids',
	'set_tag_ids',
	'get_image_id',
	'set_image_id',
	'get_gallery_image_ids',
	'set_gallery_image_ids',
	'get_data',
	'save',
);

$classes = array();
foreach ( $required_classes as $class ) {
	$classes[ $class ] = class_exists( $class );
}
$functions = array();
foreach ( $required_functions as $function ) {
	$functions[ $function ] = function_exists( $function );
}
$methods = array();
foreach ( $required_methods as $method ) {
	$methods[ $method ] = method_exists( 'WC_Product', $method );
}

$product_type = get_post_type_object( 'product' );
$taxonomy_names = array( 'product_cat', 'product_tag', 'product_type', 'product_visibility' );
$taxonomies = array();
foreach ( $taxonomy_names as $taxonomy ) {
	$object = get_taxonomy( $taxonomy );
	$taxonomies[ $taxonomy ] = $object ? array(
		'registered'   => true,
		'object_type'  => array_values( (array) $object->object_type ),
		'capabilities' => array(
			'manage_terms' => isset( $object->cap->manage_terms ) ? (string) $object->cap->manage_terms : '',
			'edit_terms'   => isset( $object->cap->edit_terms ) ? (string) $object->cap->edit_terms : '',
			'delete_terms' => isset( $object->cap->delete_terms ) ? (string) $object->cap->delete_terms : '',
			'assign_terms' => isset( $object->cap->assign_terms ) ? (string) $object->cap->assign_terms : '',
		),
	) : array( 'registered' => false );
}

$product_caps = array();
if ( $product_type ) {
	foreach ( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ) as $key ) {
		$product_caps[ $key ] = isset( $product_type->cap->{$key} ) ? (string) $product_type->cap->{$key} : '';
	}
}

$meta_cap_keys = array( 'edit_post', 'read_post', 'delete_post' );
$primitive_caps = array( 'manage_woocommerce' );
foreach ( $product_caps as $key => $cap ) {
	if ( ! in_array( $key, $meta_cap_keys, true ) && '' !== $cap ) {
		$primitive_caps[] = $cap;
	}
}
$admin_caps = array();
foreach ( array_unique( $primitive_caps ) as $cap ) {
	$admin_caps[ $cap ] = current_user_can( $cap );
}

$prototype = class_exists( 'WC_Product_Simple' ) ? new WC_Product_Simple() : null;
$data_keys = $prototype ? array_keys( $prototype->get_data() ) : array();
$product_store_wrapper = '';
$product_store_class = '';
try {
	$store = class_exists( 'WC_Data_Store' ) ? WC_Data_Store::load( 'product' ) : null;
	$product_store_wrapper = is_object( $store ) ? get_class( $store ) : '';
	if ( is_object( $store ) && method_exists( $store, 'get_current_class_name' ) ) {
		$product_store_class = (string) $store->get_current_class_name();
	}
} catch ( Throwable $error ) {
	$product_store_wrapper = '';
	$product_store_class = '';
}

$payload = array(
	'woocommerce' => array(
		'version' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
		'plugin'  => defined( 'WC_PLUGIN_FILE' ) ? plugin_basename( WC_PLUGIN_FILE ) : '',
	),
	'classes' => $classes,
	'functions' => $functions,
	'product_post_type' => array(
		'registered'      => (bool) $product_type,
		'capability_type' => $product_type ? $product_type->capability_type : null,
		'map_meta_cap'    => $product_type ? (bool) $product_type->map_meta_cap : null,
		'capabilities'    => $product_caps,
	),
	'admin_primitive_caps' => $admin_caps,
	'taxonomies' => $taxonomies,
	'wc_product_methods' => $methods,
	'product_data_keys' => $data_keys,
	'product_data_store_wrapper' => $product_store_wrapper,
	'product_data_store_class' => $product_store_class,
	'product_types' => function_exists( 'wc_get_product_types' ) ? array_keys( wc_get_product_types() ) : array(),
);

echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

$failures = array();
if ( '11.0.1' !== (string) $payload['woocommerce']['version'] ) {
	$failures[] = 'unexpected_woocommerce_version';
}
foreach ( $classes as $class => $present ) {
	if ( ! $present ) {
		$failures[] = 'missing_class:' . $class;
	}
}
foreach ( $functions as $function => $present ) {
	if ( ! $present ) {
		$failures[] = 'missing_function:' . $function;
	}
}
if ( ! $product_type || ! $product_type->map_meta_cap ) {
	$failures[] = 'product_post_type_contract';
}
foreach ( $taxonomies as $taxonomy => $contract ) {
	if ( empty( $contract['registered'] ) || ! in_array( 'product', isset( $contract['object_type'] ) ? $contract['object_type'] : array(), true ) ) {
		$failures[] = 'taxonomy_contract:' . $taxonomy;
	}
}
foreach ( $methods as $method => $present ) {
	if ( ! $present ) {
		$failures[] = 'missing_method:' . $method;
	}
}
if ( '' === $product_store_wrapper ) {
	$failures[] = 'product_data_store_missing';
}
foreach ( array( 'name', 'status', 'description', 'short_description', 'sku', 'regular_price', 'sale_price', 'catalog_visibility', 'manage_stock', 'stock_quantity', 'stock_status', 'category_ids', 'tag_ids', 'image_id', 'gallery_image_ids' ) as $key ) {
	if ( ! in_array( $key, $data_keys, true ) ) {
		$failures[] = 'product_data_key:' . $key;
	}
}
foreach ( $admin_caps as $cap => $allowed ) {
	if ( ! $allowed ) {
		$failures[] = 'administrator_missing_primitive_cap:' . $cap;
	}
}

if ( $failures ) {
	fwrite( STDERR, 'woocommerce-product-model-cli: FAIL ' . implode( ',', $failures ) . "\n" );
	exit( 1 );
}

echo 'woocommerce-product-model-cli: PASS version=' . WC_VERSION . ' store=' . ( $product_store_class ? $product_store_class : $product_store_wrapper ) . ' product-model=present primitive-capabilities=verified meta-capabilities=object-scoped taxonomies=recorded' . "\n";
