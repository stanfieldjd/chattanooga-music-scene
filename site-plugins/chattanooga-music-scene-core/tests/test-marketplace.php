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

class WC_Product {
	private $visible;

	public function __construct( $visible ) {
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

function cms_test_private( $object, $method ) {
	$reflection = new ReflectionMethod( 'CMS_Marketplace', $method );
	$reflection->setAccessible( true );
	return $reflection->invoke( $object );
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

echo "Marketplace integration tests passed.\n";
