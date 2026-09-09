<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Universal_Control_Plane {
	const OWN_NAMESPACE = 'chattanooga-cms-admin/';

	public function inspect( array $input ) {
		if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error( 'cmsa_universal_unavailable', 'The WordPress Abilities API is unavailable.' );
		}

		$name = isset( $input['name'] ) ? trim( (string) $input['name'] ) : '';
		if ( '' !== $name ) {
			return $this->inspect_exact( $name );
		}

		$namespace = isset( $input['namespace'] ) ? trim( (string) $input['namespace'] ) : '';
		$category  = isset( $input['category'] ) ? trim( (string) $input['category'] ) : '';
		$search    = isset( $input['search'] ) ? trim( (string) $input['search'] ) : '';
		$page      = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page  = isset( $input['per_page'] ) ? min( 100, max( 1, (int) $input['per_page'] ) ) : 50;

		$items = array();
		foreach ( wp_get_abilities() as $ability ) {
			if ( ! $ability instanceof WP_Ability || ! $this->is_discoverable( $ability ) ) {
				continue;
			}

			$item = $this->describe( $ability );
			if ( '' !== $namespace && $item['namespace'] !== $namespace ) {
				continue;
			}
			if ( '' !== $category && $item['category'] !== $category ) {
				continue;
			}
			if ( '' !== $search ) {
				$haystack = strtolower( $item['name'] . "\n" . $item['label'] . "\n" . $item['description'] );
				if ( false === strpos( $haystack, strtolower( $search ) ) ) {
					continue;
				}
			}
			$items[] = $item;
		}

		usort(
			$items,
			static function ( $left, $right ) {
				return strcmp( $left['name'], $right['name'] );
			}
		);

		$total  = count( $items );
		$offset = ( $page - 1 ) * $per_page;
		$items  = array_slice( $items, $offset, $per_page );

		return array(
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'items'    => $items,
		);
	}

	public function inspect_resource_registry( array $input ) {
		$kind     = isset( $input['kind'] ) ? trim( (string) $input['kind'] ) : '';
		$name     = isset( $input['name'] ) ? trim( (string) $input['name'] ) : '';
		$search   = isset( $input['search'] ) ? trim( (string) $input['search'] ) : '';
		$page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? min( 100, max( 1, (int) $input['per_page'] ) ) : 50;

		if ( ! in_array( $kind, array( 'post_type', 'taxonomy' ), true ) ) {
			return new WP_Error( 'cmsa_universal_resource_kind', 'Resource kind must be post_type or taxonomy.' );
		}

		if ( '' !== $name ) {
			return $this->inspect_resource_exact( $kind, $name );
		}

		$items = 'post_type' === $kind ? $this->post_type_registry() : $this->taxonomy_registry();
		if ( '' !== $search ) {
			$needle = strtolower( $search );
			$items  = array_values(
				array_filter(
					$items,
					static function ( $item ) use ( $needle ) {
						$haystack = strtolower( $item['name'] . "\n" . $item['label'] . "\n" . $item['description'] );
						return false !== strpos( $haystack, $needle );
					}
				)
			);
		}

		usort(
			$items,
			static function ( $left, $right ) {
				return strcmp( $left['name'], $right['name'] );
			}
		);

		$total  = count( $items );
		$offset = ( $page - 1 ) * $per_page;

		return array(
			'kind'     => $kind,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'items'    => array_slice( $items, $offset, $per_page ),
		);
	}

	private function inspect_exact( $name ) {
		if ( 0 === strpos( $name, self::OWN_NAMESPACE ) ) {
			return new WP_Error( 'cmsa_universal_self', 'Chattanooga CMS Admin abilities are not mirrored through the universal extension catalog.' );
		}

		$ability = wp_get_ability( $name );
		if ( ! $ability instanceof WP_Ability || ! $this->is_discoverable( $ability ) ) {
			return new WP_Error( 'cmsa_universal_not_found', 'A public native extension ability with that name was not found.' );
		}

		return array( 'item' => $this->describe( $ability ) );
	}

	private function inspect_resource_exact( $kind, $name ) {
		if ( 'post_type' === $kind ) {
			$object = get_post_type_object( $name );
			if ( ! $object instanceof WP_Post_Type || ! $this->is_post_type_discoverable( $object ) ) {
				return new WP_Error( 'cmsa_universal_resource_not_found', 'A discoverable registered post type with that name was not found.' );
			}
			return array( 'item' => $this->describe_post_type( $object ) );
		}

		$object = get_taxonomy( $name );
		if ( ! $object instanceof WP_Taxonomy || ! $this->is_taxonomy_discoverable( $object ) ) {
			return new WP_Error( 'cmsa_universal_resource_not_found', 'A discoverable registered taxonomy with that name was not found.' );
		}
		return array( 'item' => $this->describe_taxonomy( $object ) );
	}

	private function post_type_registry() {
		$items = array();
		foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
			if ( $post_type instanceof WP_Post_Type && $this->is_post_type_discoverable( $post_type ) ) {
				$items[] = $this->describe_post_type( $post_type );
			}
		}
		return $items;
	}

	private function taxonomy_registry() {
		$items = array();
		foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
			if ( $taxonomy instanceof WP_Taxonomy && $this->is_taxonomy_discoverable( $taxonomy ) ) {
				$items[] = $this->describe_taxonomy( $taxonomy );
			}
		}
		return $items;
	}

	private function is_post_type_discoverable( WP_Post_Type $post_type ) {
		return ! empty( $post_type->show_ui ) || ! empty( $post_type->show_in_rest );
	}

	private function is_taxonomy_discoverable( WP_Taxonomy $taxonomy ) {
		return ! empty( $taxonomy->show_ui ) || ! empty( $taxonomy->show_in_rest );
	}

	private function describe_post_type( WP_Post_Type $post_type ) {
		$supports = array_keys( get_all_post_type_supports( $post_type->name ) );
		$taxonomies = get_object_taxonomies( $post_type->name, 'names' );
		sort( $supports, SORT_STRING );
		sort( $taxonomies, SORT_STRING );

		return array(
			'kind'          => 'post_type',
			'name'          => $post_type->name,
			'label'         => $post_type->label,
			'description'   => (string) $post_type->description,
			'public'        => (bool) $post_type->public,
			'show_ui'       => (bool) $post_type->show_ui,
			'show_in_rest'  => (bool) $post_type->show_in_rest,
			'hierarchical'  => (bool) $post_type->hierarchical,
			'has_archive'   => $post_type->has_archive,
			'rest_base'     => $post_type->rest_base ? (string) $post_type->rest_base : null,
			'map_meta_cap'  => (bool) $post_type->map_meta_cap,
			'supports'      => $supports,
			'taxonomies'    => $taxonomies,
			'capabilities'  => $this->post_type_capabilities( $post_type ),
		);
	}

	private function describe_taxonomy( WP_Taxonomy $taxonomy ) {
		$object_types = is_array( $taxonomy->object_type ) ? array_values( $taxonomy->object_type ) : array();
		sort( $object_types, SORT_STRING );

		return array(
			'kind'          => 'taxonomy',
			'name'          => $taxonomy->name,
			'label'         => $taxonomy->label,
			'description'   => (string) $taxonomy->description,
			'public'        => (bool) $taxonomy->public,
			'show_ui'       => (bool) $taxonomy->show_ui,
			'show_in_rest'  => (bool) $taxonomy->show_in_rest,
			'hierarchical'  => (bool) $taxonomy->hierarchical,
			'rest_base'     => $taxonomy->rest_base ? (string) $taxonomy->rest_base : null,
			'object_types'  => $object_types,
			'capabilities'  => $this->taxonomy_capabilities( $taxonomy ),
		);
	}

	private function post_type_capabilities( WP_Post_Type $post_type ) {
		$capabilities = $post_type->cap;
		return array(
			'edit_posts'         => $this->capability_name( $capabilities, 'edit_posts' ),
			'create_posts'       => $this->capability_name( $capabilities, 'create_posts' ),
			'publish_posts'      => $this->capability_name( $capabilities, 'publish_posts' ),
			'delete_posts'       => $this->capability_name( $capabilities, 'delete_posts' ),
			'read_private_posts' => $this->capability_name( $capabilities, 'read_private_posts' ),
		);
	}

	private function taxonomy_capabilities( WP_Taxonomy $taxonomy ) {
		$capabilities = $taxonomy->cap;
		return array(
			'manage_terms' => $this->capability_name( $capabilities, 'manage_terms' ),
			'edit_terms'   => $this->capability_name( $capabilities, 'edit_terms' ),
			'delete_terms' => $this->capability_name( $capabilities, 'delete_terms' ),
			'assign_terms' => $this->capability_name( $capabilities, 'assign_terms' ),
		);
	}

	private function capability_name( $capabilities, $property ) {
		if ( is_object( $capabilities ) && isset( $capabilities->{$property} ) ) {
			return (string) $capabilities->{$property};
		}
		return null;
	}

	private function is_discoverable( WP_Ability $ability ) {
		$name = $ability->get_name();
		if ( 0 === strpos( $name, self::OWN_NAMESPACE ) ) {
			return false;
		}

		$meta = $ability->get_meta();
		return ! empty( $meta['public'] );
	}

	private function describe( WP_Ability $ability ) {
		$name = $ability->get_name();
		$slash = strpos( $name, '/' );
		$namespace = false === $slash ? '' : substr( $name, 0, $slash );
		$meta = $ability->get_meta();
		$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();

		return array(
			'name'          => $name,
			'namespace'     => $namespace,
			'label'         => $ability->get_label(),
			'description'   => $ability->get_description(),
			'category'      => $ability->get_category(),
			'input_schema'  => $ability->get_input_schema(),
			'output_schema' => $ability->get_output_schema(),
			'public'        => ! empty( $meta['public'] ),
			'show_in_rest'  => ! empty( $meta['show_in_rest'] ),
			'mcp_public'    => ! empty( $meta['mcp']['public'] ),
			'annotations'   => array(
				'readonly'    => array_key_exists( 'readonly', $annotations ) ? $annotations['readonly'] : null,
				'destructive' => array_key_exists( 'destructive', $annotations ) ? $annotations['destructive'] : null,
				'idempotent'  => array_key_exists( 'idempotent', $annotations ) ? $annotations['idempotent'] : null,
			),
		);
	}
}
