<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "awpcp-marketplace-candidate-dependency-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$expected = getenv( 'CMSA_EXPECT_AWPCP_DEPENDENCY' );
if ( ! in_array( $expected, array( 'absent', 'version-mismatch' ), true ) ) {
	fwrite( STDERR, "awpcp-marketplace-candidate-dependency-cli: CMSA_EXPECT_AWPCP_DEPENDENCY must be absent or version-mismatch.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( $admin ) {
	wp_set_current_user( $admin->ID );
}
$service = new CMSA_Marketplace_Listings();
$result = $service->list_items( array( 'per_page' => 1 ) );
if ( ! is_wp_error( $result ) ) {
	fwrite( STDERR, "awpcp-marketplace-candidate-dependency-cli: adapter did not fail closed.\n" );
	exit( 1 );
}

$expected_code = 'absent' === $expected ? 'cmsa_marketplace_dependency' : 'cmsa_marketplace_dependency_version';
if ( $expected_code !== $result->get_error_code() ) {
	fwrite( STDERR, 'awpcp-marketplace-candidate-dependency-cli: unexpected error code ' . $result->get_error_code() . ', expected ' . $expected_code . ".\n" );
	exit( 1 );
}

$observed_version = defined( 'AWPCP_VERSION' ) ? (string) AWPCP_VERSION : 'absent';
echo 'awpcp-marketplace-candidate-dependency-cli: PASS expectation=' . $expected . ' observed=' . $observed_version . ' code=' . $expected_code . "\n";
