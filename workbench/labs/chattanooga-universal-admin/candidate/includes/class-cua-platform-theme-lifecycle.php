<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Theme_Lifecycle {
	const CATEGORY = 'chattanooga-cms-admin';
	const ABILITY = 'chattanooga-cms-admin/switch-theme';

	public static function register_ability() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::ABILITY,
			array(
				'label'               => __( 'Switch active theme', 'chattanooga-cms-admin' ),
				'description'         => __( 'Switches to an installed WordPress theme after exact current-theme conflict validation and restores the previous theme if verification fails.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'stylesheet' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
						'expected_stylesheet' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
					),
					'required'             => array( 'stylesheet', 'expected_stylesheet' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'switch_theme' ),
				'permission_callback' => static function () { return current_user_can( 'switch_themes' ); },
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
				),
			)
		);
	}

	public static function switch_theme( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'cmsa_invalid_input', 'Theme switch input must be an object.' );
		}
		$stylesheet = isset( $input['stylesheet'] ) ? trim( (string) $input['stylesheet'] ) : '';
		$expected = isset( $input['expected_stylesheet'] ) ? trim( (string) $input['expected_stylesheet'] ) : '';
		if ( '' === $stylesheet || '' === $expected || 0 !== validate_file( $stylesheet ) || 0 !== validate_file( $expected ) ) {
			return new WP_Error( 'cmsa_invalid_theme', 'Valid target and expected theme stylesheets are required.' );
		}

		$current_stylesheet = get_stylesheet();
		$current_template = get_template();
		if ( $current_stylesheet !== $expected ) {
			return new WP_Error(
				'cmsa_theme_state_conflict',
				'The active theme changed before the switch could begin.',
				array( 'current_stylesheet' => $current_stylesheet, 'current_template' => $current_template )
			);
		}

		$target = wp_get_theme( $stylesheet );
		if ( ! $target->exists() ) {
			return new WP_Error( 'cmsa_theme_not_found', 'The requested theme is not installed.' );
		}
		$errors = $target->errors();
		if ( is_wp_error( $errors ) && $errors->has_errors() ) {
			return new WP_Error( 'cmsa_theme_invalid', $errors->get_error_message() );
		}

		if ( $stylesheet === $current_stylesheet ) {
			return array(
				'previous_stylesheet' => $current_stylesheet,
				'stylesheet'          => $current_stylesheet,
				'template'            => $current_template,
				'changed'             => false,
			);
		}

		switch_theme( $stylesheet );
		wp_clean_themes_cache();
		if ( get_stylesheet() !== $stylesheet ) {
			switch_theme( $current_stylesheet );
			wp_clean_themes_cache();
			if ( get_stylesheet() !== $current_stylesheet || get_template() !== $current_template ) {
				return new WP_Error( 'cmsa_theme_switch_rollback_failed', 'Theme switch failed and the previous active theme could not be restored.' );
			}
			return new WP_Error( 'cmsa_theme_switch_failed', 'The requested theme did not become active; the previous theme was restored.' );
		}

		return array(
			'previous_stylesheet' => $current_stylesheet,
			'stylesheet'          => get_stylesheet(),
			'template'            => get_template(),
			'changed'             => true,
		);
	}
}
