<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Theme_Updater {
	const CATEGORY = 'chattanooga-cms-admin';
	const ABILITY = 'chattanooga-cms-admin/update-theme';

	public static function register_ability() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::ABILITY,
			array(
				'label'               => __( 'Update installed theme', 'chattanooga-cms-admin' ),
				'description'         => __( 'Updates one installed theme from the current WordPress update offer with exact-version conflict control, temporary-backup rollback, and active-theme verification.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'stylesheet' => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 255,
						),
						'expected_version' => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 64,
						),
					),
					'required'             => array( 'stylesheet', 'expected_version' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'update_theme' ),
				'permission_callback' => static function () {
					return current_user_can( 'update_themes' );
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
	}

	public static function update_theme( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'cmsa_invalid_input', 'Theme update input must be an object.' );
		}

		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

		$stylesheet = isset( $input['stylesheet'] ) ? trim( (string) $input['stylesheet'] ) : '';
		$expected_version = isset( $input['expected_version'] ) ? trim( (string) $input['expected_version'] ) : '';
		if ( '' === $stylesheet || '' === $expected_version || 0 !== validate_file( $stylesheet ) ) {
			return new WP_Error( 'cmsa_invalid_theme', 'A valid installed theme stylesheet and expected version are required.' );
		}

		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'cmsa_theme_not_found', 'The requested theme is not installed.' );
		}

		$current_version = (string) $theme->get( 'Version' );
		if ( $current_version !== $expected_version ) {
			return new WP_Error(
				'cmsa_theme_version_conflict',
				'The installed theme version changed before the update could begin.',
				array( 'current_version' => $current_version )
			);
		}

		$updates = get_site_transient( 'update_themes' );
		if ( ! is_object( $updates ) || empty( $updates->response[ $stylesheet ] ) || ! is_array( $updates->response[ $stylesheet ] ) ) {
			return new WP_Error( 'cmsa_theme_update_unavailable', 'No current WordPress update offer exists for this theme. Refresh update inventory first.' );
		}

		$offer = $updates->response[ $stylesheet ];
		$new_version = isset( $offer['new_version'] ) ? trim( (string) $offer['new_version'] ) : '';
		$package = isset( $offer['package'] ) ? trim( (string) $offer['package'] ) : '';
		if ( '' === $new_version || '' === $package ) {
			return new WP_Error( 'cmsa_theme_update_incomplete', 'The current WordPress update offer does not contain a version and package.' );
		}

		$theme_root = get_theme_root( $stylesheet );
		$backup = array(
			'slug' => $stylesheet,
			'src'  => $theme_root,
			'dir'  => 'themes',
		);
		$active_stylesheet = get_stylesheet();
		$active_template = get_template();
		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result = $upgrader->upgrade( $stylesheet, array( 'clear_update_cache' => false ) );

		wp_clean_themes_cache();
		$after_theme = wp_get_theme( $stylesheet );
		$after_version = $after_theme->exists() ? (string) $after_theme->get( 'Version' ) : '';

		if ( true !== $result ) {
			$rollback = self::restore_theme_transaction_state(
				$upgrader,
				$backup,
				$stylesheet,
				$current_version,
				$active_stylesheet,
				$active_template,
				$after_version !== $current_version
			);
			if ( is_wp_error( $rollback ) ) {
				return $rollback;
			}
			return new WP_Error(
				'cmsa_theme_update_failed',
				is_wp_error( $result ) ? $result->get_error_message() : 'WordPress did not complete the theme update.',
				array( 'previous_version' => $current_version, 'rolled_back' => true )
			);
		}

		if ( $after_version !== $new_version || get_stylesheet() !== $active_stylesheet || get_template() !== $active_template ) {
			$observed_stylesheet = get_stylesheet();
			$observed_template = get_template();
			$rollback = self::restore_theme_transaction_state(
				$upgrader,
				$backup,
				$stylesheet,
				$current_version,
				$active_stylesheet,
				$active_template,
				true
			);
			if ( is_wp_error( $rollback ) ) {
				return $rollback;
			}
			return new WP_Error(
				'cmsa_theme_update_verification_failed',
				'The theme update did not match the offered version or preserve the active theme identity; the previous state was restored.',
				array(
					'expected_new_version' => $new_version,
					'observed_version'     => $after_version,
					'observed_stylesheet'  => $observed_stylesheet,
					'observed_template'    => $observed_template,
				)
			);
		}

		return array(
			'stylesheet'       => $stylesheet,
			'previous_version' => $current_version,
			'version'          => $after_version,
			'active'           => get_stylesheet() === $stylesheet,
			'active_stylesheet'=> get_stylesheet(),
			'active_template'  => get_template(),
			'rollback'         => 'wordpress_temp_backup',
		);
	}

	private static function restore_theme_transaction_state( Theme_Upgrader $upgrader, array $backup, $stylesheet, $version, $active_stylesheet, $active_template, $restore_files ) {
		if ( $restore_files ) {
			$restore = $upgrader->restore_temp_backup( array( $backup ) );
			if ( is_wp_error( $restore ) || true !== $restore ) {
				return new WP_Error( 'cmsa_theme_update_rollback_failed', 'Theme update failed and WordPress could not restore the temporary backup.' );
			}
		}

		wp_clean_themes_cache();
		$theme = wp_get_theme( $stylesheet );
		$restored_version = $theme->exists() ? (string) $theme->get( 'Version' ) : '';
		if ( $restored_version !== $version ) {
			return new WP_Error(
				'cmsa_theme_update_rollback_failed',
				'Theme rollback did not restore the expected previous version.',
				array( 'restored_version' => $restored_version )
			);
		}

		if ( get_stylesheet() !== $active_stylesheet || get_template() !== $active_template ) {
			$prior_theme = wp_get_theme( $active_stylesheet );
			if ( ! $prior_theme->exists() ) {
				return new WP_Error( 'cmsa_theme_update_rollback_failed', 'Theme files were restored but the previously active theme no longer exists.' );
			}
			switch_theme( $active_stylesheet );
			wp_clean_themes_cache();
		}

		if ( get_stylesheet() !== $active_stylesheet || get_template() !== $active_template ) {
			return new WP_Error( 'cmsa_theme_update_rollback_failed', 'Theme rollback could not restore the prior active theme identity.' );
		}

		return true;
	}
}
