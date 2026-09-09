<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "media-delete-reference-contract-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$fail = static function ( $message ) {
	fwrite( STDERR, 'media-delete-reference-contract-cli: ' . $message . "\n" );
	exit( 1 );
};

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	$fail( 'administrator fixture missing.' );
}
wp_set_current_user( $admin->ID );

foreach ( array( 'imagecreatetruecolor', 'imagepng', 'imagedestroy' ) as $function ) {
	if ( ! function_exists( $function ) ) {
		$fail( 'GD image fixture support is unavailable.' );
	}
}

$make_png = static function ( $basename, $width, $height ) use ( $fail ) {
	$temp = wp_tempnam( $basename );
	if ( ! $temp ) {
		$fail( 'could not allocate PNG fixture.' );
	}
	$image = imagecreatetruecolor( $width, $height );
	if ( ! $image ) {
		@unlink( $temp );
		$fail( 'could not create PNG fixture image.' );
	}
	$background = imagecolorallocate( $image, 42, 108, 154 );
	imagefilledrectangle( $image, 0, 0, $width - 1, $height - 1, $background );
	if ( ! imagepng( $image, $temp, 9 ) ) {
		imagedestroy( $image );
		@unlink( $temp );
		$fail( 'could not write PNG fixture image.' );
	}
	imagedestroy( $image );
	return $temp;
};

$owned_paths = static function ( $primary, $metadata ) {
	$paths = array();
	if ( is_string( $primary ) && '' !== $primary ) {
		$paths[] = $primary;
	}
	if ( is_array( $metadata ) && is_string( $primary ) && '' !== $primary ) {
		$directory = dirname( $primary );
		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( is_array( $size ) && ! empty( $size['file'] ) ) {
					$paths[] = path_join( $directory, basename( (string) $size['file'] ) );
				}
			}
		}
		foreach ( array( 'original_image', 'source_image', 'animated_video', 'animated_video_poster' ) as $key ) {
			if ( ! empty( $metadata[ $key ] ) && is_string( $metadata[ $key ] ) ) {
				$paths[] = path_join( $directory, basename( $metadata[ $key ] ) );
			}
		}
	}
	return array_values( array_unique( $paths ) );
};

$create_post = static function ( $title, $content = '' ) use ( $fail ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		$fail( 'could not create consumer post: ' . $title );
	}
	return (int) $id;
};

$parent_id = $create_post( 'CMSA media delete parent', 'parent-control-content' );
$featured_post_id = $create_post( 'CMSA media delete featured consumer', 'featured-control-content' );
$url_post_id = $create_post( 'CMSA media delete URL consumer' );
$gallery_post_id = $create_post( 'CMSA media delete gallery consumer' );
$meta_post_id = $create_post( 'CMSA media delete metadata consumer', 'meta-control-content' );
$unrelated_post_id = $create_post( 'CMSA media delete unrelated control', 'unrelated-control-content' );

$temp = $make_png( 'cmsa-media-delete-reference.png', 640, 480 );
$attachment_id = media_handle_sideload(
	array(
		'name'     => 'cmsa-media-delete-reference.png',
		'tmp_name' => $temp,
	),
	$parent_id,
	'CMSA media delete reference fixture'
);
if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
	if ( is_file( $temp ) ) {
		@unlink( $temp );
	}
	$fail( 'attachment fixture creation failed.' );
}
$attachment_id = (int) $attachment_id;

$attachment = get_post( $attachment_id );
$primary = get_attached_file( $attachment_id );
$metadata = wp_get_attachment_metadata( $attachment_id );
$url = wp_get_attachment_url( $attachment_id );
if ( ! $attachment instanceof WP_Post || ! is_string( $primary ) || ! is_file( $primary ) || ! is_array( $metadata ) || ! is_string( $url ) || '' === $url ) {
	$fail( 'attachment fixture did not establish complete state.' );
}
$paths = $owned_paths( $primary, $metadata );
if ( count( $paths ) < 2 ) {
	$fail( 'attachment fixture did not generate derivative files.' );
}
foreach ( $paths as $path ) {
	if ( ! is_file( $path ) ) {
		$fail( 'attachment-owned file missing before deletion: ' . basename( $path ) );
	}
}

if ( ! set_post_thumbnail( $featured_post_id, $attachment_id ) || (int) get_post_thumbnail_id( $featured_post_id ) !== $attachment_id ) {
	$fail( 'featured-image relationship fixture failed.' );
}

$url_content = '<p>Direct attachment URL consumer.</p><img src="' . esc_url( $url ) . '" alt="delete-reference" />';
$result = wp_update_post( array( 'ID' => $url_post_id, 'post_content' => $url_content ), true );
if ( is_wp_error( $result ) || ! $result ) {
	$fail( 'direct URL content fixture failed.' );
}

$gallery_content = '[gallery ids="' . $attachment_id . '"]';
$result = wp_update_post( array( 'ID' => $gallery_post_id, 'post_content' => $gallery_content ), true );
if ( is_wp_error( $result ) || ! $result ) {
	$fail( 'gallery ID content fixture failed.' );
}

update_post_meta( $meta_post_id, '_cmsa_media_delete_reference_id', $attachment_id );
update_post_meta( $meta_post_id, '_cmsa_media_delete_reference_url', $url );
$meta_id_before = get_post_meta( $meta_post_id, '_cmsa_media_delete_reference_id', true );
$meta_url_before = get_post_meta( $meta_post_id, '_cmsa_media_delete_reference_url', true );
if ( (int) $meta_id_before !== $attachment_id || (string) $meta_url_before !== $url ) {
	$fail( 'postmeta reference fixture failed.' );
}

$option_name = 'cmsa_media_delete_reference_option';
$option_before = array( 'attachment_id' => $attachment_id, 'url' => $url, 'sentinel' => 'unchanged' );
update_option( $option_name, $option_before, false );
if ( get_option( $option_name ) !== $option_before ) {
	$fail( 'option reference fixture failed.' );
}

$parent_before = get_post( $parent_id );
$featured_before = get_post( $featured_post_id );
$url_before = get_post( $url_post_id );
$gallery_before = get_post( $gallery_post_id );
$meta_before = get_post( $meta_post_id );
$unrelated_before = get_post( $unrelated_post_id );
if ( ! $parent_before instanceof WP_Post || ! $featured_before instanceof WP_Post || ! $url_before instanceof WP_Post || ! $gallery_before instanceof WP_Post || ! $meta_before instanceof WP_Post || ! $unrelated_before instanceof WP_Post ) {
	$fail( 'consumer snapshot failed.' );
}

$deleted = wp_delete_attachment( $attachment_id, true );
if ( false === $deleted ) {
	$fail( 'native permanent attachment deletion returned failure.' );
}
if ( null !== get_post( $attachment_id ) ) {
	$fail( 'attachment post survived permanent deletion.' );
}
foreach ( $paths as $path ) {
	clearstatcache( true, $path );
	if ( is_file( $path ) ) {
		$fail( 'attachment-owned file survived permanent deletion: ' . basename( $path ) );
	}
}

if ( 0 !== (int) get_post_thumbnail_id( $featured_post_id ) ) {
	$fail( 'featured-image relationship was not cleared by native deletion.' );
}

$parent_after = get_post( $parent_id );
$featured_after = get_post( $featured_post_id );
$url_after = get_post( $url_post_id );
$gallery_after = get_post( $gallery_post_id );
$meta_after = get_post( $meta_post_id );
$unrelated_after = get_post( $unrelated_post_id );
if ( ! $parent_after instanceof WP_Post || ! $featured_after instanceof WP_Post || ! $url_after instanceof WP_Post || ! $gallery_after instanceof WP_Post || ! $meta_after instanceof WP_Post || ! $unrelated_after instanceof WP_Post ) {
	$fail( 'native deletion removed a consumer or parent object.' );
}
if ( (string) $parent_after->post_content !== (string) $parent_before->post_content ) {
	$fail( 'native deletion changed parent content.' );
}
if ( (string) $featured_after->post_content !== (string) $featured_before->post_content ) {
	$fail( 'native deletion changed unrelated featured-consumer content.' );
}
if ( (string) $url_after->post_content !== $url_content || false === strpos( (string) $url_after->post_content, $url ) ) {
	$fail( 'direct URL content reference was rewritten or removed unexpectedly.' );
}
if ( (string) $gallery_after->post_content !== $gallery_content || false === strpos( (string) $gallery_after->post_content, (string) $attachment_id ) ) {
	$fail( 'core gallery ID content reference was rewritten or removed unexpectedly.' );
}
if ( (string) $meta_after->post_content !== (string) $meta_before->post_content ) {
	$fail( 'native deletion changed metadata-consumer content.' );
}
if ( (int) get_post_meta( $meta_post_id, '_cmsa_media_delete_reference_id', true ) !== $attachment_id ) {
	$fail( 'postmeta attachment-ID reference did not persist exactly.' );
}
if ( (string) get_post_meta( $meta_post_id, '_cmsa_media_delete_reference_url', true ) !== $url ) {
	$fail( 'postmeta attachment-URL reference did not persist exactly.' );
}
if ( get_option( $option_name ) !== $option_before ) {
	$fail( 'option attachment references did not persist exactly.' );
}
if ( (string) $unrelated_after->post_content !== (string) $unrelated_before->post_content ) {
	$fail( 'unrelated control post changed.' );
}

foreach ( array( $featured_post_id, $url_post_id, $gallery_post_id, $meta_post_id, $unrelated_post_id, $parent_id ) as $post_id ) {
	wp_delete_post( $post_id, true );
}
delete_option( $option_name );

echo 'media-delete-reference-contract-cli: PASS attachment=absent files=absent featured=cleared parent=preserved unmanaged=post-content-url,gallery-id,postmeta-id,postmeta-url,option-id,option-url consumers=preserved reference-graph=not-native-complete\n';
