<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded core WordPress navigation-menu administration.
 *
 * This service intentionally supports classic/core nav menus only. It does not
 * mutate theme templates, third-party menu plugins, widgets, or arbitrary
 * options/theme-mod state.
 */
final class CMSA_Navigation {
	public function list_menus() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'cmsa_navigation_permission', 'Current user cannot inspect WordPress navigation menus.' );
		}

		$terms = wp_get_nav_menus(
			array(
				'orderby' => 'term_id',
				'order'   => 'ASC',
				'number'  => 100,
			)
		);
		if ( ! is_array( $terms ) ) {
			return new WP_Error( 'cmsa_navigation_list', 'WordPress could not list navigation menus.' );
		}

		$menus = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$menus[] = $this->normalize_menu( $term );
			}
		}

		return array(
			'menus'     => $menus,
			'locations' => $this->normalized_locations(),
		);
	}

	public function get_menu( $menu_id ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'cmsa_navigation_permission', 'Current user cannot inspect WordPress navigation menus.' );
		}
		$menu = $this->menu_object( $menu_id );
		if ( is_wp_error( $menu ) ) {
			return $menu;
		}
		return array( 'menu' => $this->normalize_menu( $menu ) );
	}

	public function create_menu( array $input ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'cmsa_navigation_manage_permission', 'Current user cannot create WordPress navigation menus.' );
		}
		$name = isset( $input['name'] ) ? trim( wp_strip_all_tags( (string) $input['name'] ) ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'cmsa_navigation_name', 'Navigation menu name is required.' );
		}

		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			return new WP_Error( 'cmsa_navigation_create', 'WordPress could not create the navigation menu.' );
		}
		$menu = $this->menu_object( $menu_id );
		if ( is_wp_error( $menu ) || $name !== (string) $menu->name ) {
			wp_delete_nav_menu( (int) $menu_id );
			return new WP_Error( 'cmsa_navigation_create_verify', 'Created navigation menu could not be verified.' );
		}

		$normalized = $this->normalize_menu( $menu );
		CMSA_Audit::record( 'create-navigation-menu', (string) $menu_id, 'success', array( 'state_token' => $normalized['state_token'] ) );
		return array( 'created' => true, 'menu' => $normalized );
	}

	public function upsert_item( array $input ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'cmsa_navigation_manage_permission', 'Current user cannot modify WordPress navigation menus.' );
		}

		$menu_id = isset( $input['menu_id'] ) ? (int) $input['menu_id'] : 0;
		$menu = $this->menu_object( $menu_id );
		if ( is_wp_error( $menu ) ) {
			return $menu;
		}
		$before = $this->normalize_menu( $menu );
		$expected = isset( $input['expected_menu_state'] ) ? (string) $input['expected_menu_state'] : '';
		if ( '' === $expected || ! hash_equals( $before['state_token'], $expected ) ) {
			return new WP_Error( 'cmsa_navigation_conflict', 'Navigation menu changed after it was read; item mutation was not attempted.', array( 'current_menu_state' => $before['state_token'] ) );
		}

		$item_id = isset( $input['item_id'] ) ? max( 0, (int) $input['item_id'] ) : 0;
		$existing = null;
		$previous_args = null;
		if ( $item_id > 0 ) {
			$existing = $this->menu_item( $menu_id, $item_id );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
			$previous_args = $this->item_update_args( $existing );
		}

		$args = $this->prepare_item_args( $menu_id, $item_id, $existing, $input );
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$result_id = wp_update_nav_menu_item( $menu_id, $item_id, $args );
		if ( is_wp_error( $result_id ) || (int) $result_id < 1 ) {
			return new WP_Error( 'cmsa_navigation_item_write', 'WordPress could not write the navigation menu item.' );
		}
		$result_id = (int) $result_id;

		$after_item = $this->menu_item( $menu_id, $result_id );
		if ( is_wp_error( $after_item ) || ! $this->item_matches_args( $after_item, $args ) ) {
			$rolled_back = $item_id > 0
				? $this->restore_item( $menu_id, $item_id, $previous_args )
				: $this->cleanup_created_item( $result_id );
			return new WP_Error( 'cmsa_navigation_item_verify', 'Navigation menu item write verification failed.', array( 'rolled_back' => $rolled_back ) );
		}

		$after_menu = $this->menu_object( $menu_id );
		if ( is_wp_error( $after_menu ) ) {
			$rolled_back = $item_id > 0
				? $this->restore_item( $menu_id, $item_id, $previous_args )
				: $this->cleanup_created_item( $result_id );
			return new WP_Error( 'cmsa_navigation_item_verify', 'Navigation menu could not be reloaded after item write.', array( 'rolled_back' => $rolled_back ) );
		}

		$normalized_menu = $this->normalize_menu( $after_menu );
		$normalized_item = $this->normalize_item( $after_item );
		CMSA_Audit::record( $item_id > 0 ? 'update-navigation-item' : 'create-navigation-item', (string) $result_id, 'success', array( 'menu_id' => $menu_id, 'menu_state' => $normalized_menu['state_token'] ) );
		return array(
			'created' => 0 === $item_id,
			'updated' => $item_id > 0,
			'item'    => $normalized_item,
			'menu'    => $normalized_menu,
		);
	}

	public function delete_item( array $input ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'cmsa_navigation_manage_permission', 'Current user cannot modify WordPress navigation menus.' );
		}
		$menu_id = isset( $input['menu_id'] ) ? (int) $input['menu_id'] : 0;
		$menu = $this->menu_object( $menu_id );
		if ( is_wp_error( $menu ) ) {
			return $menu;
		}
		$before = $this->normalize_menu( $menu );
		$expected = isset( $input['expected_menu_state'] ) ? (string) $input['expected_menu_state'] : '';
		if ( '' === $expected || ! hash_equals( $before['state_token'], $expected ) ) {
			return new WP_Error( 'cmsa_navigation_conflict', 'Navigation menu changed after it was read; item deletion was not attempted.', array( 'current_menu_state' => $before['state_token'] ) );
		}
		if ( empty( $input['confirm_delete'] ) || true !== (bool) $input['confirm_delete'] ) {
			return new WP_Error( 'cmsa_navigation_delete_confirmation', 'Navigation item deletion requires explicit confirmation.' );
		}

		$item_id = isset( $input['item_id'] ) ? (int) $input['item_id'] : 0;
		$item = $this->menu_item( $menu_id, $item_id );
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		$receipt = $this->normalize_item( $item );
		$deleted = wp_delete_post( $item_id, true );
		if ( ! $deleted instanceof WP_Post || get_post( $item_id ) instanceof WP_Post ) {
			return new WP_Error( 'cmsa_navigation_item_delete', 'WordPress could not verify navigation item deletion.' );
		}
		$after_menu = $this->menu_object( $menu_id );
		if ( is_wp_error( $after_menu ) ) {
			return new WP_Error( 'cmsa_navigation_item_delete_verify', 'Navigation menu could not be reloaded after item deletion.' );
		}
		$normalized_menu = $this->normalize_menu( $after_menu );
		CMSA_Audit::record( 'delete-navigation-item', (string) $item_id, 'success', array( 'menu_id' => $menu_id, 'menu_state' => $normalized_menu['state_token'] ) );
		return array( 'deleted' => true, 'item' => $receipt, 'menu' => $normalized_menu );
	}

	public function set_location( array $input ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'cmsa_navigation_manage_permission', 'Current user cannot assign WordPress navigation menu locations.' );
		}
		$location = isset( $input['location'] ) ? sanitize_key( (string) $input['location'] ) : '';
		$registered = get_registered_nav_menus();
		if ( '' === $location || ! isset( $registered[ $location ] ) ) {
			return new WP_Error( 'cmsa_navigation_location', 'Requested navigation menu location is not registered by the active theme/runtime.' );
		}
		$current = $this->normalized_locations();
		$expected = isset( $input['expected_locations_state'] ) ? (string) $input['expected_locations_state'] : '';
		if ( '' === $expected || ! hash_equals( $current['state_token'], $expected ) ) {
			return new WP_Error( 'cmsa_navigation_locations_conflict', 'Navigation menu location assignments changed after they were read; mutation was not attempted.', array( 'current_locations_state' => $current['state_token'] ) );
		}

		$menu_id = isset( $input['menu_id'] ) ? max( 0, (int) $input['menu_id'] ) : 0;
		if ( $menu_id > 0 && is_wp_error( $this->menu_object( $menu_id ) ) ) {
			return new WP_Error( 'cmsa_navigation_menu', 'Requested navigation menu does not exist.' );
		}

		$previous = get_theme_mod( 'nav_menu_locations', array() );
		$previous = is_array( $previous ) ? $previous : array();
		$target = $previous;
		$target[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $target );

		$after = $this->normalized_locations();
		$assigned = isset( $after['assignments'][ $location ] ) ? (int) $after['assignments'][ $location ] : 0;
		if ( $assigned !== $menu_id ) {
			set_theme_mod( 'nav_menu_locations', $previous );
			$rollback = $this->normalized_locations();
			return new WP_Error( 'cmsa_navigation_location_verify', 'Navigation menu location assignment verification failed.', array( 'rolled_back' => hash_equals( $current['state_token'], $rollback['state_token'] ) ) );
		}

		CMSA_Audit::record( 'set-navigation-location', $location, 'success', array( 'menu_id' => $menu_id, 'locations_state' => $after['state_token'] ) );
		return array( 'updated' => true, 'locations' => $after );
	}

	private function menu_object( $menu_id ) {
		$menu_id = (int) $menu_id;
		if ( $menu_id < 1 ) {
			return new WP_Error( 'cmsa_navigation_menu', 'A valid navigation menu ID is required.' );
		}
		$menu = wp_get_nav_menu_object( $menu_id );
		if ( ! $menu instanceof WP_Term ) {
			return new WP_Error( 'cmsa_navigation_menu', 'Requested navigation menu does not exist.' );
		}
		return $menu;
	}

	private function menu_item( $menu_id, $item_id ) {
		$item_id = (int) $item_id;
		if ( $item_id < 1 ) {
			return new WP_Error( 'cmsa_navigation_item', 'A valid navigation item ID is required.' );
		}
		$items = wp_get_nav_menu_items( (int) $menu_id, array( 'post_status' => 'publish' ) );
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'cmsa_navigation_items', 'WordPress could not read navigation menu items.' );
		}
		foreach ( $items as $item ) {
			if ( $item instanceof WP_Post && (int) $item->ID === $item_id ) {
				return $item;
			}
		}
		return new WP_Error( 'cmsa_navigation_item', 'Requested navigation item does not belong to the requested menu.' );
	}

	private function prepare_item_args( $menu_id, $item_id, $existing, array $input ) {
		$type = isset( $input['type'] ) ? sanitize_key( (string) $input['type'] ) : ( $existing instanceof WP_Post ? (string) $existing->type : '' );
		if ( ! in_array( $type, array( 'post_type', 'custom' ), true ) ) {
			return new WP_Error( 'cmsa_navigation_item_type', 'Navigation item type must be post_type or custom.' );
		}

		$parent_id = array_key_exists( 'parent_id', $input ) ? max( 0, (int) $input['parent_id'] ) : ( $existing instanceof WP_Post ? (int) $existing->menu_item_parent : 0 );
		if ( $parent_id > 0 ) {
			$parent = $this->menu_item( $menu_id, $parent_id );
			if ( is_wp_error( $parent ) ) {
				return new WP_Error( 'cmsa_navigation_item_parent', 'Requested parent navigation item is not in this menu.' );
			}
			if ( $item_id > 0 && ( $parent_id === $item_id || $this->would_create_cycle( $menu_id, $item_id, $parent_id ) ) ) {
				return new WP_Error( 'cmsa_navigation_item_parent_cycle', 'Navigation item parent would create a cycle.' );
			}
		}

		$position = array_key_exists( 'position', $input ) ? max( 0, (int) $input['position'] ) : ( $existing instanceof WP_Post ? (int) $existing->menu_order : 0 );
		$title = array_key_exists( 'title', $input ) ? trim( wp_strip_all_tags( (string) $input['title'] ) ) : ( $existing instanceof WP_Post ? (string) $existing->title : '' );

		$args = array(
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent_id,
			'menu-item-position'  => $position,
		);

		if ( 'post_type' === $type ) {
			$object_id = array_key_exists( 'object_id', $input ) ? (int) $input['object_id'] : ( $existing instanceof WP_Post ? (int) $existing->object_id : 0 );
			$page = get_post( $object_id );
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
				return new WP_Error( 'cmsa_navigation_page', 'Core page navigation items require an existing published WordPress page.' );
			}
			$args['menu-item-type'] = 'post_type';
			$args['menu-item-object'] = 'page';
			$args['menu-item-object-id'] = $object_id;
			$args['menu-item-title'] = '' !== $title ? $title : (string) $page->post_title;
		} else {
			$url = array_key_exists( 'url', $input ) ? trim( (string) $input['url'] ) : ( $existing instanceof WP_Post ? (string) $existing->url : '' );
			$url = $this->validated_url( $url );
			if ( is_wp_error( $url ) ) {
				return $url;
			}
			if ( '' === $title ) {
				return new WP_Error( 'cmsa_navigation_item_title', 'Custom navigation links require a title.' );
			}
			$args['menu-item-type'] = 'custom';
			$args['menu-item-object'] = 'custom';
			$args['menu-item-url'] = $url;
			$args['menu-item-title'] = $title;
		}

		return $args;
	}

	private function validated_url( $url ) {
		if ( '' === $url ) {
			return new WP_Error( 'cmsa_navigation_item_url', 'Custom navigation link URL is required.' );
		}
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return $url;
		}
		$sanitized = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( '' === $sanitized ) {
			return new WP_Error( 'cmsa_navigation_item_url', 'Custom navigation link URL must be root-relative or use HTTP/HTTPS.' );
		}
		return $sanitized;
	}

	private function would_create_cycle( $menu_id, $item_id, $parent_id ) {
		$seen = array();
		$current = (int) $parent_id;
		while ( $current > 0 && ! isset( $seen[ $current ] ) ) {
			if ( $current === (int) $item_id ) {
				return true;
			}
			$seen[ $current ] = true;
			$item = $this->menu_item( $menu_id, $current );
			if ( is_wp_error( $item ) ) {
				return false;
			}
			$current = (int) $item->menu_item_parent;
		}
		return false;
	}

	private function item_update_args( WP_Post $item ) {
		$args = array(
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => (int) $item->menu_item_parent,
			'menu-item-position'  => (int) $item->menu_order,
			'menu-item-title'     => (string) $item->title,
			'menu-item-type'      => (string) $item->type,
			'menu-item-object'    => (string) $item->object,
			'menu-item-object-id' => (int) $item->object_id,
		);
		if ( 'custom' === (string) $item->type ) {
			$args['menu-item-url'] = (string) $item->url;
		}
		return $args;
	}

	private function restore_item( $menu_id, $item_id, $args ) {
		if ( ! is_array( $args ) ) {
			return false;
		}
		$result = wp_update_nav_menu_item( (int) $menu_id, (int) $item_id, $args );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		$restored = $this->menu_item( $menu_id, $item_id );
		return ! is_wp_error( $restored ) && $this->item_matches_args( $restored, $args );
	}

	private function cleanup_created_item( $item_id ) {
		$deleted = wp_delete_post( (int) $item_id, true );
		return $deleted instanceof WP_Post && ! ( get_post( (int) $item_id ) instanceof WP_Post );
	}

	private function item_matches_args( WP_Post $item, array $args ) {
		if ( isset( $args['menu-item-title'] ) && (string) $item->title !== (string) $args['menu-item-title'] ) {
			return false;
		}
		if ( isset( $args['menu-item-type'] ) && (string) $item->type !== (string) $args['menu-item-type'] ) {
			return false;
		}
		if ( isset( $args['menu-item-object'] ) && (string) $item->object !== (string) $args['menu-item-object'] ) {
			return false;
		}
		if ( isset( $args['menu-item-object-id'] ) && (int) $item->object_id !== (int) $args['menu-item-object-id'] ) {
			return false;
		}
		if ( isset( $args['menu-item-url'] ) && (string) $item->url !== (string) $args['menu-item-url'] ) {
			return false;
		}
		if ( isset( $args['menu-item-parent-id'] ) && (int) $item->menu_item_parent !== (int) $args['menu-item-parent-id'] ) {
			return false;
		}
		return true;
	}

	private function normalize_menu( WP_Term $menu ) {
		$items = wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'publish' ) );
		$items = is_array( $items ) ? $items : array();
		$normalized_items = array();
		foreach ( $items as $item ) {
			if ( $item instanceof WP_Post ) {
				$normalized_items[] = $this->normalize_item( $item );
			}
		}
		usort(
			$normalized_items,
			static function ( $a, $b ) {
				if ( $a['position'] === $b['position'] ) {
					return $a['id'] <=> $b['id'];
				}
				return $a['position'] <=> $b['position'];
			}
		);
		$payload = array(
			'id'    => (int) $menu->term_id,
			'name'  => (string) $menu->name,
			'slug'  => (string) $menu->slug,
			'items' => $normalized_items,
		);
		$payload['state_token'] = hash( 'sha256', wp_json_encode( $payload ) );
		return $payload;
	}

	private function normalize_item( WP_Post $item ) {
		return array(
			'id'        => (int) $item->ID,
			'title'     => isset( $item->title ) ? (string) $item->title : (string) $item->post_title,
			'url'       => isset( $item->url ) ? (string) $item->url : '',
			'type'      => isset( $item->type ) ? (string) $item->type : '',
			'object'    => isset( $item->object ) ? (string) $item->object : '',
			'object_id' => isset( $item->object_id ) ? (int) $item->object_id : 0,
			'parent_id' => isset( $item->menu_item_parent ) ? (int) $item->menu_item_parent : 0,
			'position'  => (int) $item->menu_order,
		);
	}

	private function normalized_locations() {
		$registered = get_registered_nav_menus();
		$assigned = get_nav_menu_locations();
		$locations = array();
		foreach ( $registered as $location => $description ) {
			$locations[ (string) $location ] = isset( $assigned[ $location ] ) ? (int) $assigned[ $location ] : 0;
		}
		ksort( $locations, SORT_STRING );
		$payload = array(
			'registered'  => array_keys( $locations ),
			'assignments' => $locations,
		);
		$payload['state_token'] = hash( 'sha256', wp_json_encode( $payload ) );
		return $payload;
	}
}
