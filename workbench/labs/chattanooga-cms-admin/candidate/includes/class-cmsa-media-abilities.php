<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Media_Abilities {
	private $media;
	private $lifecycle;

	public function __construct( CMSA_Media $media ) {
		$this->media = $media;
		$this->lifecycle = new CMSA_Media_Lifecycle( $media );
	}

	public function register() {
		$this->register_ability(
			'list-media',
			'List media',
			'Returns a bounded WordPress media-library collection using an allowlisted metadata shape and exact state tokens.',
			$this->object_schema(
				array(
					'page'       => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
					'per_page'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
					'search'     => array( 'type' => 'string', 'default' => '' ),
					'mime_group' => array( 'type' => 'string', 'enum' => array( 'any', 'image', 'audio', 'video', 'application' ), 'default' => 'any' ),
				)
			),
			function ( $input ) { return $this->media->list_items( is_array( $input ) ? $input : array() ); },
			'upload_files',
			true,
			false,
			true
		);

		$this->register_ability(
			'get-media',
			'Get media',
			'Returns one editable WordPress media attachment, allowlisted metadata, dimensions when available, the ordinary state token, and a file/metadata-bound lifecycle state token for guarded destructive lifecycle operations.',
			$this->object_schema( array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ) ),
			function ( $input ) { return $this->get_media( $input['id'] ); },
			'upload_files',
			true,
			false,
			true
		);

		$this->register_ability(
			'create-media',
			'Create media',
			'Creates one WordPress media attachment from a bounded base64 payload after native file-type and payload MIME validation. This ability never fetches an arbitrary remote URL.',
			$this->object_schema(
				array(
					'filename'    => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
					'mime_type'   => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 127 ),
					'data_base64' => array( 'type' => 'string', 'minLength' => 1 ),
					'title'       => array( 'type' => 'string' ),
					'caption'     => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'alt_text'    => array( 'type' => 'string' ),
					'parent_id'   => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
				),
				array( 'filename', 'mime_type', 'data_base64' )
			),
			function ( $input ) { return $this->media->create_from_base64( $input ); },
			'upload_files',
			false,
			false,
			false
		);

		$this->register_ability(
			'replace-media',
			'Replace media file',
			'Replaces the primary file of one eligible ordinary image attachment in place only when its exact file-and-metadata lifecycle state still matches. The attachment ID, URL/path, MIME type, parent, and featured-image references are preserved; obsolete derivatives are removed; verification failure restores the exact prior file and metadata state.',
			$this->object_schema(
				array(
					'id'                             => array( 'type' => 'integer', 'minimum' => 1 ),
					'expected_lifecycle_state_token' => array( 'type' => 'string', 'minLength' => 1 ),
					'mime_type'                      => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 127 ),
					'data_base64'                    => array( 'type' => 'string', 'minLength' => 1 ),
				),
				array( 'id', 'expected_lifecycle_state_token', 'mime_type', 'data_base64' )
			),
			function ( $input ) { return $this->lifecycle->replace_from_base64( $input ); },
			'upload_files',
			false,
			true,
			false
		);

		$this->register_ability(
			'update-media',
			'Update media metadata',
			'Updates title, caption, description, or alt text for one media attachment only when its exact prior state still matches; verification failure restores the prior metadata values.',
			$this->object_schema(
				array(
					'id'                   => array( 'type' => 'integer', 'minimum' => 1 ),
					'expected_state_token' => array( 'type' => 'string', 'minLength' => 1 ),
					'title'                => array( 'type' => 'string' ),
					'caption'              => array( 'type' => 'string' ),
					'description'          => array( 'type' => 'string' ),
					'alt_text'             => array( 'type' => 'string' ),
				),
				array( 'id', 'expected_state_token' )
			),
			function ( $input ) { return $this->media->update_metadata( $input ); },
			'upload_files',
			false,
			true,
			false
		);

		$this->register_ability(
			'set-featured-image',
			'Set featured image',
			'Sets or clears one post/page featured-image relationship after exact post timestamp and relationship conflict checks; verification failure restores the prior relationship.',
			$this->object_schema(
				array(
					'post_id'                    => array( 'type' => 'integer', 'minimum' => 1 ),
					'expected_post_modified_gmt' => array( 'type' => 'string', 'minLength' => 1 ),
					'expected_attachment_id'     => array( 'type' => 'integer', 'minimum' => 0 ),
					'attachment_id'              => array( 'type' => 'integer', 'minimum' => 0 ),
				),
				array( 'post_id', 'expected_post_modified_gmt', 'expected_attachment_id', 'attachment_id' )
			),
			function ( $input ) { return $this->media->set_featured_image( $input ); },
			'upload_files',
			false,
			true,
			false
		);
	}

	private function get_media( $id ) {
		$result = $this->media->get_item( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$lifecycle = $this->lifecycle->get_state( $id, $result['item'] );
		if ( is_wp_error( $lifecycle ) ) {
			return $lifecycle;
		}
		$result['item']['lifecycle_state_token'] = $lifecycle['lifecycle_state_token'];
		$result['item']['lifecycle_file_count'] = $lifecycle['lifecycle_file_count'];
		$result['item']['lifecycle_complete'] = $lifecycle['lifecycle_complete'];
		return $result;
	}

	private function register_ability( $slug, $label, $description, $input_schema, $callback, $capability, $readonly, $destructive, $idempotent ) {
		wp_register_ability(
			'chattanooga-cms-admin/' . $slug,
			array(
				'label'               => __( $label, 'chattanooga-cms-admin' ),
				'description'         => __( $description, 'chattanooga-cms-admin' ),
				'category'            => 'chattanooga-cms-admin',
				'input_schema'        => $input_schema,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => $callback,
				'permission_callback' => function () use ( $capability ) {
					return current_user_can( $capability );
				},
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => (bool) $readonly,
						'destructive' => (bool) $destructive,
						'idempotent'  => (bool) $idempotent,
					),
				),
			)
		);
	}

	private function object_schema( array $properties, array $required = array() ) {
		$schema = array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
		if ( $required ) {
			$schema['required'] = $required;
		}
		return $schema;
	}
}
