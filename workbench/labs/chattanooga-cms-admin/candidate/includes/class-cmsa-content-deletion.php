<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Explicit hard-delete contract for core WordPress posts/pages.
 *
 * Permanent deletion is intentionally separated from ordinary content CRUD.
 * An item must already be in trash, still match the caller's exact read state,
 * and carry an explicit destructive confirmation on the invocation.
 */
final class CMSA_Content_Deletion {
	public function delete_permanently( $post_type, array $input ) {
		$config = $this->type_config( $post_type );
		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( $id < 1 ) {
			return new WP_Error( 'cmsa_content_delete_id', 'A valid content ID is required.' );
		}

		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || $post_type !== $post->post_type ) {
			return new WP_Error( 'cmsa_content_delete_type', 'Requested content item does not exist in the expected type.' );
		}
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return new WP_Error( 'cmsa_content_delete_permission', 'Current user cannot permanently delete the requested content item.' );
		}
		if ( 'trash' !== $post->post_status ) {
			return new WP_Error( 'cmsa_content_delete_requires_trash', 'Content must already be in WordPress trash before permanent deletion.' );
		}

		$expected = isset( $input['expected_modified_gmt'] ) ? (string) $input['expected_modified_gmt'] : '';
		if ( '' === $expected ) {
			return new WP_Error( 'cmsa_content_delete_expected_modified', 'Expected modified timestamp is required.' );
		}
		if ( $expected !== (string) $post->post_modified_gmt ) {
			return new WP_Error(
				'cmsa_content_delete_conflict',
				'Content changed after it was read; permanent deletion was not attempted.',
				array( 'current_modified_gmt' => (string) $post->post_modified_gmt )
			);
		}
		if ( empty( $input['confirm_permanent_delete'] ) || true !== (bool) $input['confirm_permanent_delete'] ) {
			return new WP_Error( 'cmsa_content_delete_confirmation', 'Permanent deletion requires explicit confirmation.' );
		}

		$receipt = array(
			'id'           => (int) $post->ID,
			'post_type'    => (string) $post->post_type,
			'status'       => (string) $post->post_status,
			'title'        => (string) $post->post_title,
			'slug'         => (string) $post->post_name,
			'parent_id'    => (int) $post->post_parent,
			'modified_gmt' => (string) $post->post_modified_gmt,
		);

		$deleted = wp_delete_post( $post->ID, true );
		$after = get_post( $post->ID );
		if ( ! $deleted instanceof WP_Post || $after instanceof WP_Post ) {
			return new WP_Error( 'cmsa_content_delete_failed', 'WordPress could not verify permanent deletion of the content item.' );
		}

		CMSA_Audit::record( 'delete-' . $post_type . '-permanently', (string) $post->ID, 'success', array( 'previous_status' => 'trash' ) );
		return array(
			'deleted' => true,
			'item'    => $receipt,
		);
	}

	private function type_config( $post_type ) {
		if ( 'post' === $post_type ) {
			return array( 'delete_cap' => 'delete_posts' );
		}
		if ( 'page' === $post_type ) {
			return array( 'delete_cap' => 'delete_pages' );
		}
		return new WP_Error( 'cmsa_content_delete_type', 'Only posts and pages are supported by the permanent deletion service.' );
	}
}
