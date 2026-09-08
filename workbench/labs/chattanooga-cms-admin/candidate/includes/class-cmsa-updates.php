<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Updates {
	private $backups;

	public function __construct() {
		$this->backups = new CMSA_Backups();
	}

	public function list_plugins() {
		$this->load_plugin_api();
		$this->refresh_updates();
		$plugins = get_plugins();
		$updates = get_site_transient( 'update_plugins' );
		$auto_updates = (array) get_site_option( 'auto_update_plugins', array() );
		$result = array();

		foreach ( $plugins as $file => $data ) {
			$update = isset( $updates->response[ $file ] ) ? $updates->response[ $file ] : null;
			$result[] = array(
				'file'                => $file,
				'name'                => $data['Name'],
				'version'             => $data['Version'],
				'active'              => is_plugin_active( $file ),
				'auto_update'         => in_array( $file, $auto_updates, true ),
				'update_available'    => (bool) $update,
				'available_version'   => $update && isset( $update->new_version ) ? $update->new_version : null,
				'requires_wordpress'  => $update && isset( $update->requires ) ? $update->requires : null,
				'requires_php'        => $update && isset( $update->requires_php ) ? $update->requires_php : null,
			);
		}

		return array( 'plugins' => $result );
	}

	public function list_themes() {
		$this->refresh_updates();
		$themes = wp_get_themes();
		$updates = get_site_transient( 'update_themes' );
		$auto_updates = (array) get_site_option( 'auto_update_themes', array() );
		$active = get_stylesheet();
		$result = array();

		foreach ( $themes as $stylesheet => $theme ) {
			$update = isset( $updates->response[ $stylesheet ] ) ? $updates->response[ $stylesheet ] : null;
			$result[] = array(
				'stylesheet'        => $stylesheet,
				'name'              => $theme->get( 'Name' ),
				'version'           => $theme->get( 'Version' ),
				'active'            => $stylesheet === $active,
				'auto_update'       => in_array( $stylesheet, $auto_updates, true ),
				'update_available'  => (bool) $update,
				'available_version' => $update && isset( $update['new_version'] ) ? $update['new_version'] : null,
			);
		}

		return array( 'themes' => $result );
	}

	public function list_updates() {
		$this->refresh_updates();
		$core = array();
		foreach ( get_core_updates( array( 'dismissed' => false ) ) as $update ) {
			$core[] = array(
				'version'  => isset( $update->version ) ? $update->version : null,
				'response' => isset( $update->response ) ? $update->response : null,
				'locale'   => isset( $update->locale ) ? $update->locale : null,
				'current'  => isset( $update->current ) ? $update->current : null,
			);
		}

		$plugins = $this->list_plugins();
		$themes = $this->list_themes();
		return array(
			'wordpress_version' => get_bloginfo( 'version' ),
			'core'              => $core,
			'plugins'           => $plugins['plugins'],
			'themes'            => $themes['themes'],
		);
	}

	public function update_plugin( $plugin, $expected_version = '' ) {
		$this->load_plugin_api();
		$this->refresh_updates();
		$plugins = get_plugins();
		if ( ! isset( $plugins[ $plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_not_found', 'The requested plugin is not installed.' );
		}
		if ( $expected_version && $plugins[ $plugin ]['Version'] !== $expected_version ) {
			return new WP_Error( 'cmsa_plugin_version_changed', 'Installed plugin version differs from the expected pre-update version.' );
		}

		$updates = get_site_transient( 'update_plugins' );
		if ( empty( $updates->response[ $plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_no_update', 'No WordPress update is currently offered for this plugin.' );
		}
		$target_version = $updates->response[ $plugin ]->new_version;

		$folder = dirname( $plugin );
		if ( '.' === $folder ) {
			return new WP_Error( 'cmsa_single_file_plugin', 'Single-file plugin updates are blocked because an exact component rollback archive cannot yet be created.' );
		}
		$source = trailingslashit( WP_PLUGIN_DIR ) . $folder;
		$backup = $this->backups->create_component_backup( 'plugin', $plugin, $source );
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}
		$verified = $this->backups->verify_backup( $backup['id'] );
		if ( is_wp_error( $verified ) || empty( $verified['valid'] ) ) {
			return new WP_Error( 'cmsa_plugin_backup_invalid', 'Plugin rollback archive did not verify; update was not attempted.' );
		}

		$was_active = is_plugin_active( $plugin );
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result = $upgrader->upgrade( $plugin, array( 'clear_update_cache' => true ) );
		if ( is_wp_error( $result ) || false === $result ) {
			return $this->rollback_error( 'cmsa_plugin_update_failed', 'Plugin update failed.', $backup['id'] );
		}

		wp_clean_plugins_cache( true );
		$plugins_after = get_plugins();
		$after_version = isset( $plugins_after[ $plugin ] ) ? $plugins_after[ $plugin ]['Version'] : null;
		if ( $after_version !== $target_version ) {
			return $this->rollback_error( 'cmsa_plugin_version_verify', 'Plugin post-update version verification failed.', $backup['id'] );
		}

		if ( $was_active && ! is_plugin_active( $plugin ) ) {
			$activation = activate_plugin( $plugin, '', false, true );
			if ( is_wp_error( $activation ) ) {
				return $this->rollback_error( 'cmsa_plugin_activation_verify', 'Plugin did not remain active after update.', $backup['id'] );
			}
		}

		CMSA_Audit::record( 'update-plugin', $plugin, 'success', array( 'from' => $plugins[ $plugin ]['Version'], 'to' => $after_version, 'backup_id' => $backup['id'] ) );
		return array(
			'updated'          => true,
			'plugin'           => $plugin,
			'previous_version' => $plugins[ $plugin ]['Version'],
			'version'          => $after_version,
			'backup_id'        => $backup['id'],
			'reload_required'  => true,
		);
	}

	public function update_theme( $stylesheet, $expected_version = '' ) {
		$this->refresh_updates();
		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'cmsa_theme_not_found', 'The requested theme is not installed.' );
		}
		$before_version = $theme->get( 'Version' );
		if ( $expected_version && $before_version !== $expected_version ) {
			return new WP_Error( 'cmsa_theme_version_changed', 'Installed theme version differs from the expected pre-update version.' );
		}

		$updates = get_site_transient( 'update_themes' );
		if ( empty( $updates->response[ $stylesheet ] ) ) {
			return new WP_Error( 'cmsa_theme_no_update', 'No WordPress update is currently offered for this theme.' );
		}
		$target_version = $updates->response[ $stylesheet ]['new_version'];
		$backup = $this->backups->create_component_backup( 'theme', $stylesheet, $theme->get_stylesheet_directory() );
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}
		$verified = $this->backups->verify_backup( $backup['id'] );
		if ( is_wp_error( $verified ) || empty( $verified['valid'] ) ) {
			return new WP_Error( 'cmsa_theme_backup_invalid', 'Theme rollback archive did not verify; update was not attempted.' );
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
		$result = $upgrader->upgrade( $stylesheet, array( 'clear_update_cache' => true ) );
		if ( is_wp_error( $result ) || false === $result ) {
			return $this->rollback_error( 'cmsa_theme_update_failed', 'Theme update failed.', $backup['id'] );
		}

		wp_clean_themes_cache( true );
		$after_version = wp_get_theme( $stylesheet )->get( 'Version' );
		if ( $after_version !== $target_version ) {
			return $this->rollback_error( 'cmsa_theme_version_verify', 'Theme post-update version verification failed.', $backup['id'] );
		}

		CMSA_Audit::record( 'update-theme', $stylesheet, 'success', array( 'from' => $before_version, 'to' => $after_version, 'backup_id' => $backup['id'] ) );
		return array(
			'updated'          => true,
			'theme'            => $stylesheet,
			'previous_version' => $before_version,
			'version'          => $after_version,
			'backup_id'        => $backup['id'],
			'reload_required'  => true,
		);
	}

	public function update_core( $requested_version = '' ) {
		$this->refresh_updates();
		$selected = null;
		foreach ( get_core_updates( array( 'dismissed' => false ) ) as $update ) {
			if ( isset( $update->response ) && 'upgrade' === $update->response ) {
				if ( ! $requested_version || $requested_version === $update->version ) {
					$selected = $update;
					break;
				}
			}
		}
		if ( ! $selected ) {
			return new WP_Error( 'cmsa_core_no_update', 'No matching WordPress core update is currently offered.' );
		}

		$before_version = $this->read_core_version();
		$backup = $this->backups->create_core_backup();
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}
		$verified = $this->backups->verify_backup( $backup['id'] );
		if ( is_wp_error( $verified ) || empty( $verified['valid'] ) ) {
			return new WP_Error( 'cmsa_core_backup_invalid', 'Core rollback snapshot did not verify; update was not attempted.' );
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader = new Core_Upgrader( new Automatic_Upgrader_Skin() );
		$result = $upgrader->upgrade( $selected );
		if ( is_wp_error( $result ) || false === $result ) {
			return $this->rollback_core_error( 'cmsa_core_update_failed', 'WordPress core update failed.', $backup['id'] );
		}

		$after_version = $this->read_core_version();
		if ( $after_version !== $selected->version ) {
			return $this->rollback_core_error( 'cmsa_core_version_verify', 'WordPress core post-update version verification failed.', $backup['id'] );
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		wp_upgrade();
		CMSA_Audit::record( 'update-core', 'wordpress-core', 'success', array( 'from' => $before_version, 'to' => $after_version, 'backup_id' => $backup['id'] ) );

		return array(
			'updated'          => true,
			'previous_version' => $before_version,
			'version'          => $after_version,
			'backup_id'        => $backup['id'],
			'reload_required'  => true,
		);
	}

	public function install_plugin( $slug ) {
		$this->load_plugin_api();
		$slug = sanitize_key( $slug );
		$api = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return CMSA_Errors::external( 'cmsa_plugin_package_lookup', 'WordPress.org plugin information could not be retrieved.' );
		}
		if ( empty( $api->download_link ) ) {
			return new WP_Error( 'cmsa_plugin_package', 'WordPress.org did not return an installable plugin package.' );
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) || false === $result ) {
			return CMSA_Errors::external( 'cmsa_plugin_install', 'Plugin installation failed.' );
		}

		$plugin = $upgrader->plugin_info();
		if ( ! $plugin || ! is_file( trailingslashit( WP_PLUGIN_DIR ) . $plugin ) ) {
			return new WP_Error( 'cmsa_plugin_install_verify', 'Plugin files could not be verified after installation.' );
		}
		CMSA_Audit::record( 'install-plugin', $plugin, 'success', array( 'slug' => $slug ) );
		return array( 'installed' => true, 'plugin' => $plugin, 'slug' => $slug );
	}

	public function activate_plugin( $plugin ) {
		$this->load_plugin_api();
		if ( ! isset( get_plugins()[ $plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_not_found', 'The requested plugin is not installed.' );
		}
		$result = activate_plugin( $plugin, '', false, true );
		if ( is_wp_error( $result ) ) {
			return CMSA_Errors::external( 'cmsa_plugin_activation', 'Plugin activation failed.' );
		}
		if ( ! is_plugin_active( $plugin ) ) {
			return new WP_Error( 'cmsa_plugin_activation_verify', 'Plugin activation did not persist.' );
		}
		CMSA_Audit::record( 'activate-plugin', $plugin, 'success' );
		return array( 'activated' => true, 'plugin' => $plugin );
	}

	public function deactivate_plugin( $plugin ) {
		$this->load_plugin_api();
		if ( ! isset( get_plugins()[ $plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_not_found', 'The requested plugin is not installed.' );
		}
		deactivate_plugins( $plugin );
		if ( is_plugin_active( $plugin ) ) {
			return new WP_Error( 'cmsa_plugin_deactivation_verify', 'Plugin deactivation did not persist.' );
		}
		CMSA_Audit::record( 'deactivate-plugin', $plugin, 'success' );
		return array( 'deactivated' => true, 'plugin' => $plugin );
	}

	public function install_theme( $slug ) {
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		$slug = sanitize_key( $slug );
		$api = themes_api( 'theme_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return CMSA_Errors::external( 'cmsa_theme_package_lookup', 'WordPress.org theme information could not be retrieved.' );
		}
		if ( empty( $api->download_link ) ) {
			return new WP_Error( 'cmsa_theme_package', 'WordPress.org did not return an installable theme package.' );
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
		$result = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) || false === $result ) {
			return CMSA_Errors::external( 'cmsa_theme_install', 'Theme installation failed.' );
		}
		wp_clean_themes_cache( true );
		$theme = wp_get_theme( $slug );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'cmsa_theme_install_verify', 'Theme files could not be verified after installation.' );
		}
		CMSA_Audit::record( 'install-theme', $slug, 'success' );
		return array( 'installed' => true, 'theme' => $slug, 'version' => $theme->get( 'Version' ) );
	}

	public function switch_theme( $stylesheet ) {
		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'cmsa_theme_not_found', 'The requested theme is not installed.' );
		}
		$previous = get_stylesheet();
		switch_theme( $stylesheet );
		if ( get_stylesheet() !== $stylesheet ) {
			return new WP_Error( 'cmsa_theme_switch_verify', 'Theme switch did not persist.' );
		}
		CMSA_Audit::record( 'switch-theme', $stylesheet, 'success', array( 'previous' => $previous ) );
		return array( 'switched' => true, 'previous_theme' => $previous, 'theme' => $stylesheet );
	}

	private function rollback_error( $code, $message, $backup_id ) {
		$rollback = $this->backups->restore_component_backup( $backup_id );
		$rolled_back = ! is_wp_error( $rollback );
		CMSA_Audit::record( 'automatic-component-rollback', $backup_id, $rolled_back ? 'success' : 'failed' );
		return CMSA_Errors::rollback( $code, $message, $backup_id, $rollback );
	}

	private function rollback_core_error( $code, $message, $backup_id ) {
		$rollback = $this->backups->restore_core_backup( $backup_id );
		$rolled_back = ! is_wp_error( $rollback );
		CMSA_Audit::record( 'automatic-core-rollback', 'wordpress-core', $rolled_back ? 'success' : 'failed', array( 'backup_id' => $backup_id ) );
		return CMSA_Errors::rollback( $code, $message, $backup_id, $rollback );
	}

	private function refresh_updates() {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		wp_version_check();
		wp_update_plugins();
		wp_update_themes();
	}

	private function load_plugin_api() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	}

	private function read_core_version() {
		$file = ABSPATH . WPINC . '/version.php';
		$contents = is_file( $file ) ? file_get_contents( $file ) : '';
		if ( preg_match( '/\\$wp_version\\s*=\\s*[\'\"]([^\'\"]+)[\'\"]/', $contents, $matches ) ) {
			return $matches[1];
		}
		return get_bloginfo( 'version' );
	}
}
