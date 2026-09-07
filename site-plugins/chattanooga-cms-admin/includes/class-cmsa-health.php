<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Health {
	public function get_health() {
		global $wpdb;

		if ( ! function_exists( 'get_filesystem_method' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$storage = CMSA_Backups::get_storage_directory();
		$db_test = $wpdb->get_var( 'SELECT 1' );

		return array(
			'wordpress_version'   => get_bloginfo( 'version' ),
			'php_version'         => PHP_VERSION,
			'environment'         => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
			'timezone'            => wp_timezone_string(),
			'https'               => is_ssl(),
			'multisite'           => is_multisite(),
			'database_responding' => '1' === (string) $db_test,
			'filesystem_method'   => get_filesystem_method(),
			'plugin_dir_writable' => is_writable( WP_PLUGIN_DIR ),
			'theme_dir_writable'  => is_writable( get_theme_root() ),
			'content_writable'    => is_writable( WP_CONTENT_DIR ),
			'backup_storage'      => is_wp_error( $storage ) ? array(
				'available' => false,
				'error'     => $storage->get_error_message(),
			) : array(
				'available' => true,
				'writable'  => is_writable( $storage ),
			),
			'ziparchive'          => class_exists( 'ZipArchive' ),
			'wp_cache_enabled'    => defined( 'WP_CACHE' ) && WP_CACHE,
			'cron_disabled'       => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'maintenance_mode'    => file_exists( ABSPATH . '.maintenance' ),
			'memory_limit'        => WP_MEMORY_LIMIT,
			'admin_memory_limit'  => WP_MAX_MEMORY_LIMIT,
		);
	}

	public function clear_cache() {
		$actions = array();

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$actions[] = 'wp-super-cache';
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$actions[] = 'object-cache';
		}

		clean_blog_cache( get_current_blog_id() );
		$actions[] = 'wordpress-object-state';

		CMSA_Audit::record( 'clear-cache', 'site', 'success', array( 'methods' => implode( ',', array_unique( $actions ) ) ) );

		return array(
			'cleared' => true,
			'methods' => array_values( array_unique( $actions ) ),
		);
	}
}
