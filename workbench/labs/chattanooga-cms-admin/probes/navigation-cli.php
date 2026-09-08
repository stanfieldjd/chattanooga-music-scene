<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "navigation-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "navigation-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
register_nav_menu( 'cmsa_test_primary', 'CMSA Disposable Primary' );

$navigation = new CMSA_Navigation();
$menu_ids = array();
$page_ids = array();
$original_locations = get_theme_mod( 'nav_menu_locations', array() );
$original_locations = is_array( $original_locations ) ? $original_locations : array();

$cleanup = static function () use ( &$menu_ids, &$page_ids, $original_locations ) {
	set_theme_mod( 'nav_menu_locations', $original_locations );
	foreach ( array_reverse( $menu_ids ) as $menu_id ) {
		wp_delete_nav_menu( (int) $menu_id );
	}
	foreach ( array_reverse( $page_ids ) as $page_id ) {
		wp_delete_post( (int) $page_id, true );
	}
};
$fail = static function ( $message ) use ( $cleanup ) {
	$cleanup();
	fwrite( STDERR, "navigation-cli: {$message}\n" );
	exit( 1 );
};

$token = strtolower( wp_generate_password( 8, false, false ) );
$target_result = $navigation->create_menu( array( 'name' => 'CMSA Navigation ' . $token ) );
if ( is_wp_error( $target_result ) || empty( $target_result['created'] ) ) {
	$fail( 'target menu create failed.' );
}
$target_menu_id = (int) $target_result['menu']['id'];
$menu_ids[] = $target_menu_id;

$control_result = $navigation->create_menu( array( 'name' => 'CMSA Navigation Control ' . $token ) );
if ( is_wp_error( $control_result ) || empty( $control_result['created'] ) ) {
	$fail( 'control menu create failed.' );
}
$control_menu_id = (int) $control_result['menu']['id'];
$menu_ids[] = $control_menu_id;
$control_state = $control_result['menu']['state_token'];

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'CMSA Navigation Page ' . $token,
		'post_content' => '<p>Disposable navigation target.</p>',
	),
	true
);
if ( is_wp_error( $page_id ) ) {
	$fail( 'page fixture create failed.' );
}
$page_id = (int) $page_id;
$page_ids[] = $page_id;

$listed = $navigation->list_menus();
if ( is_wp_error( $listed ) || empty( $listed['locations']['registered'] ) || ! in_array( 'cmsa_test_primary', $listed['locations']['registered'], true ) ) {
	$fail( 'menu/location list failed.' );
}
$target_read = $navigation->get_menu( $target_menu_id );
if ( is_wp_error( $target_read ) || $target_read['menu']['state_token'] !== $target_result['menu']['state_token'] ) {
	$fail( 'menu read/state token failed.' );
}

$stale_item = $navigation->upsert_item(
	array(
		'menu_id'             => $target_menu_id,
		'expected_menu_state' => str_repeat( '0', 64 ),
		'type'                 => 'post_type',
		'object_id'            => $page_id,
		'title'                => 'Scene',
	)
);
if ( ! is_wp_error( $stale_item ) || 'cmsa_navigation_conflict' !== $stale_item->get_error_code() ) {
	$fail( 'stale item mutation did not fail closed.' );
}

$page_item = $navigation->upsert_item(
	array(
		'menu_id'             => $target_menu_id,
		'expected_menu_state' => $target_read['menu']['state_token'],
		'type'                 => 'post_type',
		'object_id'            => $page_id,
		'title'                => 'Scene',
		'position'             => 1,
	)
);
if ( is_wp_error( $page_item ) || empty( $page_item['created'] ) || 'page' !== $page_item['item']['object'] || $page_id !== (int) $page_item['item']['object_id'] ) {
	$fail( 'page navigation item create/readback failed.' );
}
$page_item_id = (int) $page_item['item']['id'];

$invalid_url = $navigation->upsert_item(
	array(
		'menu_id'             => $target_menu_id,
		'expected_menu_state' => $page_item['menu']['state_token'],
		'type'                 => 'custom',
		'url'                  => 'javascript:alert(1)',
		'title'                => 'Unsafe',
	)
);
if ( ! is_wp_error( $invalid_url ) || 'cmsa_navigation_item_url' !== $invalid_url->get_error_code() ) {
	$fail( 'unsafe custom URL did not fail closed.' );
}

$custom_item = $navigation->upsert_item(
	array(
		'menu_id'             => $target_menu_id,
		'expected_menu_state' => $page_item['menu']['state_token'],
		'type'                 => 'custom',
		'url'                  => '/upcoming/',
		'title'                => 'Upcoming',
		'position'             => 2,
	)
);
if ( is_wp_error( $custom_item ) || empty( $custom_item['created'] ) || '/upcoming/' !== $custom_item['item']['url'] ) {
	$fail( 'custom navigation item create/readback failed.' );
}
$custom_item_id = (int) $custom_item['item']['id'];

$cycle = $navigation->upsert_item(
	array(
		'menu_id'             => $target_menu_id,
		'expected_menu_state' => $custom_item['menu']['state_token'],
		'item_id'              => $page_item_id,
		'parent_id'            => $page_item_id,
	)
);
if ( ! is_wp_error( $cycle ) || 'cmsa_navigation_item_parent_cycle' !== $cycle->get_error_code() ) {
	$fail( 'navigation parent cycle did not fail closed.' );
}

$custom_update = $navigation->upsert_item(
	array(
		'menu_id'             => $target_menu_id,
		'expected_menu_state' => $custom_item['menu']['state_token'],
		'item_id'              => $custom_item_id,
		'title'                => 'Tonight',
		'url'                  => '/tonight/',
	)
);
if ( is_wp_error( $custom_update ) || empty( $custom_update['updated'] ) || 'Tonight' !== $custom_update['item']['title'] || '/tonight/' !== $custom_update['item']['url'] ) {
	$fail( 'custom navigation item update/readback failed.' );
}

$before_fault_state = $custom_update['menu']['state_token'];
$filter = null;
$filter = static function ( $data, $postarr ) use ( &$filter, $custom_item_id ) {
	if ( isset( $data['post_type'] ) && 'nav_menu_item' === $data['post_type'] && isset( $postarr['ID'] ) && (int) $postarr['ID'] === $custom_item_id ) {
		remove_filter( 'wp_insert_post_data', $filter, 10 );
		$data['post_title'] = 'Injected Navigation Corruption';
	}
	return $data;
};
add_filter( 'wp_insert_post_data', $filter, 10, 2 );
$fault_result = $navigation->upsert_item(
	array(
		'menu_id'             => $target_menu_id,
		'expected_menu_state' => $before_fault_state,
		'item_id'              => $custom_item_id,
		'title'                => 'Fault Target',
	)
);
remove_filter( 'wp_insert_post_data', $filter, 10 );
if ( ! is_wp_error( $fault_result ) || 'cmsa_navigation_item_verify' !== $fault_result->get_error_code() || empty( $fault_result->get_error_data()['rolled_back'] ) ) {
	$fail( 'navigation item verification fault did not trigger rollback.' );
}
$after_fault = $navigation->get_menu( $target_menu_id );
if ( is_wp_error( $after_fault ) || $before_fault_state !== $after_fault['menu']['state_token'] ) {
	$fail( 'navigation item rollback did not restore managed state.' );
}

$locations_before = $navigation->list_menus();
if ( is_wp_error( $locations_before ) ) {
	$fail( 'navigation location state read failed.' );
}
$stale_location = $navigation->set_location(
	array(
		'location'                 => 'cmsa_test_primary',
		'menu_id'                  => $target_menu_id,
		'expected_locations_state' => str_repeat( 'f', 64 ),
	)
);
if ( ! is_wp_error( $stale_location ) || 'cmsa_navigation_locations_conflict' !== $stale_location->get_error_code() ) {
	$fail( 'stale navigation location mutation did not fail closed.' );
}

$location_set = $navigation->set_location(
	array(
		'location'                 => 'cmsa_test_primary',
		'menu_id'                  => $target_menu_id,
		'expected_locations_state' => $locations_before['locations']['state_token'],
	)
);
if ( is_wp_error( $location_set ) || $target_menu_id !== (int) $location_set['locations']['assignments']['cmsa_test_primary'] ) {
	$fail( 'navigation location assignment failed.' );
}

$location_state_before_fault = $location_set['locations']['state_token'];
$location_filter = null;
$location_filter = static function ( $value ) use ( &$location_filter ) {
	remove_filter( 'pre_set_theme_mod_nav_menu_locations', $location_filter, 10 );
	if ( is_array( $value ) ) {
		$value['cmsa_test_primary'] = 999999;
	}
	return $value;
};
add_filter( 'pre_set_theme_mod_nav_menu_locations', $location_filter, 10, 1 );
$location_fault = $navigation->set_location(
	array(
		'location'                 => 'cmsa_test_primary',
		'menu_id'                  => $control_menu_id,
		'expected_locations_state' => $location_state_before_fault,
	)
);
remove_filter( 'pre_set_theme_mod_nav_menu_locations', $location_filter, 10 );
if ( ! is_wp_error( $location_fault ) || 'cmsa_navigation_location_verify' !== $location_fault->get_error_code() || empty( $location_fault->get_error_data()['rolled_back'] ) ) {
	$fail( 'navigation location verification fault did not trigger rollback.' );
}
$locations_after_fault = $navigation->list_menus();
if ( is_wp_error( $locations_after_fault ) || $location_state_before_fault !== $locations_after_fault['locations']['state_token'] ) {
	$fail( 'navigation location rollback did not restore assignment state.' );
}

$before_delete = $navigation->get_menu( $target_menu_id );
if ( is_wp_error( $before_delete ) ) {
	$fail( 'navigation menu reread before delete failed.' );
}
$unconfirmed_delete = $navigation->delete_item(
	array(
		'menu_id'             => $target_menu_id,
		'item_id'              => $custom_item_id,
		'expected_menu_state' => $before_delete['menu']['state_token'],
		'confirm_delete'       => false,
	)
);
if ( ! is_wp_error( $unconfirmed_delete ) || 'cmsa_navigation_delete_confirmation' !== $unconfirmed_delete->get_error_code() || ! get_post( $custom_item_id ) ) {
	$fail( 'unconfirmed navigation item delete did not fail closed.' );
}
$deleted = $navigation->delete_item(
	array(
		'menu_id'             => $target_menu_id,
		'item_id'              => $custom_item_id,
		'expected_menu_state' => $before_delete['menu']['state_token'],
		'confirm_delete'       => true,
	)
);
if ( is_wp_error( $deleted ) || empty( $deleted['deleted'] ) || get_post( $custom_item_id ) instanceof WP_Post || ! ( get_post( $page_id ) instanceof WP_Post ) ) {
	$fail( 'navigation item delete verification or target isolation failed.' );
}

$control_after = $navigation->get_menu( $control_menu_id );
if ( is_wp_error( $control_after ) || $control_state !== $control_after['menu']['state_token'] ) {
	$fail( 'unrelated navigation menu changed.' );
}

$cleanup();
echo "navigation-cli: PASS menu=create-list-get item=page-custom-conflict-cycle-update-rollback-delete location=conflict-assign-rollback unrelated=unchanged third-party=untouched\n";
