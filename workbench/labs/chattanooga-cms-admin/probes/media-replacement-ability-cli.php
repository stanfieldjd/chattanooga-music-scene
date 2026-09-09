<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "media-replacement-ability-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$fail = static function ( $message ) {
	fwrite( STDERR, 'media-replacement-ability-cli: ' . $message . "\n" );
	exit( 1 );
};
$error_is = static function ( $value, $code ) {
	return is_wp_error( $value ) && $value->get_error_code() === $code;
};

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	$fail( 'administrator fixture missing.' );
}
wp_set_current_user( $admin->ID );

foreach ( array( 'imagecreatetruecolor', 'imagepng', 'imagejpeg', 'imagedestroy' ) as $function ) {
	if ( ! function_exists( $function ) ) {
		$fail( 'GD image fixture support is unavailable.' );
	}
}

$make_image = static function ( $basename, $width, $height, $red, $green, $blue, $format = 'png' ) use ( $fail ) {
	$temp = wp_tempnam( $basename );
	if ( ! $temp ) {
		$fail( 'could not allocate image fixture.' );
	}
	$image = imagecreatetruecolor( $width, $height );
	if ( ! $image ) {
		@unlink( $temp );
		$fail( 'could not create image fixture.' );
	}
	$background = imagecolorallocate( $image, $red, $green, $blue );
	imagefilledrectangle( $image, 0, 0, $width - 1, $height - 1, $background );
	$written = 'jpeg' === $format ? imagejpeg( $image, $temp, 90 ) : imagepng( $image, $temp, 9 );
	imagedestroy( $image );
	if ( ! $written ) {
		@unlink( $temp );
		$fail( 'could not write image fixture.' );
	}
	return $temp;
};

$read_bytes = static function ( $path ) use ( $fail ) {
	$bytes = file_get_contents( $path );
	if ( false === $bytes || '' === $bytes ) {
		$fail( 'could not read fixture bytes: ' . basename( $path ) );
	}
	return $bytes;
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

$prefix_files = static function ( $primary ) {
	$directory = dirname( $primary );
	$extension = pathinfo( $primary, PATHINFO_EXTENSION );
	$stem = pathinfo( $primary, PATHINFO_FILENAME );
	$pattern = path_join( $directory, $stem . '-*' . ( '' !== $extension ? '.' . $extension : '' ) );
	$files = glob( $pattern );
	$files = is_array( $files ) ? array_values( array_filter( $files, 'is_file' ) ) : array();
	sort( $files, SORT_STRING );
	return $files;
};

$create_fixture = static function ( $name, $target_title ) use ( $make_image, $fail ) {
	$target_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $target_title,
			'post_content' => 'initial',
		),
		true
	);
	if ( is_wp_error( $target_id ) || ! $target_id ) {
		$fail( 'target post fixture creation failed.' );
	}
	$temp = $make_image( $name, 512, 512, 31, 101, 171, 'png' );
	$attachment_id = media_handle_sideload(
		array(
			'name'     => $name,
			'tmp_name' => $temp,
		),
		(int) $target_id,
		$target_title . ' attachment',
		array( 'post_title' => $target_title . ' attachment' )
	);
	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		if ( is_file( $temp ) ) {
			@unlink( $temp );
		}
		$fail( 'attachment fixture creation failed.' );
	}
	$attachment_id = (int) $attachment_id;
	$primary = get_attached_file( $attachment_id );
	$url = wp_get_attachment_url( $attachment_id );
	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_string( $primary ) || ! is_file( $primary ) || ! is_string( $url ) || '' === $url || ! is_array( $metadata ) ) {
		$fail( 'attachment fixture state is incomplete.' );
	}
	if ( metadata_exists( 'post', $attachment_id, '_wp_attachment_backup_sizes' ) ) {
		$fail( 'ordinary fixture unexpectedly contains backup-size metadata.' );
	}
	foreach ( array( 'original_image', 'source_image', 'animated_video', 'animated_video_poster' ) as $key ) {
		if ( ! empty( $metadata[ $key ] ) ) {
			$fail( 'ordinary fixture unexpectedly contains companion-file metadata.' );
		}
	}
	if ( ! set_post_thumbnail( $target_id, $attachment_id ) || (int) get_post_thumbnail_id( $target_id ) !== $attachment_id ) {
		$fail( 'featured-image fixture failed.' );
	}
	$content = '<p>Persistent media reference:</p><img src="' . esc_url( $url ) . '" alt="fixture" />';
	$updated = wp_update_post( array( 'ID' => $target_id, 'post_content' => $content ), true );
	if ( is_wp_error( $updated ) || ! $updated ) {
		$fail( 'content URL reference fixture failed.' );
	}
	return array(
		'target_id'     => (int) $target_id,
		'attachment_id' => $attachment_id,
		'primary'       => $primary,
		'url'           => $url,
		'metadata'      => $metadata,
		'content'       => $content,
		'parent_id'     => (int) $target_id,
		'mime_type'     => 'image/png',
	);
};

$media = new CMSA_Media();
$lifecycle = new CMSA_Media_Lifecycle( $media );

$control_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'CMSA replacement unrelated control',
		'post_content' => 'unchanged-control',
	),
	true
);
if ( is_wp_error( $control_id ) || ! $control_id ) {
	$fail( 'control post creation failed.' );
}
$control_before = get_post( $control_id );

$fixture = $create_fixture( 'cmsa-guarded-replacement.png', 'CMSA guarded replacement target' );
$attachment_id = $fixture['attachment_id'];
$original_primary_bytes = $read_bytes( $fixture['primary'] );
$original_primary_hash = hash_file( 'sha256', $fixture['primary'] );
$original_attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
$state_before = $lifecycle->get_state( $attachment_id );
if ( is_wp_error( $state_before ) || empty( $state_before['lifecycle_complete'] ) ) {
	$fail( 'initial lifecycle state is unavailable or incomplete.' );
}
$token_before = (string) $state_before['lifecycle_state_token'];
$owned_before = $owned_paths( $fixture['primary'], $fixture['metadata'] );
if ( count( $owned_before ) < 2 ) {
	$fail( 'ordinary image fixture did not generate derivatives.' );
}

$replacement_temp = $make_image( 'cmsa-guarded-replacement.png', 640, 360, 181, 62, 44, 'png' );
$replacement_bytes = $read_bytes( $replacement_temp );
$replacement_hash = hash_file( 'sha256', $replacement_temp );
if ( ! is_string( $replacement_hash ) || ! is_string( $original_primary_hash ) || hash_equals( $original_primary_hash, $replacement_hash ) ) {
	$fail( 'replacement payload is not distinct from the original.' );
}

$stale = $lifecycle->replace_from_base64(
	array(
		'id'                             => $attachment_id,
		'expected_lifecycle_state_token' => str_repeat( '0', 64 ),
		'mime_type'                      => 'image/png',
		'data_base64'                    => base64_encode( $replacement_bytes ),
	)
);
if ( ! $error_is( $stale, 'cmsa_media_replace_conflict' ) || ! hash_equals( $original_primary_hash, (string) hash_file( 'sha256', $fixture['primary'] ) ) ) {
	$fail( 'stale replacement was not rejected before mutation.' );
}

$no_change = $lifecycle->replace_from_base64(
	array(
		'id'                             => $attachment_id,
		'expected_lifecycle_state_token' => $token_before,
		'mime_type'                      => 'image/png',
		'data_base64'                    => base64_encode( $original_primary_bytes ),
	)
);
if ( ! $error_is( $no_change, 'cmsa_media_replace_no_change' ) ) {
	$fail( 'no-change replacement was not rejected.' );
}
$state_after_no_change = $lifecycle->get_state( $attachment_id );
if ( is_wp_error( $state_after_no_change ) || ! hash_equals( $token_before, (string) $state_after_no_change['lifecycle_state_token'] ) ) {
	$fail( 'no-change rejection altered lifecycle state.' );
}

$jpeg_temp = $make_image( 'cmsa-replacement-type.jpg', 640, 360, 79, 142, 91, 'jpeg' );
$jpeg_bytes = $read_bytes( $jpeg_temp );
$type_mismatch = $lifecycle->replace_from_base64(
	array(
		'id'                             => $attachment_id,
		'expected_lifecycle_state_token' => $token_before,
		'mime_type'                      => 'image/jpeg',
		'data_base64'                    => base64_encode( $jpeg_bytes ),
	)
);
@unlink( $jpeg_temp );
if ( ! $error_is( $type_mismatch, 'cmsa_media_replace_type' ) ) {
	$fail( 'cross-MIME replacement was not rejected.' );
}

$derivative_path = '';
foreach ( $owned_before as $path ) {
	if ( $path !== $fixture['primary'] && is_file( $path ) ) {
		$derivative_path = $path;
		break;
	}
}
if ( '' === $derivative_path ) {
	$fail( 'no derivative is available for incomplete-state verification.' );
}
$derivative_bytes = $read_bytes( $derivative_path );
$derivative_mtime = filemtime( $derivative_path );
if ( false === $derivative_mtime ) {
	$fail( 'could not snapshot derivative mtime.' );
}
wp_delete_file( $derivative_path );
if ( is_file( $derivative_path ) ) {
	$fail( 'could not establish a missing-derivative fixture.' );
}
$incomplete_state = $lifecycle->get_state( $attachment_id );
if ( is_wp_error( $incomplete_state ) || ! empty( $incomplete_state['lifecycle_complete'] ) ) {
	$fail( 'missing derivative did not mark lifecycle state incomplete.' );
}
$incomplete = $lifecycle->replace_from_base64(
	array(
		'id'                             => $attachment_id,
		'expected_lifecycle_state_token' => $incomplete_state['lifecycle_state_token'],
		'mime_type'                      => 'image/png',
		'data_base64'                    => base64_encode( $replacement_bytes ),
	)
);
if ( ! $error_is( $incomplete, 'cmsa_media_replace_incomplete' ) ) {
	$fail( 'incomplete lifecycle state was not rejected.' );
}
if ( strlen( $derivative_bytes ) !== file_put_contents( $derivative_path, $derivative_bytes, LOCK_EX ) ) {
	$fail( 'could not restore missing derivative fixture.' );
}
@touch( $derivative_path, (int) $derivative_mtime );
clearstatcache( true, $derivative_path );
$state_after_derivative_restore = $lifecycle->get_state( $attachment_id );
if ( is_wp_error( $state_after_derivative_restore ) || ! hash_equals( $token_before, (string) $state_after_derivative_restore['lifecycle_state_token'] ) ) {
	$fail( 'missing-derivative restoration did not restore exact lifecycle state.' );
}

$login = 'cmsa_replace_' . strtolower( wp_generate_password( 8, false, false ) );
$limited_id = wp_create_user( $login, wp_generate_password( 20, true, true ), $login . '@example.invalid' );
if ( is_wp_error( $limited_id ) ) {
	$fail( 'limited user fixture creation failed.' );
}
$limited = new WP_User( $limited_id );
$limited->set_role( 'subscriber' );
$limited->add_cap( 'upload_files', true );
$limited->add_cap( 'edit_posts', true );
clean_user_cache( $limited_id );
wp_set_current_user( 0 );
wp_set_current_user( $limited_id );
$other_owner = $lifecycle->replace_from_base64(
	array(
		'id'                             => $attachment_id,
		'expected_lifecycle_state_token' => $token_before,
		'mime_type'                      => 'image/png',
		'data_base64'                    => base64_encode( $replacement_bytes ),
	)
);
if ( ! $error_is( $other_owner, 'cmsa_media_lifecycle_permission' ) ) {
	$fail( 'object-scoped replacement permission did not deny another owner.' );
}
wp_set_current_user( $admin->ID );

$backup_probe = array(
	'cmsa-probe' => array(
		'file'      => basename( $derivative_path ),
		'width'     => 150,
		'height'    => 150,
		'mime-type' => 'image/png',
	),
);
update_post_meta( $attachment_id, '_wp_attachment_backup_sizes', $backup_probe );
$complex_state = $lifecycle->get_state( $attachment_id );
if ( is_wp_error( $complex_state ) || empty( $complex_state['lifecycle_complete'] ) || hash_equals( $token_before, (string) $complex_state['lifecycle_state_token'] ) ) {
	$fail( 'backup-size metadata did not enter exact lifecycle state.' );
}
$complex = $lifecycle->replace_from_base64(
	array(
		'id'                             => $attachment_id,
		'expected_lifecycle_state_token' => $complex_state['lifecycle_state_token'],
		'mime_type'                      => 'image/png',
		'data_base64'                    => base64_encode( $replacement_bytes ),
	)
);
if ( ! $error_is( $complex, 'cmsa_media_replace_complex' ) ) {
	$fail( 'backup-size attachment state did not fail closed.' );
}
delete_post_meta( $attachment_id, '_wp_attachment_backup_sizes' );
$state_after_complex_restore = $lifecycle->get_state( $attachment_id );
if ( is_wp_error( $state_after_complex_restore ) || ! hash_equals( $token_before, (string) $state_after_complex_restore['lifecycle_state_token'] ) ) {
	$fail( 'complex-state rejection changed the ordinary attachment state.' );
}

$success = $lifecycle->replace_from_base64(
	array(
		'id'                             => $attachment_id,
		'expected_lifecycle_state_token' => $token_before,
		'mime_type'                      => 'image/png',
		'data_base64'                    => base64_encode( $replacement_bytes ),
	)
);
if ( is_wp_error( $success ) || empty( $success['replaced'] ) || empty( $success['item']['lifecycle_state_token'] ) || empty( $success['item']['lifecycle_complete'] ) ) {
	$fail( 'guarded ordinary-image replacement failed.' );
}
$metadata_after = wp_get_attachment_metadata( $attachment_id );
$owned_after = $owned_paths( $fixture['primary'], $metadata_after );
$old_only = array_values( array_diff( $owned_before, $owned_after ) );
if ( empty( $old_only ) ) {
	$fail( 'replacement fixture did not produce obsolete derivatives to verify.' );
}
foreach ( $old_only as $path ) {
	if ( is_file( $path ) ) {
		$fail( 'successful replacement left an obsolete derivative behind.' );
	}
}
$target_after = get_post( $fixture['target_id'] );
$attachment_after = get_post( $attachment_id );
if ( ! is_array( $metadata_after ) || 640 !== (int) $metadata_after['width'] || 360 !== (int) $metadata_after['height'] ) {
	$fail( 'replacement dimensions were not persisted.' );
}
if ( ! is_string( $success['sha256'] ) || ! hash_equals( $replacement_hash, $success['sha256'] ) || ! hash_equals( $replacement_hash, (string) hash_file( 'sha256', $fixture['primary'] ) ) ) {
	$fail( 'replacement primary hash verification failed.' );
}
if ( ! $attachment_after instanceof WP_Post || (int) $attachment_after->ID !== $attachment_id || (string) wp_get_attachment_url( $attachment_id ) !== $fixture['url'] || (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) !== $original_attached_file || (int) $attachment_after->post_parent !== $fixture['parent_id'] || (string) $attachment_after->post_mime_type !== $fixture['mime_type'] ) {
	$fail( 'replacement changed attachment identity, URL/path, parent, or MIME.' );
}
if ( (int) get_post_thumbnail_id( $fixture['target_id'] ) !== $attachment_id || ! $target_after instanceof WP_Post || (string) $target_after->post_content !== $fixture['content'] ) {
	$fail( 'replacement changed a protected featured-image or content URL reference.' );
}
if ( hash_equals( $token_before, (string) $success['item']['lifecycle_state_token'] ) ) {
	$fail( 'replacement did not advance lifecycle state.' );
}

$control_after_success = get_post( $control_id );
if ( ! $control_before instanceof WP_Post || ! $control_after_success instanceof WP_Post || (string) $control_after_success->post_title !== (string) $control_before->post_title || (string) $control_after_success->post_content !== (string) $control_before->post_content || (string) $control_after_success->post_status !== (string) $control_before->post_status ) {
	$fail( 'replacement changed the unrelated control post.' );
}

$fault_fixture = $create_fixture( 'cmsa-rollback-replacement.png', 'CMSA rollback replacement target' );
$fault_id = $fault_fixture['attachment_id'];
$fault_state_before = $lifecycle->get_state( $fault_id );
if ( is_wp_error( $fault_state_before ) || empty( $fault_state_before['lifecycle_complete'] ) ) {
	$fail( 'fault fixture lifecycle state is unavailable.' );
}
$fault_token_before = (string) $fault_state_before['lifecycle_state_token'];
$fault_hash_before = hash_file( 'sha256', $fault_fixture['primary'] );
$fault_meta_before = wp_get_attachment_metadata( $fault_id );
$fault_files_before = $prefix_files( $fault_fixture['primary'] );
$blocked_old_path = '';
if ( isset( $fault_meta_before['sizes']['medium']['file'] ) ) {
	$blocked_old_path = path_join( dirname( $fault_fixture['primary'] ), basename( $fault_meta_before['sizes']['medium']['file'] ) );
}
if ( '' === $blocked_old_path || ! is_file( $blocked_old_path ) ) {
	foreach ( $fault_meta_before['sizes'] as $size_name => $size ) {
		if ( 'thumbnail' !== $size_name && is_array( $size ) && ! empty( $size['file'] ) ) {
			$candidate = path_join( dirname( $fault_fixture['primary'] ), basename( $size['file'] ) );
			if ( is_file( $candidate ) ) {
				$blocked_old_path = $candidate;
				break;
			}
		}
	}
}
if ( '' === $blocked_old_path ) {
	$fail( 'fault fixture lacks a non-thumbnail derivative for cleanup fault injection.' );
}
$block_delete = static function ( $path ) use ( $blocked_old_path ) {
	return (string) $path === (string) $blocked_old_path ? '' : $path;
};
add_filter( 'wp_delete_file', $block_delete );
$fault = $lifecycle->replace_from_base64(
	array(
		'id'                             => $fault_id,
		'expected_lifecycle_state_token' => $fault_token_before,
		'mime_type'                      => 'image/png',
		'data_base64'                    => base64_encode( $replacement_bytes ),
	)
);
remove_filter( 'wp_delete_file', $block_delete );
$fault_data = is_wp_error( $fault ) ? $fault->get_error_data() : null;
if ( ! $error_is( $fault, 'cmsa_media_replace_cleanup' ) || ! is_array( $fault_data ) || empty( $fault_data['rolled_back'] ) ) {
	$fail( 'cleanup verification fault did not produce a verified rollback.' );
}
$fault_state_after = $lifecycle->get_state( $fault_id );
$fault_target_after = get_post( $fault_fixture['target_id'] );
$fault_files_after = $prefix_files( $fault_fixture['primary'] );
if ( is_wp_error( $fault_state_after ) || ! hash_equals( $fault_token_before, (string) $fault_state_after['lifecycle_state_token'] ) || ! is_string( $fault_hash_before ) || ! hash_equals( $fault_hash_before, (string) hash_file( 'sha256', $fault_fixture['primary'] ) ) || wp_get_attachment_metadata( $fault_id ) !== $fault_meta_before ) {
	$fail( 'fault rollback did not restore exact file/metadata/lifecycle state.' );
}
if ( $fault_files_after !== $fault_files_before ) {
	$fail( 'fault rollback left new-only derivative residue or lost original derivatives.' );
}
if ( (int) get_post_thumbnail_id( $fault_fixture['target_id'] ) !== $fault_id || ! $fault_target_after instanceof WP_Post || (string) $fault_target_after->post_content !== $fault_fixture['content'] || (string) wp_get_attachment_url( $fault_id ) !== $fault_fixture['url'] ) {
	$fail( 'fault rollback changed a protected reference or URL.' );
}

$control_after_fault = get_post( $control_id );
if ( ! $control_after_fault instanceof WP_Post || (string) $control_after_fault->post_title !== (string) $control_before->post_title || (string) $control_after_fault->post_content !== (string) $control_before->post_content || (string) $control_after_fault->post_status !== (string) $control_before->post_status ) {
	$fail( 'fault transaction changed the unrelated control post.' );
}

@unlink( $replacement_temp );
wp_delete_attachment( $attachment_id, true );
wp_delete_post( $fixture['target_id'], true );
wp_delete_attachment( $fault_id, true );
wp_delete_post( $fault_fixture['target_id'], true );
wp_delete_post( $control_id, true );
wp_delete_user( $limited_id );

echo 'media-replacement-ability-cli: PASS stale=true no-change=true same-mime=true incomplete=true object-scope=true complex-fail-closed=true success=same-id-path-url-parent-mime-references obsolete-derivatives=' . count( $old_only ) . ' rollback=cleanup-fault-exact lifecycle-token=guarded registry-target=98\n';
