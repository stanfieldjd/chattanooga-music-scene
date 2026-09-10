<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_v2_wc_fail( $message ) {
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_wc_catalog() {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}
	$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
	if ( ! $catalog instanceof WP_Ability ) {
		cmsa_v2_wc_fail( 'Universal catalog is unavailable in WooCommerce compatibility job.' );
	}
	$result = $catalog->execute( array() );
	if ( is_wp_error( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
		cmsa_v2_wc_fail( 'Universal catalog could not enumerate WooCommerce contracts.' );
	}
	$items = $result['items'];
	return $items;
}

function cmsa_v2_wc_ability( $target ) {
	$matches = array();
	foreach ( cmsa_v2_wc_catalog() as $item ) {
		if ( 'ability' === ( $item['contract'] ?? '' ) && $target === ( $item['target'] ?? '' ) && ! empty( $item['bridge'] ) ) {
			$matches[] = (string) $item['bridge'];
		}
	}
	if ( 1 !== count( $matches ) ) {
		cmsa_v2_wc_fail( sprintf( 'Expected one bridged WooCommerce ability for %1$s; found %2$d.', $target, count( $matches ) ) );
	}
	$ability = wp_get_ability( $matches[0] );
	if ( ! $ability instanceof WP_Ability ) {
		cmsa_v2_wc_fail( 'Discovered WooCommerce facade is unavailable.' );
	}
	return $ability;
}

function cmsa_v2_wc_execute( $target, array $input ) {
	$ability = cmsa_v2_wc_ability( $target );
	$permission = $ability->check_permissions( $input );
	if ( true !== $permission ) {
		cmsa_v2_wc_fail( 'WooCommerce provider permission was not preserved for ' . $target . '.' );
	}
	$result = $ability->execute( $input );
	if ( is_wp_error( $result ) ) {
		cmsa_v2_wc_fail( sprintf( '%1$s failed: %2$s %3$s', $target, $result->get_error_code(), $result->get_error_message() ) );
	}
	return $result;
}

function cmsa_v2_wc_extract_id( $value ) {
	if ( is_array( $value ) ) {
		foreach ( array( 'id', 'product_id' ) as $key ) {
			if ( isset( $value[ $key ] ) && is_numeric( $value[ $key ] ) && (int) $value[ $key ] > 0 ) {
				return (int) $value[ $key ];
			}
		}
		foreach ( $value as $child ) {
			$id = cmsa_v2_wc_extract_id( $child );
			if ( $id > 0 ) {
				return $id;
			}
		}
	}
	if ( is_object( $value ) ) {
		if ( method_exists( $value, 'get_id' ) ) {
			$id = (int) $value->get_id();
			if ( $id > 0 ) {
				return $id;
			}
		}
		return cmsa_v2_wc_extract_id( get_object_vars( $value ) );
	}
	return 0;
}

wp_set_current_user( 1 );

if ( ! defined( 'WC_VERSION' ) || '11.0.1' !== (string) WC_VERSION ) {
	cmsa_v2_wc_fail( 'WooCommerce 11.0.1 is not the active compatibility target.' );
}
if ( ! function_exists( 'wc_get_product' ) ) {
	cmsa_v2_wc_fail( 'WooCommerce product API is unavailable.' );
}

$query = cmsa_v2_wc_ability( 'woocommerce/products-query' );
$create = cmsa_v2_wc_ability( 'woocommerce/product-create' );
$update = cmsa_v2_wc_ability( 'woocommerce/product-update' );
$delete = cmsa_v2_wc_ability( 'woocommerce/product-delete' );

$query_input = array( 'status' => 'draft', 'page' => 1, 'per_page' => 5 );
if ( true !== $query->check_permissions( $query_input ) ) {
	cmsa_v2_wc_fail( 'WooCommerce query permission was not preserved.' );
}
$query_result = $query->execute( $query_input );
if ( is_wp_error( $query_result ) ) {
	cmsa_v2_wc_fail( 'WooCommerce product query failed through the universal facade.' );
}

$suffix = substr( hash( 'sha256', wp_generate_uuid4() ), 0, 10 );
$name = 'CMSA v2 WooCommerce ' . $suffix;
$updated_name = $name . ' Updated';

$created = cmsa_v2_wc_execute(
	'woocommerce/product-create',
	array(
		'name'               => $name,
		'product_type_alias' => 'physical',
		'status'             => 'draft',
		'regular_price'      => '12.34',
	)
);
$product_id = cmsa_v2_wc_extract_id( $created );
if ( $product_id < 1 ) {
	cmsa_v2_wc_fail( 'WooCommerce product-create returned no product ID.' );
}

$product = wc_get_product( $product_id );
if ( ! $product || $product->get_name() !== $name || $product->get_status() !== 'draft' || $product->get_regular_price() !== '12.34' ) {
	cmsa_v2_wc_fail( 'WooCommerce created product did not match the requested state.' );
}

cmsa_v2_wc_execute(
	'woocommerce/product-update',
	array(
		'id'                 => $product_id,
		'product_type_alias' => 'physical',
		'name'               => $updated_name,
		'regular_price'      => '23.45',
		'status'             => 'draft',
	)
);
clean_post_cache( $product_id );
$product = wc_get_product( $product_id );
if ( ! $product || $product->get_name() !== $updated_name || $product->get_regular_price() !== '23.45' ) {
	cmsa_v2_wc_fail( 'WooCommerce product-update did not persist through the universal facade.' );
}

$query_after = cmsa_v2_wc_execute( 'woocommerce/products-query', array( 'id' => $product_id, 'page' => 1, 'per_page' => 5 ) );
if ( 0 === cmsa_v2_wc_extract_id( $query_after ) ) {
	cmsa_v2_wc_fail( 'WooCommerce products-query did not return the created product.' );
}

wp_set_current_user( 0 );
if ( false !== $create->check_permissions( array( 'name' => 'denied', 'product_type_alias' => 'physical' ) ) ) {
	cmsa_v2_wc_fail( 'Anonymous WooCommerce administration was not blocked.' );
}
wp_set_current_user( 1 );

cmsa_v2_wc_execute( 'woocommerce/product-delete', array( 'id' => $product_id, 'force' => true ) );
clean_post_cache( $product_id );
if ( false !== wc_get_product( $product_id ) ) {
	cmsa_v2_wc_fail( 'WooCommerce product remained after permanent disposable deletion.' );
}

echo "cmsa-v2-woocommerce: PASS version=11.0.1 native_abilities=bridged query=verified product_create=verified product_update=verified product_delete=verified provider_permissions=preserved admin_boundary=verified candidate_provider_special_case=absent\n";
exit( 0 );
