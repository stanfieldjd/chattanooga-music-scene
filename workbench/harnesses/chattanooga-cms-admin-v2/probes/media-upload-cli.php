<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

$created_attachment_id = 0;

function cmsa_v2_media_upload_cleanup() {
	global $created_attachment_id;
	if ( $created_attachment_id > 0 && get_post( $created_attachment_id ) instanceof WP_Post ) {
		wp_delete_attachment( $created_attachment_id, true );
	}
	$created_attachment_id = 0;
}

function cmsa_v2_media_upload_fail( $message ) {
	cmsa_v2_media_upload_cleanup();
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_media_attachment_ids() {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		)
	);
}

if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagepng' ) ) {
	cmsa_v2_media_upload_fail( 'GD PNG support is required for the media upload probe.' );
}

wp_set_current_user( 1 );
$ability = wp_get_ability( 'chattanooga-cms-admin/upload-media' );
if ( ! $ability instanceof WP_Ability ) {
	cmsa_v2_media_upload_fail( 'Native media upload ability is unavailable.' );
}

$permission = $ability->check_permissions( array() );
if ( true !== $permission ) {
	cmsa_v2_media_upload_fail( 'Administrator permission check failed for media upload.' );
}

wp_set_current_user( 0 );
$anonymous_permission = $ability->check_permissions( array() );
if ( true === $anonymous_permission ) {
	cmsa_v2_media_upload_fail( 'Anonymous user was allowed to upload media.' );
}
wp_set_current_user( 1 );

$image = imagecreatetruecolor( 3, 2 );
if ( false === $image ) {
	cmsa_v2_media_upload_fail( 'Could not create the disposable PNG fixture.' );
}
$image_color = imagecolorallocate( $image, 31, 97, 141 );
imagefill( $image, 0, 0, $image_color );
ob_start();
$png_written = imagepng( $image );
$png = ob_get_clean();
imagedestroy( $image );
if ( true !== $png_written || ! is_string( $png ) || '' === $png ) {
	cmsa_v2_media_upload_fail( 'Could not encode the disposable PNG fixture.' );
}

$before_ids = cmsa_v2_media_attachment_ids();
$mismatch = $ability->execute(
	array(
		'filename'    => 'cmsa-media-mismatch.png',
		'mime_type'   => 'image/jpeg',
		'data_base64' => base64_encode( $png ),
	)
);
if ( ! is_wp_error( $mismatch ) || 'cmsa_media_upload_mime' !== $mismatch->get_error_code() ) {
	cmsa_v2_media_upload_fail( 'Mismatched media MIME payload was not rejected.' );
}
$after_mismatch_ids = cmsa_v2_media_attachment_ids();
if ( $before_ids !== $after_mismatch_ids ) {
	cmsa_v2_media_upload_fail( 'Rejected media payload left an attachment behind.' );
}

$result = $ability->execute(
	array(
		'filename'    => 'cmsa-media-probe.png',
		'mime_type'   => 'image/png',
		'data_base64' => base64_encode( $png ),
		'title'       => 'CMSA Media Probe',
		'caption'     => 'Disposable media upload probe.',
		'description' => '<p>Disposable native media upload test.</p>',
		'alt_text'    => 'CMSA media probe image',
		'parent_id'   => 0,
	)
);
if ( is_wp_error( $result ) ) {
	cmsa_v2_media_upload_fail( 'Media upload failed: ' . $result->get_error_code() . ' ' . $result->get_error_message() );
}
if ( ! is_array( $result ) || empty( $result['created'] ) || empty( $result['item']['id'] ) ) {
	cmsa_v2_media_upload_fail( 'Media upload returned an invalid result.' );
}

$created_attachment_id = (int) $result['item']['id'];
$attachment = get_post( $created_attachment_id );
if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || 'image/png' !== $attachment->post_mime_type ) {
	cmsa_v2_media_upload_fail( 'Created attachment identity or MIME type is invalid.' );
}

$file = get_attached_file( $created_attachment_id );
if ( ! is_string( $file ) || ! is_file( $file ) ) {
	cmsa_v2_media_upload_fail( 'Created media file is missing.' );
}
$file_hash = hash_file( 'sha256', $file );
if ( ! is_string( $file_hash ) || ! isset( $result['sha256'] ) || ! hash_equals( (string) $result['sha256'], $file_hash ) ) {
	cmsa_v2_media_upload_fail( 'Created media hash verification failed.' );
}

if ( 'CMSA Media Probe' !== (string) $attachment->post_title
	|| 'Disposable media upload probe.' !== (string) $attachment->post_excerpt
	|| '<p>Disposable native media upload test.</p>' !== (string) $attachment->post_content
	|| 'CMSA media probe image' !== (string) get_post_meta( $created_attachment_id, '_wp_attachment_image_alt', true ) ) {
	cmsa_v2_media_upload_fail( 'Created media metadata did not persist exactly.' );
}

$metadata = wp_get_attachment_metadata( $created_attachment_id );
if ( ! is_array( $metadata ) || 3 !== (int) ( $metadata['width'] ?? 0 ) || 2 !== (int) ( $metadata['height'] ?? 0 ) ) {
	cmsa_v2_media_upload_fail( 'Created media image metadata was not generated correctly.' );
}

$created_file = $file;
$deleted = wp_delete_attachment( $created_attachment_id, true );
if ( false === $deleted ) {
	cmsa_v2_media_upload_fail( 'Disposable media attachment could not be removed.' );
}
$created_attachment_id = 0;
if ( get_post( (int) $deleted->ID ) instanceof WP_Post || is_file( $created_file ) ) {
	cmsa_v2_media_upload_fail( 'Disposable media attachment cleanup was incomplete.' );
}

$final_ids = cmsa_v2_media_attachment_ids();
if ( $before_ids !== $final_ids ) {
	cmsa_v2_media_upload_fail( 'Media upload probe did not restore the initial attachment set.' );
}

echo "cmsa-v2-media-upload: PASS ability=registered upload=verified file=verified metadata=verified mime_boundary=verified admin_boundary=verified cleanup=verified\n";
