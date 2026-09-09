<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['cms_marketplace_test_page_content'] = '';
$GLOBALS['cms_marketplace_test_products']     = array();

function add_filter() {}
function add_action() {}
function wp_enqueue_style() {}
function is_admin() { return false; }
function is_page( $id ) { return 12 === (int) $id; }
function get_post_field() { return $GLOBALS['cms_marketplace_test_page_content']; }
function has_shortcode( $content, $tag ) { return false !== strpos( $content, '[' . $tag ); }
function wc_get_products() { return $GLOBALS['cms_marketplace_test_products']; }
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

require dirname( __DIR__ ) . '/includes/class-cms-unified-marketplace.php';

function cms_marketplace_test_instance() {
	$reflection = new ReflectionClass( 'CMS_Unified_Marketplace' );
	return $reflection->newInstanceWithoutConstructor();
}

function cms_marketplace_test_private( $object, $method, array $args = array() ) {
	$reflection = new ReflectionMethod( 'CMS_Unified_Marketplace', $method );
	$reflection->setAccessible( true );
	return $reflection->invokeArgs( $object, $args );
}

function cms_marketplace_test_property( $object, $property ) {
	$reflection = new ReflectionProperty( 'CMS_Unified_Marketplace', $property );
	$reflection->setAccessible( true );
	return $reflection->getValue( $object );
}

function cms_marketplace_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . "\n" );
		fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$GLOBALS['cms_marketplace_test_page_content'] = '<p>[AWPCPCLASSIFIEDSUI]</p><p>[products limit="12"]</p>';
$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	false,
	cms_marketplace_test_private( $marketplace, 'integration_ready' ),
	'Unified rendering must remain inactive while the legacy standalone product block exists.'
);

$GLOBALS['cms_marketplace_test_page_content'] = '<p>[AWPCPCLASSIFIEDSUI]</p>';
$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	true,
	cms_marketplace_test_private( $marketplace, 'integration_ready' ),
	'Unified rendering must activate only after the standalone product block is removed.'
);

$GLOBALS['cms_marketplace_test_page_content'] = '<p>ordinary page content</p>';
$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	false,
	cms_marketplace_test_private( $marketplace, 'integration_ready' ),
	'The feature must not activate when the Marketplace listing interface is absent.'
);

$visible_a = new WC_Product( true );
$hidden    = new WC_Product( false );
$visible_b = new WC_Product( true );
$GLOBALS['cms_marketplace_test_products'] = array( $visible_a, $hidden, $visible_b );
$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	array( $visible_a, $visible_b ),
	cms_marketplace_test_private( $marketplace, 'get_products' ),
	'Only catalog-visible published products may enter the Marketplace stream.'
);

$GLOBALS['cms_marketplace_test_products'] = array(
	new WC_Product(), new WC_Product(), new WC_Product(), new WC_Product(),
	new WC_Product(), new WC_Product(), new WC_Product(), new WC_Product(),
);
$GLOBALS['cms_marketplace_test_page_content'] = '<p>[AWPCPCLASSIFIEDSUI]</p>';
$marketplace = cms_marketplace_test_instance();
$listings    = array( (object) array(), (object) array(), (object) array(), (object) array() );
$marketplace->prepare_interleaving( array(), 'main-page', $listings, array( 'offset' => 0, 'paged' => 1 ) );
cms_marketplace_assert_same(
	array( 1 => 2, 2 => 2, 3 => 2, 4 => 2 ),
	cms_marketplace_test_property( $marketplace, 'assignments' ),
	'Products must be distributed across the existing Marketplace listing stream.'
);

$marketplace = cms_marketplace_test_instance();
$marketplace->prepare_interleaving( array(), 'browse-listings', $listings, array( 'offset' => 10, 'paged' => 2 ) );
cms_marketplace_assert_same(
	array(),
	cms_marketplace_test_property( $marketplace, 'assignments' ),
	'Products must not repeat on later listing pages.'
);

$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	true,
	cms_marketplace_test_private(
		$marketplace,
		'has_active_listing_filter',
		array( array(), array( 'category' => 42 ) )
	),
	'Category-filtered listings must retain their filter semantics.'
);

$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	true,
	cms_marketplace_test_private(
		$marketplace,
		'has_active_listing_filter',
		array( array(), array( 'regions' => array( 'city' => 'Chattanooga' ) ) )
	),
	'Location-filtered listings must retain their filter semantics.'
);

$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	true,
	cms_marketplace_test_private(
		$marketplace,
		'has_active_listing_filter',
		array( array(), array( 'region' => 'Chattanooga' ) )
	),
	'A text location filter must be recognized as active.'
);

$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	false,
	cms_marketplace_test_private(
		$marketplace,
		'has_active_listing_filter',
		array( array(), array( 'context' => 'public-listings' ) )
	),
	'An unfiltered browse request must remain eligible for the unified stream.'
);

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-cms-unified-marketplace.php' );
cms_marketplace_assert_same(
	false,
	false !== strpos( $source, 'CJ Dropshipping' ),
	'Supplier identity must not appear in the Marketplace presentation source.'
);
cms_marketplace_assert_same(
	false,
	false !== strpos( $source, '>WooCommerce<' ),
	'The commerce engine must not appear as a customer-facing Marketplace label.'
);

echo "Marketplace feature tests passed.\n";
