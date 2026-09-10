<?php
/**
 * Plugin Name: Chattanooga CMS Admin
 * Description: Replacement Chattanooga CMS Admin implementation using plugin-agnostic public WordPress contracts.
 * Version: 0.0.1-replacement-lab
 * Author: Chattanooga Music Scene
 * Requires at least: 7.1
 * Requires PHP: 8.0
 * Text Domain: chattanooga-cms-admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CUA_VERSION', '0.0.1-replacement-lab' );
define( 'CUA_DIR', plugin_dir_path( __FILE__ ) );

require_once CUA_DIR . 'includes/class-cua-control-plane-guard.php';
require_once CUA_DIR . 'includes/class-cua-platform-services.php';
require_once CUA_DIR . 'includes/class-cua-platform-inventory.php';
require_once CUA_DIR . 'includes/class-cua-platform-theme-updater.php';
require_once CUA_DIR . 'includes/class-cua-platform-theme-lifecycle.php';
require_once CUA_DIR . 'includes/class-cua-platform-component-lifecycle.php';
require_once CUA_DIR . 'includes/class-cua-platform-update-policy.php';
require_once CUA_DIR . 'includes/class-cua-platform-package-lifecycle.php';
require_once CUA_DIR . 'includes/class-cua-rest-bridge.php';
require_once CUA_DIR . 'includes/class-cua-ability-bridge.php';

add_action( 'wp_abilities_api_categories_init', array( 'CUA_Ability_Bridge', 'register_category' ) );
add_action( 'wp_abilities_api_init', array( 'CUA_Ability_Bridge', 'register_catalog_ability' ), 5 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Services', 'register_abilities' ), 10 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Inventory', 'register_abilities' ), 11 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Theme_Updater', 'register_ability' ), 12 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Theme_Lifecycle', 'register_ability' ), 13 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Component_Lifecycle', 'register_abilities' ), 14 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Update_Policy', 'register_abilities' ), 15 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Package_Lifecycle', 'register_abilities' ), 16 );
add_action( 'wp_abilities_api_init', array( 'CUA_REST_Bridge', 'register_external_bridges' ), 9998 );
add_action( 'wp_abilities_api_init', array( 'CUA_Ability_Bridge', 'register_external_bridges' ), 9999 );
