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
