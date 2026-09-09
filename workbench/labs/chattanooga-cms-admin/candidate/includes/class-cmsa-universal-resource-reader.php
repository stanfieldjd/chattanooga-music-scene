<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only access to administratively exposed standard WordPress resources.
 *
 * This service intentionally uses only documented WordPress post-type and
 * taxonomy registries plus their capability model. It does not read arbitrary
 * postmeta/termmeta, plugin tables, options, filesystem state, or plugin-private
 * APIs, and it provides no mutation path.
 */
final class CMSA_Universal_Resource_Reader {
	const MAX_PER_PAGE = 100;

	public function read( array $input ) {
		$kind      = isset( $input['kind'] ) ? trim( (string) $input['kind'] ) : '';
		$resource  = isset( $input['resource'] ) ? trim( (string) $input['resource'] ) : '';
		$operation = isset( $input['operation'] ) ? trim( (string) $input['operation'] ) : '';

		if ( ! in_array( $kind, array( 'post_type', 'taxonomy' ), true ) ) {
			return new WP_Error( 'cmsa_universal_resource_kind', 'Resource kind must be post_type or taxonomy.' );
		}
		if ( '' === $resource ) {
			return new WP_Error( 'cmsa_universal_resource_name', 'A registered WordPress resource name is required.' );
		}
		if ( ! in_array( $operation, array( 'list', 'get' ), true ) ) {
			return new WP_Error( 'cmsa_universal_resource_operation', 'Resource operation must be list or get.' );
		}

		return 'post_type' === $kind
			? $this->read_post_type( $resource, $operation, $input )
			: $this->read_taxonomy( $resource, $operation, $input );
	}

	private function read_post_type( $resource, $operation, array $input ) {
		$post_type = get_post_type_object( $resource );
		if ( ! $post_type instanceof WP_Post_Type || ! $this->is_post_type_discoverable( $post_type ) ) {
			return new WP_Error( 'cmsa_universal_resource_not_found', 'A discoverable registered post type with that name was not found.' );
		}

		$edit_posts = $this->capability_name( $post_type->cap, 'edit_posts' );
		if ( '' === $edit_posts || ! current_user_can( $edit_posts ) ) {
			return new WP_Error( 'cmsa_universal_resource_permission', 'Current user cannot inspect this registered post type.' );
		}

		if ( 'get' === $operation ) {
			$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
			if ( $id < 1 ) {
				return new WP_Error( 'cmsa_universal_resource_id', 'A valid post ID is required for a get operation.' );
			}
			$post = get_post( $id );
			if ( ! $post instanceof WP_Post || $resource !== $post->post_type ) {
				return new WP_Error( 'cmsa_universal_resource_record_not_found', 'The requested registered post-type record was not found.' );
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				return new WP_Error( 'cmsa_universal_resource_permission', 'Current user cannot inspect this registered post-type record.' );
			}
			return array(
				'kind'      => 'post_type',
				'resource'  => $resource,
				'operation' => 'get',
				'item'      => $this->normalize_post( $post, true ),
			);
		}

		$page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? min( self::MAX_PER_PAGE, max( 1, (int) $input['per_page'] ) ) : 20;
		$status   = isset( $input['status'] ) ? trim( (string) $input['status'] ) : 'any';
		$search   = isset( $input['search'] ) ? trim( (string) $input['search'] ) : '';

		if ( '' === $status ) {
			$status = 'any';
		}
		if ( 'any' !== $status && ! get_post_status_object( $status ) ) {
			return new WP_Error( 'cmsa_universal_resource_status', 'Requested post status is not registered in WordPress.' );
		}

		$args = array(
			'post_type'           => $resource,
			'post_status'         => $status,
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'orderby'             => 'modified',
			'order'               => 'DESC',
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
			'suppress_filters'    => false,
		);
		if ( '' !== $search ) {
			$args['s'] = sanitize_text_field( $search );
		}

		$edit_others = $this->capability_name( $post_type->cap, 'edit_others_posts' );
		if ( '' === $edit_others || ! current_user_can( $edit_others ) ) {
			$args['author'] = get_current_user_id();
		}

		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post && $resource === $post->post_type ) {
				$items[] = $this->normalize_post( $post, false );
			}
		}

		return array(
			'kind'      => 'post_type',
			'resource'  => $resource,
			'operation' => 'list',
			'page'      => $page,
			'per_page'  => $per_page,
			'total'     => (int) $query->found_posts,
			'pages'     => (int) $query->max_num_pages,
			'items'     => $items,
		);
	}

	private function read_taxonomy( $resource, $operation, array $input ) {
		$taxonomy = get_taxonomy( $resource );
		if ( ! $taxonomy instanceof WP_Taxonomy || ! $this->is_taxonomy_discoverable( $taxonomy ) ) {
			return new WP_Error( 'cmsa_universal_resource_not_found', 'A discoverable registered taxonomy with that name was not found.' );
		}

		$manage_terms = $this->capability_name( $taxonomy->cap, 'manage_terms' );
		if ( '' === $manage_terms || ! current_user_can( $manage_terms ) ) {
			return new WP_Error( 'cmsa_universal_resource_permission', 'Current user cannot inspect this registered taxonomy.' );
		}

		if ( 'get' === $operation ) {
			$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
			if ( $id < 1 ) {
				return new WP_Error( 'cmsa_universal_resource_id', 'A valid term ID is required for a get operation.' );
			}
			$term = get_term( $id, $resource );
			if ( is_wp_error( $term ) || ! $term instanceof WP_Term || $resource !== $term->taxonomy ) {
				return new WP_Error( 'cmsa_universal_resource_record_not_found', 'The requested registered taxonomy term was not found.' );
			}
			return array(
				'kind'      => 'taxonomy',
				'resource'  => $resource,
				'operation' => 'get',
				'item'      => $this->normalize_term( $term ),
			);
		}

		$page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? min( self::MAX_PER_PAGE, max( 1, (int) $input['per_page'] ) ) : 20;
		$search   = isset( $input['search'] ) ? trim( (string) $input['search'] ) : '';
		$offset   = ( $page - 1 ) * $per_page;

		$args = array(
			'taxonomy'   => $resource,
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => $offset,
			'orderby'    => 'term_id',
			'order'      => 'ASC',
		);
		$count_args = array( 'hide_empty' => false );
		if ( '' !== $search ) {
			$args['search'] = sanitize_text_field( $search );
			$count_args['search'] = sanitize_text_field( $search );
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return new WP_Error( 'cmsa_universal_resource_read', 'WordPress could not read the registered taxonomy.' );
		}
		$total = wp_count_terms( $resource, $count_args );
		if ( is_wp_error( $total ) ) {
			return new WP_Error( 'cmsa_universal_resource_read', 'WordPress could not count the registered taxonomy.' );
		}

		$items = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term && $resource === $term->taxonomy ) {
				$items[] = $this->normalize_term( $term );
			}
		}

		$total = (int) $total;
		return array(
			'kind'      => 'taxonomy',
			'resource'  => $resource,
			'operation' => 'list',
			'page'      => $page,
			'per_page'  => $per_page,
			'total'     => $total,
			'pages'     => $total > 0 ? (int) ceil( $total / $per_page ) : 0,
			'items'     => $items,
		);
	}

	private function normalize_post( WP_Post $post, $include_content ) {
		$item = array(
			'id'           => (int) $post->ID,
			'post_type'    => (string) $post->post_type,
			'status'       => (string) $post->post_status,
			'title'        => (string) $post->post_title,
			'excerpt'      => (string) $post->post_excerpt,
			'slug'         => (string) $post->post_name,
			'parent_id'    => (int) $post->post_parent,
			'date_gmt'     => (string) $post->post_date_gmt,
			'modified_gmt' => (string) $post->post_modified_gmt,
		);
		if ( $include_content ) {
			$item['content'] = (string) $post->post_content;
		}
		return $item;
	}

	private function normalize_term( WP_Term $term ) {
		return array(
			'id'          => (int) $term->term_id,
			'taxonomy'    => (string) $term->taxonomy,
			'name'        => (string) $term->name,
			'slug'        => (string) $term->slug,
			'description' => (string) $term->description,
			'parent_id'   => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	private function is_post_type_discoverable( WP_Post_Type $post_type ) {
		return ! empty( $post_type->show_ui ) || ! empty( $post_type->show_in_rest );
	}

	private function is_taxonomy_discoverable( WP_Taxonomy $taxonomy ) {
		return ! empty( $taxonomy->show_ui ) || ! empty( $taxonomy->show_in_rest );
	}

	private function capability_name( $capabilities, $property ) {
		if ( is_object( $capabilities ) && isset( $capabilities->{$property} ) ) {
			return (string) $capabilities->{$property};
		}
		return '';
	}
}
