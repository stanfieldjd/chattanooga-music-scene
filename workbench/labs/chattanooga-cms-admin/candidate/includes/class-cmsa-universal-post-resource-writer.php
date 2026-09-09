<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded mutation engine for WordPress's built-in post/page REST contracts.
 *
 * This writer intentionally does not mutate taxonomies, metadata, statuses,
 * plugin custom post types, or arbitrary REST fields. Those surfaces require
 * their own verified contracts.
 */
final class CMSA_Universal_Post_Resource_Writer {
	public function write( array $input ) {
		$operation = isset( $input['operation'] ) ? sanitize_key( (string) $input['operation'] ) : '';

		if ( 'create_draft' === $operation ) {
			return $this->create_draft( $input );
		}
		if ( 'update' === $operation ) {
			return $this->update( $input );
		}

		return new WP_Error( 'cmsa_universal_post_operation', 'Operation must be create_draft or update.' );
	}

	private function create_draft( array $input ) {
		$config = $this->resource_config( isset( $input['resource'] ) ? $input['resource'] : '' );
		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$title = isset( $input['title'] ) ? trim( (string) $input['title'] ) : '';
		if ( '' === $title ) {
			return new WP_Error( 'cmsa_universal_post_title', 'A non-empty title is required for draft creation.' );
		}

		$request = new WP_REST_Request( 'POST', $this->route( $config ) );
		$request->set_param( 'status', 'draft' );
		$request->set_param( 'title', $title );
		$request->set_param( 'content', isset( $input['content'] ) ? (string) $input['content'] : '' );
		$request->set_param( 'excerpt', isset( $input['excerpt'] ) ? (string) $input['excerpt'] : '' );

		if ( isset( $input['slug'] ) && '' !== trim( (string) $input['slug'] ) ) {
			$request->set_param( 'slug', sanitize_title( (string) $input['slug'] ) );
		}

		if ( isset( $input['parent_id'] ) && (int) $input['parent_id'] > 0 ) {
			if ( 'page' !== $config['name'] ) {
				return new WP_Error( 'cmsa_universal_post_parent', 'parent_id is supported only for pages.' );
			}
			$parent = get_post( (int) $input['parent_id'] );
			if ( ! $parent instanceof WP_Post || 'page' !== $parent->post_type || ! current_user_can( 'edit_post', $parent->ID ) ) {
				return new WP_Error( 'cmsa_universal_post_parent', 'Requested parent page is not available for editing.' );
			}
			$request->set_param( 'parent', (int) $parent->ID );
		}

		$permission = $config['controller']->create_item_permissions_check( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( true !== $permission ) {
			return new WP_Error( 'cmsa_universal_post_permission', 'Current user cannot create this standard post resource.' );
		}

		$response = $config['controller']->create_item( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$id = $this->response_id( $response );
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof WP_Post || ! $this->matches_created_draft( $post, $config['name'], $request ) ) {
			$rolled_back = false;
			if ( $id > 0 ) {
				$deleted = wp_delete_post( $id, true );
				$rolled_back = $deleted instanceof WP_Post && null === get_post( $id );
			}
			return new WP_Error(
				'cmsa_universal_post_create_verify',
				'Draft creation did not match the requested standard post state.',
				array( 'rolled_back' => $rolled_back )
			);
		}

		CMSA_Audit::record( 'universal-create-' . $config['name'] . '-draft', (string) $post->ID, 'success' );

		return array(
			'created' => true,
			'item'    => $this->normalize( $post ),
		);
	}

	private function update( array $input ) {
		$config = $this->resource_config( isset( $input['resource'] ) ? $input['resource'] : '' );
		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$post = $this->checked_post( $config['name'], $id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$expected_state = isset( $input['expected_state_token'] ) ? trim( (string) $input['expected_state_token'] ) : '';
		$current_state  = $this->state_token( $post );
		if ( '' === $expected_state || ! hash_equals( $current_state, $expected_state ) ) {
			return new WP_Error(
				'cmsa_universal_post_conflict',
				'Standard post resource changed after inspection; refresh its state before updating.',
				array( 'current_state_token' => $current_state )
			);
		}

		$changed = array();
		foreach ( array( 'title', 'content', 'excerpt' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$changed[ $field ] = (string) $input[ $field ];
			}
		}
		if ( ! $changed ) {
			return new WP_Error( 'cmsa_universal_post_no_changes', 'At least one revision-backed field is required for update.' );
		}

		$revision_id = $this->create_rollback_revision( $post );
		if ( is_wp_error( $revision_id ) ) {
			return $revision_id;
		}

		$before  = $this->revision_state( $post );
		$request = new WP_REST_Request( 'POST', $this->route( $config ) . '/' . $post->ID );
		$request->set_param( 'id', (int) $post->ID );
		foreach ( $changed as $field => $value ) {
			$request->set_param( $field, $value );
		}

		$permission = $config['controller']->update_item_permissions_check( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( true !== $permission ) {
			return new WP_Error( 'cmsa_universal_post_permission', 'Current user cannot update this standard post resource.' );
		}

		$response = $config['controller']->update_item( $request );
		if ( is_wp_error( $response ) ) {
			$after_error = get_post( $post->ID );
			if ( $after_error instanceof WP_Post && ! $this->matches_revision_state( $after_error, $before ) ) {
				$rolled_back = $this->restore_rollback_revision( $revision_id, $before );
				return new WP_Error(
					'cmsa_universal_post_update_error_state',
					'Standard post controller returned an error after changing resource state.',
					array(
						'cause'                => $response->get_error_code(),
						'rollback_revision_id' => $revision_id,
						'rolled_back'          => $rolled_back,
					)
				);
			}
			return $response;
		}

		$after = get_post( $post->ID );
		if ( ! $after instanceof WP_Post || $config['name'] !== $after->post_type || $post->post_status !== $after->post_status || ! $this->matches_changed_fields( $after, $changed ) ) {
			$rolled_back = $this->restore_rollback_revision( $revision_id, $before );
			return new WP_Error(
				'cmsa_universal_post_update_verify',
				'Standard post update verification failed.',
				array(
					'rollback_revision_id' => $revision_id,
					'rolled_back'          => $rolled_back,
				)
			);
		}

		CMSA_Audit::record(
			'universal-update-' . $config['name'],
			(string) $post->ID,
			'success',
			array( 'rollback_revision_id' => $revision_id )
		);

		return array(
			'updated'              => true,
			'rollback_revision_id' => (int) $revision_id,
			'item'                 => $this->normalize( $after ),
		);
	}

	private function resource_config( $resource ) {
		$name = sanitize_key( (string) $resource );
		if ( ! in_array( $name, array( 'post', 'page' ), true ) ) {
			return new WP_Error( 'cmsa_universal_post_resource_not_supported', 'Universal post mutation is currently admitted only for WordPress posts and pages.' );
		}

		$post_type = get_post_type_object( $name );
		if ( ! $post_type instanceof WP_Post_Type || empty( $post_type->_builtin ) || empty( $post_type->show_in_rest ) || ! $post_type->map_meta_cap ) {
			return new WP_Error( 'cmsa_universal_post_resource_not_supported', 'Requested post resource does not satisfy the admitted WordPress mutation contract.' );
		}

		$controller = $post_type->get_rest_controller();
		if ( ! $controller instanceof WP_REST_Posts_Controller ) {
			return new WP_Error( 'cmsa_universal_post_controller', 'Requested post resource does not expose the standard WordPress posts REST controller.' );
		}

		return array(
			'name'          => $name,
			'post_type'     => $post_type,
			'controller'    => $controller,
			'rest_base'     => $post_type->rest_base ? (string) $post_type->rest_base : $name,
			'rest_namespace'=> $post_type->rest_namespace ? (string) $post_type->rest_namespace : 'wp/v2',
		);
	}

	private function checked_post( $resource, $id ) {
		$id = (int) $id;
		if ( $id < 1 ) {
			return new WP_Error( 'cmsa_universal_post_id', 'A valid post resource ID is required.' );
		}
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || $resource !== $post->post_type ) {
			return new WP_Error( 'cmsa_universal_post_not_found', 'Requested post resource was not found in the expected type.' );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'cmsa_universal_post_permission', 'Current user cannot edit this post resource.' );
		}
		return $post;
	}

	private function create_rollback_revision( WP_Post $post ) {
		if ( ! function_exists( 'wp_revisions_enabled' ) || ! wp_revisions_enabled( $post ) ) {
			return new WP_Error( 'cmsa_universal_post_revisions_disabled', 'Standard post mutation requires WordPress revisions to be enabled.' );
		}

		$revision_id = wp_save_post_revision( $post->ID );
		if ( is_wp_error( $revision_id ) ) {
			return new WP_Error( 'cmsa_universal_post_revision_create', 'Could not create a rollback revision.' );
		}
		if ( $revision_id ) {
			return (int) $revision_id;
		}

		$revisions = wp_get_post_revisions(
			$post->ID,
			array(
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'check_enabled'  => false,
			)
		);
		if ( ! $revisions ) {
			return new WP_Error( 'cmsa_universal_post_revision_create', 'Could not establish a rollback revision.' );
		}
		$revision = reset( $revisions );
		if ( ! $revision instanceof WP_Post || ! $this->matches_revision_state( $revision, $this->revision_state( $post ) ) ) {
			return new WP_Error( 'cmsa_universal_post_revision_create', 'Existing revision does not match current content state.' );
		}
		return (int) $revision->ID;
	}

	private function restore_rollback_revision( $revision_id, array $before ) {
		$restored = wp_restore_post_revision( (int) $revision_id );
		if ( false === $restored || null === $restored ) {
			return false;
		}
		$post = get_post( (int) $before['id'] );
		return $post instanceof WP_Post && $this->matches_revision_state( $post, $before );
	}

	private function matches_created_draft( WP_Post $post, $resource, WP_REST_Request $request ) {
		if ( $resource !== $post->post_type || 'draft' !== $post->post_status ) {
			return false;
		}
		if ( (string) $request->get_param( 'title' ) !== $post->post_title ) {
			return false;
		}
		if ( (string) $request->get_param( 'content' ) !== $post->post_content ) {
			return false;
		}
		if ( (string) $request->get_param( 'excerpt' ) !== $post->post_excerpt ) {
			return false;
		}
		$slug = (string) $request->get_param( 'slug' );
		if ( '' !== $slug && $slug !== $post->post_name ) {
			return false;
		}
		if ( 'page' === $resource && $request->has_param( 'parent' ) && (int) $request->get_param( 'parent' ) !== (int) $post->post_parent ) {
			return false;
		}
		return true;
	}

	private function matches_changed_fields( WP_Post $post, array $changed ) {
		$map = array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
		);
		foreach ( $changed as $field => $value ) {
			$property = $map[ $field ];
			if ( (string) $value !== (string) $post->{$property} ) {
				return false;
			}
		}
		return true;
	}

	private function revision_state( WP_Post $post ) {
		return array(
			'id'      => (int) $post->ID,
			'type'    => (string) $post->post_type,
			'status'  => (string) $post->post_status,
			'title'   => (string) $post->post_title,
			'content' => (string) $post->post_content,
			'excerpt' => (string) $post->post_excerpt,
		);
	}

	private function matches_revision_state( WP_Post $post, array $before ) {
		return (int) $before['id'] === (int) $post->ID
			&& (string) $before['type'] === (string) $post->post_type
			&& (string) $before['status'] === (string) $post->post_status
			&& (string) $before['title'] === (string) $post->post_title
			&& (string) $before['content'] === (string) $post->post_content
			&& (string) $before['excerpt'] === (string) $post->post_excerpt;
	}

	private function state_token( WP_Post $post ) {
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

	private function normalize( WP_Post $post ) {
		return array(
			'id'           => (int) $post->ID,
			'post_type'    => (string) $post->post_type,
			'status'       => (string) $post->post_status,
			'title'        => (string) $post->post_title,
			'content'      => (string) $post->post_content,
			'excerpt'      => (string) $post->post_excerpt,
			'slug'         => (string) $post->post_name,
			'parent_id'    => (int) $post->post_parent,
			'modified_gmt' => (string) $post->post_modified_gmt,
			'state_token'  => $this->state_token( $post ),
		);
	}

	private function response_id( $response ) {
		if ( is_object( $response ) && method_exists( $response, 'get_data' ) ) {
			$data = $response->get_data();
			if ( is_array( $data ) && ! empty( $data['id'] ) ) {
				return (int) $data['id'];
			}
		}
		return 0;
	}

	private function route( array $config ) {
		return '/' . trim( $config['rest_namespace'], '/' ) . '/' . trim( $config['rest_base'], '/' );
	}
}
