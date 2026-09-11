<?php
/**
 * Plugin Name: Chattanooga Music Marketplace
 * Description: Unified Marketplace presentation for Chattanooga Music Scene.
 * Version: 0.1.1
 * Author: Chattanooga Music Scene
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: chattanooga-music-marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMS_MARKETPLACE_VERSION', '0.1.1' );
define( 'CMS_MARKETPLACE_FILE', __FILE__ );
define( 'CMS_MARKETPLACE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CMS_MARKETPLACE_URL', plugin_dir_url( __FILE__ ) );

require_once CMS_MARKETPLACE_DIR . 'includes/class-cms-unified-marketplace.php';

CMS_Unified_Marketplace::instance();
