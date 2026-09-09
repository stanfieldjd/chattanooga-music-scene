<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared exact-state token contract for standard WordPress post resources.
 */
final class CMSA_Universal_Post_State {
	public static function token( WP_Post $post ) {
		return hash(
			'sha256',
			wp_json_encode(
				array(
					'id'           => (int) $post->ID,
					'post_type'    => (string) $post->post_type,
					'status'       => (string) $post->post_status,
					'title'        => (string) $post->post_title,
					'content'      => (string) $post->post_content,
					'excerpt'      => (string) $post->post_excerpt,
					'slug'         => (string) $post->post_name,
					'parent_id'    => (int) $post->post_parent,
					'modified_gmt' => (string) $post->post_modified_gmt,
				)
			)
		);
	}
}
