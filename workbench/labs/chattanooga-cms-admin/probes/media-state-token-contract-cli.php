<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "media-state-token-contract-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$fail = static function ( $message ) {
	fwrite( STDERR, 'media-state-token-contract-cli: ' . $message . "\n" );
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

$temp = wp_tempnam( 'cmsa-state-token.png' );
if ( ! $temp ) {
	$fail( 'could not allocate image fixture.' );
}
$image = imagecreatetruecolor( 512, 512 );
if ( ! $image ) {
	@unlink( $temp );
	$fail( 'could not create image fixture.' );
}
$background = imagecolorallocate( $image, 41, 112, 168 );
imagefilledrectangle( $image, 0, 0, 511, 511, $background );
if ( ! imagepng( $image, $temp, 9 ) ) {
	imagedestroy( $image );
	@unlink( $temp );
	$fail( 'could not write image fixture.' );
}
imagedestroy( $image );
$fixture_bytes = file_get_contents( $temp );
@unlink( $temp );
if ( false === $fixture_bytes || '' === $fixture_bytes ) {
	$fail( 'could not read image fixture.' );
}

$media = new CMSA_Media();
$lifecycle = new CMSA_Media_Lifecycle( $media );
$created = $media->create_from_base64(
	array(
		'filename'    => 'cmsa-state-token.png',
		'mime_type'   => 'image/png',
		'data_base64' => base64_encode( $fixture_bytes ),
		'title'       => 'CMSA state token fixture',
	)
);
if ( is_wp_error( $created ) || empty( $created['item']['id'] ) ) {
	$fail( 'attachment fixture creation failed.' );
}
$attachment_id = (int) $created['item']['id'];
$file = get_attached_file( $attachment_id );
if ( ! is_string( $file ) || ! is_file( $file ) ) {
	$fail( 'attachment primary file is unavailable.' );
}

$before = $media->get_item( $attachment_id );
$before_lifecycle = $lifecycle->get_state( $attachment_id, is_wp_error( $before ) ? array() : $before['item'] );
if ( is_wp_error( $before ) || is_wp_error( $before_lifecycle ) || empty( $before_lifecycle['lifecycle_complete'] ) ) {
	$fail( 'initial media lifecycle read failed or was incomplete.' );
}
$before_token = (string) $before['item']['state_token'];
$before_lifecycle_token = (string) $before_lifecycle['lifecycle_state_token'];
$before_bytes = file_get_contents( $file );
$before_hash = hash_file( 'sha256', $file );
$before_size = filesize( $file );
$before_mtime = filemtime( $file );
$before_metadata = wp_get_attachment_metadata( $attachment_id );
$before_backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
if ( false === $before_bytes || ! is_string( $before_hash ) || false === $before_size || false === $before_mtime || ! is_array( $before_metadata ) ) {
	$fail( 'could not snapshot initial media state.' );
}

$mutated_bytes = $before_bytes;
$index = max( 0, strlen( $mutated_bytes ) - 5 );
$mutated_bytes[ $index ] = chr( ord( $mutated_bytes[ $index ] ) ^ 1 );
if ( false === file_put_contents( $file, $mutated_bytes, LOCK_EX ) ) {
	$fail( 'could not write same-size file mutation.' );
}
@touch( $file, (int) $before_mtime );
clearstatcache( true, $file );
$mutated_hash = hash_file( 'sha256', $file );
if ( ! is_string( $mutated_hash ) || hash_equals( $before_hash, $mutated_hash ) || (int) filesize( $file ) !== (int) $before_size || (int) filemtime( $file ) !== (int) $before_mtime ) {
	$fail( 'same-size/same-mtime file mutation fixture was not established.' );
}
$after_file_mutation = $media->get_item( $attachment_id );
$after_file_lifecycle = $lifecycle->get_state( $attachment_id, is_wp_error( $after_file_mutation ) ? array() : $after_file_mutation['item'] );
if ( is_wp_error( $after_file_mutation ) || is_wp_error( $after_file_lifecycle ) ) {
	$fail( 'media read after file mutation failed.' );
}
$file_blind = hash_equals( $before_token, (string) $after_file_mutation['item']['state_token'] );
$file_detected = ! hash_equals( $before_lifecycle_token, (string) $after_file_lifecycle['lifecycle_state_token'] );
if ( ! $file_blind || ! $file_detected ) {
	$fail( 'generic/lifecycle token file-mutation boundary did not match the expected contract.' );
}

if ( false === file_put_contents( $file, $before_bytes, LOCK_EX ) ) {
	$fail( 'could not restore original primary bytes.' );
}
@touch( $file, (int) $before_mtime );
clearstatcache( true, $file );
$after_file_restore = $media->get_item( $attachment_id );
$after_file_restore_lifecycle = $lifecycle->get_state( $attachment_id, is_wp_error( $after_file_restore ) ? array() : $after_file_restore['item'] );
if ( ! hash_equals( $before_hash, (string) hash_file( 'sha256', $file ) ) || is_wp_error( $after_file_restore ) || is_wp_error( $after_file_restore_lifecycle ) || ! hash_equals( $before_token, (string) $after_file_restore['item']['state_token'] ) || ! hash_equals( $before_lifecycle_token, (string) $after_file_restore_lifecycle['lifecycle_state_token'] ) ) {
	$fail( 'primary file restoration did not restore both initial tokens.' );
}

$metadata_mutated = $before_metadata;
$metadata_mutated['cmsa_state_token_probe'] = 'changed';
wp_update_attachment_metadata( $attachment_id, $metadata_mutated );
$metadata_readback = wp_get_attachment_metadata( $attachment_id );
if ( ! is_array( $metadata_readback ) || ! isset( $metadata_readback['cmsa_state_token_probe'] ) ) {
	$fail( 'attachment metadata mutation fixture was not established.' );
}
$after_metadata_mutation = $media->get_item( $attachment_id );
$after_metadata_lifecycle = $lifecycle->get_state( $attachment_id, is_wp_error( $after_metadata_mutation ) ? array() : $after_metadata_mutation['item'] );
if ( is_wp_error( $after_metadata_mutation ) || is_wp_error( $after_metadata_lifecycle ) ) {
	$fail( 'media read after attachment metadata mutation failed.' );
}
$metadata_blind = hash_equals( $before_token, (string) $after_metadata_mutation['item']['state_token'] );
$metadata_detected = ! hash_equals( $before_lifecycle_token, (string) $after_metadata_lifecycle['lifecycle_state_token'] );
if ( ! $metadata_blind || ! $metadata_detected ) {
	$fail( 'generic/lifecycle token attachment-metadata boundary did not match the expected contract.' );
}

wp_update_attachment_metadata( $attachment_id, $before_metadata );
$metadata_restored = wp_get_attachment_metadata( $attachment_id );
$after_metadata_restore = $media->get_item( $attachment_id );
$after_metadata_restore_lifecycle = $lifecycle->get_state( $attachment_id, is_wp_error( $after_metadata_restore ) ? array() : $after_metadata_restore['item'] );
if ( $metadata_restored !== $before_metadata || is_wp_error( $after_metadata_restore ) || is_wp_error( $after_metadata_restore_lifecycle ) || ! hash_equals( $before_token, (string) $after_metadata_restore['item']['state_token'] ) || ! hash_equals( $before_lifecycle_token, (string) $after_metadata_restore_lifecycle['lifecycle_state_token'] ) ) {
	$fail( 'attachment metadata restoration did not restore both initial tokens.' );
}

$derivative_path = '';
if ( isset( $before_metadata['sizes'] ) && is_array( $before_metadata['sizes'] ) ) {
	foreach ( $before_metadata['sizes'] as $size ) {
		if ( is_array( $size ) && ! empty( $size['file'] ) ) {
			$candidate = path_join( dirname( $file ), basename( $size['file'] ) );
			if ( is_file( $candidate ) ) {
				$derivative_path = $candidate;
				break;
			}
		}
}
}
if ( '' === $derivative_path ) {
	$fail( 'image fixture did not generate a derivative for lifecycle hashing.' );
}
$derivative_bytes = file_get_contents( $derivative_path );
$derivative_hash = hash_file( 'sha256', $derivative_path );
$derivative_size = filesize( $derivative_path );
$derivative_mtime = filemtime( $derivative_path );
if ( false === $derivative_bytes || ! is_string( $derivative_hash ) || false === $derivative_size || false === $derivative_mtime ) {
	$fail( 'could not snapshot derivative file.' );
}
$derivative_mutated = $derivative_bytes;
$derivative_index = max( 0, strlen( $derivative_mutated ) - 5 );
$derivative_mutated[ $derivative_index ] = chr( ord( $derivative_mutated[ $derivative_index ] ) ^ 1 );
if ( false === file_put_contents( $derivative_path, $derivative_mutated, LOCK_EX ) ) {
	$fail( 'could not mutate derivative file.' );
}
@touch( $derivative_path, (int) $derivative_mtime );
clearstatcache( true, $derivative_path );
$after_derivative_generic = $media->get_item( $attachment_id );
$after_derivative_lifecycle = $lifecycle->get_state( $attachment_id, is_wp_error( $after_derivative_generic ) ? array() : $after_derivative_generic['item'] );
$derivative_blind = ! is_wp_error( $after_derivative_generic ) && hash_equals( $before_token, (string) $after_derivative_generic['item']['state_token'] );
$derivative_detected = ! is_wp_error( $after_derivative_lifecycle ) && ! hash_equals( $before_lifecycle_token, (string) $after_derivative_lifecycle['lifecycle_state_token'] );
if ( ! $derivative_blind || ! $derivative_detected ) {
	$fail( 'generic/lifecycle token derivative-file boundary did not match the expected contract.' );
}
if ( false === file_put_contents( $derivative_path, $derivative_bytes, LOCK_EX ) ) {
	$fail( 'could not restore derivative file.' );
}
@touch( $derivative_path, (int) $derivative_mtime );
clearstatcache( true, $derivative_path );
$after_derivative_restore = $media->get_item( $attachment_id );
$after_derivative_restore_lifecycle = $lifecycle->get_state( $attachment_id, is_wp_error( $after_derivative_restore ) ? array() : $after_derivative_restore['item'] );
if ( ! hash_equals( $derivative_hash, (string) hash_file( 'sha256', $derivative_path ) ) || is_wp_error( $after_derivative_restore ) || is_wp_error( $after_derivative_restore_lifecycle ) || ! hash_equals( $before_lifecycle_token, (string) $after_derivative_restore_lifecycle['lifecycle_state_token'] ) ) {
	$fail( 'derivative restoration did not restore the initial lifecycle token.' );
}

$backup_probe = array(
	'cmsa-probe' => array(
		'file'      => basename( $derivative_path ),
		'width'     => 150,
		'height'    => 150,
		'mime-type' => 'image/png',
	)
);
update_post_meta( $attachment_id, '_wp_attachment_backup_sizes', $backup_probe );
$backup_readback = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
if ( $backup_readback !== $backup_probe ) {
	$fail( 'backup-size metadata mutation fixture was not established.' );
}
$after_backup_generic = $media->get_item( $attachment_id );
$after_backup_lifecycle = $lifecycle->get_state( $attachment_id, is_wp_error( $after_backup_generic ) ? array() : $after_backup_generic['item'] );
$backup_blind = ! is_wp_error( $after_backup_generic ) && hash_equals( $before_token, (string) $after_backup_generic['item']['state_token'] );
$backup_detected = ! is_wp_error( $after_backup_lifecycle ) && ! hash_equals( $before_lifecycle_token, (string) $after_backup_lifecycle['lifecycle_state_token'] );
if ( ! $backup_blind || ! $backup_detected || empty( $after_backup_lifecycle['lifecycle_complete'] ) ) {
	$fail( 'generic/lifecycle token backup-metadata boundary did not match the expected contract.' );
}
if ( empty( $before_backup_sizes ) ) {
	delete_post_meta( $attachment_id, '_wp_attachment_backup_sizes' );
} else {
	update_post_meta( $attachment_id, '_wp_attachment_backup_sizes', $before_backup_sizes );
}
$after_backup_restore = $media->get_item( $attachment_id );
$after_backup_restore_lifecycle = $lifecycle->get_state( $attachment_id, is_wp_error( $after_backup_restore ) ? array() : $after_backup_restore['item'] );
if ( get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true ) !== $before_backup_sizes || is_wp_error( $after_backup_restore ) || is_wp_error( $after_backup_restore_lifecycle ) || ! hash_equals( $before_lifecycle_token, (string) $after_backup_restore_lifecycle['lifecycle_state_token'] ) ) {
	$fail( 'backup-size metadata restoration did not restore the initial lifecycle token.' );
}

if ( 2 > (int) $before_lifecycle['lifecycle_file_count'] ) {
	$fail( 'lifecycle state did not enumerate the derivative file set.' );
}

wp_delete_attachment( $attachment_id, true );

echo 'media-state-token-contract-cli: PASS generic-primary-blind=' . ( $file_blind ? 'true' : 'false' ) . ' lifecycle-primary-detects=' . ( $file_detected ? 'true' : 'false' ) . ' generic-metadata-blind=' . ( $metadata_blind ? 'true' : 'false' ) . ' lifecycle-metadata-detects=' . ( $metadata_detected ? 'true' : 'false' ) . ' generic-derivative-blind=' . ( $derivative_blind ? 'true' : 'false' ) . ' lifecycle-derivative-detects=' . ( $derivative_detected ? 'true' : 'false' ) . ' generic-backup-blind=' . ( $backup_blind ? 'true' : 'false' ) . ' lifecycle-backup-detects=' . ( $backup_detected ? 'true' : 'false' ) . ' restoration=verified lifecycle-files=' . (int) $before_lifecycle['lifecycle_file_count'] . "\n";
