<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_v2_awp_fail( $message ) {
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_awp_catalog() {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}
	$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
	if ( ! $catalog instanceof WP_Ability ) {
		cmsa_v2_awp_fail( 'Universal catalog is unavailable.' );
	}
	$result = $catalog->execute( array() );
	if ( is_wp_error( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
		cmsa_v2_awp_fail( 'Universal catalog could not enumerate generic CPT abilities.' );
	}
	$items = $result['items'];
	return $items;
}

function cmsa_v2_awp_ability( $target ) {
	$matches = array();
	foreach ( cmsa_v2_awp_catalog() as $item ) {
		if ( 'ability' === ( $item['contract'] ?? '' ) && $target === ( $item['target'] ?? '' ) && ! empty( $item['bridge'] ) ) {
			$matches[] = (string) $item['bridge'];
		}
	}
	if ( 1 !== count( $matches ) ) {
		cmsa_v2_awp_fail( sprintf( 'Expected one universal facade for %1$s; found %2$d.', $target, count( $matches ) ) );
	}
	$ability = wp_get_ability( $matches[0] );
	if ( ! $ability instanceof WP_Ability ) {
		cmsa_v2_awp_fail( 'Discovered generic CPT facade is unavailable.' );
	}
	return $ability;
}

function cmsa_v2_awp_execute( $target, array $input = array() ) {
	$ability = cmsa_v2_awp_ability( $target );
	$permission = $ability->check_permissions( $input );
	if ( true !== $permission ) {
		cmsa_v2_awp_fail( 'Provider permission was not preserved for ' . $target . '.' );
	}
	$result = $ability->execute( $input );
	if ( is_wp_error( $result ) ) {
		cmsa_v2_awp_fail( sprintf( '%1$s failed: %2$s %3$s', $target, $result->get_error_code(), $result->get_error_message() ) );
	}
	return $result;
}

wp_set_current_user( 1 );

$list_types = cmsa_v2_awp_execute( 'mosmcp/cpt-list-types' );
$types = $list_types['types'] ?? array();
$classified = null;
foreach ( $types as $type ) {
	if ( 'awpcp_listing' === ( $type['post_type'] ?? '' ) ) {
		$classified = $type;
		break;
	}
}
if ( ! is_array( $classified ) ) {
	cmsa_v2_awp_fail( 'AWP Classifieds post type was not discovered through the generic CPT contract.' );
}
if ( empty( $classified['access']['can_create'] ) || empty( $classified['access']['can_edit'] ) ) {
	cmsa_v2_awp_fail( 'Generic CPT discovery did not preserve AWP Classifieds create/edit capability.' );
}

$description = cmsa_v2_awp_execute( 'mosmcp/cpt-describe-type', array( 'post_type' => 'awpcp_listing' ) );
if ( 'awpcp_listing' !== ( $description['post_type'] ?? $description['type']['post_type'] ?? null ) ) {
	cmsa_v2_awp_fail( 'Generic CPT description did not describe the classifieds type.' );
}

$created = cmsa_v2_awp_execute(
	'mosmcp/cpt-create',
	array(
		'post_type' => 'awpcp_listing',
		'title'     => 'CMSA v2 disposable classified',
		'content'   => 'Disposable classified compatibility record.',
		'status'    => 'draft',
	)
);
$id = (int) ( $created['id'] ?? $created['ID'] ?? 0 );
if ( $id < 1 ) {
	cmsa_v2_awp_fail( 'Generic CPT create did not return a classified ID.' );
}

$post = get_post( $id );
if ( ! $post instanceof WP_Post || 'awpcp_listing' !== $post->post_type || 'draft' !== $post->post_status ) {
	wp_delete_post( $id, true );
	cmsa_v2_awp_fail( 'Created classified did not persist as the registered AWP post type.' );
}

$read = cmsa_v2_awp_execute( 'mosmcp/cpt-get', array( 'post_type' => 'awpcp_listing', 'id' => $id ) );
if ( $id !== (int) ( $read['id'] ?? $read['ID'] ?? 0 ) ) {
	wp_delete_post( $id, true );
	cmsa_v2_awp_fail( 'Generic CPT read did not return the created classified.' );
}

cmsa_v2_awp_execute( 'mosmcp/cpt-trash', array( 'post_type' => 'awpcp_listing', 'id' => $id ) );
$post = get_post( $id );
if ( ! $post instanceof WP_Post || 'trash' !== $post->post_status ) {
	wp_delete_post( $id, true );
	cmsa_v2_awp_fail( 'Generic CPT trash did not move the classified to trash.' );
}

cmsa_v2_awp_execute( 'mosmcp/cpt-restore', array( 'post_type' => 'awpcp_listing', 'id' => $id ) );
$post = get_post( $id );
if ( ! $post instanceof WP_Post || 'trash' === $post->post_status ) {
	wp_delete_post( $id, true );
	cmsa_v2_awp_fail( 'Generic CPT restore did not restore the classified.' );
}

$create_facade = cmsa_v2_awp_ability( 'mosmcp/cpt-create' );
wp_set_current_user( 0 );
$anonymous_input = array(
	'post_type' => 'awpcp_listing',
	'title'     => 'Anonymous classified',
	'content'   => '',
	'status'    => 'draft',
);
if ( false !== $create_facade->check_permissions( $anonymous_input ) ) {
	wp_set_current_user( 1 );
	wp_delete_post( $id, true );
	cmsa_v2_awp_fail( 'Anonymous classified creation was not blocked.' );
}
wp_set_current_user( 1 );
wp_delete_post( $id, true );

echo "cmsa-v2-awp-classifieds: PASS discovery=generic-cpt describe=verified create=verified read=verified trash_restore=verified provider_capabilities=preserved admin_boundary=verified candidate_awp_adapter=absent\n";
exit( 0 );
