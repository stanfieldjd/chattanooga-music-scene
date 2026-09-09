<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Media_Lifecycle {
	private $media;

	public function __construct( CMSA_Media $media ) {
		$this->media = $media;
	}

	public function get_state( $id, array $media_item = array() ) {
		$attachment_id = (int) $id;
		$attachment = get_post( $attachment_id );
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'cmsa_media_lifecycle_not_found', 'Media attachment was not found.' );
		}
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error( 'cmsa_media_lifecycle_permission', 'Current user cannot inspect this media lifecycle state.' );
		}

		if ( empty( $media_item ) ) {
			$result = $this->media->get_item( $attachment_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$media_item = $result['item'];
		}
		if ( empty( $media_item['state_token'] ) ) {
			return new WP_Error( 'cmsa_media_lifecycle_state', 'Media item did not provide the generic state required for lifecycle inspection.' );
		}

		$primary = get_attached_file( $attachment_id );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$file_state = $this->owned_file_state( $primary, $metadata, $backup_sizes );
		$state = array(
			'generic_state_token' => (string) $media_item['state_token'],
			'attachment_metadata' => maybe_serialize( $metadata ),
			'backup_sizes'        => maybe_serialize( $backup_sizes ),
			'files'               => $file_state['files'],
		);
		$encoded = wp_json_encode( $state, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) ) {
			return new WP_Error( 'cmsa_media_lifecycle_encode', 'Could not encode exact media lifecycle state.' );
		}

		return array(
			'lifecycle_state_token' => hash( 'sha256', $encoded ),
			'lifecycle_file_count'   => count( $file_state['files'] ),
			'lifecycle_complete'     => (bool) $file_state['complete'],
		);
	}

	private function owned_file_state( $primary, $metadata, $backup_sizes ) {
		$owned = array();
		$complete = true;
		$primary_path = is_string( $primary ) ? $primary : '';
		if ( '' === $primary_path ) {
			$complete = false;
		} else {
			$this->add_owned_file( $owned, $primary_path, 'primary' );
		}

		$directory = '' !== $primary_path ? dirname( $primary_path ) : '';
		if ( is_array( $metadata ) && '' !== $directory ) {
			if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size_name => $size ) {
					if ( is_array( $size ) && ! empty( $size['file'] ) && is_string( $size['file'] ) ) {
						$this->add_owned_file( $owned, path_join( $directory, basename( $size['file'] ) ), 'size:' . sanitize_key( (string) $size_name ) );
					}
				}
			}
			foreach ( array( 'original_image', 'source_image', 'animated_video', 'animated_video_poster' ) as $key ) {
				if ( ! empty( $metadata[ $key ] ) && is_string( $metadata[ $key ] ) ) {
					$this->add_owned_file( $owned, path_join( $directory, basename( $metadata[ $key ] ) ), 'metadata:' . $key );
				}
			}
		}

		if ( is_array( $backup_sizes ) && '' !== $directory ) {
			foreach ( $backup_sizes as $backup_name => $backup ) {
				if ( is_array( $backup ) && ! empty( $backup['file'] ) && is_string( $backup['file'] ) ) {
					$this->add_owned_file( $owned, path_join( $directory, basename( $backup['file'] ) ), 'backup:' . sanitize_key( (string) $backup_name ) );
				}
			}
		}

		ksort( $owned, SORT_STRING );
		$files = array();
		foreach ( $owned as $entry ) {
			sort( $entry['roles'], SORT_STRING );
			$path = $entry['path'];
			$exists = is_file( $path );
			$readable = $exists && is_readable( $path );
			$hash = $readable ? hash_file( 'sha256', $path ) : false;
			$size = $exists ? filesize( $path ) : false;
			$mtime = $exists ? filemtime( $path ) : false;
			$file_complete = $exists && $readable && is_string( $hash ) && false !== $size && false !== $mtime;
			if ( ! $file_complete ) {
				$complete = false;
			}
			$files[] = array(
				'filename' => basename( $path ),
				'roles'    => $entry['roles'],
				'exists'   => $exists,
				'readable' => $readable,
				'size'     => false !== $size ? (int) $size : 0,
				'mtime'    => false !== $mtime ? (int) $mtime : 0,
				'sha256'   => is_string( $hash ) ? $hash : '',
			);
		}

		return array(
			'files'    => $files,
			'complete' => $complete,
		);
	}

	private function add_owned_file( array &$owned, $path, $role ) {
		$path = (string) $path;
		if ( '' === $path ) {
			return;
		}
		$key = str_replace( '\\', '/', $path );
		if ( ! isset( $owned[ $key ] ) ) {
			$owned[ $key ] = array(
				'path'  => $path,
				'roles' => array(),
			);
		}
		if ( ! in_array( $role, $owned[ $key ]['roles'], true ) ) {
			$owned[ $key ]['roles'][] = $role;
		}
	}
}
