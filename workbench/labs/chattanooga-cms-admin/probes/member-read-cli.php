<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "member-read-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "member-read-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

$token = strtolower( wp_generate_password( 10, false, false ) );
$prefix = 'cmsa_member_' . $token;
$password_a = 'CMSA_MEMBER_PASSWORD_A_' . $token;
$password_b = 'CMSA_MEMBER_PASSWORD_B_' . $token;
$email_a = $prefix . '_a@example.invalid';
$email_b = $prefix . '_b@example.invalid';
$id_a = wp_create_user( $prefix . '_a', $password_a, $email_a );
$id_b = wp_create_user( $prefix . '_b', $password_b, $email_b );
if ( is_wp_error( $id_a ) || is_wp_error( $id_b ) ) {
	fwrite( STDERR, "member-read-cli: member fixture creation failed.\n" );
	exit( 1 );
}
$user_a = new WP_User( $id_a );
$user_b = new WP_User( $id_b );
$user_a->set_role( 'subscriber' );
$user_b->set_role( 'editor' );
clean_user_cache( $id_a );
clean_user_cache( $id_b );

$members = new CMSA_Members();
$page_one = $members->list_members( array( 'search' => $prefix, 'page' => 1, 'per_page' => 1 ) );
$page_two = $members->list_members( array( 'search' => $prefix, 'page' => 2, 'per_page' => 1 ) );
if ( is_wp_error( $page_one ) || is_wp_error( $page_two ) || 2 !== (int) $page_one['total'] || 1 !== count( $page_one['items'] ) || 1 !== count( $page_two['items'] ) || (int) $page_one['items'][0]['id'] === (int) $page_two['items'][0]['id'] ) {
	fwrite( STDERR, "member-read-cli: bounded pagination failed.\n" );
	exit( 1 );
}

$list_keys = array_keys( $page_one['items'][0] );
sort( $list_keys, SORT_STRING );
$expected_list_keys = array( 'display_name', 'id', 'registered_gmt', 'roles', 'username' );
sort( $expected_list_keys, SORT_STRING );
if ( $list_keys !== $expected_list_keys ) {
	fwrite( STDERR, "member-read-cli: member list exposed an unexpected field.\n" );
	exit( 1 );
}

$email_search = $members->list_members( array( 'search' => $email_b, 'per_page' => 10 ) );
if ( is_wp_error( $email_search ) || 1 !== (int) $email_search['total'] || (int) $email_search['items'][0]['id'] !== (int) $id_b ) {
	fwrite( STDERR, "member-read-cli: email search failed.\n" );
	exit( 1 );
}

$role_search = $members->list_members( array( 'search' => $prefix, 'role' => 'subscriber', 'per_page' => 10 ) );
if ( is_wp_error( $role_search ) || 1 !== (int) $role_search['total'] || (int) $role_search['items'][0]['id'] !== (int) $id_a ) {
	fwrite( STDERR, "member-read-cli: role filter failed.\n" );
	exit( 1 );
}
$invalid_role = $members->list_members( array( 'role' => 'cmsa_role_does_not_exist' ) );
if ( ! is_wp_error( $invalid_role ) || 'cmsa_member_role_invalid' !== $invalid_role->get_error_code() ) {
	fwrite( STDERR, "member-read-cli: invalid role did not fail closed.\n" );
	exit( 1 );
}

$detail = $members->get_member( $id_a );
if ( is_wp_error( $detail ) || (int) $detail['member']['id'] !== (int) $id_a || $email_a !== $detail['member']['email'] || array( 'subscriber' ) !== $detail['member']['roles'] || 64 !== strlen( $detail['member']['profile_state'] ) || 64 !== strlen( $detail['member']['roles_state'] ) ) {
	fwrite( STDERR, "member-read-cli: bounded member detail/state failed.\n" );
	exit( 1 );
}
$detail_keys = array_keys( $detail['member'] );
sort( $detail_keys, SORT_STRING );
$expected_detail_keys = array( 'display_name', 'email', 'id', 'profile_state', 'registered_gmt', 'roles', 'roles_state', 'url', 'username' );
sort( $expected_detail_keys, SORT_STRING );
if ( $detail_keys !== $expected_detail_keys ) {
	fwrite( STDERR, "member-read-cli: member detail exposed an unexpected field.\n" );
	exit( 1 );
}

$roles = $members->list_roles();
$role_slugs = array();
foreach ( $roles['items'] as $role ) {
	$role_slugs[] = $role['slug'];
}
if ( ! in_array( 'subscriber', $role_slugs, true ) || ! in_array( 'editor', $role_slugs, true ) || ! in_array( 'administrator', $role_slugs, true ) ) {
	fwrite( STDERR, "member-read-cli: role inventory is incomplete.\n" );
	exit( 1 );
}
$member_roles = $members->get_member_roles( $id_b );
if ( is_wp_error( $member_roles ) || (int) $member_roles['id'] !== (int) $id_b || array( 'editor' ) !== $member_roles['roles'] || 64 !== strlen( $member_roles['roles_state'] ) ) {
	fwrite( STDERR, "member-read-cli: member role state read failed.\n" );
	exit( 1 );
}

$missing = $members->get_member( 2147483647 );
if ( ! is_wp_error( $missing ) || 'cmsa_member_not_found' !== $missing->get_error_code() ) {
	fwrite( STDERR, "member-read-cli: missing member did not fail closed.\n" );
	exit( 1 );
}

$serialized = wp_json_encode( array( $page_one, $page_two, $email_search, $role_search, $detail, $roles, $member_roles ) );
foreach ( array( $password_a, $password_b, 'user_pass', 'user_activation_key', 'session_tokens' ) as $forbidden ) {
	if ( false !== strpos( $serialized, $forbidden ) ) {
		fwrite( STDERR, "member-read-cli: credential/private field escaped bounded response.\n" );
		exit( 1 );
	}
}

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $id_a );
wp_delete_user( $id_b );

echo "member-read-cli: PASS list=bounded search=email role=verified detail=bounded states=verified credentials=absent\n";
