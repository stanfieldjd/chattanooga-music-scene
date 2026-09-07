<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Content_Taxonomy {
	private $content;
	private $allowed_taxonomies = array( 'category', 'post_tag' );

	public function __construct( CMSA_Content $content ) {
		$this->content = $content;
	}

	public function list_terms( $taxonomy, array $input = array() ) {
		$tax = $this->taxonomy_object( $taxonomy );
		if ( is_wp_error( $tax ) ) {
			return $tax;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'cmsa_taxonomy_permission', 'Current user cannot inspect content taxonomies.' );
		}

		$page = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 50;
		$per_page = max( 1, min( 100, $per_page ) );
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'orderby'    => 'term_id',
			'order'      => 'ASC',
		);
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}
		if ( 'category' === $taxonomy && isset( $input['parent'] ) ) {
			$args['parent'] = max( 0, (int) $input['parent'] );
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return new WP_Error( 'cmsa_taxonomy_list', 'Could not list taxonomy terms.' );
		}
		$count_args = $args;
		unset( $count_args['number'], $count_args['offset'], $count_args['orderby'], $count_args['order'] );
		$count_args['fields'] = 'count';
		$total = get_terms( $count_args );
		if ( is_wp_error( $total ) ) {
			return new WP_Error( 'cmsa_taxonomy_count', 'Could not count taxonomy terms.' );
		}

		$items = array();
		foreach ( $terms as $term ) {
			$items[] = $this->normalize_term( $term );
		}
		return array(
			'taxonomy' => $taxonomy,
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => (int) $total,
			'items'    => $items,
		);
	}

	public function get_term( $taxonomy, $term_id ) {
		$tax = $this->taxonomy_object( $taxonomy );
		if ( is_wp_error( $tax ) ) {
			return $tax;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'cmsa_taxonomy_permission', 'Current user cannot inspect content taxonomies.' );
		}
		$term = get_term( (int) $term_id, $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'cmsa_taxonomy_term', 'Requested taxonomy term does not exist.' );
		}
		return array( 'term' => $this->normalize_term( $term ) );
	}

	public function create_term( $taxonomy, array $input ) {
		$tax = $this->taxonomy_object( $taxonomy );
		if ( is_wp_error( $tax ) ) {
			return $tax;
		}
		if ( ! current_user_can( $tax->cap->manage_terms ) ) {
			return new WP_Error( 'cmsa_taxonomy_manage_permission', 'Current user cannot create taxonomy terms.' );
		}
		$name = isset( $input['name'] ) ? trim( wp_strip_all_tags( (string) $input['name'] ) ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'cmsa_taxonomy_name', 'Term name is required.' );
		}

		$args = array();
		if ( array_key_exists( 'slug', $input ) && '' !== trim( (string) $input['slug'] ) ) {
			$args['slug'] = sanitize_title( $input['slug'] );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$args['description'] = sanitize_textarea_field( $input['description'] );
		}
		if ( array_key_exists( 'parent', $input ) ) {
			$parent = $this->validated_parent( $taxonomy, $input['parent'], 0 );
			if ( is_wp_error( $parent ) ) {
				return $parent;
			}
			$args['parent'] = $parent;
		}

		$result = wp_insert_term( $name, $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'cmsa_taxonomy_create', 'Could not create taxonomy term.' );
		}
		$term_id = isset( $result['term_id'] ) ? (int) $result['term_id'] : 0;
		$created = get_term( $term_id, $taxonomy );
		if ( ! $created instanceof WP_Term ) {
			if ( $term_id ) {
				wp_delete_term( $term_id, $taxonomy );
			}
			return new WP_Error( 'cmsa_taxonomy_create_verify', 'Created taxonomy term could not be verified.' );
		}
		$normalized = $this->normalize_term( $created );
		if ( $name !== $normalized['name'] ) {
			wp_delete_term( $term_id, $taxonomy );
			return new WP_Error( 'cmsa_taxonomy_create_verify', 'Created taxonomy term did not match the requested state.' );
		}
		CMSA_Audit::record( 'create-term', $taxonomy . ':' . $term_id, 'success', array( 'taxonomy' => $taxonomy ) );
		return array( 'created' => true, 'term' => $normalized );
	}

	public function update_term( $taxonomy, array $input ) {
		$tax = $this->taxonomy_object( $taxonomy );
		if ( is_wp_error( $tax ) ) {
			return $tax;
		}
		if ( ! current_user_can( $tax->cap->edit_terms ) ) {
			return new WP_Error( 'cmsa_taxonomy_manage_permission', 'Current user cannot update taxonomy terms.' );
		}
		$term_id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			return new WP_Error( 'cmsa_taxonomy_term', 'Requested taxonomy term does not exist.' );
		}
		$before = $this->normalize_term( $term );
		$expected = isset( $input['expected_state_token'] ) ? (string) $input['expected_state_token'] : '';
		if ( '' === $expected || ! hash_equals( $before['state_token'], $expected ) ) {
			return new WP_Error( 'cmsa_taxonomy_conflict', 'Taxonomy term changed after it was read; update was not attempted.', array( 'current_state_token' => $before['state_token'] ) );
		}

		$args = array();
		if ( array_key_exists( 'name', $input ) ) {
			$name = trim( wp_strip_all_tags( (string) $input['name'] ) );
			if ( '' === $name ) {
				return new WP_Error( 'cmsa_taxonomy_name', 'Term name cannot be empty.' );
			}
			$args['name'] = $name;
		}
		if ( array_key_exists( 'slug', $input ) ) {
			$args['slug'] = sanitize_title( $input['slug'] );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$args['description'] = sanitize_textarea_field( $input['description'] );
		}
		if ( array_key_exists( 'parent', $input ) ) {
			$parent = $this->validated_parent( $taxonomy, $input['parent'], $term_id );
			if ( is_wp_error( $parent ) ) {
				return $parent;
			}
			$args['parent'] = $parent;
		}
		if ( empty( $args ) ) {
			return new WP_Error( 'cmsa_taxonomy_no_fields', 'No taxonomy term fields were provided for update.' );
		}

		$result = wp_update_term( $term_id, $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'cmsa_taxonomy_update', 'Could not update taxonomy term.' );
		}
		$after_term = get_term( $term_id, $taxonomy );
		$after = $after_term instanceof WP_Term ? $this->normalize_term( $after_term ) : null;
		if ( ! $after || ! $this->term_matches_update( $after, $args ) ) {
			$rolled_back = $this->restore_term( $taxonomy, $term_id, $before );
			return new WP_Error( 'cmsa_taxonomy_update_verify', 'Taxonomy term update verification failed.', array( 'rolled_back' => $rolled_back ) );
		}
		CMSA_Audit::record( 'update-term', $taxonomy . ':' . $term_id, 'success', array( 'taxonomy' => $taxonomy ) );
		return array( 'updated' => true, 'previous_state_token' => $before['state_token'], 'term' => $after );
	}

	public function change_relationship( $post_type, $taxonomy, array $input, $mode ) {
		$tax = $this->taxonomy_object( $taxonomy );
		if ( is_wp_error( $tax ) ) {
			return $tax;
		}
		if ( 'post' !== $post_type || ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
			return new WP_Error( 'cmsa_taxonomy_object_type', 'Taxonomy is not registered for the requested content type.' );
		}
		if ( ! current_user_can( $tax->cap->assign_terms ) ) {
			return new WP_Error( 'cmsa_taxonomy_assign_permission', 'Current user cannot assign this taxonomy.' );
		}
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$item = $this->content->get_item( $post_type, $id );
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$current = $this->object_term_ids( $id, $taxonomy );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$expected = isset( $input['expected_term_ids'] ) && is_array( $input['expected_term_ids'] ) ? $this->normalize_ids( $input['expected_term_ids'] ) : array();
		if ( $expected !== $current ) {
			return new WP_Error( 'cmsa_taxonomy_relationship_conflict', 'Content taxonomy relationships changed after they were read; mutation was not attempted.', array( 'current_term_ids' => $current ) );
		}
		$term_ids = isset( $input['term_ids'] ) && is_array( $input['term_ids'] ) ? $this->normalize_ids( $input['term_ids'] ) : array();
		if ( empty( $term_ids ) ) {
			return new WP_Error( 'cmsa_taxonomy_relationship_terms', 'At least one taxonomy term ID is required.' );
		}
		foreach ( $term_ids as $term_id ) {
			if ( ! term_exists( $term_id, $taxonomy ) ) {
				return new WP_Error( 'cmsa_taxonomy_relationship_term', 'One or more taxonomy terms do not exist in the requested taxonomy.' );
			}
		}

		if ( 'assign' === $mode ) {
			$target = $this->normalize_ids( array_merge( $current, $term_ids ) );
		} elseif ( 'remove' === $mode ) {
			$target = array_values( array_diff( $current, $term_ids ) );
			$target = $this->normalize_ids( $target );
			if ( 'category' === $taxonomy && empty( $target ) ) {
				$default = (int) get_option( 'default_category' );
				if ( $default < 1 || ! term_exists( $default, 'category' ) ) {
					return new WP_Error( 'cmsa_taxonomy_default_category', 'Default category is unavailable; category relationship was not removed.' );
				}
				$target = array( $default );
			}
		} else {
			return new WP_Error( 'cmsa_taxonomy_relationship_mode', 'Unsupported taxonomy relationship mutation mode.' );
		}
		if ( $target === $current ) {
			return new WP_Error( 'cmsa_taxonomy_relationship_no_change', 'Requested taxonomy relationship mutation would not change the target.' );
		}

		$result = wp_set_object_terms( $id, $target, $taxonomy, false );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'cmsa_taxonomy_relationship_update', 'Could not update content taxonomy relationships.' );
		}
		$after = $this->object_term_ids( $id, $taxonomy );
		if ( is_wp_error( $after ) || $after !== $target ) {
			$rollback_result = wp_set_object_terms( $id, $current, $taxonomy, false );
			$rollback_state = $this->object_term_ids( $id, $taxonomy );
			$rolled_back = ! is_wp_error( $rollback_result ) && ! is_wp_error( $rollback_state ) && $rollback_state === $current;
			return new WP_Error( 'cmsa_taxonomy_relationship_verify', 'Content taxonomy relationship verification failed.', array( 'rolled_back' => $rolled_back, 'previous_term_ids' => $current ) );
		}

		CMSA_Audit::record( $mode . '-terms', $post_type . ':' . $id, 'success', array( 'taxonomy' => $taxonomy, 'previous_term_ids' => $current, 'term_ids' => $after ) );
		return array( 'updated' => true, 'mode' => $mode, 'taxonomy' => $taxonomy, 'previous_term_ids' => $current, 'term_ids' => $after );
	}

	private function taxonomy_object( $taxonomy ) {
		$taxonomy = sanitize_key( $taxonomy );
		if ( ! in_array( $taxonomy, $this->allowed_taxonomies, true ) ) {
			return new WP_Error( 'cmsa_taxonomy_not_allowed', 'Only the bounded category and post_tag taxonomies are supported.' );
		}
		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax ) {
			return new WP_Error( 'cmsa_taxonomy_unavailable', 'Requested taxonomy is not registered.' );
		}
		return $tax;
	}

	private function validated_parent( $taxonomy, $parent, $self_id ) {
		$parent = max( 0, (int) $parent );
		if ( 'category' !== $taxonomy ) {
			if ( 0 !== $parent ) {
				return new WP_Error( 'cmsa_taxonomy_parent', 'Tags do not support parent terms.' );
			}
			return 0;
		}
		if ( 0 === $parent ) {
			return 0;
		}
		if ( $parent === (int) $self_id ) {
			return new WP_Error( 'cmsa_taxonomy_parent', 'A category cannot be its own parent.' );
		}
		$parent_term = get_term( $parent, $taxonomy );
		if ( ! $parent_term instanceof WP_Term ) {
			return new WP_Error( 'cmsa_taxonomy_parent', 'Requested category parent does not exist.' );
		}
		if ( $self_id && term_is_ancestor_of( $self_id, $parent, $taxonomy ) ) {
			return new WP_Error( 'cmsa_taxonomy_parent', 'A category cannot be moved beneath one of its descendants.' );
		}
		return $parent;
	}

	private function normalize_term( WP_Term $term ) {
		$state = array(
			'id'          => (int) $term->term_id,
			'taxonomy'    => (string) $term->taxonomy,
			'name'        => (string) $term->name,
			'slug'        => (string) $term->slug,
			'description' => (string) $term->description,
			'parent'      => (int) $term->parent,
		);
		$state['count'] = (int) $term->count;
		$token_state = $state;
		unset( $token_state['count'] );
		$state['state_token'] = hash( 'sha256', wp_json_encode( $token_state ) );
		return $state;
	}

	private function term_matches_update( array $after, array $args ) {
		foreach ( array( 'name', 'slug', 'description', 'parent' ) as $field ) {
			if ( array_key_exists( $field, $args ) ) {
				$expected = 'parent' === $field ? (int) $args[ $field ] : (string) $args[ $field ];
				if ( $after[ $field ] !== $expected ) {
					return false;
				}
			}
		}
		return true;
	}

	private function restore_term( $taxonomy, $term_id, array $before ) {
		$result = wp_update_term(
			(int) $term_id,
			$taxonomy,
			array(
				'name'        => $before['name'],
				'slug'        => $before['slug'],
				'description' => $before['description'],
				'parent'      => $before['parent'],
			)
		);
		if ( is_wp_error( $result ) ) {
			return false;
		}
		$restored = get_term( (int) $term_id, $taxonomy );
		return $restored instanceof WP_Term && hash_equals( $before['state_token'], $this->normalize_term( $restored )['state_token'] );
	}

	private function object_term_ids( $id, $taxonomy ) {
		$ids = wp_get_object_terms( (int) $id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $ids ) ) {
			return new WP_Error( 'cmsa_taxonomy_relationship_read', 'Could not read content taxonomy relationships.' );
		}
		return $this->normalize_ids( $ids );
	}

	private function normalize_ids( array $ids ) {
		$normalized = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$normalized[] = $id;
			}
		}
		$normalized = array_values( array_unique( $normalized ) );
		sort( $normalized, SORT_NUMERIC );
		return $normalized;
	}
}
