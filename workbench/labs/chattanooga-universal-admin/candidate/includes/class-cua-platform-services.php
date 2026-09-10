<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Services {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'get-health',
			array(
				'label'               => __( 'Get WordPress health', 'chattanooga-cms-admin' ),
				'description'         => __( 'Returns bounded WordPress runtime and writable-path health without inspecting provider-private state.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::empty_input_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'get_health' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => self::meta( true, false, true ),
			)
		);

		wp_register_ability(
			self::PREFIX . 'list-updates',
			array(
				'label'               => __( 'List WordPress updates', 'chattanooga-cms-admin' ),
				'description'         => __( 'Refreshes and returns normalized WordPress core, plugin, and theme update offers through WordPress core update APIs.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::empty_input_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'list_updates' ),
				'permission_callback' => static function () {
					return current_user_can( 'update_core' ) || current_user_can( 'update_plugins' ) || current_user_can( 'update_themes' );
				},
				'meta'                => self::meta( true, false, true ),
			)
		);

		wp_register_ability(
			self::PREFIX . 'update-plugin',
			array(
				'label'               => __( 'Update installed plugin', 'chattanooga-cms-admin' ),
				'description'         => __( 'Updates one installed plugin from the current WordPress update offer, using WordPress core temporary-backup rollback and exact version conflict checking.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin' => array(
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
					'required'             => array( 'plugin', 'expected_version' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'update_plugin' ),
				'permission_callback' => static function () {
					return current_user_can( 'update_plugins' );
				},
				'meta'                => self::meta( false, true, false ),
			)
		);
	}

	public static function get_health() {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$maintenance_file = ABSPATH . '.maintenance';
		$memory_limit = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : ini_get( 'memory_limit' );
		$admin_memory_limit = defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : $memory_limit;

		return array(
			'wordpress_version'   => get_bloginfo( 'version' ),
			'php_version'         => PHP_VERSION,
			'environment'         => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
			'timezone'            => wp_timezone_string(),
			'https'               => is_ssl(),
			'filesystem_method'   => get_filesystem_method(),
			'plugin_dir_writable' => wp_is_writable( WP_PLUGIN_DIR ),
			'theme_dir_writable'  => wp_is_writable( get_theme_root() ),
			'content_writable'    => wp_is_writable( WP_CONTENT_DIR ),
			'ziparchive'          => class_exists( 'ZipArchive' ),
			'wp_cache_enabled'    => defined( 'WP_CACHE' ) && (bool) WP_CACHE,
			'cron_disabled'       => defined( 'DISABLE_WP_CRON' ) && (bool) DISABLE_WP_CRON,
			'maintenance_mode'    => is_file( $maintenance_file ),
			'memory_limit'        => (string) $memory_limit,
			'admin_memory_limit'  => (string) $admin_memory_limit,
		);
	}

	public static function list_updates() {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		wp_version_check();
		wp_update_plugins();
		wp_update_themes();

		$core_updates = array();
		foreach ( (array) get_core_updates() as $update ) {
			if ( ! is_object( $update ) ) {
				continue;
			}
			$core_updates[] = array(
				'response' => isset( $update->response ) ? (string) $update->response : '',
				'version'  => isset( $update->version ) ? (string) $update->version : '',
				'locale'   => isset( $update->locale ) ? (string) $update->locale : '',
			);
		}

		$plugin_updates = array();
		foreach ( get_plugin_updates() as $plugin_file => $update ) {
			if ( ! is_object( $update ) || empty( $update->Name ) ) {
				continue;
			}
			$response = isset( $update->update ) && is_object( $update->update ) ? $update->update : null;
			$plugin_updates[] = array(
				'plugin'          => (string) $plugin_file,
				'name'            => (string) $update->Name,
				'current_version' => isset( $update->Version ) ? (string) $update->Version : '',
				'new_version'     => $response && isset( $response->new_version ) ? (string) $response->new_version : '',
			);
		}

		$theme_updates = array();
		foreach ( get_theme_updates() as $stylesheet => $theme ) {
			if ( ! $theme instanceof WP_Theme ) {
				continue;
			}
			$update = $theme->update;
			$theme_updates[] = array(
				'stylesheet'      => (string) $stylesheet,
				'name'            => (string) $theme->get( 'Name' ),
				'current_version' => (string) $theme->get( 'Version' ),
				'new_version'     => is_array( $update ) && isset( $update['new_version'] ) ? (string) $update['new_version'] : '',
			);
		}

		return array(
			'core'    => $core_updates,
			'plugins' => $plugin_updates,
			'themes'  => $theme_updates,
		);
	}

	public static function update_plugin( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'cmsa_invalid_input', 'Plugin update input must be an object.' );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

		$plugin = isset( $input['plugin'] ) ? trim( (string) $input['plugin'] ) : '';
		$expected_version = isset( $input['expected_version'] ) ? trim( (string) $input['expected_version'] ) : '';
		if ( '' === $plugin || '' === $expected_version || 0 !== validate_file( $plugin ) || '.php' !== substr( $plugin, -4 ) ) {
			return new WP_Error( 'cmsa_invalid_plugin', 'A valid installed plugin file and expected version are required.' );
		}

		$self_plugin = plugin_basename( CUA_DIR . 'chattanooga-cms-admin.php' );
		if ( $plugin === $self_plugin ) {
			return new WP_Error( 'cmsa_self_update_forbidden', 'Chattanooga CMS Admin cannot replace its own files during an administration request.' );
		}

		$plugins = get_plugins();
		if ( ! isset( $plugins[ $plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_not_found', 'The requested plugin is not installed.' );
		}

		$current_version = isset( $plugins[ $plugin ]['Version'] ) ? (string) $plugins[ $plugin ]['Version'] : '';
		if ( $current_version !== $expected_version ) {
			return new WP_Error(
				'cmsa_plugin_version_conflict',
				'The installed plugin version changed before the update could begin.',
				array( 'current_version' => $current_version )
			);
		}

		$slug = dirname( $plugin );
		if ( '.' === $slug || '' === $slug ) {
			return new WP_Error( 'cmsa_plugin_backup_unavailable', 'WordPress temporary-backup rollback is unavailable for a root-level single-file plugin.' );
		}

		$updates = get_site_transient( 'update_plugins' );
		if ( ! is_object( $updates ) || empty( $updates->response[ $plugin ] ) || ! is_object( $updates->response[ $plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_update_unavailable', 'No current WordPress update offer exists for this plugin. Refresh update inventory first.' );
		}

		$offer = $updates->response[ $plugin ];
		$new_version = isset( $offer->new_version ) ? trim( (string) $offer->new_version ) : '';
		$package = isset( $offer->package ) ? trim( (string) $offer->package ) : '';
		if ( '' === $new_version || '' === $package ) {
			return new WP_Error( 'cmsa_plugin_update_incomplete', 'The current WordPress update offer does not contain a version and package.' );
		}

		$backup = array(
			'slug' => $slug,
			'src'  => WP_PLUGIN_DIR,
			'dir'  => 'plugins',
		);
		$was_active = is_plugin_active( $plugin );
		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result = $upgrader->upgrade( $plugin, array( 'clear_update_cache' => false ) );

		wp_clean_plugins_cache( false );
		$after_plugins = get_plugins();
		$after_version = isset( $after_plugins[ $plugin ]['Version'] ) ? (string) $after_plugins[ $plugin ]['Version'] : '';

		if ( false === $result || is_wp_error( $result ) ) {
			$rolled_back = false;
			if ( $after_version !== $current_version ) {
				$restore = $upgrader->restore_temp_backup( array( $backup ) );
				wp_clean_plugins_cache( false );
				$restored_plugins = get_plugins();
				$restored_version = isset( $restored_plugins[ $plugin ]['Version'] ) ? (string) $restored_plugins[ $plugin ]['Version'] : '';
				$rolled_back = ! is_wp_error( $restore ) && true === $restore && $restored_version === $current_version;
				if ( ! $rolled_back ) {
					return new WP_Error( 'cmsa_plugin_update_rollback_failed', 'Plugin update failed and the previous version could not be verified after rollback.' );
				}
			} else {
				$rolled_back = true;
			}

			$message = is_wp_error( $result ) ? $result->get_error_message() : 'WordPress did not complete the plugin update.';
			return new WP_Error(
				'cmsa_plugin_update_failed',
				$message,
				array(
					'previous_version' => $current_version,
					'rolled_back'      => $rolled_back,
				)
			);
		}

		if ( $after_version !== $new_version ) {
			$restore = $upgrader->restore_temp_backup( array( $backup ) );
			wp_clean_plugins_cache( false );
			$restored_plugins = get_plugins();
			$restored_version = isset( $restored_plugins[ $plugin ]['Version'] ) ? (string) $restored_plugins[ $plugin ]['Version'] : '';
			if ( is_wp_error( $restore ) || true !== $restore || $restored_version !== $current_version ) {
				return new WP_Error( 'cmsa_plugin_update_verification_rollback_failed', 'Plugin update verification failed and the previous version could not be restored.' );
			}

			return new WP_Error(
				'cmsa_plugin_update_verification_failed',
				'The installed plugin version did not match the offered version after update; the previous version was restored.',
				array(
					'expected_new_version' => $new_version,
					'observed_version'     => $after_version,
					'restored_version'     => $restored_version,
				)
			);
		}

		return array(
			'plugin'           => $plugin,
			'previous_version' => $current_version,
			'version'          => $after_version,
			'active'           => is_plugin_active( $plugin ),
			'was_active'       => $was_active,
			'rollback'         => 'wordpress_temp_backup',
		);
	}

	private static function empty_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		);
	}

	private static function meta( $readonly, $destructive, $idempotent ) {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => (bool) $readonly,
				'destructive' => (bool) $destructive,
				'idempotent'  => (bool) $idempotent,
			),
		);
	}
}
