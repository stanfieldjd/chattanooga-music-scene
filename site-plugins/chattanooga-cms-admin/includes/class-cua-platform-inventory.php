<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Inventory {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'list-plugins',
			array(
				'label'               => __( 'List installed plugins', 'chattanooga-cms-admin' ),
				'description'         => __( 'Returns normalized installed-plugin identity, version, activation state, and current WordPress update offer.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::empty_input_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'list_plugins' ),
				'permission_callback' => static function () { return current_user_can( 'activate_plugins' ) || current_user_can( 'update_plugins' ); },
				'meta'                => self::readonly_meta(),
			)
		);

		wp_register_ability(
			self::PREFIX . 'list-themes',
			array(
				'label'               => __( 'List installed themes', 'chattanooga-cms-admin' ),
				'description'         => __( 'Returns normalized installed-theme identity, version, active state, and current WordPress update offer.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::empty_input_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'list_themes' ),
				'permission_callback' => static function () { return current_user_can( 'switch_themes' ) || current_user_can( 'update_themes' ); },
				'meta'                => self::readonly_meta(),
			)
		);

		wp_register_ability(
			self::PREFIX . 'clear-cache',
			array(
				'label'               => __( 'Clear WordPress cache', 'chattanooga-cms-admin' ),
				'description'         => __( 'Flushes the WordPress object cache through the core cache contract. Provider-specific caches require their own discovered public contract.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::empty_input_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'clear_cache' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);
	}

	public static function list_plugins() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$updates = get_site_transient( 'update_plugins' );
		$responses = is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ? $updates->response : array();
		$items = array();
		foreach ( get_plugins() as $plugin_file => $data ) {
			$offer = isset( $responses[ $plugin_file ] ) && is_object( $responses[ $plugin_file ] ) ? $responses[ $plugin_file ] : null;
			$items[] = array(
				'plugin'          => (string) $plugin_file,
				'name'            => isset( $data['Name'] ) ? (string) $data['Name'] : '',
				'version'         => isset( $data['Version'] ) ? (string) $data['Version'] : '',
				'active'          => is_plugin_active( $plugin_file ),
				'network_active'  => is_multisite() && is_plugin_active_for_network( $plugin_file ),
				'new_version'     => $offer && isset( $offer->new_version ) ? (string) $offer->new_version : '',
			);
		}
		usort( $items, static function ( $a, $b ) { return strcmp( $a['plugin'], $b['plugin'] ); } );
		return array( 'count' => count( $items ), 'items' => $items );
	}

	public static function list_themes() {
		$updates = get_site_transient( 'update_themes' );
		$responses = is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ? $updates->response : array();
		$active_stylesheet = get_stylesheet();
		$active_template = get_template();
		$items = array();
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			if ( ! $theme instanceof WP_Theme ) {
				continue;
			}
			$offer = isset( $responses[ $stylesheet ] ) && is_array( $responses[ $stylesheet ] ) ? $responses[ $stylesheet ] : array();
			$items[] = array(
				'stylesheet'      => (string) $stylesheet,
				'name'            => (string) $theme->get( 'Name' ),
				'version'         => (string) $theme->get( 'Version' ),
				'active'          => $active_stylesheet === $stylesheet,
				'active_template' => $active_template === $stylesheet,
				'new_version'     => isset( $offer['new_version'] ) ? (string) $offer['new_version'] : '',
			);
		}
		usort( $items, static function ( $a, $b ) { return strcmp( $a['stylesheet'], $b['stylesheet'] ); } );
		return array( 'count' => count( $items ), 'items' => $items );
	}

	public static function clear_cache() {
		$result = wp_cache_flush();
		return array( 'object_cache_flushed' => false !== $result );
	}

	private static function empty_input_schema() {
		return array( 'type' => 'object', 'properties' => array(), 'additionalProperties' => false );
	}

	private static function readonly_meta() {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
		);
	}
}
