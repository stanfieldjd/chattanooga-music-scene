<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "media-transaction-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "media-transaction-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

$fail = static function ( $message ) {
	fwrite( STDERR, 'media-transaction-cli: ' . $message . "\n" );
	exit( 1 );
};
$error_is = static function ( $value, $code ) {
	return is_wp_error( $value ) && $value->get_error_code() === $code;
};

$media = new CMSA_Media();
$png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZMhAAAAAASUVORK5CYII=';

$control_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'CMSA media unrelated control post',
		'post_content' => 'unchanged-control',
	),
	true
);
$target_post_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => 'CMSA media featured target',
	),
	true
);
$control_attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'application/pdf',
		'post_title'     => 'CMSA media unrelated control attachment',
		'post_status'    => 'inherit',
		'post_author'    => $admin->ID,
	),
	false,
	0,
	true
);
$non_image_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'application/pdf',
		'post_title'     => 'CMSA media non image',
		'post_status'    => 'inherit',
		'post_author'    => $admin->ID,
	),
	false,
	0,
	true
);
if ( is_wp_error( $control_post_id ) || is_wp_error( $target_post_id ) || is_wp_error( $control_attachment_id ) || is_wp_error( $non_image_id ) ) {
	$fail( 'fixture creation failed.' );
}
$control_before = get_post( $control_post_id );
$control_attachment_before = get_post( $control_attachment_id );

$count_before_failures = (int) wp_count_posts( 'attachment' )->inherit;
$bad_base64 = $media->create_from_base64(
	array(
		'filename'    => 'cmsa-bad.png',
		'mime_type'   => 'image/png',
		'data_base64' => '***not-base64***',
	)
);
if ( ! $error_is( $bad_base64, 'cmsa_media_upload_base64' ) ) {
	$fail( 'invalid base64 did not fail closed.' );
}
$mismatch = $media->create_from_base64(
	array(
		'filename'    => 'cmsa-mismatch.png',
		'mime_type'   => 'image/jpeg',
		'data_base64' => $png,
	)
);
if ( ! $error_is( $mismatch, 'cmsa_media_upload_mime' ) ) {
	$fail( 'MIME mismatch did not fail closed.' );
}
$oversize = $media->create_from_base64(
	array(
		'filename'    => 'cmsa-oversize.png',
		'mime_type'   => 'image/png',
		'data_base64' => str_repeat( 'A', (int) ceil( CMSA_Media::MAX_UPLOAD_BYTES * 4 / 3 ) + 32 ),
	)
);
if ( ! $error_is( $oversize, 'cmsa_media_upload_size' ) ) {
	$fail( 'oversize encoded payload did not fail closed.' );
}
$count_after_failures = (int) wp_count_posts( 'attachment' )->inherit;
if ( $count_after_failures !== $count_before_failures ) {
	$fail( 'failed uploads created orphaned attachments.' );
}

$created_one = $media->create_from_base64(
	array(
		'filename'    => 'cmsa-media-one.png',
		'mime_type'   => 'image/png',
		'data_base64' => $png,
		'title'       => 'CMSA media one',
		'caption'     => 'Initial caption',
		'description' => '<p>Initial description</p>',
		'alt_text'    => 'Initial alt',
	)
);
if ( is_wp_error( $created_one ) || empty( $created_one['created'] ) || empty( $created_one['sha256'] ) ) {
	$fail( 'valid first PNG upload failed.' );
}
$one_id = (int) $created_one['item']['id'];
if ( 'image/png' !== $created_one['item']['mime_type'] || $created_one['item']['file_size'] < 1 || strlen( $created_one['item']['state_token'] ) !== 64 ) {
	$fail( 'first upload readback shape is invalid.' );
}
$one_file = get_attached_file( $one_id );
if ( ! is_string( $one_file ) || ! is_file( $one_file ) || hash_file( 'sha256', $one_file ) !== $created_one['sha256'] ) {
	$fail( 'first upload file/hash verification failed.' );
}

$created_two = $media->create_from_base64(
	array(
		'filename'    => 'cmsa-media-two.png',
		'mime_type'   => 'image/png',
		'data_base64' => $png,
		'title'       => 'CMSA media two',
		'alt_text'    => 'Second alt',
	)
);
if ( is_wp_error( $created_two ) || empty( $created_two['created'] ) ) {
	$fail( 'valid second PNG upload failed.' );
}
$two_id = (int) $created_two['item']['id'];

$list = $media->list_items( array( 'search' => 'CMSA media', 'per_page' => 100, 'mime_group' => 'image' ) );
if ( is_wp_error( $list ) ) {
	$fail( 'media list failed.' );
}
$list_ids = array_map( static function ( $item ) { return (int) $item['id']; }, $list['items'] );
if ( ! in_array( $one_id, $list_ids, true ) || ! in_array( $two_id, $list_ids, true ) ) {
	$fail( 'media list did not return created images.' );
}
$get_one = $media->get_item( $one_id );
if ( is_wp_error( $get_one ) || (int) $get_one['item']['id'] !== $one_id || ! array_key_exists( 'width', $get_one['item'] ) || ! array_key_exists( 'height', $get_one['item'] ) ) {
	$fail( 'media detail read failed.' );
}

$before_update = $get_one['item'];
$updated = $media->update_metadata(
	array(
		'id'                   => $one_id,
		'expected_state_token' => $before_update['state_token'],
		'title'                => 'CMSA media one updated',
		'caption'              => 'Updated caption',
		'description'          => '<p>Updated description</p>',
		'alt_text'             => 'Updated alt',
	)
);
if ( is_wp_error( $updated ) || empty( $updated['updated'] ) ) {
	$fail( 'exact-state metadata update failed.' );
}
if ( $updated['item']['title'] !== 'CMSA media one updated' || $updated['item']['caption'] !== 'Updated caption' || $updated['item']['alt_text'] !== 'Updated alt' ) {
	$fail( 'metadata update readback mismatch.' );
}
$stale = $media->update_metadata(
	array(
		'id'                   => $one_id,
		'expected_state_token' => $before_update['state_token'],
		'alt_text'             => 'stale write',
	)
);
if ( ! $error_is( $stale, 'cmsa_media_conflict' ) ) {
	$fail( 'stale metadata state was not rejected.' );
}
$no_change = $media->update_metadata(
	array(
		'id'                   => $one_id,
		'expected_state_token' => $updated['item']['state_token'],
		'alt_text'             => 'Updated alt',
	)
);
if ( ! $error_is( $no_change, 'cmsa_media_no_change' ) ) {
	$fail( 'metadata no-change mutation was not rejected.' );
}

$before_fault = $media->get_item( $one_id );
if ( is_wp_error( $before_fault ) ) {
	$fail( 'could not refresh media before rollback probe.' );
}
$fault_hook = null;
$fault_hook = static function ( $meta_id, $object_id, $meta_key, $meta_value ) use ( &$fault_hook, $one_id ) {
	if ( (int) $object_id === $one_id && '_wp_attachment_image_alt' === $meta_key && 'Fault target alt' === (string) $meta_value ) {
		remove_action( 'updated_post_meta', $fault_hook, 10 );
		update_post_meta( $one_id, '_wp_attachment_image_alt', 'injected-corruption' );
	}
};
add_action( 'updated_post_meta', $fault_hook, 10, 4 );
$fault = $media->update_metadata(
	array(
		'id'                   => $one_id,
		'expected_state_token' => $before_fault['item']['state_token'],
		'alt_text'             => 'Fault target alt',
	)
);
remove_action( 'updated_post_meta', $fault_hook, 10 );
$fault_data = is_wp_error( $fault ) ? $fault->get_error_data() : null;
if ( ! $error_is( $fault, 'cmsa_media_update_verify' ) || ! is_array( $fault_data ) || true !== $fault_data['rolled_back'] ) {
	$fail( 'metadata verification fault did not report successful rollback.' );
}
$after_fault = $media->get_item( $one_id );
if ( is_wp_error( $after_fault ) || $after_fault['item']['title'] !== $before_fault['item']['title'] || $after_fault['item']['caption'] !== $before_fault['item']['caption'] || $after_fault['item']['description'] !== $before_fault['item']['description'] || $after_fault['item']['alt_text'] !== $before_fault['item']['alt_text'] ) {
	$fail( 'metadata rollback did not restore semantic prior state.' );
}

$target_post = get_post( $target_post_id );
$set_one = $media->set_featured_image(
	array(
		'post_id'                    => $target_post_id,
		'expected_post_modified_gmt' => $target_post->post_modified_gmt,
		'expected_attachment_id'     => 0,
		'attachment_id'              => $one_id,
	)
);
if ( is_wp_error( $set_one ) || (int) get_post_thumbnail_id( $target_post_id ) !== $one_id ) {
	$fail( 'featured-image set failed.' );
}
$relationship_stale = $media->set_featured_image(
	array(
		'post_id'                    => $target_post_id,
		'expected_post_modified_gmt' => $target_post->post_modified_gmt,
		'expected_attachment_id'     => 0,
		'attachment_id'              => $two_id,
	)
);
if ( ! $error_is( $relationship_stale, 'cmsa_media_featured_relationship_conflict' ) ) {
	$fail( 'stale featured-image relationship was not rejected.' );
}
$non_image = $media->set_featured_image(
	array(
		'post_id'                    => $target_post_id,
		'expected_post_modified_gmt' => $target_post->post_modified_gmt,
		'expected_attachment_id'     => $one_id,
		'attachment_id'              => $non_image_id,
	)
);
if ( ! $error_is( $non_image, 'cmsa_media_featured_type' ) ) {
	$fail( 'non-image featured attachment was not rejected.' );
}
$clear = $media->set_featured_image(
	array(
		'post_id'                    => $target_post_id,
		'expected_post_modified_gmt' => $target_post->post_modified_gmt,
		'expected_attachment_id'     => $one_id,
		'attachment_id'              => 0,
	)
);
if ( is_wp_error( $clear ) || 0 !== (int) get_post_thumbnail_id( $target_post_id ) ) {
	$fail( 'featured-image clear failed.' );
}

$thumbnail_fault = null;
$thumbnail_fault = static function ( $check, $object_id, $meta_key, $meta_value, $prev_value ) use ( &$thumbnail_fault, $target_post_id ) {
	if ( (int) $object_id === $target_post_id && '_thumbnail_id' === $meta_key ) {
		remove_filter( 'update_post_metadata', $thumbnail_fault, 10 );
		return true;
	}
	return $check;
};
add_filter( 'update_post_metadata', $thumbnail_fault, 10, 5 );
$fault_featured = $media->set_featured_image(
	array(
		'post_id'                    => $target_post_id,
		'expected_post_modified_gmt' => $target_post->post_modified_gmt,
		'expected_attachment_id'     => 0,
		'attachment_id'              => $two_id,
	)
);
remove_filter( 'update_post_metadata', $thumbnail_fault, 10 );
$featured_fault_data = is_wp_error( $fault_featured ) ? $fault_featured->get_error_data() : null;
if ( ! $error_is( $fault_featured, 'cmsa_media_featured_verify' ) || ! is_array( $featured_fault_data ) || true !== $featured_fault_data['rolled_back'] || 0 !== (int) get_post_thumbnail_id( $target_post_id ) ) {
	$fail( 'featured-image verification fault did not preserve/restore prior relationship.' );
}

$control_after = get_post( $control_post_id );
$control_attachment_after = get_post( $control_attachment_id );
if ( ! $control_after instanceof WP_Post || ! $control_attachment_after instanceof WP_Post || $control_after->post_title !== $control_before->post_title || $control_after->post_content !== $control_before->post_content || $control_attachment_after->post_title !== $control_attachment_before->post_title || 0 !== (int) get_post_thumbnail_id( $control_post_id ) ) {
	$fail( 'unrelated control objects changed.' );
}

wp_delete_attachment( $one_id, true );
wp_delete_attachment( $two_id, true );
wp_delete_attachment( $control_attachment_id, true );
wp_delete_attachment( $non_image_id, true );
wp_delete_post( $target_post_id, true );
wp_delete_post( $control_post_id, true );

echo "media-transaction-cli: PASS upload validation metadata conflict rollback featured-image conflict rollback isolation\n";
