<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded read-only WordPress member administration.
 *
 * This service intentionally exposes only selected account fields. It does not
 * read arbitrary usermeta, credentials, activation keys, or session tokens.
 */
final class CMSA_Members {
	const MAX_PER_PAGE = 100;

	public function list_members( array $input = array() ) {
		$page = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 20;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$search = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
		$role = isset( $input['role'] ) ? sanitize_key( (string) $input['role'] ) : '';

		if ( '' !== $role && ! $this->role_exists( $role ) ) {
			return new WP_Error( 'cmsa_member_role_invalid', 'The requested role does not exist.' );
		}

		$args = array(
			'number'      => $per_page,
			'paged'       => $page,
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'count_total' => true,
		);
		if ( '' !== $role ) {
			$args['role'] = $role;
		}
		if ( '' !== $search ) {
			$args['search'] = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$query = new WP_User_Query( $args );
		$items = array();
		foreach ( $query->get_results() as $user ) {
			if ( ! $user instanceof WP_User ) {
				$user = new WP_User( (int) $user );
			}
			if ( $user->exists() ) {
				$items[] = $this->normalize_member( $user, false );
			}
		}

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => (int) $query->get_total(),
			'items'    => $items,
		);
	}

	public function get_member( $id ) {
		$user = get_user_by( 'id', (int) $id );
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return new WP_Error( 'cmsa_member_not_found', 'The requested member was not found.' );
		}

		return array( 'member' => $this->normalize_member( $user, true ) );
	}

	public function list_roles() {
		$roles = wp_roles();
		$items = array();
		foreach ( $roles->roles as $slug => $definition ) {
			$items[] = array(
				'slug' => sanitize_key( (string) $slug ),
				'name' => sanitize_text_field( isset( $definition['name'] ) ? (string) $definition['name'] : (string) $slug ),
			);
		}
		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( $a['slug'], $b['slug'] );
			}
		);

		return array( 'items' => $items );
	}

	public function get_member_roles( $id ) {
		$user = get_user_by( 'id', (int) $id );
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return new WP_Error( 'cmsa_member_not_found', 'The requested member was not found.' );
		}

		return array(
			'id'          => (int) $user->ID,
			'roles'       => $this->roles_for( $user ),
			'roles_state' => $this->roles_state( $user ),
		);
	}

	public function profile_state( WP_User $user ) {
		$payload = array(
			'id'           => (int) $user->ID,
			'email'        => (string) $user->user_email,
			'display_name' => (string) $user->display_name,
			'url'          => (string) $user->user_url,
		);
		return hash( 'sha256', wp_json_encode( $payload ) );
	}

	public function roles_state( WP_User $user ) {
		return hash( 'sha256', wp_json_encode( $this->roles_for( $user ) ) );
	}

	public function roles_for( WP_User $user ) {
		$roles = array_values( array_unique( array_map( 'sanitize_key', (array) $user->roles ) ) );
		sort( $roles, SORT_STRING );
		return $roles;
	}

	private function normalize_member( WP_User $user, $include_detail ) {
		$member = array(
			'id'             => (int) $user->ID,
			'username'       => sanitize_user( (string) $user->user_login, true ),
			'display_name'   => sanitize_text_field( (string) $user->display_name ),
			'registered_gmt' => sanitize_text_field( (string) $user->user_registered ),
			'roles'          => $this->roles_for( $user ),
		);
		if ( $include_detail ) {
			$member['email'] = sanitize_email( (string) $user->user_email );
			$member['url'] = esc_url_raw( (string) $user->user_url );
			$member['profile_state'] = $this->profile_state( $user );
			$member['roles_state'] = $this->roles_state( $user );
		}

		return $member;
	}

	private function role_exists( $role ) {
		$roles = wp_roles();
		return isset( $roles->roles[ $role ] );
	}
}
