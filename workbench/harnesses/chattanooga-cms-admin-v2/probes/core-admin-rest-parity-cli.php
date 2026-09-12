<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

$cmsa_admin_rest_post_id    = 0;
$cmsa_admin_rest_comment_id = 0;
$cmsa_admin_rest_media_id   = 0;

function cmsa_v2_admin_rest_cleanup() {
	global $cmsa_admin_rest_post_id, $cmsa_admin_rest_comment_id, $cmsa_admin_rest_media_id;

	if ( $cmsa_admin_rest_comment_id > 0 && get_comment( $cmsa_admin_rest_comment_id ) instanceof WP_Comment ) {
		wp_delete_comment( $cmsa_admin_rest_comment_id, true );
	}
	$cmsa_admin_rest_comment_id = 0;

	if ( $cmsa_admin_rest_media_id > 0 && get_post( $cmsa_admin_rest_media_id ) instanceof WP_Post ) {
		wp_delete_attachment( $cmsa_admin_rest_media_id, true );
	}
	$cmsa_admin_rest_media_id = 0;

	if ( $cmsa_admin_rest_post_id > 0 && get_post( $cmsa_admin_rest_post_id ) instanceof WP_Post ) {
		wp_delete_post( $cmsa_admin_rest_post_id, true );
	}
	$cmsa_admin_rest_post_id = 0;
}

function cmsa_v2_admin_rest_fail( $message ) {
	cmsa_v2_admin_rest_cleanup();
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_admin_rest_catalog() {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}

	$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
	if ( ! $catalog instanceof WP_Ability ) {
		cmsa_v2_admin_rest_fail( 'Universal catalog is unavailable.' );
	}
	$result = $catalog->execute( array() );
	if ( is_wp_error( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
		cmsa_v2_admin_rest_fail( 'Universal catalog could not enumerate REST facades.' );
	}
	$items = $result['items'];
	return $items;
}

function cmsa_v2_admin_rest_ability( $method, $path ) {
	$method = strtoupper( (string) $method );
	$matches = array();
	foreach ( cmsa_v2_admin_rest_catalog() as $item ) {
		if ( 'rest' !== ( $item['contract'] ?? '' ) || $method !== ( $item['method'] ?? '' ) ) {
			continue;
		}
		$route  = (string) ( $item['route'] ?? '' );
		$bridge = (string) ( $item['bridge'] ?? '' );
		if ( '' !== $route && '' !== $bridge && 1 === @preg_match( '@^' . $route . '$@i', $path ) ) {
			$matches[] = $bridge;
		}
	}

	if ( 1 !== count( $matches ) ) {
		cmsa_v2_admin_rest_fail( sprintf( 'Expected one %1$s facade for %2$s; found %3$d.', $method, $path, count( $matches ) ) );
	}
	$ability = wp_get_ability( $matches[0] );
	if ( ! $ability instanceof WP_Ability ) {
		cmsa_v2_admin_rest_fail( 'Discovered REST facade is unavailable.' );
	}
	return $ability;
}

function cmsa_v2_admin_rest_call( $method, $path, array $params = array() ) {
	$ability = cmsa_v2_admin_rest_ability( $method, $path );
	$input = array( 'path' => $path, 'params' => $params );
	$permission = $ability->check_permissions( $input );
	if ( true !== $permission ) {
		cmsa_v2_admin_rest_fail( sprintf( 'Permission failed for %1$s %2$s.', $method, $path ) );
	}
	$result = $ability->execute( $input );
	if ( is_wp_error( $result ) ) {
		cmsa_v2_admin_rest_fail( sprintf( '%1$s %2$s failed: %3$s %4$s', $method, $path, $result->get_error_code(), $result->get_error_message() ) );
	}
	if ( ! is_array( $result ) || (int) ( $result['status'] ?? 0 ) < 200 || (int) ( $result['status'] ?? 0 ) >= 300 || ! array_key_exists( 'data', $result ) ) {
		cmsa_v2_admin_rest_fail( sprintf( '%1$s %2$s returned an invalid facade response.', $method, $path ) );
	}
	return $result['data'];
}

wp_set_current_user( 1 );
$suffix = substr( hash( 'sha256', wp_generate_uuid4() ), 0, 10 );

foreach ( array(
	array( 'POST', '/wp/v2/comments' ),
	array( 'GET', '/wp/v2/comments/1' ),
	array( 'POST', '/wp/v2/comments/1' ),
	array( 'DELETE', '/wp/v2/comments/1' ),
	array( 'GET', '/wp/v2/media/1' ),
	array( 'POST', '/wp/v2/media/1' ),
	array( 'DELETE', '/wp/v2/media/1' ),
) as $route ) {
	cmsa_v2_admin_rest_ability( $route[0], $route[1] );
}

$cmsa_admin_rest_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'CMSA Admin REST Fixture ' . $suffix,
		'post_content' => 'Disposable parent post for comment administration parity.',
	)
);
if ( is_wp_error( $cmsa_admin_rest_post_id ) || (int) $cmsa_admin_rest_post_id < 1 ) {
	cmsa_v2_admin_rest_fail( 'Could not create the disposable parent post.' );
}
$cmsa_admin_rest_post_id = (int) $cmsa_admin_rest_post_id;

$comment = cmsa_v2_admin_rest_call(
	'POST',
	'/wp/v2/comments',
	array(
		'post'         => $cmsa_admin_rest_post_id,
		'author'       => 1,
		'author_name'  => 'CMSA Admin',
		'author_email' => 'simulation@example.invalid',
		'content'      => 'CMSA REST comment ' . $suffix,
		'status'       => 'approved',
	)
);
$cmsa_admin_rest_comment_id = (int) ( $comment['id'] ?? 0 );
if ( $cmsa_admin_rest_comment_id < 1 || 'approved' !== (string) ( $comment['status'] ?? '' ) ) {
	cmsa_v2_admin_rest_fail( 'Core REST comment creation failed.' );
}

$comment_read = cmsa_v2_admin_rest_call( 'GET', '/wp/v2/comments/' . $cmsa_admin_rest_comment_id, array( 'context' => 'edit' ) );
if ( (int) ( $comment_read['id'] ?? 0 ) !== $cmsa_admin_rest_comment_id || (int) ( $comment_read['post'] ?? 0 ) !== $cmsa_admin_rest_post_id ) {
	cmsa_v2_admin_rest_fail( 'Core REST comment readback failed.' );
}

$comment_update = cmsa_v2_admin_rest_call(
	'POST',
	'/wp/v2/comments/' . $cmsa_admin_rest_comment_id,
	array(
		'content' => 'CMSA REST comment updated ' . $suffix,
		'status'  => 'hold',
	)
);
if ( (int) ( $comment_update['id'] ?? 0 ) !== $cmsa_admin_rest_comment_id
	|| 'hold' !== (string) ( $comment_update['status'] ?? '' )
	|| false === strpos( (string) ( $comment_update['content']['rendered'] ?? '' ), 'updated' ) ) {
	cmsa_v2_admin_rest_fail( 'Core REST comment update/moderation failed.' );
}

if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagepng' ) ) {
	cmsa_v2_admin_rest_fail( 'GD PNG support is required for media REST parity.' );
}
$upload = wp_get_ability( 'chattanooga-cms-admin/upload-media' );
if ( ! $upload instanceof WP_Ability ) {
	cmsa_v2_admin_rest_fail( 'Native media upload ability is unavailable.' );
}
$image = imagecreatetruecolor( 4, 3 );
if ( false === $image ) {
	cmsa_v2_admin_rest_fail( 'Could not create media REST parity fixture.' );
}
$color = imagecolorallocate( $image, 62, 108, 151 );
imagefill( $image, 0, 0, $color );
ob_start();
$png_written = imagepng( $image );
$png = ob_get_clean();
imagedestroy( $image );
if ( true !== $png_written || ! is_string( $png ) || '' === $png ) {
	cmsa_v2_admin_rest_fail( 'Could not encode media REST parity fixture.' );
}

$media = $upload->execute(
	array(
		'filename'    => 'cmsa-admin-rest-' . $suffix . '.png',
		'mime_type'   => 'image/png',
		'data_base64' => base64_encode( $png ),
		'title'       => 'CMSA Media REST ' . $suffix,
		'alt_text'    => 'CMSA media REST fixture',
	)
);
if ( is_wp_error( $media ) || empty( $media['item']['id'] ) ) {
	cmsa_v2_admin_rest_fail( 'Could not create media fixture through native upload ability.' );
}
$cmsa_admin_rest_media_id = (int) $media['item']['id'];

$media_read = cmsa_v2_admin_rest_call( 'GET', '/wp/v2/media/' . $cmsa_admin_rest_media_id, array( 'context' => 'edit' ) );
if ( (int) ( $media_read['id'] ?? 0 ) !== $cmsa_admin_rest_media_id || 'image/png' !== (string) ( $media_read['mime_type'] ?? '' ) ) {
	cmsa_v2_admin_rest_fail( 'Core REST media readback failed.' );
}

$media_update = cmsa_v2_admin_rest_call(
	'POST',
	'/wp/v2/media/' . $cmsa_admin_rest_media_id,
	array(
		'title'       => 'CMSA Media REST Updated ' . $suffix,
		'caption'     => 'Updated media caption ' . $suffix,
		'description' => 'Updated media description ' . $suffix,
		'alt_text'    => 'Updated media alt ' . $suffix,
	)
);
if ( (int) ( $media_update['id'] ?? 0 ) !== $cmsa_admin_rest_media_id
	|| false === strpos( (string) ( $media_update['title']['rendered'] ?? '' ), 'Updated' )
	|| false === strpos( (string) ( $media_update['caption']['rendered'] ?? '' ), 'Updated media caption' )
	|| (string) ( $media_update['alt_text'] ?? '' ) !== 'Updated media alt ' . $suffix ) {
	cmsa_v2_admin_rest_fail( 'Core REST media metadata update failed.' );
}

wp_set_current_user( 0 );
$blocked = cmsa_v2_admin_rest_ability( 'POST', '/wp/v2/comments/' . $cmsa_admin_rest_comment_id );
if ( false !== $blocked->check_permissions( array( 'path' => '/wp/v2/comments/' . $cmsa_admin_rest_comment_id, 'params' => array( 'status' => 'approved' ) ) ) ) {
	cmsa_v2_admin_rest_fail( 'Anonymous comment moderation was not blocked.' );
}
wp_set_current_user( 1 );

$comment_delete = cmsa_v2_admin_rest_call( 'DELETE', '/wp/v2/comments/' . $cmsa_admin_rest_comment_id, array( 'force' => true ) );
if ( empty( $comment_delete['deleted'] ) || get_comment( $cmsa_admin_rest_comment_id ) instanceof WP_Comment ) {
	cmsa_v2_admin_rest_fail( 'Core REST comment deletion was not verified.' );
}
$cmsa_admin_rest_comment_id = 0;

$media_file = get_attached_file( $cmsa_admin_rest_media_id );
$media_delete = cmsa_v2_admin_rest_call( 'DELETE', '/wp/v2/media/' . $cmsa_admin_rest_media_id, array( 'force' => true ) );
if ( empty( $media_delete['deleted'] )
	|| get_post( $cmsa_admin_rest_media_id ) instanceof WP_Post
	|| ( is_string( $media_file ) && is_file( $media_file ) ) ) {
	cmsa_v2_admin_rest_fail( 'Core REST media deletion did not remove the attachment and file.' );
}
$cmsa_admin_rest_media_id = 0;

wp_delete_post( $cmsa_admin_rest_post_id, true );
$cmsa_admin_rest_post_id = 0;

echo "cmsa-v2-core-admin-rest: PASS comments=crud_moderation media=read_update_delete dynamic_bridge=verified admin_boundary=verified final_state=restored\n";
exit( 0 );
