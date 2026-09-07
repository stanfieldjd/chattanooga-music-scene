<?php
/**
 * Plugin Name: Chattanooga CMS Admin
 * Description: Site-owned WordPress administration abilities for Chattanooga Music Scene AI operations.
 * Version: 0.1.0
 * Author: Chattanooga Music Scene
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Text Domain: chattanooga-cms-admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMSA_VERSION', '0.1.0' );
define( 'CMSA_FILE', __FILE__ );
define( 'CMSA_DIR', plugin_dir_path( __FILE__ ) );

require_once CMSA_DIR . 'includes/class-cmsa-errors.php';
require_once CMSA_DIR . 'includes/class-cmsa-audit.php';
require_once CMSA_DIR . 'includes/class-cmsa-backups.php';
require_once CMSA_DIR . 'includes/class-cmsa-health.php';
require_once CMSA_DIR . 'includes/class-cmsa-updates.php';
require_once CMSA_DIR . 'includes/class-cmsa-lifecycle.php';
require_once CMSA_DIR . 'includes/class-cmsa-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-plugin.php';

CMSA_Plugin::instance();

register_activation_hook( CMSA_FILE, array( 'CMSA_Backups', 'activate' ) );
