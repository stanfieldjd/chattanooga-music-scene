<?php
/**
 * Plugin Name: CMSA Universal Fixture Two
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function () {
		register_taxonomy(
			'fixture_two_label',
			array( 'post' ),
			array(
				'label'        => 'Fixture two labels',
				'description'  => 'Disposable administratively exposed taxonomy.',
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'hierarchical' => false,
			)
		);

		register_taxonomy(
			'fixture_two_hidden',
			array( 'post' ),
			array(
				'label'        => 'Fixture two hidden',
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
			)
		);
	}
);

add_action(
	'wp_abilities_api_categories_init',
	static function () {
		wp_register_ability_category(
			'fixture-two',
			array(
				'label'       => 'Fixture Two',
				'description' => 'Second disposable universal-control-plane fixture.',
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	static function () {
		wp_register_ability(
			'fixture-two/write',
			array(
				'label'               => 'Fixture two write',
				'description'         => 'Public disposable mutation ability used only to verify discovery does not proxy execution.',
				'category'            => 'fixture-two',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'value' => array( 'type' => 'string' ),
					),
					'required'             => array( 'value' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					update_option( 'cmsa_universal_fixture_two_executed', isset( $input['value'] ) ? (string) $input['value'] : 'executed', false );
					return array( 'written' => true );
				},
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
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
