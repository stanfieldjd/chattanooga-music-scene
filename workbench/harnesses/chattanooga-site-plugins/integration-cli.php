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

function cms_site_plugins_set_request( array $values ) {
	$_GET     = $values;
	$_POST    = array();
	$_REQUEST = $values;
}

function cms_site_plugins_set_page_query( $page_id ) {
	$query = new WP_Query(
		array(
			'page_id'   => (int) $page_id,
			'post_type' => 'page',
		)
	);

	$GLOBALS['wp_query']     = $query;
	$GLOBALS['wp_the_query'] = $query;
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

// Build a real Marketplace request with zero community listings and one real store product.
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
		'post_content' => '[cms_marketplace]',
	),
	true
);
cms_site_plugins_assert( ! is_wp_error( $page_id ) && CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID === (int) $page_id, 'Could not create the Marketplace page at its configured ID.' );
cms_site_plugins_assert( shortcode_exists( 'cms_marketplace' ), 'Marketplace unified shortcode is not registered.' );
cms_site_plugins_assert( awpcp_update_plugin_page_id( 'main-page-name', $page_id ), 'Could not assign the disposable Marketplace page as the AWP main page.' );
cms_site_plugins_assert( awpcp()->settings->update_option( 'main_page_display', 1, true ), 'Could not enable AWP main-page listing display for the Marketplace test.' );

$existing_listings = get_posts(
	array(
		'post_type'      => AWPCP_LISTING_POST_TYPE,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $existing_listings as $listing_id ) {
	wp_delete_post( $listing_id, true );
}

$product_category_result = wp_insert_term(
	'Integration Test Instruments',
	'product_cat',
	array( 'slug' => 'integration-test-instruments' )
);
cms_site_plugins_assert( ! is_wp_error( $product_category_result ), 'Could not create the disposable WooCommerce product category.' );
$product_category_id = absint( $product_category_result['term_id'] );
cms_site_plugins_assert( $product_category_id > 0, 'Disposable WooCommerce product category has no valid term ID.' );

$product = new WC_Product_Simple();
$product->set_name( 'Integration Test Guitar Strings' );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'visible' );
$product->set_regular_price( '12.99' );
$product->set_short_description( 'Fresh strings for the integration test and stage use with reliable tone, comfortable feel, balanced tension, durable winding, smooth playability, clear response, and dependable performance for rehearsals, recording sessions, and live shows.' );
$product->set_stock_status( 'instock' );
$product->set_category_ids( array( $product_category_id ) );
$product_id = $product->save();
cms_site_plugins_assert( $product_id > 0, 'Could not create the Marketplace WooCommerce product.' );

$prior_wp_query     = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query'] : null;
$prior_wp_the_query = isset( $GLOBALS['wp_the_query'] ) ? $GLOBALS['wp_the_query'] : null;
$prior_get          = $_GET;
$prior_post         = $_POST;
$prior_request      = $_REQUEST;

cms_site_plugins_set_request( array() );
cms_site_plugins_set_page_query( CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID );
cms_site_plugins_assert( is_page( CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID ), 'Disposable request is not recognized as the Marketplace page.' );

$rendered = do_shortcode( '[cms_marketplace]' );
cms_site_plugins_assert( false !== strpos( $rendered, 'Integration Test Guitar Strings' ), 'Store product was not rendered inside the unified Marketplace item stream.' );
cms_site_plugins_assert( false !== strpos( $rendered, 'cms-marketplace-product' ), 'Unified Marketplace product item marker is missing.' );
cms_site_plugins_assert( false !== strpos( $rendered, 'awpcp-listings' ), 'Marketplace no longer renders inside the AWP listings container.' );
cms_site_plugins_assert( false !== strpos( wp_strip_all_tags( $rendered ), 'Price:' ), 'Marketplace product price is missing.' );
cms_site_plugins_assert( false === strpos( $rendered, 'There were no listings found.' ), 'Marketplace rendered AWP empty-state text even though a store product exists.' );
cms_site_plugins_assert( false === stripos( wp_strip_all_tags( $rendered ), 'WooCommerce' ), 'Marketplace rendered a customer-facing WooCommerce label.' );
cms_site_plugins_assert( false === stripos( wp_strip_all_tags( $rendered ), 'CJ Dropshipping' ), 'Marketplace rendered a customer-facing supplier label.' );
cms_site_plugins_assert( false === strpos( $rendered, '&amp;hellip;' ), 'Marketplace double-escaped the description truncation suffix.' );
cms_site_plugins_assert( false !== strpos( $rendered, '…' ), 'Marketplace did not render a visible ellipsis for a truncated description.' );

$marketplace = CMS_Unified_Marketplace::instance();
$multi_term_search = cms_site_plugins_private(
	$marketplace,
	'get_products_for_query',
	array(
		'search',
		array(
			's'                 => 'Integration Strings',
			'classifieds_query' => array(),
		),
	)
);
cms_site_plugins_assert(
	1 === count( $multi_term_search ) && $product_id === (int) $multi_term_search[0]->get_id(),
	'Marketplace product search does not preserve WordPress multi-term search semantics.'
);

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

// Exercise AWP's real Browse route. The Marketplace filter must replace the zero-listing output with the same product stream.
$browse_page_id = absint( awpcp_get_page_id_by_ref( 'browse-ads-page-name' ) );
cms_site_plugins_assert( $browse_page_id > 0, 'AWP Browse Listings page is unavailable.' );
cms_site_plugins_set_request( array() );
cms_site_plugins_set_page_query( $browse_page_id );
$browse_rendered = awpcp_browse_listings_page()->dispatch();
cms_site_plugins_assert( false !== strpos( $browse_rendered, 'Integration Test Guitar Strings' ), 'AWP Browse route did not render the store product in the unified Marketplace stream.' );
cms_site_plugins_assert( false === strpos( $browse_rendered, 'There were no listings found.' ), 'AWP Browse route rendered a false empty-state message while a store product exists.' );
cms_site_plugins_assert( false === stripos( wp_strip_all_tags( $browse_rendered ), 'WooCommerce' ), 'AWP Browse route exposed the commerce engine label.' );
cms_site_plugins_assert( false === stripos( wp_strip_all_tags( $browse_rendered ), 'CJ Dropshipping' ), 'AWP Browse route exposed the supplier label.' );

// Exercise AWP's real Search route with a matching product-only result.
$search_page_id = absint( awpcp_get_page_id_by_ref( 'search-ads-page-name' ) );
cms_site_plugins_assert( $search_page_id > 0, 'AWP Search Listings page is unavailable.' );
cms_site_plugins_set_request(
	array(
		'awpcp-step'      => 'dosearch',
		'keywordphrase'   => 'Integration Strings',
		'searchname'      => '',
		'searchpricemin'  => '',
		'searchpricemax'  => '',
		'searchcategory'  => array(),
		'regions'         => array(),
	)
);
cms_site_plugins_set_page_query( $search_page_id );
$search_rendered = awpcp_search_listings_page()->dispatch();
cms_site_plugins_assert( false !== strpos( $search_rendered, 'Integration Test Guitar Strings' ), 'AWP Search route did not render the matching store product.' );
cms_site_plugins_assert( false === strpos( $search_rendered, 'There were no listings found.' ), 'AWP Search route rendered a false empty-state message while a matching store product exists.' );
cms_site_plugins_assert( false === stripos( wp_strip_all_tags( $search_rendered ), 'WooCommerce' ), 'AWP Search route exposed the commerce engine label.' );
cms_site_plugins_assert( false === stripos( wp_strip_all_tags( $search_rendered ), 'CJ Dropshipping' ), 'AWP Search route exposed the supplier label.' );

// A location-filtered AWP search must not inject store products that have no Marketplace location data.
cms_site_plugins_set_request(
	array(
		'awpcp-step'      => 'dosearch',
		'keywordphrase'   => '',
		'searchname'      => '',
		'searchpricemin'  => '',
		'searchpricemax'  => '',
		'searchcategory'  => array(),
		'regions'         => array( 'city' => 'Chattanooga' ),
	)
);
cms_site_plugins_set_page_query( $search_page_id );
$location_search_rendered = awpcp_search_listings_page()->dispatch();
cms_site_plugins_assert( false === strpos( $location_search_rendered, 'Integration Test Guitar Strings' ), 'AWP Search route inserted a store product into a location-filtered result.' );

// Respect another integration that has already claimed an AWP replacement hook.
$earlier_replacement = static function ( $output ) {
	return null === $output ? '<div class="integration-owner">Earlier integration owns this output.</div>' : $output;
};
add_filter( 'awpcp-browse-listings-content-replacement', $earlier_replacement, 10, 2 );
cms_site_plugins_set_request( array() );
cms_site_plugins_set_page_query( $browse_page_id );
$claimed_browse = awpcp_browse_listings_page()->dispatch();
cms_site_plugins_assert( false !== strpos( $claimed_browse, 'Earlier integration owns this output.' ), 'Marketplace overrode a Browse replacement already supplied by another integration.' );
remove_filter( 'awpcp-browse-listings-content-replacement', $earlier_replacement, 10 );

add_filter( 'awpcp-search-listings-content-replacement', $earlier_replacement, 10, 2 );
cms_site_plugins_set_request(
	array(
		'awpcp-step'      => 'dosearch',
		'keywordphrase'   => 'Integration Strings',
		'searchname'      => '',
		'searchpricemin'  => '',
		'searchpricemax'  => '',
		'searchcategory'  => array(),
		'regions'         => array(),
	)
);
cms_site_plugins_set_page_query( $search_page_id );
$claimed_search = awpcp_search_listings_page()->dispatch();
cms_site_plugins_assert( false !== strpos( $claimed_search, 'Earlier integration owns this output.' ), 'Marketplace overrode a Search replacement already supplied by another integration.' );
remove_filter( 'awpcp-search-listings-content-replacement', $earlier_replacement, 10 );

// Create a valid community listing through AWP's production API and verify the real mixed stream.
$category_result = wp_insert_term(
	'Integration Test Instruments',
	AWPCP_CATEGORY_TAXONOMY,
	array( 'slug' => 'integration-test-instruments' )
);
cms_site_plugins_assert( ! is_wp_error( $category_result ), 'Could not create the disposable AWP listing category.' );
$category_id = absint( $category_result['term_id'] );
cms_site_plugins_assert( $category_id > 0, 'Disposable AWP listing category has no valid term ID.' );

$start_date = ( new DateTimeImmutable( '-1 day', wp_timezone() ) )->format( 'Y-m-d H:i:s' );
$end_date   = ( new DateTimeImmutable( '+30 days', wp_timezone() ) )->format( 'Y-m-d H:i:s' );

try {
	$community_listing = awpcp_listings_api()->create_listing(
		array(
			'post_fields' => array(
				'post_author'  => 1,
				'post_title'   => 'Integration Strings Community Listing',
				'post_content' => 'Integration Strings community listing created through the AWP production listing API for mixed Marketplace verification.',
			),
			'terms'       => array(
				AWPCP_CATEGORY_TAXONOMY => array( $category_id ),
			),
			'metadata'    => array(
				'_awpcp_contact_name'   => 'Integration Seller',
				'_awpcp_contact_email'  => 'integration@example.test',
				'_awpcp_price'          => 1500,
				'_awpcp_payment_status' => AWPCP_Payment_Transaction::PAYMENT_STATUS_COMPLETED,
				'_awpcp_is_paid'        => true,
				'_awpcp_verified'       => true,
				'_awpcp_start_date'     => $start_date,
				'_awpcp_end_date'       => $end_date,
			),
		)
	);
} catch ( AWPCP_Exception $exception ) {
	cms_site_plugins_fail( 'Could not create a valid AWP community listing: ' . $exception->getMessage() );
}

cms_site_plugins_assert( $community_listing instanceof WP_Post, 'AWP production API did not return a listing post.' );
cms_site_plugins_assert( awpcp_listings_api()->enable_listing_without_triggering_actions( $community_listing ), 'AWP production API could not enable the disposable community listing.' );

$enabled_listings = awpcp_listings_collection()->find_enabled_listings(
	array(
		'posts_per_page'   => -1,
		'classifieds_query' => array( 'context' => 'public-listings' ),
	)
);
$enabled_listing_ids = wp_list_pluck( $enabled_listings, 'ID' );
cms_site_plugins_assert( in_array( $community_listing->ID, $enabled_listing_ids, true ), 'Disposable community listing does not satisfy AWP enabled-listing rules.' );

cms_site_plugins_set_request( array() );
cms_site_plugins_set_page_query( CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID );
$mixed_rendered = do_shortcode( '[cms_marketplace]' );
$community_position = strpos( $mixed_rendered, 'Integration Strings Community Listing' );
$product_position   = strpos( $mixed_rendered, 'Integration Test Guitar Strings' );
cms_site_plugins_assert( false !== $community_position, 'Unified Marketplace did not render the real AWP community listing.' );
cms_site_plugins_assert( false !== $product_position, 'Unified Marketplace lost the store product after a real community listing was added.' );
cms_site_plugins_assert( $community_position < $product_position, 'Unified Marketplace did not interleave the store product after the real community listing.' );
cms_site_plugins_assert( false === strpos( $mixed_rendered, 'There were no listings found.' ), 'Unified mixed Marketplace rendered a false empty-state message.' );

cms_site_plugins_set_request( array() );
cms_site_plugins_set_page_query( $browse_page_id );
$mixed_browse_rendered = awpcp_browse_listings_page()->dispatch();
cms_site_plugins_assert( false !== strpos( $mixed_browse_rendered, 'Integration Strings Community Listing' ), 'AWP Browse route did not render the real community listing.' );
cms_site_plugins_assert( false !== strpos( $mixed_browse_rendered, 'Integration Test Guitar Strings' ), 'AWP Browse route did not keep the store product in the mixed stream.' );

cms_site_plugins_set_request(
	array(
		'awpcp-step'      => 'dosearch',
		'keywordphrase'   => 'Integration Strings',
		'searchname'      => '',
		'searchpricemin'  => '',
		'searchpricemax'  => '',
		'searchcategory'  => array(),
		'regions'         => array(),
	)
);
cms_site_plugins_set_page_query( $search_page_id );
$mixed_search_rendered = awpcp_search_listings_page()->dispatch();
cms_site_plugins_assert( false !== strpos( $mixed_search_rendered, 'Integration Strings Community Listing' ), 'AWP Search route did not return the matching real community listing.' );
cms_site_plugins_assert( false !== strpos( $mixed_search_rendered, 'Integration Test Guitar Strings' ), 'AWP Search route did not keep the matching store product in the mixed result.' );

// Verify exact category mapping against real AWP and WooCommerce taxonomies.
cms_site_plugins_set_request( array( 'awpcp_category_id' => $category_id ) );
cms_site_plugins_set_page_query( $browse_page_id );
$category_browse_rendered = awpcp_browse_listings_page()->dispatch();
cms_site_plugins_assert( false !== strpos( $category_browse_rendered, 'Integration Strings Community Listing' ), 'AWP Browse category route lost the matching community listing.' );
cms_site_plugins_assert( false !== strpos( $category_browse_rendered, 'Integration Test Guitar Strings' ), 'AWP Browse category route did not include the exactly mapped store product.' );

cms_site_plugins_set_request(
	array(
		'awpcp-step'      => 'dosearch',
		'keywordphrase'   => '',
		'searchname'      => '',
		'searchpricemin'  => '',
		'searchpricemax'  => '',
		'searchcategory'  => array( $category_id ),
		'regions'         => array(),
	)
);
cms_site_plugins_set_page_query( $search_page_id );
$category_search_rendered = awpcp_search_listings_page()->dispatch();
cms_site_plugins_assert( false !== strpos( $category_search_rendered, 'Integration Strings Community Listing' ), 'AWP Search category route lost the matching community listing.' );
cms_site_plugins_assert( false !== strpos( $category_search_rendered, 'Integration Test Guitar Strings' ), 'AWP Search category route did not include the exactly mapped store product.' );

$unmatched_category_result = wp_insert_term(
	'Integration Test Amplifiers',
	AWPCP_CATEGORY_TAXONOMY,
	array( 'slug' => 'integration-test-amplifiers' )
);
cms_site_plugins_assert( ! is_wp_error( $unmatched_category_result ), 'Could not create the disposable unmatched AWP listing category.' );
$unmatched_category_id = absint( $unmatched_category_result['term_id'] );
cms_site_plugins_assert( $unmatched_category_id > 0, 'Disposable unmatched AWP listing category has no valid term ID.' );

$reassigned_terms = wp_set_object_terms( $community_listing->ID, array( $unmatched_category_id ), AWPCP_CATEGORY_TAXONOMY, false );
cms_site_plugins_assert( ! is_wp_error( $reassigned_terms ), 'Could not reassign the disposable community listing to the unmatched category.' );

cms_site_plugins_set_request( array( 'awpcp_category_id' => $unmatched_category_id ) );
cms_site_plugins_set_page_query( $browse_page_id );
$unmatched_browse_rendered = awpcp_browse_listings_page()->dispatch();
cms_site_plugins_assert( false !== strpos( $unmatched_browse_rendered, 'Integration Strings Community Listing' ), 'AWP Browse route did not return the community listing in the unmatched category.' );
cms_site_plugins_assert( false === strpos( $unmatched_browse_rendered, 'Integration Test Guitar Strings' ), 'AWP Browse route inserted a store product into an unmapped category.' );

cms_site_plugins_set_request(
	array(
		'awpcp-step'      => 'dosearch',
		'keywordphrase'   => '',
		'searchname'      => '',
		'searchpricemin'  => '',
		'searchpricemax'  => '',
		'searchcategory'  => array( $unmatched_category_id ),
		'regions'         => array(),
	)
);
cms_site_plugins_set_page_query( $search_page_id );
$unmatched_search_rendered = awpcp_search_listings_page()->dispatch();
cms_site_plugins_assert( false !== strpos( $unmatched_search_rendered, 'Integration Strings Community Listing' ), 'AWP Search route did not return the community listing in the unmatched category.' );
cms_site_plugins_assert( false === strpos( $unmatched_search_rendered, 'Integration Test Guitar Strings' ), 'AWP Search route inserted a store product into an unmapped category.' );

wp_delete_post( $community_listing->ID, true );
wp_delete_term( $unmatched_category_id, AWPCP_CATEGORY_TAXONOMY );
wp_delete_term( $category_id, AWPCP_CATEGORY_TAXONOMY );
wp_delete_post( $product_id, true );
wp_delete_term( $product_category_id, 'product_cat' );
wp_delete_post( CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID, true );
$_GET     = $prior_get;
$_POST    = $prior_post;
$_REQUEST = $prior_request;
if ( null !== $prior_wp_query ) {
	$GLOBALS['wp_query'] = $prior_wp_query;
}
if ( null !== $prior_wp_the_query ) {
	$GLOBALS['wp_the_query'] = $prior_wp_the_query;
}

wp_set_current_user( 0 );
cms_site_plugins_assert( false === $health->check_permissions( array() ), 'Anonymous CMS Admin access was not denied.' );
wp_set_current_user( 1 );

echo "cms-site-plugins-integration: PASS cms_admin=1.0.0 marketplace=0.1.1 weekend_feature=0.2.1 wordpress_native_install=verified coexistence=verified marketplace_awp=verified marketplace_woocommerce=verified marketplace_product_only=verified marketplace_empty_state=absent marketplace_browse_route=verified marketplace_search_route=verified marketplace_route_ownership=preserved marketplace_mixed_stream=verified marketplace_mixed_browse=verified marketplace_mixed_search=verified awpcp_public_listing_api=verified marketplace_category_browse=verified marketplace_category_search=verified marketplace_unmapped_category=excluded marketplace_search=verified marketplace_truncation=verified woocommerce_label=absent supplier_label=absent location_filter=preserved weekend_events_manager=verified weekend_schedule=verified cms_admin_health=verified database=verified admin_boundary=verified\n";
exit( 0 );
