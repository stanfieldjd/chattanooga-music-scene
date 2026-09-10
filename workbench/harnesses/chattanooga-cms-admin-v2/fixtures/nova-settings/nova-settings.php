<?php
/**
 * Plugin Name: Nova Settings Fixture
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function () {
		register_setting(
			'nova_private_group',
			'nova_private_setting',
			array(
				'type'              => 'string',
				'label'             => 'Nova Private Setting',
				'description'       => 'Non-REST Settings API fixture.',
				'sanitize_callback' => static function ( $value ) {
					return strtoupper( trim( (string) $value ) );
				},
				'default'           => 'NOVA-DEFAULT',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'nova_rest_group',
			'nova_rest_setting',
			array(
				'type'              => 'string',
				'label'             => 'Nova REST Setting',
				'description'       => 'REST-visible Settings API fixture.',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'rest-default',
				'show_in_rest'      => true,
			)
		);

		register_setting(
			'nova_custom_group',
			'nova_custom_setting',
			array(
				'type'              => 'integer',
				'label'             => 'Nova Custom Capability Setting',
				'description'       => 'Settings API fixture protected by a custom option-page capability.',
				'sanitize_callback' => static function ( $value ) {
					return max( 0, min( 100, (int) $value ) );
				},
				'default'           => 10,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'nova_absent_group',
			'nova_absent_setting',
			array(
				'type'              => 'string',
				'label'             => 'Nova Absent Setting',
				'description'       => 'Registered default exists while the option row is absent.',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'DEFAULT-NOVA',
				'show_in_rest'      => false,
			)
		);
	},
	1
);

add_filter(
	'option_page_capability_nova_custom_group',
	static function () {
		return 'edit_nova_settings';
	}
);

add_action(
	'updated_option',
	static function ( $option, $old_value, $value ) {
		if ( 'nova_private_setting' === $option && 'TRIGGER-VERIFY-FAIL' === $value ) {
			$GLOBALS['nova_settings_corrupt_next_read'] = true;
		}
	},
	10,
	3
);

add_filter(
	'option_nova_private_setting',
	static function ( $value ) {
		if ( ! empty( $GLOBALS['nova_settings_corrupt_next_read'] ) ) {
			unset( $GLOBALS['nova_settings_corrupt_next_read'] );
			return 'CORRUPTED-READBACK';
		}
		return $value;
	}
);
