<?php
/**
 * Plugin Name: Chattanooga CMS Admin
 * Description: Universal Chattanooga CMS Admin replacement using plugin-agnostic public WordPress contracts and verified intrinsic platform services.
 * Version: 1.0.0
 * Author: Chattanooga Music Scene
 * Requires at least: 7.1
 * Requires PHP: 8.0
 * Text Domain: chattanooga-cms-admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CUA_VERSION', '1.0.0' );
define( 'CUA_DIR', plugin_dir_path( __FILE__ ) );

require_once CUA_DIR . 'includes/class-cua-local-storage.php';
require_once CUA_DIR . 'includes/class-cua-audit.php';
require_once CUA_DIR . 'includes/class-cua-control-plane-guard.php';
require_once CUA_DIR . 'includes/class-cua-platform-services.php';
require_once CUA_DIR . 'includes/class-cua-platform-inventory.php';
require_once CUA_DIR . 'includes/class-cua-platform-theme-updater.php';
require_once CUA_DIR . 'includes/class-cua-platform-theme-lifecycle.php';
require_once CUA_DIR . 'includes/class-cua-platform-component-lifecycle.php';
require_once CUA_DIR . 'includes/class-cua-platform-update-policy.php';
require_once CUA_DIR . 'includes/class-cua-platform-package-lifecycle.php';
require_once CUA_DIR . 'includes/class-cua-backups.php';
require_once CUA_DIR . 'includes/class-cua-platform-core-maintenance.php';
require_once CUA_DIR . 'includes/class-cua-platform-settings.php';
require_once CUA_DIR . 'includes/class-cua-rest-bridge.php';
require_once CUA_DIR . 'includes/class-cua-ability-bridge.php';

CUA_Audit::bootstrap();

add_action( 'wp_abilities_api_categories_init', array( 'CUA_Ability_Bridge', 'register_category' ) );
add_action( 'wp_abilities_api_init', array( 'CUA_Ability_Bridge', 'register_catalog_ability' ), 5 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Services', 'register_abilities' ), 10 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Inventory', 'register_abilities' ), 11 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Theme_Updater', 'register_ability' ), 12 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Theme_Lifecycle', 'register_ability' ), 13 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Component_Lifecycle', 'register_abilities' ), 14 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Update_Policy', 'register_abilities' ), 15 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Package_Lifecycle', 'register_abilities' ), 16 );
add_action( 'wp_abilities_api_init', array( 'CUA_Audit', 'register_ability' ), 17 );
add_action( 'wp_abilities_api_init', array( 'CUA_Backups', 'register_abilities' ), 18 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Core_Maintenance', 'register_abilities' ), 19 );
add_action( 'wp_abilities_api_init', array( 'CUA_Platform_Settings', 'register_abilities' ), 20 );
add_action( 'wp_abilities_api_init', array( 'CUA_REST_Bridge', 'register_external_bridges' ), 9998 );
add_action( 'wp_abilities_api_init', array( 'CUA_Ability_Bridge', 'register_external_bridges' ), 9999 );
