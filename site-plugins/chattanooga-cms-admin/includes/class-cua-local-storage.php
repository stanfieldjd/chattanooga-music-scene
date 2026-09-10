<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Local_Storage {
	const DIRECTORY_NAME = 'chattanooga-cms-admin-data';

	public static function directory( $child = '' ) {
		$base = defined( 'CUA_STORAGE_DIR' ) && CUA_STORAGE_DIR
			? untrailingslashit( (string) CUA_STORAGE_DIR )
			: trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . self::DIRECTORY_NAME;

		if ( '' === trim( $base ) ) {
			return new WP_Error( 'cmsa_storage_path', 'The local CMS Admin storage path is empty.' );
		}

		if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
			return new WP_Error( 'cmsa_storage_create', 'The local CMS Admin storage directory could not be created.' );
		}
		if ( ! is_writable( $base ) ) {
			return new WP_Error( 'cmsa_storage_writable', 'The local CMS Admin storage directory is not writable.' );
		}

		self::protect_directory( $base );

		$child = trim( (string) $child, "/\\ \t\n\r\0\x0B" );
		if ( '' === $child ) {
			return $base;
		}
		if ( 0 !== validate_file( $child ) || false !== strpos( $child, '..' ) ) {
			return new WP_Error( 'cmsa_storage_child', 'The requested local storage subdirectory is invalid.' );
		}

		$path = trailingslashit( $base ) . $child;
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return new WP_Error( 'cmsa_storage_create', 'The local CMS Admin storage subdirectory could not be created.' );
		}
		if ( ! is_writable( $path ) ) {
			return new WP_Error( 'cmsa_storage_writable', 'The local CMS Admin storage subdirectory is not writable.' );
		}
		self::protect_directory( $path );
		return $path;
	}

	public static function path( $filename, $child = '' ) {
		$directory = self::directory( $child );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$filename = basename( (string) $filename );
		if ( '' === $filename || '.' === $filename || '..' === $filename ) {
			return new WP_Error( 'cmsa_storage_filename', 'The local storage filename is invalid.' );
		}
		return trailingslashit( $directory ) . $filename;
	}

	private static function protect_directory( $directory ) {
		$files = array(
			'index.php'  => "<?php\nhttp_response_code( 404 );\nexit;\n",
			'.htaccess'  => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
		);
		foreach ( $files as $name => $contents ) {
			$path = trailingslashit( $directory ) . $name;
			if ( ! is_file( $path ) ) {
				@file_put_contents( $path, $contents, LOCK_EX );
			}
		}
	}
}
