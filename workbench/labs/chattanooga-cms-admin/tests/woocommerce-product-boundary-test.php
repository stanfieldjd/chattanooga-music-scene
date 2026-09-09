<?php

$lab = dirname( __DIR__ );
$bootstrap = file_get_contents( $lab . '/candidate/chattanooga-cms-admin.php' );
$coordinator = file_get_contents( $lab . '/candidate/includes/class-cmsa-plugin.php' );
$service = file_get_contents( $lab . '/candidate/includes/class-cmsa-woocommerce-products.php' );
$abilities = file_get_contents( $lab . '/candidate/includes/class-cmsa-woocommerce-product-abilities.php' );

foreach ( array( 'class-cmsa-woocommerce-products.php', 'class-cmsa-woocommerce-product-abilities.php' ) as $required_include ) {
	if ( false === strpos( $bootstrap, $required_include ) ) {
		fwrite( STDERR, "WooCommerce product bootstrap include missing: {$required_include}.\n" );
		exit( 1 );
	}
}
foreach ( array( 'new CMSA_WooCommerce_Products()', 'new CMSA_WooCommerce_Product_Abilities', 'woocommerce_product_abilities->register()' ) as $wiring ) {
	if ( false === strpos( $coordinator, $wiring ) ) {
		fwrite( STDERR, "WooCommerce product coordinator wiring missing: {$wiring}.\n" );
		exit( 1 );
	}
}
foreach ( array( "'list-products'", "'get-product'", "'create-simple-product-draft'", "'update-simple-product'" ) as $slug ) {
	if ( false === strpos( $abilities, $slug ) ) {
		fwrite( STDERR, "WooCommerce product ability registration missing: {$slug}.\n" );
		exit( 1 );
	}
}
foreach ( array( "current_user_can( 'manage_woocommerce' )", "current_user_can( 'edit_products' )", "'show_in_rest' => false", "'mcp'          => array( 'public' => true )" ) as $ability_guard ) {
	if ( false === strpos( $abilities, $ability_guard ) ) {
		fwrite( STDERR, "WooCommerce product ability guard missing: {$ability_guard}.\n" );
		exit( 1 );
	}
}
foreach ( array( "SUPPORTED_WC_VERSION = '11.0.1'", "WC_Data_Store::load( 'product' )", "'WC_Product_Data_Store_CPT'", 'wc_get_product(', 'new WC_Product_Simple()', '->save()', '->delete( true )', "current_user_can( 'edit_post'", "current_user_can( 'assign_product_terms' )", "current_user_can( 'upload_files' )", 'expected_state_token', 'cmsa_woocommerce_product_force_verify_failure' ) as $service_guard ) {
	if ( false === strpos( $service, $service_guard ) ) {
		fwrite( STDERR, "WooCommerce product service guard missing: {$service_guard}.\n" );
		exit( 1 );
	}
}
foreach ( array( 'update_post_meta(', 'add_post_meta(', 'delete_post_meta(', 'wp_insert_post(', 'wp_update_post(', '$wpdb', 'get_meta_data(', 'update_meta_data(', 'wc_create_order(', 'wc_get_order(', 'WC_Order', 'WC_Customer' ) as $forbidden ) {
	if ( false !== strpos( $service, $forbidden ) ) {
		fwrite( STDERR, "WooCommerce product service escaped its typed catalog boundary: {$forbidden}.\n" );
		exit( 1 );
	}
}
foreach ( array( "'publish-product'", "'delete-product'", "'list-orders'", "'get-order'", "'list-customers'", "'get-customer'" ) as $forbidden_slug ) {
	if ( false !== strpos( $abilities, $forbidden_slug ) ) {
		fwrite( STDERR, "WooCommerce product ability surface expanded beyond the authorized slice: {$forbidden_slug}.\n" );
		exit( 1 );
	}
}

echo "woocommerce-product-boundary-test: PASS\n";
