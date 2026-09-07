<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/update.php';

$plugin = 'cmsa-deterministic-update/cmsa-deterministic-update.php';
$slug = 'cmsa-deterministic-update';
$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug;
$main_file = trailingslashit( $plugin_dir ) . 'cmsa-deterministic-update.php';
$state_file = trailingslashit( $plugin_dir ) . 'state.txt';
$good_zip = trailingslashit( sys_get_temp_dir() ) . 'cmsa-deterministic-update-good.zip';
$bad_zip = trailingslashit( sys_get_temp_dir() ) . 'cmsa-deterministic-update-bad.zip';
$good_url = 'https://downloads.wordpress.org/plugin/cmsa-deterministic-update-2.0.0.zip';
$bad_url = 'https://downloads.wordpress.org/plugin/cmsa-deterministic-update-malformed.zip';

$remove_tree = static function ( $path ) use ( &$remove_tree ) {
	if ( ! file_exists( $path ) ) {
		return;
	}
	if ( is_file( $path ) || is_link( $path ) ) {
		@unlink( $path );
		return;
	}
	foreach ( scandir( $path ) ?: array() as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}
		$remove_tree( trailingslashit( $path ) . $entry );
	}
	@rmdir( $path );
};

$write_fixture = static function ( $version, $state ) use ( $plugin_dir, $main_file, $state_file, $remove_tree ) {
	$remove_tree( $plugin_dir );
	if ( ! wp_mkdir_p( $plugin_dir ) ) {
		return false;
	}
	$plugin_php = "<?php\n/*\nPlugin Name: CMSA Deterministic Update Fixture\nVersion: {$version}\n*/\n";
	return false !== file_put_contents( $main_file, $plugin_php, LOCK_EX )
		&& false !== file_put_contents( $state_file, $state . "\n", LOCK_EX );
};

if ( ! $write_fixture( '1.0.0', 'v1' ) ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: could not create v1 fixture.\n" );
	exit( 1 );
}
wp_clean_plugins_cache( true );
$activation = activate_plugin( $plugin, '', false, true );
if ( is_wp_error( $activation ) || ! is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: could not activate v1 fixture.\n" );
	exit( 1 );
}

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: ZipArchive unavailable.\n" );
	exit( 1 );
}
$zip = new ZipArchive();
if ( true !== $zip->open( $good_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: could not create v2 package.\n" );
	exit( 1 );
}
$zip->addEmptyDir( $slug );
$zip->addFromString( $slug . '/cmsa-deterministic-update.php', "<?php\n/*\nPlugin Name: CMSA Deterministic Update Fixture\nVersion: 2.0.0\n*/\n" );
$zip->addFromString( $slug . '/state.txt', "v2\n" );
$zip->close();
file_put_contents( $bad_zip, 'cmsa-not-a-zip-package', LOCK_EX );

$mode = 'none';
$transient_filter = static function ( $value ) use ( &$mode, $plugin, $slug, $good_url, $bad_url ) {
	if ( ! is_object( $value ) ) {
		$value = (object) array();
	}
	if ( ! isset( $value->response ) || ! is_array( $value->response ) ) {
		$value->response = array();
	}
	unset( $value->response[ $plugin ] );
	if ( 'good' === $mode || 'bad' === $mode ) {
		$value->response[ $plugin ] = (object) array(
			'id'           => 'w.org/plugins/' . $slug,
			'slug'         => $slug,
			'plugin'       => $plugin,
			'new_version'  => '2.0.0',
			'url'          => 'https://wordpress.org/plugins/' . $slug . '/',
			'package'      => 'good' === $mode ? $good_url : $bad_url,
			'tested'       => '7.1',
			'requires'     => '6.9',
			'requires_php' => '7.4',
		);
	}
	return $value;
};
add_filter( 'pre_set_site_transient_update_plugins', $transient_filter, PHP_INT_MAX );

$http_filter = static function ( $preempt, $args, $url ) use ( $good_url, $bad_url, $good_zip, $bad_zip ) {
	$source = null;
	if ( $good_url === $url ) {
		$source = $good_zip;
	} elseif ( $bad_url === $url ) {
		$source = $bad_zip;
	}
	if ( null === $source ) {
		return $preempt;
	}

	$body = '';
	$filename = null;
	if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
		$filename = $args['filename'];
		if ( ! copy( $source, $filename ) ) {
			return new WP_Error( 'cmsa_fixture_stream', 'Fixture package stream failed.' );
		}
	} else {
		$body = (string) file_get_contents( $source );
	}

	return array(
		'headers'  => array(),
		'body'     => $body,
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => $filename,
	);
};
add_filter( 'pre_http_request', $http_filter, PHP_INT_MAX, 3 );

$updates = new CMSA_Updates();

$mode = 'none';
delete_site_transient( 'update_plugins' );
$no_update = $updates->update_plugin( $plugin, '1.0.0' );
if ( ! is_wp_error( $no_update ) || 'cmsa_plugin_no_update' !== $no_update->get_error_code() ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: no-update case did not fail with cmsa_plugin_no_update.\n" );
	exit( 1 );
}

$mode = 'good';
delete_site_transient( 'update_plugins' );
$updated = $updates->update_plugin( $plugin, '1.0.0' );
wp_clean_plugins_cache( true );
$plugins_after = get_plugins();
if ( is_wp_error( $updated ) || empty( $updated['updated'] ) || '2.0.0' !== ( $plugins_after[ $plugin ]['Version'] ?? '' ) ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: deterministic v1-to-v2 update failed.\n" );
	exit( 1 );
}
if ( ! is_file( $state_file ) || "v2\n" !== file_get_contents( $state_file ) || ! is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: v2 content or active-state verification failed.\n" );
	exit( 1 );
}
if ( empty( $updated['backup_id'] ) ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: successful update did not report a rollback backup.\n" );
	exit( 1 );
}

if ( ! $write_fixture( '1.0.0', 'v1' ) ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: could not reset v1 fixture.\n" );
	exit( 1 );
}
wp_clean_plugins_cache( true );
if ( ! is_plugin_active( $plugin ) ) {
	$activation = activate_plugin( $plugin, '', false, true );
	if ( is_wp_error( $activation ) ) {
		fwrite( STDERR, "deterministic-plugin-update-cli: could not reactivate reset v1 fixture.\n" );
		exit( 1 );
	}
}

$mode = 'bad';
delete_site_transient( 'update_plugins' );
$malformed = $updates->update_plugin( $plugin, '1.0.0' );
if ( ! is_wp_error( $malformed ) || 'cmsa_plugin_update_failed' !== $malformed->get_error_code() ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: malformed package did not produce cmsa_plugin_update_failed.\n" );
	exit( 1 );
}
$error_data = $malformed->get_error_data();
if ( ! is_array( $error_data ) || empty( $error_data['backup_id'] ) || empty( $error_data['rolled_back'] ) ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: malformed package failure did not report successful rollback.\n" );
	exit( 1 );
}
wp_clean_plugins_cache( true );
$plugins_restored = get_plugins();
if ( '1.0.0' !== ( $plugins_restored[ $plugin ]['Version'] ?? '' ) || ! is_file( $state_file ) || "v1\n" !== file_get_contents( $state_file ) ) {
	fwrite( STDERR, "deterministic-plugin-update-cli: malformed package rollback did not restore exact v1 fixture.\n" );
	exit( 1 );
}

remove_filter( 'pre_http_request', $http_filter, PHP_INT_MAX );
remove_filter( 'pre_set_site_transient_update_plugins', $transient_filter, PHP_INT_MAX );
@unlink( $good_zip );
@unlink( $bad_zip );

printf(
	"deterministic-plugin-update-cli: PASS no-update=blocked update=1.0.0->2.0.0 malformed=rolled-back backup=%s\n",
	$error_data['backup_id']
);
