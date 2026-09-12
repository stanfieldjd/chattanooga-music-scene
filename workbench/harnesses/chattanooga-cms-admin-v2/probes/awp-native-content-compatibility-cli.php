<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

$cmsa_awp_probe_id = 0;

function cmsa_v2_awp_native_cleanup() {
	global $cmsa_awp_probe_id;
	if ( $cmsa_awp_probe_id > 0 && get_post( $cmsa_awp_probe_id ) instanceof WP_Post ) {
		wp_delete_post( $cmsa_awp_probe_id, true );
	}
	$cmsa_awp_probe_id = 0;
}

function cmsa_v2_awp_native_fail( $message ) {
	cmsa_v2_awp_native_cleanup();
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_awp_native_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_v2_awp_native_fail( $message );
	}
}

wp_set_current_user( 1 );

cmsa_v2_awp_native_assert( post_type_exists( 'awpcp_listing' ), 'AWP Classifieds did not register awpcp_listing.' );
$type = get_post_type_object( 'awpcp_listing' );
cmsa_v2_awp_native_assert( $type instanceof WP_Post_Type, 'AWP Classifieds post type object is unavailable.' );

cmsa_v2_awp_native_assert(
	! function_exists( 'is_plugin_active' ) || ! is_plugin_active( 'miniorange-secure-mcp-server/miniorange-secure-mcp-server.php' ),
	'Third-party miniOrange MCP transport is active in the native-only compatibility environment.'
);

$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
cmsa_v2_awp_native_assert( $catalog instanceof WP_Ability, 'Chattanooga universal catalog is unavailable.' );
$catalog_result = $catalog->execute( array() );
cmsa_v2_awp_native_assert( ! is_wp_error( $catalog_result ) && is_array( $catalog_result['items'] ?? null ), 'Chattanooga universal catalog could not be inspected.' );

$path = '';

if ( ! empty( $type->show_in_rest ) ) {
	$namespace = ! empty( $type->rest_namespace ) ? trim( (string) $type->rest_namespace, '/' ) : 'wp/v2';
	$base      = ! empty( $type->rest_base ) ? trim( (string) $type->rest_base, '/' ) : $type->name;
	$route_prefix = '/' . $namespace . '/' . $base;
	$methods = array();

	foreach ( $catalog_result['items'] as $item ) {
		if ( ! is_array( $item ) || 'rest' !== ( $item['contract'] ?? '' ) ) {
			continue;
		}
		$route = (string) ( $item['route'] ?? '' );
		if ( 0 !== strpos( $route, $route_prefix ) ) {
			continue;
		}
		$methods[] = (string) ( $item['method'] ?? '' );
	}

	cmsa_v2_awp_native_assert( in_array( 'GET', $methods, true ), 'AWP REST content route is not discoverable through the native catalog.' );
	cmsa_v2_awp_native_assert( in_array( 'POST', $methods, true ) || in_array( 'PUT', $methods, true ) || in_array( 'PATCH', $methods, true ), 'AWP REST content route has no discoverable native write path.' );
	$path = 'rest';
} elseif ( ! empty( $type->show_ui ) ) {
	$types_ability = wp_get_ability( 'chattanooga-cms-admin/private-content-types' );
	$query_ability = wp_get_ability( 'chattanooga-cms-admin/private-content-query' );
	$save_ability  = wp_get_ability( 'chattanooga-cms-admin/private-content-save' );
	$get_ability   = wp_get_ability( 'chattanooga-cms-admin/private-content-get' );
	$delete_ability = wp_get_ability( 'chattanooga-cms-admin/private-content-delete' );

	foreach ( array( $types_ability, $query_ability, $save_ability, $get_ability, $delete_ability ) as $ability ) {
		cmsa_v2_awp_native_assert( $ability instanceof WP_Ability, 'Native private-content administration is incomplete for AWP Classifieds.' );
	}

	$types_result = $types_ability->execute( array() );
	cmsa_v2_awp_native_assert( ! is_wp_error( $types_result ) && is_array( $types_result['items'] ?? null ), 'Native private-content inventory failed for AWP Classifieds.' );
	$names = array();
	foreach ( $types_result['items'] as $item ) {
		if ( is_array( $item ) && isset( $item['name'] ) ) {
			$names[] = (string) $item['name'];
		}
	}
	cmsa_v2_awp_native_assert( in_array( 'awpcp_listing', $names, true ), 'AWP Classifieds is not discoverable through native private-content administration.' );

	$query_result = $query_ability->execute(
		array(
			'post_type' => 'awpcp_listing',
			'per_page'  => 5,
			'page'      => 1,
		)
	);
	cmsa_v2_awp_native_assert( ! is_wp_error( $query_result ) && is_array( $query_result['items'] ?? null ), 'Native AWP private-content query failed.' );

	$create = $save_ability->execute(
		array(
			'post_type' => 'awpcp_listing',
			'title'     => 'CMSA AWP Native Compatibility Probe',
			'status'    => 'draft',
			'meta'      => array( 'cmsa_awp_native_probe' => '1' ),
		)
	);
	cmsa_v2_awp_native_assert( ! is_wp_error( $create ) && ! empty( $create['created'] ) && ! empty( $create['item']['id'] ), 'Native AWP private-content create failed.' );
	$cmsa_awp_probe_id = (int) $create['item']['id'];

	$created = get_post( $cmsa_awp_probe_id );
	cmsa_v2_awp_native_assert( $created instanceof WP_Post && 'awpcp_listing' === $created->post_type && 'draft' === $created->post_status, 'Native AWP private-content create did not persist the expected post record.' );
	cmsa_v2_awp_native_assert( '1' === (string) get_post_meta( $cmsa_awp_probe_id, 'cmsa_awp_native_probe', true ), 'Native AWP private-content metadata did not persist.' );

	$read = $get_ability->execute( array( 'post_type' => 'awpcp_listing', 'id' => $cmsa_awp_probe_id ) );
	cmsa_v2_awp_native_assert( ! is_wp_error( $read ) && $cmsa_awp_probe_id === (int) ( $read['item']['id'] ?? 0 ), 'Native AWP private-content read failed.' );

	$update = $save_ability->execute(
		array(
			'post_type' => 'awpcp_listing',
			'id'        => $cmsa_awp_probe_id,
			'title'     => 'CMSA AWP Native Compatibility Updated',
			'meta'      => array( 'cmsa_awp_native_probe' => '2' ),
		)
	);
	cmsa_v2_awp_native_assert( ! is_wp_error( $update ) && ! empty( $update['updated'] ), 'Native AWP private-content update failed.' );
	cmsa_v2_awp_native_assert( '2' === (string) get_post_meta( $cmsa_awp_probe_id, 'cmsa_awp_native_probe', true ), 'Native AWP private-content metadata update did not persist.' );

	$delete = $delete_ability->execute(
		array(
			'post_type' => 'awpcp_listing',
			'id'        => $cmsa_awp_probe_id,
			'force'     => true,
		)
	);
	cmsa_v2_awp_native_assert( ! is_wp_error( $delete ) && ! empty( $delete['deleted'] ), 'Native AWP private-content force delete failed.' );
	cmsa_v2_awp_native_assert( null === get_post( $cmsa_awp_probe_id ), 'Native AWP private-content force delete left the disposable post behind.' );
	$cmsa_awp_probe_id = 0;
	$path = 'private-content';
} else {
	cmsa_v2_awp_native_fail( 'AWP Classifieds exposes neither a REST content route nor an admin-visible post-type path that Chattanooga CMS Admin can administer generically.' );
}

wp_set_current_user( 0 );
cmsa_v2_awp_native_assert( true !== $catalog->check_permissions( array() ), 'Anonymous universal catalog access was not blocked.' );

cmsa_v2_awp_native_cleanup();
echo 'cmsa-v2-awp-native-content: PASS awpcp_post_type=present native_path=' . $path . " third_party_mcp=absent admin_boundary=verified cleanup=verified\n";
exit( 0 );
