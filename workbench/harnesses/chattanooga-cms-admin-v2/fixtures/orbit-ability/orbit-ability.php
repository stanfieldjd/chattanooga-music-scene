<?php
/**
 * Plugin Name: Orbit Ability Fixture
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_categories_init',
	static function () {
		wp_register_ability_category(
			'orbit-fixture',
			array(
				'label'       => 'Orbit Fixture',
				'description' => 'Disposable v2 Ability provider.',
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	static function () {
		$permission = static function () {
			return current_user_can( 'manage_options' );
		};
		$readonly = array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);

		wp_register_ability(
			'orbit-fixture/read-marker',
			array(
				'label'               => 'Read marker',
				'description'         => 'Returns a deterministic marker from a public provider Ability.',
				'category'            => 'orbit-fixture',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'value' => array( 'type' => 'integer' ),
					),
					'required'             => array( 'value' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function ( $input ) {
					return array( 'marker' => (int) $input['value'] + 7 );
				},
				'permission_callback' => $permission,
				'meta'                => array(
					'public'      => true,
					'annotations' => $readonly,
				),
			)
		);

		wp_register_ability(
			'orbit-fixture/mcp-only',
			array(
				'label'               => 'MCP-only marker',
				'description'         => 'Uses explicit MCP exposure while the general public flag remains false.',
				'category'            => 'orbit-fixture',
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function () {
					return array( 'marker' => 'mcp-only' );
				},
				'permission_callback' => $permission,
				'meta'                => array(
					'public'       => false,
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
					'annotations'  => $readonly,
				),
			)
		);

		wp_register_ability(
			'orbit-fixture/mcp-opt-out',
			array(
				'label'               => 'MCP opt-out marker',
				'description'         => 'Is generally public but explicitly opts out of MCP exposure.',
				'category'            => 'orbit-fixture',
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function () {
					return array( 'marker' => 'mcp-opt-out' );
				},
				'permission_callback' => $permission,
				'meta'                => array(
					'public'      => true,
					'mcp'         => array( 'public' => false ),
					'annotations' => $readonly,
				),
			)
		);

		wp_register_ability(
			'orbit-fixture/rest-only',
			array(
				'label'               => 'REST-only marker',
				'description'         => 'Uses REST exposure without general or MCP exposure.',
				'category'            => 'orbit-fixture',
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function () {
					return array( 'marker' => 'rest-only' );
				},
				'permission_callback' => $permission,
				'meta'                => array(
					'public'       => false,
					'show_in_rest' => true,
					'annotations'  => $readonly,
				),
			)
		);
	}
);
