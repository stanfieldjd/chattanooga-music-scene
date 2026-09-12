<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Media {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';
	const MAX_UPLOAD_BYTES = 8388608;

	public static function register_ability() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'upload-media',
			array(
				'label'               => __( 'Upload media', 'chattanooga-cms-admin' ),
				'description'         => __( 'Creates one WordPress media attachment from a bounded base64 payload after WordPress file-type and payload MIME validation. Arbitrary remote URLs are never fetched.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'filename' => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 255,
						),
						'mime_type' => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 127,
						),
						'data_base64' => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'title'       => array( 'type' => 'string' ),
						'caption'     => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'alt_text'    => array( 'type' => 'string' ),
						'parent_id'   => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
					),
					'required'             => array( 'filename', 'mime_type', 'data_base64' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'upload_media' ),
				'permission_callback' => static function () {
					return current_user_can( 'upload_files' );
				},
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);
	}

	public static function upload_media( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'cmsa_media_invalid_input', 'Media upload input must be an object.' );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'cmsa_media_permission', 'The current user cannot upload media.' );
		}

		$prepared = self::prepare_payload(
			isset( $input['filename'] ) ? $input['filename'] : '',
			isset( $input['mime_type'] ) ? $input['mime_type'] : '',
			isset( $input['data_base64'] ) ? $input['data_base64'] : ''
		);
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$temp = $prepared['temp_path'];
		$parent_id = isset( $input['parent_id'] ) ? max( 0, (int) $input['parent_id'] ) : 0;
		if ( $parent_id > 0 ) {
			$parent = get_post( $parent_id );
			if ( ! $parent instanceof WP_Post || ! current_user_can( 'edit_post', $parent_id ) ) {
				@unlink( $temp );
				return new WP_Error( 'cmsa_media_parent', 'The requested media parent is not available for editing.' );
			}
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$post_data = array();
		if ( array_key_exists( 'title', $input ) ) {
			$post_data['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( array_key_exists( 'caption', $input ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( (string) $input['caption'] );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$post_data['post_content'] = wp_kses_post( (string) $input['description'] );
		}

		$file_array = array(
			'name'     => $prepared['filename'],
			'tmp_name' => $temp,
		);
		$attachment_id = media_handle_sideload( $file_array, $parent_id, null, $post_data );
		if ( is_wp_error( $attachment_id ) ) {
			if ( is_file( $temp ) ) {
				@unlink( $temp );
			}
			return new WP_Error( 'cmsa_media_upload_failed', 'WordPress could not create the media attachment.' );
		}
		$attachment_id = (int) $attachment_id;

		if ( array_key_exists( 'alt_text', $input ) ) {
			$alt_text = sanitize_text_field( (string) $input['alt_text'] );
			if ( '' === $alt_text ) {
				delete_post_meta( $attachment_id, '_wp_attachment_image_alt' );
			} else {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
			}
		}

		$verified = self::verify_created_attachment( $attachment_id, $prepared, $parent_id, $input );
		if ( is_wp_error( $verified ) ) {
			$file = get_attached_file( $attachment_id );
			$deleted = wp_delete_attachment( $attachment_id, true );
			$rolled_back = false !== $deleted
				&& null === get_post( $attachment_id )
				&& ( ! is_string( $file ) || ! is_file( $file ) );

			return new WP_Error(
				'cmsa_media_upload_verify_failed',
				$verified->get_error_message(),
				array( 'rolled_back' => $rolled_back )
			);
		}

		return array(
			'created' => true,
			'sha256'  => $prepared['sha256'],
			'item'    => self::normalize_attachment( $attachment_id ),
		);
	}

	private static function prepare_payload( $filename, $expected_mime, $encoded ) {
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

		$validation = self::validate_upload_file( $temp, $filename, $expected_mime );
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

	private static function validate_upload_file( $path, $filename, $expected_mime ) {
		$checked = wp_check_filetype_and_ext( $path, $filename, get_allowed_mime_types() );
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
			if ( ! is_string( $actual ) || ! self::mime_equivalent( $detected_type, $actual ) ) {
				return new WP_Error( 'cmsa_media_upload_mime', 'Media payload does not match the validated MIME type.' );
			}
		} else {
			return new WP_Error( 'cmsa_media_upload_mime_probe', 'Non-image media upload requires the PHP fileinfo extension for payload verification.' );
		}

		return true;
	}

	private static function verify_created_attachment( $attachment_id, array $prepared, $parent_id, array $input ) {
		$attachment = get_post( $attachment_id );
		$file = get_attached_file( $attachment_id );
		$after_hash = is_string( $file ) && is_file( $file ) ? hash_file( 'sha256', $file ) : false;

		if ( ! $attachment instanceof WP_Post
			|| 'attachment' !== $attachment->post_type
			|| $prepared['mime_type'] !== (string) $attachment->post_mime_type
			|| (int) $attachment->post_parent !== (int) $parent_id
			|| ! is_string( $file )
			|| ! is_file( $file )
			|| (int) filesize( $file ) !== (int) $prepared['byte_length']
			|| ! is_string( $after_hash )
			|| ! hash_equals( $prepared['sha256'], $after_hash ) ) {
			return new WP_Error( 'cmsa_media_upload_file_state', 'Created media did not match the requested file state.' );
		}

		$expected = array();
		if ( array_key_exists( 'title', $input ) ) {
			$expected['title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( array_key_exists( 'caption', $input ) ) {
			$expected['caption'] = sanitize_textarea_field( (string) $input['caption'] );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$expected['description'] = wp_kses_post( (string) $input['description'] );
		}
		if ( array_key_exists( 'alt_text', $input ) ) {
			$expected['alt_text'] = sanitize_text_field( (string) $input['alt_text'] );
		}

		$actual = array(
			'title'       => (string) $attachment->post_title,
			'caption'     => (string) $attachment->post_excerpt,
			'description' => (string) $attachment->post_content,
			'alt_text'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
		foreach ( $expected as $field => $value ) {
			if ( $actual[ $field ] !== $value ) {
				return new WP_Error( 'cmsa_media_upload_metadata_state', 'Created media metadata did not match the requested state.' );
			}
		}

		return true;
	}

	private static function normalize_attachment( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		$file = get_attached_file( $attachment_id );
		$url = wp_get_attachment_url( $attachment_id );
		$metadata = wp_get_attachment_metadata( $attachment_id );

		return array(
			'id'           => (int) $attachment_id,
			'title'        => $attachment instanceof WP_Post ? (string) $attachment->post_title : '',
			'caption'      => $attachment instanceof WP_Post ? (string) $attachment->post_excerpt : '',
			'description'  => $attachment instanceof WP_Post ? (string) $attachment->post_content : '',
			'alt_text'     => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'mime_type'    => $attachment instanceof WP_Post ? (string) $attachment->post_mime_type : '',
			'parent_id'    => $attachment instanceof WP_Post ? (int) $attachment->post_parent : 0,
			'filename'     => is_string( $file ) && '' !== $file ? basename( $file ) : '',
			'file_size'    => is_string( $file ) && is_file( $file ) ? (int) filesize( $file ) : 0,
			'url'          => is_string( $url ) ? $url : '',
			'width'        => is_array( $metadata ) && isset( $metadata['width'] ) ? (int) $metadata['width'] : 0,
			'height'       => is_array( $metadata ) && isset( $metadata['height'] ) ? (int) $metadata['height'] : 0,
		);
	}

	private static function mime_equivalent( $expected, $actual ) {
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
}
