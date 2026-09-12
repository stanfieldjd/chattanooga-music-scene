<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

$cmsa_private_probe_type = 'cmsa_private_probe';
$cmsa_private_probe_ids  = array();

function cmsa_v2_private_content_cleanup() {
	global $cmsa_private_probe_type, $cmsa_private_probe_ids;

	foreach ( array_unique( array_map( 'intval', $cmsa_private_probe_ids ) ) as $post_id ) {
		if ( $post_id > 0 && get_post( $post_id ) instanceof WP_Post ) {
			wp_delete_post( $post_id, true );
		}
	}
	$cmsa_private_probe_ids = array();

	if ( post_type_exists( $cmsa_private_probe_type ) && function_exists( 'unregister_post_type' ) ) {
		unregister_post_type( $cmsa_private_probe_type );
	}
}

function cmsa_v2_private_content_fail( $message ) {
	cmsa_v2_private_content_cleanup();
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_private_content_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_v2_private_content_fail( $message );
	}
}

register_post_type(
	$cmsa_private_probe_type,
	array(
		'label'        => 'CMSA Private Probe',
		'public'       => false,
		'show_ui'      => true,
		'show_in_rest' => false,
		'supports'     => array( 'title', 'editor', 'excerpt' ),
	)
);

cmsa_v2_private_content_assert( post_type_exists( $cmsa_private_probe_type ), 'Disposable private post type was not registered.' );
$type_object = get_post_type_object( $cmsa_private_probe_type );
cmsa_v2_private_content_assert( $type_object instanceof WP_Post_Type, 'Disposable private post type object is unavailable.' );
cmsa_v2_private_content_assert( true === (bool) $type_object->show_ui && false === (bool) $type_object->show_in_rest, 'Disposable post type does not represent the private admin boundary.' );

$ability_names = array(
	'types'  => 'chattanooga-cms-admin/private-content-types',
	'query'  => 'chattanooga-cms-admin/private-content-query',
	'get'    => 'chattanooga-cms-admin/private-content-get',
	'save'   => 'chattanooga-cms-admin/private-content-save',
	'delete' => 'chattanooga-cms-admin/private-content-delete',
);
$abilities = array();
foreach ( $ability_names as $key => $ability_name ) {
	$ability = wp_get_ability( $ability_name );
	cmsa_v2_private_content_assert( $ability instanceof WP_Ability, 'Private content ability is unavailable: ' . $ability_name );
	$abilities[ $key ] = $ability;
}

wp_set_current_user( 1 );
foreach ( $abilities as $key => $ability ) {
	$permission = $ability->check_permissions( array() );
	cmsa_v2_private_content_assert( true === $permission, 'Administrator permission check failed for private content ability: ' . $key );
}

wp_set_current_user( 0 );
foreach ( $abilities as $key => $ability ) {
	$permission = $ability->check_permissions( array() );
	cmsa_v2_private_content_assert( true !== $permission, 'Anonymous permission was granted for private content ability: ' . $key );
}
wp_set_current_user( 1 );

$type_result = $abilities['types']->execute( array() );
cmsa_v2_private_content_assert( ! is_wp_error( $type_result ) && is_array( $type_result['items'] ?? null ), 'Private content type inventory failed.' );
$type_names = array();
foreach ( $type_result['items'] as $item ) {
	if ( is_array( $item ) && isset( $item['name'] ) ) {
		$type_names[] = (string) $item['name'];
	}
}
cmsa_v2_private_content_assert( in_array( $cmsa_private_probe_type, $type_names, true ), 'Private content inventory omitted the disposable private post type.' );
cmsa_v2_private_content_assert( ! in_array( 'post', $type_names, true ), 'REST-public core posts leaked into the private content inventory.' );

$create = $abilities['save']->execute(
	array(
		'post_type' => $cmsa_private_probe_type,
		'title'     => 'CMSA Private Content Probe',
		'content'   => 'Disposable private content body.',
		'excerpt'   => 'Disposable private content excerpt.',
		'meta'      => array(
			'cmsa_probe_scalar' => 'alpha',
			'cmsa_probe_array'  => array( 'one', 'two' ),
			'cmsa_probe_remove' => 'remove-me',
		),
	)
);
cmsa_v2_private_content_assert( ! is_wp_error( $create ), 'Private content create failed: ' . ( is_wp_error( $create ) ? $create->get_error_message() : 'invalid result' ) );
cmsa_v2_private_content_assert( ! empty( $create['created'] ) && empty( $create['updated'] ) && ! empty( $create['item']['id'] ), 'Private content create returned an invalid result.' );
$created_id = (int) $create['item']['id'];
$cmsa_private_probe_ids[] = $created_id;

$created = get_post( $created_id );
cmsa_v2_private_content_assert( $created instanceof WP_Post && $cmsa_private_probe_type === $created->post_type, 'Created private content record identity is invalid.' );
cmsa_v2_private_content_assert( 'draft' === $created->post_status, 'Private content create did not default to draft.' );
cmsa_v2_private_content_assert( 'CMSA Private Content Probe' === $created->post_title, 'Private content title did not persist.' );
cmsa_v2_private_content_assert( 'alpha' === get_post_meta( $created_id, 'cmsa_probe_scalar', true ), 'Private content scalar metadata did not persist.' );
cmsa_v2_private_content_assert( array( 'one', 'two' ) === get_post_meta( $created_id, 'cmsa_probe_array', true ), 'Private content array metadata did not persist.' );

$query = $abilities['query']->execute(
	array(
		'post_type' => $cmsa_private_probe_type,
		'status'    => 'draft',
		'search'    => 'CMSA Private Content Probe',
		'per_page'  => 10,
		'page'      => 1,
	)
);
cmsa_v2_private_content_assert( ! is_wp_error( $query ) && is_array( $query['items'] ?? null ), 'Private content query failed.' );
$query_ids = array_map(
	static function ( $item ) {
		return (int) ( $item['id'] ?? 0 );
	},
	$query['items']
);
cmsa_v2_private_content_assert( in_array( $created_id, $query_ids, true ), 'Private content query did not return the created record.' );

$read = $abilities['get']->execute( array( 'post_type' => $cmsa_private_probe_type, 'id' => $created_id ) );
cmsa_v2_private_content_assert( ! is_wp_error( $read ) && $created_id === (int) ( $read['item']['id'] ?? 0 ), 'Private content read failed.' );
cmsa_v2_private_content_assert( isset( $read['item']['meta']['cmsa_probe_scalar'] ), 'Private content read omitted post metadata.' );

$update = $abilities['save']->execute(
	array(
		'post_type'  => $cmsa_private_probe_type,
		'id'         => $created_id,
		'title'      => 'CMSA Private Content Updated',
		'content'    => 'Updated disposable private content body.',
		'slug'       => 'cmsa-private-content-updated',
		'menu_order' => 7,
		'meta'       => array(
			'cmsa_probe_scalar' => 'beta',
			'cmsa_probe_added'  => 'new-value',
		),
		'meta_delete' => array( 'cmsa_probe_remove' ),
	)
);
cmsa_v2_private_content_assert( ! is_wp_error( $update ), 'Private content update failed: ' . ( is_wp_error( $update ) ? $update->get_error_message() : 'invalid result' ) );
cmsa_v2_private_content_assert( empty( $update['created'] ) && ! empty( $update['updated'] ), 'Private content update returned an invalid result.' );
$updated = get_post( $created_id );
cmsa_v2_private_content_assert( $updated instanceof WP_Post && 'CMSA Private Content Updated' === $updated->post_title, 'Private content update title did not persist.' );
cmsa_v2_private_content_assert( 'cmsa-private-content-updated' === $updated->post_name && 7 === (int) $updated->menu_order, 'Private content update fields did not persist.' );
cmsa_v2_private_content_assert( 'beta' === get_post_meta( $created_id, 'cmsa_probe_scalar', true ), 'Private content metadata update did not persist.' );
cmsa_v2_private_content_assert( 'new-value' === get_post_meta( $created_id, 'cmsa_probe_added', true ), 'Private content metadata addition did not persist.' );
cmsa_v2_private_content_assert( ! metadata_exists( 'post', $created_id, 'cmsa_probe_remove' ), 'Private content metadata deletion did not persist.' );

$trash = $abilities['delete']->execute(
	array(
		'post_type' => $cmsa_private_probe_type,
		'id'        => $created_id,
		'force'     => false,
	)
);
cmsa_v2_private_content_assert( ! is_wp_error( $trash ) && ! empty( $trash['trashed'] ) && empty( $trash['deleted'] ), 'Private content trash operation failed.' );
cmsa_v2_private_content_assert( 'trash' === get_post_status( $created_id ), 'Private content record was not moved to trash.' );

$force_create = $abilities['save']->execute(
	array(
		'post_type' => $cmsa_private_probe_type,
		'title'     => 'CMSA Private Force Delete Probe',
	)
);
cmsa_v2_private_content_assert( ! is_wp_error( $force_create ) && ! empty( $force_create['item']['id'] ), 'Private content force-delete fixture could not be created.' );
$force_id = (int) $force_create['item']['id'];
$cmsa_private_probe_ids[] = $force_id;

$force_delete = $abilities['delete']->execute(
	array(
		'post_type' => $cmsa_private_probe_type,
		'id'        => $force_id,
		'force'     => true,
	)
);
cmsa_v2_private_content_assert( ! is_wp_error( $force_delete ) && ! empty( $force_delete['deleted'] ) && empty( $force_delete['trashed'] ), 'Private content force-delete operation failed.' );
cmsa_v2_private_content_assert( null === get_post( $force_id ), 'Force-deleted private content record still exists.' );
$cmsa_private_probe_ids = array_values( array_diff( $cmsa_private_probe_ids, array( $force_id ) ) );

wp_delete_post( $created_id, true );
$cmsa_private_probe_ids = array_values( array_diff( $cmsa_private_probe_ids, array( $created_id ) ) );
cmsa_v2_private_content_assert( null === get_post( $created_id ), 'Trashed private content fixture could not be removed during cleanup.' );

if ( function_exists( 'unregister_post_type' ) ) {
	unregister_post_type( $cmsa_private_probe_type );
}
cmsa_v2_private_content_assert( ! post_type_exists( $cmsa_private_probe_type ), 'Disposable private post type cleanup was incomplete.' );

echo "cmsa-v2-private-content: PASS abilities=5 inventory=verified create=verified query=verified read=verified update=verified meta=verified trash=verified force_delete=verified admin_boundary=verified cleanup=verified\n";
exit( 0 );
