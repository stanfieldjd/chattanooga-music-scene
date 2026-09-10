<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
$admin = wp_get_current_user();
$admin->add_cap( 'edit_nova_settings' );

$list = wp_get_ability( 'chattanooga-cms-admin/list-registered-settings' );
$get = wp_get_ability( 'chattanooga-cms-admin/get-registered-setting' );
$update = wp_get_ability( 'chattanooga-cms-admin/update-registered-setting' );
if ( ! $list instanceof WP_Ability || ! $get instanceof WP_Ability || ! $update instanceof WP_Ability ) {
	fwrite( STDERR, "Registered Settings API abilities are missing.\n" );
	exit( 1 );
}

$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
if ( ! $catalog instanceof WP_Ability ) {
	fwrite( STDERR, "Universal catalog is unavailable during Settings proof.\n" );
	exit( 1 );
}
$catalog_result = $catalog->execute( array() );
if ( is_wp_error( $catalog_result ) ) {
	fwrite( STDERR, "Universal catalog failed during Settings proof.\n" );
	exit( 1 );
}
foreach ( (array) ( $catalog_result['items'] ?? array() ) as $item ) {
	$target = (string) ( $item['target'] ?? '' );
	$route = (string) ( $item['route'] ?? '' );
	if ( 0 === strpos( $target, 'nova-settings/' ) || false !== strpos( $route, '/nova-settings/' ) ) {
		fwrite( STDERR, "Nova fixture unexpectedly exposed a provider Ability or REST route.\n" );
		exit( 1 );
	}
}

update_option( 'nova_private_setting', 'initial private value', false );
update_option( 'nova_rest_setting', 'visible rest value', false );
delete_option( 'nova_absent_setting' );
delete_option( 'nova_custom_setting' );

$inventory = $list->execute( array() );
if ( is_wp_error( $inventory ) || empty( $inventory['items'] ) ) {
	fwrite( STDERR, "Registered Settings inventory failed.\n" );
	exit( 1 );
}
$by_name = array();
foreach ( $inventory['items'] as $item ) {
	if ( isset( $item['setting'] ) ) {
		$by_name[ $item['setting'] ] = $item;
	}
	if ( array_key_exists( 'value', $item ) ) {
		fwrite( STDERR, "Settings inventory disclosed a stored value.\n" );
		exit( 1 );
	}
}
foreach ( array( 'nova_private_setting', 'nova_rest_setting', 'nova_absent_setting', 'nova_custom_setting' ) as $required ) {
	if ( ! isset( $by_name[ $required ] ) ) {
		fwrite( STDERR, "Registered setting missing from bounded inventory: {$required}\n" );
		exit( 1 );
	}
}

$private = $get->execute( array( 'setting' => 'nova_private_setting' ) );
if ( is_wp_error( $private ) || empty( $private['exists'] ) || ! empty( $private['show_in_rest'] ) || ! empty( $private['value_exposed'] ) || array_key_exists( 'value', $private ) || ! preg_match( '/^[a-f0-9]{64}$/', (string) ( $private['state_token'] ?? '' ) ) ) {
	fwrite( STDERR, "Non-REST registered setting disclosure boundary failed.\n" );
	exit( 1 );
}

$rest = $get->execute( array( 'setting' => 'nova_rest_setting' ) );
if ( is_wp_error( $rest ) || empty( $rest['show_in_rest'] ) || empty( $rest['value_exposed'] ) || 'visible rest value' !== ( $rest['value'] ?? null ) ) {
	fwrite( STDERR, "REST-visible registered setting did not preserve its existing disclosure contract.\n" );
	exit( 1 );
}

$absent = $get->execute( array( 'setting' => 'nova_absent_setting' ) );
if ( is_wp_error( $absent ) || ! empty( $absent['exists'] ) || array_key_exists( 'value', $absent ) || ! preg_match( '/^[a-f0-9]{64}$/', (string) ( $absent['state_token'] ?? '' ) ) ) {
	fwrite( STDERR, "Absent registered setting was not distinguished from its registered default.\n" );
	exit( 1 );
}

$private_before = $get->execute( array( 'setting' => 'nova_private_setting' ) );
$sanitized = $update->execute(
	array(
		'setting'              => 'nova_private_setting',
		'value'                => '  mixed Case  ',
		'expected_state_token' => $private_before['state_token'],
	)
);
if ( is_wp_error( $sanitized ) || empty( $sanitized['updated'] ) || empty( $sanitized['sanitized'] ) || 'option-state-only' !== ( $sanitized['rollback_scope'] ?? '' ) || array_key_exists( 'value', $sanitized ) ) {
	fwrite( STDERR, 'Sanitized non-REST setting update failed: ' . ( is_wp_error( $sanitized ) ? $sanitized->get_error_code() . ' ' . $sanitized->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}
if ( 'MIXED CASE' !== get_option( 'nova_private_setting' ) ) {
	fwrite( STDERR, "Registered sanitizer was not preserved by the universal Settings update.\n" );
	exit( 1 );
}
$private_after = $get->execute( array( 'setting' => 'nova_private_setting' ) );
if ( is_wp_error( $private_after ) || hash_equals( $private_before['state_token'], $private_after['state_token'] ) ) {
	fwrite( STDERR, "Settings state token did not change after verified mutation.\n" );
	exit( 1 );
}

$stale_token = $private_after['state_token'];
update_option( 'nova_private_setting', 'external change', false );
$stale = $update->execute(
	array(
		'setting'              => 'nova_private_setting',
		'value'                => 'must not win',
		'expected_state_token' => $stale_token,
	)
);
if ( ! is_wp_error( $stale ) || 'cmsa_setting_state_conflict' !== $stale->get_error_code() || 'EXTERNAL CHANGE' !== get_option( 'nova_private_setting' ) ) {
	fwrite( STDERR, "Stale Settings state token did not block mutation before write.\n" );
	exit( 1 );
}

$unknown_name = 'nova_completely_unregistered_option';
delete_option( $unknown_name );
$unknown = $update->execute(
	array(
		'setting'              => $unknown_name,
		'value'                => 'forbidden',
		'expected_state_token' => str_repeat( '0', 64 ),
	)
);
if ( ! is_wp_error( $unknown ) || false !== get_option( $unknown_name, false ) ) {
	fwrite( STDERR, "Arbitrary unregistered option mutation was not blocked.\n" );
	exit( 1 );
}

$private_current = $get->execute( array( 'setting' => 'nova_private_setting' ) );
$rollback_test = $update->execute(
	array(
		'setting'              => 'nova_private_setting',
		'value'                => 'trigger-verify-fail',
		'expected_state_token' => $private_current['state_token'],
	)
);
if ( ! is_wp_error( $rollback_test ) || 'cmsa_setting_update_verification_failed' !== $rollback_test->get_error_code() || 'EXTERNAL CHANGE' !== get_option( 'nova_private_setting' ) ) {
	fwrite( STDERR, "Failed Settings readback did not restore the exact prior option state.\n" );
	exit( 1 );
}

$user_id = wp_create_user( 'nova_settings_operator', wp_generate_password( 24, true, true ), 'nova-settings@example.invalid' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "Could not create bounded Settings capability test user.\n" );
	exit( 1 );
}
$operator = new WP_User( $user_id );
$operator->set_role( 'subscriber' );
$operator->add_cap( 'edit_nova_settings' );
wp_set_current_user( $user_id );

if ( true !== $get->check_permissions( array( 'setting' => 'nova_custom_setting' ) ) || false !== $get->check_permissions( array( 'setting' => 'nova_private_setting' ) ) ) {
	fwrite( STDERR, "Settings group capability filter was not preserved.\n" );
	exit( 1 );
}
$custom = $get->execute( array( 'setting' => 'nova_custom_setting' ) );
if ( is_wp_error( $custom ) || ! empty( $custom['exists'] ) ) {
	fwrite( STDERR, "Custom-capability registered setting could not be inspected.\n" );
	exit( 1 );
}
$custom_update = $update->execute(
	array(
		'setting'              => 'nova_custom_setting',
		'value'                => 150,
		'expected_state_token' => $custom['state_token'],
	)
);
if ( is_wp_error( $custom_update ) || empty( $custom_update['updated'] ) || 100 !== get_option( 'nova_custom_setting' ) ) {
	fwrite( STDERR, "Custom-capability setting update or integer sanitizer failed.\n" );
	exit( 1 );
}
$operator_inventory = $list->execute( array() );
$operator_names = array_map( static function ( $item ) { return $item['setting'] ?? ''; }, (array) ( $operator_inventory['items'] ?? array() ) );
if ( ! in_array( 'nova_custom_setting', $operator_names, true ) || in_array( 'nova_private_setting', $operator_names, true ) ) {
	fwrite( STDERR, "Settings inventory did not filter registrations by their option-page capability.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( false !== $list->check_permissions( array() ) || false !== $get->check_permissions( array( 'setting' => 'nova_private_setting' ) ) || false !== $update->check_permissions( array( 'setting' => 'nova_private_setting', 'value' => 'x', 'expected_state_token' => str_repeat( '0', 64 ) ) ) ) {
	fwrite( STDERR, "Anonymous Settings administration was not blocked.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
$admin = wp_get_current_user();
$admin->remove_cap( 'edit_nova_settings' );
wp_delete_user( $user_id );
delete_option( 'nova_private_setting' );
delete_option( 'nova_rest_setting' );
delete_option( 'nova_absent_setting' );
delete_option( 'nova_custom_setting' );

echo "cmsa-v2-settings: PASS provider_ability=absent provider_rest=absent registry=discovered nonrest_value=protected rest_value=existing_contract absent_default=distinguished sanitizer=preserved state_token=verified stale_write=blocked unregistered_option=blocked readback_failure=rolled_back group_capability=preserved inventory_capability=filtered admin_boundary=verified\n";
exit( 0 );
