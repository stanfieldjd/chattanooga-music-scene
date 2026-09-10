<?php
/**
 * Plugin Name: CUA Lab Beta
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_categories_init',
	static function () {
		wp_register_ability_category(
			'cua-lab-beta',
			array(
				'label'       => 'CUA Lab Beta',
				'description' => 'Disposable mutation capability provider used only by the engineering test harness.',
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	static function () {
		wp_register_ability(
			'cua-lab-beta/set-flag',
			array(
				'label'               => 'Set beta flag',
				'description'         => 'Changes a disposable test flag through the provider-owned ability.',
				'category'            => 'cua-lab-beta',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'value' => array( 'type' => 'boolean' ),
					),
					'required'             => array( 'value' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function ( $input ) {
					update_option( 'cua_lab_beta_flag', (bool) $input['value'], false );
					return array( 'value' => (bool) get_option( 'cua_lab_beta_flag', false ) );
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
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'cua-lab-beta/denied',
			array(
				'label'               => 'Denied beta action',
				'description'         => 'Public control ability that always denies execution.',
				'category'            => 'cua-lab-beta',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function () { return array( 'executed' => true ); },
				'permission_callback' => static function () { return false; },
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
);
