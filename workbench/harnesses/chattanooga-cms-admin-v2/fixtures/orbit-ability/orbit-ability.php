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
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'public' => true,
					'annotations' => array(
						'readonly' => true,
						'destructive' => false,
						'idempotent' => true,
					),
				),
			)
		);
	}
);