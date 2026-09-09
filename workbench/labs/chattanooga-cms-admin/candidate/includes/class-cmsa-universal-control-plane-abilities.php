<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Universal_Control_Plane_Abilities {
	private $control_plane;

	public function __construct( CMSA_Universal_Control_Plane $control_plane ) {
		$this->control_plane = $control_plane;
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
	}
}
