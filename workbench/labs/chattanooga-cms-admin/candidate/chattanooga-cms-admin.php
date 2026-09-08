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
require_once CMSA_DIR . 'includes/class-cmsa-content.php';
require_once CMSA_DIR . 'includes/class-cmsa-content-deletion.php';
require_once CMSA_DIR . 'includes/class-cmsa-content-status.php';
require_once CMSA_DIR . 'includes/class-cmsa-content-taxonomy.php';
require_once CMSA_DIR . 'includes/class-cmsa-navigation.php';
require_once CMSA_DIR . 'includes/class-cmsa-members.php';
require_once CMSA_DIR . 'includes/class-cmsa-member-mutations.php';
require_once CMSA_DIR . 'includes/class-cmsa-events-manager.php';
require_once CMSA_DIR . 'includes/class-cmsa-events-manager-cache.php';
require_once CMSA_DIR . 'includes/class-cmsa-events-manager-mutations.php';
require_once CMSA_DIR . 'includes/class-cmsa-events-manager-deletion.php';
require_once CMSA_DIR . 'includes/class-cmsa-events-manager-taxonomy.php';
require_once CMSA_DIR . 'includes/class-cmsa-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-content-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-content-deletion-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-content-status-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-content-taxonomy-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-navigation-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-member-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-member-mutation-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-events-manager-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-events-manager-mutation-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-events-manager-deletion-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-events-manager-taxonomy-abilities.php';
require_once CMSA_DIR . 'includes/class-cmsa-plugin.php';

CMSA_Events_Manager_Cache::register();
CMSA_Plugin::instance();

register_activation_hook( CMSA_FILE, array( 'CMSA_Backups', 'activate' ) );
