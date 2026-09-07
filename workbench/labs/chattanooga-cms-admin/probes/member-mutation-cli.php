<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "member-mutation-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "member-mutation-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

$token = strtolower( wp_generate_password( 10, false, false ) );
$login = 'cmsa_member_mut_' . $token;
$initial_email = $login . '@example.invalid';
$id = wp_create_user( $login, wp_generate_password( 24, true, true ), $initial_email );
if ( is_wp_error( $id ) ) {
	fwrite( STDERR, "member-mutation-cli: target fixture creation failed.\n" );
	exit( 1 );
}
$target = new WP_User( $id );
$target->set_role( 'subscriber' );
wp_update_user( array( 'ID' => $id, 'display_name' => 'CMSA Original', 'user_url' => 'https://example.invalid/original' ) );
clean_user_cache( $id );

$members = new CMSA_Members();
$mutations = new CMSA_Member_Mutations( $members );
$initial = $members->get_member( $id );
if ( is_wp_error( $initial ) ) {
	fwrite( STDERR, "member-mutation-cli: initial read failed.\n" );
	exit( 1 );
}
$initial_profile_state = $initial['member']['profile_state'];
$initial_roles_state = $initial['member']['roles_state'];

$stale_profile = $mutations->update_profile(
	array(
		'id'                     => $id,
		'expected_profile_state' => str_repeat( '0', 64 ),
		'display_name'           => 'Should Not Apply',
	)
);
if ( ! is_wp_error( $stale_profile ) || 'cmsa_member_profile_conflict' !== $stale_profile->get_error_code() ) {
	fwrite( STDERR, "member-mutation-cli: stale profile state did not fail closed.\n" );
	exit( 1 );
}
$unchanged = $members->get_member( $id );
if ( is_wp_error( $unchanged ) || $initial_profile_state !== $unchanged['member']['profile_state'] ) {
	fwrite( STDERR, "member-mutation-cli: stale profile conflict changed target.\n" );
	exit( 1 );
}

$updated_email = $login . '.updated@example.invalid';
$profile_update = $mutations->update_profile(
	array(
		'id'                     => $id,
		'expected_profile_state' => $initial_profile_state,
		'email'                  => $updated_email,
		'display_name'           => 'CMSA Updated',
		'url'                    => 'https://example.invalid/updated',
	)
);
if ( is_wp_error( $profile_update ) || empty( $profile_update['updated'] ) || $updated_email !== $profile_update['member']['email'] || 'CMSA Updated' !== $profile_update['member']['display_name'] || 'https://example.invalid/updated' !== $profile_update['member']['url'] || $initial_profile_state === $profile_update['member']['profile_state'] ) {
	fwrite( STDERR, "member-mutation-cli: profile update/readback failed.\n" );
	exit( 1 );
}
$current_profile_state = $profile_update['member']['profile_state'];

$stale_after_update = $mutations->update_profile(
	array(
		'id'                     => $id,
		'expected_profile_state' => $initial_profile_state,
		'display_name'           => 'Stale Again',
	)
);
if ( ! is_wp_error( $stale_after_update ) || 'cmsa_member_profile_conflict' !== $stale_after_update->get_error_code() ) {
	fwrite( STDERR, "member-mutation-cli: consumed profile state was accepted twice.\n" );
	exit( 1 );
}

$before_profile_fault = $members->get_member( $id );
$profile_fault_used = false;
$profile_fault = static function ( $data, $update, $user_id, $userdata ) use ( $id, &$profile_fault_used ) {
	if ( $update && (int) $user_id === (int) $id && ! $profile_fault_used && isset( $userdata['display_name'] ) && 'CMSA Intended Fault Update' === $userdata['display_name'] ) {
		$profile_fault_used = true;
		$data['display_name'] = 'CMSA Injected Mismatch';
	}
	return $data;
};
add_filter( 'wp_pre_insert_user_data', $profile_fault, 10, 4 );
$profile_fault_result = $mutations->update_profile(
	array(
		'id'                     => $id,
		'expected_profile_state' => $current_profile_state,
		'display_name'           => 'CMSA Intended Fault Update',
	)
);
remove_filter( 'wp_pre_insert_user_data', $profile_fault, 10 );
if ( ! $profile_fault_used || ! is_wp_error( $profile_fault_result ) || 'cmsa_member_profile_verify' !== $profile_fault_result->get_error_code() || true !== $profile_fault_result->get_error_data()['rolled_back'] ) {
	fwrite( STDERR, "member-mutation-cli: profile verification fault did not report rollback.\n" );
	exit( 1 );
}
$after_profile_fault = $members->get_member( $id );
if ( is_wp_error( $after_profile_fault ) || $before_profile_fault['member']['profile_state'] !== $after_profile_fault['member']['profile_state'] ) {
	fwrite( STDERR, "member-mutation-cli: profile rollback was not exact.\n" );
	exit( 1 );
}

$stale_roles = $mutations->set_roles( array( 'id' => $id, 'expected_roles_state' => str_repeat( '0', 64 ), 'roles' => array( 'editor' ) ) );
if ( ! is_wp_error( $stale_roles ) || 'cmsa_member_roles_conflict' !== $stale_roles->get_error_code() ) {
	fwrite( STDERR, "member-mutation-cli: stale role state did not fail closed.\n" );
	exit( 1 );
}
$roles_before = $members->get_member_roles( $id );
if ( is_wp_error( $roles_before ) || $initial_roles_state !== $roles_before['roles_state'] || array( 'subscriber' ) !== $roles_before['roles'] ) {
	fwrite( STDERR, "member-mutation-cli: stale role conflict changed target.\n" );
	exit( 1 );
}

$role_update = $mutations->set_roles( array( 'id' => $id, 'expected_roles_state' => $roles_before['roles_state'], 'roles' => array( 'editor' ) ) );
if ( is_wp_error( $role_update ) || empty( $role_update['updated'] ) || array( 'editor' ) !== $role_update['roles'] || array( 'subscriber' ) !== $role_update['previous_roles'] || $roles_before['roles_state'] === $role_update['roles_state'] ) {
	fwrite( STDERR, "member-mutation-cli: role replacement failed.\n" );
	exit( 1 );
}

$invalid_role = $mutations->set_roles( array( 'id' => $id, 'expected_roles_state' => $role_update['roles_state'], 'roles' => array( 'cmsa_role_does_not_exist' ) ) );
if ( ! is_wp_error( $invalid_role ) || 'cmsa_member_role_not_editable' !== $invalid_role->get_error_code() ) {
	fwrite( STDERR, "member-mutation-cli: invalid role was not rejected.\n" );
	exit( 1 );
}
$after_invalid_role = $members->get_member_roles( $id );
if ( is_wp_error( $after_invalid_role ) || $role_update['roles_state'] !== $after_invalid_role['roles_state'] ) {
	fwrite( STDERR, "member-mutation-cli: invalid role attempt changed target.\n" );
	exit( 1 );
}

$admin_roles = $members->get_member_roles( $admin->ID );
$self_change = $mutations->set_roles( array( 'id' => $admin->ID, 'expected_roles_state' => $admin_roles['roles_state'], 'roles' => array( 'administrator' ) ) );
if ( ! is_wp_error( $self_change ) || 'cmsa_member_roles_self' !== $self_change->get_error_code() ) {
	fwrite( STDERR, "member-mutation-cli: current-account role guard failed.\n" );
	exit( 1 );
}

$back_to_subscriber = $mutations->set_roles( array( 'id' => $id, 'expected_roles_state' => $after_invalid_role['roles_state'], 'roles' => array( 'subscriber' ) ) );
if ( is_wp_error( $back_to_subscriber ) || array( 'subscriber' ) !== $back_to_subscriber['roles'] ) {
	fwrite( STDERR, "member-mutation-cli: role reset fixture failed.\n" );
	exit( 1 );
}
$before_role_fault = $members->get_member_roles( $id );
$role_fault_used = false;
$role_fault = static function ( $user_id, $role, $old_roles ) use ( $id, &$role_fault_used ) {
	if ( (int) $user_id === (int) $id && 'editor' === $role && ! $role_fault_used ) {
		$role_fault_used = true;
		$faulted = new WP_User( $id );
		$faulted->add_role( 'contributor' );
	}
};
add_action( 'set_user_role', $role_fault, 10, 3 );
$role_fault_result = $mutations->set_roles( array( 'id' => $id, 'expected_roles_state' => $before_role_fault['roles_state'], 'roles' => array( 'editor' ) ) );
remove_action( 'set_user_role', $role_fault, 10 );
if ( ! $role_fault_used || ! is_wp_error( $role_fault_result ) || 'cmsa_member_roles_verify' !== $role_fault_result->get_error_code() || true !== $role_fault_result->get_error_data()['rolled_back'] ) {
	fwrite( STDERR, "member-mutation-cli: role verification fault did not report rollback.\n" );
	exit( 1 );
}
$after_role_fault = $members->get_member_roles( $id );
if ( is_wp_error( $after_role_fault ) || $before_role_fault['roles_state'] !== $after_role_fault['roles_state'] || array( 'subscriber' ) !== $after_role_fault['roles'] ) {
	fwrite( STDERR, "member-mutation-cli: role rollback was not exact.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $id );

echo "member-mutation-cli: PASS profile=conflict-update-rollback roles=conflict-replace-invalid-self-rollback protected-surfaces=absent\n";
