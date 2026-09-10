<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this probe runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$self_plugin = 'chattanooga-cms-admin/chattanooga-cms-admin.php';
if ( ! is_plugin_active( $self_plugin ) ) {
	fwrite( STDERR, "Replacement control plane is not active before the guard probe.\n" );
	exit( 1 );
}

$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
if ( ! $catalog instanceof WP_Ability ) {
	fwrite( STDERR, "Universal catalog ability is unavailable.\n" );
	exit( 1 );
}
$catalog_result = $catalog->execute( array() );
if ( is_wp_error( $catalog_result ) || empty( $catalog_result['items'] ) || ! is_array( $catalog_result['items'] ) ) {
	fwrite( STDERR, "Universal catalog could not be read.\n" );
	exit( 1 );
}

$ability_bridge_name = '';
$rest_post_bridge_name = '';
$rest_post_route = '';
$rest_delete_bridge_name = '';
$rest_delete_route = '';
$self_rest_path = '/wp/v2/plugins/chattanooga-cms-admin/chattanooga-cms-admin';

foreach ( $catalog_result['items'] as $item ) {
	if ( 'ability' === ( $item['contract'] ?? '' ) && 'cua-lab-alpha/mutate-plugin-reference' === ( $item['target'] ?? '' ) ) {
		$ability_bridge_name = (string) ( $item['bridge'] ?? '' );
	}
	if ( 'rest' !== ( $item['contract'] ?? '' ) ) {
		continue;
	}
	$route = (string) ( $item['route'] ?? '' );
	if ( '' === $route || 1 !== preg_match( '@^' . $route . '$@i', $self_rest_path ) ) {
		continue;
	}
	if ( 'POST' === ( $item['method'] ?? '' ) ) {
		$rest_post_bridge_name = (string) ( $item['bridge'] ?? '' );
		$rest_post_route = $route;
	}
	if ( 'DELETE' === ( $item['method'] ?? '' ) ) {
		$rest_delete_bridge_name = (string) ( $item['bridge'] ?? '' );
		$rest_delete_route = $route;
	}
}

if ( '' === $ability_bridge_name || '' === $rest_post_bridge_name || '' === $rest_delete_bridge_name ) {
	fwrite( STDERR, "Required self-mutation facade was not discovered.\n" );
	exit( 1 );
}

delete_option( 'cua_lab_alpha_mutation_count' );
$ability_input = array( 'plugin' => $self_plugin );
$ability_bridge = wp_get_ability( $ability_bridge_name );
$ability_permission = $ability_bridge instanceof WP_Ability ? $ability_bridge->check_permissions( $ability_input ) : false;
if ( ! is_wp_error( $ability_permission ) || 'cmsa_control_plane_self_mutation_forbidden' !== $ability_permission->get_error_code() ) {
	fwrite( STDERR, "Ability facade permission guard did not block self-targeting mutation.\n" );
	exit( 1 );
}
$ability_result = $ability_bridge->execute( $ability_input );
if ( ! is_wp_error( $ability_result ) || 0 !== (int) get_option( 'cua_lab_alpha_mutation_count', 0 ) ) {
	fwrite( STDERR, "Ability facade self-targeting mutation reached provider execution.\n" );
	exit( 1 );
}
$direct_ability_result = CUA_Ability_Bridge::execute_target( 'cua-lab-alpha/mutate-plugin-reference', $ability_input );
if ( ! is_wp_error( $direct_ability_result ) || 'cmsa_control_plane_self_mutation_forbidden' !== $direct_ability_result->get_error_code() || 0 !== (int) get_option( 'cua_lab_alpha_mutation_count', 0 ) ) {
	fwrite( STDERR, "Ability bridge execution guard did not block self-targeting mutation.\n" );
	exit( 1 );
}

$post_input = array( 'path' => $self_rest_path, 'params' => array( 'status' => 'inactive' ) );
$post_bridge = wp_get_ability( $rest_post_bridge_name );
$post_permission = $post_bridge instanceof WP_Ability ? $post_bridge->check_permissions( $post_input ) : false;
if ( ! is_wp_error( $post_permission ) || 'cmsa_control_plane_self_mutation_forbidden' !== $post_permission->get_error_code() ) {
	fwrite( STDERR, "REST facade permission guard did not block self-deactivation.\n" );
	exit( 1 );
}
$post_result = $post_bridge->execute( $post_input );
if ( ! is_wp_error( $post_result ) || ! is_plugin_active( $self_plugin ) ) {
	fwrite( STDERR, "REST facade allowed the replacement plugin to deactivate itself.\n" );
	exit( 1 );
}
$direct_post_result = CUA_REST_Bridge::execute_route( $rest_post_route, 'POST', $post_input );
if ( ! is_wp_error( $direct_post_result ) || 'cmsa_control_plane_self_mutation_forbidden' !== $direct_post_result->get_error_code() || ! is_plugin_active( $self_plugin ) ) {
	fwrite( STDERR, "REST execution guard did not block direct self-deactivation dispatch.\n" );
	exit( 1 );
}

$delete_input = array( 'path' => $self_rest_path, 'params' => array() );
$delete_bridge = wp_get_ability( $rest_delete_bridge_name );
$delete_permission = $delete_bridge instanceof WP_Ability ? $delete_bridge->check_permissions( $delete_input ) : false;
if ( ! is_wp_error( $delete_permission ) || 'cmsa_control_plane_self_mutation_forbidden' !== $delete_permission->get_error_code() ) {
	fwrite( STDERR, "REST facade permission guard did not block self-deletion.\n" );
	exit( 1 );
}
$direct_delete_result = CUA_REST_Bridge::execute_route( $rest_delete_route, 'DELETE', $delete_input );
if ( ! is_wp_error( $direct_delete_result ) || 'cmsa_control_plane_self_mutation_forbidden' !== $direct_delete_result->get_error_code() || ! is_plugin_active( $self_plugin ) || ! file_exists( WP_PLUGIN_DIR . '/' . $self_plugin ) ) {
	fwrite( STDERR, "REST execution guard did not block direct self-deletion dispatch.\n" );
	exit( 1 );
}

delete_option( 'cua_lab_alpha_mutation_count' );
echo 'cmsa-control-plane-guard-cli: PASS ability_permission=blocked ability_execution=blocked rest_permission=blocked rest_execution=blocked self_deactivation=blocked self_deletion=blocked state_unchanged=verified' . "\n";
exit( 0 );
