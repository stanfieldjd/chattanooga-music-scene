<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Update_Policy {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		self::register_policy_ability( 'plugin' );
		self::register_policy_ability( 'theme' );
	}

	public static function set_plugin_auto_update( $input ) {
		return self::set_policy( 'plugin', $input );
	}

	public static function set_theme_auto_update( $input ) {
		return self::set_policy( 'theme', $input );
	}

	private static function register_policy_ability( $type ) {
		$is_plugin = 'plugin' === $type;
		$key = $is_plugin ? 'plugin' : 'stylesheet';
		$name = self::PREFIX . 'set-' . $type . '-auto-update';
		wp_register_ability(
			$name,
			array(
				'label'               => $is_plugin ? __( 'Set plugin auto-update policy', 'chattanooga-cms-admin' ) : __( 'Set theme auto-update policy', 'chattanooga-cms-admin' ),
				'description'         => $is_plugin ? __( 'Enables or disables WordPress automatic updates for one installed plugin and verifies the stored core policy.', 'chattanooga-cms-admin' ) : __( 'Enables or disables WordPress automatic updates for one installed theme and verifies the stored core policy.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						$key => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
						'enabled' => array( 'type' => 'boolean' ),
					),
					'required'             => array( $key, 'enabled' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => $is_plugin ? array( __CLASS__, 'set_plugin_auto_update' ) : array( __CLASS__, 'set_theme_auto_update' ),
				'permission_callback' => static function () use ( $is_plugin ) { return current_user_can( $is_plugin ? 'update_plugins' : 'update_themes' ); },
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);
	}

	private static function set_policy( $type, $input ) {
		if ( ! is_array( $input ) || ! array_key_exists( 'enabled', $input ) ) {
			return new WP_Error( 'cmsa_invalid_input', 'Update policy input must contain an explicit enabled boolean.' );
		}

		$is_plugin = 'plugin' === $type;
		$key = $is_plugin ? 'plugin' : 'stylesheet';
		$asset = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
		if ( '' === $asset || 0 !== validate_file( $asset ) ) {
			return new WP_Error( 'cmsa_invalid_asset', 'A valid installed component identity is required.' );
		}

		if ( $is_plugin ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$installed = get_plugins();
		} else {
			$installed = wp_get_themes();
		}
		if ( ! array_key_exists( $asset, $installed ) ) {
			return new WP_Error( 'cmsa_asset_not_found', 'The requested component is not installed.' );
		}

		$option = $is_plugin ? 'auto_update_plugins' : 'auto_update_themes';
		$before = array_values( array_unique( array_map( 'strval', (array) get_site_option( $option, array() ) ) ) );
		$enabled = (bool) $input['enabled'];
		$after = $before;
		if ( $enabled ) {
			$after[] = $asset;
			$after = array_values( array_unique( $after ) );
		} else {
			$after = array_values( array_diff( $after, array( $asset ) ) );
		}

		$valid_assets = array_keys( $installed );
		$after = array_values( array_intersect( $after, $valid_assets ) );
		if ( $after !== $before ) {
			update_site_option( $option, $after );
		}

		$stored = array_values( array_unique( array_map( 'strval', (array) get_site_option( $option, array() ) ) ) );
		$stored_enabled = in_array( $asset, $stored, true );
		if ( $stored_enabled !== $enabled ) {
			if ( $before !== $stored ) {
				update_site_option( $option, $before );
			}
			return new WP_Error( 'cmsa_auto_update_policy_verification_failed', 'The requested automatic-update policy did not persist; the previous policy was restored.' );
		}

		return array(
			$type       => $asset,
			'enabled'   => $stored_enabled,
			'changed'   => $before !== $stored,
			'option'    => $option,
		);
	}
}
