<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

$package = getenv( 'CMSA_V2_CORE_PACKAGE' );
$package_root = getenv( 'CMSA_V2_CORE_PACKAGE_ROOT' );
$state_path = getenv( 'CMSA_V2_CORE_UPDATE_STATE' );
$target = '7.1.1';
$package_url = 'https://cmsa-v2.invalid/core/wordpress-7.1.1.zip';
if ( ! is_string( $package ) || ! is_file( $package ) || ! is_string( $package_root ) || ! is_dir( $package_root ) || ! is_string( $state_path ) || '' === $state_path ) {
	fwrite( STDERR, "Synthetic core update package environment is incomplete.\n" );
	exit( 1 );
}

function cmsa_v2_update_core_files( $root ) {
	$root = trailingslashit( wp_normalize_path( (string) $root ) );
	$files = array();
	foreach ( array( 'wp-admin', 'wp-includes' ) as $folder ) {
		$directory = $root . $folder;
		if ( ! is_dir( $directory ) ) {
			return new WP_Error( 'fixture_core_layout', 'Synthetic core package is missing a required directory.' );
		}
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $item ) {
			if ( ! $item->isFile() || $item->isLink() ) {
				continue;
			}
			$path = wp_normalize_path( $item->getPathname() );
			$relative = ltrim( substr( $path, strlen( $root ) ), '/' );
			$files[ $relative ] = $path;
		}
	}
	foreach ( @scandir( $root ) ?: array() as $name ) {
		if ( '.' === $name || '..' === $name || 'wp-config.php' === $name ) {
			continue;
		}
		$path = $root . $name;
		if ( ! is_file( $path ) || is_link( $path ) ) {
			continue;
		}
		if ( in_array( $name, array( 'index.php', 'xmlrpc.php', 'license.txt', 'readme.html' ), true ) || preg_match( '/^wp-(?!config\.php$)[A-Za-z0-9_.-]+\.php$/', $name ) ) {
			$files[ $name ] = $path;
		}
	}
	ksort( $files, SORT_STRING );
	return $files;
}

function cmsa_v2_update_checksums( $root ) {
	$files = cmsa_v2_update_core_files( $root );
	if ( is_wp_error( $files ) ) {
		return $files;
	}
	$checksums = array();
	foreach ( $files as $relative => $path ) {
		$hash = md5_file( $path );
		if ( false === $hash ) {
			return new WP_Error( 'fixture_checksum', 'Could not calculate a synthetic core checksum.' );
		}
		$checksums[ $relative ] = $hash;
	}
	return $checksums;
}

$current_checksums = cmsa_v2_update_checksums( ABSPATH );
$target_checksums = cmsa_v2_update_checksums( $package_root );
if ( is_wp_error( $current_checksums ) || is_wp_error( $target_checksums ) || count( $current_checksums ) < 100 || count( $target_checksums ) < 100 ) {
	fwrite( STDERR, "Synthetic core checksum maps are incomplete.\n" );
	exit( 1 );
}
if ( ! isset( $current_checksums['wp-includes/version.php'], $target_checksums['wp-includes/version.php'] ) || $current_checksums['wp-includes/version.php'] === $target_checksums['wp-includes/version.php'] ) {
	fwrite( STDERR, "Synthetic target core does not differ at wp-includes/version.php.\n" );
	exit( 1 );
}

$http_calls = array( 'version' => 0, 'checksums_current' => 0, 'checksums_target' => 0 );
add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) use ( $target, $package_url, $current_checksums, $target_checksums, &$http_calls ) {
		if ( false !== strpos( $url, 'api.wordpress.org/core/version-check/1.7/' ) ) {
			++$http_calls['version'];
			$offer = array(
				'response'      => 'upgrade',
				'download'      => $package_url,
				'locale'        => get_locale(),
				'packages'      => array(
					'full'        => $package_url,
					'no_content'  => '',
					'new_bundled' => '',
					'partial'     => '',
					'rollback'    => '',
				),
				'current'       => $target,
				'version'       => $target,
				'php_version'   => '7.4',
				'mysql_version' => '5.7',
				'new_bundled'   => $target,
				'partial_version' => '',
				'new_files'     => true,
			);
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'offers' => array( $offer ), 'translations' => array(), 'ttl' => 3600 ) ),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		if ( false !== strpos( $url, 'api.wordpress.org/core/checksums/1.0/' ) ) {
			$query = array();
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			$version = isset( $query['version'] ) ? (string) $query['version'] : '';
			if ( $target === $version ) {
				++$http_calls['checksums_target'];
				$map = $target_checksums;
			} else {
				++$http_calls['checksums_current'];
				$map = $current_checksums;
			}
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'checksums' => $map ) ),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		return $preempt;
	},
	10,
	3
);

$download_calls = 0;
add_filter(
	'upgrader_pre_download',
	static function ( $reply, $requested, $upgrader ) use ( $package_url, $package, &$download_calls ) {
		if ( $requested === $package_url && $upgrader instanceof Core_Upgrader ) {
			++$download_calls;
			return $package;
		}
		return $reply;
	},
	10,
	3
);

wp_set_current_user( 1 );
$ability = wp_get_ability( 'chattanooga-cms-admin/update-core' );
$verify = wp_get_ability( 'chattanooga-cms-admin/verify-backup' );
if ( ! $ability instanceof WP_Ability || ! $verify instanceof WP_Ability ) {
	fwrite( STDERR, "Core update or backup verification ability is missing.\n" );
	exit( 1 );
}

$before = wp_get_wp_version();
if ( '7.1' !== $before ) {
	fwrite( STDERR, "Synthetic core update probe requires the clean WordPress 7.1 baseline.\n" );
	exit( 1 );
}
$marker_key = 'cmsa_v2_core_update_db_marker';
update_option( $marker_key, 'pre-update-database', false );

$result = $ability->execute( array( 'version' => $target ) );
if ( is_wp_error( $result ) || empty( $result['updated'] ) || $target !== ( $result['version'] ?? '' ) || $before !== ( $result['from_version'] ?? '' ) || empty( $result['rollback_backup_id'] ) || empty( $result['checksums'] ) || empty( $result['database_version'] ) ) {
	fwrite( STDERR, 'Synthetic Core_Upgrader transition failed: ' . ( is_wp_error( $result ) ? $result->get_error_code() . ' ' . $result->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}
if ( $download_calls < 1 || $http_calls['version'] < 1 || $http_calls['checksums_target'] < 1 ) {
	fwrite( STDERR, "Core update did not exercise the version-offer, package-download, and target-checksum contracts.\n" );
	exit( 1 );
}

$version_contents = file_get_contents( ABSPATH . 'wp-includes/version.php' );
if ( ! is_string( $version_contents ) || ! preg_match( '/\$wp_version\s*=\s*[\'\"]7\.1\.1[\'\"]\s*;/', $version_contents ) ) {
	fwrite( STDERR, "Synthetic target version was not installed on disk.\n" );
	exit( 1 );
}
foreach ( $target_checksums as $relative => $expected ) {
	$path = ABSPATH . $relative;
	if ( ! is_file( $path ) || ! hash_equals( $expected, (string) md5_file( $path ) ) ) {
		fwrite( STDERR, "Installed synthetic core does not match its complete checksum map: {$relative}\n" );
		exit( 1 );
	}
}
if ( get_option( $marker_key ) !== 'pre-update-database' ) {
	fwrite( STDERR, "Synthetic core update unexpectedly changed the rollback marker database state.\n" );
	exit( 1 );
}

$rollback_verify = $verify->execute( array( 'id' => $result['rollback_backup_id'] ) );
if ( is_wp_error( $rollback_verify ) || empty( $rollback_verify['valid'] ) ) {
	fwrite( STDERR, "Pre-update core rollback snapshot did not verify after the transition.\n" );
	exit( 1 );
}

$state = array(
	'target'             => $target,
	'baseline'           => $before,
	'rollback_backup_id' => $result['rollback_backup_id'],
	'marker_key'         => $marker_key,
	'checksum_count'     => count( $target_checksums ),
	'version_calls'      => $http_calls['version'],
	'checksum_calls'     => $http_calls['checksums_target'],
	'download_calls'     => $download_calls,
);
$payload = wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $payload ) || false === file_put_contents( $state_path, $payload, LOCK_EX ) ) {
	fwrite( STDERR, "Could not persist synthetic core transition state for fresh-process verification.\n" );
	exit( 1 );
}

echo "cmsa-v2-core-update: PASS core_upgrader=executed version_offer=verified package_download=verified target_checksums=verified rollback_snapshot=verified disk_version=7.1.1 database_version=verified\n";
exit( 0 );
