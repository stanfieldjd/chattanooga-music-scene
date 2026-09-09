<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this test runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-cms-admin/read-standard-resource' ) : null;
if ( ! $ability instanceof WP_Ability ) {
	fwrite( STDERR, "Universal standard-resource read ability was not registered.\n" );
	exit( 1 );
}

$meta = $ability->get_meta();
$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();
if ( empty( $meta['public'] ) || empty( $meta['mcp']['public'] ) || ! empty( $meta['show_in_rest'] ) ) {
	fwrite( STDERR, "Universal standard-resource ability exposure metadata is incorrect.\n" );
	exit( 1 );
}
if ( true !== ( $annotations['readonly'] ?? null ) || false !== ( $annotations['destructive'] ?? null ) || true !== ( $annotations['idempotent'] ?? null ) ) {
	fwrite( STDERR, "Universal standard-resource ability annotations are incorrect.\n" );
	exit( 1 );
}

$post_id = wp_insert_post(
	array(
		'post_type'    => 'fixture_one_record',
		'post_status'  => 'draft',
		'post_title'   => 'Universal reader fixture record',
		'post_content' => 'Universal reader fixture content.',
		'post_excerpt' => 'Universal reader fixture excerpt.',
	),
	true
);
if ( is_wp_error( $post_id ) || $post_id < 1 ) {
	fwrite( STDERR, "Could not create disposable fixture post.\n" );
	exit( 1 );
}

$other_post_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => 'Universal reader cross-resource control',
	),
	true
);
if ( is_wp_error( $other_post_id ) || $other_post_id < 1 ) {
	wp_delete_post( $post_id, true );
	fwrite( STDERR, "Could not create disposable cross-resource post.\n" );
	exit( 1 );
}

$term_result = wp_insert_term(
	'Universal reader fixture label',
	'fixture_two_label',
	array(
		'slug'        => 'universal-reader-fixture-label',
		'description' => 'Universal reader fixture taxonomy term.',
	)
);
if ( is_wp_error( $term_result ) || empty( $term_result['term_id'] ) ) {
	wp_delete_post( $post_id, true );
	wp_delete_post( $other_post_id, true );
	fwrite( STDERR, "Could not create disposable fixture term.\n" );
	exit( 1 );
}
$term_id = (int) $term_result['term_id'];

$post_before = get_post( $post_id, ARRAY_A );
$term_before = get_term( $term_id, 'fixture_two_label', ARRAY_A );

$post_list = $ability->execute(
	array(
		'kind'      => 'post_type',
		'resource'  => 'fixture_one_record',
		'operation' => 'list',
		'search'    => 'Universal reader fixture record',
		'page'      => 1,
		'per_page'  => 20,
	)
);
if ( is_wp_error( $post_list ) || 'post_type' !== ( $post_list['kind'] ?? '' ) || 'fixture_one_record' !== ( $post_list['resource'] ?? '' ) ) {
	fwrite( STDERR, "Universal post-type list failed.\n" );
	goto cleanup_failure;
}
$post_items = isset( $post_list['items'] ) && is_array( $post_list['items'] ) ? $post_list['items'] : array();
$post_row = null;
foreach ( $post_items as $item ) {
	if ( (int) ( $item['id'] ?? 0 ) === (int) $post_id ) {
		$post_row = $item;
		break;
	}
}
if ( ! is_array( $post_row ) ) {
	fwrite( STDERR, "Disposable post was not returned by universal list.\n" );
	goto cleanup_failure;
}
$post_list_keys = array_keys( $post_row );
sort( $post_list_keys, SORT_STRING );
$expected_post_list_keys = array( 'date_gmt', 'excerpt', 'id', 'modified_gmt', 'parent_id', 'post_type', 'slug', 'state_token', 'status', 'title' );
sort( $expected_post_list_keys, SORT_STRING );
if ( $expected_post_list_keys !== $post_list_keys ) {
	fwrite( STDERR, "Universal post list output escaped its field allowlist.\n" );
	goto cleanup_failure;
}
if ( empty( $post_row['state_token'] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', (string) $post_row['state_token'] ) ) {
	fwrite( STDERR, "Universal post list did not expose a valid exact-state token.\n" );
	goto cleanup_failure;
}

$post_get = $ability->execute(
	array(
		'kind'      => 'post_type',
		'resource'  => 'fixture_one_record',
		'operation' => 'get',
		'id'        => (int) $post_id,
	)
);
if ( is_wp_error( $post_get ) || (int) ( $post_get['item']['id'] ?? 0 ) !== (int) $post_id ) {
	fwrite( STDERR, "Universal post-type get failed.\n" );
	goto cleanup_failure;
}
$post_get_keys = array_keys( $post_get['item'] );
sort( $post_get_keys, SORT_STRING );
$expected_post_get_keys = array( 'content', 'date_gmt', 'excerpt', 'id', 'modified_gmt', 'parent_id', 'post_type', 'slug', 'state_token', 'status', 'title' );
sort( $expected_post_get_keys, SORT_STRING );
if ( $expected_post_get_keys !== $post_get_keys || 'Universal reader fixture content.' !== ( $post_get['item']['content'] ?? '' ) ) {
	fwrite( STDERR, "Universal post get output escaped its field allowlist.\n" );
	goto cleanup_failure;
}
if ( (string) $post_row['state_token'] !== (string) ( $post_get['item']['state_token'] ?? '' ) ) {
	fwrite( STDERR, "Universal post list/get state-token contract is inconsistent.\n" );
	goto cleanup_failure;
}

$cross_resource = $ability->execute(
	array(
		'kind'      => 'post_type',
		'resource'  => 'fixture_one_record',
		'operation' => 'get',
		'id'        => (int) $other_post_id,
	)
);
if ( ! is_wp_error( $cross_resource ) || 'cmsa_universal_resource_record_not_found' !== $cross_resource->get_error_code() ) {
	fwrite( STDERR, "Cross-resource post identity boundary failed.\n" );
	goto cleanup_failure;
}

$hidden_post = $ability->execute(
	array(
		'kind'      => 'post_type',
		'resource'  => 'fixture_one_hidden',
		'operation' => 'list',
	)
);
if ( ! is_wp_error( $hidden_post ) || 'cmsa_universal_resource_not_found' !== $hidden_post->get_error_code() ) {
	fwrite( STDERR, "Hidden post type was readable through the universal reader.\n" );
	goto cleanup_failure;
}

$term_list = $ability->execute(
	array(
		'kind'      => 'taxonomy',
		'resource'  => 'fixture_two_label',
		'operation' => 'list',
		'search'    => 'Universal reader fixture label',
		'page'      => 1,
		'per_page'  => 20,
	)
);
if ( is_wp_error( $term_list ) || 'taxonomy' !== ( $term_list['kind'] ?? '' ) || 'fixture_two_label' !== ( $term_list['resource'] ?? '' ) ) {
	fwrite( STDERR, "Universal taxonomy list failed.\n" );
	goto cleanup_failure;
}
$term_row = null;
foreach ( (array) ( $term_list['items'] ?? array() ) as $item ) {
	if ( (int) ( $item['id'] ?? 0 ) === $term_id ) {
		$term_row = $item;
		break;
	}
}
if ( ! is_array( $term_row ) ) {
	fwrite( STDERR, "Disposable taxonomy term was not returned by universal list.\n" );
	goto cleanup_failure;
}
$term_keys = array_keys( $term_row );
sort( $term_keys, SORT_STRING );
$expected_term_keys = array( 'count', 'description', 'id', 'name', 'parent_id', 'slug', 'taxonomy' );
sort( $expected_term_keys, SORT_STRING );
if ( $expected_term_keys !== $term_keys ) {
	fwrite( STDERR, "Universal taxonomy output escaped its field allowlist.\n" );
	goto cleanup_failure;
}

$term_get = $ability->execute(
	array(
		'kind'      => 'taxonomy',
		'resource'  => 'fixture_two_label',
		'operation' => 'get',
		'id'        => $term_id,
	)
);
if ( is_wp_error( $term_get ) || $term_id !== (int) ( $term_get['item']['id'] ?? 0 ) ) {
	fwrite( STDERR, "Universal taxonomy get failed.\n" );
	goto cleanup_failure;
}

$hidden_taxonomy = $ability->execute(
	array(
		'kind'      => 'taxonomy',
		'resource'  => 'fixture_two_hidden',
		'operation' => 'list',
	)
);
if ( ! is_wp_error( $hidden_taxonomy ) || 'cmsa_universal_resource_not_found' !== $hidden_taxonomy->get_error_code() ) {
	fwrite( STDERR, "Hidden taxonomy was readable through the universal reader.\n" );
	goto cleanup_failure;
}

$post_after = get_post( $post_id, ARRAY_A );
$term_after = get_term( $term_id, 'fixture_two_label', ARRAY_A );
if ( $post_before !== $post_after || $term_before !== $term_after ) {
	fwrite( STDERR, "Universal reader changed fixture state during a read.\n" );
	goto cleanup_failure;
}

wp_set_current_user( 0 );
$denied = $ability->check_permissions(
	array(
		'kind'      => 'post_type',
		'resource'  => 'fixture_one_record',
		'operation' => 'get',
		'id'        => (int) $post_id,
	)
);
wp_set_current_user( 1 );
$allowed = $ability->check_permissions(
	array(
		'kind'      => 'post_type',
		'resource'  => 'fixture_one_record',
		'operation' => 'get',
		'id'        => (int) $post_id,
	)
);
if ( false !== $denied || true !== $allowed ) {
	fwrite( STDERR, "Universal standard-resource ability permission boundary failed.\n" );
	goto cleanup_failure;
}

if ( wp_get_ability( 'chattanooga-cms-admin/write-standard-resource' ) || wp_get_ability( 'chattanooga-cms-admin/mutate-standard-resource' ) || wp_get_ability( 'chattanooga-cms-admin/execute-extension-capability' ) ) {
	fwrite( STDERR, "A prohibited generic mutation or execution proxy was registered.\n" );
	goto cleanup_failure;
}

wp_delete_term( $term_id, 'fixture_two_label' );
wp_delete_post( $post_id, true );
wp_delete_post( $other_post_id, true );

echo 'universal-resource-reader-cli: PASS post_list=bounded post_get=bounded post_state_token=consistent taxonomy_list=bounded taxonomy_get=bounded hidden=blocked cross_resource=blocked permissions=preserved mutation_during_read=absent generic_mutation_proxy=absent' . "\n";
exit( 0 );

cleanup_failure:
wp_set_current_user( 1 );
wp_delete_term( $term_id, 'fixture_two_label' );
wp_delete_post( $post_id, true );
wp_delete_post( $other_post_id, true );
exit( 1 );
