<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

$cmsa_users_rest_user_id = 0;

function cmsa_v2_users_rest_cleanup() {
	global $cmsa_users_rest_user_id;
	if ( $cmsa_users_rest_user_id > 1 && get_userdata( $cmsa_users_rest_user_id ) instanceof WP_User ) {
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		wp_delete_user( $cmsa_users_rest_user_id, 1 );
	}
	$cmsa_users_rest_user_id = 0;
}

function cmsa_v2_users_rest_fail( $message ) {
	cmsa_v2_users_rest_cleanup();
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_users_rest_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_v2_users_rest_fail( $message );
	}
}

function cmsa_v2_users_rest_catalog() {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}
	$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
	if ( ! $catalog instanceof WP_Ability ) {
		cmsa_v2_users_rest_fail( 'Universal catalog is unavailable.' );
	}
	$result = $catalog->execute( array() );
	if ( is_wp_error( $result ) || ! is_array( $result['items'] ?? null ) ) {
		cmsa_v2_users_rest_fail( 'Universal catalog could not enumerate REST facades.' );
	}
	$items = $result['items'];
	return $items;
}

function cmsa_v2_users_rest_ability( $method, $path ) {
	$method = strtoupper( (string) $method );
	$matches = array();
	foreach ( cmsa_v2_users_rest_catalog() as $item ) {
		if ( 'rest' !== ( $item['contract'] ?? '' ) || $method !== ( $item['method'] ?? '' ) ) {
			continue;
		}
		$route  = (string) ( $item['route'] ?? '' );
		$bridge = (string) ( $item['bridge'] ?? '' );
		if ( '' !== $route && '' !== $bridge && 1 === @preg_match( '@^' . $route . '$@i', $path ) ) {
			$matches[] = $bridge;
		}
	}
	if ( 1 !== count( $matches ) ) {
		cmsa_v2_users_rest_fail( sprintf( 'Expected one %1$s user facade for %2$s; found %3$d.', $method, $path, count( $matches ) ) );
	}
	$ability = wp_get_ability( $matches[0] );
	if ( ! $ability instanceof WP_Ability ) {
		cmsa_v2_users_rest_fail( 'Discovered user REST facade is unavailable.' );
	}
	return $ability;
}

function cmsa_v2_users_rest_call( $method, $path, array $params = array() ) {
	$ability = cmsa_v2_users_rest_ability( $method, $path );
	$input = array( 'path' => $path, 'params' => $params );
	if ( true !== $ability->check_permissions( $input ) ) {
		cmsa_v2_users_rest_fail( sprintf( 'Permission failed for %1$s %2$s.', $method, $path ) );
	}
	$result = $ability->execute( $input );
	if ( is_wp_error( $result ) ) {
		cmsa_v2_users_rest_fail( sprintf( '%1$s %2$s failed: %3$s %4$s', $method, $path, $result->get_error_code(), $result->get_error_message() ) );
	}
	if ( ! is_array( $result ) || (int) ( $result['status'] ?? 0 ) < 200 || (int) ( $result['status'] ?? 0 ) >= 300 || ! array_key_exists( 'data', $result ) ) {
		cmsa_v2_users_rest_fail( sprintf( '%1$s %2$s returned an invalid facade response.', $method, $path ) );
	}
	return $result['data'];
}

wp_set_current_user( 1 );
$suffix   = substr( hash( 'sha256', wp_generate_uuid4() ), 0, 10 );
$username = 'cmsa_user_' . $suffix;
$email    = 'cmsa-user-' . $suffix . '@example.invalid';
$password = wp_generate_password( 32, true, true );

foreach ( array(
	array( 'POST', '/wp/v2/users' ),
	array( 'GET', '/wp/v2/users/2' ),
	array( 'POST', '/wp/v2/users/2' ),
	array( 'DELETE', '/wp/v2/users/2' ),
) as $route ) {
	cmsa_v2_users_rest_ability( $route[0], $route[1] );
}

$created = cmsa_v2_users_rest_call(
	'POST',
	'/wp/v2/users',
	array(
		'username'    => $username,
		'email'       => $email,
		'password'    => $password,
		'roles'       => array( 'subscriber' ),
		'name'        => 'CMSA User Probe ' . $suffix,
		'description' => 'Disposable CMSA user administration probe.',
	)
);
$cmsa_users_rest_user_id = (int) ( $created['id'] ?? 0 );
cmsa_v2_users_rest_assert( $cmsa_users_rest_user_id > 1, 'Core REST user creation did not return a disposable user ID.' );

$stored = get_userdata( $cmsa_users_rest_user_id );
cmsa_v2_users_rest_assert( $stored instanceof WP_User, 'Created disposable user was not persisted.' );
cmsa_v2_users_rest_assert( $username === $stored->user_login && $email === $stored->user_email, 'Created disposable user identity did not persist.' );
cmsa_v2_users_rest_assert( in_array( 'subscriber', (array) $stored->roles, true ), 'Created disposable user role did not persist.' );

$read = cmsa_v2_users_rest_call( 'GET', '/wp/v2/users/' . $cmsa_users_rest_user_id, array( 'context' => 'edit' ) );
cmsa_v2_users_rest_assert( $cmsa_users_rest_user_id === (int) ( $read['id'] ?? 0 ), 'Core REST user readback failed.' );
cmsa_v2_users_rest_assert( in_array( 'subscriber', (array) ( $read['roles'] ?? array() ), true ), 'Core REST user readback omitted the subscriber role.' );

$updated = cmsa_v2_users_rest_call(
	'POST',
	'/wp/v2/users/' . $cmsa_users_rest_user_id,
	array(
		'name'        => 'CMSA User Updated ' . $suffix,
		'description' => 'Updated disposable CMSA user administration probe.',
		'roles'       => array( 'author' ),
	)
);
cmsa_v2_users_rest_assert( $cmsa_users_rest_user_id === (int) ( $updated['id'] ?? 0 ), 'Core REST user update returned the wrong user.' );
cmsa_v2_users_rest_assert( in_array( 'author', (array) ( $updated['roles'] ?? array() ), true ), 'Core REST user role update did not return author.' );

$stored = get_userdata( $cmsa_users_rest_user_id );
cmsa_v2_users_rest_assert( $stored instanceof WP_User && in_array( 'author', (array) $stored->roles, true ), 'Core REST user role update did not persist.' );
cmsa_v2_users_rest_assert( 'CMSA User Updated ' . $suffix === $stored->display_name, 'Core REST user display name update did not persist.' );

wp_set_current_user( 0 );
$blocked = cmsa_v2_users_rest_ability( 'POST', '/wp/v2/users' );
cmsa_v2_users_rest_assert(
	false === $blocked->check_permissions(
		array(
			'path'   => '/wp/v2/users',
			'params' => array(
				'username' => 'blocked-' . $suffix,
				'email'    => 'blocked-' . $suffix . '@example.invalid',
				'password' => $password,
			),
		)
	),
	'Anonymous user creation was not blocked.'
);
wp_set_current_user( 1 );

$deleted = cmsa_v2_users_rest_call(
	'DELETE',
	'/wp/v2/users/' . $cmsa_users_rest_user_id,
	array(
		'force'    => true,
		'reassign' => 1,
	)
);
cmsa_v2_users_rest_assert( ! empty( $deleted['deleted'] ), 'Core REST user deletion was not confirmed.' );
cmsa_v2_users_rest_assert( ! ( get_userdata( $cmsa_users_rest_user_id ) instanceof WP_User ), 'Deleted disposable user still exists.' );
$cmsa_users_rest_user_id = 0;

cmsa_v2_users_rest_assert( get_userdata( 1 ) instanceof WP_User, 'Administrator account changed during the user administration probe.' );

echo "cmsa-v2-core-users-rest: PASS users=crud roles=assignment_update dynamic_bridge=verified admin_boundary=verified final_state=restored\n";
exit( 0 );
