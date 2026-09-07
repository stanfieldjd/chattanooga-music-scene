<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "content-taxonomy-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "content-taxonomy-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
$content = new CMSA_Content();
$taxonomy = new CMSA_Content_Taxonomy( $content );

$normalize_ids = static function ( $ids ) {
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	$ids = array_values( array_filter( $ids, static function ( $id ) { return $id > 0; } ) );
	sort( $ids, SORT_NUMERIC );
	return $ids;
};
$get_ids = static function ( $post_id, $tax ) use ( $normalize_ids ) {
	$ids = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
	return is_wp_error( $ids ) ? array() : $normalize_ids( $ids );
};

$control_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'CMSA taxonomy control', 'post_content' => 'TAXONOMY_CONTROL_UNCHANGED' ), true );
if ( is_wp_error( $control_id ) ) {
	fwrite( STDERR, "content-taxonomy-cli: control fixture creation failed.\n" );
	exit( 1 );
}
$control_before = get_post( $control_id );
$control_signature = $control_before->post_content . '|' . implode( ',', $get_ids( $control_id, 'category' ) ) . '|' . implode( ',', $get_ids( $control_id, 'post_tag' ) );

$parent = $taxonomy->create_term( 'category', array( 'name' => 'CMSA Taxonomy Parent', 'description' => 'parent' ) );
if ( is_wp_error( $parent ) ) {
	fwrite( STDERR, "content-taxonomy-cli: parent category creation failed.\n" );
	exit( 1 );
}
$parent_id = (int) $parent['term']['id'];
$child = $taxonomy->create_term( 'category', array( 'name' => 'CMSA Taxonomy Child', 'description' => 'child', 'parent' => $parent_id ) );
$tag = $taxonomy->create_term( 'post_tag', array( 'name' => 'CMSA Taxonomy Tag', 'description' => 'tag' ) );
if ( is_wp_error( $child ) || is_wp_error( $tag ) ) {
	fwrite( STDERR, "content-taxonomy-cli: child category or tag creation failed.\n" );
	exit( 1 );
}
$child_id = (int) $child['term']['id'];
$tag_id = (int) $tag['term']['id'];

$duplicate = $taxonomy->create_term( 'post_tag', array( 'name' => 'CMSA Taxonomy Tag' ) );
if ( ! is_wp_error( $duplicate ) || 'cmsa_taxonomy_create' !== $duplicate->get_error_code() ) {
	fwrite( STDERR, "content-taxonomy-cli: duplicate term did not fail through bounded error contract.\n" );
	exit( 1 );
}
$unsupported = $taxonomy->list_terms( 'nav_menu', array() );
if ( ! is_wp_error( $unsupported ) || 'cmsa_taxonomy_not_allowed' !== $unsupported->get_error_code() ) {
	fwrite( STDERR, "content-taxonomy-cli: arbitrary taxonomy was not rejected.\n" );
	exit( 1 );
}

$categories = $taxonomy->list_terms( 'category', array( 'search' => 'CMSA Taxonomy', 'per_page' => 100 ) );
$tags = $taxonomy->list_terms( 'post_tag', array( 'search' => 'CMSA Taxonomy', 'per_page' => 100 ) );
if ( is_wp_error( $categories ) || is_wp_error( $tags ) || count( $categories['items'] ) < 2 || count( $tags['items'] ) < 1 ) {
	fwrite( STDERR, "content-taxonomy-cli: term listing failed.\n" );
	exit( 1 );
}
$child_read = $taxonomy->get_term( 'category', $child_id );
if ( is_wp_error( $child_read ) || (int) $child_read['term']['parent'] !== $parent_id ) {
	fwrite( STDERR, "content-taxonomy-cli: term get/parent verification failed.\n" );
	exit( 1 );
}

$stale = $taxonomy->update_term(
	'category',
	array(
		'id'                   => $child_id,
		'expected_state_token' => str_repeat( '0', 64 ),
		'name'                 => 'MUST NOT APPLY',
	)
);
$after_stale = $taxonomy->get_term( 'category', $child_id );
if ( ! is_wp_error( $stale ) || 'cmsa_taxonomy_conflict' !== $stale->get_error_code() || is_wp_error( $after_stale ) || 'CMSA Taxonomy Child' !== $after_stale['term']['name'] ) {
	fwrite( STDERR, "content-taxonomy-cli: stale term update did not fail closed.\n" );
	exit( 1 );
}

$one_shot_filter = null;
$one_shot_filter = static function ( $data, $term_id, $tax, $args ) use ( &$one_shot_filter, $child_id ) {
	if ( 'category' === $tax && (int) $term_id === $child_id ) {
		remove_filter( 'wp_update_term_data', $one_shot_filter, 10 );
		$data['name'] = 'CMSA FORCED MISMATCH';
	}
	return $data;
};
add_filter( 'wp_update_term_data', $one_shot_filter, 10, 4 );
$forced = $taxonomy->update_term(
	'category',
	array(
		'id'                   => $child_id,
		'expected_state_token' => $child_read['term']['state_token'],
		'name'                 => 'CMSA Requested Update',
	)
);
$forced_after = $taxonomy->get_term( 'category', $child_id );
$forced_data = is_wp_error( $forced ) ? $forced->get_error_data() : array();
if ( ! is_wp_error( $forced ) || 'cmsa_taxonomy_update_verify' !== $forced->get_error_code() || empty( $forced_data['rolled_back'] ) || is_wp_error( $forced_after ) || $child_read['term']['state_token'] !== $forced_after['term']['state_token'] ) {
	fwrite( STDERR, "content-taxonomy-cli: forced term verification failure did not restore exact prior state.\n" );
	exit( 1 );
}

$updated = $taxonomy->update_term(
	'category',
	array(
		'id'                   => $child_id,
		'expected_state_token' => $forced_after['term']['state_token'],
		'name'                 => 'CMSA Taxonomy Child Updated',
		'description'          => 'child updated',
		'parent'               => $parent_id,
	)
);
if ( is_wp_error( $updated ) || 'CMSA Taxonomy Child Updated' !== $updated['term']['name'] || $parent_id !== (int) $updated['term']['parent'] || $forced_after['term']['state_token'] === $updated['term']['state_token'] ) {
	fwrite( STDERR, "content-taxonomy-cli: normal term update failed.\n" );
	exit( 1 );
}

$post = $content->create_draft( 'post', array( 'title' => 'CMSA taxonomy relationship post' ) );
$page = $content->create_draft( 'page', array( 'title' => 'CMSA taxonomy relationship page' ) );
if ( is_wp_error( $post ) || is_wp_error( $page ) ) {
	fwrite( STDERR, "content-taxonomy-cli: content relationship fixtures failed.\n" );
	exit( 1 );
}
$post_id = (int) $post['item']['id'];
$page_id = (int) $page['item']['id'];

$page_reject = $taxonomy->change_relationship( 'page', 'category', array( 'id' => $page_id, 'expected_term_ids' => array(), 'term_ids' => array( $child_id ) ), 'assign' );
if ( ! is_wp_error( $page_reject ) || 'cmsa_taxonomy_object_type' !== $page_reject->get_error_code() ) {
	fwrite( STDERR, "content-taxonomy-cli: taxonomy relationship was allowed on an unregistered page type.\n" );
	exit( 1 );
}

$category_before = $get_ids( $post_id, 'category' );
$category_assign = $taxonomy->change_relationship( 'post', 'category', array( 'id' => $post_id, 'expected_term_ids' => $category_before, 'term_ids' => array( $child_id ) ), 'assign' );
if ( is_wp_error( $category_assign ) || ! in_array( $child_id, $category_assign['term_ids'], true ) ) {
	fwrite( STDERR, "content-taxonomy-cli: category assignment failed.\n" );
	exit( 1 );
}
$category_remove = $taxonomy->change_relationship( 'post', 'category', array( 'id' => $post_id, 'expected_term_ids' => $category_assign['term_ids'], 'term_ids' => array( $child_id ) ), 'remove' );
if ( is_wp_error( $category_remove ) || in_array( $child_id, $category_remove['term_ids'], true ) || empty( $category_remove['term_ids'] ) ) {
	fwrite( STDERR, "content-taxonomy-cli: category removal/default-category preservation failed.\n" );
	exit( 1 );
}

$tag_before = $get_ids( $post_id, 'post_tag' );
$relationship_hook = null;
$relationship_hook = static function ( $object_id, $terms, $tt_ids, $tax, $append, $old_tt_ids ) use ( &$relationship_hook, $post_id ) {
	if ( 'post_tag' === $tax && (int) $object_id === $post_id ) {
		remove_action( 'set_object_terms', $relationship_hook, 10 );
		wp_set_object_terms( $object_id, array(), $tax, false );
	}
};
add_action( 'set_object_terms', $relationship_hook, 10, 6 );
$forced_relationship = $taxonomy->change_relationship( 'post', 'post_tag', array( 'id' => $post_id, 'expected_term_ids' => $tag_before, 'term_ids' => array( $tag_id ) ), 'assign' );
$forced_relationship_data = is_wp_error( $forced_relationship ) ? $forced_relationship->get_error_data() : array();
if ( ! is_wp_error( $forced_relationship ) || 'cmsa_taxonomy_relationship_verify' !== $forced_relationship->get_error_code() || empty( $forced_relationship_data['rolled_back'] ) || $tag_before !== $get_ids( $post_id, 'post_tag' ) ) {
	fwrite( STDERR, "content-taxonomy-cli: forced relationship verification failure did not restore prior state.\n" );
	exit( 1 );
}

$tag_assign = $taxonomy->change_relationship( 'post', 'post_tag', array( 'id' => $post_id, 'expected_term_ids' => $tag_before, 'term_ids' => array( $tag_id ) ), 'assign' );
if ( is_wp_error( $tag_assign ) || array( $tag_id ) !== $tag_assign['term_ids'] ) {
	fwrite( STDERR, "content-taxonomy-cli: tag assignment failed.\n" );
	exit( 1 );
}
$stale_relationship = $taxonomy->change_relationship( 'post', 'post_tag', array( 'id' => $post_id, 'expected_term_ids' => array(), 'term_ids' => array( $tag_id ) ), 'remove' );
if ( ! is_wp_error( $stale_relationship ) || 'cmsa_taxonomy_relationship_conflict' !== $stale_relationship->get_error_code() || array( $tag_id ) !== $get_ids( $post_id, 'post_tag' ) ) {
	fwrite( STDERR, "content-taxonomy-cli: stale relationship mutation did not fail closed.\n" );
	exit( 1 );
}
$tag_remove = $taxonomy->change_relationship( 'post', 'post_tag', array( 'id' => $post_id, 'expected_term_ids' => array( $tag_id ), 'term_ids' => array( $tag_id ) ), 'remove' );
if ( is_wp_error( $tag_remove ) || ! empty( $tag_remove['term_ids'] ) ) {
	fwrite( STDERR, "content-taxonomy-cli: tag removal failed.\n" );
	exit( 1 );
}

$login = 'cmsa_tax_limited_' . strtolower( wp_generate_password( 8, false, false ) );
$limited_id = wp_create_user( $login, wp_generate_password( 20, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $limited_id ) ) {
	fwrite( STDERR, "content-taxonomy-cli: limited user creation failed.\n" );
	exit( 1 );
}
$limited = new WP_User( $limited_id );
$limited->set_role( 'subscriber' );
$limited->add_cap( 'edit_posts', true );
clean_user_cache( $limited_id );
wp_set_current_user( $limited_id );
$own = $content->create_draft( 'post', array( 'title' => 'CMSA limited taxonomy post' ) );
if ( is_wp_error( $own ) ) {
	fwrite( STDERR, "content-taxonomy-cli: limited own post creation failed.\n" );
	exit( 1 );
}
$own_id = (int) $own['item']['id'];
$own_tags = $get_ids( $own_id, 'post_tag' );
$own_assign = $taxonomy->change_relationship( 'post', 'post_tag', array( 'id' => $own_id, 'expected_term_ids' => $own_tags, 'term_ids' => array( $tag_id ) ), 'assign' );
$other_assign = $taxonomy->change_relationship( 'post', 'post_tag', array( 'id' => $post_id, 'expected_term_ids' => $get_ids( $post_id, 'post_tag' ), 'term_ids' => array( $tag_id ) ), 'assign' );
if ( is_wp_error( $own_assign ) || ! is_wp_error( $other_assign ) || 'cmsa_content_permission' !== $other_assign->get_error_code() ) {
	fwrite( STDERR, "content-taxonomy-cli: object-level relationship authorization failed.\n" );
	exit( 1 );
}

wp_set_current_user( $admin->ID );
$control_after = get_post( $control_id );
$control_after_signature = $control_after->post_content . '|' . implode( ',', $get_ids( $control_id, 'category' ) ) . '|' . implode( ',', $get_ids( $control_id, 'post_tag' ) );
if ( $control_signature !== $control_after_signature ) {
	fwrite( STDERR, "content-taxonomy-cli: unrelated control content changed.\n" );
	exit( 1 );
}

wp_delete_post( $own_id, true );
wp_delete_post( $post_id, true );
wp_delete_post( $page_id, true );
wp_delete_post( $control_id, true );
wp_delete_term( $child_id, 'category' );
wp_delete_term( $parent_id, 'category' );
wp_delete_term( $tag_id, 'post_tag' );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $limited_id );

echo "content-taxonomy-cli: PASS terms=list-get-create-conflict-update-rollback relationships=category-default,tag-rollback,stale-conflict object-scope=verified page=taxonomy-rejected unrelated=unchanged\n";
