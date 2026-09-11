<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
$catalog = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-cms-admin/catalog' ) : null;
$read_gateway = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-cms-admin/read-bridge' ) : null;
$write_gateway = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-cms-admin/write-bridge' ) : null;
if ( ! $catalog instanceof WP_Ability || ! $read_gateway instanceof WP_Ability || ! $write_gateway instanceof WP_Ability ) {
	fwrite( STDERR, "Universal catalog or bounded MCP bridge gateways are missing.\n" );
	exit( 1 );
}

foreach ( array( $catalog, $read_gateway, $write_gateway ) as $public_gateway ) {
	$gateway_meta = $public_gateway->get_meta();
	if ( true !== ( $gateway_meta['mcp']['public'] ?? false ) ) {
		fwrite( STDERR, "A required bounded MCP gateway is not MCP-public.\n" );
		exit( 1 );
	}
}

$result = $catalog->execute( array() );
if ( is_wp_error( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
	fwrite( STDERR, "Universal catalog returned no items.\n" );
	exit( 1 );
}

$ability_bridge = '';
$mcp_only_bridge = '';
$rest_bridge = '';
foreach ( $result['items'] as $item ) {
	$target = (string) ( $item['target'] ?? '' );
	if ( 'orbit-fixture/read-marker' === $target ) {
		$ability_bridge = (string) ( $item['bridge'] ?? '' );
	}
	if ( 'orbit-fixture/mcp-only' === $target ) {
		$mcp_only_bridge = (string) ( $item['bridge'] ?? '' );
	}
	if ( in_array( $target, array( 'orbit-fixture/mcp-opt-out', 'orbit-fixture/rest-only' ), true ) ) {
		fwrite( STDERR, "Ability bridge ignored a client-specific exposure boundary.\n" );
		exit( 1 );
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

if ( '' === $ability_bridge || '' === $mcp_only_bridge || '' === $rest_bridge ) {
	fwrite( STDERR, "Fresh providers or MCP-specific public ability were not dynamically discovered.\n" );
	exit( 1 );
}

foreach ( wp_get_abilities() as $registered_ability ) {
	if ( ! $registered_ability instanceof WP_Ability ) {
		continue;
	}
	$name = $registered_ability->get_name();
	if ( ! preg_match( '/^chattanooga-cms-admin\/(?:bridge|rest)-[a-f0-9]{24}$/', $name ) ) {
		continue;
	}
	$meta = $registered_ability->get_meta();
	if ( true === ( $meta['mcp']['public'] ?? false ) ) {
		fwrite( STDERR, "A generated universal facade leaked into MCP tool discovery.\n" );
		exit( 1 );
	}
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

$gateway_ability_input = array(
	'bridge' => $ability_bridge,
	'input'  => $ability_input,
);
if ( true !== $read_gateway->check_permissions( $gateway_ability_input ) ) {
	fwrite( STDERR, "Read gateway did not preserve Ability facade permission.\n" );
	exit( 1 );
}
$gateway_ability_result = $read_gateway->execute( $gateway_ability_input );
if ( is_wp_error( $gateway_ability_result ) || 12 !== (int) ( $gateway_ability_result['result']['marker'] ?? 0 ) ) {
	fwrite( STDERR, "Read gateway Ability execution failed.\n" );
	exit( 1 );
}
if ( true === $write_gateway->check_permissions( $gateway_ability_input ) ) {
	fwrite( STDERR, "Write gateway accepted an explicitly read-only bridge.\n" );
	exit( 1 );
}

$mcp_only = wp_get_ability( $mcp_only_bridge );
if ( ! $mcp_only instanceof WP_Ability || true !== $mcp_only->check_permissions() ) {
	fwrite( STDERR, "MCP-specific public ability facade permission failed.\n" );
	exit( 1 );
}
$mcp_result = $mcp_only->execute();
if ( is_wp_error( $mcp_result ) || 'mcp-only' !== ( $mcp_result['marker'] ?? '' ) ) {
	fwrite( STDERR, "MCP-specific public ability facade execution failed.\n" );
	exit( 1 );
}
$gateway_mcp_result = $read_gateway->execute( array( 'bridge' => $mcp_only_bridge ) );
if ( is_wp_error( $gateway_mcp_result ) || 'mcp-only' !== ( $gateway_mcp_result['result']['marker'] ?? '' ) ) {
	fwrite( STDERR, "Read gateway MCP-specific Ability execution failed.\n" );
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
$gateway_rest_result = $read_gateway->execute(
	array(
		'bridge' => $rest_bridge,
		'input'  => $rest_input,
	)
);
if ( is_wp_error( $gateway_rest_result ) || 29 !== (int) ( $gateway_rest_result['result']['data']['id'] ?? 0 ) || 'comet' !== ( $gateway_rest_result['result']['data']['marker'] ?? '' ) ) {
	fwrite( STDERR, "Read gateway REST execution failed.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if (
	false !== $catalog->check_permissions( array() )
	|| false !== $ability->check_permissions( $ability_input )
	|| false !== $mcp_only->check_permissions()
	|| false !== $rest->check_permissions( $rest_input )
	|| false !== $read_gateway->check_permissions( $gateway_ability_input )
) {
	fwrite( STDERR, "Anonymous administration was not blocked.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
echo "cmsa-v2-baseline: PASS identity=existing_slot ability_discovery=verified ability_execution=verified mcp_specific_exposure=verified mcp_opt_out=preserved rest_only_not_broadened=verified rest_discovery=verified rest_execution=verified mcp_discovery_compaction=verified bounded_gateway_execution=verified unsupported_provider=closed admin_boundary=verified\n";
exit( 0 );
