<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Lifecycle {
	private $backups;

	public function __construct() {
		$this->backups = new CMSA_Backups();
	}

	public function delete_plugin( $plugin ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		if ( ! isset( $plugins[ $plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_not_found', 'The requested plugin is not installed.' );
		}
		if ( plugin_basename( CMSA_FILE ) === $plugin ) {
			return new WP_Error( 'cmsa_self_delete_blocked', 'Chattanooga CMS Admin cannot delete its own active control plugin.' );
		}

		$folder = dirname( $plugin );
		if ( '.' === $folder ) {
			return new WP_Error( 'cmsa_single_file_plugin', 'Single-file plugin deletion is blocked because an exact rollback archive cannot yet be created.' );
		}

		$backup = $this->backups->create_component_backup( 'plugin', $plugin, trailingslashit( WP_PLUGIN_DIR ) . $folder );
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}
		$verified = $this->backups->verify_backup( $backup['id'] );
		if ( is_wp_error( $verified ) || empty( $verified['valid'] ) ) {
			return new WP_Error( 'cmsa_plugin_backup_invalid', 'Plugin rollback archive did not verify; deletion was not attempted.' );
		}

		$was_active = is_plugin_active( $plugin );
		if ( $was_active ) {
			deactivate_plugins( $plugin );
		}

		$result = delete_plugins( array( $plugin ) );
		wp_clean_plugins_cache( true );
		if ( is_wp_error( $result ) || false === $result || isset( get_plugins()[ $plugin ] ) ) {
			$rollback = $this->backups->restore_component_backup( $backup['id'] );
			if ( ! is_wp_error( $rollback ) && $was_active ) {
				activate_plugin( $plugin, '', false, true );
			}
			return CMSA_Errors::rollback( 'cmsa_plugin_delete_failed', 'Plugin deletion failed and rollback was attempted.', $backup['id'], $rollback );
		}

		CMSA_Audit::record( 'delete-plugin', $plugin, 'success', array( 'backup_id' => $backup['id'] ) );
		return array( 'deleted' => true, 'plugin' => $plugin, 'backup_id' => $backup['id'] );
	}

	public function delete_theme( $stylesheet ) {
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'cmsa_theme_not_found', 'The requested theme is not installed.' );
		}

		$active_stylesheet = get_stylesheet();
		$active_template = get_template();
		if ( $stylesheet === $active_stylesheet || $stylesheet === $active_template ) {
			return new WP_Error( 'cmsa_active_theme_delete', 'The active theme or its active parent cannot be deleted.' );
		}

		$backup = $this->backups->create_component_backup( 'theme', $stylesheet, $theme->get_stylesheet_directory() );
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}
		$verified = $this->backups->verify_backup( $backup['id'] );
		if ( is_wp_error( $verified ) || empty( $verified['valid'] ) ) {
			return new WP_Error( 'cmsa_theme_backup_invalid', 'Theme rollback archive did not verify; deletion was not attempted.' );
		}

		$result = delete_theme( $stylesheet );
		wp_clean_themes_cache( true );
		if ( is_wp_error( $result ) || false === $result || wp_get_theme( $stylesheet )->exists() ) {
			$rollback = $this->backups->restore_component_backup( $backup['id'] );
			return CMSA_Errors::rollback( 'cmsa_theme_delete_failed', 'Theme deletion failed and rollback was attempted.', $backup['id'], $rollback );
		}

		CMSA_Audit::record( 'delete-theme', $stylesheet, 'success', array( 'backup_id' => $backup['id'] ) );
		return array( 'deleted' => true, 'theme' => $stylesheet, 'backup_id' => $backup['id'] );
	}

	public function set_plugin_auto_update( $plugin, $enabled ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( ! isset( get_plugins()[ $plugin ] ) ) {
			return new WP_Error( 'cmsa_plugin_not_found', 'The requested plugin is not installed.' );
		}

		$current = array_values( array_unique( (array) get_site_option( 'auto_update_plugins', array() ) ) );
		if ( $enabled && ! in_array( $plugin, $current, true ) ) {
			$current[] = $plugin;
		} elseif ( ! $enabled ) {
			$current = array_values( array_diff( $current, array( $plugin ) ) );
		}
		update_site_option( 'auto_update_plugins', $current );
		$persisted = in_array( $plugin, (array) get_site_option( 'auto_update_plugins', array() ), true );
		if ( $persisted !== (bool) $enabled ) {
			return new WP_Error( 'cmsa_plugin_auto_update_verify', 'Plugin auto-update setting did not persist.' );
		}
		CMSA_Audit::record( 'set-plugin-auto-update', $plugin, 'success', array( 'enabled' => (bool) $enabled ) );
		return array( 'plugin' => $plugin, 'auto_update' => $persisted );
	}

	public function set_theme_auto_update( $stylesheet, $enabled ) {
		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'cmsa_theme_not_found', 'The requested theme is not installed.' );
		}

		$current = array_values( array_unique( (array) get_site_option( 'auto_update_themes', array() ) ) );
		if ( $enabled && ! in_array( $stylesheet, $current, true ) ) {
			$current[] = $stylesheet;
		} elseif ( ! $enabled ) {
			$current = array_values( array_diff( $current, array( $stylesheet ) ) );
		}
		update_site_option( 'auto_update_themes', $current );
		$persisted = in_array( $stylesheet, (array) get_site_option( 'auto_update_themes', array() ), true );
		if ( $persisted !== (bool) $enabled ) {
			return new WP_Error( 'cmsa_theme_auto_update_verify', 'Theme auto-update setting did not persist.' );
		}
		CMSA_Audit::record( 'set-theme-auto-update', $stylesheet, 'success', array( 'enabled' => (bool) $enabled ) );
		return array( 'theme' => $stylesheet, 'auto_update' => $persisted );
	}
}
