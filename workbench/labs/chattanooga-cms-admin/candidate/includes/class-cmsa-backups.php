<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Backups {
	const META_SUFFIX = '.json';

	public static function activate() {
		self::get_storage_directory();
	}

	public static function get_storage_directory() {
		$candidates = array();

		if ( defined( 'CMSA_BACKUP_DIR' ) && CMSA_BACKUP_DIR ) {
			$candidates[] = untrailingslashit( CMSA_BACKUP_DIR );
		}

		$candidates[] = trailingslashit( dirname( ABSPATH ) ) . 'chattanooga-cms-admin-backups';
		$candidates[] = trailingslashit( WP_CONTENT_DIR ) . 'chattanooga-cms-admin-backups';

		foreach ( array_unique( $candidates ) as $directory ) {
			if ( is_dir( $directory ) || wp_mkdir_p( $directory ) ) {
				if ( is_writable( $directory ) ) {
					self::protect_directory( $directory );
					return $directory;
				}
			}
		}

		return new WP_Error( 'cmsa_backup_directory', 'No writable local backup directory is available.' );
	}

	public function create_backup( $scope = 'database', $label = '' ) {
		$scope = sanitize_key( $scope );
		if ( ! in_array( $scope, array( 'database', 'wp-content', 'full' ), true ) ) {
			return new WP_Error( 'cmsa_backup_scope', 'Backup scope must be database, wp-content, or full.' );
		}

		$directory = self::get_storage_directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$id = $this->new_id( 'backup' );
		$files = array();

		if ( in_array( $scope, array( 'database', 'full' ), true ) ) {
			$sql_path = trailingslashit( $directory ) . $id . '-database.sql';
			$result = $this->dump_database( $sql_path );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$files[] = $this->file_descriptor( $sql_path );
		}

		if ( in_array( $scope, array( 'wp-content', 'full' ), true ) ) {
			$zip_path = trailingslashit( $directory ) . $id . '-wp-content.zip';
			$result = $this->zip_directory( WP_CONTENT_DIR, $zip_path, array( untrailingslashit( $directory ) ) );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$files[] = $this->file_descriptor( $zip_path );
		}

		$meta = array(
			'id'             => $id,
			'type'           => 'site',
			'scope'          => $scope,
			'label'          => sanitize_text_field( $label ),
			'created_at'     => gmdate( 'c' ),
			'wordpress'      => get_bloginfo( 'version' ),
			'plugin_version' => CMSA_VERSION,
			'files'          => $files,
		);

		$written = $this->write_meta( $meta );
		if ( is_wp_error( $written ) ) {
			return $written;
		}

		CMSA_Audit::record( 'create-backup', $id, 'success', array( 'scope' => $scope ) );
		return $meta;
	}

	public function create_component_backup( $component_type, $target, $source_directory ) {
		$component_type = sanitize_key( $component_type );
		if ( ! in_array( $component_type, array( 'plugin', 'theme' ), true ) ) {
			return new WP_Error( 'cmsa_component_type', 'Component type must be plugin or theme.' );
		}
		if ( ! is_dir( $source_directory ) ) {
			return new WP_Error( 'cmsa_component_missing', 'Component source directory does not exist.' );
		}

		$directory = self::get_storage_directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$id = $this->new_id( $component_type );
		$zip_path = trailingslashit( $directory ) . $id . '.zip';
		$result = $this->zip_directory( $source_directory, $zip_path );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$meta = array(
			'id'             => $id,
			'type'           => 'component',
			'component_type' => $component_type,
			'target'         => sanitize_text_field( $target ),
			'folder'         => basename( untrailingslashit( $source_directory ) ),
			'created_at'     => gmdate( 'c' ),
			'files'          => array( $this->file_descriptor( $zip_path ) ),
		);

		$written = $this->write_meta( $meta );
		if ( is_wp_error( $written ) ) {
			return $written;
		}

		CMSA_Audit::record( 'create-component-backup', $target, 'success', array( 'backup_id' => $id, 'type' => $component_type ) );
		return $meta;
	}

	public function create_core_backup() {
		$directory = self::get_storage_directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$id = $this->new_id( 'core' );
		$sql_path = trailingslashit( $directory ) . $id . '-database.sql';
		$zip_path = trailingslashit( $directory ) . $id . '-core.zip';

		$result = $this->dump_database( $sql_path );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$core_manifest = $this->zip_core_files( $zip_path );
		if ( is_wp_error( $core_manifest ) ) {
			@unlink( $sql_path );
			return $core_manifest;
		}

		$meta = array(
			'id'             => $id,
			'type'           => 'core',
			'scope'          => 'core-and-database',
			'created_at'     => gmdate( 'c' ),
			'wordpress'      => get_bloginfo( 'version' ),
			'root_files'     => $core_manifest,
			'files'          => array(
				$this->file_descriptor( $sql_path ),
				$this->file_descriptor( $zip_path ),
			),
		);

		$written = $this->write_meta( $meta );
		if ( is_wp_error( $written ) ) {
			return $written;
		}

		CMSA_Audit::record( 'create-core-backup', 'wordpress-core', 'success', array( 'backup_id' => $id ) );
		return $meta;
	}

	public function list_backups() {
		$directory = self::get_storage_directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$items = array();
		foreach ( glob( trailingslashit( $directory ) . '*' . self::META_SUFFIX ) ?: array() as $path ) {
			$data = json_decode( (string) file_get_contents( $path ), true );
			if ( is_array( $data ) && ! empty( $data['id'] ) ) {
				$items[] = $data;
			}
		}

		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( isset( $b['created_at'] ) ? $b['created_at'] : '', isset( $a['created_at'] ) ? $a['created_at'] : '' );
			}
		);

		return array( 'backups' => $items );
	}

	public function verify_backup( $id ) {
		$meta = $this->read_meta( $id );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}

		$directory = self::get_storage_directory();
		$checks = array();
		$valid = true;
		foreach ( isset( $meta['files'] ) ? $meta['files'] : array() as $file ) {
			$path = trailingslashit( $directory ) . basename( $file['name'] );
			$exists = is_file( $path );
			$hash = $exists ? hash_file( 'sha256', $path ) : '';
			$matches = $exists && hash_equals( (string) $file['sha256'], (string) $hash );
			$valid = $valid && $matches;
			$checks[] = array(
				'name'    => basename( $path ),
				'exists'  => $exists,
				'matches' => $matches,
				'sha256'  => $hash,
			);
		}

		return array(
			'id'     => $meta['id'],
			'valid'  => $valid,
			'checks' => $checks,
		);
	}

	public function restore_component_backup( $id ) {
		$meta = $this->read_meta( $id );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}
		if ( 'component' !== $meta['type'] || empty( $meta['component_type'] ) || empty( $meta['folder'] ) ) {
			return new WP_Error( 'cmsa_restore_type', 'Backup is not a component rollback archive.' );
		}

		$verification = $this->verify_backup( $id );
		if ( is_wp_error( $verification ) || empty( $verification['valid'] ) ) {
			return new WP_Error( 'cmsa_restore_verify', 'Backup verification failed; restore was not attempted.' );
		}

		$this->load_filesystem_api();
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return new WP_Error( 'cmsa_filesystem', 'WordPress filesystem access is unavailable.' );
		}

		$base = 'plugin' === $meta['component_type'] ? WP_PLUGIN_DIR : get_theme_root();
		$destination = trailingslashit( $base ) . sanitize_file_name( $meta['folder'] );
		$directory = self::get_storage_directory();
		$archive = trailingslashit( $directory ) . basename( $meta['files'][0]['name'] );
		$temp = trailingslashit( $directory ) . 'restore-' . wp_generate_password( 12, false, false );
		if ( ! wp_mkdir_p( $temp ) ) {
			return new WP_Error( 'cmsa_restore_temp', 'Could not create restore staging directory.' );
		}

		$result = unzip_file( $archive, $temp );
		if ( is_wp_error( $result ) ) {
			$wp_filesystem->delete( $temp, true );
			return $result;
		}

		$source = trailingslashit( $temp ) . sanitize_file_name( $meta['folder'] );
		if ( ! is_dir( $source ) ) {
			$wp_filesystem->delete( $temp, true );
			return new WP_Error( 'cmsa_restore_layout', 'Rollback archive layout is invalid.' );
		}

		if ( $wp_filesystem->exists( $destination ) && ! $wp_filesystem->delete( $destination, true ) ) {
			$wp_filesystem->delete( $temp, true );
			return new WP_Error( 'cmsa_restore_delete', 'Could not remove the current component before rollback.' );
		}

		$result = copy_dir( $source, $destination );
		$wp_filesystem->delete( $temp, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		CMSA_Audit::record( 'restore-component-backup', $meta['target'], 'success', array( 'backup_id' => $id ) );
		return array( 'restored' => true, 'backup_id' => $id, 'target' => $meta['target'] );
	}

	public function restore_database_backup( $id ) {
		$meta = $this->read_meta( $id );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}

		$verification = $this->verify_backup( $id );
		if ( is_wp_error( $verification ) || empty( $verification['valid'] ) ) {
			return new WP_Error( 'cmsa_restore_verify', 'Backup verification failed; database restore was not attempted.' );
		}

		$sql_path = $this->find_file_by_suffix( $meta, '-database.sql' );
		if ( ! $sql_path ) {
			return new WP_Error( 'cmsa_database_missing', 'This backup does not contain a database snapshot.' );
		}

		$result = $this->restore_database_file( $sql_path );
		if ( is_wp_error( $result ) ) {
			CMSA_Audit::record( 'restore-database-backup', $id, 'failed' );
			return $result;
		}

		CMSA_Audit::record( 'restore-database-backup', $id, 'success' );
		return array( 'restored' => true, 'backup_id' => $id, 'database' => true );
	}

	public function restore_core_backup( $id ) {
		$meta = $this->read_meta( $id );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}
		if ( 'core' !== $meta['type'] ) {
			return new WP_Error( 'cmsa_core_restore_type', 'Backup is not a WordPress core rollback snapshot.' );
		}

		$verification = $this->verify_backup( $id );
		if ( is_wp_error( $verification ) || empty( $verification['valid'] ) ) {
			return new WP_Error( 'cmsa_restore_verify', 'Core backup verification failed; restore was not attempted.' );
		}

		$directory = self::get_storage_directory();
		$archive = $this->find_file_by_suffix( $meta, '-core.zip' );
		if ( ! $archive ) {
			return new WP_Error( 'cmsa_core_archive_missing', 'Core rollback archive is missing.' );
		}

		$this->load_filesystem_api();
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return new WP_Error( 'cmsa_filesystem', 'WordPress filesystem access is unavailable.' );
		}

		$temp = trailingslashit( $directory ) . 'restore-core-' . wp_generate_password( 12, false, false );
		if ( ! wp_mkdir_p( $temp ) ) {
			return new WP_Error( 'cmsa_restore_temp', 'Could not create core restore staging directory.' );
		}
		$result = unzip_file( $archive, $temp );
		if ( is_wp_error( $result ) ) {
			$wp_filesystem->delete( $temp, true );
			return $result;
		}

		$source = trailingslashit( $temp ) . 'wordpress-core';
		if ( ! is_dir( $source . '/wp-admin' ) || ! is_dir( $source . '/wp-includes' ) ) {
			$wp_filesystem->delete( $temp, true );
			return new WP_Error( 'cmsa_core_layout', 'Core rollback archive layout is invalid.' );
		}

		if ( ! $wp_filesystem->delete( ABSPATH . 'wp-admin', true ) || ! $wp_filesystem->delete( ABSPATH . 'wp-includes', true ) ) {
			$wp_filesystem->delete( $temp, true );
			return new WP_Error( 'cmsa_core_delete', 'Could not clear current WordPress core directories.' );
		}

		$result = copy_dir( $source . '/wp-admin', ABSPATH . 'wp-admin' );
		if ( is_wp_error( $result ) ) {
			$wp_filesystem->delete( $temp, true );
			return $result;
		}
		$result = copy_dir( $source . '/wp-includes', ABSPATH . 'wp-includes' );
		if ( is_wp_error( $result ) ) {
			$wp_filesystem->delete( $temp, true );
			return $result;
		}

		foreach ( isset( $meta['root_files'] ) ? $meta['root_files'] : array() as $root_file ) {
			$root_file = basename( $root_file );
			if ( ! $wp_filesystem->copy( $source . '/' . $root_file, ABSPATH . $root_file, true, FS_CHMOD_FILE ) ) {
				$wp_filesystem->delete( $temp, true );
				return new WP_Error( 'cmsa_core_copy', 'Could not restore core root file ' . $root_file . '.' );
			}
		}

		$wp_filesystem->delete( $temp, true );
		$db_result = $this->restore_database_backup( $id );
		if ( is_wp_error( $db_result ) ) {
			return $db_result;
		}

		CMSA_Audit::record( 'restore-core-backup', 'wordpress-core', 'success', array( 'backup_id' => $id ) );
		return array( 'restored' => true, 'backup_id' => $id, 'core' => true, 'database' => true );
	}

	private function dump_database( $path ) {
		global $wpdb;
		$handle = @fopen( $path, 'wb' );
		if ( ! $handle ) {
			return new WP_Error( 'cmsa_database_file', 'Could not create database backup file.' );
		}

		fwrite( $handle, "SET FOREIGN_KEY_CHECKS=0;\n" );
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		foreach ( $tables as $table ) {
			$identifier = '`' . str_replace( '`', '``', $table ) . '`';
			$create = $wpdb->get_row( 'SHOW CREATE TABLE ' . $identifier, ARRAY_N );
			if ( ! $create || empty( $create[1] ) ) {
				fclose( $handle );
				return new WP_Error( 'cmsa_database_schema', 'Could not read schema for table ' . $table . '.' );
			}

			$column_definitions = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $identifier, ARRAY_A );
			if ( ! is_array( $column_definitions ) || empty( $column_definitions ) ) {
				fclose( $handle );
				return new WP_Error( 'cmsa_database_columns', 'Could not read column definitions for table ' . $table . '.' );
			}
			$numeric_columns = array();
			foreach ( $column_definitions as $definition ) {
				if ( empty( $definition['Field'] ) || empty( $definition['Type'] ) ) {
					continue;
				}
				if ( preg_match( '/^(?:tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real|year)\b/i', (string) $definition['Type'] ) ) {
					$numeric_columns[ $definition['Field'] ] = true;
				}
			}

			fwrite( $handle, "DROP TABLE IF EXISTS {$identifier};\n" . $create[1] . ";\n" );

			$offset = 0;
			$limit = 250;
			do {
				$rows = $wpdb->get_results( "SELECT * FROM {$identifier} LIMIT {$offset}, {$limit}", ARRAY_A );
				foreach ( $rows as $row ) {
					$columns = array();
					$values = array();
					foreach ( $row as $column => $value ) {
						$columns[] = '`' . str_replace( '`', '``', $column ) . '`';
						if ( null === $value ) {
							$values[] = 'NULL';
						} elseif ( isset( $numeric_columns[ $column ] ) ) {
							$numeric_value = (string) $value;
							if ( ! is_numeric( $numeric_value ) ) {
								fclose( $handle );
								return new WP_Error( 'cmsa_database_numeric', 'Database backup encountered a non-numeric value in numeric column ' . $table . '.' . $column . '.' );
							}
							$values[] = $numeric_value;
						} elseif ( '' === (string) $value ) {
							$values[] = "''";
						} else {
							$values[] = '0x' . bin2hex( (string) $value );
						}
					}
					fwrite( $handle, 'INSERT INTO ' . $identifier . ' (' . implode( ',', $columns ) . ') VALUES (' . implode( ',', $values ) . ");\n" );
				}
				$offset += $limit;
			} while ( count( $rows ) === $limit );
		}
		fwrite( $handle, "SET FOREIGN_KEY_CHECKS=1;\n" );
		fclose( $handle );
		return true;
	}

	private function restore_database_file( $path ) {
		global $wpdb;
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) {
			return new WP_Error( 'cmsa_database_read', 'Could not open database backup file.' );
		}

		$statement = '';
		while ( false !== ( $line = fgets( $handle ) ) ) {
			$statement .= $line;
			if ( ';' !== substr( rtrim( $line ), -1 ) ) {
				continue;
			}

			$sql = trim( $statement );
			$statement = '';
			if ( '' === $sql ) {
				continue;
			}

			$result = $wpdb->query( $sql );
			if ( false === $result ) {
				fclose( $handle );
				return new WP_Error( 'cmsa_database_restore_query', 'Database restore failed: ' . $wpdb->last_error );
			}
		}
		fclose( $handle );
		wp_cache_flush();
		return true;
	}

	private function zip_directory( $source_directory, $destination, array $excluded_directories = array() ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'cmsa_ziparchive', 'PHP ZipArchive is required for filesystem rollback archives.' );
		}

		$source_directory = untrailingslashit( wp_normalize_path( $source_directory ) );
		$excluded = array_map( 'wp_normalize_path', $excluded_directories );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'cmsa_zip_open', 'Could not create rollback archive.' );
		}

		$root_name = basename( $source_directory );
		$zip->addEmptyDir( $root_name );
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$real = wp_normalize_path( $item->getPathname() );
			$skip = false;
			foreach ( $excluded as $excluded_path ) {
				if ( 0 === strpos( $real, untrailingslashit( $excluded_path ) . '/' ) || $real === untrailingslashit( $excluded_path ) ) {
					$skip = true;
					break;
				}
			}
			if ( $skip ) {
				continue;
			}

			$relative = ltrim( substr( $real, strlen( $source_directory ) ), '/' );
			$local = $root_name . '/' . $relative;
			if ( $item->isDir() ) {
				$zip->addEmptyDir( $local );
			} elseif ( $item->isFile() ) {
				$zip->addFile( $real, $local );
			}
		}

		$zip->close();
		return true;
	}

	private function zip_core_files( $destination ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'cmsa_ziparchive', 'PHP ZipArchive is required for WordPress core rollback archives.' );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'cmsa_zip_open', 'Could not create WordPress core rollback archive.' );
		}

		$zip->addEmptyDir( 'wordpress-core' );
		foreach ( array( 'wp-admin', 'wp-includes' ) as $directory ) {
			$source = ABSPATH . $directory;
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $item ) {
				$real = wp_normalize_path( $item->getPathname() );
				$relative = ltrim( substr( $real, strlen( wp_normalize_path( ABSPATH ) ) ), '/' );
				$local = 'wordpress-core/' . $relative;
				if ( $item->isDir() ) {
					$zip->addEmptyDir( $local );
				} elseif ( $item->isFile() ) {
					$zip->addFile( $real, $local );
				}
			}
		}

		$root_files = array();
		foreach ( glob( ABSPATH . '*.php' ) ?: array() as $path ) {
			$name = basename( $path );
			if ( 'index.php' === $name || 'xmlrpc.php' === $name || 0 === strpos( $name, 'wp-' ) ) {
				$root_files[] = $name;
				$zip->addFile( $path, 'wordpress-core/' . $name );
			}
		}
		foreach ( array( 'license.txt', 'readme.html' ) as $name ) {
			if ( is_file( ABSPATH . $name ) ) {
				$root_files[] = $name;
				$zip->addFile( ABSPATH . $name, 'wordpress-core/' . $name );
			}
		}

		$zip->close();
		sort( $root_files );
		return $root_files;
	}

	private static function protect_directory( $directory ) {
		$files = array(
			'.htaccess'  => "Deny from all\n",
			'index.php'  => "<?php\nhttp_response_code( 403 );\nexit;\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>",
		);
		foreach ( $files as $name => $contents ) {
			$path = trailingslashit( $directory ) . $name;
			if ( ! file_exists( $path ) ) {
				@file_put_contents( $path, $contents, LOCK_EX );
			}
		}
	}

	private function new_id( $prefix ) {
		return sanitize_key( $prefix ) . '-' . gmdate( 'Ymd-His' ) . '-' . strtolower( wp_generate_password( 8, false, false ) );
	}

	private function file_descriptor( $path ) {
		return array(
			'name'   => basename( $path ),
			'size'   => (int) filesize( $path ),
			'sha256' => hash_file( 'sha256', $path ),
		);
	}

	private function write_meta( array $meta ) {
		$directory = self::get_storage_directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$path = trailingslashit( $directory ) . sanitize_file_name( $meta['id'] ) . self::META_SUFFIX;
		if ( false === @file_put_contents( $path, wp_json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), LOCK_EX ) ) {
			return new WP_Error( 'cmsa_backup_meta', 'Could not write backup metadata.' );
		}
		return true;
	}

	private function read_meta( $id ) {
		$directory = self::get_storage_directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$id = sanitize_file_name( $id );
		$path = trailingslashit( $directory ) . $id . self::META_SUFFIX;
		if ( ! is_file( $path ) ) {
			return new WP_Error( 'cmsa_backup_not_found', 'Backup metadata was not found.' );
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) || empty( $data['id'] ) || $data['id'] !== $id ) {
			return new WP_Error( 'cmsa_backup_meta_invalid', 'Backup metadata is invalid.' );
		}
		return $data;
	}

	private function find_file_by_suffix( array $meta, $suffix ) {
		$directory = self::get_storage_directory();
		if ( is_wp_error( $directory ) ) {
			return false;
		}
		foreach ( isset( $meta['files'] ) ? $meta['files'] : array() as $file ) {
			$name = basename( $file['name'] );
			if ( substr( $name, -strlen( $suffix ) ) === $suffix ) {
				$path = trailingslashit( $directory ) . $name;
				return is_file( $path ) ? $path : false;
			}
		}
		return false;
	}

	private function load_filesystem_api() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
	}
}
