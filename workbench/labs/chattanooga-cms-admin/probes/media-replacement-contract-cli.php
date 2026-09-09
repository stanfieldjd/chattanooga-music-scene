<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "media-replacement-contract-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$fail = static function ( $message ) {
	fwrite( STDERR, 'media-replacement-contract-cli: ' . $message . "\n" );
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

$make_png = static function ( $basename, $width, $height, $red, $green, $blue ) use ( $fail ) {
	$temp = wp_tempnam( $basename );
	if ( ! $temp ) {
		$fail( 'could not allocate PNG fixture.' );
	}
	$image = imagecreatetruecolor( $width, $height );
	if ( ! $image ) {
		$fail( 'could not create PNG fixture image.' );
	}
	$background = imagecolorallocate( $image, $red, $green, $blue );
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
	if ( ! is_array( $metadata ) || ! is_string( $primary ) || '' === $primary ) {
		return array_values( array_unique( $paths ) );
	}
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
	return array_values( array_unique( $paths ) );
};

$snapshot_paths = static function ( array $paths ) use ( $fail ) {
	$snapshot = array();
	foreach ( $paths as $path ) {
		if ( ! is_file( $path ) ) {
			$fail( 'snapshot path is missing: ' . basename( $path ) );
		}
		$bytes = file_get_contents( $path );
		$hash = hash_file( 'sha256', $path );
		$mtime = filemtime( $path );
		if ( false === $bytes || false === $hash || false === $mtime ) {
			$fail( 'could not snapshot attachment file: ' . basename( $path ) );
		}
		$snapshot[ $path ] = array(
			'bytes' => $bytes,
			'hash'  => $hash,
			'mtime' => (int) $mtime,
		);
	}
	return $snapshot;
};

$verify_snapshot = static function ( array $snapshot ) {
	foreach ( $snapshot as $path => $state ) {
		if ( ! is_file( $path ) ) {
			return false;
		}
		$hash = hash_file( 'sha256', $path );
		if ( ! is_string( $hash ) || ! hash_equals( $state['hash'], $hash ) ) {
			return false;
		}
	}
	return true;
};

$target_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'CMSA media replacement target',
		'post_content' => 'initial',
	),
	true
);
if ( is_wp_error( $target_id ) || ! $target_id ) {
	$fail( 'target post creation failed.' );
}

$original_temp = $make_png( 'cmsa-media-replacement.png', 512, 512, 24, 96, 154 );
$attachment_id = media_handle_sideload(
	array(
		'name'     => 'cmsa-media-replacement.png',
		'tmp_name' => $original_temp,
	),
	(int) $target_id,
	'CMSA media replacement attachment',
	array(
		'post_title'   => 'CMSA media replacement attachment',
		'post_excerpt' => 'replacement fixture',
	)
);
if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
	if ( is_file( $original_temp ) ) {
		@unlink( $original_temp );
	}
	$fail( 'attachment fixture creation failed.' );
}
$attachment_id = (int) $attachment_id;

$attachment = get_post( $attachment_id );
$primary = get_attached_file( $attachment_id );
$metadata_before = wp_get_attachment_metadata( $attachment_id );
$url_before = wp_get_attachment_url( $attachment_id );
if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! is_string( $primary ) || ! is_file( $primary ) || ! is_array( $metadata_before ) || ! is_string( $url_before ) || '' === $url_before ) {
	$fail( 'attachment fixture did not establish complete media state.' );
}
if ( ! empty( get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true ) ) ) {
	$fail( 'fixture unexpectedly contains backup image sizes.' );
}
foreach ( array( 'original_image', 'source_image', 'animated_video', 'animated_video_poster' ) as $unsupported_fixture_key ) {
	if ( ! empty( $metadata_before[ $unsupported_fixture_key ] ) ) {
		$fail( 'fixture unexpectedly contains companion file metadata: ' . $unsupported_fixture_key );
	}
}

if ( ! set_post_thumbnail( $target_id, $attachment_id ) || (int) get_post_thumbnail_id( $target_id ) !== $attachment_id ) {
	$fail( 'featured-image fixture relationship failed.' );
}
$content_before = '<p>Persistent replacement URL reference:</p><img src="' . esc_url( $url_before ) . '" alt="fixture" />';
$result = wp_update_post( array( 'ID' => $target_id, 'post_content' => $content_before ), true );
if ( is_wp_error( $result ) || ! $result ) {
	$fail( 'content URL reference fixture failed.' );
}

$media = new CMSA_Media();
$item_before_result = $media->get_item( $attachment_id );
if ( is_wp_error( $item_before_result ) ) {
	$fail( 'candidate media read failed before replacement.' );
}
$item_before = $item_before_result['item'];
$owned_before = $owned_paths( $primary, $metadata_before );
if ( count( $owned_before ) < 2 ) {
	$fail( 'original fixture did not generate derivatives.' );
}
$snapshot = $snapshot_paths( $owned_before );
$attached_file_meta_before = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
$parent_before = (int) $attachment->post_parent;
$mime_before = (string) $attachment->post_mime_type;

$replacement_temp = $make_png( 'cmsa-media-replacement.png', 640, 360, 181, 62, 44 );
$checked = wp_check_filetype_and_ext( $replacement_temp, basename( $primary ), get_allowed_mime_types() );
if ( empty( $checked['type'] ) || 'image/png' !== sanitize_mime_type( $checked['type'] ) || 'png' !== sanitize_key( $checked['ext'] ?? '' ) ) {
	$fail( 'replacement payload failed WordPress extension/MIME validation.' );
}
if ( ! function_exists( 'wp_get_image_mime' ) || 'image/png' !== wp_get_image_mime( $replacement_temp ) ) {
	$fail( 'replacement payload MIME does not match image content.' );
}
$replacement_hash = hash_file( 'sha256', $replacement_temp );
if ( ! is_string( $replacement_hash ) || hash_equals( $snapshot[ $primary ]['hash'], $replacement_hash ) ) {
	$fail( 'replacement payload is not distinct from original.' );
}

$apply_replacement = static function ( $attachment_id, $primary, $replacement_temp ) use ( $owned_paths, $fail ) {
	if ( ! copy( $replacement_temp, $primary ) ) {
		$fail( 'could not replace primary attachment bytes.' );
	}
	clearstatcache( true, $primary );
	$generated = wp_generate_attachment_metadata( $attachment_id, $primary );
	if ( ! is_array( $generated ) || empty( $generated['width'] ) || empty( $generated['height'] ) ) {
		$fail( 'WordPress did not generate replacement attachment metadata.' );
	}
	wp_update_attachment_metadata( $attachment_id, $generated );
	$persisted = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $persisted ) || $persisted !== $generated ) {
		$fail( 'WordPress replacement attachment metadata readback does not match generated metadata.' );
	}
	return array(
		'metadata' => $generated,
		'files'    => $owned_paths( $primary, $generated ),
	);
};

$restore_snapshot = static function ( $attachment_id, $primary, array $metadata_before, array $snapshot, array $current_files ) use ( $owned_paths ) {
	$original_paths = array_keys( $snapshot );
	foreach ( array_unique( $current_files ) as $path ) {
		if ( $path !== $primary && ! in_array( $path, $original_paths, true ) && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
	foreach ( $snapshot as $path => $state ) {
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}
		$written = file_put_contents( $path, $state['bytes'], LOCK_EX );
		if ( false === $written || $written !== strlen( $state['bytes'] ) ) {
			return false;
		}
		@touch( $path, $state['mtime'] );
	}
	wp_update_attachment_metadata( $attachment_id, $metadata_before );
	clearstatcache();
	$current = wp_get_attachment_metadata( $attachment_id );
	if ( $current !== $metadata_before ) {
		return false;
	}
	foreach ( $owned_paths( $primary, $current ) as $path ) {
		if ( ! isset( $snapshot[ $path ] ) ) {
			return false;
		}
	}
	return true;
};

$first = $apply_replacement( $attachment_id, $primary, $replacement_temp );
$metadata_after = $first['metadata'];
$owned_after = $first['files'];
$old_only = array_values( array_diff( $owned_before, $owned_after ) );
$new_only = array_values( array_diff( $owned_after, $owned_before ) );
if ( empty( $old_only ) || empty( $new_only ) ) {
	$fail( 'replacement fixture did not produce a changed derivative set.' );
}
$orphaned_old = array_values( array_filter( $old_only, 'is_file' ) );
if ( empty( $orphaned_old ) ) {
	$fail( 'WordPress replacement metadata generation unexpectedly removed every obsolete derivative.' );
}
foreach ( $old_only as $path ) {
	if ( is_file( $path ) && ! wp_delete_file( $path ) ) {
		$fail( 'could not clean an obsolete original derivative.' );
	}
}
foreach ( $old_only as $path ) {
	if ( is_file( $path ) ) {
		$fail( 'obsolete derivative survived explicit cleanup.' );
	}
}

$after_post = get_post( $attachment_id );
$url_after = wp_get_attachment_url( $attachment_id );
$target_after = get_post( $target_id );
$item_after_result = $media->get_item( $attachment_id );
if ( ! $after_post instanceof WP_Post || is_wp_error( $item_after_result ) || ! $target_after instanceof WP_Post ) {
	$fail( 'replacement readback failed.' );
}
$item_after = $item_after_result['item'];
if ( (int) $after_post->ID !== $attachment_id || (string) $url_after !== (string) $url_before || (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) !== $attached_file_meta_before || (int) $after_post->post_parent !== $parent_before || (string) $after_post->post_mime_type !== $mime_before ) {
	$fail( 'same-path replacement changed attachment identity, URL, parent, attached-file path, or MIME type.' );
}
if ( (int) get_post_thumbnail_id( $target_id ) !== $attachment_id ) {
	$fail( 'same-ID replacement broke the featured-image relationship.' );
}
if ( (string) $target_after->post_content !== $content_before || false === strpos( (string) $target_after->post_content, $url_before ) ) {
	$fail( 'same-path replacement changed the direct content URL reference.' );
}
if ( 640 !== (int) $metadata_after['width'] || 360 !== (int) $metadata_after['height'] ) {
	$fail( 'replacement dimensions were not reflected in attachment metadata.' );
}
if ( ! is_file( $primary ) || ! hash_equals( $replacement_hash, (string) hash_file( 'sha256', $primary ) ) ) {
	$fail( 'replacement primary hash verification failed.' );
}
if ( hash_equals( $item_before['state_token'], $item_after['state_token'] ) ) {
	$fail( 'current media state token did not change after replacement.' );
}

if ( ! $restore_snapshot( $attachment_id, $primary, $metadata_before, $snapshot, $owned_after ) ) {
	$fail( 'semantic replacement rollback failed.' );
}
if ( ! $verify_snapshot( $snapshot ) ) {
	$fail( 'replacement rollback did not restore original file hashes.' );
}
$item_restored_result = $media->get_item( $attachment_id );
if ( is_wp_error( $item_restored_result ) || ! hash_equals( $item_before['state_token'], $item_restored_result['item']['state_token'] ) ) {
	$fail( 'replacement rollback did not restore the original candidate state token.' );
}
foreach ( $new_only as $path ) {
	if ( ! isset( $snapshot[ $path ] ) && is_file( $path ) ) {
		$fail( 'replacement rollback left a new-only derivative behind.' );
	}
}

$second = $apply_replacement( $attachment_id, $primary, $replacement_temp );
$fault_metadata = $second['metadata'];
$fault_metadata['width'] = 1;
wp_update_attachment_metadata( $attachment_id, $fault_metadata );
$fault_read = wp_get_attachment_metadata( $attachment_id );
if ( ! is_array( $fault_read ) || 1 !== (int) $fault_read['width'] ) {
	$fail( 'replacement verification fault did not persist.' );
}
if ( ! $restore_snapshot( $attachment_id, $primary, $metadata_before, $snapshot, $second['files'] ) ) {
	$fail( 'fault-injected replacement rollback failed.' );
}
if ( ! $verify_snapshot( $snapshot ) ) {
	$fail( 'fault-injected rollback did not restore original file hashes.' );
}
$item_fault_restored = $media->get_item( $attachment_id );
if ( is_wp_error( $item_fault_restored ) || ! hash_equals( $item_before['state_token'], $item_fault_restored['item']['state_token'] ) ) {
	$fail( 'fault-injected rollback did not restore original candidate state token.' );
}
$target_final = get_post( $target_id );
if ( ! $target_final instanceof WP_Post || (int) get_post_thumbnail_id( $target_id ) !== $attachment_id || (string) $target_final->post_content !== $content_before || (string) wp_get_attachment_url( $attachment_id ) !== (string) $url_before ) {
	$fail( 'rollback changed a protected reference or URL.' );
}

@unlink( $replacement_temp );
wp_delete_attachment( $attachment_id, true );
wp_delete_post( $target_id, true );

echo 'media-replacement-contract-cli: PASS identity=same-id url=same-path featured=preserved content-url=preserved parent=preserved mime=same orphaned-old-derivatives=' . count( $orphaned_old ) . ' new-only-derivatives=' . count( $new_only ) . ' rollback=exact-files-metadata-state-token fault-rollback=verified state-token=changed metadata-persistence=readback-verified\n';
