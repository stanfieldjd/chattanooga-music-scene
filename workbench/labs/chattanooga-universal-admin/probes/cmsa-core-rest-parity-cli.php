<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this probe runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$catalog = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-cms-admin/catalog' ) : null;
if ( ! $catalog instanceof WP_Ability ) {
	fwrite( STDERR, "CMS Admin universal catalog ability was not registered.\n" );
	exit( 1 );
}

$catalog_result = $catalog->execute( array() );
if ( is_wp_error( $catalog_result ) || empty( $catalog_result['items'] ) || ! is_array( $catalog_result['items'] ) ) {
	fwrite( STDERR, "CMS Admin universal catalog is unavailable.\n" );
	exit( 1 );
}

$items = $catalog_result['items'];
$post_id = 0;
$page_id = 0;
$category_id = 0;
$tag_id = 0;
$user_id = 0;
$menu_id = 0;
$menu_item_id = 0;

function cmsa_core_find_bridge( array $items, $method, $path ) {
	foreach ( $items as $item ) {
		if ( 'rest' !== ( $item['contract'] ?? '' ) || $method !== ( $item['method'] ?? '' ) ) {
			continue;
		}
		$route = (string) ( $item['route'] ?? '' );
		$bridge = (string) ( $item['bridge'] ?? '' );
		if ( '' === $route || '' === $bridge || 0 !== strpos( $bridge, 'chattanooga-cms-admin/' ) ) {
			continue;
		}
		if ( 1 === preg_match( '@^' . $route . '$@i', $path ) ) {
			return $bridge;
		}
	}
	return '';
}

function cmsa_core_execute( array $items, $method, $path, array $params = array() ) {
	$bridge_name = cmsa_core_find_bridge( $items, $method, $path );
	if ( '' === $bridge_name ) {
		return new WP_Error( 'cmsa_probe_bridge_missing', sprintf( 'No CMS Admin REST facade for %s %s.', $method, $path ) );
	}
	$bridge = wp_get_ability( $bridge_name );
	if ( ! $bridge instanceof WP_Ability ) {
		return new WP_Error( 'cmsa_probe_bridge_invalid', sprintf( 'CMS Admin facade %s is not registered.', $bridge_name ) );
	}
	$input = array(
		'path'   => $path,
		'params' => $params,
	);
	$permission = $bridge->check_permissions( $input );
	if ( is_wp_error( $permission ) ) {
		return $permission;
	}
	if ( true !== $permission ) {
		return new WP_Error( 'cmsa_probe_permission_denied', sprintf( 'CMS Admin facade denied %s %s.', $method, $path ) );
	}
	return $bridge->execute( $input );
}

function cmsa_core_expect_success( $result, $label ) {
	if ( is_wp_error( $result ) ) {
		fwrite( STDERR, $label . ': ' . $result->get_error_code() . ' ' . $result->get_error_message() . "\n" );
		return false;
	}
	if ( ! is_array( $result ) || empty( $result['status'] ) || ! array_key_exists( 'data', $result ) ) {
		fwrite( STDERR, $label . ": invalid facade response.\n" );
		return false;
	}
	return true;
}

$required_routes = array(
	array( 'GET', '/wp/v2/posts' ), array( 'POST', '/wp/v2/posts' ),
	array( 'GET', '/wp/v2/pages' ), array( 'POST', '/wp/v2/pages' ),
	array( 'GET', '/wp/v2/categories' ), array( 'POST', '/wp/v2/categories' ),
	array( 'GET', '/wp/v2/tags' ), array( 'POST', '/wp/v2/tags' ),
	array( 'GET', '/wp/v2/users' ), array( 'POST', '/wp/v2/users' ),
	array( 'GET', '/wp/v2/menus' ), array( 'POST', '/wp/v2/menus' ),
	array( 'GET', '/wp/v2/menu-items' ), array( 'POST', '/wp/v2/menu-items' ),
	array( 'GET', '/wp/v2/menu-locations' ),
	array( 'GET', '/wp/v2/plugins' ), array( 'GET', '/wp/v2/themes' ),
);
foreach ( $required_routes as $route_check ) {
	if ( '' === cmsa_core_find_bridge( $items, $route_check[0], $route_check[1] ) ) {
		fwrite( STDERR, sprintf( "Required core REST facade missing: %s %s\n", $route_check[0], $route_check[1] ) );
		goto cleanup_failure;
	}
}

$post_create = cmsa_core_execute( $items, 'POST', '/wp/v2/posts', array( 'title' => 'CMS Admin universal parity post', 'content' => 'Disposable replacement parity probe.', 'status' => 'draft' ) );
if ( ! cmsa_core_expect_success( $post_create, 'Post create failed' ) ) { goto cleanup_failure; }
$post_id = (int) ( $post_create['data']['id'] ?? 0 );
if ( $post_id < 1 || 'draft' !== ( $post_create['data']['status'] ?? '' ) ) { fwrite( STDERR, "Post create verification failed.\n" ); goto cleanup_failure; }
$post_update = cmsa_core_execute( $items, 'POST', '/wp/v2/posts/' . $post_id, array( 'title' => 'CMS Admin universal parity post updated', 'status' => 'publish' ) );
if ( ! cmsa_core_expect_success( $post_update, 'Post update failed' ) || 'publish' !== ( $post_update['data']['status'] ?? '' ) ) { goto cleanup_failure; }
$post_read = cmsa_core_execute( $items, 'GET', '/wp/v2/posts/' . $post_id, array( 'context' => 'edit' ) );
if ( ! cmsa_core_expect_success( $post_read, 'Post read failed' ) || $post_id !== (int) ( $post_read['data']['id'] ?? 0 ) ) { goto cleanup_failure; }

$page_create = cmsa_core_execute( $items, 'POST', '/wp/v2/pages', array( 'title' => 'CMS Admin universal parity page', 'status' => 'draft' ) );
if ( ! cmsa_core_expect_success( $page_create, 'Page create failed' ) ) { goto cleanup_failure; }
$page_id = (int) ( $page_create['data']['id'] ?? 0 );
if ( $page_id < 1 ) { fwrite( STDERR, "Page create verification failed.\n" ); goto cleanup_failure; }
$page_update = cmsa_core_execute( $items, 'POST', '/wp/v2/pages/' . $page_id, array( 'title' => 'CMS Admin universal parity page updated' ) );
if ( ! cmsa_core_expect_success( $page_update, 'Page update failed' ) || $page_id !== (int) ( $page_update['data']['id'] ?? 0 ) ) { goto cleanup_failure; }

$category_create = cmsa_core_execute( $items, 'POST', '/wp/v2/categories', array( 'name' => 'CMSA Parity Category' ) );
if ( ! cmsa_core_expect_success( $category_create, 'Category create failed' ) ) { goto cleanup_failure; }
$category_id = (int) ( $category_create['data']['id'] ?? 0 );
if ( $category_id < 1 ) { fwrite( STDERR, "Category create verification failed.\n" ); goto cleanup_failure; }
$category_update = cmsa_core_execute( $items, 'POST', '/wp/v2/categories/' . $category_id, array( 'description' => 'Updated through universal REST facade.' ) );
if ( ! cmsa_core_expect_success( $category_update, 'Category update failed' ) || $category_id !== (int) ( $category_update['data']['id'] ?? 0 ) ) { goto cleanup_failure; }

$tag_create = cmsa_core_execute( $items, 'POST', '/wp/v2/tags', array( 'name' => 'CMSA Parity Tag' ) );
if ( ! cmsa_core_expect_success( $tag_create, 'Tag create failed' ) ) { goto cleanup_failure; }
$tag_id = (int) ( $tag_create['data']['id'] ?? 0 );
if ( $tag_id < 1 ) { fwrite( STDERR, "Tag create verification failed.\n" ); goto cleanup_failure; }
$tag_update = cmsa_core_execute( $items, 'POST', '/wp/v2/tags/' . $tag_id, array( 'description' => 'Updated through universal REST facade.' ) );
if ( ! cmsa_core_expect_success( $tag_update, 'Tag update failed' ) || $tag_id !== (int) ( $tag_update['data']['id'] ?? 0 ) ) { goto cleanup_failure; }

$user_create = cmsa_core_execute( $items, 'POST', '/wp/v2/users', array( 'username' => 'cmsa_parity_user', 'email' => 'cmsa-parity@example.com', 'password' => wp_generate_password( 32, true, true ), 'roles' => array( 'subscriber' ) ) );
if ( ! cmsa_core_expect_success( $user_create, 'User create failed' ) ) { goto cleanup_failure; }
$user_id = (int) ( $user_create['data']['id'] ?? 0 );
if ( $user_id < 1 ) { fwrite( STDERR, "User create verification failed.\n" ); goto cleanup_failure; }
$user_update = cmsa_core_execute( $items, 'POST', '/wp/v2/users/' . $user_id, array( 'display_name' => 'CMSA Parity User', 'url' => 'https://example.com/', 'roles' => array( 'editor' ) ) );
if ( ! cmsa_core_expect_success( $user_update, 'User update failed' ) || ! in_array( 'editor', (array) ( $user_update['data']['roles'] ?? array() ), true ) ) { goto cleanup_failure; }

$menu_create = cmsa_core_execute( $items, 'POST', '/wp/v2/menus', array( 'name' => 'CMSA Parity Menu' ) );
if ( ! cmsa_core_expect_success( $menu_create, 'Menu create failed' ) ) { goto cleanup_failure; }
$menu_id = (int) ( $menu_create['data']['id'] ?? 0 );
if ( $menu_id < 1 ) { fwrite( STDERR, "Menu create verification failed.\n" ); goto cleanup_failure; }
$menu_update = cmsa_core_execute( $items, 'POST', '/wp/v2/menus/' . $menu_id, array( 'name' => 'CMSA Parity Menu Updated' ) );
if ( ! cmsa_core_expect_success( $menu_update, 'Menu update failed' ) || $menu_id !== (int) ( $menu_update['data']['id'] ?? 0 ) ) { goto cleanup_failure; }
$menu_item_create = cmsa_core_execute( $items, 'POST', '/wp/v2/menu-items', array( 'title' => 'CMSA Parity Link', 'type' => 'custom', 'url' => 'https://example.com/', 'menus' => $menu_id, 'status' => 'publish' ) );
if ( ! cmsa_core_expect_success( $menu_item_create, 'Menu item create failed' ) ) { goto cleanup_failure; }
$menu_item_id = (int) ( $menu_item_create['data']['id'] ?? 0 );
if ( $menu_item_id < 1 ) { fwrite( STDERR, "Menu item create verification failed.\n" ); goto cleanup_failure; }
$menu_item_update = cmsa_core_execute( $items, 'POST', '/wp/v2/menu-items/' . $menu_item_id, array( 'title' => 'CMSA Parity Link Updated' ) );
if ( ! cmsa_core_expect_success( $menu_item_update, 'Menu item update failed' ) || $menu_item_id !== (int) ( $menu_item_update['data']['id'] ?? 0 ) ) { goto cleanup_failure; }

$plugins_read = cmsa_core_execute( $items, 'GET', '/wp/v2/plugins', array( 'context' => 'edit' ) );
if ( ! cmsa_core_expect_success( $plugins_read, 'Plugin inventory failed' ) || ! is_array( $plugins_read['data'] ) || empty( $plugins_read['data'] ) ) { goto cleanup_failure; }
$themes_read = cmsa_core_execute( $items, 'GET', '/wp/v2/themes', array( 'context' => 'edit' ) );
if ( ! cmsa_core_expect_success( $themes_read, 'Theme inventory failed' ) || ! is_array( $themes_read['data'] ) || empty( $themes_read['data'] ) ) { goto cleanup_failure; }
$menu_locations_read = cmsa_core_execute( $items, 'GET', '/wp/v2/menu-locations', array( 'context' => 'edit' ) );
if ( ! cmsa_core_expect_success( $menu_locations_read, 'Menu-location inventory failed' ) || ! is_array( $menu_locations_read['data'] ) ) { goto cleanup_failure; }

wp_delete_post( $menu_item_id, true ); $menu_item_id = 0;
wp_delete_nav_menu( $menu_id ); $menu_id = 0;
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id ); $user_id = 0;
wp_delete_term( $tag_id, 'post_tag' ); $tag_id = 0;
wp_delete_term( $category_id, 'category' ); $category_id = 0;
wp_delete_post( $page_id, true ); $page_id = 0;
wp_delete_post( $post_id, true ); $post_id = 0;

echo 'cmsa-core-rest-parity-cli: PASS posts=read_create_update_status pages=create_update categories=create_update tags=create_update members=create_profile_roles navigation=menu_item_create_update plugin_inventory=read theme_inventory=read menu_locations=read candidate_adapters=none' . "\n";
exit( 0 );

cleanup_failure:
wp_set_current_user( 1 );
if ( $menu_item_id > 0 ) { wp_delete_post( $menu_item_id, true ); }
if ( $menu_id > 0 ) { wp_delete_nav_menu( $menu_id ); }
if ( $user_id > 0 ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $user_id ); }
if ( $tag_id > 0 ) { wp_delete_term( $tag_id, 'post_tag' ); }
if ( $category_id > 0 ) { wp_delete_term( $category_id, 'category' ); }
if ( $page_id > 0 ) { wp_delete_post( $page_id, true ); }
if ( $post_id > 0 ) { wp_delete_post( $post_id, true ); }
exit( 1 );
