<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this test runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
delete_option( 'cmsa_universal_post_writer_after_insert' );

$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-cms-admin/write-standard-post-resource' ) : null;
$reader  = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-cms-admin/read-standard-resource' ) : null;
if ( ! $ability instanceof WP_Ability ) {
	fwrite( STDERR, "Universal post writer ability was not registered.\n" );
	exit( 1 );
}
if ( ! $reader instanceof WP_Ability ) {
	fwrite( STDERR, "Universal standard-resource reader ability was not registered.\n" );
	exit( 1 );
}

$meta = $ability->get_meta();
$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();
if ( empty( $meta['public'] ) || empty( $meta['mcp']['public'] ) || ! empty( $meta['show_in_rest'] ) ) {
	fwrite( STDERR, "Universal post writer exposure metadata is incorrect.\n" );
	exit( 1 );
}
if ( false !== ( $annotations['readonly'] ?? null ) || false !== ( $annotations['destructive'] ?? null ) || false !== ( $annotations['idempotent'] ?? null ) ) {
	fwrite( STDERR, "Universal post writer annotations are incorrect.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
$denied = $ability->check_permissions(
	array(
		'resource'  => 'post',
		'operation' => 'create_draft',
		'title'     => 'Denied post',
	)
);
wp_set_current_user( 1 );
$allowed = $ability->check_permissions(
	array(
		'resource'  => 'post',
		'operation' => 'create_draft',
		'title'     => 'Allowed post',
	)
);
if ( false !== $denied || true !== $allowed ) {
	fwrite( STDERR, "Universal post writer outer permission boundary failed.\n" );
	exit( 1 );
}

$writer = new CMSA_Universal_Post_Resource_Writer();
$unsupported_post_type = $writer->write(
	array(
		'resource'  => 'fixture_one_record',
		'operation' => 'create_draft',
		'title'     => 'Plugin CPT must not be generic writable',
	)
);
if ( ! is_wp_error( $unsupported_post_type ) || 'cmsa_universal_post_resource_not_supported' !== $unsupported_post_type->get_error_code() ) {
	fwrite( STDERR, "Plugin custom post type escaped the admitted mutation boundary.\n" );
	exit( 1 );
}
$unsupported_taxonomy = $writer->write(
	array(
		'resource'  => 'fixture_two_label',
		'operation' => 'create_draft',
		'title'     => 'Taxonomy must not be generic writable',
	)
);
if ( ! is_wp_error( $unsupported_taxonomy ) || 'cmsa_universal_post_resource_not_supported' !== $unsupported_taxonomy->get_error_code() ) {
	fwrite( STDERR, "Taxonomy escaped the admitted post-only mutation boundary.\n" );
	exit( 1 );
}

$pre_insert_guard = static function ( $prepared_post, $request ) {
	if ( $request instanceof WP_REST_Request && 'Universal blocked draft' === (string) $request->get_param( 'title' ) ) {
		return new WP_Error( 'cmsa_probe_rest_preinsert_block', 'Probe rejected draft through the standard post REST contract.' );
	}
	return $prepared_post;
};
add_filter( 'rest_pre_insert_post', $pre_insert_guard, 10, 2 );

$after_insert_probe = static function ( $post, $request, $creating ) {
	if ( ! $post instanceof WP_Post || ! $request instanceof WP_REST_Request ) {
		return;
	}
	$title = (string) $request->get_param( 'title' );
	if ( 0 === strpos( $title, 'Universal writer' ) || 'Universal rollback trigger' === $title || 'Universal create rollback trigger' === $title ) {
		update_option(
			'cmsa_universal_post_writer_after_insert',
			array(
				'id'       => (int) $post->ID,
				'creating' => (bool) $creating,
				'title'    => $title,
			),
			false
		);
	}
	if ( 'Universal create rollback trigger' === $title && $creating ) {
		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => 'Probe changed content after REST creation.',
			)
		);
	}
	if ( 'Universal rollback trigger' === $title && ! $creating ) {
		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => 'Probe changed content after REST update.',
			)
		);
	}
};
add_action( 'rest_after_insert_post', $after_insert_probe, 20, 3 );

$blocked = $ability->execute(
	array(
		'resource'  => 'post',
		'operation' => 'create_draft',
		'title'     => 'Universal blocked draft',
		'content'   => 'This must never be stored.',
	)
);
if ( ! is_wp_error( $blocked ) || 'cmsa_probe_rest_preinsert_block' !== $blocked->get_error_code() ) {
	fwrite( STDERR, "Standard post pre-insert validation was not authoritative.\n" );
	goto cleanup_failure;
}
$blocked_ids = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'any',
		'title'          => 'Universal blocked draft',
		'fields'         => 'ids',
		'posts_per_page' => -1,
	)
);
if ( $blocked_ids ) {
	fwrite( STDERR, "Rejected post creation changed persistent state.\n" );
	goto cleanup_failure;
}

$create_rollback = $ability->execute(
	array(
		'resource'  => 'post',
		'operation' => 'create_draft',
		'title'     => 'Universal create rollback trigger',
		'content'   => 'Requested create content.',
	)
);
if ( ! is_wp_error( $create_rollback ) || 'cmsa_universal_post_create_verify' !== $create_rollback->get_error_code() ) {
	fwrite( STDERR, "Create verification failure did not fail closed.\n" );
	goto cleanup_failure;
}
$create_rollback_data = $create_rollback->get_error_data();
if ( empty( $create_rollback_data['rolled_back'] ) ) {
	fwrite( STDERR, "Create verification failure did not report successful rollback.\n" );
	goto cleanup_failure;
}
$rolled_back_ids = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'any',
		'title'          => 'Universal create rollback trigger',
		'fields'         => 'ids',
		'posts_per_page' => -1,
	)
);
if ( $rolled_back_ids ) {
	fwrite( STDERR, "Failed draft creation left a persistent post behind.\n" );
	goto cleanup_failure;
}

$created = $ability->execute(
	array(
		'resource'  => 'post',
		'operation' => 'create_draft',
		'title'     => 'Universal writer post',
		'content'   => 'Initial universal writer content.',
		'excerpt'   => 'Initial universal writer excerpt.',
		'slug'      => 'universal-writer-post',
	)
);
if ( is_wp_error( $created ) || empty( $created['created'] ) || empty( $created['item']['id'] ) || empty( $created['item']['state_token'] ) ) {
	fwrite( STDERR, "Universal post draft creation failed.\n" );
	goto cleanup_failure;
}
$post_id = (int) $created['item']['id'];
$post = get_post( $post_id );
if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'draft' !== $post->post_status || 'Universal writer post' !== $post->post_title || 'universal-writer-post' !== $post->post_name ) {
	fwrite( STDERR, "Universal post draft did not match exact requested state.\n" );
	goto cleanup_failure;
}
$after_create_hook = get_option( 'cmsa_universal_post_writer_after_insert', array() );
if ( $post_id !== (int) ( $after_create_hook['id'] ?? 0 ) || true !== ( $after_create_hook['creating'] ?? null ) ) {
	fwrite( STDERR, "Standard post REST after-insert hook did not run for draft creation.\n" );
	goto cleanup_failure;
}

$post_read = $reader->execute(
	array(
		'kind'      => 'post_type',
		'resource'  => 'post',
		'operation' => 'get',
		'id'        => $post_id,
	)
);
if ( is_wp_error( $post_read ) || empty( $post_read['item']['state_token'] ) || (string) $created['item']['state_token'] !== (string) $post_read['item']['state_token'] ) {
	fwrite( STDERR, "Reader-to-writer post state-token handoff failed after creation.\n" );
	goto cleanup_failure;
}

$stale = $ability->execute(
	array(
		'resource'             => 'post',
		'operation'            => 'update',
		'id'                   => $post_id,
		'expected_state_token' => str_repeat( '0', 64 ),
		'title'                => 'Stale update must not apply',
	)
);
if ( ! is_wp_error( $stale ) || 'cmsa_universal_post_conflict' !== $stale->get_error_code() || 'Universal writer post' !== get_post( $post_id )->post_title ) {
	fwrite( STDERR, "Universal post stale-state conflict boundary failed.\n" );
	goto cleanup_failure;
}

$updated = $ability->execute(
	array(
		'resource'             => 'post',
		'operation'            => 'update',
		'id'                   => $post_id,
		'expected_state_token' => (string) $post_read['item']['state_token'],
		'title'                => 'Universal writer post updated',
		'content'              => 'Updated universal writer content.',
		'excerpt'              => 'Updated universal writer excerpt.',
	)
);
if ( is_wp_error( $updated ) || empty( $updated['updated'] ) || empty( $updated['rollback_revision_id'] ) || empty( $updated['item']['state_token'] ) ) {
	fwrite( STDERR, "Universal post update failed.\n" );
	goto cleanup_failure;
}
$post = get_post( $post_id );
if ( ! $post instanceof WP_Post || 'Universal writer post updated' !== $post->post_title || 'Updated universal writer content.' !== $post->post_content || 'Updated universal writer excerpt.' !== $post->post_excerpt ) {
	fwrite( STDERR, "Universal post update did not match requested revision-backed fields.\n" );
	goto cleanup_failure;
}
$after_update_hook = get_option( 'cmsa_universal_post_writer_after_insert', array() );
if ( $post_id !== (int) ( $after_update_hook['id'] ?? 0 ) || false !== ( $after_update_hook['creating'] ?? null ) ) {
	fwrite( STDERR, "Standard post REST after-insert hook did not run for update.\n" );
	goto cleanup_failure;
}

$post_read_after_update = $reader->execute(
	array(
		'kind'      => 'post_type',
		'resource'  => 'post',
		'operation' => 'get',
		'id'        => $post_id,
	)
);
if ( is_wp_error( $post_read_after_update ) || empty( $post_read_after_update['item']['state_token'] ) || (string) $updated['item']['state_token'] !== (string) $post_read_after_update['item']['state_token'] ) {
	fwrite( STDERR, "Reader-to-writer post state-token handoff failed after update.\n" );
	goto cleanup_failure;
}

$before_rollback = array(
	'title'   => $post->post_title,
	'content' => $post->post_content,
	'excerpt' => $post->post_excerpt,
	'status'  => $post->post_status,
);
$rollback_test = $ability->execute(
	array(
		'resource'             => 'post',
		'operation'            => 'update',
		'id'                   => $post_id,
		'expected_state_token' => (string) $post_read_after_update['item']['state_token'],
		'title'                => 'Universal rollback trigger',
		'content'              => 'Requested content that will be corrupted after REST update.',
		'excerpt'              => 'Requested rollback-test excerpt.',
	)
);
if ( ! is_wp_error( $rollback_test ) || 'cmsa_universal_post_update_verify' !== $rollback_test->get_error_code() ) {
	fwrite( STDERR, "Post-update verification failure did not fail closed.\n" );
	goto cleanup_failure;
}
$rollback_data = $rollback_test->get_error_data();
$post_after_rollback = get_post( $post_id );
if ( empty( $rollback_data['rolled_back'] ) || ! $post_after_rollback instanceof WP_Post || $before_rollback['title'] !== $post_after_rollback->post_title || $before_rollback['content'] !== $post_after_rollback->post_content || $before_rollback['excerpt'] !== $post_after_rollback->post_excerpt || $before_rollback['status'] !== $post_after_rollback->post_status ) {
	fwrite( STDERR, "Universal post update rollback did not restore the exact prior revision-backed state.\n" );
	goto cleanup_failure;
}

$parent = $ability->execute(
	array(
		'resource'  => 'page',
		'operation' => 'create_draft',
		'title'     => 'Universal writer parent page',
	)
);
if ( is_wp_error( $parent ) || empty( $parent['item']['id'] ) ) {
	fwrite( STDERR, "Universal parent page draft creation failed.\n" );
	goto cleanup_failure;
}
$parent_id = (int) $parent['item']['id'];

$child = $ability->execute(
	array(
		'resource'  => 'page',
		'operation' => 'create_draft',
		'title'     => 'Universal writer child page',
		'content'   => 'Universal child page content.',
		'slug'      => 'universal-writer-child-page',
		'parent_id' => $parent_id,
	)
);
if ( is_wp_error( $child ) || empty( $child['item']['id'] ) || empty( $child['item']['state_token'] ) ) {
	fwrite( STDERR, "Universal child page draft creation failed.\n" );
	goto cleanup_failure;
}
$child_id = (int) $child['item']['id'];
$child_post = get_post( $child_id );
if ( ! $child_post instanceof WP_Post || 'page' !== $child_post->post_type || $parent_id !== (int) $child_post->post_parent || 'draft' !== $child_post->post_status ) {
	fwrite( STDERR, "Universal page parent relationship was not preserved.\n" );
	goto cleanup_failure;
}

$child_read = $reader->execute(
	array(
		'kind'      => 'post_type',
		'resource'  => 'page',
		'operation' => 'get',
		'id'        => $child_id,
	)
);
if ( is_wp_error( $child_read ) || empty( $child_read['item']['state_token'] ) || (string) $child['item']['state_token'] !== (string) $child_read['item']['state_token'] ) {
	fwrite( STDERR, "Reader-to-writer page state-token handoff failed.\n" );
	goto cleanup_failure;
}

$child_update = $ability->execute(
	array(
		'resource'             => 'page',
		'operation'            => 'update',
		'id'                   => $child_id,
		'expected_state_token' => (string) $child_read['item']['state_token'],
		'excerpt'              => 'Universal child page excerpt updated.',
	)
);
if ( is_wp_error( $child_update ) || empty( $child_update['updated'] ) || 'Universal child page excerpt updated.' !== get_post( $child_id )->post_excerpt ) {
	fwrite( STDERR, "Universal page update failed.\n" );
	goto cleanup_failure;
}

$post_parent_reject = $writer->write(
	array(
		'resource'  => 'post',
		'operation' => 'create_draft',
		'title'     => 'Post parent must fail',
		'parent_id' => $parent_id,
	)
);
if ( ! is_wp_error( $post_parent_reject ) || 'cmsa_universal_post_parent' !== $post_parent_reject->get_error_code() ) {
	fwrite( STDERR, "Post parent boundary failed.\n" );
	goto cleanup_failure;
}

remove_filter( 'rest_pre_insert_post', $pre_insert_guard, 10 );
remove_action( 'rest_after_insert_post', $after_insert_probe, 20 );
wp_delete_post( $child_id, true );
wp_delete_post( $parent_id, true );
wp_delete_post( $post_id, true );
delete_option( 'cmsa_universal_post_writer_after_insert' );

echo 'universal-post-writer-cli: PASS resources=post,page draft_only=yes update_fields=revision_backed plugin_cpt=blocked taxonomy=blocked permissions=preserved preinsert=authoritative afterinsert=preserved stale_state=blocked reader_writer_state_handoff=verified create_rollback=verified update_rollback=verified' . "\n";
exit( 0 );

cleanup_failure:
remove_filter( 'rest_pre_insert_post', $pre_insert_guard, 10 );
remove_action( 'rest_after_insert_post', $after_insert_probe, 20 );
if ( isset( $child_id ) && $child_id > 0 ) {
	wp_delete_post( $child_id, true );
}
if ( isset( $parent_id ) && $parent_id > 0 ) {
	wp_delete_post( $parent_id, true );
}
if ( isset( $post_id ) && $post_id > 0 ) {
	wp_delete_post( $post_id, true );
}
delete_option( 'cmsa_universal_post_writer_after_insert' );
exit( 1 );
