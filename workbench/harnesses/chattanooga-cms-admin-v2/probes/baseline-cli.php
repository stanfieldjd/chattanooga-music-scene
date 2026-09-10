<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
$catalog = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-cms-admin/catalog' ) : null;
if ( ! $catalog instanceof WP_Ability ) {
	fwrite( STDERR, "Universal catalog is missing.\n" );
	exit( 1 );
}

$result = $catalog->execute( array() );
if ( is_wp_error( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
	fwrite( STDERR, "Universal catalog returned no items.\n" );
	exit( 1 );
}

$ability_bridge = '';
$rest_bridge = '';
foreach ( $result['items'] as $item ) {
	$target = (string) ( $item['target'] ?? '' );
	if ( 'orbit-fixture/read-marker' === $target ) {
		$ability_bridge = (string) ( $item['bridge'] ?? '' );
	}
	if ( 'rest' === ( $item['contract'] ?? '' ) && 'GET' === ( $item['method'] ?? '' ) ) {
		$route = (string) ( $item['route'] ?? '' );
		if ( false !== strpos( $route, '/comet-fixture/v1/marker/' ) ) {
			$rest_bridge = (string) ( $item['bridge'] ?? '' );
		}
	}
	$encoded = wp_json_encode( $item );
	if ( is_string( $encoded ) && false !== stripos( $encoded, 'asteroid-private' ) ) {
		fwrite( STDERR, "Unsupported private-only provider appeared in the universal catalog.\n" );
		exit( 1 );
	}
}

if ( '' === $ability_bridge || '' === $rest_bridge ) {
	fwrite( STDERR, "Fresh providers were not dynamically discovered.\n" );
	exit( 1 );
}

$ability = wp_get_ability( $ability_bridge );
$ability_input = array( 'value' => 5 );
if ( ! $ability instanceof WP_Ability || true !== $ability->check_permissions( $ability_input ) ) {
	fwrite( STDERR, "Ability facade permission failed.\n" );
	exit( 1 );
}
$ability_result = $ability->execute( $ability_input );
if ( is_wp_error( $ability_result ) || 12 !== (int) ( $ability_result['marker'] ?? 0 ) ) {
	fwrite( STDERR, "Ability facade execution failed.\n" );
	exit( 1 );
}

$rest = wp_get_ability( $rest_bridge );
$rest_input = array( 'path' => '/comet-fixture/v1/marker/29', 'params' => array() );
if ( ! $rest instanceof WP_Ability || true !== $rest->check_permissions( $rest_input ) ) {
	fwrite( STDERR, "REST facade permission failed.\n" );
	exit( 1 );
}
$rest_result = $rest->execute( $rest_input );
if ( is_wp_error( $rest_result ) || 29 !== (int) ( $rest_result['data']['id'] ?? 0 ) || 'comet' !== ( $rest_result['data']['marker'] ?? '' ) ) {
	fwrite( STDERR, "REST facade execution failed.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( false !== $catalog->check_permissions( array() ) || false !== $ability->check_permissions( $ability_input ) || false !== $rest->check_permissions( $rest_input ) ) {
	fwrite( STDERR, "Anonymous administration was not blocked.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
echo "cmsa-v2-baseline: PASS identity=existing_slot ability_discovery=verified ability_execution=verified rest_discovery=verified rest_execution=verified unsupported_provider=closed admin_boundary=verified\n";
exit( 0 );
