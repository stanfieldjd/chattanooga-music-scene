<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_v2_core_content_fail( $message ) {
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_core_content_catalog() {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}
	$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
	if ( ! $catalog instanceof WP_Ability ) {
		cmsa_v2_core_content_fail( 'Universal catalog is unavailable.' );
	}
	$result = $catalog->execute( array() );
	if ( is_wp_error( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
		cmsa_v2_core_content_fail( 'Universal catalog could not enumerate REST facades.' );
	}
	$items = $result['items'];
	return $items;
}

function cmsa_v2_core_content_ability( $method, $path ) {
	$method = strtoupper( (string) $method );
	$matches = array();
	foreach ( cmsa_v2_core_content_catalog() as $item ) {
		if ( 'rest' !== ( $item['contract'] ?? '' ) || $method !== ( $item['method'] ?? '' ) ) {
			continue;
		}
		$route = (string) ( $item['route'] ?? '' );
		$bridge = (string) ( $item['bridge'] ?? '' );
		if ( '' !== $route && '' !== $bridge && 1 === @preg_match( '@^' . $route . '$@i', $path ) ) {
			$matches[] = $bridge;
		}
	}
	if ( 1 !== count( $matches ) ) {
		cmsa_v2_core_content_fail( sprintf( 'Expected one %1$s facade for %2$s; found %3$d.', $method, $path, count( $matches ) ) );
	}
	$ability = wp_get_ability( $matches[0] );
	if ( ! $ability instanceof WP_Ability ) {
		cmsa_v2_core_content_fail( 'Discovered core REST facade is unavailable.' );
	}
	return $ability;
}

function cmsa_v2_core_content_call( $method, $path, array $params = array() ) {
	$ability = cmsa_v2_core_content_ability( $method, $path );
	$input = array( 'path' => $path, 'params' => $params );
	$permission = $ability->check_permissions( $input );
	if ( true !== $permission ) {
		cmsa_v2_core_content_fail( sprintf( 'Permission failed for %1$s %2$s.', $method, $path ) );
	}
	$result = $ability->execute( $input );
	if ( is_wp_error( $result ) ) {
		cmsa_v2_core_content_fail( sprintf( '%1$s %2$s failed: %3$s %4$s', $method, $path, $result->get_error_code(), $result->get_error_message() ) );
	}
	if ( ! is_array( $result ) || (int) ( $result['status'] ?? 0 ) < 200 || (int) ( $result['status'] ?? 0 ) >= 300 || ! array_key_exists( 'data', $result ) ) {
		cmsa_v2_core_content_fail( sprintf( '%1$s %2$s returned an invalid facade response.', $method, $path ) );
	}
	return $result['data'];
}

wp_set_current_user( 1 );
$suffix = substr( hash( 'sha256', wp_generate_uuid4() ), 0, 10 );
$menu_location = 'cmsa-v2-location-' . $suffix;
register_nav_menu( $menu_location, 'CMSA v2 REST parity location' );

foreach ( array(
	array( 'POST', '/wp/v2/posts' ),
	array( 'POST', '/wp/v2/pages' ),
	array( 'POST', '/wp/v2/categories' ),
	array( 'POST', '/wp/v2/tags' ),
	array( 'POST', '/wp/v2/menus' ),
	array( 'POST', '/wp/v2/menu-items' ),
	array( 'GET', '/wp/v2/menu-locations' ),
) as $route ) {
	cmsa_v2_core_content_ability( $route[0], $route[1] );
}

$category = cmsa_v2_core_content_call( 'POST', '/wp/v2/categories', array( 'name' => 'CMSA v2 Category ' . $suffix ) );
$category_id = (int) ( $category['id'] ?? 0 );
$tag = cmsa_v2_core_content_call( 'POST', '/wp/v2/tags', array( 'name' => 'CMSA v2 Tag ' . $suffix ) );
$tag_id = (int) ( $tag['id'] ?? 0 );
if ( $category_id < 1 || $tag_id < 1 ) {
	cmsa_v2_core_content_fail( 'Core REST taxonomy creation failed.' );
}

$post = cmsa_v2_core_content_call( 'POST', '/wp/v2/posts', array(
	'title' => 'CMSA v2 Post ' . $suffix,
	'content' => 'Core REST parity post body.',
	'status' => 'draft',
	'categories' => array( $category_id ),
	'tags' => array( $tag_id ),
) );
$post_id = (int) ( $post['id'] ?? 0 );
if ( $post_id < 1 ) {
	cmsa_v2_core_content_fail( 'Core REST post creation failed.' );
}
$post_read = cmsa_v2_core_content_call( 'GET', '/wp/v2/posts/' . $post_id, array( 'context' => 'edit' ) );
if ( (int) ( $post_read['id'] ?? 0 ) !== $post_id || ! in_array( $category_id, (array) ( $post_read['categories'] ?? array() ), true ) || ! in_array( $tag_id, (array) ( $post_read['tags'] ?? array() ), true ) ) {
	cmsa_v2_core_content_fail( 'Core REST post read did not preserve taxonomy assignments.' );
}
$post_update = cmsa_v2_core_content_call( 'POST', '/wp/v2/posts/' . $post_id, array( 'title' => 'CMSA v2 Post Updated ' . $suffix ) );
if ( (int) ( $post_update['id'] ?? 0 ) !== $post_id || false === strpos( (string) ( $post_update['title']['rendered'] ?? '' ), 'Updated' ) ) {
	cmsa_v2_core_content_fail( 'Core REST post update failed.' );
}

$page = cmsa_v2_core_content_call( 'POST', '/wp/v2/pages', array( 'title' => 'CMSA v2 Page ' . $suffix, 'content' => 'Core REST parity page body.', 'status' => 'draft' ) );
$page_id = (int) ( $page['id'] ?? 0 );
if ( $page_id < 1 ) {
	cmsa_v2_core_content_fail( 'Core REST page creation failed.' );
}
$page_read = cmsa_v2_core_content_call( 'GET', '/wp/v2/pages/' . $page_id, array( 'context' => 'edit' ) );
$page_update = cmsa_v2_core_content_call( 'POST', '/wp/v2/pages/' . $page_id, array( 'title' => 'CMSA v2 Page Updated ' . $suffix ) );
if ( (int) ( $page_read['id'] ?? 0 ) !== $page_id || (int) ( $page_update['id'] ?? 0 ) !== $page_id || false === strpos( (string) ( $page_update['title']['rendered'] ?? '' ), 'Updated' ) ) {
	cmsa_v2_core_content_fail( 'Core REST page read/update failed.' );
}

$menu = cmsa_v2_core_content_call( 'POST', '/wp/v2/menus', array( 'name' => 'CMSA v2 Menu ' . $suffix ) );
$menu_id = (int) ( $menu['id'] ?? 0 );
if ( $menu_id < 1 ) {
	cmsa_v2_core_content_fail( 'Core REST menu creation failed.' );
}
$menu_update = cmsa_v2_core_content_call( 'POST', '/wp/v2/menus/' . $menu_id, array( 'name' => 'CMSA v2 Menu Updated ' . $suffix, 'locations' => array( $menu_location ) ) );
if ( (int) ( $menu_update['id'] ?? 0 ) !== $menu_id || ! in_array( $menu_location, (array) ( $menu_update['locations'] ?? array() ), true ) ) {
	cmsa_v2_core_content_fail( 'Core REST menu update/location assignment failed.' );
}
$location = cmsa_v2_core_content_call( 'GET', '/wp/v2/menu-locations/' . $menu_location, array( 'context' => 'edit' ) );
if ( (int) ( $location['menu'] ?? 0 ) !== $menu_id ) {
	cmsa_v2_core_content_fail( 'Core REST menu-location readback failed.' );
}

$menu_item = cmsa_v2_core_content_call( 'POST', '/wp/v2/menu-items', array(
	'title' => 'CMSA v2 Menu Item ' . $suffix,
	'type' => 'custom',
	'status' => 'publish',
	'url' => 'https://example.com/cmsa-v2-' . $suffix,
	'menus' => $menu_id,
) );
$menu_item_id = (int) ( $menu_item['id'] ?? 0 );
if ( $menu_item_id < 1 ) {
	cmsa_v2_core_content_fail( 'Core REST menu-item creation failed.' );
}
$menu_item_read = cmsa_v2_core_content_call( 'GET', '/wp/v2/menu-items/' . $menu_item_id, array( 'context' => 'edit' ) );
$menu_item_update = cmsa_v2_core_content_call( 'POST', '/wp/v2/menu-items/' . $menu_item_id, array( 'description' => 'CMSA v2 updated menu item ' . $suffix ) );
if ( (int) ( $menu_item_read['id'] ?? 0 ) !== $menu_item_id || (int) ( $menu_item_read['menus'] ?? 0 ) !== $menu_id || false === strpos( (string) ( $menu_item_update['description'] ?? '' ), 'updated menu item' ) ) {
	cmsa_v2_core_content_fail( 'Core REST menu-item read/update failed.' );
}

wp_set_current_user( 0 );
$blocked = cmsa_v2_core_content_ability( 'POST', '/wp/v2/posts' );
if ( false !== $blocked->check_permissions( array( 'path' => '/wp/v2/posts', 'params' => array( 'title' => 'blocked', 'status' => 'draft' ) ) ) ) {
	cmsa_v2_core_content_fail( 'Anonymous core REST mutation was not blocked.' );
}
wp_set_current_user( 1 );

foreach ( array(
	array( 'DELETE', '/wp/v2/menu-items/' . $menu_item_id, array( 'force' => true ) ),
	array( 'DELETE', '/wp/v2/menus/' . $menu_id, array( 'force' => true ) ),
	array( 'DELETE', '/wp/v2/pages/' . $page_id, array( 'force' => true ) ),
	array( 'DELETE', '/wp/v2/posts/' . $post_id, array( 'force' => true ) ),
	array( 'DELETE', '/wp/v2/tags/' . $tag_id, array( 'force' => true ) ),
	array( 'DELETE', '/wp/v2/categories/' . $category_id, array( 'force' => true ) ),
) as $delete ) {
	$result = cmsa_v2_core_content_call( $delete[0], $delete[1], $delete[2] );
	if ( empty( $result['deleted'] ) ) {
		cmsa_v2_core_content_fail( 'Core REST deletion was not confirmed for ' . $delete[1] );
	}
}

$location_after_delete = cmsa_v2_core_content_call( 'GET', '/wp/v2/menu-locations/' . $menu_location, array( 'context' => 'edit' ) );
if ( 0 !== (int) ( $location_after_delete['menu'] ?? 0 ) ) {
	cmsa_v2_core_content_fail( 'Deleting the menu did not clear its location assignment.' );
}
if ( get_post( $post_id ) || get_post( $page_id ) || get_post( $menu_item_id ) || term_exists( $category_id, 'category' ) || term_exists( $tag_id, 'post_tag' ) || wp_get_nav_menu_object( $menu_id ) ) {
	cmsa_v2_core_content_fail( 'Core content REST proof did not restore final disposable state.' );
}

echo "cmsa-v2-core-content-rest: PASS posts=crud pages=crud categories=crud tags=crud menus=crud menu_items=crud menu_locations=assignment_readback dynamic_bridge=verified admin_boundary=verified final_state=restored\n";
exit( 0 );
