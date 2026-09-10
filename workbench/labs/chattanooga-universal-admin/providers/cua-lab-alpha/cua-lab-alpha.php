<?php
/**
 * Plugin Name: CUA Lab Alpha
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_categories_init',
	static function () {
		wp_register_ability_category(
			'cua-lab-alpha',
			array(
				'label'       => 'CUA Lab Alpha',
				'description' => 'Disposable capability provider used only by the engineering test harness.',
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	static function () {
		wp_register_ability(
			'cua-lab-alpha/read-record',
			array(
				'label'               => 'Read alpha record',
				'description'         => 'Returns a disposable test record.',
				'category'            => 'cua-lab-alpha',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function ( $input ) {
					return array(
						'id'     => (int) $input['id'],
						'source' => 'alpha',
					);
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
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

		wp_register_ability(
			'cua-lab-alpha/core-public-record',
			array(
				'label'               => 'Core-public alpha record',
				'description'         => 'Public WordPress ability with no channel-specific MCP metadata and no input.',
				'category'            => 'cua-lab-alpha',
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function () {
					return array( 'source' => 'alpha-core-public' );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'cua-lab-alpha/mutate-plugin-reference',
			array(
				'label'               => 'Mutate referenced plugin fixture state',
				'description'         => 'Disposable mutating provider ability used to verify universal control-plane self-protection.',
				'category'            => 'cua-lab-alpha',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin' => array( 'type' => 'string', 'minLength' => 1 ),
					),
					'required'             => array( 'plugin' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function ( $input ) {
					$count = (int) get_option( 'cua_lab_alpha_mutation_count', 0 ) + 1;
					update_option( 'cua_lab_alpha_mutation_count', $count, false );
					return array( 'plugin' => (string) $input['plugin'], 'count' => $count );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
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

		wp_register_ability(
			'cua-lab-alpha/private-record',
			array(
				'label'               => 'Private alpha record',
				'description'         => 'Private control ability that must never be bridged.',
				'category'            => 'cua-lab-alpha',
				'execute_callback'    => static function () { return true; },
				'permission_callback' => static function () { return true; },
				'meta'                => array(
					'public'       => false,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => false ),
				),
			)
		);
	}
);
