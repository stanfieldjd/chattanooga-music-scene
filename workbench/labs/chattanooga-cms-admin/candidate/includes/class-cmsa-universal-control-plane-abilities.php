<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Universal_Control_Plane_Abilities {
	private $control_plane;
	private $resource_reader;
	private $post_writer;

	public function __construct( CMSA_Universal_Control_Plane $control_plane, CMSA_Universal_Resource_Reader $resource_reader, CMSA_Universal_Post_Resource_Writer $post_writer ) {
		$this->control_plane   = $control_plane;
		$this->resource_reader = $resource_reader;
		$this->post_writer     = $post_writer;
	}

	public function register() {
		wp_register_ability(
			'chattanooga-cms-admin/inspect-extension-capabilities',
			array(
				'label'       => __( 'Inspect extension capabilities', 'chattanooga-cms-admin' ),
				'description' => __( 'Discovers public native WordPress Abilities API capabilities registered by core or extensions without requiring a Chattanooga CMS Admin adapter for each plugin. This ability does not proxy execution.', 'chattanooga-cms-admin' ),
				'category'    => 'chattanooga-cms-admin',
				'input_schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'name'      => array( 'type' => 'string', 'default' => '' ),
						'namespace' => array( 'type' => 'string', 'default' => '' ),
						'category'  => array( 'type' => 'string', 'default' => '' ),
						'search'    => array( 'type' => 'string', 'default' => '' ),
						'page'      => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
						'per_page'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ),
					),
					'additionalProperties' => false,
				),
				'output_schema' => array( 'type' => 'object' ),
				'execute_callback' => function ( $input ) {
					return $this->control_plane->inspect( is_array( $input ) ? $input : array() );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta' => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'chattanooga-cms-admin/inspect-resource-registry',
			array(
				'label'       => __( 'Inspect WordPress resource registry', 'chattanooga-cms-admin' ),
				'description' => __( 'Discovers administratively exposed WordPress post types or taxonomies through their standard registries without plugin-name-specific adapters. This ability is read-only and does not mutate registered resources.', 'chattanooga-cms-admin' ),
				'category'    => 'chattanooga-cms-admin',
				'input_schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'kind' => array(
							'type' => 'string',
							'enum' => array( 'post_type', 'taxonomy' ),
						),
						'name'     => array( 'type' => 'string', 'default' => '' ),
						'search'   => array( 'type' => 'string', 'default' => '' ),
						'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ),
					),
					'required'             => array( 'kind' ),
					'additionalProperties' => false,
				),
				'output_schema' => array( 'type' => 'object' ),
				'execute_callback' => function ( $input ) {
					return $this->control_plane->inspect_resource_registry( is_array( $input ) ? $input : array() );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta' => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'chattanooga-cms-admin/read-standard-resource',
			array(
				'label'       => __( 'Read standard WordPress resource', 'chattanooga-cms-admin' ),
				'description' => __( 'Reads bounded records from an administratively exposed WordPress post type or taxonomy through the standard WordPress object and capability model. This ability does not read arbitrary metadata or mutate resources.', 'chattanooga-cms-admin' ),
				'category'    => 'chattanooga-cms-admin',
				'input_schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'kind' => array(
							'type' => 'string',
							'enum' => array( 'post_type', 'taxonomy' ),
						),
						'resource' => array( 'type' => 'string', 'minLength' => 1 ),
						'operation' => array(
							'type' => 'string',
							'enum' => array( 'list', 'get' ),
						),
						'id'       => array( 'type' => 'integer', 'minimum' => 1 ),
						'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'status'   => array( 'type' => 'string', 'default' => 'any' ),
						'search'   => array( 'type' => 'string', 'default' => '' ),
					),
					'required'             => array( 'kind', 'resource', 'operation' ),
					'additionalProperties' => false,
				),
				'output_schema' => array( 'type' => 'object' ),
				'execute_callback' => function ( $input ) {
					return $this->resource_reader->read( is_array( $input ) ? $input : array() );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta' => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'chattanooga-cms-admin/write-standard-post-resource',
			array(
				'label'       => __( 'Write standard WordPress post resource', 'chattanooga-cms-admin' ),
				'description' => __( 'Creates drafts or updates revision-backed fields for WordPress posts and pages through their standard REST controllers with exact-state conflict control, readback verification, and rollback. Plugin post types, taxonomies, metadata, status changes, and arbitrary REST fields are not admitted.', 'chattanooga-cms-admin' ),
				'category'    => 'chattanooga-cms-admin',
				'input_schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'resource' => array(
							'type' => 'string',
							'enum' => array( 'post', 'page' ),
						),
						'operation' => array(
							'type' => 'string',
							'enum' => array( 'create_draft', 'update' ),
						),
						'id'                   => array( 'type' => 'integer', 'minimum' => 1 ),
						'expected_state_token' => array( 'type' => 'string' ),
						'title'                => array( 'type' => 'string' ),
						'content'              => array( 'type' => 'string' ),
						'excerpt'              => array( 'type' => 'string' ),
						'slug'                 => array( 'type' => 'string' ),
						'parent_id'            => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'required'             => array( 'resource', 'operation' ),
					'additionalProperties' => false,
				),
				'output_schema' => array( 'type' => 'object' ),
				'execute_callback' => function ( $input ) {
					return $this->post_writer->write( is_array( $input ) ? $input : array() );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta' => array(
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
}
