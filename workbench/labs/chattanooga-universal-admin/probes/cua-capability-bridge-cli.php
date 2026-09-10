<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this probe runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$catalog = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-universal-admin/catalog' ) : null;
if ( ! $catalog instanceof WP_Ability ) {
	fwrite( STDERR, "Universal catalog ability was not registered.\n" );
	exit( 1 );
}

if ( true !== $catalog->check_permissions( array() ) ) {
	fwrite( STDERR, "Administrator could not access the universal catalog.\n" );
	exit( 1 );
}

$result = $catalog->execute( array() );
if ( is_wp_error( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
	fwrite( STDERR, "Universal catalog returned no discovered bridges.\n" );
	exit( 1 );
}

$by_target = array();
foreach ( $result['items'] as $item ) {
	$target = isset( $item['target'] ) ? (string) $item['target'] : '';
	$bridge = isset( $item['bridge'] ) ? (string) $item['bridge'] : '';
	if ( '' === $target || 0 !== strpos( $bridge, 'chattanooga-universal-admin/bridge-' ) ) {
		fwrite( STDERR, "Catalog contained an invalid bridge mapping.\n" );
		exit( 1 );
	}
	$by_target[ $target ] = $bridge;
}

$required_targets = array(
	'cua-lab-alpha/read-record',
	'cua-lab-beta/set-flag',
	'cua-lab-beta/denied',
);
foreach ( $required_targets as $target_name ) {
	if ( empty( $by_target[ $target_name ] ) ) {
		fwrite( STDERR, "A public provider ability was not discovered: {$target_name}\n" );
		exit( 1 );
	}
}

if ( isset( $by_target['cua-lab-alpha/private-record'] ) ) {
	fwrite( STDERR, "A private provider ability was exposed by the universal bridge.\n" );
	exit( 1 );
}

$read_bridge = wp_get_ability( $by_target['cua-lab-alpha/read-record'] );
if ( ! $read_bridge instanceof WP_Ability ) {
	fwrite( STDERR, "Read bridge was not registered.\n" );
	exit( 1 );
}
$read_input = array( 'id' => 17 );
if ( true !== $read_bridge->check_permissions( $read_input ) ) {
	fwrite( STDERR, "Read bridge did not preserve target permission.\n" );
	exit( 1 );
}
$read_result = $read_bridge->execute( $read_input );
if ( is_wp_error( $read_result ) || 17 !== (int) ( $read_result['id'] ?? 0 ) || 'alpha' !== ( $read_result['source'] ?? '' ) ) {
	fwrite( STDERR, "Read bridge did not execute the provider ability correctly.\n" );
	exit( 1 );
}

$write_bridge = wp_get_ability( $by_target['cua-lab-beta/set-flag'] );
if ( ! $write_bridge instanceof WP_Ability ) {
	fwrite( STDERR, "Mutation bridge was not registered.\n" );
	exit( 1 );
}
delete_option( 'cua_lab_beta_flag' );
$write_input = array( 'value' => true );
if ( true !== $write_bridge->check_permissions( $write_input ) ) {
	fwrite( STDERR, "Mutation bridge did not preserve target permission.\n" );
	exit( 1 );
}
$write_result = $write_bridge->execute( $write_input );
if ( is_wp_error( $write_result ) || true !== ( $write_result['value'] ?? null ) || true !== (bool) get_option( 'cua_lab_beta_flag', false ) ) {
	delete_option( 'cua_lab_beta_flag' );
	fwrite( STDERR, "Mutation bridge did not execute the provider-owned mutation.\n" );
	exit( 1 );
}

$denied_bridge = wp_get_ability( $by_target['cua-lab-beta/denied'] );
if ( ! $denied_bridge instanceof WP_Ability ) {
	delete_option( 'cua_lab_beta_flag' );
	fwrite( STDERR, "Denied control bridge was not registered.\n" );
	exit( 1 );
}
if ( false !== $denied_bridge->check_permissions( array() ) ) {
	delete_option( 'cua_lab_beta_flag' );
	fwrite( STDERR, "Denied target permission was not preserved.\n" );
	exit( 1 );
}
$denied_result = $denied_bridge->execute( array() );
if ( ! is_wp_error( $denied_result ) || 'ability_invalid_permissions' !== $denied_result->get_error_code() ) {
	delete_option( 'cua_lab_beta_flag' );
	fwrite( STDERR, "Denied target did not remain blocked by the facade permission boundary.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( false !== $catalog->check_permissions( array() ) ) {
	delete_option( 'cua_lab_beta_flag' );
	fwrite( STDERR, "Anonymous user unexpectedly passed the universal catalog permission check.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
delete_option( 'cua_lab_beta_flag' );

echo 'chattanooga-universal-admin-probe: PASS dynamic_discovery=verified public_bridge=verified private_exclusion=verified read_execution=verified mutation_execution=verified target_permissions=preserved denied_execution=blocked direct_universal_mutation=absent' . "\n";
exit( 0 );
