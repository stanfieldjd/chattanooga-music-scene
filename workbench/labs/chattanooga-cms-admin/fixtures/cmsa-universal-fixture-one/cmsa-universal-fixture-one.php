<?php
/**
 * Plugin Name: CMSA Universal Fixture One
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_categories_init',
	static function () {
		wp_register_ability_category(
			'fixture-one',
			array(
				'label'       => 'Fixture One',
				'description' => 'Disposable universal-control-plane fixture.',
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	static function () {
		wp_register_ability(
			'fixture-one/read',
			array(
				'label'               => 'Fixture one read',
				'description'         => 'Public disposable read ability.',
				'category'            => 'fixture-one',
				'execute_callback'    => static function () { return array( 'fixture' => 'one' ); },
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
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
			'fixture-one/private',
			array(
				'label'               => 'Fixture one private',
				'description'         => 'Private disposable ability that must not appear in the universal catalog.',
				'category'            => 'fixture-one',
				'execute_callback'    => static function () { return true; },
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => array(
					'public'       => false,
					'show_in_rest' => false,
				),
			)
		);
	}
);
