<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Backups {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';
	const FORMAT = 'cmsa-database-jsonl-v1';
	const META_SUFFIX = '.meta.json';
	const ROW_BATCH = 250;

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'list-backups',
			array(
				'label'               => __( 'List local backups', 'chattanooga-cms-admin' ),
				'description'         => __( 'Lists local Chattanooga CMS Admin backup manifests without exposing storage paths or backup contents.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'list_backups' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => self::read_meta(),
			)
		);

		wp_register_ability(
			self::PREFIX . 'create-backup',
			array(
				'label'               => __( 'Create local site backup', 'chattanooga-cms-admin' ),
				'description'         => __( 'Creates a local database, wp-content, or combined backup with SHA-256 artifact integrity metadata.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'scope' => array( 'type' => 'string', 'enum' => array( 'database', 'wp-content', 'full' ) ),
						'label' => array( 'type' => 'string', 'maxLength' => 160, 'default' => '' ),
					),
					'required'             => array( 'scope' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'create_backup' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => self::mutation_meta( false ),
			)
		);

		wp_register_ability(
			self::PREFIX . 'verify-backup',
			array(
				'label'               => __( 'Verify local backup', 'chattanooga-cms-admin' ),
				'description'         => __( 'Recomputes SHA-256 and size checks for every artifact recorded in a local backup manifest.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::id_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'verify_backup' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => self::read_meta(),
			)
		);

		wp_register_ability(
			self::PREFIX . 'restore-database-backup',
			array(
				'label'               => __( 'Restore local database backup', 'chattanooga-cms-admin' ),
				'description'         => __( 'Restores a verified local database snapshot after first creating and verifying a rollback snapshot of the current WordPress database tables.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::id_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'restore_database_backup' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => self::mutation_meta( true ),
			)
		);
	}

	public static function create_backup( $input ) {
		$scope = is_array( $input ) && isset( $input['scope'] ) ? (string) $input['scope'] : '';
		$label = is_array( $input ) && isset( $input['label'] ) ? (string) $input['label'] : '';
		return self::create_site_backup( $scope, $label );
	}

	public static function create_site_backup( $scope, $label = '', $type = 'site', array $extra = array() ) {
		$scope = sanitize_key( (string) $scope );
		if ( ! in_array( $scope, array( 'database', 'wp-content', 'full' ), true ) ) {
			return new WP_Error( 'cmsa_backup_scope', 'Backup scope must be database, wp-content, or full.' );
		}

		$directory = CUA_Local_Storage::directory( 'backups' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$id = self::new_id( 'backup' );
		$created = array();
		$files = array();

		if ( in_array( $scope, array( 'database', 'full' ), true ) ) {
			$db_path = trailingslashit( $directory ) . $id . '.database.jsonl';
			$db_result = self::dump_database( $db_path );
			if ( is_wp_error( $db_result ) ) {
				self::cleanup_paths( $created );
				return $db_result;
			}
			$created[] = $db_path;
			$descriptor = self::file_descriptor( $db_path );
			$descriptor['kind'] = 'database';
			$descriptor['database'] = $db_result;
			$files[] = $descriptor;
		}

		if ( in_array( $scope, array( 'wp-content', 'full' ), true ) ) {
			$storage_root = CUA_Local_Storage::directory();
			if ( is_wp_error( $storage_root ) ) {
				self::cleanup_paths( $created );
				return $storage_root;
			}
			$content_path = trailingslashit( $directory ) . $id . '.wp-content.zip';
			$content_result = self::zip_directory( WP_CONTENT_DIR, $content_path, array( $storage_root ) );
			if ( is_wp_error( $content_result ) ) {
				self::cleanup_paths( $created );
				return $content_result;
			}
			$created[] = $content_path;
			$descriptor = self::file_descriptor( $content_path );
			$descriptor['kind'] = 'wp-content';
			$files[] = $descriptor;
		}

		$meta = array_merge(
			array(
				'id'             => $id,
				'type'           => sanitize_key( (string) $type ),
				'scope'          => $scope,
				'label'          => sanitize_text_field( (string) $label ),
				'created_at'     => gmdate( 'c' ),
				'wordpress'      => wp_get_wp_version(),
				'plugin_version' => CUA_VERSION,
				'files'          => $files,
			),
			$extra
		);

		$written = self::write_meta( $meta );
		if ( is_wp_error( $written ) ) {
			self::cleanup_paths( $created );
			return $written;
		}

		$verification = self::verify_meta( $meta );
		if ( is_wp_error( $verification ) || empty( $verification['valid'] ) ) {
			self::delete_backup_files( $meta );
			return is_wp_error( $verification ) ? $verification : new WP_Error( 'cmsa_backup_verification_failed', 'The newly created backup failed integrity verification and was removed.' );
		}

		return $meta;
	}

	public static function list_backups() {
		$directory = CUA_Local_Storage::directory( 'backups' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$backups = array();
		foreach ( glob( trailingslashit( $directory ) . '*' . self::META_SUFFIX ) ?: array() as $path ) {
			$data = json_decode( (string) @file_get_contents( $path ), true );
			if ( ! is_array( $data ) || empty( $data['id'] ) || basename( $path ) !== $data['id'] . self::META_SUFFIX ) {
				continue;
			}
			$backups[] = self::public_meta( $data );
		}
		usort( $backups, static function ( $a, $b ) { return strcmp( (string) ( $b['created_at'] ?? '' ), (string) ( $a['created_at'] ?? '' ) ); } );
		return array( 'backups' => $backups );
	}

	public static function verify_backup( $input ) {
		$id = self::read_id( $input );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$meta = self::read_meta_file( $id );
		return is_wp_error( $meta ) ? $meta : self::verify_meta( $meta );
	}

	public static function restore_database_backup( $input ) {
		$id = self::read_id( $input );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$meta = self::read_meta_file( $id );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}
		$verification = self::verify_meta( $meta );
		if ( is_wp_error( $verification ) || empty( $verification['valid'] ) ) {
			return new WP_Error( 'cmsa_restore_verify', 'Backup integrity verification failed; database restore was not attempted.' );
		}
		$database = self::artifact_path( $meta, 'database' );
		if ( is_wp_error( $database ) ) {
			return $database;
		}

		$pre = self::create_site_backup( 'database', 'Automatic pre-restore rollback for ' . $id, 'pre-restore' );
		if ( is_wp_error( $pre ) ) {
			return new WP_Error( 'cmsa_pre_restore_backup_failed', 'The current database could not be backed up before restore.', $pre );
		}
		$pre_verify = self::verify_meta( $pre );
		if ( is_wp_error( $pre_verify ) || empty( $pre_verify['valid'] ) ) {
			return new WP_Error( 'cmsa_pre_restore_verify_failed', 'The automatic pre-restore rollback snapshot did not verify; restore was not attempted.' );
		}
		$pre_database = self::artifact_path( $pre, 'database' );
		if ( is_wp_error( $pre_database ) ) {
			return $pre_database;
		}

		$result = self::restore_database_file( $database );
		if ( is_wp_error( $result ) ) {
			$rollback = self::restore_database_file( $pre_database );
			if ( is_wp_error( $rollback ) ) {
				return new WP_Error(
					'cmsa_database_restore_rollback_failed',
					'Database restore failed and the automatic rollback also failed.',
					array( 'restore_error' => $result->get_error_code(), 'rollback_error' => $rollback->get_error_code(), 'rollback_backup_id' => $pre['id'] )
				);
			}
			return new WP_Error(
				'cmsa_database_restore_failed_rolled_back',
				'Database restore failed; the pre-restore database state was restored automatically.',
				array( 'restore_error' => $result->get_error_code(), 'rollback_backup_id' => $pre['id'] )
			);
		}

		return array(
			'restored'           => true,
			'backup_id'          => $id,
			'rollback_backup_id' => $pre['id'],
			'database'           => true,
			'tables_verified'    => $result['tables_verified'],
		);
	}

	public static function get_meta( $id ) {
		return self::read_meta_file( $id );
	}

	public static function verify_meta( array $meta ) {
		$directory = CUA_Local_Storage::directory( 'backups' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		if ( empty( $meta['id'] ) || empty( $meta['files'] ) || ! is_array( $meta['files'] ) ) {
			return new WP_Error( 'cmsa_backup_meta_invalid', 'Backup metadata is incomplete.' );
		}

		$valid = true;
		$checks = array();
		foreach ( $meta['files'] as $file ) {
			$name = isset( $file['name'] ) ? basename( (string) $file['name'] ) : '';
			$path = trailingslashit( $directory ) . $name;
			$exists = '' !== $name && is_file( $path );
			$size = $exists ? (int) filesize( $path ) : 0;
			$hash = $exists ? (string) hash_file( 'sha256', $path ) : '';
			$matches = $exists && isset( $file['size'], $file['sha256'] ) && $size === (int) $file['size'] && hash_equals( (string) $file['sha256'], $hash );
			$valid = $valid && $matches;
			$checks[] = array( 'name' => $name, 'exists' => $exists, 'size' => $size, 'sha256' => $hash, 'matches' => $matches );
		}
		return array( 'id' => (string) $meta['id'], 'valid' => $valid, 'checks' => $checks );
	}

	private static function dump_database( $path ) {
		global $wpdb;
		$tables = self::wordpress_tables();
		if ( is_wp_error( $tables ) ) {
			return $tables;
		}
		if ( empty( $tables ) ) {
			return new WP_Error( 'cmsa_database_tables', 'No WordPress database tables were found for backup.' );
		}

		$handle = @fopen( $path, 'wb' );
		if ( ! $handle ) {
			return new WP_Error( 'cmsa_database_file', 'Could not create the database snapshot file.' );
		}
		$header = array( 'kind' => 'header', 'format' => self::FORMAT, 'base_prefix' => $wpdb->base_prefix, 'table_count' => count( $tables ) );
		if ( ! self::write_json_line( $handle, $header ) ) {
			fclose( $handle );
			@unlink( $path );
			return new WP_Error( 'cmsa_database_write', 'Could not write the database snapshot header.' );
		}

		$table_summary = array();
		foreach ( $tables as $table ) {
			$schema = self::table_schema( $table );
			if ( is_wp_error( $schema ) ) {
				fclose( $handle );
				@unlink( $path );
				return $schema;
			}
			$begin = array( 'kind' => 'table', 'name' => $table, 'create' => $schema['create'], 'columns' => $schema['columns'], 'order_by' => $schema['order_by'] );
			if ( ! self::write_json_line( $handle, $begin ) ) {
				fclose( $handle );
				@unlink( $path );
				return new WP_Error( 'cmsa_database_write', 'Could not write a database table header.' );
			}

			$digest = hash_init( 'sha256' );
			$count = 0;
			$offset = 0;
			do {
				$rows = self::read_table_rows( $table, $schema['columns'], $schema['order_by'], $offset, self::ROW_BATCH );
				if ( is_wp_error( $rows ) ) {
					fclose( $handle );
					@unlink( $path );
					return $rows;
				}
				foreach ( $rows as $row ) {
					$encoded = self::encode_row( $row );
					$canonical = wp_json_encode( $encoded, JSON_UNESCAPED_SLASHES );
					if ( ! is_string( $canonical ) || ! self::write_json_line( $handle, array( 'kind' => 'row', 'values' => $encoded ) ) ) {
						fclose( $handle );
						@unlink( $path );
						return new WP_Error( 'cmsa_database_write', 'Could not write a database row to the snapshot.' );
					}
					hash_update( $digest, $canonical . "\n" );
					++$count;
				}
				$offset += count( $rows );
			} while ( count( $rows ) === self::ROW_BATCH );

			$row_hash = hash_final( $digest );
			if ( ! self::write_json_line( $handle, array( 'kind' => 'table_end', 'name' => $table, 'row_count' => $count, 'row_sha256' => $row_hash ) ) ) {
				fclose( $handle );
				@unlink( $path );
				return new WP_Error( 'cmsa_database_write', 'Could not finalize a database table snapshot.' );
			}
			$table_summary[] = array( 'name' => $table, 'row_count' => $count, 'row_sha256' => $row_hash );
		}

		if ( ! fflush( $handle ) || ! fclose( $handle ) || ! is_file( $path ) || 0 === (int) filesize( $path ) ) {
			@unlink( $path );
			return new WP_Error( 'cmsa_database_write', 'The database snapshot file could not be finalized.' );
		}
		return array( 'format' => self::FORMAT, 'base_prefix' => $wpdb->base_prefix, 'tables' => $table_summary );
	}

	private static function restore_database_file( $path ) {
		global $wpdb;
		$manifest = self::preflight_database_file( $path );
		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}
		if ( (string) $manifest['base_prefix'] !== (string) $wpdb->base_prefix ) {
			return new WP_Error( 'cmsa_database_prefix_mismatch', 'The database snapshot belongs to a different WordPress table prefix.' );
		}
		$current_tables = self::wordpress_tables();
		if ( is_wp_error( $current_tables ) ) {
			return $current_tables;
		}

		if ( false === $wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' ) ) {
			return new WP_Error( 'cmsa_database_restore_query', 'Could not disable foreign-key checks for database restore.' );
		}
		foreach ( $current_tables as $table ) {
			$drop_query = $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table );
			if ( false === $wpdb->query( $drop_query ) ) {
				$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
				return new WP_Error( 'cmsa_database_restore_query', 'Could not clear a current WordPress database table.' );
			}
		}

		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) {
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
			return new WP_Error( 'cmsa_database_read', 'Could not reopen the verified database snapshot.' );
		}
		$current_table = '';
		$current_columns = array();
		while ( false !== ( $line = fgets( $handle ) ) ) {
			$record = json_decode( trim( $line ), true );
			if ( ! is_array( $record ) || empty( $record['kind'] ) ) {
				fclose( $handle );
				$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
				return new WP_Error( 'cmsa_database_snapshot_invalid', 'The verified database snapshot contains an invalid record.' );
			}
			if ( 'header' === $record['kind'] || 'table_end' === $record['kind'] ) {
				continue;
			}
			if ( 'table' === $record['kind'] ) {
				$current_table = (string) $record['name'];
				$current_columns = (array) $record['columns'];
				if ( false === $wpdb->query( (string) $record['create'] ) ) {
					fclose( $handle );
					$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
					return new WP_Error( 'cmsa_database_restore_query', 'Could not recreate a WordPress database table.' );
				}
				continue;
			}
			if ( 'row' === $record['kind'] ) {
				$values = isset( $record['values'] ) && is_array( $record['values'] ) ? $record['values'] : array();
				if ( '' === $current_table || count( $values ) !== count( $current_columns ) ) {
					fclose( $handle );
					$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
					return new WP_Error( 'cmsa_database_snapshot_invalid', 'The verified database snapshot row layout is invalid.' );
				}
				$data = array();
				foreach ( $current_columns as $index => $column ) {
					$value = $values[ $index ];
					if ( null === $value ) {
						$data[ $column ] = null;
					} elseif ( ! is_string( $value ) || false === ( $decoded = base64_decode( $value, true ) ) ) {
						fclose( $handle );
						$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
						return new WP_Error( 'cmsa_database_snapshot_invalid', 'The verified database snapshot contains an invalid encoded value.' );
					} else {
						$data[ $column ] = $decoded;
					}
				}
				if ( false === $wpdb->insert( $current_table, $data ) ) {
					fclose( $handle );
					$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
					return new WP_Error( 'cmsa_database_restore_query', 'Could not restore a database row.' );
				}
			}
		}
		fclose( $handle );
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
		wp_cache_flush();
		$verified = self::verify_live_database( $manifest );
		return is_wp_error( $verified ) ? $verified : array( 'tables_verified' => $verified );
	}

	private static function preflight_database_file( $path ) {
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) {
			return new WP_Error( 'cmsa_database_read', 'Could not open the database snapshot.' );
		}
		$header = null;
		$current = null;
		$tables = array();
		while ( false !== ( $line = fgets( $handle ) ) ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$record = json_decode( $line, true );
			if ( ! is_array( $record ) || empty( $record['kind'] ) ) {
				fclose( $handle );
				return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot contains malformed JSON records.' );
			}
			if ( 'header' === $record['kind'] ) {
				if ( null !== $header || ( $record['format'] ?? '' ) !== self::FORMAT || empty( $record['base_prefix'] ) ) {
					fclose( $handle );
					return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot header is invalid.' );
				}
				$header = $record;
				continue;
			}
			if ( 'table' === $record['kind'] ) {
				if ( null === $header || null !== $current || empty( $record['name'] ) || empty( $record['create'] ) || empty( $record['columns'] ) || empty( $record['order_by'] ) ) {
					fclose( $handle );
					return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot table header is invalid.' );
				}
				$name = (string) $record['name'];
				if ( 0 !== strpos( $name, (string) $header['base_prefix'] ) || isset( $tables[ $name ] ) ) {
					fclose( $handle );
					return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot contains an invalid or duplicate table identity.' );
				}
				$quoted = preg_quote( self::quote_identifier( $name ), '/' );
				if ( ! preg_match( '/^CREATE\s+TABLE\s+' . $quoted . '\s*\(/i', ltrim( (string) $record['create'] ) ) ) {
					fclose( $handle );
					return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot table schema does not match its table identity.' );
				}
				$current = array(
					'name'      => $name,
					'create'    => (string) $record['create'],
					'columns'   => array_values( array_map( 'strval', (array) $record['columns'] ) ),
					'order_by'  => array_values( array_map( 'strval', (array) $record['order_by'] ) ),
					'count'     => 0,
					'digest'    => hash_init( 'sha256' ),
				);
				continue;
			}
			if ( 'row' === $record['kind'] ) {
				if ( null === $current || ! isset( $record['values'] ) || ! is_array( $record['values'] ) || count( $record['values'] ) !== count( $current['columns'] ) ) {
					fclose( $handle );
					return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot row appears outside a valid table or has the wrong width.' );
				}
				foreach ( $record['values'] as $value ) {
					if ( null !== $value && ( ! is_string( $value ) || false === base64_decode( $value, true ) ) ) {
						fclose( $handle );
						return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot contains an invalid encoded row value.' );
					}
				}
				$canonical = wp_json_encode( array_values( $record['values'] ), JSON_UNESCAPED_SLASHES );
				if ( ! is_string( $canonical ) ) {
					fclose( $handle );
					return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot row could not be canonicalized.' );
				}
				hash_update( $current['digest'], $canonical . "\n" );
				++$current['count'];
				continue;
			}
			if ( 'table_end' === $record['kind'] ) {
				if ( null === $current || (string) ( $record['name'] ?? '' ) !== $current['name'] ) {
					fclose( $handle );
					return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot table terminator is invalid.' );
				}
				$digest = hash_final( $current['digest'] );
				if ( (int) ( $record['row_count'] ?? -1 ) !== $current['count'] || ! isset( $record['row_sha256'] ) || ! hash_equals( (string) $record['row_sha256'], $digest ) ) {
					fclose( $handle );
					return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot table row integrity metadata does not match.' );
				}
				$tables[ $current['name'] ] = array(
					'create'     => $current['create'],
					'columns'    => $current['columns'],
					'order_by'   => $current['order_by'],
					'row_count'  => $current['count'],
					'row_sha256' => $digest,
				);
				$current = null;
				continue;
			}
			fclose( $handle );
			return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot contains an unknown record type.' );
		}
		fclose( $handle );
		if ( null === $header || null !== $current || count( $tables ) !== (int) ( $header['table_count'] ?? -1 ) ) {
			return new WP_Error( 'cmsa_database_snapshot_invalid', 'Database snapshot did not terminate cleanly.' );
		}
		return array( 'base_prefix' => (string) $header['base_prefix'], 'tables' => $tables );
	}

	private static function verify_live_database( array $manifest ) {
		$tables = self::wordpress_tables();
		if ( is_wp_error( $tables ) ) {
			return $tables;
		}
		$expected = array_keys( $manifest['tables'] );
		$actual = $tables;
		sort( $expected );
		sort( $actual );
		if ( $expected !== $actual ) {
			return new WP_Error( 'cmsa_database_restore_verification_failed', 'The restored WordPress table set does not match the verified snapshot.' );
		}
		foreach ( $manifest['tables'] as $table => $state ) {
			$rows = self::read_all_table_rows_for_digest( $table, $state['columns'], $state['order_by'] );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			if ( $rows['row_count'] !== (int) $state['row_count'] || ! hash_equals( (string) $state['row_sha256'], (string) $rows['row_sha256'] ) ) {
				return new WP_Error( 'cmsa_database_restore_verification_failed', 'A restored database table does not match its verified row digest.' );
			}
		}
		return count( $expected );
	}

	private static function read_all_table_rows_for_digest( $table, array $columns, array $order_by ) {
		$digest = hash_init( 'sha256' );
		$count = 0;
		$offset = 0;
		do {
			$rows = self::read_table_rows( $table, $columns, $order_by, $offset, self::ROW_BATCH );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			foreach ( $rows as $row ) {
				$encoded = self::encode_row( $row );
				$canonical = wp_json_encode( $encoded, JSON_UNESCAPED_SLASHES );
				if ( ! is_string( $canonical ) ) {
					return new WP_Error( 'cmsa_database_verify', 'A restored database row could not be canonicalized.' );
				}
				hash_update( $digest, $canonical . "\n" );
				++$count;
			}
			$offset += count( $rows );
		} while ( count( $rows ) === self::ROW_BATCH );
		return array( 'row_count' => $count, 'row_sha256' => hash_final( $digest ) );
	}

	private static function table_schema( $table ) {
		global $wpdb;
		$create_query = $wpdb->prepare( 'SHOW CREATE TABLE %i', $table );
		$create = $wpdb->get_row( $create_query, ARRAY_N );
		if ( ! is_array( $create ) || empty( $create[1] ) ) {
			return new WP_Error( 'cmsa_database_schema', 'Could not read a WordPress database table schema.' );
		}
		$columns_query = $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table );
		$definitions = $wpdb->get_results( $columns_query, ARRAY_A );
		if ( ! is_array( $definitions ) || empty( $definitions ) ) {
			return new WP_Error( 'cmsa_database_columns', 'Could not read WordPress database table columns.' );
		}
		$columns = array();
		foreach ( $definitions as $definition ) {
			if ( empty( $definition['Field'] ) || false !== stripos( (string) ( $definition['Extra'] ?? '' ), 'GENERATED' ) ) {
				continue;
			}
			$columns[] = (string) $definition['Field'];
		}
		if ( empty( $columns ) ) {
			return new WP_Error( 'cmsa_database_columns', 'Database table has no restorable columns.' );
		}

		$indexes_query = $wpdb->prepare( 'SHOW INDEX FROM %i', $table );
		$indexes = $wpdb->get_results( $indexes_query, ARRAY_A );
		if ( ! is_array( $indexes ) ) {
			return new WP_Error( 'cmsa_database_indexes', 'Could not read database indexes needed for deterministic backup ordering.' );
		}
		$primary = array();
		foreach ( $indexes as $index ) {
			if ( 'PRIMARY' !== (string) ( $index['Key_name'] ?? '' ) || empty( $index['Column_name'] ) ) {
				continue;
			}
			$primary[] = array( 'sequence' => (int) ( $index['Seq_in_index'] ?? 0 ), 'column' => (string) $index['Column_name'] );
		}
		usort( $primary, static function ( $a, $b ) { return $a['sequence'] <=> $b['sequence']; } );
		$order_by = array_values( array_map( static function ( $index ) { return $index['column']; }, $primary ) );
		if ( empty( $order_by ) ) {
			$order_by = $columns;
		}
		return array( 'create' => (string) $create[1], 'columns' => $columns, 'order_by' => $order_by );
	}

	private static function read_table_rows( $table, array $columns, array $order_by, $offset, $limit ) {
		global $wpdb;
		if ( empty( $columns ) || empty( $order_by ) ) {
			return new WP_Error( 'cmsa_database_order', 'Database backup cannot read a table without deterministic columns and ordering.' );
		}
		$select_placeholders = implode( ',', array_fill( 0, count( $columns ), '%i' ) );
		$order_placeholders = implode( ',', array_fill( 0, count( $order_by ), '%i' ) );
		$query_args = array_merge(
			array_values( $columns ),
			array( $table ),
			array_values( $order_by ),
			array( max( 1, (int) $limit ), max( 0, (int) $offset ) )
		);
		$sql = $wpdb->prepare(
			'SELECT ' . $select_placeholders . ' FROM %i ORDER BY ' . $order_placeholders . ' LIMIT %d OFFSET %d',
			$query_args
		);
		$rows = $wpdb->get_results( $sql, ARRAY_N );
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'cmsa_database_read', 'Could not read database table rows for backup or verification.' );
		}
		return $rows;
	}

	private static function wordpress_tables() {
		global $wpdb;
		$pattern = $wpdb->esc_like( (string) $wpdb->base_prefix ) . '%';
		$query = $wpdb->prepare( 'SHOW FULL TABLES LIKE %s', $pattern );
		$rows = $wpdb->get_results( $query, ARRAY_N );
		if ( ! is_array( $rows ) ) {
			return new WP_Error( 'cmsa_database_tables', 'Could not enumerate WordPress database tables.' );
		}
		$tables = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $row[0] ) || ( isset( $row[1] ) && 'BASE TABLE' !== strtoupper( (string) $row[1] ) ) ) {
				continue;
			}
			$tables[] = (string) $row[0];
		}
		sort( $tables );
		return $tables;
	}

	private static function encode_row( array $row ) {
		$values = array();
		foreach ( $row as $value ) {
			$values[] = null === $value ? null : base64_encode( (string) $value );
		}
		return $values;
	}

	private static function zip_directory( $source, $destination, array $excluded = array() ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'cmsa_ziparchive', 'PHP ZipArchive is required for wp-content backups.' );
		}
		$source = untrailingslashit( wp_normalize_path( (string) $source ) );
		if ( ! is_dir( $source ) ) {
			return new WP_Error( 'cmsa_backup_source', 'The wp-content directory is unavailable.' );
		}
		$normalized_excluded = array();
		foreach ( $excluded as $excluded_path ) {
			if ( ! is_string( $excluded_path ) || '' === trim( $excluded_path ) ) {
				return new WP_Error( 'cmsa_backup_exclusion', 'A backup exclusion path is invalid.' );
			}
			$normalized_excluded[] = untrailingslashit( wp_normalize_path( $excluded_path ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'cmsa_zip_open', 'Could not create the wp-content backup archive.' );
		}
		$root = basename( $source );
		$zip->addEmptyDir( $root );
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $iterator as $item ) {
			$path = wp_normalize_path( $item->getPathname() );
			$skip = false;
			foreach ( $normalized_excluded as $excluded_path ) {
				if ( $path === $excluded_path || 0 === strpos( $path, $excluded_path . '/' ) ) {
					$skip = true;
					break;
				}
			}
			if ( $skip ) {
				continue;
			}
			if ( $item->isLink() ) {
				$zip->close();
				@unlink( $destination );
				return new WP_Error( 'cmsa_backup_symlink', 'wp-content contains a symbolic link; the backup stopped rather than following an external filesystem path.' );
			}
			$relative = ltrim( substr( $path, strlen( $source ) ), '/' );
			$local = $root . '/' . $relative;
			if ( $item->isDir() ) {
				$zip->addEmptyDir( $local );
			} elseif ( $item->isFile() && ! $zip->addFile( $path, $local ) ) {
				$zip->close();
				@unlink( $destination );
				return new WP_Error( 'cmsa_zip_write', 'Could not add a wp-content file to the backup archive.' );
			}
		}
		if ( ! $zip->close() || ! is_file( $destination ) || 0 === (int) filesize( $destination ) ) {
			@unlink( $destination );
			return new WP_Error( 'cmsa_zip_finalize', 'The wp-content backup archive could not be finalized.' );
		}
		return true;
	}

	private static function artifact_path( array $meta, $kind ) {
		$directory = CUA_Local_Storage::directory( 'backups' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		foreach ( (array) ( $meta['files'] ?? array() ) as $file ) {
			if ( ( $file['kind'] ?? '' ) === $kind && ! empty( $file['name'] ) ) {
				$path = trailingslashit( $directory ) . basename( (string) $file['name'] );
				return is_file( $path ) ? $path : new WP_Error( 'cmsa_backup_artifact_missing', 'A recorded backup artifact is missing.' );
			}
		}
		return new WP_Error( 'cmsa_backup_artifact_missing', 'The requested backup does not contain that artifact type.' );
	}

	private static function file_descriptor( $path ) {
		return array( 'name' => basename( $path ), 'size' => (int) filesize( $path ), 'sha256' => (string) hash_file( 'sha256', $path ) );
	}

	private static function write_meta( array $meta ) {
		$directory = CUA_Local_Storage::directory( 'backups' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		if ( empty( $meta['id'] ) || ! preg_match( '/^[a-z0-9-]+$/', (string) $meta['id'] ) ) {
			return new WP_Error( 'cmsa_backup_meta_invalid', 'Backup metadata has an invalid identifier.' );
		}
		$payload = wp_json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $payload ) ) {
			return new WP_Error( 'cmsa_backup_meta', 'Backup metadata could not be encoded.' );
		}
		$path = trailingslashit( $directory ) . $meta['id'] . self::META_SUFFIX;
		$temp = $path . '.tmp-' . strtolower( wp_generate_password( 8, false, false ) );
		$written = @file_put_contents( $temp, $payload, LOCK_EX );
		if ( false === $written || strlen( $payload ) !== $written || ! @rename( $temp, $path ) ) {
			@unlink( $temp );
			return new WP_Error( 'cmsa_backup_meta', 'Backup metadata could not be written atomically.' );
		}
		return true;
	}

	private static function read_meta_file( $id ) {
		$directory = CUA_Local_Storage::directory( 'backups' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		if ( ! preg_match( '/^[a-z0-9-]+$/', (string) $id ) ) {
			return new WP_Error( 'cmsa_backup_id', 'Backup identifier is invalid.' );
		}
		$path = trailingslashit( $directory ) . $id . self::META_SUFFIX;
		if ( ! is_file( $path ) ) {
			return new WP_Error( 'cmsa_backup_not_found', 'Backup metadata was not found.' );
		}
		$data = json_decode( (string) @file_get_contents( $path ), true );
		if ( ! is_array( $data ) || ( $data['id'] ?? '' ) !== $id || empty( $data['files'] ) ) {
			return new WP_Error( 'cmsa_backup_meta_invalid', 'Backup metadata is invalid.' );
		}
		return $data;
	}

	private static function public_meta( array $meta ) {
		$files = array();
		foreach ( (array) ( $meta['files'] ?? array() ) as $file ) {
			$files[] = array(
				'kind'   => sanitize_key( (string) ( $file['kind'] ?? '' ) ),
				'name'   => basename( (string) ( $file['name'] ?? '' ) ),
				'size'   => (int) ( $file['size'] ?? 0 ),
				'sha256' => sanitize_text_field( (string) ( $file['sha256'] ?? '' ) ),
			);
		}
		return array(
			'id'         => (string) ( $meta['id'] ?? '' ),
			'type'       => (string) ( $meta['type'] ?? '' ),
			'scope'      => (string) ( $meta['scope'] ?? '' ),
			'label'      => (string) ( $meta['label'] ?? '' ),
			'created_at' => (string) ( $meta['created_at'] ?? '' ),
			'wordpress'  => (string) ( $meta['wordpress'] ?? '' ),
			'files'      => $files,
		);
	}

	private static function delete_backup_files( array $meta ) {
		$directory = CUA_Local_Storage::directory( 'backups' );
		if ( is_wp_error( $directory ) ) {
			return;
		}
		foreach ( (array) ( $meta['files'] ?? array() ) as $file ) {
			if ( ! empty( $file['name'] ) ) {
				@unlink( trailingslashit( $directory ) . basename( (string) $file['name'] ) );
			}
		}
		if ( ! empty( $meta['id'] ) ) {
			@unlink( trailingslashit( $directory ) . basename( (string) $meta['id'] ) . self::META_SUFFIX );
		}
	}

	private static function cleanup_paths( array $paths ) {
		foreach ( $paths as $path ) {
			if ( is_string( $path ) && is_file( $path ) ) {
				@unlink( $path );
			}
		}
	}

	private static function new_id( $prefix ) {
		return sanitize_key( (string) $prefix ) . '-' . gmdate( 'Ymd-His' ) . '-' . strtolower( wp_generate_password( 10, false, false ) );
	}

	private static function read_id( $input ) {
		$id = is_array( $input ) && isset( $input['id'] ) ? trim( (string) $input['id'] ) : '';
		return preg_match( '/^[a-z0-9-]+$/', $id ) ? $id : new WP_Error( 'cmsa_backup_id', 'A valid local backup identifier is required.' );
	}

	private static function id_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array( 'id' => array( 'type' => 'string', 'pattern' => '^[a-z0-9-]+$', 'minLength' => 1, 'maxLength' => 191 ) ),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	private static function quote_identifier( $identifier ) {
		return '`' . str_replace( '`', '``', (string) $identifier ) . '`';
	}

	private static function write_json_line( $handle, array $record ) {
		$json = wp_json_encode( $record, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			return false;
		}
		$line = $json . "\n";
		$length = strlen( $line );
		$offset = 0;
		while ( $offset < $length ) {
			$written = @fwrite( $handle, substr( $line, $offset ) );
			if ( false === $written || 0 === $written ) {
				return false;
			}
			$offset += $written;
		}
		return true;
	}

	private static function read_meta() {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
		);
	}

	private static function mutation_meta( $destructive ) {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => false, 'destructive' => (bool) $destructive, 'idempotent' => false ),
		);
	}
}
