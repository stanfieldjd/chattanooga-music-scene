<?php
/**
 * Plugin Name: Quasar Audit Fixture
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_categories_init',
	static function () {
		wp_register_ability_category(
			'quasar-audit',
			array(
				'label'       => 'Quasar Audit Fixture',
				'description' => 'Disposable v2 audit provider.',
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	static function () {
		wp_register_ability(
			'quasar-audit/process',
			array(
				'label'               => 'Process audit marker',
				'description'         => 'Disposable provider used to verify universal execution auditing without retaining raw input.',
				'category'            => 'quasar-audit',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'secret' => array( 'type' => 'string', 'minLength' => 1 ),
						'fail'   => array( 'type' => 'boolean', 'default' => false ),
					),
					'required'             => array( 'secret' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function ( $input ) {
					if ( ! empty( $input['fail'] ) ) {
						return new WP_Error( 'quasar_fixture_failure', 'Intentional disposable audit failure.' );
					}
					return array( 'processed' => true, 'length' => strlen( (string) $input['secret'] ) );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'public' => true,
					'annotations' => array(
						'readonly' => false,
						'destructive' => false,
						'idempotent' => true,
					),
				),
			)
		);
	}
);
