<?php
/**
 * Plugin Name: Chattanooga Universal Admin
 * Description: Plugin-agnostic administrative capability bridge built on public WordPress contracts.
 * Version: 0.0.1
 * Author: Chattanooga Music Scene
 * Requires at least: 7.1
 * Requires PHP: 8.0
 * Text Domain: chattanooga-universal-admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CUA_VERSION', '0.0.1' );
define( 'CUA_DIR', plugin_dir_path( __FILE__ ) );

require_once CUA_DIR . 'includes/class-cua-ability-bridge.php';

add_action( 'wp_abilities_api_categories_init', array( 'CUA_Ability_Bridge', 'register_category' ) );
add_action( 'wp_abilities_api_init', array( 'CUA_Ability_Bridge', 'register_catalog_ability' ), 5 );
add_action( 'wp_abilities_api_init', array( 'CUA_Ability_Bridge', 'register_external_bridges' ), 9999 );
