<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "error-redaction-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

function cmsa_redaction_contains_marker( $value, $marker, $depth = 0 ) {
	if ( $depth > 8 ) {
		return false;
	}
	if ( is_string( $value ) || is_numeric( $value ) ) {
		return false !== strpos( (string) $value, $marker );
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			if ( false !== strpos( (string) $key, $marker ) || cmsa_redaction_contains_marker( $item, $marker, $depth + 1 ) ) {
				return true;
			}
		}
		return false;
	}
	if ( is_object( $value ) ) {
		return cmsa_redaction_contains_marker( get_object_vars( $value ), $marker, $depth + 1 );
	}
	return false;
}

function cmsa_redaction_error_leaks( $error, $marker ) {
	if ( ! is_wp_error( $error ) ) {
		return true;
	}
	return cmsa_redaction_contains_marker( $error->get_error_messages(), $marker ) || cmsa_redaction_contains_marker( $error->get_all_error_data(), $marker );
}

$marker = 'cmsa-redaction-' . strtolower( wp_generate_password( 18, false, false ) );
$failures = array();
$updates = new CMSA_Updates();

// Boundary 1: plugin API errors must not expose upstream diagnostic text.
$plugins_api_filter = function ( $result, $action, $args ) use ( $marker ) {
	if ( 'plugin_information' === $action && is_object( $args ) && isset( $args->slug ) && 'cmsa-redaction-probe' === $args->slug ) {
		return new WP_Error( 'cmsa_probe_upstream', 'Injected upstream plugin API diagnostic ' . $marker );
	}
	return $result;
};
add_filter( 'plugins_api', $plugins_api_filter, 10, 3 );
$api_error = $updates->install_plugin( 'cmsa-redaction-probe' );
remove_filter( 'plugins_api', $plugins_api_filter, 10 );
if ( ! is_wp_error( $api_error ) ) {
	$failures[] = 'plugin_api_did_not_fail';
} elseif ( cmsa_redaction_error_leaks( $api_error, $marker ) ) {
	$failures[] = 'plugin_api_error_leaked_marker';
}

// Boundary 2: updater failure detail must not expose an upstream diagnostic.
$legacy_plugin = 'classic-editor/classic-editor.php';
$plugins = get_plugins();
if ( ! isset( $plugins[ $legacy_plugin ] ) || '1.6' !== $plugins[ $legacy_plugin ]['Version'] ) {
	$failures[] = 'legacy_plugin_fixture_missing';
} else {
	$pre_download_filter = function ( $reply, $package, $upgrader, $hook_extra ) use ( $marker ) {
		if ( $upgrader instanceof Plugin_Upgrader ) {
			return new WP_Error( 'cmsa_probe_download', 'Injected package download diagnostic ' . $marker );
		}
		return $reply;
	};
	add_filter( 'upgrader_pre_download', $pre_download_filter, 10, 4 );
	$update_error = $updates->update_plugin( $legacy_plugin, '1.6' );
	remove_filter( 'upgrader_pre_download', $pre_download_filter, 10 );

	if ( ! is_wp_error( $update_error ) ) {
		$failures[] = 'plugin_update_did_not_fail';
	} else {
		if ( cmsa_redaction_error_leaks( $update_error, $marker ) ) {
			$failures[] = 'plugin_update_error_leaked_marker';
		}
		$data = $update_error->get_error_data();
		if ( ! is_array( $data ) || empty( $data['rolled_back'] ) ) {
			$failures[] = 'plugin_update_rollback_not_confirmed';
		}
	}

	wp_clean_plugins_cache( true );
	$plugins_after = get_plugins();
	if ( ! isset( $plugins_after[ $legacy_plugin ] ) || '1.6' !== $plugins_after[ $legacy_plugin ]['Version'] ) {
		$failures[] = 'plugin_update_fixture_not_restored';
	}
}

// Boundary 3: database restore failures must not expose raw SQL/database diagnostics.
$backups = new CMSA_Backups();
$db_backup = $backups->create_backup( 'database', 'error-redaction-probe' );
if ( is_wp_error( $db_backup ) || empty( $db_backup['id'] ) ) {
	$failures[] = 'database_backup_failed';
} else {
	$directory = CMSA_Backups::get_storage_directory();
	$sql_path = trailingslashit( $directory ) . $db_backup['id'] . '-database.sql';
	$meta_path = trailingslashit( $directory ) . $db_backup['id'] . '.json';
	$original_sql = is_file( $sql_path ) ? file_get_contents( $sql_path ) : false;
	$original_meta = is_file( $meta_path ) ? file_get_contents( $meta_path ) : false;
	if ( false === $original_sql || false === $original_meta ) {
		$failures[] = 'database_backup_artifacts_missing';
	} else {
		$bad_table = 'cmsa_' . str_replace( '-', '_', $marker );
		$mutated_sql = $original_sql . "\nSELECT * FROM `{$bad_table}`;\n";
		file_put_contents( $sql_path, $mutated_sql, LOCK_EX );
		$meta = json_decode( $original_meta, true );
		foreach ( $meta['files'] as &$file ) {
			if ( basename( $file['name'] ) === basename( $sql_path ) ) {
				$file['size'] = filesize( $sql_path );
				$file['sha256'] = hash_file( 'sha256', $sql_path );
			}
		}
		unset( $file );
		file_put_contents( $meta_path, wp_json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), LOCK_EX );

		$db_error = $backups->restore_database_backup( $db_backup['id'] );
		if ( ! is_wp_error( $db_error ) ) {
			$failures[] = 'database_restore_did_not_fail';
		} elseif ( cmsa_redaction_error_leaks( $db_error, str_replace( '-', '_', $marker ) ) || cmsa_redaction_error_leaks( $db_error, $marker ) ) {
			$failures[] = 'database_restore_error_leaked_marker';
		}

		// Restore the pristine backup artifact and database before leaving the probe.
		file_put_contents( $sql_path, $original_sql, LOCK_EX );
		file_put_contents( $meta_path, $original_meta, LOCK_EX );
		$cleanup_restore = $backups->restore_database_backup( $db_backup['id'] );
		if ( is_wp_error( $cleanup_restore ) || empty( $cleanup_restore['restored'] ) ) {
			$failures[] = 'database_cleanup_restore_failed';
		}
	}
}

if ( $failures ) {
	fwrite( STDERR, 'error-redaction-cli: FAIL ' . implode( ',', $failures ) . "\n" );
	exit( 1 );
}

printf( "error-redaction-cli: PASS boundaries=plugin-api,plugin-updater,database-restore marker=absent rollback=verified\n" );
