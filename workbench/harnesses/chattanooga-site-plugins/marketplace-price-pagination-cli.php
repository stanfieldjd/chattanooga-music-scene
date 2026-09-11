<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cms_marketplace_probe_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cms_marketplace_probe_assert( $condition, $message ) {
	if ( ! $condition ) {
		cms_marketplace_probe_fail( $message );
	}
}

function cms_marketplace_probe_set_request( array $values ) {
	$_GET     = $values;
	$_POST    = array();
	$_REQUEST = $values;
}

function cms_marketplace_probe_set_page_query( $page_id ) {
	$query = new WP_Query(
		array(
			'page_id'   => (int) $page_id,
			'post_type' => 'page',
		)
	);

	$GLOBALS['wp_query']     = $query;
	$GLOBALS['wp_the_query'] = $query;
}

function cms_marketplace_probe_create_listing( $title, $price_cents, $category_id ) {
	$start_date = ( new DateTimeImmutable( '-1 day', wp_timezone() ) )->format( 'Y-m-d H:i:s' );
	$end_date   = ( new DateTimeImmutable( '+30 days', wp_timezone() ) )->format( 'Y-m-d H:i:s' );

	try {
		$listing = awpcp_listings_api()->create_listing(
			array(
				'post_fields' => array(
					'post_author'  => 1,
					'post_title'   => $title,
					'post_content' => $title . ' created through the AWP production listing API for Marketplace price and pagination verification.',
				),
				'terms'       => array(
					AWPCP_CATEGORY_TAXONOMY => array( $category_id ),
				),
				'metadata'    => array(
					'_awpcp_contact_name'   => 'Integration Seller',
					'_awpcp_contact_email'  => 'integration@example.test',
					'_awpcp_price'          => (int) $price_cents,
					'_awpcp_payment_status' => AWPCP_Payment_Transaction::PAYMENT_STATUS_COMPLETED,
					'_awpcp_is_paid'        => true,
					'_awpcp_verified'       => true,
					'_awpcp_start_date'     => $start_date,
					'_awpcp_end_date'       => $end_date,
				),
			)
		);
	} catch ( AWPCP_Exception $exception ) {
		cms_marketplace_probe_fail( 'Could not create AWP listing: ' . $exception->getMessage() );
	}

	cms_marketplace_probe_assert( $listing instanceof WP_Post, 'AWP production API did not return a listing post.' );
	cms_marketplace_probe_assert( awpcp_listings_api()->enable_listing_without_triggering_actions( $listing ), 'AWP production API could not enable the listing.' );

	return $listing;
}

wp_set_current_user( 1 );

cms_marketplace_probe_assert( defined( 'CMS_MARKETPLACE_VERSION' ) && '0.1.1' === CMS_MARKETPLACE_VERSION, 'Marketplace 0.1.1 is not active.' );
cms_marketplace_probe_assert( class_exists( 'WC_Product_Simple' ), 'WooCommerce product API is unavailable.' );
cms_marketplace_probe_assert( function_exists( 'awpcp_listings_api' ), 'AWP production listing API is unavailable.' );

$prior_wp_query     = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query'] : null;
$prior_wp_the_query = isset( $GLOBALS['wp_the_query'] ) ? $GLOBALS['wp_the_query'] : null;
$prior_get          = $_GET;
$prior_post         = $_POST;
$prior_request      = $_REQUEST;

$existing_12 = get_post( CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID );
if ( $existing_12 ) {
	wp_delete_post( CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID, true );
}

$page_id = wp_insert_post(
	array(
		'import_id'    => CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Marketplace Price Pagination Probe',
		'post_content' => '[cms_marketplace]',
	),
	true
);
cms_marketplace_probe_assert( ! is_wp_error( $page_id ) && CMS_Unified_Marketplace::MARKETPLACE_PAGE_ID === (int) $page_id, 'Could not create Marketplace page 12.' );
cms_marketplace_probe_assert( awpcp_update_plugin_page_id( 'main-page-name', $page_id ), 'Could not assign Marketplace page as the AWP main page.' );
cms_marketplace_probe_assert( awpcp()->settings->update_option( 'main_page_display', 1, true ), 'Could not enable AWP main-page listing display.' );

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

$category_result = wp_insert_term(
	'Marketplace Probe Listings',
	AWPCP_CATEGORY_TAXONOMY,
	array( 'slug' => 'marketplace-probe-listings' )
);
cms_marketplace_probe_assert( ! is_wp_error( $category_result ), 'Could not create AWP probe category.' );
$category_id = absint( $category_result['term_id'] );

$product = new WC_Product_Simple();
$product->set_name( 'Marketplace Probe Strings' );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'visible' );
$product->set_regular_price( '12.99' );
$product->set_short_description( 'Marketplace price and pagination integration product.' );
$product->set_stock_status( 'instock' );
$product_id = $product->save();
cms_marketplace_probe_assert( $product_id > 0, 'Could not create WooCommerce probe product.' );

$listing_15 = cms_marketplace_probe_create_listing( 'Marketplace Probe Listing Fifteen', 1500, $category_id );
$listing_30 = cms_marketplace_probe_create_listing( 'Marketplace Probe Listing Thirty', 3000, $category_id );

$search_page_id = absint( awpcp_get_page_id_by_ref( 'search-ads-page-name' ) );
$browse_page_id = absint( awpcp_get_page_id_by_ref( 'browse-ads-page-name' ) );
cms_marketplace_probe_assert( $search_page_id > 0, 'AWP Search Listings page is unavailable.' );
cms_marketplace_probe_assert( $browse_page_id > 0, 'AWP Browse Listings page is unavailable.' );

// $10-$13: Woo product at $12.99 must match; AWP listings at $15 and $30 must not.
cms_marketplace_probe_set_request(
	array(
		'awpcp-step'     => 'dosearch',
		'keywordphrase'  => '',
		'searchname'     => '',
		'searchpricemin' => '10',
		'searchpricemax' => '13',
		'searchcategory' => array(),
		'regions'        => array(),
	)
);
cms_marketplace_probe_set_page_query( $search_page_id );
$low_price_rendered = awpcp_search_listings_page()->dispatch();
cms_marketplace_probe_assert( false !== strpos( $low_price_rendered, 'Marketplace Probe Strings' ), 'Real AWP Search price band $10-$13 excluded the matching $12.99 store product.' );
cms_marketplace_probe_assert( false === strpos( $low_price_rendered, 'Marketplace Probe Listing Fifteen' ), 'Real AWP Search price band $10-$13 included the $15 community listing.' );
cms_marketplace_probe_assert( false === strpos( $low_price_rendered, 'Marketplace Probe Listing Thirty' ), 'Real AWP Search price band $10-$13 included the $30 community listing.' );

// $14-$16: AWP listing at $15 must match; Woo product at $12.99 and $30 listing must not.
cms_marketplace_probe_set_request(
	array(
		'awpcp-step'     => 'dosearch',
		'keywordphrase'  => '',
		'searchname'     => '',
		'searchpricemin' => '14',
		'searchpricemax' => '16',
		'searchcategory' => array(),
		'regions'        => array(),
	)
);
cms_marketplace_probe_set_page_query( $search_page_id );
$mid_price_rendered = awpcp_search_listings_page()->dispatch();
cms_marketplace_probe_assert( false !== strpos( $mid_price_rendered, 'Marketplace Probe Listing Fifteen' ), 'Real AWP Search price band $14-$16 excluded the matching $15 community listing.' );
cms_marketplace_probe_assert( false === strpos( $mid_price_rendered, 'Marketplace Probe Strings' ), 'Real AWP Search price band $14-$16 included the $12.99 store product.' );
cms_marketplace_probe_assert( false === strpos( $mid_price_rendered, 'Marketplace Probe Listing Thirty' ), 'Real AWP Search price band $14-$16 included the $30 community listing.' );

// First Browse page may contain a store product; page two must contain a community listing but must not repeat that product.
cms_marketplace_probe_set_request( array( 'results' => 1, 'offset' => 0 ) );
cms_marketplace_probe_set_page_query( $browse_page_id );
$browse_page_one = awpcp_browse_listings_page()->dispatch();
cms_marketplace_probe_assert( false !== strpos( $browse_page_one, 'Marketplace Probe Strings' ), 'First real AWP Browse page did not contain the Marketplace store product.' );

cms_marketplace_probe_set_request( array( 'results' => 1, 'offset' => 1 ) );
cms_marketplace_probe_set_page_query( $browse_page_id );
$browse_page_two = awpcp_browse_listings_page()->dispatch();
$page_two_has_listing = false !== strpos( $browse_page_two, 'Marketplace Probe Listing Fifteen' ) || false !== strpos( $browse_page_two, 'Marketplace Probe Listing Thirty' );
cms_marketplace_probe_assert( $page_two_has_listing, 'Second real AWP Browse page did not contain the remaining community listing.' );
cms_marketplace_probe_assert( false === strpos( $browse_page_two, 'Marketplace Probe Strings' ), 'Marketplace store product repeated on the second AWP Browse results page.' );
cms_marketplace_probe_assert( false === strpos( $browse_page_two, 'There were no listings found.' ), 'Second AWP Browse page rendered a false empty state despite a remaining community listing.' );

wp_delete_post( $listing_15->ID, true );
wp_delete_post( $listing_30->ID, true );
wp_delete_term( $category_id, AWPCP_CATEGORY_TAXONOMY );
wp_delete_post( $product_id, true );
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

echo "marketplace-price-pagination: PASS price_low=verified price_mid=verified pagination_first_page=verified pagination_no_repeat=verified\n";
exit( 0 );
