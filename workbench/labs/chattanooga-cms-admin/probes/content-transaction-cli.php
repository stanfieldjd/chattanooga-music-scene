<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "content-transaction-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "content-transaction-cli: disposable administrator missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
$content = new CMSA_Content();

$control_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'CMSA control sentinel',
		'post_content' => 'CONTROL_UNCHANGED',
	),
	true
);
if ( is_wp_error( $control_id ) ) {
	fwrite( STDERR, "content-transaction-cli: control fixture creation failed.\n" );
	exit( 1 );
}
$control_before = get_post( $control_id );
$control_signature = implode( '|', array( $control_before->post_title, $control_before->post_content, $control_before->post_excerpt, $control_before->post_status ) );

$created = $content->create_draft(
	'post',
	array(
		'title'   => 'CMSA transactional post fixture',
		'content' => 'ORIGINAL_POST_CONTENT',
		'excerpt' => 'ORIGINAL_POST_EXCERPT',
		'slug'    => 'cmsa-transactional-post-fixture',
	)
);
if ( is_wp_error( $created ) || empty( $created['created'] ) || 'draft' !== $created['item']['status'] ) {
	fwrite( STDERR, "content-transaction-cli: draft post creation failed.\n" );
	exit( 1 );
}
$post_id = (int) $created['item']['id'];
$initial_modified = (string) $created['item']['modified_gmt'];

$listed = $content->list_items( 'post', array( 'search' => 'CMSA transactional post fixture', 'per_page' => 100 ) );
$listed_ids = array();
if ( is_wp_error( $listed ) ) {
	fwrite( STDERR, "content-transaction-cli: post listing failed.\n" );
	exit( 1 );
}
foreach ( $listed['items'] as $item ) {
	$listed_ids[] = (int) $item['id'];
}
if ( ! in_array( $post_id, $listed_ids, true ) ) {
	fwrite( STDERR, "content-transaction-cli: created post missing from bounded list.\n" );
	exit( 1 );
}

$read = $content->get_item( 'post', $post_id );
if ( is_wp_error( $read ) || 'ORIGINAL_POST_CONTENT' !== $read['item']['content'] ) {
	fwrite( STDERR, "content-transaction-cli: post read verification failed.\n" );
	exit( 1 );
}

$conflict = $content->update_item(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => '1970-01-01 00:00:00',
		'content'               => 'MUST_NOT_APPLY',
	)
);
$after_conflict = get_post( $post_id );
if ( ! is_wp_error( $conflict ) || 'cmsa_content_conflict' !== $conflict->get_error_code() || 'ORIGINAL_POST_CONTENT' !== $after_conflict->post_content ) {
	fwrite( STDERR, "content-transaction-cli: stale-write conflict did not fail closed.\n" );
	exit( 1 );
}

$updated = $content->update_item(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => $initial_modified,
		'title'                 => 'CMSA transactional post fixture updated',
		'content'               => 'UPDATED_POST_CONTENT',
		'excerpt'               => 'UPDATED_POST_EXCERPT',
	)
);
if ( is_wp_error( $updated ) || empty( $updated['updated'] ) || empty( $updated['rollback_revision_id'] ) || 'UPDATED_POST_CONTENT' !== $updated['item']['content'] ) {
	fwrite( STDERR, "content-transaction-cli: revision-protected post update failed.\n" );
	exit( 1 );
}
$rollback_revision_id = (int) $updated['rollback_revision_id'];
$revision = wp_get_post_revision( $rollback_revision_id );
if ( ! $revision instanceof WP_Post || (int) $revision->post_parent !== $post_id || 'ORIGINAL_POST_CONTENT' !== $revision->post_content ) {
	fwrite( STDERR, "content-transaction-cli: rollback revision does not preserve pre-update state.\n" );
	exit( 1 );
}

$restored_revision = $content->restore_revision(
	'post',
	array(
		'id'                    => $post_id,
		'revision_id'           => $rollback_revision_id,
		'expected_modified_gmt' => $updated['item']['modified_gmt'],
	)
);
if ( is_wp_error( $restored_revision ) || empty( $restored_revision['restored'] ) || 'ORIGINAL_POST_CONTENT' !== $restored_revision['item']['content'] ) {
	fwrite( STDERR, "content-transaction-cli: post revision restoration failed.\n" );
	exit( 1 );
}

$trashed = $content->trash_item(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => $restored_revision['item']['modified_gmt'],
	)
);
if ( is_wp_error( $trashed ) || empty( $trashed['trashed'] ) || 'trash' !== $trashed['item']['status'] ) {
	fwrite( STDERR, "content-transaction-cli: post trash transaction failed.\n" );
	exit( 1 );
}
$restored_post = $content->restore_item(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => $trashed['item']['modified_gmt'],
	)
);
if ( is_wp_error( $restored_post ) || empty( $restored_post['restored'] ) || 'trash' === $restored_post['item']['status'] ) {
	fwrite( STDERR, "content-transaction-cli: post trash restore failed.\n" );
	exit( 1 );
}

$parent = $content->create_draft( 'page', array( 'title' => 'CMSA parent page fixture', 'content' => 'PARENT' ) );
if ( is_wp_error( $parent ) ) {
	fwrite( STDERR, "content-transaction-cli: parent page creation failed.\n" );
	exit( 1 );
}
$parent_id = (int) $parent['item']['id'];
$child = $content->create_draft(
	'page',
	array(
		'title'     => 'CMSA child page fixture',
		'content'   => 'ORIGINAL_PAGE_CONTENT',
		'parent_id' => $parent_id,
	)
);
if ( is_wp_error( $child ) || (int) $child['item']['parent_id'] !== $parent_id ) {
	fwrite( STDERR, "content-transaction-cli: child page creation/parent validation failed.\n" );
	exit( 1 );
}
$page_id = (int) $child['item']['id'];
$page_update = $content->update_item(
	'page',
	array(
		'id'                    => $page_id,
		'expected_modified_gmt' => $child['item']['modified_gmt'],
		'content'               => 'UPDATED_PAGE_CONTENT',
	)
);
if ( is_wp_error( $page_update ) || empty( $page_update['rollback_revision_id'] ) || 'UPDATED_PAGE_CONTENT' !== $page_update['item']['content'] ) {
	fwrite( STDERR, "content-transaction-cli: page update/revision gate failed.\n" );
	exit( 1 );
}
$page_trash = $content->trash_item(
	'page',
	array(
		'id'                    => $page_id,
		'expected_modified_gmt' => $page_update['item']['modified_gmt'],
	)
);
if ( is_wp_error( $page_trash ) || 'trash' !== $page_trash['item']['status'] ) {
	fwrite( STDERR, "content-transaction-cli: page trash failed.\n" );
	exit( 1 );
}
$page_restore = $content->restore_item(
	'page',
	array(
		'id'                    => $page_id,
		'expected_modified_gmt' => $page_trash['item']['modified_gmt'],
	)
);
if ( is_wp_error( $page_restore ) || 'trash' === $page_restore['item']['status'] || (int) $page_restore['item']['parent_id'] !== $parent_id ) {
	fwrite( STDERR, "content-transaction-cli: page restore or parent relationship failed.\n" );
	exit( 1 );
}

$control_after = get_post( $control_id );
$control_after_signature = implode( '|', array( $control_after->post_title, $control_after->post_content, $control_after->post_excerpt, $control_after->post_status ) );
if ( $control_signature !== $control_after_signature ) {
	fwrite( STDERR, "content-transaction-cli: unrelated control content changed.\n" );
	exit( 1 );
}

wp_delete_post( $post_id, true );
wp_delete_post( $page_id, true );
wp_delete_post( $parent_id, true );
wp_delete_post( $control_id, true );

echo "content-transaction-cli: PASS post=draft-conflict-update-revision-trash-restore page=parent-update-trash-restore unrelated=unchanged\n";
