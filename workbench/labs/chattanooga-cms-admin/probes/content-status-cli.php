<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "content-status-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "content-status-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

$content = new CMSA_Content();
$status = new CMSA_Content_Status( $content );

$control_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'CMSA status control', 'post_content' => 'STATUS_CONTROL_UNCHANGED' ), true );
if ( is_wp_error( $control_id ) ) {
	fwrite( STDERR, "content-status-cli: control fixture creation failed.\n" );
	exit( 1 );
}
$control_before = get_post( $control_id );
$control_signature = $control_before->post_status . '|' . $control_before->post_content . '|' . $control_before->post_modified_gmt;

$post = $content->create_draft( 'post', array( 'title' => 'CMSA status post', 'content' => 'STATUS_POST' ) );
$page = $content->create_draft( 'page', array( 'title' => 'CMSA status page', 'content' => 'STATUS_PAGE' ) );
if ( is_wp_error( $post ) || is_wp_error( $page ) ) {
	fwrite( STDERR, "content-status-cli: draft fixtures failed.\n" );
	exit( 1 );
}
$post_id = (int) $post['item']['id'];
$page_id = (int) $page['item']['id'];

$stale = $status->set_status(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => '1970-01-01 00:00:00',
		'expected_status'       => 'draft',
		'status'                => 'pending',
	)
);
if ( ! is_wp_error( $stale ) || 'cmsa_content_conflict' !== $stale->get_error_code() || 'draft' !== get_post_status( $post_id ) ) {
	fwrite( STDERR, "content-status-cli: stale timestamp did not fail closed.\n" );
	exit( 1 );
}

$wrong_status = $status->set_status(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => $post['item']['modified_gmt'],
		'expected_status'       => 'pending',
		'status'                => 'publish',
	)
);
if ( ! is_wp_error( $wrong_status ) || 'cmsa_content_status_conflict' !== $wrong_status->get_error_code() || 'draft' !== get_post_status( $post_id ) ) {
	fwrite( STDERR, "content-status-cli: stale status did not fail closed.\n" );
	exit( 1 );
}

$pending = $status->set_status(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => $post['item']['modified_gmt'],
		'expected_status'       => 'draft',
		'status'                => 'pending',
	)
);
if ( is_wp_error( $pending ) || 'pending' !== $pending['item']['status'] ) {
	fwrite( STDERR, "content-status-cli: draft-to-pending failed.\n" );
	exit( 1 );
}

$private = $status->set_status(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => $pending['item']['modified_gmt'],
		'expected_status'       => 'pending',
		'status'                => 'private',
	)
);
if ( is_wp_error( $private ) || 'private' !== $private['item']['status'] ) {
	fwrite( STDERR, "content-status-cli: pending-to-private failed.\n" );
	exit( 1 );
}

$published = $status->set_status(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => $private['item']['modified_gmt'],
		'expected_status'       => 'private',
		'status'                => 'publish',
	)
);
if ( is_wp_error( $published ) || 'publish' !== $published['item']['status'] ) {
	fwrite( STDERR, "content-status-cli: private-to-publish failed.\n" );
	exit( 1 );
}

$future_gmt = gmdate( 'Y-m-d H:i:s', time() + 7200 );
$future = $status->set_status(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => $published['item']['modified_gmt'],
		'expected_status'       => 'publish',
		'status'                => 'future',
		'date_gmt'              => $future_gmt,
	)
);
if ( is_wp_error( $future ) || 'future' !== $future['item']['status'] || $future_gmt !== $future['item']['date_gmt'] ) {
	fwrite( STDERR, "content-status-cli: publish-to-future scheduling failed.\n" );
	exit( 1 );
}

$publish_now = $status->set_status(
	'post',
	array(
		'id'                    => $post_id,
		'expected_modified_gmt' => $future['item']['modified_gmt'],
		'expected_status'       => 'future',
		'status'                => 'publish',
	)
);
if ( is_wp_error( $publish_now ) || 'publish' !== $publish_now['item']['status'] ) {
	fwrite( STDERR, "content-status-cli: scheduled-to-publish-now failed.\n" );
	exit( 1 );
}

$page_pending = $status->set_status(
	'page',
	array(
		'id'                    => $page_id,
		'expected_modified_gmt' => $page['item']['modified_gmt'],
		'expected_status'       => 'draft',
		'status'                => 'pending',
	)
);
$page_publish = is_wp_error( $page_pending ) ? $page_pending : $status->set_status(
	'page',
	array(
		'id'                    => $page_id,
		'expected_modified_gmt' => $page_pending['item']['modified_gmt'],
		'expected_status'       => 'pending',
		'status'                => 'publish',
	)
);
$page_draft = is_wp_error( $page_publish ) ? $page_publish : $status->set_status(
	'page',
	array(
		'id'                    => $page_id,
		'expected_modified_gmt' => $page_publish['item']['modified_gmt'],
		'expected_status'       => 'publish',
		'status'                => 'draft',
	)
);
if ( is_wp_error( $page_pending ) || is_wp_error( $page_publish ) || is_wp_error( $page_draft ) || 'draft' !== $page_draft['item']['status'] ) {
	fwrite( STDERR, "content-status-cli: page status transition sequence failed.\n" );
	exit( 1 );
}

$login = 'cmsa_status_limited_' . strtolower( wp_generate_password( 8, false, false ) );
$limited_id = wp_create_user( $login, wp_generate_password( 20, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $limited_id ) ) {
	fwrite( STDERR, "content-status-cli: limited user creation failed.\n" );
	exit( 1 );
}
$limited = new WP_User( $limited_id );
$limited->set_role( 'subscriber' );
$limited->add_cap( 'edit_posts', true );
clean_user_cache( $limited_id );
wp_set_current_user( $limited_id );
$limited_post = $content->create_draft( 'post', array( 'title' => 'CMSA limited status post' ) );
if ( is_wp_error( $limited_post ) ) {
	fwrite( STDERR, "content-status-cli: limited user's draft creation failed.\n" );
	exit( 1 );
}
$limited_post_id = (int) $limited_post['item']['id'];
$limited_pending = $status->set_status(
	'post',
	array(
		'id'                    => $limited_post_id,
		'expected_modified_gmt' => $limited_post['item']['modified_gmt'],
		'expected_status'       => 'draft',
		'status'                => 'pending',
	)
);
if ( is_wp_error( $limited_pending ) || 'pending' !== $limited_pending['item']['status'] ) {
	fwrite( STDERR, "content-status-cli: limited user could not set pending.\n" );
	exit( 1 );
}
$denied_publish = $status->set_status(
	'post',
	array(
		'id'                    => $limited_post_id,
		'expected_modified_gmt' => $limited_pending['item']['modified_gmt'],
		'expected_status'       => 'pending',
		'status'                => 'publish',
	)
);
if ( ! is_wp_error( $denied_publish ) || 'cmsa_content_publish_permission' !== $denied_publish->get_error_code() || 'pending' !== get_post_status( $limited_post_id ) ) {
	fwrite( STDERR, "content-status-cli: publish capability boundary failed.\n" );
	exit( 1 );
}

wp_set_current_user( $admin->ID );
$control_after = get_post( $control_id );
$control_after_signature = $control_after->post_status . '|' . $control_after->post_content . '|' . $control_after->post_modified_gmt;
if ( $control_signature !== $control_after_signature ) {
	fwrite( STDERR, "content-status-cli: unrelated control content changed.\n" );
	exit( 1 );
}

wp_delete_post( $limited_post_id, true );
wp_delete_post( $post_id, true );
wp_delete_post( $page_id, true );
wp_delete_post( $control_id, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $limited_id );

echo "content-status-cli: PASS conflicts=timestamp,status post=pending-private-publish-future-publish page=pending-publish-draft limited=publish-denied unrelated=unchanged\n";
