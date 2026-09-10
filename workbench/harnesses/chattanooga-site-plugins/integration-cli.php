<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cms_site_plugins_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cms_site_plugins_assert( $condition, $message ) {
	if ( ! $condition ) {
		cms_site_plugins_fail( $message );
	}
}

function cms_site_plugins_private( $object, $method, array $args = array() ) {
	$reflection = new ReflectionMethod( get_class( $object ), $method );
	$reflection->setAccessible( true );
	return $reflection->invokeArgs( $object, $args );
}

wp_set_current_user( 1 );

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$required_plugins = array(
	'chattanooga-cms-admin/chattanooga-cms-admin.php',
	'chattanooga-music-marketplace/chattanooga-music-marketplace.php',
	'chattanooga-music-scene-core/chattanooga-music-scene-core.php',
	'woocommerce/woocommerce.php',
	'events-manager/events-manager.php',
	'another-wordpress-classifieds-plugin/awpcp.php',
);
foreach ( $required_plugins as $plugin_file ) {
	cms_site_plugins_assert( is_plugin_active( $plugin_file ), 'Required plugin is not active: ' . $plugin_file );
}

cms_site_plugins_assert( defined( 'CUA_VERSION' ) && '1.0.0' === CUA_VERSION, 'Chattanooga CMS Admin 1.0.0 did not load.' );
cms_site_plugins_assert( defined( 'CMS_MARKETPLACE_VERSION' ) && '0.1.1' === CMS_MARKETPLACE_VERSION, 'Marketplace 0.1.1 did not load.' );
cms_site_plugins_assert( defined( 'CMS_CORE_VERSION' ) && '0.2.1' === CMS_CORE_VERSION, 'Weekend Feature 0.2.1 did not load.' );
cms_site_plugins_assert( class_exists( 'WC_Product_Simple' ), 'WooCommerce product API is unavailable.' );
cms_site_plugins_assert( class_exists( 'EM_Events' ), 'Events Manager API is unavailable.' );

// Chattanooga CMS Admin must remain functional with the other two site plugins active.
cms_site_plugins_assert( function_exists( 'wp_get_ability' ), 'WordPress Abilities API is unavailable.' );
$health = wp_get_ability( 'chattanooga-cms-admin/get-health' );
cms_site_plugins_assert( $health instanceof WP_Ability, 'Chattanooga CMS Admin health ability is missing.' );
cms_site_plugins_assert( true === $health->check_permissions( array() ), 'Chattanooga CMS Admin health permission failed for administrator.' );
$health_result = $health->execute( array() );
cms_site_plugins_assert( ! is_wp_error( $health_result ), 'Chattanooga CMS Admin health execution failed.' );
cms_site_plugins_assert( get_bloginfo( 'version' ) === ( $health_result['wordpress_version'] ?? '' ), 'Chattanooga CMS Admin health returned the wrong WordPress version.' );
cms_site_plugins_assert( array_key_exists( 'plugin_dir_writable', $health_result ), 'Chattanooga CMS Admin health omitted plugin-directory state.' );
global $wpdb;
cms_site_plugins_assert( '1' === (string) $wpdb->get_var( 'SELECT 1' ), 'WordPress database verification failed.' );

// Weekend Feature must register its public WordPress contracts and remain connected to Events Manager.
cms_site_plugins_assert( post_type_exists( CMS_Weekend_Posts::POST_TYPE ), 'Weekend Feature post type is not registered.' );
$weekend_type = get_post_type_object( CMS_Weekend_Posts::POST_TYPE );
cms_site_plugins_assert( $weekend_type && ! empty( $weekend_type->show_in_rest ), 'Weekend Feature post type is not REST-visible.' );
cms_site_plugins_assert( shortcode_exists( 'cms_weekend_feature' ), 'Weekend Feature shortcode is not registered.' );

$schedules = apply_filters( 'cron_schedules', array() );
cms_site_plugins_assert( isset( $schedules['cms_weekly'] ), 'Weekend Feature weekly cron interval is missing.' );
cms_site_plugins_assert( WEEK_IN_SECONDS === (int) $schedules['cms_weekly']['interval'], 'Weekend Feature weekly cron interval is incorrect.' );

$publicize_types = apply_filters( 'publicize_post_types', array( 'post' ) );
cms_site_plugins_assert( in_array( CMS_Weekend_Posts::POST_TYPE, $publicize_types, true ), 'Weekend Feature is not exposed to Jetpack Social filter contract.' );

$weekend = CMS_Weekend_Posts::instance();
$weekend->register_settings();
$registered_settings = get_registered_settings();
cms_site_plugins_assert( isset( $registered_settings[ CMS_Weekend_Posts::OPTION_SETTINGS ] ), 'Weekend Feature Settings API registration is missing.' );
cms_site_plugins_assert( 'array' === ( $registered_settings[ CMS_Weekend_Posts::OPTION_SETTINGS ]['type'] ?? '' ), 'Weekend Feature setting type is not array.' );

$reference = new DateTimeImmutable( '2026-09-10 12:00:00', wp_timezone() );
$window    = cms_site_plugins_private( $weekend, 'weekend_window', array( $reference ) );
cms_site_plugins_assert( '2026-09-11' === $window['start']->format( 'Y-m-d' ), 'Weekend Feature window does not begin Friday.' );
cms_site_plugins_assert( '2026-09-13' === $window['end']->format( 'Y-m-d' ), 'Weekend Feature window does not end Sunday.' );
$events = cms_site_plugins_private( $weekend, 'get_events', array( $window ) );
cms_site_plugins_assert( ! is_wp_error( $events ) && is_array( $events ), 'Weekend Feature could not query Events Manager.' );

$next_run = cms_site_plugins_private( $weekend, 'next_thursday_timestamp', array( '08:00' ) );
cms_site_plugins_assert( $next_run > time(), 'Weekend Feature next Thursday schedule is not in the future.' );
cms_site_plugins_assert( '4 08:00' === wp_date( 'N H:i', $next_run, wp_timezone() ), 'Weekend Feature schedule is not Thursday at 08:00 site time.' );

// Build a real Marketplace request and a real WooCommerce product.
$existing_12 = get_post( CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID );
if ( $existing_12 ) {
	wp_delete_post( CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID, true );
}
$page_id = wp_insert_post(
	array(
		'import_id'    => CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Marketplace Integration Test',
		'post_content' => '[AWPCPCLASSIFIEDSUI]',
	),
	true
);
cms_site_plugins_assert( ! is_wp_error( $page_id ) && CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID === (int) $page_id, 'Could not create the Marketplace page at its configured ID.' );

$product = new WC_Product_Simple();
$product->set_name( 'Integration Test Guitar Strings' );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'visible' );
$product->set_regular_price( '12.99' );
$product->set_short_description( 'Fresh strings for the integration test.' );
$product->set_stock_status( 'instock' );
$product_id = $product->save();
cms_site_plugins_assert( $product_id > 0, 'Could not create the Marketplace WooCommerce product.' );

$prior_wp_query      = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query'] : null;
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID, 'post_type' => 'page' ) );
cms_site_plugins_assert( is_page( CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID ), 'Disposable request is not recognized as the Marketplace page.' );

$marketplace = CMS_Unified_Marketplace::instance();
cms_site_plugins_assert(
	false !== has_filter( 'awpcp-content-before-listings-pagination', array( $marketplace, 'prepare_interleaving' ) ),
	'Marketplace is not connected to the AWP listing stream.'
);
cms_site_plugins_assert(
	false !== has_filter( 'awpcp-render-listing-item', array( $marketplace, 'interleave_product' ) ),
	'Marketplace item interleaving hook is missing.'
);

$marketplace->prepare_interleaving(
	array(),
	'main-page',
	array( (object) array( 'id' => 1 ) ),
	array( 'offset' => 0, 'paged' => 1 )
);
$rendered = $marketplace->interleave_product( '<article>Community listing</article>', (object) array(), 1 );
cms_site_plugins_assert( false !== strpos( $rendered, 'Integration Test Guitar Strings' ), 'Store product was not interleaved into the Marketplace stream.' );
cms_site_plugins_assert( false !== strpos( wp_strip_all_tags( $rendered ), 'Price:' ), 'Marketplace product price is missing.' );
cms_site_plugins_assert( false === stripos( wp_strip_all_tags( $rendered ), 'WooCommerce' ), 'Marketplace rendered a customer-facing WooCommerce label.' );

$location_filtered = cms_site_plugins_private(
	$marketplace,
	'get_products_for_query',
	array(
		'search',
		array(
			'classifieds_query' => array(
				'regions' => array( 'city' => 'Chattanooga' ),
			),
		),
	)
);
cms_site_plugins_assert( array() === $location_filtered, 'Marketplace incorrectly inserted a store product into a location-filtered result.' );

wp_delete_post( $product_id, true );
wp_delete_post( CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID, true );
if ( null !== $prior_wp_query ) {
	$GLOBALS['wp_query'] = $prior_wp_query;
}

wp_set_current_user( 0 );
cms_site_plugins_assert( false === $health->check_permissions( array() ), 'Anonymous CMS Admin access was not denied.' );
wp_set_current_user( 1 );

echo "cms-site-plugins-integration: PASS cms_admin=1.0.0 marketplace=0.1.1 weekend_feature=0.2.1 wordpress_native_install=verified coexistence=verified marketplace_awp=verified marketplace_woocommerce=verified woocommerce_label=absent location_filter=preserved weekend_events_manager=verified weekend_schedule=verified cms_admin_health=verified database=verified admin_boundary=verified\n";
exit( 0 );
