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

$media = new CMSA_Media();
$png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZMhAAAAAASUVORK5CYII=';
$created = $media->create_from_base64(
	array(
		'filename'    => 'cmsa-state-token.png',
		'mime_type'   => 'image/png',
		'data_base64' => $png,
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
if ( is_wp_error( $before ) ) {
	$fail( 'initial media read failed.' );
}
$before_token = (string) $before['item']['state_token'];
$before_bytes = file_get_contents( $file );
$before_hash = hash_file( 'sha256', $file );
$before_size = filesize( $file );
$before_mtime = filemtime( $file );
$before_metadata = wp_get_attachment_metadata( $attachment_id );
if ( false === $before_bytes || ! is_string( $before_hash ) || false === $before_size || false === $before_mtime ) {
	$fail( 'could not snapshot primary file state.' );
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
if ( is_wp_error( $after_file_mutation ) ) {
	$fail( 'media read after file mutation failed.' );
}
$file_blind = hash_equals( $before_token, (string) $after_file_mutation['item']['state_token'] );
if ( ! $file_blind ) {
	$fail( 'current state token unexpectedly detected the controlled same-size/same-mtime file mutation.' );
}

if ( false === file_put_contents( $file, $before_bytes, LOCK_EX ) ) {
	$fail( 'could not restore original primary bytes.' );
}
@touch( $file, (int) $before_mtime );
clearstatcache( true, $file );
if ( ! hash_equals( $before_hash, (string) hash_file( 'sha256', $file ) ) ) {
	$fail( 'primary file restoration hash mismatch.' );
}
$after_file_restore = $media->get_item( $attachment_id );
if ( is_wp_error( $after_file_restore ) || ! hash_equals( $before_token, (string) $after_file_restore['item']['state_token'] ) ) {
	$fail( 'primary file restoration did not restore initial state token.' );
}

$metadata_mutated = is_array( $before_metadata ) ? $before_metadata : array();
$metadata_mutated['cmsa_state_token_probe'] = 'changed';
wp_update_attachment_metadata( $attachment_id, $metadata_mutated );
$metadata_readback = wp_get_attachment_metadata( $attachment_id );
if ( ! is_array( $metadata_readback ) || ! isset( $metadata_readback['cmsa_state_token_probe'] ) ) {
	$fail( 'attachment metadata mutation fixture was not established.' );
}
$after_metadata_mutation = $media->get_item( $attachment_id );
if ( is_wp_error( $after_metadata_mutation ) ) {
	$fail( 'media read after attachment metadata mutation failed.' );
}
$metadata_blind = hash_equals( $before_token, (string) $after_metadata_mutation['item']['state_token'] );
if ( ! $metadata_blind ) {
	$fail( 'current state token unexpectedly detected the controlled attachment metadata mutation.' );
}

wp_update_attachment_metadata( $attachment_id, $before_metadata );
$metadata_restored = wp_get_attachment_metadata( $attachment_id );
$after_metadata_restore = $media->get_item( $attachment_id );
if ( $metadata_restored !== $before_metadata || is_wp_error( $after_metadata_restore ) || ! hash_equals( $before_token, (string) $after_metadata_restore['item']['state_token'] ) ) {
	$fail( 'attachment metadata restoration did not restore the initial state.' );
}

wp_delete_attachment( $attachment_id, true );

echo 'media-state-token-contract-cli: PASS current-token-file-hash-blind=' . ( $file_blind ? 'true' : 'false' ) . ' current-token-attachment-metadata-blind=' . ( $metadata_blind ? 'true' : 'false' ) . ' file-size-and-mtime-held-constant=true restoration=verified\n';
