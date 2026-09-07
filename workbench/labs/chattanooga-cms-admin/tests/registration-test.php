<?php

$lab = dirname( __DIR__ );
require $lab . '/tests/bootstrap/wp-stubs.php';
require $lab . '/candidate/chattanooga-cms-admin.php';

do_action( 'wp_abilities_api_categories_init' );
do_action( 'wp_abilities_api_init' );

$expected = array();
$fixtures = glob( $lab . '/fixtures/expected*abilities.json' );
sort( $fixtures, SORT_STRING );
foreach ( $fixtures as $fixture ) {
	$items = json_decode( file_get_contents( $fixture ), true );
	if ( ! is_array( $items ) ) {
		fwrite( STDERR, 'Could not read expected ability fixture ' . basename( $fixture ) . ".\n" );
		exit( 1 );
	}
	$expected = array_merge( $expected, $items );
}
if ( count( $expected ) !== count( array_unique( $expected ) ) ) {
	fwrite( STDERR, "Duplicate ability appears across expected manifests.\n" );
	exit( 1 );
}

$actual = array_keys( $GLOBALS['cmsa_registered_abilities'] );
sort( $expected );
sort( $actual );

if ( $expected !== $actual ) {
	fwrite( STDERR, "Ability set mismatch.\nExpected: " . json_encode( $expected, JSON_PRETTY_PRINT ) . "\nActual: " . json_encode( $actual, JSON_PRETTY_PRINT ) . "\n" );
	exit( 1 );
}

if ( ! isset( $GLOBALS['cmsa_registered_categories']['chattanooga-cms-admin'] ) ) {
	fwrite( STDERR, "CMS Admin ability category was not registered.\n" );
	exit( 1 );
}

foreach ( $GLOBALS['cmsa_registered_abilities'] as $name => $args ) {
	if ( empty( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) ) {
		fwrite( STDERR, "Ability lacks callable execute_callback: {$name}\n" );
		exit( 1 );
	}
	if ( empty( $args['permission_callback'] ) || ! is_callable( $args['permission_callback'] ) ) {
		fwrite( STDERR, "Ability lacks callable permission_callback: {$name}\n" );
		exit( 1 );
	}
	if ( ! isset( $args['category'] ) || 'chattanooga-cms-admin' !== $args['category'] ) {
		fwrite( STDERR, "Ability category mismatch: {$name}\n" );
		exit( 1 );
	}
	if ( ! isset( $args['output_schema']['type'] ) || 'object' !== $args['output_schema']['type'] ) {
		fwrite( STDERR, "Ability output schema is not object: {$name}\n" );
		exit( 1 );
	}
	if ( ! isset( $args['meta']['show_in_rest'] ) || false !== $args['meta']['show_in_rest'] ) {
		fwrite( STDERR, "Ability is not explicitly hidden from REST: {$name}\n" );
		exit( 1 );
	}
	if ( empty( $args['meta']['mcp']['public'] ) ) {
		fwrite( STDERR, "Ability is not marked for MCP discovery: {$name}\n" );
		exit( 1 );
	}
	if ( ! isset( $args['meta']['annotations'] ) || ! is_array( $args['meta']['annotations'] ) ) {
		fwrite( STDERR, "Ability lacks annotations: {$name}\n" );
		exit( 1 );
	}
}

echo 'registration-test: PASS (' . count( $actual ) . " abilities)\n";
