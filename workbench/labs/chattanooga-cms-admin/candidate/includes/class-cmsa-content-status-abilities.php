<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Content_Status_Abilities {
	private $status;

	public function __construct( CMSA_Content_Status $status ) {
		$this->status = $status;
	}

	public function register() {
		$this->register_type( 'post', 'Post', 'edit_posts' );
		$this->register_type( 'page', 'Page', 'edit_pages' );
	}

	private function register_type( $post_type, $label, $capability ) {
		$schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'id'                    => array( 'type' => 'integer', 'minimum' => 1 ),
				'expected_modified_gmt' => array( 'type' => 'string', 'minLength' => 1 ),
				'expected_status'       => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private', 'future' ) ),
				'status'                => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private', 'future' ) ),
				'date_gmt'              => array( 'type' => 'string', 'default' => '' ),
			),
			'required'             => array( 'id', 'expected_modified_gmt', 'expected_status', 'status' ),
			'additionalProperties' => false,
		);

		wp_register_ability(
			'chattanooga-cms-admin/set-' . $post_type . '-status',
			array(
				'label'               => __( 'Set ' . strtolower( $label ) . ' status', 'chattanooga-cms-admin' ),
				'description'         => __( 'Changes one ' . strtolower( $label ) . ' between draft, pending, published, private, and scheduled states after exact status/timestamp conflict checks. Publishing, private, and scheduling require the corresponding WordPress publish capability.', 'chattanooga-cms-admin' ),
				'category'            => 'chattanooga-cms-admin',
				'input_schema'        => $schema,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => function ( $input ) use ( $post_type ) {
					return $this->status->set_status( $post_type, $input );
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
