<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Weekend_Feature_Abilities {
	private $weekend;

	public function __construct( CMSA_Weekend_Feature $weekend ) {
		$this->weekend = $weekend;
	}

	public function register() {
		$this->register_ability(
			'get-weekend-feature-status',
			'Get Weekend Feature status',
			'Returns the bounded Chattanooga Weekend Feature settings, schedule, current Friday-Sunday window, event count, and exact current-feature state.',
			$this->object_schema( array() ),
			function () { return $this->weekend->get_status(); },
			'publish_posts',
			true,
			false,
			true
		);

		$this->register_ability(
			'update-weekend-feature-settings',
			'Update Weekend Feature settings',
			'Replaces the complete Weekend Feature settings only when the exact settings/schedule state still matches, then verifies the source plugin schedule and rolls back on failed verification.',
			$this->object_schema(
				array(
					'expected_settings_state_token' => array( 'type' => 'string', 'minLength' => 1 ),
					'enabled'                       => array( 'type' => 'boolean' ),
					'publish_time'                  => array( 'type' => 'string', 'maxLength' => 5 ),
					'post_author'                   => array( 'type' => 'integer', 'minimum' => 0 ),
					'introduction'                  => array( 'type' => 'string' ),
					'closing'                       => array( 'type' => 'string' ),
				),
				array( 'expected_settings_state_token', 'enabled', 'publish_time', 'post_author', 'introduction', 'closing' )
			),
			function ( $input ) { return $this->weekend->update_settings( $input ); },
			'manage_options',
			false,
			false,
			false
		);

		$generation_schema = $this->object_schema(
			array(
				'expected_week_key'            => array( 'type' => 'string', 'minLength' => 10, 'maxLength' => 10 ),
				'expected_feature_id'          => array( 'type' => 'integer', 'minimum' => 0 ),
				'expected_feature_state_token' => array( 'type' => 'string', 'default' => '' ),
			),
			array( 'expected_week_key', 'expected_feature_id', 'expected_feature_state_token' )
		);

		$this->register_ability(
			'generate-weekend-feature-draft',
			'Generate Weekend Feature draft',
			'Creates or refreshes only the current Weekend Feature draft through the source plugin after exact weekend/feature conflict checks, with readback verification and rollback.',
			$generation_schema,
			function ( $input ) { return $this->weekend->generate( $input, 'draft' ); },
			'publish_posts',
			false,
			false,
			false
		);

		$this->register_ability(
			'publish-weekend-feature-now',
			'Publish Weekend Feature now',
			'Publishes the current Weekend Feature through the source plugin after exact weekend/feature conflict checks. Live execution is an external publication effect and requires separate authorization.',
			$generation_schema,
			function ( $input ) { return $this->weekend->generate( $input, 'publish' ); },
			'publish_posts',
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
