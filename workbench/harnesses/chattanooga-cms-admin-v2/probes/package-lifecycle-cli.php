<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$install_plugin   = wp_get_ability( 'chattanooga-cms-admin/install-plugin' );
$install_theme    = wp_get_ability( 'chattanooga-cms-admin/install-theme' );
$activate_plugin  = wp_get_ability( 'chattanooga-cms-admin/activate-plugin' );
$deactivate_plugin = wp_get_ability( 'chattanooga-cms-admin/deactivate-plugin' );
if ( ! $install_plugin instanceof WP_Ability || ! $install_theme instanceof WP_Ability || ! $activate_plugin instanceof WP_Ability || ! $deactivate_plugin instanceof WP_Ability ) {
	fwrite( STDERR, "Package lifecycle abilities are missing.\n" );
	exit( 1 );
}

$package_dir = rtrim( (string) getenv( 'CMSA_V2_PACKAGE_DIR' ), DIRECTORY_SEPARATOR );
$plugin_package = $package_dir . DIRECTORY_SEPARATOR . 'neptune-install.zip';
$theme_package  = $package_dir . DIRECTORY_SEPARATOR . 'aurora-install.zip';
if ( ! is_file( $plugin_package ) || ! is_file( $theme_package ) ) {
	fwrite( STDERR, "Package lifecycle ZIP fixtures are missing.\n" );
	exit( 1 );
}

$plugin_slug = 'neptune-install';
$theme_slug  = 'aurora-install';
$plugin_file = 'neptune-install/neptune-install.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';

if ( isset( get_plugins()[ $plugin_file ] ) || wp_get_theme( $theme_slug )->exists() ) {
	fwrite( STDERR, "Install targets unexpectedly exist before the test.\n" );
	exit( 1 );
}
$active_theme_before = get_stylesheet();

$plugin_api_filter = static function ( $result, $action, $args ) use ( $plugin_slug, $plugin_package ) {
	if ( 'plugin_information' === $action && is_object( $args ) && ( $args->slug ?? '' ) === $plugin_slug ) {
		return (object) array(
			'slug'          => $plugin_slug,
			'name'          => 'Neptune Install Fixture',
			'version'       => '3.2.1',
			'download_link' => $plugin_package,
		);
	}
	return $result;
};
$theme_api_filter = static function ( $result, $action, $args ) use ( $theme_slug, $theme_package ) {
	if ( 'theme_information' === $action && is_object( $args ) && ( $args->slug ?? '' ) === $theme_slug ) {
		return (object) array(
			'slug'          => $theme_slug,
			'name'          => 'Aurora Install Fixture',
			'version'       => '2.4.0',
			'download_link' => $theme_package,
		);
	}
	return $result;
};
add_filter( 'plugins_api', $plugin_api_filter, 10, 3 );
add_filter( 'themes_api', $theme_api_filter, 10, 3 );

$invalid_plugin = $install_plugin->execute( array( 'slug' => '../invalid' ) );
$invalid_theme  = $install_theme->execute( array( 'slug' => 'Invalid Theme' ) );
if ( ! is_wp_error( $invalid_plugin ) || 'cmsa_invalid_slug' !== $invalid_plugin->get_error_code()
	|| ! is_wp_error( $invalid_theme ) || 'cmsa_invalid_slug' !== $invalid_theme->get_error_code() ) {
	fwrite( STDERR, "Package lifecycle accepted an invalid slug.\n" );
	exit( 1 );
}

$plugin_install = $install_plugin->execute( array( 'slug' => $plugin_slug ) );
if ( is_wp_error( $plugin_install ) ) {
	fwrite( STDERR, 'Plugin install failed: ' . $plugin_install->get_error_code() . ' ' . $plugin_install->get_error_message() . "\n" );
	exit( 1 );
}
wp_clean_plugins_cache( false );
$plugins = get_plugins();
if ( ! isset( $plugins[ $plugin_file ] ) || '3.2.1' !== (string) $plugins[ $plugin_file ]['Version'] || is_plugin_active( $plugin_file ) ) {
	fwrite( STDERR, "Installed plugin did not match the verified inactive package state.\n" );
	exit( 1 );
}
if ( $plugin_file !== ( $plugin_install['plugin'] ?? '' ) || '3.2.1' !== ( $plugin_install['version'] ?? '' ) ) {
	fwrite( STDERR, "Plugin install result did not report verified identity/version.\n" );
	exit( 1 );
}
$plugin_repeat = $install_plugin->execute( array( 'slug' => $plugin_slug ) );
if ( ! is_wp_error( $plugin_repeat ) || 'cmsa_plugin_already_installed' !== $plugin_repeat->get_error_code() ) {
	fwrite( STDERR, "Plugin re-install did not fail closed.\n" );
	exit( 1 );
}

$activation = $activate_plugin->execute( array( 'plugin' => $plugin_file ) );
if ( is_wp_error( $activation ) || empty( $activation['active'] ) || ! is_plugin_active( $plugin_file ) ) {
	fwrite( STDERR, "Plugin activation did not persist.\n" );
	exit( 1 );
}
$activation_repeat = $activate_plugin->execute( array( 'plugin' => $plugin_file ) );
if ( is_wp_error( $activation_repeat ) || ! empty( $activation_repeat['changed'] ) ) {
	fwrite( STDERR, "Repeated plugin activation was not idempotent.\n" );
	exit( 1 );
}

$self_plugin = plugin_basename( CUA_DIR . 'chattanooga-cms-admin.php' );
$self_deactivate = $deactivate_plugin->execute( array( 'plugin' => $self_plugin ) );
if ( ! is_wp_error( $self_deactivate ) || 'cmsa_self_deactivation_forbidden' !== $self_deactivate->get_error_code() || ! is_plugin_active( $self_plugin ) ) {
	fwrite( STDERR, "Control-plane deactivation was not blocked.\n" );
	exit( 1 );
}

$deactivation = $deactivate_plugin->execute( array( 'plugin' => $plugin_file ) );
if ( is_wp_error( $deactivation ) || ! empty( $deactivation['active'] ) || is_plugin_active( $plugin_file ) ) {
	fwrite( STDERR, "Plugin deactivation did not persist.\n" );
	exit( 1 );
}
$deactivation_repeat = $deactivate_plugin->execute( array( 'plugin' => $plugin_file ) );
if ( is_wp_error( $deactivation_repeat ) || ! empty( $deactivation_repeat['changed'] ) ) {
	fwrite( STDERR, "Repeated plugin deactivation was not idempotent.\n" );
	exit( 1 );
}

$theme_install = $install_theme->execute( array( 'slug' => $theme_slug ) );
if ( is_wp_error( $theme_install ) ) {
	fwrite( STDERR, 'Theme install failed: ' . $theme_install->get_error_code() . ' ' . $theme_install->get_error_message() . "\n" );
	exit( 1 );
}
search_theme_directories( true );
$themes = wp_get_themes( array( 'errors' => null ) );
if ( ! isset( $themes[ $theme_slug ] ) || '2.4.0' !== (string) $themes[ $theme_slug ]->get( 'Version' ) || get_stylesheet() !== $active_theme_before ) {
	fwrite( STDERR, "Installed theme did not match the verified inactive package state.\n" );
	exit( 1 );
}
if ( '2.4.0' !== ( $theme_install['version'] ?? '' ) || ! empty( $theme_install['active'] ) ) {
	fwrite( STDERR, "Theme install result did not report verified identity/version.\n" );
	exit( 1 );
}
$theme_repeat = $install_theme->execute( array( 'slug' => $theme_slug ) );
if ( ! is_wp_error( $theme_repeat ) || 'cmsa_theme_already_installed' !== $theme_repeat->get_error_code() ) {
	fwrite( STDERR, "Theme re-install did not fail closed.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( false !== $install_plugin->check_permissions( array( 'slug' => $plugin_slug ) )
	|| false !== $install_theme->check_permissions( array( 'slug' => $theme_slug ) )
	|| false !== $activate_plugin->check_permissions( array( 'plugin' => $plugin_file ) )
	|| false !== $deactivate_plugin->check_permissions( array( 'plugin' => $plugin_file ) ) ) {
	fwrite( STDERR, "Anonymous package lifecycle administration was not blocked.\n" );
	exit( 1 );
}
wp_set_current_user( 1 );

remove_filter( 'plugins_api', $plugin_api_filter, 10 );
remove_filter( 'themes_api', $theme_api_filter, 10 );

$plugin_cleanup = delete_plugins( array( $plugin_file ) );
$theme_cleanup = delete_theme( $theme_slug );
wp_clean_plugins_cache( false );
search_theme_directories( true );
$themes = wp_get_themes( array( 'errors' => null ) );
if ( is_wp_error( $plugin_cleanup ) || false === $plugin_cleanup || is_wp_error( $theme_cleanup ) || false === $theme_cleanup
	|| isset( get_plugins()[ $plugin_file ] ) || isset( $themes[ $theme_slug ] ) || get_stylesheet() !== $active_theme_before ) {
	fwrite( STDERR, "Package lifecycle harness cleanup did not restore its initial installed state.\n" );
	exit( 1 );
}

echo "cmsa-v2-package-lifecycle: PASS plugin_install=verified theme_install=verified official_api_contract=verified package_input=absent activation=verified deactivation=verified repeated_state=idempotent self_deactivation=blocked invalid_slug=blocked reinstall=blocked admin_boundary=verified final_state=restored\n";
exit( 0 );
