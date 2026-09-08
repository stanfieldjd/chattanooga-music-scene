<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Navigation_Abilities {
	private $navigation;

	public function __construct( CMSA_Navigation $navigation ) {
		$this->navigation = $navigation;
	}

	public function register() {
		$this->register_ability(
			'list-navigation-menus',
			'List navigation menus',
			'Returns up to 100 core WordPress navigation menus plus registered menu locations and assignments.',
			null,
			array( $this->navigation, 'list_menus' ),
			true,
			false,
			true
		);

		$this->register_ability(
			'get-navigation-menu',
			'Get navigation menu',
			'Returns one core WordPress navigation menu, its bounded normalized items, and an exact state token for conflict-checked mutation.',
			$this->object_schema( array( 'menu_id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'menu_id' ) ),
			function ( $input ) { return $this->navigation->get_menu( $input['menu_id'] ); },
			true,
			false,
			true
		);

		$this->register_ability(
			'create-navigation-menu',
			'Create navigation menu',
			'Creates one empty core WordPress navigation menu and verifies its resulting state.',
			$this->object_schema( array( 'name' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 ) ), array( 'name' ) ),
			function ( $input ) { return $this->navigation->create_menu( $input ); },
			false,
			false,
			false
		);

		$this->register_ability(
			'upsert-navigation-menu-item',
			'Create or update navigation menu item',
			'Creates or updates one core WordPress navigation item after an exact menu-state conflict check. Supports published WordPress pages and bounded custom HTTP/HTTPS or root-relative links.',
			$this->object_schema(
				array(
					'menu_id'             => array( 'type' => 'integer', 'minimum' => 1 ),
					'expected_menu_state' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
					'item_id'              => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
					'type'                 => array( 'type' => 'string', 'enum' => array( 'post_type', 'custom' ) ),
					'object_id'            => array( 'type' => 'integer', 'minimum' => 1 ),
					'url'                  => array( 'type' => 'string' ),
					'title'                => array( 'type' => 'string', 'maxLength' => 200 ),
					'parent_id'            => array( 'type' => 'integer', 'minimum' => 0 ),
					'position'             => array( 'type' => 'integer', 'minimum' => 0 ),
				),
				array( 'menu_id', 'expected_menu_state' )
			),
			function ( $input ) { return $this->navigation->upsert_item( $input ); },
			false,
			false,
			false
		);

		$this->register_ability(
			'delete-navigation-menu-item',
			'Delete navigation menu item',
			'Permanently deletes one core WordPress navigation-menu item after an exact menu-state conflict check and explicit destructive confirmation. The linked page or URL target is not deleted.',
			$this->object_schema(
				array(
					'menu_id'             => array( 'type' => 'integer', 'minimum' => 1 ),
					'item_id'              => array( 'type' => 'integer', 'minimum' => 1 ),
					'expected_menu_state' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
					'confirm_delete'       => array( 'type' => 'boolean' ),
				),
				array( 'menu_id', 'item_id', 'expected_menu_state', 'confirm_delete' )
			),
			function ( $input ) { return $this->navigation->delete_item( $input ); },
			false,
			true,
			false
		);

		$this->register_ability(
			'set-navigation-menu-location',
			'Set navigation menu location',
			'Assigns or unassigns one core WordPress navigation menu to a location registered by the active theme/runtime after an exact assignment-state conflict check.',
			$this->object_schema(
				array(
					'location'                 => array( 'type' => 'string', 'minLength' => 1 ),
					'menu_id'                  => array( 'type' => 'integer', 'minimum' => 0 ),
					'expected_locations_state' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
				),
				array( 'location', 'menu_id', 'expected_locations_state' )
			),
			function ( $input ) { return $this->navigation->set_location( $input ); },
			false,
			false,
			true
		);
	}

	private function register_ability( $slug, $label, $description, $input_schema, $callback, $readonly, $destructive, $idempotent ) {
		$args = array(
			'label'               => __( $label, 'chattanooga-cms-admin' ),
			'description'         => __( $description, 'chattanooga-cms-admin' ),
			'category'            => 'chattanooga-cms-admin',
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => $callback,
			'permission_callback' => static function () {
				return current_user_can( 'edit_theme_options' );
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
		);
		if ( null !== $input_schema ) {
			$args['input_schema'] = $input_schema;
		}
		wp_register_ability( 'chattanooga-cms-admin/' . $slug, $args );
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
