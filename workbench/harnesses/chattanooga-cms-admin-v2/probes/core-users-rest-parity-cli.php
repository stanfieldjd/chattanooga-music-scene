<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_v2_user_rest_fail( $message ) {
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_user_rest_catalog() {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}
	$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
	if ( ! $catalog instanceof WP_Ability ) {
		cmsa_v2_user_rest_fail( 'Universal catalog is unavailable.' );
	}
	$result = $catalog->execute( array() );
	if ( is_wp_error( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
		cmsa_v2_user_rest_fail( 'Universal catalog could not enumerate REST facades.' );
	}
	$items = $result['items'];
	return $items;
}

function cmsa_v2_user_rest_ability( $method, $path ) {
	$method = strtoupper( (string) $method );
	$matches = array();
	foreach ( cmsa_v2_user_rest_catalog() as $item ) {
		if ( 'rest' !== ( $item['contract'] ?? '' ) || $method !== ( $item['method'] ?? '' ) ) {
			continue;
		}
		$route = (string) ( $item['route'] ?? '' );
		$bridge = (string) ( $item['bridge'] ?? '' );
		if ( '' !== $route && '' !== $bridge && 1 === @preg_match( '@^' . $route . '$@i', $path ) ) {
			$matches[] = $bridge;
		}
	}
	if ( 1 !== count( $matches ) ) {
		cmsa_v2_user_rest_fail( sprintf( 'Expected one %1$s user facade for %2$s; found %3$d.', $method, $path, count( $matches ) ) );
	}
	$ability = wp_get_ability( $matches[0] );
	if ( ! $ability instanceof WP_Ability ) {
		cmsa_v2_user_rest_fail( 'Discovered user REST facade is unavailable.' );
	}
	return $ability;
}

function cmsa_v2_user_rest_call( $method, $path, array $params = array() ) {
	$ability = cmsa_v2_user_rest_ability( $method, $path );
	$input = array( 'path' => $path, 'params' => $params );
	if ( true !== $ability->check_permissions( $input ) ) {
		cmsa_v2_user_rest_fail( sprintf( 'Permission failed for %1$s %2$s.', $method, $path ) );
	}
	$result = $ability->execute( $input );
	if ( is_wp_error( $result ) ) {
		cmsa_v2_user_rest_fail( sprintf( '%1$s %2$s failed: %3$s %4$s', $method, $path, $result->get_error_code(), $result->get_error_message() ) );
	}
	if ( ! is_array( $result ) || (int) ( $result['status'] ?? 0 ) < 200 || (int) ( $result['status'] ?? 0 ) >= 300 || ! array_key_exists( 'data', $result ) ) {
		cmsa_v2_user_rest_fail( sprintf( '%1$s %2$s returned an invalid facade response.', $method, $path ) );
	}
	return $result['data'];
}

wp_set_current_user( 1 );
$suffix = substr( hash( 'sha256', wp_generate_uuid4() ), 0, 10 );
$username = 'cmsav2user' . $suffix;
$password = wp_generate_password( 32, true, true );

cmsa_v2_user_rest_ability( 'POST', '/wp/v2/users' );

$created = cmsa_v2_user_rest_call(
	'POST',
	'/wp/v2/users',
	array(
		'username' => $username,
		'email'    => $username . '@example.com',
		'password' => $password,
		'roles'    => array( 'subscriber' ),
	)
);
$user_id = (int) ( $created['id'] ?? 0 );
if ( $user_id < 2 ) {
	cmsa_v2_user_rest_fail( 'Core REST user creation did not return a non-admin ID.' );
}

$read = cmsa_v2_user_rest_call( 'GET', '/wp/v2/users/' . $user_id, array( 'context' => 'edit' ) );
if ( (int) ( $read['id'] ?? 0 ) !== $user_id || (string) ( $read['username'] ?? '' ) !== $username || ! in_array( 'subscriber', (array) ( $read['roles'] ?? array() ), true ) ) {
	cmsa_v2_user_rest_fail( 'Core REST user read did not preserve identity and role.' );
}

$updated_name = 'CMSA v2 REST User ' . $suffix;
$updated = cmsa_v2_user_rest_call( 'POST', '/wp/v2/users/' . $user_id, array( 'name' => $updated_name ) );
if ( (int) ( $updated['id'] ?? 0 ) !== $user_id || (string) ( $updated['name'] ?? '' ) !== $updated_name ) {
	cmsa_v2_user_rest_fail( 'Core REST user update did not persist.' );
}

wp_set_current_user( 0 );
$create_ability = cmsa_v2_user_rest_ability( 'POST', '/wp/v2/users' );
$blocked_input = array( 'path' => '/wp/v2/users', 'params' => array( 'username' => 'blocked-user', 'email' => 'blocked-user@example.com', 'password' => wp_generate_password( 24, true, true ) ) );
if ( false !== $create_ability->check_permissions( $blocked_input ) ) {
	cmsa_v2_user_rest_fail( 'Anonymous user creation was not blocked.' );
}
wp_set_current_user( 1 );

$deleted = cmsa_v2_user_rest_call( 'DELETE', '/wp/v2/users/' . $user_id, array( 'force' => true, 'reassign' => 1 ) );
if ( empty( $deleted['deleted'] ) || get_user_by( 'id', $user_id ) ) {
	cmsa_v2_user_rest_fail( 'Core REST user deletion was not confirmed.' );
}

if ( get_user_by( 'login', $username ) ) {
	cmsa_v2_user_rest_fail( 'Core user REST proof did not restore final disposable state.' );
}

echo "cmsa-v2-core-users-rest: PASS users=crud dynamic_bridge=verified provider_permission=preserved admin_boundary=verified final_state=restored\n";
exit( 0 );
