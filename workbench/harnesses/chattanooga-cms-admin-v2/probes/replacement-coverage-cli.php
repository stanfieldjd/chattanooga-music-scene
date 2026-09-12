<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_v2_coverage_fail( $message ) {
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_coverage_ability_is_bridgeable( $ability ) {
	if ( ! $ability instanceof WP_Ability ) {
		return false;
	}
	$name = $ability->get_name();
	if ( 0 === strpos( $name, 'chattanooga-cms-admin/' ) ) {
		return false;
	}
	$meta = $ability->get_meta();
	if ( isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && array_key_exists( 'public', $meta['mcp'] ) && null !== $meta['mcp']['public'] ) {
		return true === $meta['mcp']['public'];
	}
	return true === ( $meta['public'] ?? false );
}

function cmsa_v2_coverage_rest_methods( $handler ) {
	if ( ! is_array( $handler ) ) {
		return array();
	}
	if ( isset( $handler['show_in_index'] ) && false === $handler['show_in_index'] ) {
		return array();
	}
	if ( empty( $handler['methods'] ) || ! is_array( $handler['methods'] ) ) {
		return array();
	}
	if ( empty( $handler['callback'] ) || ! is_callable( $handler['callback'] ) ) {
		return array();
	}
	if ( empty( $handler['permission_callback'] ) || ! is_callable( $handler['permission_callback'] ) ) {
		return array();
	}
	$methods = array();
	foreach ( array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) as $method ) {
		if ( ! empty( $handler['methods'][ $method ] ) ) {
			$methods[] = $method;
		}
	}
	return $methods;
}

add_action(
	'wp_abilities_api_init',
	static function () {
		wp_register_ability(
			'cmsa-coverage-fixture/late-public-ability',
			array(
				'label'               => 'Late public coverage fixture',
				'description'         => 'Synthetic public ability registered after the normal bridge-registration pass.',
				'category'            => 'chattanooga-cms-admin',
				'input_schema'        => array( 'type' => 'object', 'properties' => array(), 'additionalProperties' => false ),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function () { return array( 'fixture' => true ); },
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => array(
					'public'      => true,
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);
	},
	PHP_INT_MAX
);

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'cmsa-coverage-fixture/v1',
			'/late/(?P<id>[\\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $request ) { return array( 'id' => (int) $request['id'] ); },
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
			)
		);
	},
	PHP_INT_MAX
);

wp_set_current_user( 1 );

$required_platform = array(
	'get-health',
	'list-updates',
	'list-plugins',
	'list-themes',
	'list-backups',
	'get-audit-log',
	'create-backup',
	'verify-backup',
	'update-plugin',
	'update-theme',
	'update-core',
	'install-plugin',
	'activate-plugin',
	'deactivate-plugin',
	'delete-plugin',
	'set-plugin-auto-update',
	'install-theme',
	'switch-theme',
	'delete-theme',
	'set-theme-auto-update',
	'clear-cache',
	'restore-component-backup',
	'restore-database-backup',
	'restore-core-backup',
);

foreach ( $required_platform as $short_name ) {
	$name = 'chattanooga-cms-admin/' . $short_name;
	if ( ! wp_get_ability( $name ) instanceof WP_Ability ) {
		cmsa_v2_coverage_fail( 'Required intrinsic replacement ability is missing: ' . $name );
	}
}

$forbidden_bespoke = array(
	'list-posts', 'get-post', 'create-post-draft', 'update-post',
	'list-pages', 'get-page', 'create-page-draft', 'update-page',
	'list-categories', 'get-category', 'create-category', 'update-category',
	'list-tags', 'get-tag', 'create-tag', 'update-tag',
	'list-navigation-menus', 'get-navigation-menu', 'create-navigation-menu',
	'list-members', 'get-member', 'set-member-roles',
	'list-events', 'get-event', 'create-event', 'update-event',
	'list-locations', 'get-location', 'create-location', 'update-location',
);
foreach ( $forbidden_bespoke as $short_name ) {
	$name = 'chattanooga-cms-admin/' . $short_name;
	if ( wp_get_ability( $name ) instanceof WP_Ability ) {
		cmsa_v2_coverage_fail( 'Legacy bespoke resource wrapper was recreated: ' . $name );
	}
}

$catalog_ability = wp_get_ability( 'chattanooga-cms-admin/catalog' );
if ( ! $catalog_ability instanceof WP_Ability ) {
	cmsa_v2_coverage_fail( 'Universal capability catalog is missing.' );
}
$catalog = $catalog_ability->execute( array() );
if ( is_wp_error( $catalog ) || empty( $catalog['items'] ) || ! is_array( $catalog['items'] ) ) {
	cmsa_v2_coverage_fail( 'Universal capability catalog could not be read.' );
}

$catalog_by_bridge = array();
foreach ( $catalog['items'] as $item ) {
	if ( ! is_array( $item ) || empty( $item['bridge'] ) ) {
		continue;
	}
	$catalog_by_bridge[ (string) $item['bridge'] ] = $item;
}

$expected_ability_bridges = array();
foreach ( wp_get_abilities() as $ability ) {
	if ( ! cmsa_v2_coverage_ability_is_bridgeable( $ability ) ) {
		continue;
	}
	$target_name = $ability->get_name();
	$bridge_name = 'chattanooga-cms-admin/bridge-' . substr( hash( 'sha256', $target_name ), 0, 24 );
	$expected_ability_bridges[ $bridge_name ] = $target_name;
}
foreach ( $expected_ability_bridges as $bridge_name => $target_name ) {
	$item = $catalog_by_bridge[ $bridge_name ] ?? null;
	if ( ! is_array( $item ) || 'ability' !== ( $item['contract'] ?? '' ) || $target_name !== ( $item['target'] ?? '' ) ) {
		cmsa_v2_coverage_fail( 'Universal catalog omitted a live public WordPress ability: ' . $target_name );
	}
}

$server = rest_get_server();
if ( ! $server instanceof WP_REST_Server ) {
	cmsa_v2_coverage_fail( 'WordPress REST server is unavailable for universal contract coverage.' );
}
$expected_rest_bridges = array();
foreach ( $server->get_routes() as $route_regex => $handlers ) {
	if ( ! is_string( $route_regex ) || ! is_array( $handlers ) ) {
		continue;
	}
	foreach ( $handlers as $handler ) {
		foreach ( cmsa_v2_coverage_rest_methods( $handler ) as $method ) {
			$bridge_name = 'chattanooga-cms-admin/rest-' . substr( hash( 'sha256', $method . '|' . $route_regex ), 0, 24 );
			$expected_rest_bridges[ $bridge_name ] = $method . ' ' . $route_regex;
		}
	}
}
foreach ( $expected_rest_bridges as $bridge_name => $target ) {
	$item = $catalog_by_bridge[ $bridge_name ] ?? null;
	if ( ! is_array( $item ) || 'rest' !== ( $item['contract'] ?? '' ) || $target !== ( $item['target'] ?? '' ) ) {
		cmsa_v2_coverage_fail( 'Universal catalog omitted a live bridgeable REST contract: ' . $target );
	}
}

$late_ability_bridge = 'chattanooga-cms-admin/bridge-' . substr( hash( 'sha256', 'cmsa-coverage-fixture/late-public-ability' ), 0, 24 );
if ( ! isset( $catalog_by_bridge[ $late_ability_bridge ] ) ) {
	cmsa_v2_coverage_fail( 'Late public ability fixture was not discovered dynamically.' );
}
$late_rest_bridge = 'chattanooga-cms-admin/rest-' . substr( hash( 'sha256', 'GET|/cmsa-coverage-fixture/v1/late/(?P<id>[\\d]+)' ), 0, 24 );
if ( ! isset( $catalog_by_bridge[ $late_rest_bridge ] ) ) {
	cmsa_v2_coverage_fail( 'Late REST fixture was not discovered dynamically.' );
}

$read_gateway = wp_get_ability( 'chattanooga-cms-admin/read-bridge' );
if ( ! $read_gateway instanceof WP_Ability ) {
	cmsa_v2_coverage_fail( 'Universal read gateway is missing.' );
}
$late_ability_result = $read_gateway->execute(
	array(
		'bridge' => $late_ability_bridge,
		'input'  => array(),
	)
);
if ( is_wp_error( $late_ability_result ) || true !== ( $late_ability_result['result']['fixture'] ?? false ) ) {
	cmsa_v2_coverage_fail( 'Late public ability could not execute through the universal gateway.' );
}
$late_rest_result = $read_gateway->execute(
	array(
		'bridge' => $late_rest_bridge,
		'input'  => array(
			'path'   => '/cmsa-coverage-fixture/v1/late/7',
			'params' => array(),
		),
	)
);
if (
	is_wp_error( $late_rest_result ) ||
	200 !== ( $late_rest_result['result']['status'] ?? null ) ||
	7 !== ( $late_rest_result['result']['data']['id'] ?? null )
) {
	cmsa_v2_coverage_fail( 'Late REST contract could not execute through the universal gateway.' );
}

$required_rest = array(
	array( 'GET', '#^/wp/v2/posts(?:/|$)#' ),
	array( 'POST', '#^/wp/v2/posts(?:/|$)#' ),
	array( 'DELETE', '#^/wp/v2/posts(?:/|$)#' ),
	array( 'GET', '#^/wp/v2/pages(?:/|$)#' ),
	array( 'POST', '#^/wp/v2/pages(?:/|$)#' ),
	array( 'DELETE', '#^/wp/v2/pages(?:/|$)#' ),
	array( 'GET', '#^/wp/v2/categories(?:/|$)#' ),
	array( 'POST', '#^/wp/v2/categories(?:/|$)#' ),
	array( 'DELETE', '#^/wp/v2/categories(?:/|$)#' ),
	array( 'GET', '#^/wp/v2/tags(?:/|$)#' ),
	array( 'POST', '#^/wp/v2/tags(?:/|$)#' ),
	array( 'DELETE', '#^/wp/v2/tags(?:/|$)#' ),
	array( 'GET', '#^/wp/v2/users(?:/|$)#' ),
	array( 'POST', '#^/wp/v2/users(?:/|$)#' ),
	array( 'DELETE', '#^/wp/v2/users(?:/|$)#' ),
	array( 'GET', '#^/wp/v2/menus(?:/|$)#' ),
	array( 'POST', '#^/wp/v2/menus(?:/|$)#' ),
	array( 'DELETE', '#^/wp/v2/menus(?:/|$)#' ),
	array( 'GET', '#^/wp/v2/menu-items(?:/|$)#' ),
	array( 'POST', '#^/wp/v2/menu-items(?:/|$)#' ),
	array( 'DELETE', '#^/wp/v2/menu-items(?:/|$)#' ),
	array( 'GET', '#^/wp/v2/menu-locations(?:/|$)#' ),
);

foreach ( $required_rest as $requirement ) {
	list( $method, $route_pattern ) = $requirement;
	$found = false;
	foreach ( $catalog['items'] as $item ) {
		if ( 'rest' !== ( $item['contract'] ?? '' ) || $method !== ( $item['method'] ?? '' ) ) {
			continue;
		}
		$route = (string) ( $item['route'] ?? '' );
		if ( '' !== $route && preg_match( $route_pattern, $route ) ) {
			$found = true;
			break;
		}
	}
	if ( ! $found ) {
		cmsa_v2_coverage_fail( sprintf( 'Required generic core REST contract is missing: %1$s %2$s', $method, $route_pattern ) );
	}
}

$settings_required = array(
	'chattanooga-cms-admin/list-registered-settings',
	'chattanooga-cms-admin/get-registered-setting',
	'chattanooga-cms-admin/update-registered-setting',
);
foreach ( $settings_required as $name ) {
	if ( ! wp_get_ability( $name ) instanceof WP_Ability ) {
		cmsa_v2_coverage_fail( 'Registered Settings API replacement ability is missing: ' . $name );
	}
}

wp_set_current_user( 0 );
foreach ( array( 'get-health', 'list-plugins', 'list-backups' ) as $short_name ) {
	$ability = wp_get_ability( 'chattanooga-cms-admin/' . $short_name );
	if ( $ability instanceof WP_Ability && false !== $ability->check_permissions( array() ) ) {
		cmsa_v2_coverage_fail( 'Anonymous intrinsic administration was not blocked for ' . $short_name . '.' );
	}
}

wp_set_current_user( 1 );
echo 'cmsa-v2-replacement-coverage: PASS system_parity=24 intrinsic=verified live_public_abilities=' . count( $expected_ability_bridges ) . ' live_rest_contracts=' . count( $expected_rest_bridges ) . " dynamic_gateway_execution=verified settings_contract=present admin_boundary=verified\n";
exit( 0 );
