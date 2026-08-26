<?php
/**
 * Plugin Name: Chattanooga Music Scene Weekend Feature
 * Description: Site-specific publishing tools for Chattanooga Music Scene.
 * Version: 0.1.0
 * Author: Chattanooga Music Scene
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: chattanooga-music-scene-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMS_CORE_VERSION', '0.1.0' );
define( 'CMS_CORE_FILE', __FILE__ );
define( 'CMS_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CMS_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once CMS_CORE_DIR . 'includes/class-cms-weekend-posts.php';

CMS_Weekend_Posts::instance();

register_activation_hook( CMS_CORE_FILE, array( 'CMS_Weekend_Posts', 'activate' ) );
register_deactivation_hook( CMS_CORE_FILE, array( 'CMS_Weekend_Posts', 'deactivate' ) );
