<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Member_Abilities {
	private $members;

	public function __construct( CMSA_Members $members ) {
		$this->members = $members;
	}

	public function register() {
		$list_schema = $this->object_schema(
			array(
				'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
				'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
				'search'   => array( 'type' => 'string', 'default' => '' ),
				'role'     => array( 'type' => 'string', 'default' => '' ),
			)
		);
		$id_schema = $this->object_schema(
			array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
			array( 'id' )
		);

		$this->register_ability(
			'list-members',
			'List members',
			'Returns a bounded list of WordPress member accounts with optional search and role filtering. Passwords, activation keys, session tokens, and arbitrary user metadata are never returned.',
			$list_schema,
			function ( $input ) { return $this->members->list_members( is_array( $input ) ? $input : array() ); }
		);
		$this->register_ability(
			'get-member',
			'Get member',
			'Returns selected core account fields for one WordPress member, including email and assigned roles, without credential or arbitrary usermeta exposure.',
			$id_schema,
			function ( $input ) { return $this->members->get_member( $input['id'] ); }
		);
		$this->register_ability(
			'list-member-roles',
			'List member roles',
			'Returns the WordPress role slugs and names available on this site.',
			$this->object_schema( array() ),
			function () { return $this->members->list_roles(); }
		);
		$this->register_ability(
			'get-member-roles',
			'Get member roles',
			'Returns the role slugs currently assigned to one WordPress member.',
			$id_schema,
			function ( $input ) { return $this->members->get_member_roles( $input['id'] ); }
		);
	}

	private function register_ability( $slug, $label, $description, $input_schema, $callback ) {
		wp_register_ability(
			'chattanooga-cms-admin/' . $slug,
			array(
				'label'               => __( $label, 'chattanooga-cms-admin' ),
				'description'         => __( $description, 'chattanooga-cms-admin' ),
				'category'            => 'chattanooga-cms-admin',
				'input_schema'        => $input_schema,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => $callback,
				'permission_callback' => function () {
					return current_user_can( 'list_users' );
				},
				'meta'                => array(
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
