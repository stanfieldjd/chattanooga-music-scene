<?php
/**
 * Read-only Chattanooga CMS Admin runtime capability probe.
 *
 * Load inside WordPress. It does not create files, update options, call external
 * services, or read member records.
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( 'cmsa_workbench_runtime_capabilities' ) ) {
	function cmsa_workbench_runtime_capabilities() {
		global $wpdb, $wp_version;

		$admin_includes = array(
			ABSPATH . 'wp-admin/includes/file.php',
			ABSPATH . 'wp-admin/includes/plugin.php',
			ABSPATH . 'wp-admin/includes/class-wp-upgrader.php',
			ABSPATH . 'wp-admin/includes/plugin-install.php',
			ABSPATH . 'wp-admin/includes/theme-install.php',
		);
		foreach ( $admin_includes as $include ) {
			if ( is_file( $include ) ) {
				require_once $include;
			}
		}

		$defined = get_defined_functions();
		$ability_functions = array();
		foreach ( isset( $defined['user'] ) ? $defined['user'] : array() as $function ) {
			if ( false !== stripos( $function, 'abilit' ) ) {
				$ability_functions[] = $function;
			}
		}
		sort( $ability_functions );

		$ability_classes = array();
		foreach ( get_declared_classes() as $class ) {
			if ( false !== stripos( $class, 'abilit' ) ) {
				$ability_classes[] = $class;
			}
		}
		sort( $ability_classes );

		$parent_backup = trailingslashit( dirname( ABSPATH ) ) . 'chattanooga-cms-admin-backups';
		$content_backup = trailingslashit( WP_CONTENT_DIR ) . 'chattanooga-cms-admin-backups';

		return array(
			'probe' => array(
				'read_only' => true,
				'generated_at' => gmdate( 'c' ),
			),
			'runtime' => array(
				'wordpress' => isset( $wp_version ) ? $wp_version : null,
				'php' => PHP_VERSION,
				'multisite' => function_exists( 'is_multisite' ) ? is_multisite() : null,
			),
			'abilities_api' => array(
				'wp_register_ability' => function_exists( 'wp_register_ability' ),
				'wp_register_ability_category' => function_exists( 'wp_register_ability_category' ),
				'wp_abilities_api_init_fired' => function_exists( 'did_action' ) ? (int) did_action( 'wp_abilities_api_init' ) : null,
				'wp_abilities_api_categories_init_fired' => function_exists( 'did_action' ) ? (int) did_action( 'wp_abilities_api_categories_init' ) : null,
				'defined_functions' => $ability_functions,
				'declared_classes' => $ability_classes,
			),
			'filesystem' => array(
				'WP_Filesystem' => function_exists( 'WP_Filesystem' ),
				'wp_mkdir_p' => function_exists( 'wp_mkdir_p' ),
				'unzip_file' => function_exists( 'unzip_file' ),
				'copy_dir' => function_exists( 'copy_dir' ),
				'wp_content_writable' => is_dir( WP_CONTENT_DIR ) && is_writable( WP_CONTENT_DIR ),
				'plugin_dir_writable' => defined( 'WP_PLUGIN_DIR' ) && is_dir( WP_PLUGIN_DIR ) && is_writable( WP_PLUGIN_DIR ),
				'theme_root_writable' => function_exists( 'get_theme_root' ) && is_dir( get_theme_root() ) && is_writable( get_theme_root() ),
				'parent_backup_exists' => is_dir( $parent_backup ),
				'parent_backup_parent_writable' => is_writable( dirname( $parent_backup ) ),
				'content_backup_exists' => is_dir( $content_backup ),
				'content_backup_parent_writable' => is_writable( dirname( $content_backup ) ),
			),
			'archive' => array(
				'ZipArchive' => class_exists( 'ZipArchive' ),
			),
			'updaters' => array(
				'Plugin_Upgrader' => class_exists( 'Plugin_Upgrader' ),
				'Theme_Upgrader' => class_exists( 'Theme_Upgrader' ),
				'Core_Upgrader' => class_exists( 'Core_Upgrader' ),
				'plugins_api' => function_exists( 'plugins_api' ),
				'themes_api' => function_exists( 'themes_api' ),
				'activate_plugin' => function_exists( 'activate_plugin' ),
				'deactivate_plugins' => function_exists( 'deactivate_plugins' ),
				'delete_plugins' => function_exists( 'delete_plugins' ),
				'delete_theme' => function_exists( 'delete_theme' ),
			),
			'database' => array(
				'wpdb_present' => is_object( $wpdb ),
				'db_version' => is_object( $wpdb ) && method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : null,
			),
			'cache' => array(
				'wp_cache_flush' => function_exists( 'wp_cache_flush' ),
				'wp_cache_flush_group' => function_exists( 'wp_cache_flush_group' ),
				'wp_super_cache_clear_cache' => function_exists( 'wp_cache_clear_cache' ),
			),
			'policy' => array(
				'DISALLOW_FILE_MODS' => defined( 'DISALLOW_FILE_MODS' ) ? (bool) DISALLOW_FILE_MODS : null,
				'AUTOMATIC_UPDATER_DISABLED' => defined( 'AUTOMATIC_UPDATER_DISABLED' ) ? (bool) AUTOMATIC_UPDATER_DISABLED : null,
				'WP_AUTO_UPDATE_CORE' => defined( 'WP_AUTO_UPDATE_CORE' ) ? WP_AUTO_UPDATE_CORE : null,
			),
			'network_api_presence' => array(
				'wp_remote_get' => function_exists( 'wp_remote_get' ),
				'wp_remote_post' => function_exists( 'wp_remote_post' ),
			),
		);
	}
}
