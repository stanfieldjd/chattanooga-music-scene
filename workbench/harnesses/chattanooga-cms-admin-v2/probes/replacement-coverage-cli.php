<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_v2_coverage_fail( $message ) {
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

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
	array( 'GET', '#^/wp/v2/navigation(?:/|$)#' ),
	array( 'POST', '#^/wp/v2/navigation(?:/|$)#' ),
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
	'chattanooga-cms-admin/inspect-registered-setting',
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
echo "cmsa-v2-replacement-coverage: PASS system_parity=24 intrinsic=verified legacy_resource_adapters=absent core_rest_contracts=present settings_contract=present admin_boundary=verified\n";
exit( 0 );
