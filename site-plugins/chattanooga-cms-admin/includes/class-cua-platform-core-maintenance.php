<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Core_Maintenance {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';
	const META_SUFFIX = '.meta.json';

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'restore-core-backup',
			array(
				'label'               => __( 'Restore WordPress core rollback backup', 'chattanooga-cms-admin' ),
				'description'         => __( 'Restores a verified Chattanooga CMS Admin WordPress core and database rollback snapshot after first creating and verifying a rollback snapshot of the current core and database state.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::id_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'restore_core_backup' ),
				'permission_callback' => static function () { return current_user_can( 'update_core' ); },
				'meta'                => self::destructive_meta(),
			)
		);

		wp_register_ability(
			self::PREFIX . 'update-core',
			array(
				'label'               => __( 'Update WordPress core', 'chattanooga-cms-admin' ),
				'description'         => __( 'Updates WordPress core only to an exact version currently offered by the official WordPress update service, after creating and verifying a local core and database rollback snapshot. Verification failure triggers rollback.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'version' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 64 ),
					),
					'required'             => array( 'version' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'update_core' ),
				'permission_callback' => static function () { return current_user_can( 'update_core' ); },
				'meta'                => self::destructive_meta(),
			)
		);
	}

	public static function create_core_backup( $label = '', $type = 'core' ) {
		$directory = CUA_Local_Storage::directory( 'backups' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$state_before = self::core_state( ABSPATH );
		if ( is_wp_error( $state_before ) ) {
			return $state_before;
		}

		$database = CUA_Backups::create_site_backup(
			'database',
			'Core rollback database: ' . sanitize_text_field( (string) $label ),
			'core-database'
		);
		if ( is_wp_error( $database ) ) {
			return $database;
		}
		$database_verify = CUA_Backups::verify_meta( $database );
		if ( is_wp_error( $database_verify ) || empty( $database_verify['valid'] ) ) {
			return new WP_Error( 'cmsa_core_database_backup_verify', 'The database portion of the core rollback snapshot did not verify.' );
		}

		$id = self::new_id();
		$archive = trailingslashit( $directory ) . $id . '.core.zip';
		$zipped = self::zip_core_files( $archive, $state_before );
		if ( is_wp_error( $zipped ) ) {
			return $zipped;
		}

		$state_after = self::core_state( ABSPATH );
		if ( is_wp_error( $state_after ) || ! self::states_equal( $state_before, $state_after ) ) {
			@unlink( $archive );
			return new WP_Error( 'cmsa_core_backup_race', 'WordPress core files changed while the rollback archive was being created.' );
		}

		$database_file = null;
		foreach ( (array) ( $database['files'] ?? array() ) as $file ) {
			if ( 'database' === ( $file['kind'] ?? '' ) ) {
				$database_file = $file;
				break;
			}
		}
		if ( ! is_array( $database_file ) ) {
			@unlink( $archive );
			return new WP_Error( 'cmsa_core_database_backup_missing', 'The database rollback snapshot did not expose its verified database artifact.' );
		}

		$core_file = self::file_descriptor( $archive );
		$core_file['kind'] = 'core';
		$meta = array(
			'id'                 => $id,
			'type'               => sanitize_key( (string) $type ),
			'scope'              => 'core-and-database',
			'label'              => sanitize_text_field( (string) $label ),
			'created_at'         => gmdate( 'c' ),
			'wordpress'          => (string) $state_before['version'],
			'plugin_version'     => CUA_VERSION,
			'database_backup_id' => (string) $database['id'],
			'core_state'         => $state_before,
			'files'              => array( $core_file, $database_file ),
		);

		$written = self::write_meta( $meta );
		if ( is_wp_error( $written ) ) {
			@unlink( $archive );
			return $written;
		}
		$verification = CUA_Backups::verify_meta( $meta );
		if ( is_wp_error( $verification ) || empty( $verification['valid'] ) ) {
			@unlink( $archive );
			@unlink( trailingslashit( $directory ) . $id . self::META_SUFFIX );
			return new WP_Error( 'cmsa_core_backup_verify', 'The completed core rollback snapshot failed artifact verification.' );
		}

		return $meta;
	}

	public static function restore_core_backup( $input ) {
		$id = self::read_id( $input );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$target = CUA_Backups::get_meta( $id );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		if ( 'core' !== ( $target['type'] ?? '' ) && 'pre-core-restore' !== ( $target['type'] ?? '' ) && 'pre-core-update' !== ( $target['type'] ?? '' ) ) {
			return new WP_Error( 'cmsa_core_restore_type', 'The requested backup is not a WordPress core rollback snapshot.' );
		}

		$pre = self::create_core_backup( 'Automatic pre-core-restore rollback for ' . $id, 'pre-core-restore' );
		if ( is_wp_error( $pre ) ) {
			return new WP_Error( 'cmsa_core_pre_restore_backup_failed', 'The current WordPress core and database state could not be backed up before restore.', $pre );
		}

		$result = self::restore_core_meta( $target );
		if ( is_wp_error( $result ) ) {
			$rollback = self::restore_core_meta( $pre );
			if ( is_wp_error( $rollback ) ) {
				return new WP_Error(
					'cmsa_core_restore_rollback_failed',
					'Core restore failed and the automatic rollback also failed.',
					array( 'restore_error' => $result->get_error_code(), 'rollback_error' => $rollback->get_error_code(), 'rollback_backup_id' => $pre['id'] )
				);
			}
			return new WP_Error(
				'cmsa_core_restore_failed_rolled_back',
				'Core restore failed; the pre-restore core and database state was restored automatically.',
				array( 'restore_error' => $result->get_error_code(), 'rollback_backup_id' => $pre['id'] )
			);
		}

		$result['rollback_backup_id'] = $pre['id'];
		return $result;
	}

	public static function update_core( $input ) {
		$version = is_array( $input ) && isset( $input['version'] ) ? trim( (string) $input['version'] ) : '';
		if ( '' === $version || ! preg_match( '/^[0-9A-Za-z][0-9A-Za-z.+-]{0,63}$/', $version ) ) {
			return new WP_Error( 'cmsa_core_version_invalid', 'A valid exact WordPress version is required.' );
		}

		$current = self::disk_version( ABSPATH );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		if ( $current === $version ) {
			return array( 'updated' => false, 'version' => $current, 'reason' => 'already-current' );
		}

		require_once ABSPATH . 'wp-admin/includes/update.php';
		wp_version_check( array(), true );
		$offer = find_core_update( $version, get_locale() );
		if ( ! is_object( $offer ) || (string) ( $offer->current ?? '' ) !== $version ) {
			return new WP_Error( 'cmsa_core_version_not_offered', 'The requested WordPress version is not currently offered by the official WordPress update service for this site locale.' );
		}
		if ( empty( $offer->packages ) || empty( $offer->packages->full ) ) {
			return new WP_Error( 'cmsa_core_package_unavailable', 'The official WordPress update offer does not include a full package.' );
		}

		$backup = self::create_core_backup( 'Automatic rollback before WordPress core update to ' . $version, 'pre-core-update' );
		if ( is_wp_error( $backup ) ) {
			return new WP_Error( 'cmsa_core_update_backup_failed', 'A verified core rollback snapshot could not be created before the update.', $backup );
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-core-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
		$upgrader = new Core_Upgrader( new Automatic_Upgrader_Skin() );
		$result = $upgrader->upgrade(
			$offer,
			array(
				'pre_check_md5'    => true,
				'attempt_rollback' => true,
			)
		);

		$failure = null;
		if ( is_wp_error( $result ) ) {
			$failure = $result;
		} elseif ( false === $result ) {
			$failure = new WP_Error( 'cmsa_core_update_failed', 'WordPress Core_Upgrader returned failure.' );
		}

		if ( null === $failure ) {
			$disk = self::disk_version( ABSPATH );
			if ( is_wp_error( $disk ) || $disk !== $version ) {
				$failure = new WP_Error( 'cmsa_core_update_version_verify', 'The installed WordPress core version did not match the requested version after update.' );
			}
		}
		if ( null === $failure ) {
			$checksums = self::verify_official_checksums( $version );
			if ( is_wp_error( $checksums ) ) {
				$failure = $checksums;
			}
		}
		if ( null === $failure ) {
			$db = self::verify_database_version_from_disk();
			if ( is_wp_error( $db ) ) {
				$failure = $db;
			}
		}

		if ( null !== $failure ) {
			$rollback = self::restore_core_meta( $backup );
			if ( is_wp_error( $rollback ) ) {
				return new WP_Error(
					'cmsa_core_update_rollback_failed',
					'WordPress core update verification failed and the automatic rollback also failed.',
					array( 'update_error' => $failure->get_error_code(), 'rollback_error' => $rollback->get_error_code(), 'rollback_backup_id' => $backup['id'] )
				);
			}
			return new WP_Error(
				'cmsa_core_update_failed_rolled_back',
				'WordPress core update did not pass verification; the pre-update core and database state was restored automatically.',
				array( 'update_error' => $failure->get_error_code(), 'rollback_backup_id' => $backup['id'] )
			);
		}

		return array(
			'updated'            => true,
			'from_version'       => $current,
			'version'            => $version,
			'rollback_backup_id' => $backup['id'],
			'checksums'          => true,
			'database_version'   => true,
		);
	}

	private static function restore_core_meta( array $meta ) {
		$verification = CUA_Backups::verify_meta( $meta );
		if ( is_wp_error( $verification ) || empty( $verification['valid'] ) ) {
			return new WP_Error( 'cmsa_core_restore_verify', 'Core rollback snapshot integrity verification failed; restore was not attempted.' );
		}
		if ( empty( $meta['database_backup_id'] ) || empty( $meta['core_state'] ) || ! is_array( $meta['core_state'] ) ) {
			return new WP_Error( 'cmsa_core_restore_meta', 'Core rollback metadata is incomplete.' );
		}

		$files = self::restore_core_files( $meta );
		if ( is_wp_error( $files ) ) {
			return $files;
		}

		$database = CUA_Backups::restore_database_backup( array( 'id' => (string) $meta['database_backup_id'] ) );
		if ( is_wp_error( $database ) ) {
			return $database;
		}

		$final = self::core_state( ABSPATH );
		if ( is_wp_error( $final ) || ! self::states_equal( $meta['core_state'], $final ) ) {
			return new WP_Error( 'cmsa_core_restore_verification_failed', 'The restored WordPress core files did not match the recorded rollback state.' );
		}
		return array(
			'restored'          => true,
			'backup_id'         => (string) $meta['id'],
			'core'              => true,
			'database'          => true,
			'version'           => (string) $final['version'],
			'core_files'        => (int) $final['file_count'],
			'database_rollback' => $database['rollback_backup_id'] ?? null,
		);
	}

	private static function restore_core_files( array $meta ) {
		$archive = self::artifact_path( $meta, 'core' );
		if ( is_wp_error( $archive ) ) {
			return $archive;
		}
		$directory = CUA_Local_Storage::directory( 'backups' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() ) {
			return new WP_Error( 'cmsa_core_filesystem', 'WordPress filesystem access is unavailable for core restore.' );
		}
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return new WP_Error( 'cmsa_core_filesystem', 'WordPress filesystem abstraction did not initialize.' );
		}

		$temp = trailingslashit( $directory ) . 'restore-core-' . strtolower( wp_generate_password( 12, false, false ) );
		if ( ! wp_mkdir_p( $temp ) ) {
			return new WP_Error( 'cmsa_core_restore_temp', 'Could not create core restore staging directory.' );
		}
		$unzipped = unzip_file( $archive, $temp );
		if ( is_wp_error( $unzipped ) ) {
			$wp_filesystem->delete( $temp, true );
			return new WP_Error( 'cmsa_core_restore_unpack', 'Could not unpack the verified core rollback archive.' );
		}

		$source = trailingslashit( $temp ) . 'wordpress-core';
		$staged = self::core_state( $source );
		if ( is_wp_error( $staged ) || ! self::states_equal( $meta['core_state'], $staged ) ) {
			$wp_filesystem->delete( $temp, true );
			return new WP_Error( 'cmsa_core_restore_staging_verify', 'The staged core rollback files did not match the recorded core digest.' );
		}

		foreach ( array( 'wp-admin', 'wp-includes' ) as $folder ) {
			$destination = ABSPATH . $folder;
			if ( $wp_filesystem->exists( $destination ) && ! $wp_filesystem->delete( $destination, true ) ) {
				$wp_filesystem->delete( $temp, true );
				return new WP_Error( 'cmsa_core_restore_delete', 'Could not clear an existing WordPress core directory.' );
			}
			$copied = copy_dir( $source . '/' . $folder, $destination );
			if ( is_wp_error( $copied ) ) {
				$wp_filesystem->delete( $temp, true );
				return new WP_Error( 'cmsa_core_restore_copy', 'Could not restore a WordPress core directory.' );
			}
		}

		$current_root = self::core_root_files( ABSPATH );
		if ( is_wp_error( $current_root ) ) {
			$wp_filesystem->delete( $temp, true );
			return $current_root;
		}
		foreach ( $current_root as $name ) {
			if ( $wp_filesystem->exists( ABSPATH . $name ) && ! $wp_filesystem->delete( ABSPATH . $name, false ) ) {
				$wp_filesystem->delete( $temp, true );
				return new WP_Error( 'cmsa_core_restore_delete', 'Could not clear a WordPress core root file.' );
			}
		}
		foreach ( (array) ( $staged['root_files'] ?? array() ) as $name ) {
			if ( ! $wp_filesystem->copy( $source . '/' . $name, ABSPATH . $name, true, FS_CHMOD_FILE ) ) {
				$wp_filesystem->delete( $temp, true );
				return new WP_Error( 'cmsa_core_restore_copy', 'Could not restore a WordPress core root file.' );
			}
		}
		$wp_filesystem->delete( $temp, true );
		clearstatcache();

		$restored = self::core_state( ABSPATH );
		if ( is_wp_error( $restored ) || ! self::states_equal( $meta['core_state'], $restored ) ) {
			return new WP_Error( 'cmsa_core_restore_verification_failed', 'WordPress core file verification failed after restore.' );
		}
		return $restored;
	}

	private static function zip_core_files( $destination, array $expected_state ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'cmsa_core_ziparchive', 'PHP ZipArchive is required for WordPress core rollback archives.' );
		}
		$files = self::collect_core_files( ABSPATH );
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		if ( count( $files ) !== (int) ( $expected_state['file_count'] ?? -1 ) ) {
			return new WP_Error( 'cmsa_core_backup_race', 'WordPress core file count changed before archiving.' );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'cmsa_core_zip_open', 'Could not create the WordPress core rollback archive.' );
		}
		$zip->addEmptyDir( 'wordpress-core' );
		foreach ( $files as $relative => $path ) {
			if ( ! $zip->addFile( $path, 'wordpress-core/' . $relative ) ) {
				$zip->close();
				@unlink( $destination );
				return new WP_Error( 'cmsa_core_zip_write', 'Could not add a WordPress core file to the rollback archive.' );
			}
		}
		if ( ! $zip->close() || ! is_file( $destination ) || 0 === (int) filesize( $destination ) ) {
			@unlink( $destination );
			return new WP_Error( 'cmsa_core_zip_finalize', 'The WordPress core rollback archive could not be finalized.' );
		}
		return true;
	}

	private static function core_state( $root ) {
		$files = self::collect_core_files( $root );
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		$root_files = self::core_root_files( $root );
		if ( is_wp_error( $root_files ) ) {
			return $root_files;
		}
		$digest = hash_init( 'sha256' );
		foreach ( $files as $relative => $path ) {
			$hash = hash_file( 'sha256', $path );
			if ( false === $hash ) {
				return new WP_Error( 'cmsa_core_hash', 'Could not hash a WordPress core file.' );
			}
			hash_update( $digest, $relative . "\0" . $hash . "\n" );
		}
		$version = self::disk_version( $root );
		if ( is_wp_error( $version ) ) {
			return $version;
		}
		return array(
			'version'    => $version,
			'file_count' => count( $files ),
			'sha256'     => hash_final( $digest ),
			'root_files' => $root_files,
		);
	}

	private static function collect_core_files( $root ) {
		$root = trailingslashit( wp_normalize_path( (string) $root ) );
		$files = array();
		foreach ( array( 'wp-admin', 'wp-includes' ) as $folder ) {
			$directory = $root . $folder;
			if ( ! is_dir( $directory ) || is_link( $directory ) ) {
				return new WP_Error( 'cmsa_core_layout', 'A required WordPress core directory is missing or is a symbolic link.' );
			}
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
			foreach ( $iterator as $item ) {
				if ( $item->isLink() ) {
					return new WP_Error( 'cmsa_core_symlink', 'WordPress core contains a symbolic link; core backup stopped rather than following an external filesystem path.' );
				}
				if ( ! $item->isFile() ) {
					continue;
				}
				$path = wp_normalize_path( $item->getPathname() );
				$relative = ltrim( substr( $path, strlen( $root ) ), '/' );
				$files[ $relative ] = $path;
			}
		}
		$root_files = self::core_root_files( $root );
		if ( is_wp_error( $root_files ) ) {
			return $root_files;
		}
		foreach ( $root_files as $name ) {
			$files[ $name ] = $root . $name;
		}
		ksort( $files, SORT_STRING );
		return $files;
	}

	private static function core_root_files( $root ) {
		$root = trailingslashit( wp_normalize_path( (string) $root ) );
		$items = @scandir( $root );
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'cmsa_core_root_read', 'Could not enumerate WordPress core root files.' );
		}
		$files = array();
		foreach ( $items as $name ) {
			if ( '.' === $name || '..' === $name || 'wp-config.php' === $name ) {
				continue;
			}
			$path = $root . $name;
			if ( is_link( $path ) ) {
				if ( self::is_core_root_name( $name ) ) {
					return new WP_Error( 'cmsa_core_symlink', 'A WordPress core root file is a symbolic link; core backup stopped.' );
				}
				continue;
			}
			if ( is_file( $path ) && self::is_core_root_name( $name ) ) {
				$files[] = $name;
			}
		}
		sort( $files, SORT_STRING );
		return $files;
	}

	private static function is_core_root_name( $name ) {
		if ( in_array( $name, array( 'index.php', 'xmlrpc.php', 'license.txt', 'readme.html' ), true ) ) {
			return true;
		}
		return 1 === preg_match( '/^wp-(?!config\.php$)[A-Za-z0-9_.-]+\.php$/', $name );
	}

	private static function disk_version( $root ) {
		$path = trailingslashit( wp_normalize_path( (string) $root ) ) . 'wp-includes/version.php';
		$contents = @file_get_contents( $path );
		if ( ! is_string( $contents ) || ! preg_match( '/\$wp_version\s*=\s*[\'\"]([^\'\"]+)[\'\"]\s*;/', $contents, $match ) ) {
			return new WP_Error( 'cmsa_core_version_read', 'Could not read the WordPress core version from disk.' );
		}
		return (string) $match[1];
	}

	private static function verify_database_version_from_disk() {
		$path = ABSPATH . 'wp-includes/version.php';
		$contents = @file_get_contents( $path );
		if ( ! is_string( $contents ) || ! preg_match( '/\$wp_db_version\s*=\s*([0-9]+)\s*;/', $contents, $match ) ) {
			return new WP_Error( 'cmsa_core_db_version_read', 'Could not read the WordPress database schema version from the updated core.' );
		}
		$expected = (int) $match[1];
		$current = (int) get_option( 'db_version', 0 );
		if ( $expected !== $current ) {
			return new WP_Error( 'cmsa_core_db_version_mismatch', 'WordPress core files updated but the database schema version did not reach the version required by the new core.', array( 'expected' => $expected, 'current' => $current ) );
		}
		return true;
	}

	private static function verify_official_checksums( $version ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		$checksums = get_core_checksums( $version, get_locale() );
		if ( ! is_array( $checksums ) || empty( $checksums ) ) {
			return new WP_Error( 'cmsa_core_checksum_unavailable', 'Official WordPress core checksums were unavailable; the update cannot be accepted without verification.' );
		}
		foreach ( $checksums as $relative => $checksum ) {
			$relative = ltrim( wp_normalize_path( (string) $relative ), '/' );
			if ( 0 === strpos( $relative, 'wp-content/' ) || 'wp-content' === $relative ) {
				continue;
			}
			if ( '' === $relative || str_contains( $relative, '../' ) ) {
				return new WP_Error( 'cmsa_core_checksum_invalid', 'Official WordPress checksum data contained an invalid path.' );
			}
			$path = ABSPATH . $relative;
			if ( ! is_file( $path ) || ! hash_equals( strtolower( (string) $checksum ), strtolower( (string) md5_file( $path ) ) ) ) {
				return new WP_Error( 'cmsa_core_checksum_mismatch', 'An installed WordPress core file failed official checksum verification.', array( 'file' => $relative ) );
			}
		}
		return true;
	}

	private static function states_equal( array $a, array $b ) {
		return (string) ( $a['version'] ?? '' ) === (string) ( $b['version'] ?? '' )
			&& (int) ( $a['file_count'] ?? -1 ) === (int) ( $b['file_count'] ?? -2 )
			&& isset( $a['sha256'], $b['sha256'] )
			&& hash_equals( (string) $a['sha256'], (string) $b['sha256'] )
			&& array_values( (array) ( $a['root_files'] ?? array() ) ) === array_values( (array) ( $b['root_files'] ?? array() ) );
	}

	private static function artifact_path( array $meta, $kind ) {
		$directory = CUA_Local_Storage::directory( 'backups' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		foreach ( (array) ( $meta['files'] ?? array() ) as $file ) {
			if ( $kind === ( $file['kind'] ?? '' ) && ! empty( $file['name'] ) ) {
				$path = trailingslashit( $directory ) . basename( (string) $file['name'] );
				return is_file( $path ) ? $path : new WP_Error( 'cmsa_core_artifact_missing', 'A recorded core rollback artifact is missing.' );
			}
		}
		return new WP_Error( 'cmsa_core_artifact_missing', 'The requested core rollback snapshot does not contain the required artifact.' );
	}

	private static function write_meta( array $meta ) {
		$directory = CUA_Local_Storage::directory( 'backups' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		if ( empty( $meta['id'] ) || ! preg_match( '/^[a-z0-9-]+$/', (string) $meta['id'] ) ) {
			return new WP_Error( 'cmsa_core_meta_invalid', 'Core rollback metadata has an invalid identifier.' );
		}
		$payload = wp_json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $payload ) ) {
			return new WP_Error( 'cmsa_core_meta', 'Core rollback metadata could not be encoded.' );
		}
		$path = trailingslashit( $directory ) . $meta['id'] . self::META_SUFFIX;
		$temp = $path . '.tmp-' . strtolower( wp_generate_password( 8, false, false ) );
		$written = @file_put_contents( $temp, $payload, LOCK_EX );
		if ( false === $written || strlen( $payload ) !== $written || ! @rename( $temp, $path ) ) {
			@unlink( $temp );
			return new WP_Error( 'cmsa_core_meta', 'Core rollback metadata could not be written atomically.' );
		}
		return true;
	}

	private static function file_descriptor( $path ) {
		return array(
			'name'   => basename( $path ),
			'size'   => (int) filesize( $path ),
			'sha256' => (string) hash_file( 'sha256', $path ),
		);
	}

	private static function new_id() {
		return 'core-' . gmdate( 'Ymd-His' ) . '-' . strtolower( wp_generate_password( 10, false, false ) );
	}

	private static function read_id( $input ) {
		$id = is_array( $input ) && isset( $input['id'] ) ? trim( (string) $input['id'] ) : '';
		if ( '' === $id || ! preg_match( '/^[a-z0-9-]{12,160}$/', $id ) ) {
			return new WP_Error( 'cmsa_core_backup_id', 'A valid core rollback backup identifier is required.' );
		}
		return $id;
	}

	private static function id_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array( 'type' => 'string', 'minLength' => 12, 'maxLength' => 160, 'pattern' => '^[a-z0-9-]+$' ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	private static function destructive_meta() {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
		);
	}
}
