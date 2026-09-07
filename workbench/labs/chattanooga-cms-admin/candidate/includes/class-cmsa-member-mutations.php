<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Member_Mutations {
	private $members;

	public function __construct( CMSA_Members $members ) {
		$this->members = $members;
	}

	public function update_profile( array $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$user = get_user_by( 'id', $id );
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return new WP_Error( 'cmsa_member_not_found', 'The requested member was not found.' );
		}
		if ( ! current_user_can( 'edit_user', $id ) ) {
			return new WP_Error( 'cmsa_member_edit_permission', 'Current user cannot edit this member.' );
		}

		$expected = isset( $input['expected_profile_state'] ) ? sanitize_text_field( (string) $input['expected_profile_state'] ) : '';
		$current_state = $this->members->profile_state( $user );
		if ( '' === $expected ) {
			return new WP_Error( 'cmsa_member_profile_expected_state', 'Expected profile state is required.' );
		}
		if ( ! hash_equals( $current_state, $expected ) ) {
			return new WP_Error( 'cmsa_member_profile_conflict', 'Member profile changed after it was read; update was not attempted.', array( 'current_profile_state' => $current_state ) );
		}

		$update = array( 'ID' => $id );
		$target = array(
			'email'        => (string) $user->user_email,
			'display_name' => (string) $user->display_name,
			'url'          => (string) $user->user_url,
		);
		$changed = false;

		if ( array_key_exists( 'display_name', $input ) ) {
			$display_name = sanitize_text_field( (string) $input['display_name'] );
			if ( '' === $display_name ) {
				return new WP_Error( 'cmsa_member_profile_display_name', 'Display name cannot be empty.' );
			}
			$update['display_name'] = $display_name;
			$target['display_name'] = $display_name;
			$changed = $changed || $display_name !== (string) $user->display_name;
		}
		if ( array_key_exists( 'url', $input ) ) {
			$url = esc_url_raw( (string) $input['url'] );
			$update['user_url'] = $url;
			$target['url'] = $url;
			$changed = $changed || $url !== (string) $user->user_url;
		}
		if ( ! $changed ) {
			return new WP_Error( 'cmsa_member_profile_no_change', 'No member profile change was requested.' );
		}

		$previous = array(
			'email'        => (string) $user->user_email,
			'display_name' => (string) $user->display_name,
			'url'          => (string) $user->user_url,
		);
		$result = wp_update_user( $update );
		if ( is_wp_error( $result ) || ! $result ) {
			return new WP_Error( 'cmsa_member_profile_update', 'Could not update the member profile.' );
		}

		$after = get_user_by( 'id', $id );
		if ( ! $after instanceof WP_User || ! $this->profile_matches( $after, $target ) ) {
			$rolled_back = $this->restore_profile( $id, $previous );
			return new WP_Error( 'cmsa_member_profile_verify', 'Member profile verification failed.', array( 'rolled_back' => $rolled_back ) );
		}

		$readback = $this->members->get_member( $id );
		if ( is_wp_error( $readback ) ) {
			$rolled_back = $this->restore_profile( $id, $previous );
			return new WP_Error( 'cmsa_member_profile_readback', 'Member profile changed but readback verification failed.', array( 'rolled_back' => $rolled_back ) );
		}

		CMSA_Audit::record( 'update-member-profile', (string) $id, 'success', array( 'profile_state' => $readback['member']['profile_state'] ) );
		return array( 'updated' => true, 'member' => $readback['member'] );
	}

	public function set_roles( array $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$user = get_user_by( 'id', $id );
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return new WP_Error( 'cmsa_member_not_found', 'The requested member was not found.' );
		}
		if ( get_current_user_id() === $id ) {
			return new WP_Error( 'cmsa_member_roles_self', 'This role operation cannot change the current account.' );
		}
		if ( ! current_user_can( 'promote_user', $id ) ) {
			return new WP_Error( 'cmsa_member_roles_permission', 'Current user cannot change this member role state.' );
		}

		$expected = isset( $input['expected_roles_state'] ) ? sanitize_text_field( (string) $input['expected_roles_state'] ) : '';
		$current_state = $this->members->roles_state( $user );
		if ( '' === $expected ) {
			return new WP_Error( 'cmsa_member_roles_expected_state', 'Expected role state is required.' );
		}
		if ( ! hash_equals( $current_state, $expected ) ) {
			return new WP_Error( 'cmsa_member_roles_conflict', 'Member roles changed after they were read; role mutation was not attempted.', array( 'current_roles_state' => $current_state ) );
		}

		$roles = isset( $input['roles'] ) && is_array( $input['roles'] ) ? array_values( array_unique( array_map( 'sanitize_key', $input['roles'] ) ) ) : array();
		$roles = array_values( array_filter( $roles ) );
		sort( $roles, SORT_STRING );
		if ( ! $roles ) {
			return new WP_Error( 'cmsa_member_roles_empty', 'At least one target role is required in this role-management gate.' );
		}

		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		$editable_roles = get_editable_roles();
		foreach ( $roles as $role ) {
			if ( ! isset( $editable_roles[ $role ] ) ) {
				return new WP_Error( 'cmsa_member_role_not_editable', 'One or more requested roles are not assignable by the current user.' );
			}
		}

		$previous = $this->members->roles_for( $user );
		if ( $previous === $roles ) {
			return new WP_Error( 'cmsa_member_roles_no_change', 'Member already has the requested role state.' );
		}

		$this->apply_roles( $id, $roles );
		$after = get_user_by( 'id', $id );
		if ( ! $after instanceof WP_User || $this->members->roles_for( $after ) !== $roles ) {
			$rolled_back = $this->restore_roles( $id, $previous );
			return new WP_Error( 'cmsa_member_roles_verify', 'Member role verification failed.', array( 'rolled_back' => $rolled_back ) );
		}

		$readback = $this->members->get_member_roles( $id );
		if ( is_wp_error( $readback ) ) {
			$rolled_back = $this->restore_roles( $id, $previous );
			return new WP_Error( 'cmsa_member_roles_readback', 'Member roles changed but readback verification failed.', array( 'rolled_back' => $rolled_back ) );
		}

		CMSA_Audit::record( 'set-member-roles', (string) $id, 'success', array( 'roles' => $readback['roles'], 'roles_state' => $readback['roles_state'] ) );
		return array( 'updated' => true, 'previous_roles' => $previous, 'roles' => $readback['roles'], 'roles_state' => $readback['roles_state'] );
	}

	private function profile_matches( WP_User $user, array $expected ) {
		return $expected['email'] === (string) $user->user_email
			&& $expected['display_name'] === (string) $user->display_name
			&& $expected['url'] === (string) $user->user_url;
	}

	private function restore_profile( $id, array $previous ) {
		$result = wp_update_user(
			array(
				'ID'           => (int) $id,
				'display_name' => $previous['display_name'],
				'user_url'     => $previous['url'],
			)
		);
		if ( is_wp_error( $result ) || ! $result ) {
			return false;
		}
		$restored = get_user_by( 'id', (int) $id );
		return $restored instanceof WP_User && $this->profile_matches( $restored, $previous );
	}

	private function apply_roles( $id, array $roles ) {
		$user = new WP_User( (int) $id );
		$user->set_role( $roles[0] );
		for ( $i = 1, $count = count( $roles ); $i < $count; $i++ ) {
			$user->add_role( $roles[ $i ] );
		}
		clean_user_cache( (int) $id );
	}

	private function restore_roles( $id, array $roles ) {
		if ( ! $roles ) {
			$user = new WP_User( (int) $id );
			$user->set_role( '' );
			clean_user_cache( (int) $id );
			$restored = get_user_by( 'id', (int) $id );
			return $restored instanceof WP_User && array() === $this->members->roles_for( $restored );
		}
		$this->apply_roles( $id, $roles );
		$restored = get_user_by( 'id', (int) $id );
		return $restored instanceof WP_User && $this->members->roles_for( $restored ) === $roles;
	}
}
