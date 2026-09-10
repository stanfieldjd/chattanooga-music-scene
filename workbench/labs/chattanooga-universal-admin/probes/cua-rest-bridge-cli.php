<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this probe runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
delete_option( 'cua_rest_gamma_flag' );
delete_option( 'cua_rest_gamma_denied_executed' );

foreach ( wp_get_abilities() as $ability ) {
	if ( $ability instanceof WP_Ability && 0 === strpos( $ability->get_name(), 'cua-rest-gamma/' ) ) {
		fwrite( STDERR, "REST-only provider unexpectedly registered a WordPress ability.\n" );
		exit( 1 );
	}
}

$catalog = wp_get_ability( 'chattanooga-universal-admin/catalog' );
if ( ! $catalog instanceof WP_Ability ) {
	fwrite( STDERR, "Universal catalog ability was not registered.\n" );
	exit( 1 );
}

$catalog_result = $catalog->execute( array() );
if ( is_wp_error( $catalog_result ) || empty( $catalog_result['items'] ) ) {
	fwrite( STDERR, "Universal catalog did not return REST bridge entries.\n" );
	exit( 1 );
}

$read_bridge_name = '';
$write_bridge_name = '';
$denied_bridge_name = '';
$private_exposed = false;
foreach ( $catalog_result['items'] as $item ) {
	if ( 'rest' !== ( $item['contract'] ?? '' ) ) {
		continue;
	}
	$route = (string) ( $item['route'] ?? '' );
	$method = (string) ( $item['method'] ?? '' );
	$bridge = (string) ( $item['bridge'] ?? '' );
	if ( 'GET' === $method && 0 === strpos( $route, '/cua-rest-gamma/v1/records/' ) ) {
		$read_bridge_name = $bridge;
	} elseif ( 'POST' === $method && '/cua-rest-gamma/v1/flag' === $route ) {
		$write_bridge_name = $bridge;
	} elseif ( 'POST' === $method && '/cua-rest-gamma/v1/denied' === $route ) {
		$denied_bridge_name = $bridge;
	} elseif ( '/cua-rest-gamma/v1/private' === $route ) {
		$private_exposed = true;
	}
}

if ( '' === $read_bridge_name || '' === $write_bridge_name || '' === $denied_bridge_name ) {
	fwrite( STDERR, "REST-only provider routes were not dynamically discovered.\n" );
	goto cleanup_failure;
}
if ( $private_exposed ) {
	fwrite( STDERR, "REST route hidden from the API index was exposed by the universal bridge.\n" );
	goto cleanup_failure;
}

$read_bridge = wp_get_ability( $read_bridge_name );
if ( ! $read_bridge instanceof WP_Ability ) {
	fwrite( STDERR, "REST read facade was not registered as an ability.\n" );
	goto cleanup_failure;
}
$read_input = array(
	'path'   => '/cua-rest-gamma/v1/records/23',
	'params' => array(),
);
if ( true !== $read_bridge->check_permissions( $read_input ) ) {
	fwrite( STDERR, "REST read facade did not preserve provider permission.\n" );
	goto cleanup_failure;
}
$read_result = $read_bridge->execute( $read_input );
if ( is_wp_error( $read_result ) || 200 !== (int) ( $read_result['status'] ?? 0 ) || 23 !== (int) ( $read_result['data']['id'] ?? 0 ) || 'gamma-rest' !== ( $read_result['data']['source'] ?? '' ) ) {
	fwrite( STDERR, "REST read facade did not execute the provider route correctly.\n" );
	goto cleanup_failure;
}

$path_escape = $read_bridge->check_permissions(
	array(
		'path'   => '/cua-rest-gamma/v1/flag',
		'params' => array(),
	)
);
if ( ! is_wp_error( $path_escape ) || 'cua_rest_path_mismatch' !== $path_escape->get_error_code() ) {
	fwrite( STDERR, "REST facade was not locked to its discovered route.\n" );
	goto cleanup_failure;
}

$write_bridge = wp_get_ability( $write_bridge_name );
if ( ! $write_bridge instanceof WP_Ability ) {
	fwrite( STDERR, "REST mutation facade was not registered as an ability.\n" );
	goto cleanup_failure;
}
$write_input = array(
	'path'   => '/cua-rest-gamma/v1/flag',
	'params' => array( 'value' => true ),
);
if ( true !== $write_bridge->check_permissions( $write_input ) ) {
	fwrite( STDERR, "REST mutation facade did not preserve provider permission.\n" );
	goto cleanup_failure;
}
$write_result = $write_bridge->execute( $write_input );
if ( is_wp_error( $write_result ) || 200 !== (int) ( $write_result['status'] ?? 0 ) || true !== ( $write_result['data']['value'] ?? null ) || true !== (bool) get_option( 'cua_rest_gamma_flag', false ) ) {
	fwrite( STDERR, "REST provider-owned mutation did not execute correctly.\n" );
	goto cleanup_failure;
}

$denied_bridge = wp_get_ability( $denied_bridge_name );
if ( ! $denied_bridge instanceof WP_Ability ) {
	fwrite( STDERR, "REST denied-control facade was not registered.\n" );
	goto cleanup_failure;
}
$denied_input = array(
	'path'   => '/cua-rest-gamma/v1/denied',
	'params' => array(),
);
if ( false !== $denied_bridge->check_permissions( $denied_input ) ) {
	fwrite( STDERR, "REST provider denial was not preserved by the facade.\n" );
	goto cleanup_failure;
}
$denied_result = $denied_bridge->execute( $denied_input );
if ( ! is_wp_error( $denied_result ) || 'ability_invalid_permissions' !== $denied_result->get_error_code() || get_option( 'cua_rest_gamma_denied_executed', false ) ) {
	fwrite( STDERR, "REST denied route reached its provider callback.\n" );
	goto cleanup_failure;
}

wp_set_current_user( 0 );
if ( false !== $read_bridge->check_permissions( $read_input ) ) {
	wp_set_current_user( 1 );
	fwrite( STDERR, "REST facade allowed an anonymous user through the administration boundary.\n" );
	goto cleanup_failure;
}
wp_set_current_user( 1 );

delete_option( 'cua_rest_gamma_flag' );
delete_option( 'cua_rest_gamma_denied_executed' );

echo 'cua-rest-bridge-cli: PASS rest_only_provider=verified route_discovery=verified route_lock=verified hidden_route=blocked read_execution=verified mutation_execution=verified provider_permissions=preserved denied_execution=blocked admin_boundary=verified direct_universal_mutation=absent' . "\n";
exit( 0 );

cleanup_failure:
wp_set_current_user( 1 );
delete_option( 'cua_rest_gamma_flag' );
delete_option( 'cua_rest_gamma_denied_executed' );
exit( 1 );
