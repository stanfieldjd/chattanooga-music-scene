<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Content_Abilities {
	private $content;

	public function __construct( CMSA_Content $content ) {
		$this->content = $content;
	}

	public function register() {
		$this->register_type( 'post', 'Post', 'edit_posts', 'delete_posts' );
		$this->register_type( 'page', 'Page', 'edit_pages', 'delete_pages' );
	}

	private function register_type( $post_type, $label, $edit_capability, $delete_capability ) {
		$list_schema = $this->object_schema(
			array(
				'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
				'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
				'search'   => array( 'type' => 'string', 'default' => '' ),
				'status'   => array(
					'type'    => 'string',
					'enum'    => array( 'any', 'publish', 'draft', 'pending', 'future', 'private', 'trash' ),
					'default' => 'any',
				),
			)
		);
		$get_schema = $this->object_schema(
			array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
			array( 'id' )
		);
		$create_properties = array(
			'title'   => array( 'type' => 'string', 'minLength' => 1 ),
			'content' => array( 'type' => 'string', 'default' => '' ),
			'excerpt' => array( 'type' => 'string', 'default' => '' ),
			'slug'    => array( 'type' => 'string', 'default' => '' ),
		);
		if ( 'page' === $post_type ) {
			$create_properties['parent_id'] = array( 'type' => 'integer', 'minimum' => 1 );
		}
		$create_schema = $this->object_schema( $create_properties, array( 'title' ) );
		$update_schema = $this->object_schema(
			array(
				'id'                    => array( 'type' => 'integer', 'minimum' => 1 ),
				'expected_modified_gmt' => array( 'type' => 'string', 'minLength' => 1 ),
				'title'                 => array( 'type' => 'string' ),
				'content'               => array( 'type' => 'string' ),
				'excerpt'               => array( 'type' => 'string' ),
			),
			array( 'id', 'expected_modified_gmt' )
		);
		$state_schema = $this->object_schema(
			array(
				'id'                    => array( 'type' => 'integer', 'minimum' => 1 ),
				'expected_modified_gmt' => array( 'type' => 'string', 'minLength' => 1 ),
			),
			array( 'id', 'expected_modified_gmt' )
		);
		$revision_schema = $this->object_schema(
			array(
				'id'                    => array( 'type' => 'integer', 'minimum' => 1 ),
				'revision_id'           => array( 'type' => 'integer', 'minimum' => 1 ),
				'expected_modified_gmt' => array( 'type' => 'string', 'minLength' => 1 ),
			),
			array( 'id', 'revision_id', 'expected_modified_gmt' )
		);

		$this->register_ability(
			'list-' . $post_type . 's',
			'List ' . strtolower( $label ) . 's',
			'Returns editable ' . strtolower( $label ) . ' records visible to the current user, with bounded paging and optional search/status filters.',
			$list_schema,
			function ( $input ) use ( $post_type ) { return $this->content->list_items( $post_type, is_array( $input ) ? $input : array() ); },
			$edit_capability,
			true,
			false,
			true
		);
		$this->register_ability(
			'get-' . $post_type,
			'Get ' . strtolower( $label ),
			'Returns one editable ' . strtolower( $label ) . ' including its content and conflict-check timestamp.',
			$get_schema,
			function ( $input ) use ( $post_type ) { return $this->content->get_item( $post_type, $input['id'] ); },
			$edit_capability,
			true,
			false,
			true
		);
		$this->register_ability(
			'create-' . $post_type . '-draft',
			'Create ' . strtolower( $label ) . ' draft',
			'Creates a new draft ' . strtolower( $label ) . ' only. This ability never publishes content.',
			$create_schema,
			function ( $input ) use ( $post_type ) { return $this->content->create_draft( $post_type, $input ); },
			$edit_capability,
			false,
			false,
			false
		);
		$this->register_ability(
			'update-' . $post_type,
			'Update ' . strtolower( $label ),
			'Updates title/content/excerpt only after an exact modified-time conflict check and establishes a native WordPress revision rollback point first.',
			$update_schema,
			function ( $input ) use ( $post_type ) { return $this->content->update_item( $post_type, $input ); },
			$edit_capability,
			false,
			true,
			false
		);
		$this->register_ability(
			'trash-' . $post_type,
			'Trash ' . strtolower( $label ),
			'Moves one ' . strtolower( $label ) . ' to WordPress trash after an exact modified-time conflict check. It does not permanently delete content.',
			$state_schema,
			function ( $input ) use ( $post_type ) { return $this->content->trash_item( $post_type, $input ); },
			$delete_capability,
			false,
			true,
			false
		);
		$this->register_ability(
			'restore-' . $post_type,
			'Restore ' . strtolower( $label ),
			'Restores one ' . strtolower( $label ) . ' from WordPress trash after an exact modified-time conflict check.',
			$state_schema,
			function ( $input ) use ( $post_type ) { return $this->content->restore_item( $post_type, $input ); },
			$delete_capability,
			false,
			false,
			false
		);
		$this->register_ability(
			'restore-' . $post_type . '-revision',
			'Restore ' . strtolower( $label ) . ' revision',
			'Restores a revision belonging to the target ' . strtolower( $label ) . ' after conflict checking and creates a rollback revision for the pre-restore state.',
			$revision_schema,
			function ( $input ) use ( $post_type ) { return $this->content->restore_revision( $post_type, $input ); },
			$edit_capability,
			false,
			true,
			false
		);
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
