<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
global $wpdb;

$create = wp_get_ability( 'chattanooga-cms-admin/create-backup' );
$list = wp_get_ability( 'chattanooga-cms-admin/list-backups' );
$verify = wp_get_ability( 'chattanooga-cms-admin/verify-backup' );
$restore = wp_get_ability( 'chattanooga-cms-admin/restore-database-backup' );
if ( ! $create instanceof WP_Ability || ! $list instanceof WP_Ability || ! $verify instanceof WP_Ability || ! $restore instanceof WP_Ability ) {
	fwrite( STDERR, "Backup abilities are missing.\n" );
	exit( 1 );
}

$table = $wpdb->base_prefix . 'cmsa_v2_snapshot_fixture';
$extra = $wpdb->base_prefix . 'cmsa_v2_extra_after_backup';
$quoted_table = '`' . str_replace( '`', '``', $table ) . '`';
$quoted_extra = '`' . str_replace( '`', '``', $extra ) . '`';
$wpdb->query( "DROP TABLE IF EXISTS {$quoted_table}" );
$wpdb->query( "DROP TABLE IF EXISTS {$quoted_extra}" );
if ( false === $wpdb->query( "CREATE TABLE {$quoted_table} (id bigint unsigned NOT NULL AUTO_INCREMENT, label varchar(191) NOT NULL, payload longblob NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" ) ) {
	fwrite( STDERR, "Could not create the database backup fixture table.\n" );
	exit( 1 );
}

$state_a = array(
	array( 'label' => 'alpha-Ω', 'payload' => "A\0B\x01\xff" ),
	array( 'label' => 'beta-日本語', 'payload' => "second\nrow" ),
);
foreach ( $state_a as $row ) {
	if ( false === $wpdb->insert( $table, $row ) ) {
		fwrite( STDERR, "Could not seed state A for the database backup fixture.\n" );
		exit( 1 );
	}
}

$good = $create->execute( array( 'scope' => 'database', 'label' => 'v2-good-database' ) );
if ( is_wp_error( $good ) || empty( $good['id'] ) || 'database' !== ( $good['scope'] ?? '' ) ) {
	fwrite( STDERR, 'Database backup creation failed: ' . ( is_wp_error( $good ) ? $good->get_error_code() . ' ' . $good->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}
$good_verify = $verify->execute( array( 'id' => $good['id'] ) );
if ( is_wp_error( $good_verify ) || empty( $good_verify['valid'] ) ) {
	fwrite( STDERR, "Fresh database backup did not verify.\n" );
	exit( 1 );
}

$content_backup = $create->execute( array( 'scope' => 'wp-content', 'label' => 'v2-content' ) );
if ( is_wp_error( $content_backup ) || 'wp-content' !== ( $content_backup['scope'] ?? '' ) || 1 !== count( (array) ( $content_backup['files'] ?? array() ) ) ) {
	fwrite( STDERR, 'wp-content backup creation failed: ' . ( is_wp_error( $content_backup ) ? $content_backup->get_error_code() . ' ' . $content_backup->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}
$content_verify = $verify->execute( array( 'id' => $content_backup['id'] ) );
if ( is_wp_error( $content_verify ) || empty( $content_verify['valid'] ) ) {
	fwrite( STDERR, "Fresh wp-content backup did not verify.\n" );
	exit( 1 );
}

$full = $create->execute( array( 'scope' => 'full', 'label' => 'v2-full' ) );
if ( is_wp_error( $full ) || 'full' !== ( $full['scope'] ?? '' ) || 2 !== count( (array) ( $full['files'] ?? array() ) ) ) {
	fwrite( STDERR, 'Full backup creation failed: ' . ( is_wp_error( $full ) ? $full->get_error_code() . ' ' . $full->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}
$full_verify = $verify->execute( array( 'id' => $full['id'] ) );
if ( is_wp_error( $full_verify ) || empty( $full_verify['valid'] ) ) {
	fwrite( STDERR, "Fresh full backup did not verify.\n" );
	exit( 1 );
}

if ( false === $wpdb->query( "TRUNCATE TABLE {$quoted_table}" ) ) {
	fwrite( STDERR, "Could not clear state A.\n" );
	exit( 1 );
}
$state_b = array(
	array( 'label' => 'gamma-state-b', 'payload' => "rollback\0state" ),
	array( 'label' => 'delta-state-b', 'payload' => "another-state" ),
);
foreach ( $state_b as $row ) {
	if ( false === $wpdb->insert( $table, $row ) ) {
		fwrite( STDERR, "Could not seed state B.\n" );
		exit( 1 );
	}
}
if ( false === $wpdb->query( "CREATE TABLE {$quoted_extra} (id int NOT NULL PRIMARY KEY, marker varchar(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" )
	|| false === $wpdb->insert( $extra, array( 'id' => 1, 'marker' => 'must-survive-failed-restore' ) ) ) {
	fwrite( STDERR, "Could not create the post-backup extra table.\n" );
	exit( 1 );
}

$bad = $create->execute( array( 'scope' => 'database', 'label' => 'v2-forced-rollback' ) );
if ( is_wp_error( $bad ) || empty( $bad['id'] ) ) {
	fwrite( STDERR, "Could not create the forced-rollback database snapshot.\n" );
	exit( 1 );
}

$backup_dir = CUA_Local_Storage::directory( 'backups' );
if ( is_wp_error( $backup_dir ) ) {
	fwrite( STDERR, "Backup storage directory is unavailable.\n" );
	exit( 1 );
}
$bad_meta_path = trailingslashit( $backup_dir ) . $bad['id'] . '.meta.json';
$bad_meta = json_decode( (string) file_get_contents( $bad_meta_path ), true );
if ( ! is_array( $bad_meta ) ) {
	fwrite( STDERR, "Forced-rollback metadata could not be read.\n" );
	exit( 1 );
}
$bad_db_name = '';
foreach ( $bad_meta['files'] as $file ) {
	if ( 'database' === ( $file['kind'] ?? '' ) ) {
		$bad_db_name = basename( (string) $file['name'] );
		break;
	}
}
$bad_db_path = trailingslashit( $backup_dir ) . $bad_db_name;
$lines = file( $bad_db_path, FILE_IGNORE_NEW_LINES );
if ( ! is_array( $lines ) ) {
	fwrite( STDERR, "Forced-rollback database artifact could not be read.\n" );
	exit( 1 );
}
$changed = false;
foreach ( $lines as &$line ) {
	$record = json_decode( $line, true );
	if ( ! $changed && is_array( $record ) && 'table' === ( $record['kind'] ?? '' ) && $table === ( $record['name'] ?? '' ) ) {
		$record['create'] = 'CREATE TABLE `' . str_replace( '`', '``', $table ) . '` ( BROKEN SYNTAX )';
		$line = wp_json_encode( $record, JSON_UNESCAPED_SLASHES );
		$changed = true;
	}
}
unset( $line );
if ( ! $changed || false === file_put_contents( $bad_db_path, implode( "\n", $lines ) . "\n", LOCK_EX ) ) {
	fwrite( STDERR, "Could not prepare the forced mid-restore failure artifact.\n" );
	exit( 1 );
}

$bad_integrity = $verify->execute( array( 'id' => $bad['id'] ) );
if ( is_wp_error( $bad_integrity ) || ! empty( $bad_integrity['valid'] ) ) {
	fwrite( STDERR, "Backup artifact tampering was not detected.\n" );
	exit( 1 );
}
foreach ( $bad_meta['files'] as &$file ) {
	if ( 'database' === ( $file['kind'] ?? '' ) ) {
		$file['size'] = filesize( $bad_db_path );
		$file['sha256'] = hash_file( 'sha256', $bad_db_path );
	}
}
unset( $file );
$meta_payload = wp_json_encode( $bad_meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $meta_payload ) || false === file_put_contents( $bad_meta_path, $meta_payload, LOCK_EX ) ) {
	fwrite( STDERR, "Could not update forced-rollback integrity metadata.\n" );
	exit( 1 );
}
$bad_integrity = $verify->execute( array( 'id' => $bad['id'] ) );
if ( is_wp_error( $bad_integrity ) || empty( $bad_integrity['valid'] ) ) {
	fwrite( STDERR, "Forced-rollback artifact did not pass outer integrity after metadata refresh.\n" );
	exit( 1 );
}

$bad_restore = $restore->execute( array( 'id' => $bad['id'] ) );
if ( ! is_wp_error( $bad_restore ) || 'cmsa_database_restore_failed_rolled_back' !== $bad_restore->get_error_code() ) {
	fwrite( STDERR, 'Forced database restore did not fail and roll back as required: ' . ( is_wp_error( $bad_restore ) ? $bad_restore->get_error_code() . ' ' . $bad_restore->get_error_message() : 'unexpected success' ) . "\n" );
	exit( 1 );
}

$state_b_rows = $wpdb->get_results( "SELECT label,payload FROM {$quoted_table} ORDER BY id", ARRAY_A );
if ( ! is_array( $state_b_rows ) || count( $state_b_rows ) !== count( $state_b ) || 1 !== (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$quoted_extra}" ) ) {
	fwrite( STDERR, "Automatic rollback did not restore the pre-failure database table set.\n" );
	exit( 1 );
}
foreach ( $state_b as $index => $expected ) {
	if ( $state_b_rows[ $index ]['label'] !== $expected['label'] || $state_b_rows[ $index ]['payload'] !== $expected['payload'] ) {
		fwrite( STDERR, "Automatic rollback did not restore binary-safe state B data.\n" );
		exit( 1 );
	}
}

$good_restore = $restore->execute( array( 'id' => $good['id'] ) );
if ( is_wp_error( $good_restore ) || empty( $good_restore['restored'] ) || empty( $good_restore['rollback_backup_id'] ) || empty( $good_restore['tables_verified'] ) ) {
	fwrite( STDERR, 'Verified database restore failed: ' . ( is_wp_error( $good_restore ) ? $good_restore->get_error_code() . ' ' . $good_restore->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}

$state_a_rows = $wpdb->get_results( "SELECT label,payload FROM {$quoted_table} ORDER BY id", ARRAY_A );
if ( ! is_array( $state_a_rows ) || count( $state_a_rows ) !== count( $state_a ) ) {
	fwrite( STDERR, "Verified database restore did not recover state A row count.\n" );
	exit( 1 );
}
foreach ( $state_a as $index => $expected ) {
	if ( $state_a_rows[ $index ]['label'] !== $expected['label'] || $state_a_rows[ $index ]['payload'] !== $expected['payload'] ) {
		fwrite( STDERR, "Verified database restore did not recover binary-safe state A data.\n" );
		exit( 1 );
	}
}
$extra_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $extra ) );
if ( $extra_exists ) {
	fwrite( STDERR, "Verified restore did not remove a WordPress-prefixed table created after the snapshot.\n" );
	exit( 1 );
}

$list_result = $list->execute();
if ( is_wp_error( $list_result ) || empty( $list_result['backups'] ) ) {
	fwrite( STDERR, "Backup inventory could not be read.\n" );
	exit( 1 );
}
$ids = array_map( static function ( $item ) { return $item['id'] ?? ''; }, $list_result['backups'] );
foreach ( array( $good['id'], $content_backup['id'], $full['id'], $bad['id'], $good_restore['rollback_backup_id'] ) as $expected_id ) {
	if ( ! in_array( $expected_id, $ids, true ) ) {
		fwrite( STDERR, "Backup inventory is missing a created backup manifest.\n" );
		exit( 1 );
	}
}
foreach ( $list_result['backups'] as $entry ) {
	$serialized = wp_json_encode( $entry );
	if ( false !== strpos( (string) $serialized, $backup_dir ) ) {
		fwrite( STDERR, "Backup inventory exposed the server-side storage path.\n" );
		exit( 1 );
	}
}

wp_set_current_user( 0 );
if ( false !== $create->check_permissions( array( 'scope' => 'database' ) )
	|| false !== $verify->check_permissions( array( 'id' => $good['id'] ) )
	|| false !== $restore->check_permissions( array( 'id' => $good['id'] ) )
	|| false !== $list->check_permissions() ) {
	fwrite( STDERR, "Anonymous backup administration was not blocked.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
$wpdb->query( "DROP TABLE IF EXISTS {$quoted_table}" );
$wpdb->query( "DROP TABLE IF EXISTS {$quoted_extra}" );

echo "cmsa-v2-backups: PASS database_create=verified wp_content_create=verified full_create=verified sha256=verified tamper=detected streaming_rows=verified binary_data=verified exact_table_set=verified restore_readback=verified automatic_pre_restore_backup=verified forced_failure=rolled_back inventory=verified storage_path=private admin_boundary=verified\n";
exit( 0 );
