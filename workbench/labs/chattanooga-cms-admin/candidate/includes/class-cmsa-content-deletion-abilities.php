<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Content_Deletion_Abilities {
	private $deletion;

	public function __construct( CMSA_Content_Deletion $deletion ) {
		$this->deletion = $deletion;
	}

	public function register() {
		$this->register_type( 'post', 'Post', 'delete_posts' );
		$this->register_type( 'page', 'Page', 'delete_pages' );
	}

	private function register_type( $post_type, $label, $capability ) {
		$schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'id'                       => array( 'type' => 'integer', 'minimum' => 1 ),
				'expected_modified_gmt'    => array( 'type' => 'string', 'minLength' => 1 ),
				'confirm_permanent_delete' => array( 'type' => 'boolean' ),
			),
			'required'             => array( 'id', 'expected_modified_gmt', 'confirm_permanent_delete' ),
			'additionalProperties' => false,
		);

		wp_register_ability(
			'chattanooga-cms-admin/delete-' . $post_type . '-permanently',
			array(
				'label'               => __( 'Permanently delete ' . strtolower( $label ), 'chattanooga-cms-admin' ),
				'description'         => __( 'Permanently deletes one already-trashed ' . strtolower( $label ) . ' after an exact modified-time conflict check and explicit destructive confirmation. This operation is not restorable through WordPress trash.', 'chattanooga-cms-admin' ),
				'category'            => 'chattanooga-cms-admin',
				'input_schema'        => $schema,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => function ( $input ) use ( $post_type ) {
					return $this->deletion->delete_permanently( $post_type, is_array( $input ) ? $input : array() );
				},
				'permission_callback' => function () use ( $capability ) {
					return current_user_can( $capability );
				},
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);
	}
}
