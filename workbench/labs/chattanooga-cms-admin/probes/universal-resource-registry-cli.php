<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this test runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$inspect = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-cms-admin/inspect-resource-registry' ) : null;
if ( ! $inspect instanceof WP_Ability ) {
	fwrite( STDERR, "Universal resource-registry ability was not registered.\n" );
	exit( 1 );
}

$post_types = $inspect->execute(
	array(
		'kind'     => 'post_type',
		'search'   => 'fixture_one',
		'page'     => 1,
		'per_page' => 100,
	)
);
if ( is_wp_error( $post_types ) ) {
	fwrite( STDERR, 'Post-type registry discovery failed: ' . $post_types->get_error_code() . "\n" );
	exit( 1 );
}
if ( 'post_type' !== $post_types['kind'] || 1 !== (int) $post_types['total'] || 1 !== count( $post_types['items'] ) ) {
	fwrite( STDERR, "Post-type registry did not preserve the exposed/hidden boundary.\n" );
	exit( 1 );
}
$post_type = $post_types['items'][0];
if ( 'fixture_one_record' !== $post_type['name'] || true !== $post_type['show_ui'] || true !== $post_type['show_in_rest'] ) {
	fwrite( STDERR, "Exposed fixture post type was not described exactly.\n" );
	exit( 1 );
}
if ( ! in_array( 'title', $post_type['supports'], true ) || ! in_array( 'editor', $post_type['supports'], true ) ) {
	fwrite( STDERR, "Post-type support metadata was not preserved.\n" );
	exit( 1 );
}

$post_allowed = array(
	'kind',
	'name',
	'label',
	'description',
	'public',
	'show_ui',
	'show_in_rest',
	'hierarchical',
	'has_archive',
	'rest_base',
	'map_meta_cap',
	'supports',
	'taxonomies',
	'capabilities',
);
$post_keys = array_keys( $post_type );
sort( $post_keys, SORT_STRING );
sort( $post_allowed, SORT_STRING );
if ( $post_keys !== $post_allowed ) {
	fwrite( STDERR, "Post-type discovery exposed fields outside the bounded contract.\n" );
	exit( 1 );
}

$post_exact = $inspect->execute( array( 'kind' => 'post_type', 'name' => 'fixture_one_record' ) );
if ( is_wp_error( $post_exact ) || empty( $post_exact['item'] ) || 'fixture_one_record' !== $post_exact['item']['name'] ) {
	fwrite( STDERR, "Exact post-type inspection failed.\n" );
	exit( 1 );
}
$post_hidden = $inspect->execute( array( 'kind' => 'post_type', 'name' => 'fixture_one_hidden' ) );
if ( ! is_wp_error( $post_hidden ) || 'cmsa_universal_resource_not_found' !== $post_hidden->get_error_code() ) {
	fwrite( STDERR, "Hidden post type was exposed through the universal registry.\n" );
	exit( 1 );
}

$taxonomies = $inspect->execute(
	array(
		'kind'     => 'taxonomy',
		'search'   => 'fixture_two',
		'page'     => 1,
		'per_page' => 100,
	)
);
if ( is_wp_error( $taxonomies ) ) {
	fwrite( STDERR, 'Taxonomy registry discovery failed: ' . $taxonomies->get_error_code() . "\n" );
	exit( 1 );
}
if ( 'taxonomy' !== $taxonomies['kind'] || 1 !== (int) $taxonomies['total'] || 1 !== count( $taxonomies['items'] ) ) {
	fwrite( STDERR, "Taxonomy registry did not preserve the exposed/hidden boundary.\n" );
	exit( 1 );
}
$taxonomy = $taxonomies['items'][0];
if ( 'fixture_two_label' !== $taxonomy['name'] || true !== $taxonomy['show_ui'] || true !== $taxonomy['show_in_rest'] ) {
	fwrite( STDERR, "Exposed fixture taxonomy was not described exactly.\n" );
	exit( 1 );
}
if ( ! in_array( 'post', $taxonomy['object_types'], true ) ) {
	fwrite( STDERR, "Taxonomy object-type relationship was not preserved.\n" );
	exit( 1 );
}

$taxonomy_allowed = array(
	'kind',
	'name',
	'label',
	'description',
	'public',
	'show_ui',
	'show_in_rest',
	'hierarchical',
	'rest_base',
	'object_types',
	'capabilities',
);
$taxonomy_keys = array_keys( $taxonomy );
sort( $taxonomy_keys, SORT_STRING );
sort( $taxonomy_allowed, SORT_STRING );
if ( $taxonomy_keys !== $taxonomy_allowed ) {
	fwrite( STDERR, "Taxonomy discovery exposed fields outside the bounded contract.\n" );
	exit( 1 );
}

$taxonomy_exact = $inspect->execute( array( 'kind' => 'taxonomy', 'name' => 'fixture_two_label' ) );
if ( is_wp_error( $taxonomy_exact ) || empty( $taxonomy_exact['item'] ) || 'fixture_two_label' !== $taxonomy_exact['item']['name'] ) {
	fwrite( STDERR, "Exact taxonomy inspection failed.\n" );
	exit( 1 );
}
$taxonomy_hidden = $inspect->execute( array( 'kind' => 'taxonomy', 'name' => 'fixture_two_hidden' ) );
if ( ! is_wp_error( $taxonomy_hidden ) || 'cmsa_universal_resource_not_found' !== $taxonomy_hidden->get_error_code() ) {
	fwrite( STDERR, "Hidden taxonomy was exposed through the universal registry.\n" );
	exit( 1 );
}

if ( function_exists( 'wp_get_ability' ) && wp_get_ability( 'chattanooga-cms-admin/mutate-resource-registry' ) ) {
	fwrite( STDERR, "A generic resource mutation proxy exists and is not admitted by this increment.\n" );
	exit( 1 );
}

echo 'universal-resource-registry-cli: PASS post_types=1 taxonomies=1 hidden=2 bounded_metadata=yes generic_mutation=absent' . "\n";
