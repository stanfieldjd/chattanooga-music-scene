<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

function cmsa_package_privacy_flatten( $value, array &$parts, $depth = 0 ) {
	if ( $depth > 5 ) {
		return;
	}
	if ( is_string( $value ) || is_numeric( $value ) ) {
		$parts[] = (string) $value;
		return;
	}
	if ( is_bool( $value ) ) {
		$parts[] = $value ? 'true' : 'false';
		return;
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$parts[] = (string) $key;
			cmsa_package_privacy_flatten( $item, $parts, $depth + 1 );
		}
		return;
	}
	if ( is_object( $value ) ) {
		cmsa_package_privacy_flatten( get_object_vars( $value ), $parts, $depth + 1 );
	}
}

function cmsa_package_privacy_variants( $marker ) {
	return array_unique(
		array(
			(string) $marker,
			rawurlencode( (string) $marker ),
			urlencode( (string) $marker ),
			base64_encode( (string) $marker ),
		)
	);
}

function cmsa_package_privacy_safe_keys( $value ) {
	if ( is_array( $value ) ) {
		$keys = array_map( 'strval', array_keys( $value ) );
		sort( $keys );
		return $keys;
	}
	if ( is_string( $value ) && '' !== $value ) {
		$parsed = array();
		parse_str( $value, $parsed );
		if ( $parsed ) {
			$keys = array_map( 'strval', array_keys( $parsed ) );
			sort( $keys );
			return $keys;
		}
	}
	return array();
}

$nonce = strtolower( wp_generate_password( 12, false, false ) );
$private_markers = array(
	'member_email'    => 'cmsa-member-' . $nonce . '@example.invalid',
	'private_content' => 'cmsa-private-content-' . $nonce,
	'order_data'      => 'cmsa-order-' . $nonce,
	'credential_data' => 'cmsa-credential-' . $nonce,
	'backup_content'  => 'cmsa-backup-' . $nonce,
);

$user_login = sanitize_user( 'cmsa_privacy_' . $nonce, true );
$user_id = wp_insert_user(
	array(
		'user_login'   => $user_login,
		'user_pass'    => wp_generate_password( 24, true, true ),
		'user_email'   => $private_markers['member_email'],
		'display_name' => 'CMSA Privacy Fixture',
	)
);
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: could not create disposable member privacy fixture.\n" );
	exit( 1 );
}

$post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'private',
		'post_title'   => 'CMSA Privacy Fixture',
		'post_content' => $private_markers['private_content'],
	),
	true
);
if ( is_wp_error( $post_id ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: could not create disposable private-content fixture.\n" );
	exit( 1 );
}

update_option( 'cmsa_lab_private_order_marker', $private_markers['order_data'], false );
update_option( 'cmsa_lab_private_credential_marker', $private_markers['credential_data'], false );
$backup_directory = CMSA_Backups::get_storage_directory();
if ( is_wp_error( $backup_directory ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: backup directory unavailable for privacy fixture.\n" );
	exit( 1 );
}
$backup_marker_path = trailingslashit( $backup_directory ) . 'cmsa-package-privacy-marker.txt';
if ( false === file_put_contents( $backup_marker_path, $private_markers['backup_content'], LOCK_EX ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: could not create disposable backup-content privacy fixture.\n" );
	exit( 1 );
}

$captured_requests = array();
$leaked_marker_classes = array();
$http_filter = function ( $args, $url ) use ( &$captured_requests, &$leaked_marker_classes, $private_markers ) {
	$parts = array();
	cmsa_package_privacy_flatten( $url, $parts );
	cmsa_package_privacy_flatten( $args, $parts );
	$haystack = implode( "\n", $parts );

	foreach ( $private_markers as $label => $marker ) {
		foreach ( cmsa_package_privacy_variants( $marker ) as $variant ) {
			if ( '' !== $variant && false !== strpos( $haystack, $variant ) ) {
				$leaked_marker_classes[ $label ] = true;
				break;
			}
		}
	}

	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$method = isset( $args['method'] ) ? strtoupper( (string) $args['method'] ) : 'GET';
	$header_fields = isset( $args['headers'] ) && is_array( $args['headers'] ) ? array_map( 'strval', array_keys( $args['headers'] ) ) : array();
	sort( $header_fields );
	$body_fields = isset( $args['body'] ) ? cmsa_package_privacy_safe_keys( $args['body'] ) : array();

	$captured_requests[] = array(
		'host'          => $host,
		'path'          => $path,
		'method'        => $method,
		'header_fields' => $header_fields,
		'body_fields'   => $body_fields,
	);
	return $args;
};
add_filter( 'http_request_args', $http_filter, 10, 2 );

$updates = new CMSA_Updates();

$install = $updates->install_plugin( 'classic-widgets' );
if ( is_wp_error( $install ) || empty( $install['installed'] ) || empty( $install['plugin'] ) ) {
	$message = is_wp_error( $install ) ? $install->get_error_message() : 'plugin install did not report success';
	fwrite( STDERR, "wordpress-org-package-cli: plugin installation failed: {$message}\n" );
	exit( 1 );
}
$installed_plugin = $install['plugin'];
if ( ! isset( get_plugins()[ $installed_plugin ] ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: installed plugin not found after install.\n" );
	exit( 1 );
}

$activate = $updates->activate_plugin( $installed_plugin );
if ( is_wp_error( $activate ) || empty( $activate['activated'] ) || ! is_plugin_active( $installed_plugin ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: plugin activation failed.\n" );
	exit( 1 );
}

$deactivate = $updates->deactivate_plugin( $installed_plugin );
if ( is_wp_error( $deactivate ) || empty( $deactivate['deactivated'] ) || is_plugin_active( $installed_plugin ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: plugin deactivation failed.\n" );
	exit( 1 );
}

$theme_install = $updates->install_theme( 'twentytwentyone' );
if ( is_wp_error( $theme_install ) || empty( $theme_install['installed'] ) || empty( $theme_install['theme'] ) ) {
	$message = is_wp_error( $theme_install ) ? $theme_install->get_error_message() : 'theme install did not report success';
	fwrite( STDERR, "wordpress-org-package-cli: theme installation failed: {$message}\n" );
	exit( 1 );
}
if ( ! wp_get_theme( $theme_install['theme'] )->exists() ) {
	fwrite( STDERR, "wordpress-org-package-cli: installed theme not found after install.\n" );
	exit( 1 );
}

$legacy_plugin = 'classic-editor/classic-editor.php';
$plugins = get_plugins();
if ( ! isset( $plugins[ $legacy_plugin ] ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: preinstalled legacy Classic Editor fixture is missing.\n" );
	exit( 1 );
}
$before = $plugins[ $legacy_plugin ]['Version'];
if ( '1.6' !== $before ) {
	fwrite( STDERR, "wordpress-org-package-cli: expected Classic Editor 1.6 precondition, found {$before}.\n" );
	exit( 1 );
}

$updated = $updates->update_plugin( $legacy_plugin, '1.6' );
if ( is_wp_error( $updated ) || empty( $updated['updated'] ) || empty( $updated['version'] ) ) {
	$message = is_wp_error( $updated ) ? $updated->get_error_message() : 'plugin update did not report success';
	fwrite( STDERR, "wordpress-org-package-cli: controlled plugin update failed: {$message}\n" );
	exit( 1 );
}
if ( version_compare( $updated['version'], '1.6', '<=' ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: plugin version did not advance.\n" );
	exit( 1 );
}
if ( empty( $updated['backup_id'] ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: update completed without rollback backup id.\n" );
	exit( 1 );
}

remove_filter( 'http_request_args', $http_filter, 10 );

if ( ! empty( $leaked_marker_classes ) ) {
	fwrite( STDERR, 'wordpress-org-package-cli: private marker classes entered a package request: ' . implode( ',', array_keys( $leaked_marker_classes ) ) . "\n" );
	exit( 1 );
}
if ( empty( $captured_requests ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: no WordPress HTTP requests were captured during package operations.\n" );
	exit( 1 );
}

$hosts = array();
$unexpected_hosts = array();
$saw_api = false;
$saw_downloads = false;
foreach ( $captured_requests as $request ) {
	$host = $request['host'];
	if ( '' !== $host ) {
		$hosts[ $host ] = true;
	}
	if ( 'api.wordpress.org' === $host ) {
		$saw_api = true;
	}
	if ( 'downloads.wordpress.org' === $host ) {
		$saw_downloads = true;
	}
	if ( 'wordpress.org' !== $host && ( strlen( $host ) <= 14 || '.wordpress.org' !== substr( $host, -14 ) ) ) {
		$unexpected_hosts[ $host ] = true;
	}
}
if ( $unexpected_hosts ) {
	fwrite( STDERR, 'wordpress-org-package-cli: unexpected package-request hosts: ' . implode( ',', array_keys( $unexpected_hosts ) ) . "\n" );
	exit( 1 );
}
if ( ! $saw_api || ! $saw_downloads ) {
	fwrite( STDERR, "wordpress-org-package-cli: expected WordPress API and download hosts were not both observed.\n" );
	exit( 1 );
}

$evidence = array(
	'generated_at'          => gmdate( 'c' ),
	'request_count'         => count( $captured_requests ),
	'requests'              => $captured_requests,
	'marker_classes_tested' => array_keys( $private_markers ),
	'leaked_marker_classes' => array(),
	'host_policy'            => 'wordpress.org and subdomains only',
);
if ( false === file_put_contents( '/tmp/cmsa-package-network-privacy.json', wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), LOCK_EX ) ) {
	fwrite( STDERR, "wordpress-org-package-cli: could not write privacy evidence artifact.\n" );
	exit( 1 );
}

wp_delete_user( $user_id );
wp_delete_post( $post_id, true );
delete_option( 'cmsa_lab_private_order_marker' );
delete_option( 'cmsa_lab_private_credential_marker' );
@unlink( $backup_marker_path );

$host_list = array_keys( $hosts );
sort( $host_list );
printf(
	"wordpress-org-package-cli: PASS plugin=%s theme=%s classic-editor=%s->%s backup=%s privacy_requests=%d hosts=%s private_markers=absent\n",
	$installed_plugin,
	$theme_install['theme'],
	$before,
	$updated['version'],
	$updated['backup_id'],
	count( $captured_requests ),
	implode( ',', $host_list )
);
