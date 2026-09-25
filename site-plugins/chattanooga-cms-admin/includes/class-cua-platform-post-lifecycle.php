<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Post_Lifecycle {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';

	public static function register_ability() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'permanently-delete-post',
			array(
				'label'               => __( 'Permanently delete WordPress post', 'chattanooga-cms-admin' ),
				'description'         => __( 'Permanently deletes one WordPress post of any registered post type using the native WordPress force-delete path. The operation bypasses Trash and cannot be undone.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'WordPress post ID to permanently delete.', 'chattanooga-cms-admin' ),
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'permanently_delete_post' ),
				'permission_callback' => static function ( $input ) {
					$id = is_array( $input ) && isset( $input['id'] ) ? absint( $input['id'] ) : 0;
					return $id > 0 && current_user_can( 'manage_options' ) && current_user_can( 'delete_post', $id );
				},
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
						'open_world'  => false,
					),
				),
			)
		);
	}

	public static function permanently_delete_post( $input ) {
		$id = is_array( $input ) && isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( $id < 1 ) {
			return new WP_Error( 'cmsa_invalid_post_id', 'A valid WordPress post ID is required.' );
		}

		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'cmsa_post_not_found', 'The requested WordPress post does not exist.' );
		}

		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'delete_post', $id ) ) {
			return new WP_Error( 'cmsa_post_delete_forbidden', 'The current administrator is not permitted to delete this post.' );
		}

		$previous = array(
			'id'        => (int) $post->ID,
			'post_type' => (string) $post->post_type,
			'status'    => (string) $post->post_status,
			'title'     => (string) get_the_title( $post ),
			'slug'      => (string) $post->post_name,
		);

		$deleted = wp_delete_post( $id, true );
		if ( ! $deleted instanceof WP_Post ) {
			return new WP_Error( 'cmsa_post_delete_failed', 'WordPress did not permanently delete the requested post.' );
		}
		if ( false !== get_post_status( $id ) || null !== get_post( $id ) ) {
			return new WP_Error( 'cmsa_post_delete_verification_failed', 'The post still exists after WordPress reported permanent deletion.' );
		}

		return array(
			'deleted'   => true,
			'permanent' => true,
			'previous'  => $previous,
		);
	}
}
