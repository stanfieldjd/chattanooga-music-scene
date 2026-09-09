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
		$state = $this->inspect_state( $id, $media_item );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		return array(
			'lifecycle_state_token' => $state['lifecycle_state_token'],
			'lifecycle_file_count'   => count( $state['file_state']['files'] ),
			'lifecycle_complete'     => (bool) $state['file_state']['complete'],
		);
	}

	public function replace_from_base64( array $input ) {
		$attachment_id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$before = $this->inspect_state( $attachment_id );
		if ( is_wp_error( $before ) ) {
			return $before;
		}
		if ( empty( $before['file_state']['complete'] ) ) {
			return new WP_Error( 'cmsa_media_replace_incomplete', 'Media lifecycle state is incomplete; replacement was not attempted.', array( 'current_lifecycle_state_token' => $before['lifecycle_state_token'] ) );
		}

		$expected = isset( $input['expected_lifecycle_state_token'] ) ? (string) $input['expected_lifecycle_state_token'] : '';
		if ( '' === $expected || ! hash_equals( $before['lifecycle_state_token'], $expected ) ) {
			return new WP_Error( 'cmsa_media_replace_conflict', 'Media lifecycle state changed after it was read; replacement was not attempted.', array( 'current_lifecycle_state_token' => $before['lifecycle_state_token'] ) );
		}

		$contract = $this->assert_ordinary_image_contract( $before );
		if ( is_wp_error( $contract ) ) {
			return $contract;
		}

		$prepared = $this->media->prepare_validated_payload(
			basename( $before['primary'] ),
			isset( $input['mime_type'] ) ? $input['mime_type'] : '',
			isset( $input['data_base64'] ) ? $input['data_base64'] : ''
		);
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$temp = $prepared['temp_path'];
		if ( $prepared['mime_type'] !== (string) $before['attachment']->post_mime_type ) {
			@unlink( $temp );
			return new WP_Error( 'cmsa_media_replace_type', 'Replacement must preserve the attachment MIME type.' );
		}

		$current_primary_hash = hash_file( 'sha256', $before['primary'] );
		if ( ! is_string( $current_primary_hash ) ) {
			@unlink( $temp );
			return new WP_Error( 'cmsa_media_replace_state', 'Could not verify the current primary media file.' );
		}
		if ( hash_equals( $current_primary_hash, $prepared['sha256'] ) ) {
			@unlink( $temp );
			return new WP_Error( 'cmsa_media_replace_no_change', 'Replacement payload already matches the current primary media file.' );
		}

		$recheck = $this->inspect_state( $attachment_id );
		if ( is_wp_error( $recheck ) || ! hash_equals( $before['lifecycle_state_token'], $recheck['lifecycle_state_token'] ) ) {
			@unlink( $temp );
			$current = is_wp_error( $recheck ) ? '' : $recheck['lifecycle_state_token'];
			return new WP_Error( 'cmsa_media_replace_conflict', 'Media lifecycle state changed while the replacement payload was prepared; replacement was not attempted.', array( 'current_lifecycle_state_token' => $current ) );
		}
		$before = $recheck;

		$checkpoint = $this->snapshot_files( $before['file_state']['paths'] );
		if ( is_wp_error( $checkpoint ) ) {
			@unlink( $temp );
			return $checkpoint;
		}

		$final_recheck = $this->inspect_state( $attachment_id );
		if ( is_wp_error( $final_recheck ) || ! hash_equals( $before['lifecycle_state_token'], $final_recheck['lifecycle_state_token'] ) ) {
			$this->cleanup_snapshot( $checkpoint );
			@unlink( $temp );
			$current = is_wp_error( $final_recheck ) ? '' : $final_recheck['lifecycle_state_token'];
			return new WP_Error( 'cmsa_media_replace_conflict', 'Media lifecycle state changed while the rollback checkpoint was created; replacement was not attempted.', array( 'current_lifecycle_state_token' => $current ) );
		}
		$before = $final_recheck;

		$invariants = array(
			'id'            => (int) $before['attachment']->ID,
			'url'           => (string) wp_get_attachment_url( $attachment_id ),
			'attached_file' => (string) get_post_meta( $attachment_id, '_wp_attached_file', true ),
			'parent_id'     => (int) $before['attachment']->post_parent,
			'mime_type'     => (string) $before['attachment']->post_mime_type,
			'featured_ids'  => $this->featured_reference_ids( $attachment_id ),
		);

		$current_paths = $before['file_state']['paths'];
		if ( ! copy( $temp, $before['primary'] ) ) {
			return $this->rollback_error( 'cmsa_media_replace_write', 'Could not replace the primary media file.', $before, $checkpoint, $current_paths, $temp );
		}
		clearstatcache( true, $before['primary'] );

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$generated = wp_generate_attachment_metadata( $attachment_id, $before['primary'] );
		$persisted_during_generation = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $persisted_during_generation ) ) {
			$current_paths = array_values( array_unique( array_merge( $current_paths, $this->owned_paths( $before['primary'], $persisted_during_generation, $before['backup_sizes'] ) ) ) );
		}
		if ( ! is_array( $generated ) || empty( $generated['width'] ) || empty( $generated['height'] ) ) {
			return $this->rollback_error( 'cmsa_media_replace_generate', 'WordPress could not generate replacement image metadata.', $before, $checkpoint, $current_paths, $temp );
		}
		$generated_contract = $this->assert_generated_ordinary_image_contract( $generated, $before['metadata'] );
		if ( is_wp_error( $generated_contract ) ) {
			$current_paths = array_values( array_unique( array_merge( $current_paths, $this->owned_paths( $before['primary'], $generated, $before['backup_sizes'] ) ) ) );
			return $this->rollback_error( $generated_contract->get_error_code(), $generated_contract->get_error_message(), $before, $checkpoint, $current_paths, $temp );
		}

		wp_update_attachment_metadata( $attachment_id, $generated );
		$persisted = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $persisted ) || $persisted !== $generated ) {
			if ( is_array( $persisted ) ) {
				$current_paths = array_values( array_unique( array_merge( $current_paths, $this->owned_paths( $before['primary'], $persisted, $before['backup_sizes'] ) ) ) );
			}
			return $this->rollback_error( 'cmsa_media_replace_metadata', 'Replacement image metadata did not persist exactly.', $before, $checkpoint, $current_paths, $temp );
		}

		$after_file_state = $this->owned_file_state( $before['primary'], $persisted, $before['backup_sizes'] );
		$current_paths = array_values( array_unique( array_merge( $current_paths, $after_file_state['paths'] ) ) );
		if ( empty( $after_file_state['complete'] ) ) {
			return $this->rollback_error( 'cmsa_media_replace_incomplete', 'Replacement generated an incomplete owned-file state.', $before, $checkpoint, $current_paths, $temp );
		}

		$old_only = array_values( array_diff( $before['file_state']['paths'], $after_file_state['paths'] ) );
		foreach ( $old_only as $path ) {
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
		foreach ( $old_only as $path ) {
			if ( is_file( $path ) ) {
				return $this->rollback_error( 'cmsa_media_replace_cleanup', 'An obsolete derivative could not be removed.', $before, $checkpoint, $current_paths, $temp );
			}
		}

		$after = $this->inspect_state( $attachment_id );
		$after_primary_hash = is_wp_error( $after ) ? false : hash_file( 'sha256', $after['primary'] );
		$verified = ! is_wp_error( $after )
			&& ! empty( $after['file_state']['complete'] )
			&& ! hash_equals( $before['lifecycle_state_token'], $after['lifecycle_state_token'] )
			&& is_string( $after_primary_hash )
			&& hash_equals( $prepared['sha256'], $after_primary_hash )
			&& (int) $after['attachment']->ID === $invariants['id']
			&& (string) wp_get_attachment_url( $attachment_id ) === $invariants['url']
			&& (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) === $invariants['attached_file']
			&& (int) $after['attachment']->post_parent === $invariants['parent_id']
			&& (string) $after['attachment']->post_mime_type === $invariants['mime_type']
			&& $this->featured_reference_ids( $attachment_id ) === $invariants['featured_ids']
			&& $after['metadata'] === $generated
			&& ! $after['backup_sizes_exists']
			&& empty( $after['backup_sizes'] );
		if ( ! $verified ) {
			return $this->rollback_error( 'cmsa_media_replace_verify', 'Replacement verification failed.', $before, $checkpoint, $current_paths, $temp );
		}

		$this->cleanup_snapshot( $checkpoint );
		@unlink( $temp );
		$item = $after['media_item'];
		$item['lifecycle_state_token'] = $after['lifecycle_state_token'];
		$item['lifecycle_file_count'] = count( $after['file_state']['files'] );
		$item['lifecycle_complete'] = (bool) $after['file_state']['complete'];

		CMSA_Audit::record(
			'replace-media',
			(string) $attachment_id,
			'success',
			array(
				'bytes'                  => (int) $prepared['byte_length'],
				'mime_type'              => $prepared['mime_type'],
				'obsolete_files_removed' => count( $old_only ),
			)
		);
		return array(
			'replaced'                       => true,
			'previous_lifecycle_state_token' => $before['lifecycle_state_token'],
			'sha256'                         => $after_primary_hash,
			'item'                           => $item,
		);
	}

	private function inspect_state( $id, array $media_item = array() ) {
		$attachment_id = (int) $id;
		$attachment = get_post( $attachment_id );
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'cmsa_media_lifecycle_not_found', 'Media attachment was not found.' );
		}
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error( 'cmsa_media_lifecycle_permission', 'Current user cannot inspect or mutate this media lifecycle state.' );
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
		$backup_exists = metadata_exists( 'post', $attachment_id, '_wp_attachment_backup_sizes' );
		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$file_state = $this->owned_file_state( $primary, $metadata, $backup_sizes );
		$state = array(
			'generic_state_token' => (string) $media_item['state_token'],
			'attachment_metadata' => maybe_serialize( $metadata ),
			'backup_sizes_exists' => (bool) $backup_exists,
			'backup_sizes'        => maybe_serialize( $backup_sizes ),
			'files'               => $file_state['files'],
		);
		$encoded = wp_json_encode( $state, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) ) {
			return new WP_Error( 'cmsa_media_lifecycle_encode', 'Could not encode exact media lifecycle state.' );
		}

		return array(
			'attachment'            => $attachment,
			'media_item'            => $media_item,
			'primary'               => is_string( $primary ) ? $primary : '',
			'metadata'              => $metadata,
			'backup_sizes'          => $backup_sizes,
			'backup_sizes_exists'   => (bool) $backup_exists,
			'file_state'            => $file_state,
			'lifecycle_state_token' => hash( 'sha256', $encoded ),
		);
	}

	private function assert_ordinary_image_contract( array $state ) {
		if ( 0 !== strpos( (string) $state['attachment']->post_mime_type, 'image/' ) ) {
			return new WP_Error( 'cmsa_media_replace_type', 'Replacement is limited to image attachments.' );
		}
		if ( '' === $state['primary'] || ! is_file( $state['primary'] ) || ! is_array( $state['metadata'] ) ) {
			return new WP_Error( 'cmsa_media_replace_incomplete', 'Replacement requires a complete ordinary image attachment.' );
		}
		if ( ! empty( $state['backup_sizes_exists'] ) ) {
			return new WP_Error( 'cmsa_media_replace_complex', 'Images with backup-size state are not eligible for bounded replacement.' );
		}
		foreach ( array( 'original_image', 'source_image', 'animated_video', 'animated_video_poster' ) as $key ) {
			if ( ! empty( $state['metadata'][ $key ] ) ) {
				return new WP_Error( 'cmsa_media_replace_complex', 'Images with companion-file state are not eligible for bounded replacement.' );
			}
		}
		return true;
	}

	private function assert_generated_ordinary_image_contract( array $generated, array $before_metadata ) {
		foreach ( array( 'original_image', 'source_image', 'animated_video', 'animated_video_poster' ) as $key ) {
			if ( ! empty( $generated[ $key ] ) ) {
				return new WP_Error( 'cmsa_media_replace_complex', 'Replacement generated unsupported companion-file state.' );
			}
		}
		$before_file = isset( $before_metadata['file'] ) ? (string) $before_metadata['file'] : '';
		$after_file = isset( $generated['file'] ) ? (string) $generated['file'] : '';
		if ( '' !== $before_file && $before_file !== $after_file ) {
			return new WP_Error( 'cmsa_media_replace_path', 'Replacement metadata changed the attachment file path.' );
		}
		return true;
	}

	private function owned_paths( $primary, $metadata, $backup_sizes ) {
		$state = $this->owned_file_state( $primary, $metadata, $backup_sizes );
		return $state['paths'];
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
		$paths = array();
		foreach ( $owned as $entry ) {
			sort( $entry['roles'], SORT_STRING );
			$path = $entry['path'];
			$paths[] = $path;
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
			'paths'    => $paths,
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

	private function snapshot_files( array $paths ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$snapshot = array();
		foreach ( array_values( array_unique( $paths ) ) as $path ) {
			if ( ! is_file( $path ) || ! is_readable( $path ) ) {
				$this->cleanup_snapshot( $snapshot );
				return new WP_Error( 'cmsa_media_replace_checkpoint', 'Could not read every owned media file for the rollback checkpoint.' );
			}
			$temp = wp_tempnam( 'cmsa-media-rollback-' . basename( $path ) );
			if ( ! $temp || ! copy( $path, $temp ) ) {
				if ( $temp ) {
					@unlink( $temp );
				}
				$this->cleanup_snapshot( $snapshot );
				return new WP_Error( 'cmsa_media_replace_checkpoint', 'Could not create the complete media rollback checkpoint.' );
			}
			$hash = hash_file( 'sha256', $path );
			$size = filesize( $path );
			$mtime = filemtime( $path );
			if ( ! is_string( $hash ) || false === $size || false === $mtime ) {
				@unlink( $temp );
				$this->cleanup_snapshot( $snapshot );
				return new WP_Error( 'cmsa_media_replace_checkpoint', 'Could not verify the complete media rollback checkpoint.' );
			}
			$snapshot[ $path ] = array(
				'temp'   => $temp,
				'sha256' => $hash,
				'size'   => (int) $size,
				'mtime'  => (int) $mtime,
			);
		}
		return $snapshot;
	}

	private function restore_snapshot( array $before, array $snapshot, array $current_paths ) {
		$original_paths = array_keys( $snapshot );
		foreach ( array_values( array_unique( $current_paths ) ) as $path ) {
			if ( ! in_array( $path, $original_paths, true ) && is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
		foreach ( $snapshot as $path => $file ) {
			if ( ! is_file( $file['temp'] ) ) {
				return false;
			}
			if ( ! is_dir( dirname( $path ) ) && ! wp_mkdir_p( dirname( $path ) ) ) {
				return false;
			}
			if ( ! copy( $file['temp'], $path ) ) {
				return false;
			}
			@touch( $path, $file['mtime'] );
		}
		wp_update_attachment_metadata( (int) $before['attachment']->ID, $before['metadata'] );
		if ( $before['backup_sizes_exists'] ) {
			update_post_meta( (int) $before['attachment']->ID, '_wp_attachment_backup_sizes', $before['backup_sizes'] );
		} else {
			delete_post_meta( (int) $before['attachment']->ID, '_wp_attachment_backup_sizes' );
		}
		clearstatcache();

		foreach ( $snapshot as $path => $file ) {
			if ( ! is_file( $path ) ) {
				return false;
			}
			$hash = hash_file( 'sha256', $path );
			$size = filesize( $path );
			$mtime = filemtime( $path );
			if ( ! is_string( $hash ) || false === $size || false === $mtime || ! hash_equals( $file['sha256'], $hash ) || (int) $size !== $file['size'] || (int) $mtime !== $file['mtime'] ) {
				return false;
			}
		}
		if ( wp_get_attachment_metadata( (int) $before['attachment']->ID ) !== $before['metadata'] ) {
			return false;
		}
		if ( metadata_exists( 'post', (int) $before['attachment']->ID, '_wp_attachment_backup_sizes' ) !== $before['backup_sizes_exists'] ) {
			return false;
		}
		if ( get_post_meta( (int) $before['attachment']->ID, '_wp_attachment_backup_sizes', true ) !== $before['backup_sizes'] ) {
			return false;
		}
		$restored = $this->inspect_state( (int) $before['attachment']->ID );
		return ! is_wp_error( $restored ) && hash_equals( $before['lifecycle_state_token'], $restored['lifecycle_state_token'] );
	}

	private function rollback_error( $code, $message, array $before, array $checkpoint, array $current_paths, $temp ) {
		$rolled_back = $this->restore_snapshot( $before, $checkpoint, $current_paths );
		$this->cleanup_snapshot( $checkpoint );
		if ( is_string( $temp ) && '' !== $temp ) {
			@unlink( $temp );
		}
		return new WP_Error( $code, $message, array( 'rolled_back' => $rolled_back ) );
	}

	private function cleanup_snapshot( array $snapshot ) {
		foreach ( $snapshot as $file ) {
			if ( is_array( $file ) && ! empty( $file['temp'] ) && is_file( $file['temp'] ) ) {
				@unlink( $file['temp'] );
			}
		}
	}

	private function featured_reference_ids( $attachment_id ) {
		$ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_thumbnail_id',
				'meta_value'     => (int) $attachment_id,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$ids = array_map( 'intval', is_array( $ids ) ? $ids : array() );
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}
}
