<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Member_Mutation_Abilities {
	private $mutations;

	public function __construct( CMSA_Member_Mutations $mutations ) {
		$this->mutations = $mutations;
	}

	public function register() {
		$this->register_ability(
			'update-member-profile',
			'Update member profile',
			'Updates selected core member profile fields after exact profile-state validation. Password and arbitrary metadata operations are outside this ability.',
			$this->object_schema(
				array(
					'id'                     => array( 'type' => 'integer', 'minimum' => 1 ),
					'expected_profile_state' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
					'email'                  => array( 'type' => 'string' ),
					'display_name'           => array( 'type' => 'string' ),
					'url'                    => array( 'type' => 'string' ),
				),
				array( 'id', 'expected_profile_state' )
			),
			function ( $input ) { return $this->mutations->update_profile( is_array( $input ) ? $input : array() ); },
			'edit_users'
		);

		$this->register_ability(
			'set-member-roles',
			'Set member roles',
			'Replaces another member account role set after exact role-state validation, editable-role checks, readback verification, and rollback on verification failure.',
			$this->object_schema(
				array(
					'id'                   => array( 'type' => 'integer', 'minimum' => 1 ),
					'expected_roles_state' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
					'roles'                => array(
						'type'     => 'array',
						'minItems' => 1,
						'items'    => array( 'type' => 'string' ),
					),
				),
				array( 'id', 'expected_roles_state', 'roles' )
			),
			function ( $input ) { return $this->mutations->set_roles( is_array( $input ) ? $input : array() ); },
			'promote_users'
		);
	}

	private function register_ability( $slug, $label, $description, $input_schema, $callback, $capability ) {
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
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
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
