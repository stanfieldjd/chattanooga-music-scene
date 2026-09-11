<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Package_Lifecycle {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'install-plugin',
			array(
				'label'               => __( 'Install WordPress.org plugin', 'chattanooga-cms-admin' ),
				'description'         => __( 'Installs one plugin by WordPress.org slug through the WordPress Plugins API and core upgrader, verifies the installed version, and leaves it inactive.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::slug_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'install_plugin' ),
				'permission_callback' => static function () {
					return current_user_can( 'install_plugins' ) && current_user_can( 'delete_plugins' );
				},
				'meta'                => self::mutation_meta(),
			)
		);

		wp_register_ability(
			self::PREFIX . 'install-plugin-package',
			array(
				'label'               => __( 'Install verified plugin package', 'chattanooga-cms-admin' ),
				'description'         => __( 'Installs one exact ZIP plugin package after SHA-256, package-root, plugin-file, and version verification. The plugin is left inactive and failed verification is rolled back.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::plugin_package_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'install_plugin_package' ),
				'permission_callback' => static function () {
					return current_user_can( 'install_plugins' ) && current_user_can( 'delete_plugins' );
				},
				'meta'                => self::mutation_meta(),
			)
		);

		wp_register_ability(
			self::PREFIX . 'install-theme',
			array(
				'label'               => __( 'Install WordPress.org theme', 'chattanooga-cms-admin' ),
				'description'         => __( 'Installs one theme by WordPress.org slug through the WordPress Themes API and core upgrader, verifies the installed version, and leaves the active theme unchanged.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::slug_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'install_theme' ),
				'permission_callback' => static function () {
					return current_user_can( 'install_themes' ) && current_user_can( 'delete_themes' );
				},
				'meta'                => self::mutation_meta(),
			)
		);

		wp_register_ability(
			self::PREFIX . 'activate-plugin',
			array(
				'label'               => __( 'Activate installed plugin', 'chattanooga-cms-admin' ),
				'description'         => __( 'Activates one installed plugin through WordPress core and verifies the resulting activation state.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::plugin_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'activate_plugin' ),
				'permission_callback' => static function () { return current_user_can( 'activate_plugins' ); },
				'meta'                => self::mutation_meta(),
			)
		);

		wp_register_ability(
			self::PREFIX . 'deactivate-plugin',
			array(
				'label'               => __( 'Deactivate installed plugin', 'chattanooga-cms-admin' ),
				'description'         => __( 'Deactivates one installed non-control-plane plugin through WordPress core and verifies the resulting activation state.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::plugin_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'deactivate_plugin' ),
				'permission_callback' => static function () { return current_user_can( 'activate_plugins' ); },
				'meta'                => self::mutation_meta(),
			)
		);
	}

	public static function install_plugin( $input ) {
		$slug = self::read_slug( $input );
		if ( is_wp_error( $slug ) ) {
			return $slug;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

		$before = get_plugins();
		if ( self::plugin_file_for_slug( $slug, $before ) ) {
			return new WP_Error( 'cmsa_plugin_already_installed', 'A plugin with the requested WordPress.org slug is already installed.' );
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false, 'language_packs' => false ),
			)
		);
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		if ( ! is_object( $api ) || $slug !== (string) ( $api->slug ?? '' ) || empty( $api->download_link ) || empty( $api->version ) ) {
			return new WP_Error( 'cmsa_plugin_api_invalid', 'The WordPress Plugins API did not return a complete matching package record.' );
		}

		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result = $upgrader->install( (string) $api->download_link );
		wp_clean_plugins_cache( false );
		$after = get_plugins();

		if ( true !== $result ) {
			self::rollback_new_plugins( $before, $after );
			return new WP_Error( 'cmsa_plugin_install_failed', is_wp_error( $result ) ? $result->get_error_message() : 'WordPress did not complete the plugin installation.' );
		}

		$plugin = $upgrader->plugin_info();
		if ( ! is_string( $plugin ) || '' === $plugin || ! isset( $after[ $plugin ] ) ) {
			$plugin = self::single_new_plugin( $before, $after );
		}
		if ( ! is_string( $plugin ) || '' === $plugin || ! isset( $after[ $plugin ] ) ) {
			$rollback = self::rollback_new_plugins( $before, $after );
			return is_wp_error( $rollback ) ? $rollback : new WP_Error( 'cmsa_plugin_install_verification_failed', 'The installed plugin could not be identified after installation.' );
		}

		$version = (string) ( $after[ $plugin ]['Version'] ?? '' );
		if ( (string) $api->version !== $version || is_plugin_active( $plugin ) ) {
			$rollback = self::rollback_new_plugins( $before, $after );
			return is_wp_error( $rollback ) ? $rollback : new WP_Error( 'cmsa_plugin_install_verification_failed', 'The installed plugin did not match the WordPress.org package record or was unexpectedly activated; the installation was removed.' );
		}

		return array(
			'slug'      => $slug,
			'plugin'    => $plugin,
			'version'   => $version,
			'installed' => true,
			'active'    => false,
		);
	}

	public static function install_plugin_package( $input ) {
		$package = self::read_plugin_package( $input );
		if ( is_wp_error( $package ) ) {
			return $package;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

		$expected_plugin  = $package['expected_plugin'];
		$expected_version = $package['expected_version'];
		$expected_sha256  = $package['expected_sha256'];
		$bytes            = $package['bytes'];

		$before = get_plugins();
		if ( isset( $before[ $expected_plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_already_installed', 'The requested plugin is already installed.' );
		}

		$self = plugin_basename( CUA_DIR . 'chattanooga-cms-admin.php' );
		if ( $expected_plugin === $self ) {
			return new WP_Error( 'cmsa_self_package_install_forbidden', 'Chattanooga CMS Admin cannot replace its own running control-plane package through this ability.' );
		}

		$temp = wp_tempnam( 'cmsa-plugin-package.zip' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return new WP_Error( 'cmsa_plugin_package_temp_failed', 'WordPress could not create temporary storage for the plugin package.' );
		}

		$written = file_put_contents( $temp, $bytes );
		if ( strlen( $bytes ) !== $written ) {
			@unlink( $temp );
			return new WP_Error( 'cmsa_plugin_package_write_failed', 'WordPress could not persist the complete verified plugin package.' );
		}

		$preflight = self::verify_plugin_package_archive( $temp, $expected_plugin );
		if ( is_wp_error( $preflight ) ) {
			@unlink( $temp );
			return $preflight;
		}

		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result = $upgrader->install( $temp );
		@unlink( $temp );

		wp_clean_plugins_cache( false );
		$after = get_plugins();

		if ( true !== $result ) {
			$rollback = self::rollback_new_plugins( $before, $after );
			if ( is_wp_error( $rollback ) ) {
				return $rollback;
			}
			return new WP_Error( 'cmsa_plugin_package_install_failed', is_wp_error( $result ) ? $result->get_error_message() : 'WordPress did not complete the verified plugin package installation.' );
		}

		$unexpected = array_diff( array_keys( $after ), array_keys( $before ), array( $expected_plugin ) );
		$version = isset( $after[ $expected_plugin ]['Version'] ) ? (string) $after[ $expected_plugin ]['Version'] : '';
		if ( ! isset( $after[ $expected_plugin ] ) || $unexpected || $expected_version !== $version || is_plugin_active( $expected_plugin ) ) {
			$rollback = self::rollback_new_plugins( $before, $after );
			if ( is_wp_error( $rollback ) ) {
				return $rollback;
			}
			return new WP_Error( 'cmsa_plugin_package_install_verification_failed', 'The installed package did not match the expected plugin identity/version or was unexpectedly activated; the installation was removed.' );
		}

		return array(
			'plugin'    => $expected_plugin,
			'version'   => $version,
			'sha256'    => $expected_sha256,
			'installed' => true,
			'active'    => false,
		);
	}

	public static function install_theme( $input ) {
		$slug = self::read_slug( $input );
		if ( is_wp_error( $slug ) ) {
			return $slug;
		}

		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/theme-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

		if ( self::theme_object( $slug ) instanceof WP_Theme ) {
			return new WP_Error( 'cmsa_theme_already_installed', 'A theme with the requested WordPress.org slug is already installed.' );
		}
		$active_before = get_stylesheet();

		$api = themes_api(
			'theme_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false, 'tags' => false ),
			)
		);
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		if ( ! is_object( $api ) || $slug !== (string) ( $api->slug ?? '' ) || empty( $api->download_link ) || empty( $api->version ) ) {
			return new WP_Error( 'cmsa_theme_api_invalid', 'The WordPress Themes API did not return a complete matching package record.' );
		}

		$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
		$result = $upgrader->install( (string) $api->download_link );
		$theme = self::theme_object( $slug );
		if ( true !== $result ) {
			if ( $theme instanceof WP_Theme ) {
				delete_theme( $slug );
				self::theme_object( $slug );
			}
			return new WP_Error( 'cmsa_theme_install_failed', is_wp_error( $result ) ? $result->get_error_message() : 'WordPress did not complete the theme installation.' );
		}

		if ( ! $theme instanceof WP_Theme || (string) $api->version !== (string) $theme->get( 'Version' ) || get_stylesheet() !== $active_before ) {
			$rollback = $theme instanceof WP_Theme ? delete_theme( $slug ) : true;
			$after_rollback = self::theme_object( $slug );
			if ( is_wp_error( $rollback ) || false === $rollback || $after_rollback instanceof WP_Theme ) {
				return new WP_Error( 'cmsa_theme_install_rollback_failed', 'Theme installation verification failed and WordPress could not remove the new theme.' );
			}
			return new WP_Error( 'cmsa_theme_install_verification_failed', 'The installed theme did not match the WordPress.org package record or changed the active theme; the installation was removed.' );
		}

		return array(
			'slug'       => $slug,
			'stylesheet' => $slug,
			'version'    => (string) $theme->get( 'Version' ),
			'installed'  => true,
			'active'     => false,
		);
	}

	public static function activate_plugin( $input ) {
		$plugin = self::read_plugin( $input );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		if ( ! isset( $plugins[ $plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_not_found', 'The requested plugin is not installed.' );
		}
		if ( is_plugin_active( $plugin ) ) {
			return array( 'plugin' => $plugin, 'active' => true, 'changed' => false );
		}
		$result = activate_plugin( $plugin, '', false, true );
		if ( is_wp_error( $result ) || ! is_plugin_active( $plugin ) ) {
			return new WP_Error( 'cmsa_plugin_activation_failed', is_wp_error( $result ) ? $result->get_error_message() : 'Plugin activation did not persist.' );
		}
		return array( 'plugin' => $plugin, 'active' => true, 'changed' => true );
	}

	public static function deactivate_plugin( $input ) {
		$plugin = self::read_plugin( $input );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		if ( ! isset( $plugins[ $plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_not_found', 'The requested plugin is not installed.' );
		}
		$self = plugin_basename( CUA_DIR . 'chattanooga-cms-admin.php' );
		if ( $plugin === $self ) {
			return new WP_Error( 'cmsa_self_deactivation_forbidden', 'Chattanooga CMS Admin cannot deactivate its own control plane.' );
		}
		if ( is_multisite() && is_plugin_active_for_network( $plugin ) ) {
			return new WP_Error( 'cmsa_network_plugin_scope_required', 'Network-active plugin deactivation requires an explicit network administration contract.' );
		}
		if ( ! is_plugin_active( $plugin ) ) {
			return array( 'plugin' => $plugin, 'active' => false, 'changed' => false );
		}
		deactivate_plugins( $plugin, true, false );
		if ( is_plugin_active( $plugin ) ) {
			return new WP_Error( 'cmsa_plugin_deactivation_failed', 'Plugin deactivation did not persist.' );
		}
		return array( 'plugin' => $plugin, 'active' => false, 'changed' => true );
	}

	private static function read_slug( $input ) {
		$slug = is_array( $input ) && isset( $input['slug'] ) ? trim( (string) $input['slug'] ) : '';
		if ( '' === $slug || ! preg_match( '/^[a-z0-9][a-z0-9-]*$/', $slug ) ) {
			return new WP_Error( 'cmsa_invalid_slug', 'A lowercase WordPress.org component slug is required.' );
		}
		return $slug;
	}

	private static function read_plugin( $input ) {
		$plugin = is_array( $input ) && isset( $input['plugin'] ) ? trim( (string) $input['plugin'] ) : '';
		if ( '' === $plugin || 0 !== validate_file( $plugin ) || '.php' !== substr( $plugin, -4 ) ) {
			return new WP_Error( 'cmsa_invalid_plugin', 'A valid installed plugin file is required.' );
		}
		return $plugin;
	}

	private static function read_plugin_package( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'cmsa_invalid_plugin_package', 'Verified plugin package input must be an object.' );
		}

		$content_base64   = isset( $input['content_base64'] ) ? (string) $input['content_base64'] : '';
		$expected_sha256 = isset( $input['expected_sha256'] ) ? strtolower( trim( (string) $input['expected_sha256'] ) ) : '';
		$expected_plugin = isset( $input['expected_plugin'] ) ? trim( (string) $input['expected_plugin'] ) : '';
		$expected_version = isset( $input['expected_version'] ) ? trim( (string) $input['expected_version'] ) : '';

		if ( '' === $content_base64
			|| ! preg_match( '/^[a-f0-9]{64}$/', $expected_sha256 )
			|| '' === $expected_plugin
			|| 0 !== validate_file( $expected_plugin )
			|| '.php' !== substr( $expected_plugin, -4 )
			|| '.' === dirname( $expected_plugin )
			|| '' === $expected_version
			|| strlen( $expected_version ) > 64 ) {
			return new WP_Error( 'cmsa_invalid_plugin_package', 'Exact package bytes, SHA-256, plugin file, and expected version are required.' );
		}

		$bytes = base64_decode( $content_base64, true );
		if ( false === $bytes || '' === $bytes ) {
			return new WP_Error( 'cmsa_invalid_plugin_package', 'Plugin package bytes were not valid base64-encoded content.' );
		}

		$actual_sha256 = hash( 'sha256', $bytes );
		if ( ! hash_equals( $expected_sha256, $actual_sha256 ) ) {
			return new WP_Error(
				'cmsa_plugin_package_hash_mismatch',
				'The plugin package SHA-256 did not match the expected digest.',
				array( 'actual_sha256' => $actual_sha256 )
			);
		}

		return array(
			'bytes'            => $bytes,
			'expected_sha256'  => $expected_sha256,
			'expected_plugin'  => $expected_plugin,
			'expected_version' => $expected_version,
		);
	}

	private static function verify_plugin_package_archive( $path, $expected_plugin ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'cmsa_plugin_package_zip_unavailable', 'ZipArchive is required to verify custom plugin package structure.' );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'cmsa_plugin_package_invalid_zip', 'The verified plugin package could not be opened as a ZIP archive.' );
		}

		$root = dirname( $expected_plugin );
		$found_expected = false;
		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$name = (string) $zip->getNameIndex( $index );
			if ( '' === $name
				|| 0 === strpos( $name, '/' )
				|| false !== strpos( $name, '\\' )
				|| preg_match( '#(^|/)\.\.(/|$)#', $name )
				|| ( $name !== $root && 0 !== strpos( $name, $root . '/' ) ) ) {
				$zip->close();
				return new WP_Error( 'cmsa_plugin_package_structure_mismatch', 'The plugin package contains files outside the expected plugin directory.' );
			}
			if ( $name === $expected_plugin ) {
				$found_expected = true;
			}
		}
		$zip->close();

		if ( ! $found_expected ) {
			return new WP_Error( 'cmsa_plugin_package_identity_mismatch', 'The expected plugin file was not present in the verified package.' );
		}

		return true;
	}

	private static function plugin_file_for_slug( $slug, array $plugins ) {
		foreach ( array_keys( $plugins ) as $plugin_file ) {
			$directory = dirname( $plugin_file );
			if ( $directory === $slug || ( '.' === $directory && basename( $plugin_file, '.php' ) === $slug ) ) {
				return $plugin_file;
			}
		}
		return false;
	}

	private static function single_new_plugin( array $before, array $after ) {
		$new = array_values( array_diff( array_keys( $after ), array_keys( $before ) ) );
		return 1 === count( $new ) ? $new[0] : false;
	}

	private static function rollback_new_plugins( array $before, array $after ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$new = array_values( array_diff( array_keys( $after ), array_keys( $before ) ) );
		$representatives = array();
		foreach ( $new as $plugin_file ) {
			$directory = dirname( $plugin_file );
			$key = '.' === $directory ? $plugin_file : $directory;
			if ( ! isset( $representatives[ $key ] ) ) {
				$representatives[ $key ] = $plugin_file;
			}
		}
		if ( $representatives ) {
			$result = delete_plugins( array_values( $representatives ) );
			wp_clean_plugins_cache( false );
			if ( is_wp_error( $result ) || false === $result ) {
				return new WP_Error( 'cmsa_plugin_install_rollback_failed', is_wp_error( $result ) ? $result->get_error_message() : 'WordPress could not remove the failed plugin installation.' );
			}
		}
		$remaining = array_diff( array_keys( get_plugins() ), array_keys( $before ) );
		return empty( $remaining ) ? true : new WP_Error( 'cmsa_plugin_install_rollback_failed', 'Unexpected plugin files remained after installation rollback.' );
	}

	private static function theme_object( $stylesheet ) {
		search_theme_directories( true );
		$themes = wp_get_themes( array( 'errors' => null ) );
		return isset( $themes[ $stylesheet ] ) && $themes[ $stylesheet ] instanceof WP_Theme ? $themes[ $stylesheet ] : null;
	}

	private static function slug_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array( 'slug' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 191 ) ),
			'required'             => array( 'slug' ),
			'additionalProperties' => false,
		);
	}

	private static function plugin_package_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'content_base64' => array( 'type' => 'string', 'minLength' => 4 ),
				'expected_sha256' => array( 'type' => 'string', 'pattern' => '^[A-Fa-f0-9]{64}$' ),
				'expected_plugin' => array( 'type' => 'string', 'minLength' => 3, 'maxLength' => 255 ),
				'expected_version' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 64 ),
			),
			'required'             => array( 'content_base64', 'expected_sha256', 'expected_plugin', 'expected_version' ),
			'additionalProperties' => false,
		);
	}

	private static function plugin_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array( 'plugin' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ) ),
			'required'             => array( 'plugin' ),
			'additionalProperties' => false,
		);
	}

	private static function mutation_meta() {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		);
	}
}
