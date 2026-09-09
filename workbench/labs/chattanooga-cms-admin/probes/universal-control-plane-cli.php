<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this test runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
delete_option( 'cmsa_universal_fixture_two_executed' );

$inspect = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-cms-admin/inspect-extension-capabilities' ) : null;
if ( ! $inspect instanceof WP_Ability ) {
	fwrite( STDERR, "Universal inspection ability was not registered.\n" );
	exit( 1 );
}

$list_one = $inspect->execute(
	array(
		'namespace' => 'fixture-one',
		'page'      => 1,
		'per_page'  => 100,
	)
);
if ( is_wp_error( $list_one ) ) {
	fwrite( STDERR, 'Fixture-one discovery failed: ' . $list_one->get_error_code() . "\n" );
	exit( 1 );
}
if ( 1 !== (int) $list_one['total'] || 1 !== count( $list_one['items'] ) || 'fixture-one/read' !== $list_one['items'][0]['name'] ) {
	fwrite( STDERR, "Fixture-one public/private discovery boundary failed.\n" );
	exit( 1 );
}

$list_two = $inspect->execute(
	array(
		'namespace' => 'fixture-two',
		'page'      => 1,
		'per_page'  => 100,
	)
);
if ( is_wp_error( $list_two ) ) {
	fwrite( STDERR, 'Fixture-two discovery failed: ' . $list_two->get_error_code() . "\n" );
	exit( 1 );
}
if ( 1 !== (int) $list_two['total'] || 1 !== count( $list_two['items'] ) || 'fixture-two/write' !== $list_two['items'][0]['name'] ) {
	fwrite( STDERR, "Fixture-two discovery failed.\n" );
	exit( 1 );
}
if ( true !== $list_two['items'][0]['public'] || false !== $list_two['items'][0]['show_in_rest'] || true !== $list_two['items'][0]['mcp_public'] ) {
	fwrite( STDERR, "Fixture-two public-channel metadata was not reported exactly.\n" );
	exit( 1 );
}
if ( false !== $list_two['items'][0]['annotations']['readonly'] || false !== $list_two['items'][0]['annotations']['destructive'] || false !== $list_two['items'][0]['annotations']['idempotent'] ) {
	fwrite( STDERR, "Fixture-two annotations were not reported exactly.\n" );
	exit( 1 );
}

$exact = $inspect->execute( array( 'name' => 'fixture-two/write' ) );
if ( is_wp_error( $exact ) || empty( $exact['item'] ) || 'fixture-two/write' !== $exact['item']['name'] ) {
	fwrite( STDERR, "Exact native ability inspection failed.\n" );
	exit( 1 );
}
if ( empty( $exact['item']['input_schema']['properties']['value'] ) ) {
	fwrite( STDERR, "Native input schema was not preserved in discovery output.\n" );
	exit( 1 );
}

$private = $inspect->execute( array( 'name' => 'fixture-one/private' ) );
if ( ! is_wp_error( $private ) || 'cmsa_universal_not_found' !== $private->get_error_code() ) {
	fwrite( STDERR, "Private native ability was exposed by the universal catalog.\n" );
	exit( 1 );
}

$self = $inspect->execute( array( 'name' => 'chattanooga-cms-admin/get-health' ) );
if ( ! is_wp_error( $self ) || 'cmsa_universal_self' !== $self->get_error_code() ) {
	fwrite( STDERR, "CMSA self-recursion boundary failed.\n" );
	exit( 1 );
}

if ( function_exists( 'wp_get_ability' ) && wp_get_ability( 'chattanooga-cms-admin/execute-extension-capability' ) ) {
	fwrite( STDERR, "A generic execute proxy exists and would undermine per-ability governance.\n" );
	exit( 1 );
}
if ( false !== get_option( 'cmsa_universal_fixture_two_executed', false ) ) {
	fwrite( STDERR, "Discovery executed a target mutation ability.\n" );
	exit( 1 );
}

$target = wp_get_ability( 'fixture-two/write' );
wp_set_current_user( 0 );
$denied = $target instanceof WP_Ability ? $target->check_permissions( array( 'value' => 'x' ) ) : true;
wp_set_current_user( 1 );
$allowed = $target instanceof WP_Ability ? $target->check_permissions( array( 'value' => 'x' ) ) : false;
if ( false !== $denied || true !== $allowed ) {
	fwrite( STDERR, "Native target permission callback did not remain authoritative.\n" );
	exit( 1 );
}

$all = $inspect->execute( array( 'search' => 'fixture', 'page' => 1, 'per_page' => 100 ) );
if ( is_wp_error( $all ) ) {
	fwrite( STDERR, "Cross-extension discovery failed.\n" );
	exit( 1 );
}
$names = array_column( $all['items'], 'name' );
sort( $names, SORT_STRING );
if ( ! in_array( 'fixture-one/read', $names, true ) || ! in_array( 'fixture-two/write', $names, true ) || in_array( 'fixture-one/private', $names, true ) ) {
	fwrite( STDERR, "Cross-extension discovery did not preserve the public-only boundary.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
echo 'universal-control-plane-cli: PASS dynamic_plugins=2 public_discovered=2 private_hidden=1 self_recursion=blocked execute_proxy=absent native_permissions=preserved mutation_during_discovery=absent' . "\n";
