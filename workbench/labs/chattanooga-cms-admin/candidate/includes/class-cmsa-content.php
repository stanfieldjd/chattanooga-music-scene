<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Content {
	private $allowed_statuses = array( 'any', 'publish', 'draft', 'pending', 'future', 'private', 'trash' );

	public function list_items( $post_type, array $input = array() ) {
		$config = $this->type_config( $post_type );
		if ( is_wp_error( $config ) ) {
			return $config;
		}
		if ( ! current_user_can( $config['edit_cap'] ) ) {
			return new WP_Error( 'cmsa_content_permission', 'Current user cannot inspect this content type.' );
		}

		$page = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 20;
		$status = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'any';
		if ( ! in_array( $status, $this->allowed_statuses, true ) ) {
			return new WP_Error( 'cmsa_content_status', 'Unsupported content status filter.' );
		}

		$args = array(
			'post_type'           => $post_type,
			'post_status'         => $status,
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'orderby'             => 'modified',
			'order'               => 'DESC',
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		if ( ! current_user_can( $config['edit_others_cap'] ) ) {
			$args['author'] = get_current_user_id();
		}

		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post_id ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post && current_user_can( 'edit_post', $post->ID ) ) {
				$items[] = $this->normalize_post( $post, false );
			}
		}

		return array(
			'post_type' => $post_type,
			'page'      => $page,
			'per_page'  => $per_page,
			'total'     => (int) $query->found_posts,
			'pages'     => (int) $query->max_num_pages,
			'items'     => $items,
		);
	}

	public function get_item( $post_type, $id ) {
		$post = $this->checked_post( $post_type, $id, 'edit_post' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		return array( 'item' => $this->normalize_post( $post, true ) );
	}

	public function create_draft( $post_type, array $input ) {
		$config = $this->type_config( $post_type );
		if ( is_wp_error( $config ) ) {
			return $config;
		}
		if ( ! current_user_can( $config['edit_cap'] ) ) {
			return new WP_Error( 'cmsa_content_permission', 'Current user cannot create this content type.' );
		}
		if ( empty( $input['title'] ) || '' === trim( (string) $input['title'] ) ) {
			return new WP_Error( 'cmsa_content_title', 'A non-empty title is required.' );
		}

		$postarr = array(
			'post_type'    => $post_type,
			'post_status'  => 'draft',
			'post_title'   => (string) $input['title'],
			'post_content' => isset( $input['content'] ) ? (string) $input['content'] : '',
			'post_excerpt' => isset( $input['excerpt'] ) ? (string) $input['excerpt'] : '',
		);
		if ( ! empty( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $input['slug'] );
		}
		if ( 'page' === $post_type && ! empty( $input['parent_id'] ) ) {
			$parent = $this->checked_post( 'page', (int) $input['parent_id'], 'edit_post' );
			if ( is_wp_error( $parent ) ) {
				return new WP_Error( 'cmsa_content_parent', 'Requested parent page is not available for editing.' );
			}
			$postarr['post_parent'] = $parent->ID;
		}

		$id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'cmsa_content_create', 'Could not create draft content.' );
		}
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || $post_type !== $post->post_type || 'draft' !== $post->post_status ) {
			return new WP_Error( 'cmsa_content_create_verify', 'Draft content could not be verified after creation.' );
		}

		CMSA_Audit::record( 'create-' . $post_type . '-draft', (string) $id, 'success' );
		return array(
			'created' => true,
			'item'    => $this->normalize_post( $post, true ),
		);
	}

	public function update_item( $post_type, array $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$post = $this->checked_post( $post_type, $id, 'edit_post' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$conflict = $this->check_expected_modified( $post, isset( $input['expected_modified_gmt'] ) ? $input['expected_modified_gmt'] : '' );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}

		$update = array( 'ID' => $post->ID );
		$changed = array();
		$field_map = array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
		);
		foreach ( $field_map as $input_key => $post_key ) {
			if ( array_key_exists( $input_key, $input ) ) {
				$update[ $post_key ] = (string) $input[ $input_key ];
				$changed[ $input_key ] = (string) $input[ $input_key ];
			}
		}
		if ( empty( $changed ) ) {
			return new WP_Error( 'cmsa_content_no_changes', 'At least one editable content field is required.' );
		}

		$revision_id = $this->create_rollback_revision( $post );
		if ( is_wp_error( $revision_id ) ) {
			return $revision_id;
		}

		$result = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $result ) || ! $result ) {
			return new WP_Error(
				'cmsa_content_update',
				'Could not update content.',
				array( 'rollback_revision_id' => $revision_id )
			);
		}

		$after = get_post( $post->ID );
		if ( ! $after instanceof WP_Post || ! $this->matches_changed_fields( $after, $changed ) ) {
			$rollback = wp_restore_post_revision( $revision_id );
			return new WP_Error(
				'cmsa_content_update_verify',
				'Content update verification failed.',
				array(
					'rollback_revision_id' => $revision_id,
					'rolled_back'          => false !== $rollback && null !== $rollback,
				)
			);
		}

		CMSA_Audit::record( 'update-' . $post_type, (string) $post->ID, 'success', array( 'rollback_revision_id' => $revision_id ) );
		return array(
			'updated'              => true,
			'rollback_revision_id' => (int) $revision_id,
			'item'                 => $this->normalize_post( $after, true ),
		);
	}

	public function restore_revision( $post_type, array $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$revision_id = isset( $input['revision_id'] ) ? (int) $input['revision_id'] : 0;
		$post = $this->checked_post( $post_type, $id, 'edit_post' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$conflict = $this->check_expected_modified( $post, isset( $input['expected_modified_gmt'] ) ? $input['expected_modified_gmt'] : '' );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}

		$revision = wp_get_post_revision( $revision_id );
		if ( ! $revision instanceof WP_Post || (int) $revision->post_parent !== $post->ID ) {
			return new WP_Error( 'cmsa_content_revision', 'Requested revision does not belong to this content item.' );
		}
		$rollback_revision_id = $this->create_rollback_revision( $post );
		if ( is_wp_error( $rollback_revision_id ) ) {
			return $rollback_revision_id;
		}

		$restored = wp_restore_post_revision( $revision_id );
		if ( false === $restored || null === $restored ) {
			return new WP_Error(
				'cmsa_content_revision_restore',
				'Could not restore the requested revision.',
				array( 'rollback_revision_id' => $rollback_revision_id )
			);
		}
		$after = get_post( $post->ID );
		if ( ! $after instanceof WP_Post || ! $this->revision_matches_post( $revision, $after ) ) {
			return new WP_Error(
				'cmsa_content_revision_verify',
				'Restored content did not match the requested revision.',
				array( 'rollback_revision_id' => $rollback_revision_id )
			);
		}

		CMSA_Audit::record(
			'restore-' . $post_type . '-revision',
			(string) $post->ID,
			'success',
			array( 'revision_id' => $revision_id, 'rollback_revision_id' => $rollback_revision_id )
		);
		return array(
			'restored'              => true,
			'revision_id'           => $revision_id,
			'rollback_revision_id'  => (int) $rollback_revision_id,
			'item'                  => $this->normalize_post( $after, true ),
		);
	}

	public function trash_item( $post_type, array $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$post = $this->checked_post( $post_type, $id, 'delete_post' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'trash' === $post->post_status ) {
			return new WP_Error( 'cmsa_content_already_trash', 'Content item is already in the trash.' );
		}
		$conflict = $this->check_expected_modified( $post, isset( $input['expected_modified_gmt'] ) ? $input['expected_modified_gmt'] : '' );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}

		$previous_status = $post->post_status;
		$result = wp_trash_post( $post->ID );
		$after = get_post( $post->ID );
		if ( ! $result || ! $after instanceof WP_Post || 'trash' !== $after->post_status ) {
			return new WP_Error( 'cmsa_content_trash', 'Content item could not be moved to the trash.' );
		}

		CMSA_Audit::record( 'trash-' . $post_type, (string) $post->ID, 'success', array( 'previous_status' => $previous_status ) );
		return array(
			'trashed'         => true,
			'previous_status' => $previous_status,
			'item'            => $this->normalize_post( $after, false ),
		);
	}

	public function restore_item( $post_type, array $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$post = $this->checked_post( $post_type, $id, 'delete_post' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'trash' !== $post->post_status ) {
			return new WP_Error( 'cmsa_content_not_trash', 'Content item is not in the trash.' );
		}
		$conflict = $this->check_expected_modified( $post, isset( $input['expected_modified_gmt'] ) ? $input['expected_modified_gmt'] : '' );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}

		$result = wp_untrash_post( $post->ID );
		$after = get_post( $post->ID );
		if ( ! $result || ! $after instanceof WP_Post || 'trash' === $after->post_status ) {
			return new WP_Error( 'cmsa_content_restore', 'Content item could not be restored from the trash.' );
		}

		CMSA_Audit::record( 'restore-' . $post_type, (string) $post->ID, 'success', array( 'status' => $after->post_status ) );
		return array(
			'restored' => true,
			'item'     => $this->normalize_post( $after, true ),
		);
	}

	private function checked_post( $post_type, $id, $meta_cap ) {
		$config = $this->type_config( $post_type );
		if ( is_wp_error( $config ) ) {
			return $config;
		}
		$id = (int) $id;
		if ( $id < 1 ) {
			return new WP_Error( 'cmsa_content_id', 'A valid content ID is required.' );
		}
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || $post_type !== $post->post_type ) {
			return new WP_Error( 'cmsa_content_type', 'Requested content item does not exist in the expected type.' );
		}
		if ( ! current_user_can( $meta_cap, $post->ID ) ) {
			return new WP_Error( 'cmsa_content_permission', 'Current user cannot modify the requested content item.' );
		}
		return $post;
	}

	private function check_expected_modified( WP_Post $post, $expected ) {
		$expected = (string) $expected;
		if ( '' === $expected ) {
			return new WP_Error( 'cmsa_content_expected_modified', 'Expected modified timestamp is required.' );
		}
		if ( $expected !== (string) $post->post_modified_gmt ) {
			return new WP_Error(
				'cmsa_content_conflict',
				'Content changed after it was read; mutation was not attempted.',
				array( 'current_modified_gmt' => (string) $post->post_modified_gmt )
			);
		}
		return true;
	}

	private function create_rollback_revision( WP_Post $post ) {
		if ( ! function_exists( 'wp_revisions_enabled' ) || ! wp_revisions_enabled( $post ) ) {
			return new WP_Error( 'cmsa_content_revisions_disabled', 'Content mutation requires WordPress revisions to be enabled.' );
		}
		$revision_id = wp_save_post_revision( $post->ID );
		if ( is_wp_error( $revision_id ) ) {
			return new WP_Error( 'cmsa_content_revision_create', 'Could not create a rollback revision.' );
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
			return new WP_Error( 'cmsa_content_revision_create', 'Could not establish a rollback revision.' );
		}
		$revision = reset( $revisions );
		if ( ! $revision instanceof WP_Post || ! $this->revision_matches_post( $revision, $post ) ) {
			return new WP_Error( 'cmsa_content_revision_create', 'Existing revision does not match current content state.' );
		}
		return (int) $revision->ID;
	}

	private function matches_changed_fields( WP_Post $post, array $changed ) {
		$map = array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
		);
		foreach ( $changed as $field => $expected ) {
			if ( ! isset( $map[ $field ] ) || (string) $post->{$map[ $field ]} !== (string) $expected ) {
				return false;
			}
		}
		return true;
	}

	private function revision_matches_post( WP_Post $revision, WP_Post $post ) {
		return (string) $revision->post_title === (string) $post->post_title
			&& (string) $revision->post_content === (string) $post->post_content
			&& (string) $revision->post_excerpt === (string) $post->post_excerpt;
	}

	private function normalize_post( WP_Post $post, $include_content ) {
		$item = array(
			'id'           => (int) $post->ID,
			'post_type'    => $post->post_type,
			'status'       => $post->post_status,
			'title'        => $post->post_title,
			'excerpt'      => $post->post_excerpt,
			'slug'         => $post->post_name,
			'parent_id'    => (int) $post->post_parent,
			'date_gmt'     => $post->post_date_gmt,
			'modified_gmt' => $post->post_modified_gmt,
		);
		if ( $include_content ) {
			$item['content'] = $post->post_content;
		}
		return $item;
	}

	private function type_config( $post_type ) {
		if ( 'post' === $post_type ) {
			return array(
				'edit_cap'        => 'edit_posts',
				'edit_others_cap' => 'edit_others_posts',
				'delete_cap'      => 'delete_posts',
			);
		}
		if ( 'page' === $post_type ) {
			return array(
				'edit_cap'        => 'edit_pages',
				'edit_others_cap' => 'edit_others_pages',
				'delete_cap'      => 'delete_pages',
			);
		}
		return new WP_Error( 'cmsa_content_type', 'Only posts and pages are supported by this content service.' );
	}
}
