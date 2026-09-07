<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this test runs.\n" );
	exit( 1 );
}

$lab = dirname( __DIR__ );
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

if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

if ( ! function_exists( 'wp_get_ability_category' ) || ! wp_get_ability_category( 'chattanooga-cms-admin' ) ) {
	fwrite( STDERR, "Chattanooga CMS Admin category is not present in the real WordPress registry.\n" );
	exit( 1 );
}

$missing = array();
foreach ( $expected as $name ) {
	if ( ! function_exists( 'wp_get_ability' ) || ! wp_get_ability( $name ) ) {
		$missing[] = $name;
	}
}

if ( $missing ) {
	fwrite( STDERR, "Real WordPress registry is missing abilities: " . implode( ', ', $missing ) . "\n" );
	exit( 1 );
}

echo 'wordpress-ability-registration: PASS (' . count( $expected ) . " abilities)\n";
echo 'categories-hook-count: ' . did_action( 'wp_abilities_api_categories_init' ) . "\n";
echo 'abilities-hook-count: ' . did_action( 'wp_abilities_api_init' ) . "\n";
