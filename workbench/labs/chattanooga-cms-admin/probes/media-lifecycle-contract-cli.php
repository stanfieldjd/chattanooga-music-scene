<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "media-lifecycle-contract-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$fail = static function ( $message ) {
	fwrite( STDERR, 'media-lifecycle-contract-cli: ' . $message . "\n" );
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

$make_png = static function ( $basename, $red, $green, $blue ) use ( $fail ) {
	$temp = wp_tempnam( $basename );
	if ( ! $temp ) {
		$fail( 'could not allocate PNG fixture.' );
	}
	$image = imagecreatetruecolor( 512, 512 );
	if ( ! $image ) {
		$fail( 'could not create PNG fixture image.' );
	}
	$background = imagecolorallocate( $image, $red, $green, $blue );
	imagefilledrectangle( $image, 0, 0, 511, 511, $background );
	if ( ! imagepng( $image, $temp, 9 ) ) {
		imagedestroy( $image );
		@unlink( $temp );
		$fail( 'could not write PNG fixture image.' );
	}
	imagedestroy( $image );
	return $temp;
};

$target_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'CMSA media lifecycle target',
		'post_content' => 'initial',
	),
	true
);
if ( is_wp_error( $target_id ) || ! $target_id ) {
	$fail( 'target post creation failed.' );
}

$temp = $make_png( 'cmsa-media-lifecycle.png', 18, 86, 140 );
$file_array = array(
	'name'     => 'cmsa-media-lifecycle.png',
	'tmp_name' => $temp,
);
$attachment_id = media_handle_sideload(
	$file_array,
	(int) $target_id,
	'CMSA media lifecycle attachment',
	array(
		'post_title'   => 'CMSA media lifecycle attachment',
		'post_excerpt' => 'lifecycle fixture',
	)
);
if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
	if ( is_file( $temp ) ) {
		@unlink( $temp );
	}
	$fail( 'attachment fixture creation failed.' );
}
$attachment_id = (int) $attachment_id;

$attachment = get_post( $attachment_id );
$file = get_attached_file( $attachment_id );
$metadata = wp_get_attachment_metadata( $attachment_id );
$url = wp_get_attachment_url( $attachment_id );
if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! is_string( $file ) || ! is_file( $file ) || ! is_array( $metadata ) || ! is_string( $url ) || '' === $url ) {
	$fail( 'attachment fixture did not establish a complete native media state.' );
}
if ( (int) $attachment->post_parent !== (int) $target_id ) {
	$fail( 'attachment parent identity was not preserved.' );
}

$derived_files = array();
if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
	foreach ( $metadata['sizes'] as $size ) {
		if ( ! is_array( $size ) || empty( $size['file'] ) ) {
			continue;
		}
		$path = path_join( dirname( $file ), basename( (string) $size['file'] ) );
		if ( is_file( $path ) ) {
			$derived_files[] = $path;
		}
	}
}
$derived_files = array_values( array_unique( $derived_files ) );
if ( empty( $derived_files ) ) {
	$fail( 'WordPress did not generate a derivative image fixture.' );
}

if ( ! set_post_thumbnail( $target_id, $attachment_id ) || (int) get_post_thumbnail_id( $target_id ) !== $attachment_id ) {
	$fail( 'featured-image fixture relationship failed.' );
}
$content = '<p>Persistent direct media URL reference:</p><img src="' . esc_url( $url ) . '" alt="fixture" />';
$updated_post = wp_update_post( array( 'ID' => $target_id, 'post_content' => $content ), true );
if ( is_wp_error( $updated_post ) || ! $updated_post ) {
	$fail( 'content URL reference fixture failed.' );
}

$primitive_contract = array(
	'update_attached_file'            => function_exists( 'update_attached_file' ),
	'wp_generate_attachment_metadata' => function_exists( 'wp_generate_attachment_metadata' ),
	'wp_update_attachment_metadata'   => function_exists( 'wp_update_attachment_metadata' ),
	'wp_delete_attachment'            => function_exists( 'wp_delete_attachment' ),
	'wp_delete_attachment_files'      => function_exists( 'wp_delete_attachment_files' ),
);
foreach ( $primitive_contract as $name => $available ) {
	if ( ! $available ) {
		$fail( "required native media primitive is unavailable: {$name}." );
	}
}

wp_set_current_user( 0 );
if ( current_user_can( 'delete_post', $attachment_id ) ) {
	$fail( 'anonymous delete_post capability leaked.' );
}

$author_login = 'cmsa_media_lifecycle_' . strtolower( wp_generate_password( 8, false, false ) );
$author_id = wp_create_user( $author_login, wp_generate_password( 20, true, true ), $author_login . '@example.invalid' );
if ( is_wp_error( $author_id ) ) {
	$fail( 'author fixture creation failed.' );
}
$author = new WP_User( $author_id );
$author->set_role( 'author' );
$own_attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'CMSA media lifecycle owner capability fixture',
		'post_status'    => 'inherit',
		'post_author'    => $author_id,
	),
	false,
	0,
	true
);
if ( is_wp_error( $own_attachment_id ) || ! $own_attachment_id ) {
	$fail( 'owner attachment capability fixture failed.' );
}
$own_attachment_id = (int) $own_attachment_id;

wp_set_current_user( $author_id );
$author_upload = current_user_can( 'upload_files' );
$author_delete_own = current_user_can( 'delete_post', $own_attachment_id );
$author_delete_other = current_user_can( 'delete_post', $attachment_id );
if ( ! $author_upload || ! $author_delete_own || $author_delete_other ) {
	$fail( 'native attachment deletion capability mapping is outside the expected owner/object scope.' );
}

wp_set_current_user( $admin->ID );
if ( ! current_user_can( 'delete_post', $attachment_id ) ) {
	$fail( 'administrator lacks native delete_post authority for attachment fixture.' );
}

$primary_path = $file;
$primary_hash = hash_file( 'sha256', $primary_path );
$derived_hashes = array();
foreach ( $derived_files as $derived ) {
	$derived_hashes[ $derived ] = hash_file( 'sha256', $derived );
}
$target_before_delete = get_post( $target_id );
if ( ! $target_before_delete instanceof WP_Post || false === strpos( (string) $target_before_delete->post_content, $url ) ) {
	$fail( 'content URL reference was not established before deletion.' );
}

$deleted = wp_delete_attachment( $attachment_id, true );
if ( ! $deleted instanceof WP_Post || (int) $deleted->ID !== $attachment_id ) {
	$fail( 'native forced attachment deletion did not return the deleted attachment.' );
}
if ( null !== get_post( $attachment_id ) ) {
	$fail( 'attachment post survived native forced deletion.' );
}
if ( is_file( $primary_path ) ) {
	$fail( 'primary attachment file survived native forced deletion.' );
}
foreach ( $derived_files as $derived ) {
	if ( is_file( $derived ) ) {
		$fail( 'generated derivative survived native forced deletion.' );
	}
}
if ( 0 !== (int) get_post_thumbnail_id( $target_id ) ) {
	$fail( 'featured-image relationship survived native forced attachment deletion.' );
}
$target_after_delete = get_post( $target_id );
if ( ! $target_after_delete instanceof WP_Post ) {
	$fail( 'attachment parent/consumer post was removed with the attachment.' );
}
if ( (string) $target_after_delete->post_content !== (string) $target_before_delete->post_content || false === strpos( (string) $target_after_delete->post_content, $url ) ) {
	$fail( 'native deletion unexpectedly rewrote the direct content URL reference.' );
}
if ( get_post_meta( $attachment_id ) ) {
	$fail( 'attachment metadata survived native forced deletion.' );
}

wp_delete_attachment( $own_attachment_id, true );
wp_delete_user( $author_id );
wp_delete_post( $target_id, true );

echo 'media-lifecycle-contract-cli: PASS deletion=post-primary-derivatives-meta-removed featured=cleared content-url=unchanged parent=preserved permissions=object-scoped derivatives=' . count( $derived_files ) . ' primary_hash=' . substr( (string) $primary_hash, 0, 12 ) . ' primitive_contract=' . implode( ',', array_keys( array_filter( $primitive_contract ) ) ) . "\n";
