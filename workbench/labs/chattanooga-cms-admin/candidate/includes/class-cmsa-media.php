<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Media {
	const MAX_UPLOAD_BYTES = 8388608;

	public function list_items( array $input = array() ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'cmsa_media_permission', 'Current user cannot inspect the media library.' );
		}

		$page = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 20;
		$mime_group = isset( $input['mime_group'] ) ? sanitize_key( $input['mime_group'] ) : 'any';
		if ( ! in_array( $mime_group, array( 'any', 'image', 'audio', 'video', 'application' ), true ) ) {
			return new WP_Error( 'cmsa_media_mime_group', 'Unsupported media MIME group filter.' );
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => false,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		if ( 'any' !== $mime_group ) {
			$args['post_mime_type'] = $mime_group;
		}
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}

		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( $attachment instanceof WP_Post && 'attachment' === $attachment->post_type && current_user_can( 'edit_post', $attachment->ID ) ) {
				$items[] = $this->normalize_attachment( $attachment, false );
			}
		}

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => (int) $query->found_posts,
			'pages'    => (int) $query->max_num_pages,
			'items'    => $items,
		);
	}

	public function get_item( $id ) {
		$attachment = $this->checked_attachment( $id );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}
		return array( 'item' => $this->normalize_attachment( $attachment, true ) );
	}

	public function prepare_validated_payload( $filename, $expected_mime, $encoded ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'cmsa_media_permission', 'Current user cannot upload media.' );
		}

		$filename = sanitize_file_name( (string) $filename );
		$expected_mime = sanitize_mime_type( (string) $expected_mime );
		$encoded = preg_replace( '/\s+/', '', (string) $encoded );
		if ( '' === $filename || '' === $expected_mime || '' === $encoded ) {
			return new WP_Error( 'cmsa_media_upload_input', 'Filename, MIME type, and base64 payload are required.' );
		}
		$max_encoded = (int) ceil( self::MAX_UPLOAD_BYTES * 4 / 3 ) + 8;
		if ( strlen( $encoded ) > $max_encoded ) {
			return new WP_Error( 'cmsa_media_upload_size', 'Encoded media payload exceeds the bounded upload limit.' );
		}

		$bytes = base64_decode( $encoded, true );
		if ( false === $bytes || '' === $bytes ) {
			return new WP_Error( 'cmsa_media_upload_base64', 'Media payload is not valid base64.' );
		}
		$byte_length = strlen( $bytes );
		if ( $byte_length > self::MAX_UPLOAD_BYTES ) {
			return new WP_Error( 'cmsa_media_upload_size', 'Decoded media payload exceeds the bounded upload limit.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$temp = wp_tempnam( $filename );
		if ( ! $temp ) {
			return new WP_Error( 'cmsa_media_upload_temp', 'Could not allocate a temporary upload file.' );
		}
		$written = file_put_contents( $temp, $bytes, LOCK_EX );
		unset( $bytes );
		if ( false === $written || $written !== $byte_length ) {
			@unlink( $temp );
			return new WP_Error( 'cmsa_media_upload_write', 'Could not write the complete media payload.' );
		}

		$validation = $this->validate_upload_file( $temp, $filename, $expected_mime );
		if ( is_wp_error( $validation ) ) {
			@unlink( $temp );
			return $validation;
		}
		$sha256 = hash_file( 'sha256', $temp );
		if ( ! is_string( $sha256 ) ) {
			@unlink( $temp );
			return new WP_Error( 'cmsa_media_upload_hash', 'Could not hash the validated media payload.' );
		}

		return array(
			'filename'    => $filename,
			'mime_type'   => $expected_mime,
			'temp_path'   => $temp,
			'byte_length' => $byte_length,
			'sha256'      => $sha256,
		);
	}

	public function create_from_base64( array $input ) {
		$prepared = $this->prepare_validated_payload(
			isset( $input['filename'] ) ? $input['filename'] : '',
			isset( $input['mime_type'] ) ? $input['mime_type'] : '',
			isset( $input['data_base64'] ) ? $input['data_base64'] : ''
		);
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$filename = $prepared['filename'];
		$expected_mime = $prepared['mime_type'];
		$temp = $prepared['temp_path'];
		$byte_length = (int) $prepared['byte_length'];
		$before_hash = $prepared['sha256'];

		$parent_id = isset( $input['parent_id'] ) ? max( 0, (int) $input['parent_id'] ) : 0;
		if ( $parent_id ) {
			$parent = get_post( $parent_id );
			if ( ! $parent instanceof WP_Post || ! current_user_can( 'edit_post', $parent_id ) ) {
				@unlink( $temp );
				return new WP_Error( 'cmsa_media_parent', 'Requested media parent is not available for editing.' );
			}
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';

		$post_data = array();
		if ( array_key_exists( 'title', $input ) ) {
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( array_key_exists( 'caption', $input ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $input['caption'] );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$post_data['post_content'] = wp_kses_post( $input['description'] );
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $temp,
		);
		$attachment_id = media_handle_sideload( $file_array, $parent_id, null, $post_data );
		if ( is_wp_error( $attachment_id ) ) {
			if ( is_file( $temp ) ) {
				@unlink( $temp );
			}
			return new WP_Error( 'cmsa_media_upload', 'WordPress could not create the media attachment.' );
		}
		$attachment_id = (int) $attachment_id;

		if ( array_key_exists( 'alt_text', $input ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
		}

		$after = get_post( $attachment_id );
		$file = get_attached_file( $attachment_id );
		$after_hash = is_string( $file ) && is_file( $file ) ? hash_file( 'sha256', $file ) : false;
		$verified = $after instanceof WP_Post
			&& 'attachment' === $after->post_type
			&& $expected_mime === $after->post_mime_type
			&& is_string( $file )
			&& is_file( $file )
			&& (int) filesize( $file ) === $byte_length
			&& is_string( $after_hash )
			&& hash_equals( $before_hash, $after_hash );
		if ( $verified && array_key_exists( 'alt_text', $input ) ) {
			$verified = sanitize_text_field( $input['alt_text'] ) === (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		}
		if ( ! $verified ) {
			$deleted = wp_delete_attachment( $attachment_id, true );
			$rolled_back = false !== $deleted && null === get_post( $attachment_id ) && ( ! is_string( $file ) || ! is_file( $file ) );
			return new WP_Error( 'cmsa_media_upload_verify', 'Created media did not match the requested file state.', array( 'rolled_back' => $rolled_back ) );
		}

		CMSA_Audit::record( 'create-media', (string) $attachment_id, 'success', array( 'bytes' => $byte_length, 'mime_type' => $expected_mime ) );
		return array(
			'created' => true,
			'sha256'  => $after_hash,
			'item'    => $this->normalize_attachment( $after, true ),
		);
	}

	public function update_metadata( array $input ) {
		$attachment = $this->checked_attachment( isset( $input['id'] ) ? $input['id'] : 0 );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}
		$before = $this->normalize_attachment( $attachment, true );
		$expected = isset( $input['expected_state_token'] ) ? (string) $input['expected_state_token'] : '';
		if ( '' === $expected || ! hash_equals( $before['state_token'], $expected ) ) {
			return new WP_Error( 'cmsa_media_conflict', 'Media item changed after it was read; update was not attempted.', array( 'current_state_token' => $before['state_token'] ) );
		}

		$requested = array();
		foreach ( array( 'title', 'caption', 'description', 'alt_text' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$requested[ $field ] = $this->sanitize_metadata_field( $field, $input[ $field ] );
			}
		}
		if ( empty( $requested ) ) {
			return new WP_Error( 'cmsa_media_no_fields', 'No editable media metadata fields were provided.' );
		}
		$changed = false;
		foreach ( $requested as $field => $value ) {
			if ( $before[ $field ] !== $value ) {
				$changed = true;
				break;
			}
		}
		if ( ! $changed ) {
			return new WP_Error( 'cmsa_media_no_change', 'Requested media metadata already matches the current state.' );
		}

		$update = array( 'ID' => $attachment->ID );
		if ( array_key_exists( 'title', $requested ) ) {
			$update['post_title'] = $requested['title'];
		}
		if ( array_key_exists( 'caption', $requested ) ) {
			$update['post_excerpt'] = $requested['caption'];
		}
		if ( array_key_exists( 'description', $requested ) ) {
			$update['post_content'] = $requested['description'];
		}
		if ( count( $update ) > 1 ) {
			$result = wp_update_post( wp_slash( $update ), true );
			if ( is_wp_error( $result ) || ! $result ) {
				return new WP_Error( 'cmsa_media_update', 'Could not update media metadata.' );
			}
		}
		if ( array_key_exists( 'alt_text', $requested ) ) {
			if ( '' === $requested['alt_text'] ) {
				delete_post_meta( $attachment->ID, '_wp_attachment_image_alt' );
			} else {
				update_post_meta( $attachment->ID, '_wp_attachment_image_alt', $requested['alt_text'] );
			}
		}

		$after_post = get_post( $attachment->ID );
		$after = $after_post instanceof WP_Post ? $this->normalize_attachment( $after_post, true ) : null;
		if ( ! $after || ! $this->metadata_matches( $after, $requested ) ) {
			$rolled_back = $this->restore_metadata( $attachment->ID, $before );
			return new WP_Error( 'cmsa_media_update_verify', 'Media metadata verification failed.', array( 'rolled_back' => $rolled_back ) );
		}

		CMSA_Audit::record( 'update-media', (string) $attachment->ID, 'success' );
		return array(
			'updated'              => true,
			'previous_state_token' => $before['state_token'],
			'item'                 => $after,
		);
	}

	public function set_featured_image( array $input ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return new WP_Error( 'cmsa_media_featured_post', 'Featured-image target must be an existing post or page.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'cmsa_media_featured_permission', 'Current user cannot edit the featured-image target.' );
		}

		$expected_modified = isset( $input['expected_post_modified_gmt'] ) ? (string) $input['expected_post_modified_gmt'] : '';
		if ( '' === $expected_modified || ! hash_equals( (string) $post->post_modified_gmt, $expected_modified ) ) {
			return new WP_Error( 'cmsa_media_featured_post_conflict', 'Featured-image target changed after it was read; mutation was not attempted.', array( 'current_post_modified_gmt' => (string) $post->post_modified_gmt ) );
		}
		$current_id = (int) get_post_thumbnail_id( $post_id );
		$expected_id = isset( $input['expected_attachment_id'] ) ? max( 0, (int) $input['expected_attachment_id'] ) : 0;
		if ( $current_id !== $expected_id ) {
			return new WP_Error( 'cmsa_media_featured_relationship_conflict', 'Featured-image relationship changed after it was read; mutation was not attempted.', array( 'current_attachment_id' => $current_id ) );
		}

		$target_id = isset( $input['attachment_id'] ) ? max( 0, (int) $input['attachment_id'] ) : 0;
		if ( $target_id === $current_id ) {
			return new WP_Error( 'cmsa_media_featured_no_change', 'Requested featured-image relationship already matches the current state.' );
		}
		if ( $target_id ) {
			$attachment = $this->checked_attachment( $target_id );
			if ( is_wp_error( $attachment ) ) {
				return $attachment;
			}
			if ( 0 !== strpos( (string) $attachment->post_mime_type, 'image/' ) ) {
				return new WP_Error( 'cmsa_media_featured_type', 'Featured image must reference an image attachment.' );
			}
		}

		$result = $target_id ? set_post_thumbnail( $post_id, $target_id ) : delete_post_thumbnail( $post_id );
		$after_id = (int) get_post_thumbnail_id( $post_id );
		if ( false === $result || $after_id !== $target_id ) {
			if ( $current_id ) {
				set_post_thumbnail( $post_id, $current_id );
			} else {
				delete_post_thumbnail( $post_id );
			}
			$rollback_id = (int) get_post_thumbnail_id( $post_id );
			$rolled_back = $rollback_id === $current_id;
			return new WP_Error( 'cmsa_media_featured_verify', 'Featured-image relationship verification failed.', array( 'rolled_back' => $rolled_back, 'previous_attachment_id' => $current_id ) );
		}

		CMSA_Audit::record( 'set-featured-image', $post->post_type . ':' . $post_id, 'success', array( 'previous_attachment_id' => $current_id, 'attachment_id' => $target_id ) );
		return array(
			'updated'                => true,
			'post_id'                => $post_id,
			'previous_attachment_id' => $current_id,
			'attachment_id'          => $after_id,
		);
	}

	private function checked_attachment( $id ) {
		$id = (int) $id;
		if ( $id < 1 ) {
			return new WP_Error( 'cmsa_media_id', 'A valid media attachment ID is required.' );
		}
		$attachment = get_post( $id );
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'cmsa_media_not_found', 'Requested media attachment does not exist.' );
		}
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'cmsa_media_permission', 'Current user cannot edit this media attachment.' );
		}
		return $attachment;
	}

	private function validate_upload_file( $path, $filename, $expected_mime ) {
		$allowed = get_allowed_mime_types();
		$checked = wp_check_filetype_and_ext( $path, $filename, $allowed );
		$detected_type = ! empty( $checked['type'] ) ? sanitize_mime_type( $checked['type'] ) : '';
		$detected_ext = ! empty( $checked['ext'] ) ? sanitize_key( $checked['ext'] ) : '';
		if ( '' === $detected_type || '' === $detected_ext ) {
			return new WP_Error( 'cmsa_media_upload_type', 'Media filename or payload type is not allowed by WordPress.' );
		}
		if ( $detected_type !== $expected_mime ) {
			return new WP_Error( 'cmsa_media_upload_mime', 'Requested MIME type does not match the WordPress-validated file type.' );
		}

		if ( 0 === strpos( $detected_type, 'image/' ) && function_exists( 'wp_get_image_mime' ) ) {
			$image_mime = wp_get_image_mime( $path );
			if ( ! is_string( $image_mime ) || $image_mime !== $detected_type ) {
				return new WP_Error( 'cmsa_media_upload_mime', 'Image payload does not match the validated image MIME type.' );
			}
		} elseif ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$actual = $finfo ? finfo_file( $finfo, $path ) : false;
			if ( $finfo ) {
				finfo_close( $finfo );
			}
			if ( ! is_string( $actual ) || ! $this->mime_equivalent( $detected_type, $actual ) ) {
				return new WP_Error( 'cmsa_media_upload_mime', 'Media payload does not match the validated MIME type.' );
			}
		} else {
			return new WP_Error( 'cmsa_media_upload_mime_probe', 'Non-image media upload requires the PHP fileinfo extension for payload verification.' );
		}

		return array( 'type' => $detected_type, 'ext' => $detected_ext );
	}

	private function mime_equivalent( $expected, $actual ) {
		if ( $expected === $actual ) {
			return true;
		}
		$groups = array(
			'audio/mp3'  => array( 'audio/mpeg' ),
			'audio/mpeg' => array( 'audio/mp3' ),
			'text/plain' => array( 'text/x-plain' ),
		);
		return isset( $groups[ $expected ] ) && in_array( $actual, $groups[ $expected ], true );
	}

	private function normalize_attachment( WP_Post $attachment, $detailed ) {
		$file = get_attached_file( $attachment->ID );
		$url = wp_get_attachment_url( $attachment->ID );
		$alt = (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		$item = array(
			'id'           => (int) $attachment->ID,
			'title'        => (string) $attachment->post_title,
			'caption'      => (string) $attachment->post_excerpt,
			'alt_text'     => $alt,
			'mime_type'    => (string) $attachment->post_mime_type,
			'parent_id'    => (int) $attachment->post_parent,
			'filename'     => is_string( $file ) && '' !== $file ? basename( $file ) : '',
			'file_size'    => is_string( $file ) && is_file( $file ) ? (int) filesize( $file ) : 0,
			'url'          => is_string( $url ) ? $url : '',
			'date_gmt'     => (string) $attachment->post_date_gmt,
			'modified_gmt' => (string) $attachment->post_modified_gmt,
		);
		if ( $detailed ) {
			$item['description'] = (string) $attachment->post_content;
			$metadata = wp_get_attachment_metadata( $attachment->ID );
			$item['width'] = is_array( $metadata ) && isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
			$item['height'] = is_array( $metadata ) && isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;
		}
		$item['state_token'] = $this->state_token( $attachment, $alt, $file );
		return $item;
	}

	private function state_token( WP_Post $attachment, $alt, $file ) {
		$state = array(
			'id'            => (int) $attachment->ID,
			'modified_gmt'  => (string) $attachment->post_modified_gmt,
			'title'         => (string) $attachment->post_title,
			'caption'       => (string) $attachment->post_excerpt,
			'description'   => (string) $attachment->post_content,
			'alt_text'      => (string) $alt,
			'mime_type'     => (string) $attachment->post_mime_type,
			'parent_id'     => (int) $attachment->post_parent,
			'attached_file' => (string) get_post_meta( $attachment->ID, '_wp_attached_file', true ),
			'file_size'     => is_string( $file ) && is_file( $file ) ? (int) filesize( $file ) : 0,
			'file_mtime'    => is_string( $file ) && is_file( $file ) ? (int) filemtime( $file ) : 0,
		);
		return hash( 'sha256', wp_json_encode( $state, JSON_UNESCAPED_SLASHES ) );
	}

	private function sanitize_metadata_field( $field, $value ) {
		if ( 'description' === $field ) {
			return wp_kses_post( (string) $value );
		}
		if ( 'caption' === $field ) {
			return sanitize_textarea_field( (string) $value );
		}
		return sanitize_text_field( (string) $value );
	}

	private function metadata_matches( array $item, array $requested ) {
		foreach ( $requested as $field => $value ) {
			if ( ! array_key_exists( $field, $item ) || $item[ $field ] !== $value ) {
				return false;
			}
		}
		return true;
	}

	private function restore_metadata( $attachment_id, array $before ) {
		$result = wp_update_post(
			wp_slash(
				array(
					'ID'           => (int) $attachment_id,
					'post_title'   => $before['title'],
					'post_excerpt' => $before['caption'],
					'post_content' => $before['description'],
				)
			),
			true
		);
		if ( is_wp_error( $result ) || ! $result ) {
			return false;
		}
		if ( '' === $before['alt_text'] ) {
			delete_post_meta( $attachment_id, '_wp_attachment_image_alt' );
		} else {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $before['alt_text'] );
		}
		$after = get_post( $attachment_id );
		if ( ! $after instanceof WP_Post ) {
			return false;
		}
		$normalized = $this->normalize_attachment( $after, true );
		return $normalized['title'] === $before['title']
			&& $normalized['caption'] === $before['caption']
			&& $normalized['description'] === $before['description']
			&& $normalized['alt_text'] === $before['alt_text'];
	}
}
