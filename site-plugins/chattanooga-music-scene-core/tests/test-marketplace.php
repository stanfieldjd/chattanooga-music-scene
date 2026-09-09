<?php

// Lightweight source-level tests for the Marketplace integration.
// These tests intentionally stub the small WordPress/WooCommerce surface used
// by the query/activation logic so they can run in CI without booting WordPress.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['cms_test_page_content'] = '';
$GLOBALS['cms_test_products']     = array();

function add_filter() {}
function add_action() {}
function wp_enqueue_style() {}
function is_admin() { return false; }
function is_page( $id ) { return 12 === (int) $id; }
function get_post_field() { return $GLOBALS['cms_test_page_content']; }
function has_shortcode( $content, $tag ) { return false !== strpos( $content, '[' . $tag ); }
function wc_get_products() { return $GLOBALS['cms_test_products']; }
function absint( $value ) { return abs( (int) $value ); }

class WC_Product {
	private $visible;

	public function __construct( $visible = true ) {
		$this->visible = (bool) $visible;
	}

	public function is_visible() {
		return $this->visible;
	}
}

require dirname( __DIR__ ) . '/includes/class-cms-marketplace.php';

function cms_test_instance() {
	$reflection = new ReflectionClass( 'CMS_Marketplace' );
	return $reflection->newInstanceWithoutConstructor();
}

function cms_test_private( $object, $method, array $args = array() ) {
	$reflection = new ReflectionMethod( 'CMS_Marketplace', $method );
	$reflection->setAccessible( true );
	return $reflection->invokeArgs( $object, $args );
}

function cms_test_property( $object, $property ) {
	$reflection = new ReflectionProperty( 'CMS_Marketplace', $property );
	$reflection->setAccessible( true );
	return $reflection->getValue( $object );
}

function cms_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . "\n" );
		fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$GLOBALS['cms_test_page_content'] = '<p>[AWPCPCLASSIFIEDSUI]</p><p>[products limit="12"]</p>';
$marketplace = cms_test_instance();
cms_assert_same(
	false,
	cms_test_private( $marketplace, 'integration_ready' ),
	'Interleaving must stay inactive while the legacy products shortcode remains.'
);
cms_assert_same(
	false,
	cms_test_private( $marketplace, 'is_marketplace_request' ),
	'The Marketplace request must remain inactive during the guarded deployment state.'
);

$GLOBALS['cms_test_page_content'] = '<p>[AWPCPCLASSIFIEDSUI]</p>';
$marketplace = cms_test_instance();
cms_assert_same(
	true,
	cms_test_private( $marketplace, 'integration_ready' ),
	'Interleaving must activate after the legacy products shortcode is removed.'
);
cms_assert_same(
	true,
	cms_test_private( $marketplace, 'is_marketplace_request' ),
	'The Marketplace request must activate on page 12 after cleanup.'
);

$visible_a = new WC_Product( true );
$hidden    = new WC_Product( false );
$visible_b = new WC_Product( true );
$GLOBALS['cms_test_products'] = array( $visible_a, $hidden, $visible_b );
$marketplace = cms_test_instance();
cms_assert_same(
	array( $visible_a, $visible_b ),
	cms_test_private( $marketplace, 'get_products' ),
	'Only catalog-visible published products returned by the product query may enter the Marketplace stream.'
);

$GLOBALS['cms_test_products'] = array(
	new WC_Product(), new WC_Product(), new WC_Product(), new WC_Product(),
	new WC_Product(), new WC_Product(), new WC_Product(), new WC_Product(),
);
$marketplace = cms_test_instance();
$listings    = array( (object) array(), (object) array(), (object) array(), (object) array() );
$marketplace->prepare_interleaving( array(), 'main-page', $listings, array( 'offset' => 0, 'paged' => 1 ) );
cms_assert_same(
	array( 1 => 2, 2 => 2, 3 => 2, 4 => 2 ),
	cms_test_property( $marketplace, 'assignments' ),
	'Eight products must be distributed evenly across four Marketplace listings.'
);
cms_assert_same(
	2,
	count( cms_test_private( $marketplace, 'products_for_position', array( 1 ) ) ),
	'The first listing must receive its assigned product count.'
);
cms_assert_same(
	2,
	count( cms_test_private( $marketplace, 'products_for_position', array( 2 ) ) ),
	'The second listing must continue from the shared product cursor.'
);

$GLOBALS['cms_test_products'] = array(
	new WC_Product(), new WC_Product(), new WC_Product(), new WC_Product(), new WC_Product(),
);
$marketplace = cms_test_instance();
$marketplace->prepare_interleaving( array(), 'main-page', $listings, array( 'offset' => 0, 'paged' => 1 ) );
cms_assert_same(
	array( 1 => 2, 2 => 1, 3 => 1, 4 => 1 ),
	cms_test_property( $marketplace, 'assignments' ),
	'An uneven product count must stay inside the listing stream instead of spilling below pagination.'
);

$marketplace = cms_test_instance();
$marketplace->prepare_interleaving( array(), 'main-page', $listings, array( 'offset' => 10, 'paged' => 2 ) );
cms_assert_same(
	array(),
	cms_test_property( $marketplace, 'assignments' ),
	'Products must not repeat on later classified-results pages.'
);

$marketplace = cms_test_instance();
$marketplace->prepare_interleaving(
	array(),
	'browse-listings',
	$listings,
	array(
		'offset'            => 0,
		'paged'             => 1,
		'classifieds_query' => array( 'category' => 96 ),
	)
);
cms_assert_same(
	array(),
	cms_test_property( $marketplace, 'assignments' ),
	'Unmapped store products must not contaminate an AWP category-filtered result set.'
);

$marketplace = cms_test_instance();
$marketplace->prepare_interleaving(
	array(),
	'browse-listings',
	$listings,
	array(
		'offset'            => 0,
		'paged'             => 1,
		'classifieds_query' => array( 'context' => 'public-listings' ),
	)
);
cms_assert_same(
	array( 1 => 2, 2 => 1, 3 => 1, 4 => 1 ),
	cms_test_property( $marketplace, 'assignments' ),
	'Unfiltered Browse Ads must remain part of the unified Marketplace stream.'
);

echo "Marketplace integration tests passed.\n";
