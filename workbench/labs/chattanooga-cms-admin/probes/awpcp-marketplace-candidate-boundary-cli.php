<?php

$lab = dirname( __DIR__ );
$repository = dirname( dirname( dirname( $lab ) ) );
$bootstrap = file_get_contents( $lab . '/candidate/chattanooga-cms-admin.php' );
$coordinator = file_get_contents( $lab . '/candidate/includes/class-cmsa-plugin.php' );
$service = file_get_contents( $lab . '/candidate/includes/class-cmsa-marketplace-listings.php' );
$abilities = file_get_contents( $lab . '/candidate/includes/class-cmsa-marketplace-listing-abilities.php' );
$marketplace_bootstrap_path = $repository . '/site-plugins/chattanooga-music-marketplace/chattanooga-music-marketplace.php';
$marketplace_class_path = $repository . '/site-plugins/chattanooga-music-marketplace/includes/class-cms-unified-marketplace.php';
$marketplace_test_path = $repository . '/site-plugins/chattanooga-music-marketplace/tests/test-marketplace.php';

foreach ( array( $bootstrap, $coordinator, $service, $abilities ) as $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: candidate source missing.\n" );
		exit( 1 );
	}
}
foreach ( array( $marketplace_bootstrap_path, $marketplace_class_path, $marketplace_test_path ) as $path ) {
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, 'awpcp-marketplace-candidate-boundary-cli: reconciled Marketplace source missing: ' . $path . "\n" );
		exit( 1 );
	}
}

foreach ( array( 'class-cmsa-marketplace-listings.php', 'class-cmsa-marketplace-listing-abilities.php' ) as $required_include ) {
	if ( false === strpos( $bootstrap, $required_include ) ) {
		fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: bootstrap include missing: {$required_include}.\n" );
		exit( 1 );
	}
}
foreach ( array( 'new CMSA_Marketplace_Listings()', 'new CMSA_Marketplace_Listing_Abilities', 'marketplace_listing_abilities->register()' ) as $wiring ) {
	if ( false === strpos( $coordinator, $wiring ) ) {
		fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: coordinator wiring missing: {$wiring}.\n" );
		exit( 1 );
	}
}
foreach ( array( "'list-marketplace-listings'", "'get-marketplace-listing'" ) as $slug ) {
	if ( false === strpos( $abilities, $slug ) ) {
		fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: read ability registration missing: {$slug}.\n" );
		exit( 1 );
	}
}
foreach ( array( "current_user_can( 'manage_awpcp' )", "current_user_can( 'edit_others_awpcp_classified_ads' )", "'show_in_rest' => false", "'mcp'          => array( 'public' => true )", "'readonly'    => true", "'destructive' => false" ) as $guard ) {
	if ( false === strpos( $abilities, $guard ) ) {
		fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: ability guard missing: {$guard}.\n" );
		exit( 1 );
	}
}
foreach ( array( "SUPPORTED_AWPCP_VERSION = '4.4.8'", 'awpcp_listings_collection()', 'awpcp_listing_renderer()', 'awpcp_listing_authorization()', 'find_listings(', 'get_last_query()', 'has_expired(', "current_user_can( 'read_post'", "current_user_can( 'edit_post'" ) as $guard ) {
	if ( false === strpos( $service, $guard ) ) {
		fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: service contract guard missing: {$guard}.\n" );
		exit( 1 );
	}
}

$forbidden_service = array(
	'->is_expired(',
	'get_contact_name(',
	'get_contact_email(',
	'get_contact_phone(',
	'get_contact_phone_digits(',
	'get_payment_email(',
	'get_payment_status(',
	'get_payment_term(',
	'get_access_key(',
	'get_ip_address(',
	'get_user(',
	'get_post_meta(',
	'update_post_meta(',
	'add_post_meta(',
	'delete_post_meta(',
	'wp_insert_post(',
	'wp_update_post(',
	'$wpdb',
	'create_listing(',
	'update_listing(',
	'delete_listing(',
	'expire_listing',
);
foreach ( $forbidden_service as $forbidden ) {
	if ( false !== strpos( $service, $forbidden ) ) {
		fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: service escaped read-only non-sensitive boundary: {$forbidden}.\n" );
		exit( 1 );
	}
}
foreach ( array( "'create-marketplace-listing'", "'update-marketplace-listing'", "'delete-marketplace-listing'", "'publish-marketplace-listing'" ) as $forbidden_slug ) {
	if ( false !== strpos( $abilities, $forbidden_slug ) ) {
		fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: unauthorized mutation ability present: {$forbidden_slug}.\n" );
		exit( 1 );
	}
}
foreach ( array( 'WooCommerce', 'CJ Dropshipping' ) as $label ) {
	if ( false !== strpos( $service, $label ) || false !== strpos( $abilities, $label ) ) {
		fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: commerce-engine or supplier label leaked into listing adapter: {$label}.\n" );
		exit( 1 );
	}
}

$marketplace_bootstrap = file_get_contents( $marketplace_bootstrap_path );
$marketplace_class = file_get_contents( $marketplace_class_path );
$marketplace_test = file_get_contents( $marketplace_test_path );
if ( false === strpos( $marketplace_bootstrap, 'Version: 0.1.1' ) || false === strpos( $marketplace_bootstrap, "CMS_MARKETPLACE_VERSION', '0.1.1" ) ) {
	fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: authoritative Marketplace version contract mismatch.\n" );
	exit( 1 );
}
foreach ( array( 'awpcp-content-before-listings-pagination', 'awpcp-render-listing-item', 'wc_get_products' ) as $integration ) {
	if ( false === strpos( $marketplace_class, $integration ) ) {
		fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: authoritative Marketplace integration contract missing: {$integration}.\n" );
		exit( 1 );
	}
}
if ( false === strpos( $marketplace_test, "'The commerce engine must not appear as a customer-facing Marketplace label.'" ) ) {
	fwrite( STDERR, "awpcp-marketplace-candidate-boundary-cli: Marketplace label regression guard missing.\n" );
	exit( 1 );
}

echo "awpcp-marketplace-candidate-boundary-cli: PASS scope=read-only allowlist=non-sensitive marketplace=0.1.1\n";
