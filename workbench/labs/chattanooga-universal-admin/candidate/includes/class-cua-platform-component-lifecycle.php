<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Component_Lifecycle {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';
	const TOKEN_TTL = 518400;

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'delete-plugin',
			array(
				'label'               => __( 'Delete installed plugin', 'chattanooga-cms-admin' ),
				'description'         => __( 'Removes one installed plugin from the plugin directory after exact-version validation while retaining a short-lived WordPress temporary-backup rollback point.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
						'expected_version' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 64 ),
						'confirm_delete' => array( 'type' => 'boolean' ),
					),
					'required'             => array( 'plugin', 'expected_version', 'confirm_delete' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'delete_plugin' ),
				'permission_callback' => static function () { return current_user_can( 'delete_plugins' ); },
				'meta'                => self::destructive_meta(),
			)
		);

		wp_register_ability(
			self::PREFIX . 'delete-theme',
			array(
				'label'               => __( 'Delete installed theme', 'chattanooga-cms-admin' ),
				'description'         => __( 'Removes one inactive installed theme from the theme directory after exact-version validation while retaining a short-lived WordPress temporary-backup rollback point.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'stylesheet' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
						'expected_version' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 64 ),
						'confirm_delete' => array( 'type' => 'boolean' ),
					),
					'required'             => array( 'stylesheet', 'expected_version', 'confirm_delete' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'delete_theme' ),
				'permission_callback' => static function () { return current_user_can( 'delete_themes' ); },
				'meta'                => self::destructive_meta(),
			)
		);

		wp_register_ability(
			self::PREFIX . 'restore-component-backup',
			array(
				'label'               => __( 'Restore component rollback backup', 'chattanooga-cms-admin' ),
				'description'         => __( 'Restores a plugin or theme previously removed by this control plane from its signed WordPress temporary-backup rollback identifier.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array( 'type' => 'string', 'minLength' => 20, 'maxLength' => 4096 ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'restore_component_backup' ),
				'permission_callback' => static function () { return current_user_can( 'install_plugins' ) || current_user_can( 'install_themes' ); },
				'meta'                => self::destructive_meta(),
			)
		);
	}

	public static function delete_plugin( $input ) {
		if ( ! is_array( $input ) || empty( $input['confirm_delete'] ) ) {
			return new WP_Error( 'cmsa_delete_not_confirmed', 'Explicit plugin deletion confirmation is required.' );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin = isset( $input['plugin'] ) ? trim( (string) $input['plugin'] ) : '';
		$expected = isset( $input['expected_version'] ) ? trim( (string) $input['expected_version'] ) : '';
		if ( '' === $plugin || '' === $expected || 0 !== validate_file( $plugin ) || '.php' !== substr( $plugin, -4 ) ) {
			return new WP_Error( 'cmsa_invalid_plugin', 'A valid installed plugin file and expected version are required.' );
		}

		$self = plugin_basename( CUA_DIR . 'chattanooga-cms-admin.php' );
		if ( $plugin === $self ) {
			return new WP_Error( 'cmsa_self_delete_forbidden', 'Chattanooga CMS Admin cannot delete its own control-plane files.' );
		}

		$plugins = get_plugins();
		if ( ! isset( $plugins[ $plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_not_found', 'The requested plugin is not installed.' );
		}
		$version = isset( $plugins[ $plugin ]['Version'] ) ? (string) $plugins[ $plugin ]['Version'] : '';
		if ( $version !== $expected ) {
			return new WP_Error( 'cmsa_plugin_version_conflict', 'The installed plugin version changed before deletion.', array( 'current_version' => $version ) );
		}

		$slug = dirname( $plugin );
		if ( '.' === $slug || '' === $slug ) {
			return new WP_Error( 'cmsa_plugin_backup_unavailable', 'Reversible deletion is unavailable for a root-level single-file plugin.' );
		}

		$was_active = is_plugin_active( $plugin );
		$was_network_active = is_multisite() && is_plugin_active_for_network( $plugin );
		if ( $was_active ) {
			deactivate_plugins( $plugin, true, $was_network_active );
			if ( is_plugin_active( $plugin ) || ( $was_network_active && is_plugin_active_for_network( $plugin ) ) ) {
				return new WP_Error( 'cmsa_plugin_deactivation_failed', 'The plugin could not be deactivated before deletion.' );
			}
		}

		$backup = array( 'slug' => $slug, 'src' => WP_PLUGIN_DIR, 'dir' => 'plugins' );
		$upgrader = self::upgrader( array( WP_CONTENT_DIR, WP_PLUGIN_DIR ) );
		if ( is_wp_error( $upgrader ) ) {
			self::restore_plugin_activation( $plugin, $was_active, $was_network_active );
			return $upgrader;
		}

		$moved = $upgrader->move_to_temp_backup_dir( $backup );
		wp_clean_plugins_cache( false );
		if ( is_wp_error( $moved ) || true !== $moved || isset( get_plugins()[ $plugin ] ) ) {
			if ( true === $moved ) {
				$upgrader->restore_temp_backup( array( $backup ) );
				wp_clean_plugins_cache( false );
			}
			self::restore_plugin_activation( $plugin, $was_active, $was_network_active );
			return new WP_Error( 'cmsa_plugin_delete_failed', is_wp_error( $moved ) ? $moved->get_error_message() : 'Plugin deletion could not be verified.' );
		}

		$state = array(
			'type'           => 'plugin',
			'slug'           => $slug,
			'plugin'         => $plugin,
			'version'        => $version,
			'active'         => $was_active,
			'network_active' => $was_network_active,
			'created'        => time(),
		);

		return array(
			'plugin'      => $plugin,
			'version'     => $version,
			'deleted'     => true,
			'rollback_id' => self::encode_token( $state ),
			'expires_in'  => self::TOKEN_TTL,
		);
	}

	public static function delete_theme( $input ) {
		if ( ! is_array( $input ) || empty( $input['confirm_delete'] ) ) {
			return new WP_Error( 'cmsa_delete_not_confirmed', 'Explicit theme deletion confirmation is required.' );
		}

		$stylesheet = isset( $input['stylesheet'] ) ? trim( (string) $input['stylesheet'] ) : '';
		$expected = isset( $input['expected_version'] ) ? trim( (string) $input['expected_version'] ) : '';
		if ( '' === $stylesheet || '' === $expected || 0 !== validate_file( $stylesheet ) ) {
			return new WP_Error( 'cmsa_invalid_theme', 'A valid installed theme stylesheet and expected version are required.' );
		}
		if ( get_stylesheet() === $stylesheet || get_template() === $stylesheet ) {
			return new WP_Error( 'cmsa_active_theme_delete_forbidden', 'The active theme or its active parent cannot be deleted.' );
		}

		$theme = self::theme_object( $stylesheet );
		if ( ! $theme instanceof WP_Theme ) {
			return new WP_Error( 'cmsa_theme_not_found', 'The requested theme is not installed.' );
		}
		$version = (string) $theme->get( 'Version' );
		if ( $version !== $expected ) {
			return new WP_Error( 'cmsa_theme_version_conflict', 'The installed theme version changed before deletion.', array( 'current_version' => $version ) );
		}

		$theme_root = get_theme_root( $stylesheet );
		$backup = array( 'slug' => $stylesheet, 'src' => $theme_root, 'dir' => 'themes' );
		$upgrader = self::upgrader( array( WP_CONTENT_DIR, $theme_root ) );
		if ( is_wp_error( $upgrader ) ) {
			return $upgrader;
		}

		$moved = $upgrader->move_to_temp_backup_dir( $backup );
		$after_theme = self::theme_object( $stylesheet );
		if ( is_wp_error( $moved ) || true !== $moved || $after_theme instanceof WP_Theme ) {
			if ( true === $moved ) {
				$upgrader->restore_temp_backup( array( $backup ) );
				self::theme_object( $stylesheet );
			}
			return new WP_Error( 'cmsa_theme_delete_failed', is_wp_error( $moved ) ? $moved->get_error_message() : 'Theme deletion could not be verified.' );
		}

		$state = array(
			'type'    => 'theme',
			'slug'    => $stylesheet,
			'version' => $version,
			'created' => time(),
		);

		return array(
			'stylesheet' => $stylesheet,
			'version'    => $version,
			'deleted'    => true,
			'rollback_id'=> self::encode_token( $state ),
			'expires_in' => self::TOKEN_TTL,
		);
	}

	public static function restore_component_backup( $input ) {
		if ( ! is_array( $input ) || empty( $input['id'] ) ) {
			return new WP_Error( 'cmsa_invalid_backup_id', 'A signed component rollback identifier is required.' );
		}
		$state = self::decode_token( (string) $input['id'] );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		$type = $state['type'];
		$slug = $state['slug'];
		if ( 'plugin' === $type ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$plugin = $state['plugin'];
			if ( isset( get_plugins()[ $plugin ] ) ) {
				return new WP_Error( 'cmsa_component_restore_conflict', 'A plugin already exists at the rollback destination.' );
			}
			$backup = array( 'slug' => $slug, 'src' => WP_PLUGIN_DIR, 'dir' => 'plugins' );
			$upgrader = self::upgrader( array( WP_CONTENT_DIR, WP_PLUGIN_DIR ) );
		} else {
			if ( self::theme_object( $slug ) instanceof WP_Theme ) {
				return new WP_Error( 'cmsa_component_restore_conflict', 'A theme already exists at the rollback destination.' );
			}
			$theme_root = get_theme_root();
			$backup = array( 'slug' => $slug, 'src' => $theme_root, 'dir' => 'themes' );
			$upgrader = self::upgrader( array( WP_CONTENT_DIR, $theme_root ) );
		}
		if ( is_wp_error( $upgrader ) ) {
			return $upgrader;
		}

		$restored = $upgrader->restore_temp_backup( array( $backup ) );
		if ( is_wp_error( $restored ) || true !== $restored ) {
			return new WP_Error( 'cmsa_component_restore_failed', is_wp_error( $restored ) ? $restored->get_error_message() : 'WordPress could not restore the component rollback backup.' );
		}

		if ( 'plugin' === $type ) {
			wp_clean_plugins_cache( false );
			$plugins = get_plugins();
			if ( ! isset( $plugins[ $plugin ] ) || (string) $plugins[ $plugin ]['Version'] !== (string) $state['version'] ) {
				return new WP_Error( 'cmsa_component_restore_verification_failed', 'The restored plugin did not match the recorded rollback state.' );
			}
			$activation = self::restore_plugin_activation( $plugin, ! empty( $state['active'] ), ! empty( $state['network_active'] ) );
			if ( is_wp_error( $activation ) ) {
				return $activation;
			}
			return array( 'type' => 'plugin', 'plugin' => $plugin, 'version' => (string) $state['version'], 'restored' => true, 'active' => is_plugin_active( $plugin ) );
		}

		$theme = self::theme_object( $slug );
		if ( ! $theme instanceof WP_Theme || (string) $theme->get( 'Version' ) !== (string) $state['version'] ) {
			return new WP_Error( 'cmsa_component_restore_verification_failed', 'The restored theme did not match the recorded rollback state.' );
		}
		return array( 'type' => 'theme', 'stylesheet' => $slug, 'version' => (string) $state['version'], 'restored' => true );
	}

	private static function theme_object( $stylesheet ) {
		search_theme_directories( true );
		$themes = wp_get_themes( array( 'errors' => null ) );
		return isset( $themes[ $stylesheet ] ) && $themes[ $stylesheet ] instanceof WP_Theme ? $themes[ $stylesheet ] : null;
	}

	private static function upgrader( array $directories ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
		$upgrader = new WP_Upgrader( new Automatic_Upgrader_Skin() );
		$upgrader->init();
		$connected = $upgrader->fs_connect( $directories );
		if ( is_wp_error( $connected ) ) {
			return $connected;
		}
		if ( true !== $connected ) {
			return new WP_Error( 'cmsa_filesystem_unavailable', 'WordPress could not establish its filesystem abstraction for the component transaction.' );
		}
		return $upgrader;
	}

	private static function restore_plugin_activation( $plugin, $active, $network_active ) {
		if ( ! $active ) {
			if ( is_plugin_active( $plugin ) ) {
				deactivate_plugins( $plugin, true, $network_active );
			}
			return is_plugin_active( $plugin ) ? new WP_Error( 'cmsa_plugin_state_restore_failed', 'The prior inactive plugin state could not be restored.' ) : true;
		}
		$activated = activate_plugin( $plugin, '', $network_active, true );
		if ( is_wp_error( $activated ) || ! is_plugin_active( $plugin ) || ( $network_active && ! is_plugin_active_for_network( $plugin ) ) ) {
			return new WP_Error( 'cmsa_plugin_state_restore_failed', is_wp_error( $activated ) ? $activated->get_error_message() : 'The prior active plugin state could not be restored.' );
		}
		return true;
	}

	private static function encode_token( array $state ) {
		$payload = rtrim( strtr( base64_encode( wp_json_encode( $state ) ), '+/', '-_' ), '=' );
		$signature = hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );
		return $payload . '.' . $signature;
	}

	private static function decode_token( $token ) {
		$parts = explode( '.', trim( $token ), 2 );
		if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ) ), $parts[1] ) ) {
			return new WP_Error( 'cmsa_invalid_backup_id', 'The component rollback identifier is invalid.' );
		}
		$encoded = strtr( $parts[0], '-_', '+/' );
		$encoded .= str_repeat( '=', ( 4 - strlen( $encoded ) % 4 ) % 4 );
		$decoded = base64_decode( $encoded, true );
		$state = is_string( $decoded ) ? json_decode( $decoded, true ) : null;
		if ( ! is_array( $state ) || ! in_array( $state['type'] ?? '', array( 'plugin', 'theme' ), true ) || empty( $state['slug'] ) || empty( $state['version'] ) || empty( $state['created'] ) ) {
			return new WP_Error( 'cmsa_invalid_backup_id', 'The component rollback identifier payload is invalid.' );
		}
		if ( time() - (int) $state['created'] > self::TOKEN_TTL || (int) $state['created'] > time() + 300 ) {
			return new WP_Error( 'cmsa_backup_id_expired', 'The component rollback identifier has expired.' );
		}
		if ( 0 !== validate_file( (string) $state['slug'] ) || ( 'plugin' === $state['type'] && ( empty( $state['plugin'] ) || 0 !== validate_file( (string) $state['plugin'] ) ) ) ) {
			return new WP_Error( 'cmsa_invalid_backup_id', 'The component rollback identifier contains an invalid component identity.' );
		}
		return $state;
	}

	private static function destructive_meta() {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
		);
	}
}
