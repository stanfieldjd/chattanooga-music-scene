<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this probe runs.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$ability = wp_get_ability( 'chattanooga-cms-admin/update-plugin' );
if ( ! $ability instanceof WP_Ability ) {
	fwrite( STDERR, "CMS Admin update-plugin ability was not registered.\n" );
	exit( 1 );
}

$plugin = 'cua-upgrade-fixture/cua-upgrade-fixture.php';
$base_url = rtrim( (string) getenv( 'CMSA_UPGRADE_PACKAGE_BASE' ), '/' );
if ( '' === $base_url ) {
	fwrite( STDERR, "Upgrade package base URL is missing.\n" );
	exit( 1 );
}

$plugins = get_plugins();
if ( ! isset( $plugins[ $plugin ] ) || '1.0.0' !== (string) $plugins[ $plugin ]['Version'] || ! is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "Disposable upgrade fixture is not active at version 1.0.0.\n" );
	exit( 1 );
}

$self_plugin = plugin_basename( CUA_DIR . 'chattanooga-cms-admin.php' );
$self_plugins = get_plugins();
$self_version = isset( $self_plugins[ $self_plugin ]['Version'] ) ? (string) $self_plugins[ $self_plugin ]['Version'] : '';
$self_result = $ability->execute(
	array(
		'plugin'           => $self_plugin,
		'expected_version' => $self_version,
	)
);
if ( ! is_wp_error( $self_result ) || 'cmsa_self_update_forbidden' !== $self_result->get_error_code() ) {
	fwrite( STDERR, "CMS Admin did not protect its own replacement files.\n" );
	exit( 1 );
}

$conflict_result = $ability->execute(
	array(
		'plugin'           => $plugin,
		'expected_version' => '0.9.0',
	)
);
if ( ! is_wp_error( $conflict_result ) || 'cmsa_plugin_version_conflict' !== $conflict_result->get_error_code() ) {
	fwrite( STDERR, "Plugin update did not enforce exact-version conflict control.\n" );
	exit( 1 );
}

add_filter(
	'http_request_host_is_external',
	static function ( $external, $host ) {
		return '127.0.0.1' === $host ? true : $external;
	},
	10,
	2
);

$GLOBALS['cmsa_probe_install_result'] = null;
add_filter(
	'upgrader_post_install',
	static function ( $response, $hook_extra, $result ) use ( $plugin ) {
		if ( isset( $hook_extra['plugin'] ) && $plugin === $hook_extra['plugin'] ) {
			$GLOBALS['cmsa_probe_install_result'] = array(
				'source'             => isset( $result['source'] ) ? (string) $result['source'] : '',
				'destination'        => isset( $result['destination'] ) ? (string) $result['destination'] : '',
				'destination_name'   => isset( $result['destination_name'] ) ? (string) $result['destination_name'] : '',
				'remote_destination' => isset( $result['remote_destination'] ) ? (string) $result['remote_destination'] : '',
				'source_files'       => isset( $result['source_files'] ) ? array_values( (array) $result['source_files'] ) : array(),
			);
		}
		return $response;
	},
	99,
	3
);

$offer = new stdClass();
$offer->slug = 'cua-upgrade-fixture';
$offer->plugin = $plugin;
$offer->new_version = '1.1.0';
$offer->package = $base_url . '/cua-upgrade-fixture-1.1.0.zip';
$updates = new stdClass();
$updates->last_checked = time();
$updates->checked = array( $plugin => '1.0.0' );
$updates->response = array( $plugin => $offer );
set_site_transient( 'update_plugins', $updates );

$success = $ability->execute(
	array(
		'plugin'           => $plugin,
		'expected_version' => '1.0.0',
	)
);
if ( is_wp_error( $success ) ) {
	fwrite(
		STDERR,
		'Successful plugin update failed: ' . $success->get_error_code() . ' ' . $success->get_error_message() . ' data=' . wp_json_encode( $success->get_error_data() ) . ' install=' . wp_json_encode( $GLOBALS['cmsa_probe_install_result'] ) . "\n"
	);
	exit( 1 );
}
wp_clean_plugins_cache( false );
$plugins = get_plugins();
if ( '1.1.0' !== (string) ( $plugins[ $plugin ]['Version'] ?? '' ) || ! is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "Plugin update success was not verified on disk with active state preserved.\n" );
	exit( 1 );
}
if ( '1.0.0' !== ( $success['previous_version'] ?? '' ) || '1.1.0' !== ( $success['version'] ?? '' ) || 'wordpress_temp_backup' !== ( $success['rollback'] ?? '' ) ) {
	fwrite( STDERR, "Plugin update result did not report the verified transaction state.\n" );
	exit( 1 );
}

$mismatch_offer = new stdClass();
$mismatch_offer->slug = 'cua-upgrade-fixture';
$mismatch_offer->plugin = $plugin;
$mismatch_offer->new_version = '1.2.0';
$mismatch_offer->package = $base_url . '/cua-upgrade-fixture-mismatch.zip';
$mismatch_updates = new stdClass();
$mismatch_updates->last_checked = time();
$mismatch_updates->checked = array( $plugin => '1.1.0' );
$mismatch_updates->response = array( $plugin => $mismatch_offer );
set_site_transient( 'update_plugins', $mismatch_updates );

$rollback_result = $ability->execute(
	array(
		'plugin'           => $plugin,
		'expected_version' => '1.1.0',
	)
);
if ( ! is_wp_error( $rollback_result ) || 'cmsa_plugin_update_verification_failed' !== $rollback_result->get_error_code() ) {
	fwrite( STDERR, "A mismatched update package did not fail closed after verification.\n" );
	exit( 1 );
}
wp_clean_plugins_cache( false );
$plugins = get_plugins();
if ( '1.1.0' !== (string) ( $plugins[ $plugin ]['Version'] ?? '' ) || ! is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "Plugin rollback did not restore version 1.1.0 with active state preserved.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( false !== $ability->check_permissions( array( 'plugin' => $plugin, 'expected_version' => '1.1.0' ) ) ) {
	fwrite( STDERR, "Plugin update ability allowed an anonymous user.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
delete_site_transient( 'update_plugins' );

echo 'cmsa-plugin-upgrade-cli: PASS core_upgrader=verified temp_backup=verified success_readback=verified active_state=preserved conflict_control=verified self_protection=verified mismatch_detection=verified rollback=verified admin_boundary=verified provider_adapter=absent' . "\n";
exit( 0 );
