<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "permission-rest-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$capability_map = array(
	'chattanooga-cms-admin/get-health'                  => 'manage_options',
	'chattanooga-cms-admin/list-updates'                => 'manage_options',
	'chattanooga-cms-admin/list-plugins'                => 'manage_options',
	'chattanooga-cms-admin/list-themes'                 => 'manage_options',
	'chattanooga-cms-admin/list-backups'                => 'manage_options',
	'chattanooga-cms-admin/get-audit-log'               => 'manage_options',
	'chattanooga-cms-admin/create-backup'               => 'manage_options',
	'chattanooga-cms-admin/verify-backup'               => 'manage_options',
	'chattanooga-cms-admin/update-plugin'               => 'update_plugins',
	'chattanooga-cms-admin/update-theme'                => 'update_themes',
	'chattanooga-cms-admin/update-core'                 => 'update_core',
	'chattanooga-cms-admin/install-plugin'              => 'install_plugins',
	'chattanooga-cms-admin/activate-plugin'             => 'activate_plugins',
	'chattanooga-cms-admin/deactivate-plugin'           => 'activate_plugins',
	'chattanooga-cms-admin/delete-plugin'               => 'delete_plugins',
	'chattanooga-cms-admin/set-plugin-auto-update'      => 'update_plugins',
	'chattanooga-cms-admin/install-theme'               => 'install_themes',
	'chattanooga-cms-admin/switch-theme'                => 'switch_themes',
	'chattanooga-cms-admin/delete-theme'                => 'delete_themes',
	'chattanooga-cms-admin/set-theme-auto-update'       => 'update_themes',
	'chattanooga-cms-admin/clear-cache'                 => 'manage_options',
	'chattanooga-cms-admin/restore-component-backup'    => 'manage_options',
	'chattanooga-cms-admin/restore-database-backup'     => 'manage_options',
	'chattanooga-cms-admin/restore-core-backup'         => 'update_core',
);

if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$expected_file = dirname( __DIR__ ) . '/fixtures/expected-abilities.json';
$expected = json_decode( (string) file_get_contents( $expected_file ), true );
if ( ! is_array( $expected ) ) {
	fwrite( STDERR, "permission-rest-cli: expected ability fixture is unreadable.\n" );
	exit( 1 );
}

$expected_sorted = $expected;
$mapped_sorted = array_keys( $capability_map );
sort( $expected_sorted );
sort( $mapped_sorted );
if ( $expected_sorted !== $mapped_sorted ) {
	fwrite( STDERR, "permission-rest-cli: capability map does not exactly match the expected ability manifest.\n" );
	exit( 1 );
}

$abilities = array();
foreach ( $capability_map as $name => $capability ) {
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
	if ( ! $ability ) {
		fwrite( STDERR, "permission-rest-cli: missing registered ability {$name}.\n" );
		exit( 1 );
	}
	if ( ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "permission-rest-cli: WP_Ability lacks check_permissions(); methods=" . implode( ',', get_class_methods( $ability ) ) . "\n" );
		exit( 1 );
	}
	$abilities[ $name ] = $ability;
}

$permission_allowed = static function ( $ability ) {
	$result = $ability->check_permissions();
	return ! is_wp_error( $result ) && true === $result;
};

wp_set_current_user( 0 );
foreach ( $abilities as $name => $ability ) {
	if ( $permission_allowed( $ability ) ) {
		fwrite( STDERR, "permission-rest-cli: anonymous user unexpectedly passed {$name}.\n" );
		exit( 1 );
	}
}

$admin_name = 'cmsa_admin_' . strtolower( wp_generate_password( 8, false, false ) );
$admin_id = wp_create_user( $admin_name, wp_generate_password( 24, true, true ), $admin_name . '@example.invalid' );
if ( is_wp_error( $admin_id ) ) {
	fwrite( STDERR, "permission-rest-cli: could not create administrator probe user.\n" );
	exit( 1 );
}
$admin = new WP_User( $admin_id );
$admin->set_role( 'administrator' );
wp_set_current_user( $admin_id );
foreach ( $abilities as $name => $ability ) {
	if ( ! $permission_allowed( $ability ) ) {
		fwrite( STDERR, "permission-rest-cli: administrator unexpectedly denied {$name}.\n" );
		exit( 1 );
	}
}

$probe_name = 'cmsa_cap_' . strtolower( wp_generate_password( 8, false, false ) );
$probe_id = wp_create_user( $probe_name, wp_generate_password( 24, true, true ), $probe_name . '@example.invalid' );
if ( is_wp_error( $probe_id ) ) {
	fwrite( STDERR, "permission-rest-cli: could not create capability-isolation probe user.\n" );
	exit( 1 );
}
$probe = new WP_User( $probe_id );
$probe->set_role( 'subscriber' );
$capabilities = array_values( array_unique( array_values( $capability_map ) ) );
foreach ( $capabilities as $capability ) {
	$probe->remove_cap( $capability );
}

foreach ( $capabilities as $granted_capability ) {
	$probe->add_cap( $granted_capability, true );
	clean_user_cache( $probe_id );
	wp_set_current_user( 0 );
	wp_set_current_user( $probe_id );

	foreach ( $abilities as $name => $ability ) {
		$should_allow = $capability_map[ $name ] === $granted_capability;
		$allowed = $permission_allowed( $ability );
		if ( $allowed !== $should_allow ) {
			fwrite(
				STDERR,
				"permission-rest-cli: capability isolation mismatch ability={$name} granted={$granted_capability} required={$capability_map[$name]} allowed=" . ( $allowed ? 'true' : 'false' ) . "\n"
			);
			exit( 1 );
		}
	}

	$probe->remove_cap( $granted_capability );
	clean_user_cache( $probe_id );
}

wp_set_current_user( $admin_id );
if ( ! function_exists( 'rest_get_server' ) || ! function_exists( 'rest_do_request' ) ) {
	fwrite( STDERR, "permission-rest-cli: WordPress REST server functions are unavailable.\n" );
	exit( 1 );
}
$server = rest_get_server();
if ( 0 === did_action( 'rest_api_init' ) ) {
	do_action( 'rest_api_init', $server );
}
$routes = $server->get_routes();
$ability_routes = array();
$collection_routes = array();
foreach ( array_keys( $routes ) as $route ) {
	if ( false !== stripos( $route, 'abilit' ) ) {
		$ability_routes[] = $route;
		if ( false === strpos( $route, '(?P<' ) && preg_match( '#/abilities/?$#', $route ) ) {
			$collection_routes[] = rtrim( $route, '/' );
		}
	}
}
if ( empty( $ability_routes ) ) {
	fwrite( STDERR, "permission-rest-cli: no WordPress Abilities REST infrastructure was registered; REST isolation could not be tested.\n" );
	exit( 1 );
}
if ( empty( $collection_routes ) ) {
	fwrite( STDERR, "permission-rest-cli: no Abilities collection route was found; routes=" . implode( ',', $ability_routes ) . "\n" );
	exit( 1 );
}

$response_contains_candidate = static function ( $value ) use ( &$response_contains_candidate ) {
	if ( is_string( $value ) ) {
		return false !== strpos( $value, 'chattanooga-cms-admin/' );
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && false !== strpos( $key, 'chattanooga-cms-admin/' ) ) {
				return true;
			}
			if ( $response_contains_candidate( $item ) ) {
				return true;
			}
		}
	}
	if ( is_object( $value ) ) {
		return $response_contains_candidate( get_object_vars( $value ) );
	}
	return false;
};

foreach ( $collection_routes as $collection_route ) {
	$get = rest_do_request( new WP_REST_Request( 'GET', $collection_route ) );
	if ( $response_contains_candidate( $get->get_data() ) ) {
		fwrite( STDERR, "permission-rest-cli: candidate ability leaked through REST collection {$collection_route}.\n" );
		exit( 1 );
	}

	$direct_route = $collection_route . '/chattanooga-cms-admin/get-health';
	foreach ( array( 'GET', 'POST' ) as $method ) {
		$response = rest_do_request( new WP_REST_Request( $method, $direct_route ) );
		if ( $response_contains_candidate( $response->get_data() ) ) {
			fwrite( STDERR, "permission-rest-cli: candidate ability leaked through REST {$method} {$direct_route}.\n" );
			exit( 1 );
		}
		if ( 'POST' === $method && $response->get_status() < 400 ) {
			fwrite( STDERR, "permission-rest-cli: candidate read ability was executable through REST POST {$direct_route}.\n" );
			exit( 1 );
		}
	}
}

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_set_current_user( 0 );
wp_delete_user( $probe_id );
wp_delete_user( $admin_id );

echo 'permission-rest-cli: PASS abilities=' . count( $abilities ) . ' isolated_capabilities=' . count( $capabilities ) . ' ability_routes=' . count( $ability_routes ) . "\n";
echo 'permission-rest-cli: routes=' . implode( ',', $ability_routes ) . "\n";
