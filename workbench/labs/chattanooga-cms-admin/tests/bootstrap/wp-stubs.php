<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 3 ) . '/fake-wordpress/' );
}

$GLOBALS['cmsa_test_hooks'] = array();
$GLOBALS['cmsa_registered_abilities'] = array();
$GLOBALS['cmsa_registered_categories'] = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback ) {
		if ( ! isset( $GLOBALS['cmsa_test_hooks'][ $hook ] ) ) {
			$GLOBALS['cmsa_test_hooks'][ $hook ] = array();
		}
		$GLOBALS['cmsa_test_hooks'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook ) {
		$callbacks = isset( $GLOBALS['cmsa_test_hooks'][ $hook ] ) ? $GLOBALS['cmsa_test_hooks'][ $hook ] : array();
		foreach ( $callbacks as $callback ) {
			call_user_func( $callback );
		}
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		return true;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $path ) {
		return rtrim( $path, '/\\' ) . '/';
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_register_ability_category' ) ) {
	function wp_register_ability_category( $name, $args ) {
		$GLOBALS['cmsa_registered_categories'][ $name ] = $args;
		return true;
	}
}

if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $name, $args ) {
		if ( isset( $GLOBALS['cmsa_registered_abilities'][ $name ] ) ) {
			throw new RuntimeException( 'Duplicate ability registration: ' . $name );
		}
		$GLOBALS['cmsa_registered_abilities'][ $name ] = $args;
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return true;
	}
}
