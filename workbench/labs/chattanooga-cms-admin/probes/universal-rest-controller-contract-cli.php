<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this test runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
delete_option( 'cmsa_universal_fixture_one_rest_contract' );
delete_option( 'cmsa_universal_fixture_two_rest_contract' );

$post_type = get_post_type_object( 'fixture_one_record' );
$taxonomy  = get_taxonomy( 'fixture_two_label' );
if ( ! $post_type instanceof WP_Post_Type || ! $taxonomy instanceof WP_Taxonomy ) {
	fwrite( STDERR, "Disposable REST-contract resources were not registered.\n" );
	exit( 1 );
}

$post_controller = $post_type->get_rest_controller();
$term_controller = $taxonomy->get_rest_controller();
if ( ! $post_controller instanceof WP_REST_Posts_Controller ) {
	fwrite( STDERR, "Fixture post type did not expose the standard WordPress REST posts controller.\n" );
	exit( 1 );
}
if ( ! $term_controller instanceof WP_REST_Terms_Controller ) {
	fwrite( STDERR, "Fixture taxonomy did not expose the standard WordPress REST terms controller.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
$anonymous_post_request = new WP_REST_Request( 'POST', '/wp/v2/fixture_one_record' );
$anonymous_post_request->set_param( 'title', 'Anonymous fixture post' );
$anonymous_post_request->set_param( 'status', 'draft' );
$anonymous_post_request->set_param( 'fixture_contract', 'approved' );
if ( true === $post_controller->create_item_permissions_check( $anonymous_post_request ) ) {
	fwrite( STDERR, "Standard post controller allowed anonymous creation.\n" );
	exit( 1 );
}
$anonymous_term_request = new WP_REST_Request( 'POST', '/wp/v2/fixture_two_label' );
$anonymous_term_request->set_param( 'name', 'Anonymous fixture term' );
$anonymous_term_request->set_param( 'fixture_contract', 'approved' );
if ( true === $term_controller->create_item_permissions_check( $anonymous_term_request ) ) {
	fwrite( STDERR, "Standard term controller allowed anonymous creation.\n" );
	exit( 1 );
}
wp_set_current_user( 1 );

$invalid_post_request = new WP_REST_Request( 'POST', '/wp/v2/fixture_one_record' );
$invalid_post_request->set_param( 'title', 'Rejected fixture post' );
$invalid_post_request->set_param( 'status', 'draft' );
if ( true !== $post_controller->create_item_permissions_check( $invalid_post_request ) ) {
	fwrite( STDERR, "Administrator did not pass standard post-controller creation permissions.\n" );
	exit( 1 );
}
$invalid_post = $post_controller->create_item( $invalid_post_request );
if ( ! is_wp_error( $invalid_post ) || 'fixture_one_rest_contract' !== $invalid_post->get_error_code() ) {
	fwrite( STDERR, "Post REST pre-insert contract was not authoritative.\n" );
	exit( 1 );
}
if ( get_posts( array( 'post_type' => 'fixture_one_record', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1 ) ) ) {
	fwrite( STDERR, "Rejected post REST request mutated fixture state.\n" );
	exit( 1 );
}

$post_create_request = new WP_REST_Request( 'POST', '/wp/v2/fixture_one_record' );
$post_create_request->set_param( 'title', 'REST contract fixture post' );
$post_create_request->set_param( 'content', 'REST contract fixture content.' );
$post_create_request->set_param( 'status', 'draft' );
$post_create_request->set_param( 'fixture_contract', 'approved' );
if ( true !== $post_controller->create_item_permissions_check( $post_create_request ) ) {
	fwrite( STDERR, "Approved post request failed standard creation permissions.\n" );
	exit( 1 );
}
$post_response = $post_controller->create_item( $post_create_request );
if ( is_wp_error( $post_response ) || ! $post_response instanceof WP_REST_Response ) {
	fwrite( STDERR, "Approved post request failed through the standard REST controller.\n" );
	exit( 1 );
}
$post_data = $post_response->get_data();
$post_id = isset( $post_data['id'] ) ? (int) $post_data['id'] : 0;
$post = $post_id > 0 ? get_post( $post_id ) : null;
if ( ! $post instanceof WP_Post || 'fixture_one_record' !== $post->post_type || 'REST contract fixture post' !== $post->post_title || 'draft' !== $post->post_status ) {
	fwrite( STDERR, "Standard post controller did not create the exact fixture state.\n" );
	goto cleanup_failure;
}
$post_contract = get_option( 'cmsa_universal_fixture_one_rest_contract', array() );
if ( $post_id !== (int) ( $post_contract['id'] ?? 0 ) || true !== ( $post_contract['creating'] ?? null ) || 'approved' !== ( $post_contract['contract'] ?? '' ) ) {
	fwrite( STDERR, "Post REST after-insert contract did not run after creation.\n" );
	goto cleanup_failure;
}

$invalid_post_update = new WP_REST_Request( 'POST', '/wp/v2/fixture_one_record/' . $post_id );
$invalid_post_update->set_param( 'id', $post_id );
$invalid_post_update->set_param( 'title', 'Rejected fixture update' );
if ( true !== $post_controller->update_item_permissions_check( $invalid_post_update ) ) {
	fwrite( STDERR, "Administrator did not pass standard post-controller update permissions.\n" );
	goto cleanup_failure;
}
$invalid_post_update_result = $post_controller->update_item( $invalid_post_update );
if ( ! is_wp_error( $invalid_post_update_result ) || 'fixture_one_rest_contract' !== $invalid_post_update_result->get_error_code() || 'REST contract fixture post' !== get_post( $post_id )->post_title ) {
	fwrite( STDERR, "Post REST pre-insert contract failed to block an invalid update.\n" );
	goto cleanup_failure;
}

$post_update_request = new WP_REST_Request( 'POST', '/wp/v2/fixture_one_record/' . $post_id );
$post_update_request->set_param( 'id', $post_id );
$post_update_request->set_param( 'title', 'REST contract fixture post updated' );
$post_update_request->set_param( 'fixture_contract', 'approved' );
if ( true !== $post_controller->update_item_permissions_check( $post_update_request ) ) {
	fwrite( STDERR, "Approved post update failed standard permissions.\n" );
	goto cleanup_failure;
}
$post_update_response = $post_controller->update_item( $post_update_request );
if ( is_wp_error( $post_update_response ) || ! $post_update_response instanceof WP_REST_Response || 'REST contract fixture post updated' !== get_post( $post_id )->post_title ) {
	fwrite( STDERR, "Approved post update failed through the standard REST controller.\n" );
	goto cleanup_failure;
}
$post_contract = get_option( 'cmsa_universal_fixture_one_rest_contract', array() );
if ( $post_id !== (int) ( $post_contract['id'] ?? 0 ) || false !== ( $post_contract['creating'] ?? null ) || 'approved' !== ( $post_contract['contract'] ?? '' ) ) {
	fwrite( STDERR, "Post REST after-insert contract did not run after update.\n" );
	goto cleanup_failure;
}

$invalid_term_request = new WP_REST_Request( 'POST', '/wp/v2/fixture_two_label' );
$invalid_term_request->set_param( 'name', 'Rejected fixture label' );
if ( true !== $term_controller->create_item_permissions_check( $invalid_term_request ) ) {
	fwrite( STDERR, "Administrator did not pass standard term-controller creation permissions.\n" );
	goto cleanup_failure;
}
$invalid_term = $term_controller->create_item( $invalid_term_request );
if ( ! is_wp_error( $invalid_term ) || 'fixture_two_rest_contract' !== $invalid_term->get_error_code() ) {
	fwrite( STDERR, "Taxonomy REST pre-insert contract was not authoritative.\n" );
	goto cleanup_failure;
}
if ( get_term_by( 'name', 'Rejected fixture label', 'fixture_two_label' ) ) {
	fwrite( STDERR, "Rejected taxonomy REST request mutated fixture state.\n" );
	goto cleanup_failure;
}

$term_create_request = new WP_REST_Request( 'POST', '/wp/v2/fixture_two_label' );
$term_create_request->set_param( 'name', 'REST contract fixture label' );
$term_create_request->set_param( 'description', 'REST contract fixture description.' );
$term_create_request->set_param( 'fixture_contract', 'approved' );
if ( true !== $term_controller->create_item_permissions_check( $term_create_request ) ) {
	fwrite( STDERR, "Approved taxonomy request failed standard creation permissions.\n" );
	goto cleanup_failure;
}
$term_response = $term_controller->create_item( $term_create_request );
if ( is_wp_error( $term_response ) || ! $term_response instanceof WP_REST_Response ) {
	fwrite( STDERR, "Approved taxonomy request failed through the standard REST controller.\n" );
	goto cleanup_failure;
}
$term_data = $term_response->get_data();
$term_id = isset( $term_data['id'] ) ? (int) $term_data['id'] : 0;
$term = $term_id > 0 ? get_term( $term_id, 'fixture_two_label' ) : null;
if ( ! $term instanceof WP_Term || 'REST contract fixture label' !== $term->name || 'REST contract fixture description.' !== $term->description ) {
	fwrite( STDERR, "Standard term controller did not create the exact fixture state.\n" );
	goto cleanup_failure;
}
$term_contract = get_option( 'cmsa_universal_fixture_two_rest_contract', array() );
if ( $term_id !== (int) ( $term_contract['id'] ?? 0 ) || true !== ( $term_contract['creating'] ?? null ) || 'approved' !== ( $term_contract['contract'] ?? '' ) ) {
	fwrite( STDERR, "Taxonomy REST after-insert contract did not run after creation.\n" );
	goto cleanup_failure;
}

$invalid_term_update = new WP_REST_Request( 'POST', '/wp/v2/fixture_two_label/' . $term_id );
$invalid_term_update->set_param( 'id', $term_id );
$invalid_term_update->set_param( 'name', 'Rejected fixture label update' );
if ( true !== $term_controller->update_item_permissions_check( $invalid_term_update ) ) {
	fwrite( STDERR, "Administrator did not pass standard term-controller update permissions.\n" );
	goto cleanup_failure;
}
$invalid_term_update_result = $term_controller->update_item( $invalid_term_update );
$term_after_reject = get_term( $term_id, 'fixture_two_label' );
if ( ! is_wp_error( $invalid_term_update_result ) || 'fixture_two_rest_contract' !== $invalid_term_update_result->get_error_code() || ! $term_after_reject instanceof WP_Term || 'REST contract fixture label' !== $term_after_reject->name ) {
	fwrite( STDERR, "Taxonomy REST pre-insert contract failed to block an invalid update.\n" );
	goto cleanup_failure;
}

$term_update_request = new WP_REST_Request( 'POST', '/wp/v2/fixture_two_label/' . $term_id );
$term_update_request->set_param( 'id', $term_id );
$term_update_request->set_param( 'name', 'REST contract fixture label updated' );
$term_update_request->set_param( 'fixture_contract', 'approved' );
if ( true !== $term_controller->update_item_permissions_check( $term_update_request ) ) {
	fwrite( STDERR, "Approved taxonomy update failed standard permissions.\n" );
	goto cleanup_failure;
}
$term_update_response = $term_controller->update_item( $term_update_request );
$term_after_update = get_term( $term_id, 'fixture_two_label' );
if ( is_wp_error( $term_update_response ) || ! $term_update_response instanceof WP_REST_Response || ! $term_after_update instanceof WP_Term || 'REST contract fixture label updated' !== $term_after_update->name ) {
	fwrite( STDERR, "Approved taxonomy update failed through the standard REST controller.\n" );
	goto cleanup_failure;
}
$term_contract = get_option( 'cmsa_universal_fixture_two_rest_contract', array() );
if ( $term_id !== (int) ( $term_contract['id'] ?? 0 ) || false !== ( $term_contract['creating'] ?? null ) || 'approved' !== ( $term_contract['contract'] ?? '' ) ) {
	fwrite( STDERR, "Taxonomy REST after-insert contract did not run after update.\n" );
	goto cleanup_failure;
}

wp_delete_term( $term_id, 'fixture_two_label' );
wp_delete_post( $post_id, true );
delete_option( 'cmsa_universal_fixture_one_rest_contract' );
delete_option( 'cmsa_universal_fixture_two_rest_contract' );

echo 'universal-rest-controller-contract-cli: PASS post_permissions=preserved post_preinsert=authoritative post_afterinsert=preserved taxonomy_permissions=preserved taxonomy_preinsert=authoritative taxonomy_afterinsert=preserved generic_mutation_ability=not_admitted' . "\n";
exit( 0 );

cleanup_failure:
wp_set_current_user( 1 );
if ( isset( $term_id ) && $term_id > 0 ) {
	wp_delete_term( $term_id, 'fixture_two_label' );
}
if ( isset( $post_id ) && $post_id > 0 ) {
	wp_delete_post( $post_id, true );
}
delete_option( 'cmsa_universal_fixture_one_rest_contract' );
delete_option( 'cmsa_universal_fixture_two_rest_contract' );
exit( 1 );
