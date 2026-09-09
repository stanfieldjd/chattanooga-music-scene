<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['cms_marketplace_test_page_content'] = '';
$GLOBALS['cms_marketplace_test_products']     = array();
$GLOBALS['cms_marketplace_test_awpcp_terms']  = array(
	42 => (object) array( 'term_id' => 42, 'name' => 'Instruments', 'slug' => 'guitars' ),
	43 => (object) array( 'term_id' => 43, 'name' => 'Guitar Accessories', 'slug' => 'guitar-accessories' ),
);
$GLOBALS['cms_marketplace_test_product_terms'] = array(
	293 => (object) array( 'term_id' => 293, 'name' => 'Guitar Accessories', 'slug' => 'guitar-accessories' ),
);

function add_filter() {}
function add_action() {}
function wp_enqueue_style() {}
function is_admin() { return false; }
function is_page( $id ) { return 12 === (int) $id; }
function get_post_field() { return $GLOBALS['cms_marketplace_test_page_content']; }
function has_shortcode( $content, $tag ) { return false !== strpos( $content, '[' . $tag ); }
function wc_get_products() { return $GLOBALS['cms_marketplace_test_products']; }
function absint( $value ) { return abs( (int) $value ); }
function is_wp_error() { return false; }
function strip_shortcodes( $value ) { return $value; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }

function get_term( $id, $taxonomy ) {
	if ( 'awpcp_listing_category' !== $taxonomy ) {
		return false;
	}
	return isset( $GLOBALS['cms_marketplace_test_awpcp_terms'][ $id ] )
		? $GLOBALS['cms_marketplace_test_awpcp_terms'][ $id ]
		: false;
}

function get_term_by( $field, $value, $taxonomy ) {
	if ( 'product_cat' !== $taxonomy ) {
		return false;
	}
	foreach ( $GLOBALS['cms_marketplace_test_product_terms'] as $term ) {
		if ( isset( $term->{$field} ) && $term->{$field} === $value ) {
			return $term;
		}
	}
	return false;
}

function get_term_children() {
	return array();
}

class WC_Product {
	private $visible;
	private $name;
	private $short_description;
	private $description;
	private $category_ids;
	private $price;
	private $type;
	private $variation_min;
	private $variation_max;

	public function __construct( $visible = true, $name = '', $category_ids = array(), $price = '', $type = 'simple', $variation_min = '', $variation_max = '' ) {
		$this->visible           = (bool) $visible;
		$this->name              = $name;
		$this->short_description = '';
		$this->description       = '';
		$this->category_ids      = $category_ids;
		$this->price             = $price;
		$this->type              = $type;
		$this->variation_min     = $variation_min;
		$this->variation_max     = $variation_max;
	}

	public function is_visible() { return $this->visible; }
	public function get_name() { return $this->name; }
	public function get_short_description() { return $this->short_description; }
	public function get_description() { return $this->description; }
	public function get_category_ids() { return $this->category_ids; }
	public function get_price() { return $this->price; }
	public function is_type( $type ) { return $this->type === $type; }
	public function get_variation_price( $bound ) { return 'min' === $bound ? $this->variation_min : $this->variation_max; }

	public function set_descriptions( $short_description, $description ) {
		$this->short_description = $short_description;
		$this->description       = $description;
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

$visible_a = new WC_Product( true, 'Visible A' );
$hidden    = new WC_Product( false, 'Hidden' );
$visible_b = new WC_Product( true, 'Visible B' );
$GLOBALS['cms_marketplace_test_products'] = array( $visible_a, $hidden, $visible_b );
$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	array( $visible_a, $visible_b ),
	cms_marketplace_test_private( $marketplace, 'get_catalog_products' ),
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

$accessory = new WC_Product( true, 'Steel Guitar Slide', array( 293 ), '9.99' );
$other     = new WC_Product( true, 'Practice Stand', array( 292 ), '24.99' );
$GLOBALS['cms_marketplace_test_products'] = array( $accessory, $other );
$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	array( $accessory ),
	cms_marketplace_test_private(
		$marketplace,
		'get_products_for_query',
		array( 'browse-listings', array( 'classifieds_query' => array( 'category' => 43 ) ) )
	),
	'An exact Marketplace category match must include matching store products.'
);

$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	array(),
	cms_marketplace_test_private(
		$marketplace,
		'get_products_for_query',
		array( 'browse-listings', array( 'classifieds_query' => array( 'category' => 42 ) ) )
	),
	'A category without a verified store-category match must not receive unrelated products.'
);

$wireless = new WC_Product( true, 'Wireless Lavalier Microphone', array(), '34.99' );
$slide    = new WC_Product( true, 'Stainless Guitar Slide', array(), '9.99' );
$wireless->set_descriptions( 'Compact microphone system', 'Wireless microphone for live and mobile recording.' );
$GLOBALS['cms_marketplace_test_products'] = array( $wireless, $slide );
$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	array( $wireless ),
	cms_marketplace_test_private(
		$marketplace,
		'get_products_for_query',
		array( 'search', array( 's' => 'wireless', 'classifieds_query' => array() ) )
	),
	'Marketplace text search must include matching store products.'
);

$cheap    = new WC_Product( true, 'Budget Accessory', array(), '8.00' );
$mid      = new WC_Product( true, 'Mid Accessory', array(), '25.00' );
$variable = new WC_Product( true, 'Variable Accessory', array(), '', 'variable', '12.00', '40.00' );
$GLOBALS['cms_marketplace_test_products'] = array( $cheap, $mid, $variable );
$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	array( $mid, $variable ),
	cms_marketplace_test_private(
		$marketplace,
		'get_products_for_query',
		array(
			'search',
			array( 'classifieds_query' => array( 'min_price' => '20', 'max_price' => '30' ) ),
		)
	),
	'Price filtering must include products whose available price range overlaps the requested range.'
);

$GLOBALS['cms_marketplace_test_products'] = array( $wireless, $slide );
$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	array(),
	cms_marketplace_test_private(
		$marketplace,
		'get_products_for_query',
		array(
			'search',
			array( 'classifieds_query' => array( 'regions' => array( 'city' => 'Chattanooga' ) ) ),
		)
	),
	'Location filtering must stay intact: products without Marketplace location data cannot enter a location-filtered result.'
);

$marketplace = cms_marketplace_test_instance();
cms_marketplace_assert_same(
	array(),
	cms_marketplace_test_private(
		$marketplace,
		'get_products_for_query',
		array(
			'search',
			array( 'classifieds_query' => array( 'region' => 'Chattanooga' ) ),
		)
	),
	'Text location filtering must remain active and must not be treated as an empty numeric value.'
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
