<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Private_Content {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX   = 'chattanooga-cms-admin/';
	const MAX_QUERY_ITEMS = 100;
	const MAX_META_KEYS   = 200;

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::register_ability(
			'private-content-types',
			__( 'List private admin content types', 'chattanooga-cms-admin' ),
			__( 'Lists registered post types that have a WordPress administration UI but are not exposed through the WordPress REST API.', 'chattanooga-cms-admin' ),
			array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
			),
			array( __CLASS__, 'list_types' ),
			true,
			false,
			true
		);

		self::register_ability(
			'private-content-query',
			__( 'Query private admin content', 'chattanooga-cms-admin' ),
			__( 'Queries records from one registered admin-visible post type that is not available through the WordPress REST API.', 'chattanooga-cms-admin' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_type' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 64 ),
					'status'    => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 32, 'default' => 'any' ),
					'search'    => array( 'type' => 'string', 'maxLength' => 512 ),
					'per_page'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_QUERY_ITEMS, 'default' => 20 ),
					'page'      => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
					'parent_id' => array( 'type' => 'integer', 'minimum' => 0 ),
				),
				'required'             => array( 'post_type' ),
				'additionalProperties' => false,
			),
			array( __CLASS__, 'query_content' ),
			true,
			false,
			true
		);

		self::register_ability(
			'private-content-get',
			__( 'Read private admin content', 'chattanooga-cms-admin' ),
			__( 'Reads one record, including its post metadata, from an admin-visible post type that is not available through the WordPress REST API.', 'chattanooga-cms-admin' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_type' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 64 ),
					'id'        => array( 'type' => 'integer', 'minimum' => 1 ),
				),
				'required'             => array( 'post_type', 'id' ),
				'additionalProperties' => false,
			),
			array( __CLASS__, 'get_content' ),
			true,
			false,
			true
		);

		self::register_ability(
			'private-content-save',
			__( 'Create or update private admin content', 'chattanooga-cms-admin' ),
			__( 'Creates or updates one record in an admin-visible post type that is not available through the WordPress REST API, with bounded post fields and metadata changes.', 'chattanooga-cms-admin' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_type'     => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 64 ),
					'id'            => array( 'type' => 'integer', 'minimum' => 1 ),
					'title'         => array( 'type' => 'string' ),
					'content'       => array( 'type' => 'string' ),
					'excerpt'       => array( 'type' => 'string' ),
					'status'        => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 32 ),
					'slug'          => array( 'type' => 'string', 'maxLength' => 200 ),
					'parent_id'     => array( 'type' => 'integer', 'minimum' => 0 ),
					'menu_order'    => array( 'type' => 'integer' ),
					'comment_status'=> array( 'type' => 'string', 'enum' => array( 'open', 'closed' ) ),
					'ping_status'   => array( 'type' => 'string', 'enum' => array( 'open', 'closed' ) ),
					'meta'          => array( 'type' => 'object' ),
					'meta_delete'   => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 191 ),
						'maxItems' => self::MAX_META_KEYS,
					),
				),
				'required'             => array( 'post_type' ),
				'additionalProperties' => false,
			),
			array( __CLASS__, 'save_content' ),
			false,
			false,
			false
		);

		self::register_ability(
			'private-content-delete',
			__( 'Delete private admin content', 'chattanooga-cms-admin' ),
			__( 'Trashes or permanently deletes one record from an admin-visible post type that is not available through the WordPress REST API.', 'chattanooga-cms-admin' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'post_type' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 64 ),
					'id'        => array( 'type' => 'integer', 'minimum' => 1 ),
					'force'     => array( 'type' => 'boolean', 'default' => false ),
				),
				'required'             => array( 'post_type', 'id' ),
				'additionalProperties' => false,
			),
			array( __CLASS__, 'delete_content' ),
			false,
			true,
			false
		);
	}

	private static function register_ability( $suffix, $label, $description, array $input_schema, $callback, $readonly, $destructive, $idempotent ) {
		wp_register_ability(
			self::PREFIX . $suffix,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => self::CATEGORY,
				'input_schema'        => $input_schema,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => $callback,
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => (bool) $readonly,
						'destructive' => (bool) $destructive,
						'idempotent'  => (bool) $idempotent,
					),
				),
			)
		);
	}

	public static function list_types( $input = array() ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'cmsa_private_content_permission', 'Administrator authority is required.' );
		}

		$items = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( ! $type instanceof WP_Post_Type || ! self::is_private_admin_type( $type ) ) {
				continue;
			}
			$items[] = self::normalize_type( $type );
		}

		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		return array( 'items' => $items, 'count' => count( $items ) );
	}

	public static function query_content( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'cmsa_private_content_input', 'Query input must be an object.' );
		}
		$type = self::resolve_type( $input['post_type'] ?? '' );
		if ( is_wp_error( $type ) ) {
			return $type;
		}
		if ( ! self::can_edit_type( $type ) ) {
			return new WP_Error( 'cmsa_private_content_permission', 'The current user cannot administer this content type.' );
		}

		$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'any';
		if ( 'any' !== $status && ! get_post_status_object( $status ) ) {
			return new WP_Error( 'cmsa_private_content_status', 'The requested post status is not registered.' );
		}

		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 20;
		$page     = isset( $input['page'] ) ? (int) $input['page'] : 1;
		$per_page = min( self::MAX_QUERY_ITEMS, max( 1, $per_page ) );
		$page     = max( 1, $page );

		$args = array(
			'post_type'              => $type->name,
			'post_status'            => $status,
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'suppress_filters'       => false,
		);
		if ( isset( $input['search'] ) && '' !== trim( (string) $input['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $input['search'] );
		}
		if ( array_key_exists( 'parent_id', $input ) ) {
			$args['post_parent'] = max( 0, (int) $input['parent_id'] );
		}

		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post && current_user_can( 'edit_post', $post->ID ) ) {
				$items[] = self::normalize_post( $post, false );
			}
		}

		return array(
			'items'       => $items,
			'count'       => count( $items ),
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	public static function get_content( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'cmsa_private_content_input', 'Read input must be an object.' );
		}
		$type = self::resolve_type( $input['post_type'] ?? '' );
		if ( is_wp_error( $type ) ) {
			return $type;
		}
		$post = self::resolve_post( $type, $input['id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'cmsa_private_content_permission', 'The current user cannot read this administrative record.' );
		}

		return array( 'item' => self::normalize_post( $post, true ) );
	}

	public static function save_content( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'cmsa_private_content_input', 'Save input must be an object.' );
		}
		$type = self::resolve_type( $input['post_type'] ?? '' );
		if ( is_wp_error( $type ) ) {
			return $type;
		}

		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$existing = null;
		if ( $id > 0 ) {
			$existing = self::resolve_post( $type, $id );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				return new WP_Error( 'cmsa_private_content_permission', 'The current user cannot edit this record.' );
			}
		} else {
			$create_cap = ! empty( $type->cap->create_posts ) ? $type->cap->create_posts : $type->cap->edit_posts;
			if ( ! $create_cap || ! current_user_can( $create_cap ) ) {
				return new WP_Error( 'cmsa_private_content_permission', 'The current user cannot create this content type.' );
			}
		}

		$prepared = self::prepare_post_fields( $type, $input, $id );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$meta = self::prepare_meta_changes( $input );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}

		$post_snapshot = $existing instanceof WP_Post ? self::snapshot_post( $existing ) : null;
		$meta_snapshot = $existing instanceof WP_Post ? self::snapshot_meta( $existing->ID, $meta ) : array();

		$saved_id = wp_insert_post( $prepared, true );
		if ( is_wp_error( $saved_id ) ) {
			return $saved_id;
		}
		$saved_id = (int) $saved_id;

		$meta_result = self::apply_meta_changes( $saved_id, $meta );
		if ( is_wp_error( $meta_result ) ) {
			$rolled_back = self::rollback_save( $saved_id, $id > 0, $post_snapshot, $meta_snapshot );
			return new WP_Error(
				'cmsa_private_content_meta_failed',
				$meta_result->get_error_message(),
				array( 'rolled_back' => $rolled_back )
			);
		}

		$verified = get_post( $saved_id );
		if ( ! $verified instanceof WP_Post || $type->name !== $verified->post_type ) {
			$rolled_back = self::rollback_save( $saved_id, $id > 0, $post_snapshot, $meta_snapshot );
			return new WP_Error(
				'cmsa_private_content_verify_failed',
				'WordPress did not persist the expected content record.',
				array( 'rolled_back' => $rolled_back )
			);
		}

		return array(
			'created' => 0 === $id,
			'updated' => $id > 0,
			'item'    => self::normalize_post( $verified, true ),
		);
	}

	public static function delete_content( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'cmsa_private_content_input', 'Delete input must be an object.' );
		}
		$type = self::resolve_type( $input['post_type'] ?? '' );
		if ( is_wp_error( $type ) ) {
			return $type;
		}
		$post = self::resolve_post( $type, $input['id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return new WP_Error( 'cmsa_private_content_permission', 'The current user cannot delete this record.' );
		}

		$force = ! empty( $input['force'] );
		$result = $force ? wp_delete_post( $post->ID, true ) : wp_trash_post( $post->ID );
		if ( ! $result instanceof WP_Post ) {
			return new WP_Error( 'cmsa_private_content_delete_failed', 'WordPress could not delete the requested record.' );
		}

		$remaining = get_post( $post->ID );
		return array(
			'id'        => (int) $post->ID,
			'force'     => $force,
			'deleted'   => $force ? null === $remaining : false,
			'trashed'   => ! $force && $remaining instanceof WP_Post && 'trash' === $remaining->post_status,
			'post_type' => $type->name,
		);
	}

	private static function resolve_type( $name ) {
		$name = sanitize_key( (string) $name );
		if ( '' === $name ) {
			return new WP_Error( 'cmsa_private_content_type', 'A post type is required.' );
		}
		$type = get_post_type_object( $name );
		if ( ! $type instanceof WP_Post_Type ) {
			return new WP_Error( 'cmsa_private_content_type', 'The requested post type is not registered.' );
		}
		if ( ! self::is_private_admin_type( $type ) ) {
			return new WP_Error( 'cmsa_private_content_type', 'The requested post type is not an admin-visible private content type.' );
		}
		return $type;
	}

	private static function is_private_admin_type( WP_Post_Type $type ) {
		return ! empty( $type->show_ui ) && empty( $type->show_in_rest ) && 'attachment' !== $type->name;
	}

	private static function can_edit_type( WP_Post_Type $type ) {
		$cap = ! empty( $type->cap->edit_posts ) ? $type->cap->edit_posts : 'edit_posts';
		return current_user_can( 'manage_options' ) && current_user_can( $cap );
	}

	private static function resolve_post( WP_Post_Type $type, $id ) {
		$id = (int) $id;
		if ( $id < 1 ) {
			return new WP_Error( 'cmsa_private_content_id', 'A valid content ID is required.' );
		}
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || $type->name !== $post->post_type ) {
			return new WP_Error( 'cmsa_private_content_missing', 'The requested content record does not exist in this post type.' );
		}
		return $post;
	}

	private static function prepare_post_fields( WP_Post_Type $type, array $input, $id ) {
		$postarr = array(
			'ID'        => (int) $id,
			'post_type' => $type->name,
		);

		$field_map = array(
			'title'      => 'post_title',
			'content'    => 'post_content',
			'excerpt'    => 'post_excerpt',
			'status'     => 'post_status',
			'slug'       => 'post_name',
			'parent_id'  => 'post_parent',
			'menu_order' => 'menu_order',
			'comment_status' => 'comment_status',
			'ping_status'    => 'ping_status',
		);
		foreach ( $field_map as $input_key => $post_key ) {
			if ( ! array_key_exists( $input_key, $input ) ) {
				continue;
			}
			switch ( $input_key ) {
				case 'parent_id':
					$postarr[ $post_key ] = max( 0, (int) $input[ $input_key ] );
					break;
				case 'menu_order':
					$postarr[ $post_key ] = (int) $input[ $input_key ];
					break;
				case 'status':
					$status = sanitize_key( (string) $input[ $input_key ] );
					if ( '' === $status || ! get_post_status_object( $status ) ) {
						return new WP_Error( 'cmsa_private_content_status', 'The requested post status is not registered.' );
					}
					if ( in_array( $status, array( 'publish', 'future' ), true ) ) {
						$publish_cap = ! empty( $type->cap->publish_posts ) ? $type->cap->publish_posts : 'publish_posts';
						if ( ! current_user_can( $publish_cap ) ) {
							return new WP_Error( 'cmsa_private_content_permission', 'The current user cannot publish this content type.' );
						}
					}
					$postarr[ $post_key ] = $status;
					break;
				case 'slug':
					$postarr[ $post_key ] = sanitize_title( (string) $input[ $input_key ] );
					break;
				case 'comment_status':
				case 'ping_status':
					$value = (string) $input[ $input_key ];
					if ( ! in_array( $value, array( 'open', 'closed' ), true ) ) {
						return new WP_Error( 'cmsa_private_content_status', 'Comment and ping status must be open or closed.' );
					}
					$postarr[ $post_key ] = $value;
					break;
				default:
					$postarr[ $post_key ] = (string) $input[ $input_key ];
			}
		}

		if ( 0 === (int) $id && ! isset( $postarr['post_status'] ) ) {
			$postarr['post_status'] = 'draft';
		}
		return $postarr;
	}

	private static function prepare_meta_changes( array $input ) {
		$set = array();
		$delete = array();
		if ( isset( $input['meta'] ) ) {
			if ( ! is_array( $input['meta'] ) || count( $input['meta'] ) > self::MAX_META_KEYS ) {
				return new WP_Error( 'cmsa_private_content_meta', 'Metadata changes must be a bounded object.' );
			}
			foreach ( $input['meta'] as $key => $value ) {
				$key = self::validate_meta_key( $key );
				if ( is_wp_error( $key ) ) {
					return $key;
				}
				$set[ $key ] = $value;
			}
		}
		if ( isset( $input['meta_delete'] ) ) {
			if ( ! is_array( $input['meta_delete'] ) || count( $input['meta_delete'] ) > self::MAX_META_KEYS ) {
				return new WP_Error( 'cmsa_private_content_meta', 'Metadata deletion keys must be a bounded array.' );
			}
			foreach ( $input['meta_delete'] as $key ) {
				$key = self::validate_meta_key( $key );
				if ( is_wp_error( $key ) ) {
					return $key;
				}
				$delete[] = $key;
			}
		}
		$delete = array_values( array_unique( $delete ) );
		foreach ( array_keys( $set ) as $key ) {
			$delete = array_values( array_diff( $delete, array( $key ) ) );
		}
		return array( 'set' => $set, 'delete' => $delete );
	}

	private static function validate_meta_key( $key ) {
		$key = (string) $key;
		if ( '' === $key || strlen( $key ) > 191 || ! preg_match( '/^[A-Za-z0-9_.:\-]+$/', $key ) ) {
			return new WP_Error( 'cmsa_private_content_meta_key', 'Metadata keys must use a bounded WordPress-compatible identifier.' );
		}
		return $key;
	}

	private static function snapshot_post( WP_Post $post ) {
		return array(
			'ID'             => (int) $post->ID,
			'post_type'      => $post->post_type,
			'post_title'     => $post->post_title,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => $post->post_status,
			'post_name'      => $post->post_name,
			'post_parent'    => (int) $post->post_parent,
			'menu_order'     => (int) $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
		);
	}

	private static function snapshot_meta( $post_id, array $changes ) {
		$snapshot = array();
		$keys = array_unique( array_merge( array_keys( $changes['set'] ), $changes['delete'] ) );
		foreach ( $keys as $key ) {
			$snapshot[ $key ] = get_post_meta( $post_id, $key, false );
		}
		return $snapshot;
	}

	private static function apply_meta_changes( $post_id, array $changes ) {
		foreach ( $changes['set'] as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
			if ( get_post_meta( $post_id, $key, true ) != $value ) {
				return new WP_Error( 'cmsa_private_content_meta_write', 'WordPress could not verify a metadata update.' );
			}
		}
		foreach ( $changes['delete'] as $key ) {
			delete_post_meta( $post_id, $key );
			if ( metadata_exists( 'post', $post_id, $key ) ) {
				return new WP_Error( 'cmsa_private_content_meta_delete', 'WordPress could not verify a metadata deletion.' );
			}
		}
		return true;
	}

	private static function rollback_save( $saved_id, $was_update, $post_snapshot, array $meta_snapshot ) {
		if ( ! $was_update ) {
			return false !== wp_delete_post( $saved_id, true ) && null === get_post( $saved_id );
		}
		if ( ! is_array( $post_snapshot ) ) {
			return false;
		}

		$restored = wp_update_post( $post_snapshot, true );
		if ( is_wp_error( $restored ) ) {
			return false;
		}
		foreach ( $meta_snapshot as $key => $values ) {
			delete_post_meta( $saved_id, $key );
			foreach ( $values as $value ) {
				add_post_meta( $saved_id, $key, $value );
			}
		}
		return true;
	}

	private static function normalize_type( WP_Post_Type $type ) {
		return array(
			'name'          => $type->name,
			'label'         => $type->label,
			'hierarchical'  => (bool) $type->hierarchical,
			'show_ui'       => (bool) $type->show_ui,
			'show_in_rest'  => (bool) $type->show_in_rest,
			'capabilities'  => array(
				'edit_posts'    => $type->cap->edit_posts ?? '',
				'create_posts'  => $type->cap->create_posts ?? '',
				'publish_posts' => $type->cap->publish_posts ?? '',
				'delete_posts'  => $type->cap->delete_posts ?? '',
			),
		);
	}

	private static function normalize_post( WP_Post $post, $include_meta ) {
		$item = array(
			'id'             => (int) $post->ID,
			'post_type'      => $post->post_type,
			'status'         => $post->post_status,
			'title'          => $post->post_title,
			'content'        => $post->post_content,
			'excerpt'        => $post->post_excerpt,
			'slug'           => $post->post_name,
			'parent_id'      => (int) $post->post_parent,
			'menu_order'     => (int) $post->menu_order,
			'author_id'      => (int) $post->post_author,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'date_gmt'       => $post->post_date_gmt,
			'modified_gmt'   => $post->post_modified_gmt,
		);
		if ( $include_meta ) {
			$item['meta'] = get_post_meta( $post->ID );
		}
		return $item;
	}
}
