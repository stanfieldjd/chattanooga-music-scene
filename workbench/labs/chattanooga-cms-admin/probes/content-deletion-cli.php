<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "content-deletion-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "content-deletion-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
$content = new CMSA_Content();
$deletion = new CMSA_Content_Deletion();

$control_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'CMSA deletion control sentinel',
		'post_content' => 'CONTROL_UNCHANGED',
	),
	true
);
if ( is_wp_error( $control_id ) ) {
	fwrite( STDERR, "content-deletion-cli: control fixture creation failed.\n" );
	exit( 1 );
}
$control_before = get_post( $control_id );
$control_signature = implode( '|', array( $control_before->post_title, $control_before->post_content, $control_before->post_status ) );

$post = $content->create_draft( 'post', array( 'title' => 'CMSA hard-delete post fixture', 'content' => 'DELETE_ME' ) );
if ( is_wp_error( $post ) ) {
	fwrite( STDERR, "content-deletion-cli: post fixture creation failed.\n" );
	exit( 1 );
}
$post_id = (int) $post['item']['id'];

$not_trashed = $deletion->delete_permanently(
	'post',
	array(
		'id'                       => $post_id,
		'expected_modified_gmt'    => $post['item']['modified_gmt'],
		'confirm_permanent_delete' => true,
	)
);
if ( ! is_wp_error( $not_trashed ) || 'cmsa_content_delete_requires_trash' !== $not_trashed->get_error_code() || ! get_post( $post_id ) ) {
	fwrite( STDERR, "content-deletion-cli: non-trash delete did not fail closed.\n" );
	exit( 1 );
}

$trashed = $content->trash_item(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => $post['item']['modified_gmt'],
	)
);
if ( is_wp_error( $trashed ) || 'trash' !== $trashed['item']['status'] ) {
	fwrite( STDERR, "content-deletion-cli: post trash prerequisite failed.\n" );
	exit( 1 );
}

$stale = $deletion->delete_permanently(
	'post',
	array(
		'id'                       => $post_id,
		'expected_modified_gmt'    => '1970-01-01 00:00:00',
		'confirm_permanent_delete' => true,
	)
);
if ( ! is_wp_error( $stale ) || 'cmsa_content_delete_conflict' !== $stale->get_error_code() || ! get_post( $post_id ) ) {
	fwrite( STDERR, "content-deletion-cli: stale hard-delete conflict did not fail closed.\n" );
	exit( 1 );
}

$unconfirmed = $deletion->delete_permanently(
	'post',
	array(
		'id'                       => $post_id,
		'expected_modified_gmt'    => $trashed['item']['modified_gmt'],
		'confirm_permanent_delete' => false,
	)
);
if ( ! is_wp_error( $unconfirmed ) || 'cmsa_content_delete_confirmation' !== $unconfirmed->get_error_code() || ! get_post( $post_id ) ) {
	fwrite( STDERR, "content-deletion-cli: missing destructive confirmation did not fail closed.\n" );
	exit( 1 );
}

$deleted_post = $deletion->delete_permanently(
	'post',
	array(
		'id'                       => $post_id,
		'expected_modified_gmt'    => $trashed['item']['modified_gmt'],
		'confirm_permanent_delete' => true,
	)
);
if ( is_wp_error( $deleted_post ) || empty( $deleted_post['deleted'] ) || get_post( $post_id ) || (int) $deleted_post['item']['id'] !== $post_id || 'trash' !== $deleted_post['item']['status'] ) {
	fwrite( STDERR, "content-deletion-cli: permanent post deletion failed verification.\n" );
	exit( 1 );
}

$page = $content->create_draft( 'page', array( 'title' => 'CMSA hard-delete page fixture', 'content' => 'DELETE_PAGE' ) );
if ( is_wp_error( $page ) ) {
	fwrite( STDERR, "content-deletion-cli: page fixture creation failed.\n" );
	exit( 1 );
}
$page_id = (int) $page['item']['id'];
$page_trash = $content->trash_item(
	'page',
	array(
		'id'                    => $page_id,
		'expected_modified_gmt' => $page['item']['modified_gmt'],
	)
);
if ( is_wp_error( $page_trash ) ) {
	fwrite( STDERR, "content-deletion-cli: page trash prerequisite failed.\n" );
	exit( 1 );
}
$deleted_page = $deletion->delete_permanently(
	'page',
	array(
		'id'                       => $page_id,
		'expected_modified_gmt'    => $page_trash['item']['modified_gmt'],
		'confirm_permanent_delete' => true,
	)
);
if ( is_wp_error( $deleted_page ) || empty( $deleted_page['deleted'] ) || get_post( $page_id ) ) {
	fwrite( STDERR, "content-deletion-cli: permanent page deletion failed verification.\n" );
	exit( 1 );
}

$control_after = get_post( $control_id );
$control_after_signature = implode( '|', array( $control_after->post_title, $control_after->post_content, $control_after->post_status ) );
if ( $control_signature !== $control_after_signature ) {
	fwrite( STDERR, "content-deletion-cli: unrelated control content changed.\n" );
	exit( 1 );
}

wp_delete_post( $control_id, true );

echo "content-deletion-cli: PASS post=trash-conflict-confirm-hard-delete page=trash-hard-delete unrelated=unchanged\n";
