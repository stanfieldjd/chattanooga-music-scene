<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$restore = wp_get_ability( 'chattanooga-cms-admin/restore-core-backup' );
$update = wp_get_ability( 'chattanooga-cms-admin/update-core' );
$verify = wp_get_ability( 'chattanooga-cms-admin/verify-backup' );
$list = wp_get_ability( 'chattanooga-cms-admin/list-backups' );
if ( ! $restore instanceof WP_Ability || ! $update instanceof WP_Ability || ! $verify instanceof WP_Ability || ! $list instanceof WP_Ability ) {
	fwrite( STDERR, "Core maintenance abilities are missing.\n" );
	exit( 1 );
}

$marker_key = 'cmsa_v2_core_restore_marker';
$before_marker = 'before-core-restore';
$after_marker = 'after-core-restore';
update_option( $marker_key, $before_marker, false );

$readme = ABSPATH . 'readme.html';
if ( ! is_file( $readme ) ) {
	fwrite( STDERR, "WordPress core readme.html is unavailable for the rollback probe.\n" );
	exit( 1 );
}
$readme_before = hash_file( 'sha256', $readme );
if ( ! is_string( $readme_before ) ) {
	fwrite( STDERR, "Could not hash the initial core readme.\n" );
	exit( 1 );
}
$extra_core_file = ABSPATH . 'wp-cmsa-v2-extra.php';
@unlink( $extra_core_file );

$backup = CUA_Platform_Core_Maintenance::create_core_backup( 'v2-core-rollback-proof', 'core' );
if ( is_wp_error( $backup ) || empty( $backup['id'] ) || 'core' !== ( $backup['type'] ?? '' ) || empty( $backup['database_backup_id'] ) || empty( $backup['core_state']['sha256'] ) ) {
	fwrite( STDERR, 'Core backup creation failed: ' . ( is_wp_error( $backup ) ? $backup->get_error_code() . ' ' . $backup->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}
$verified = $verify->execute( array( 'id' => $backup['id'] ) );
if ( is_wp_error( $verified ) || empty( $verified['valid'] ) ) {
	fwrite( STDERR, "Fresh core rollback backup did not verify.\n" );
	exit( 1 );
}

if ( false === file_put_contents( $readme, "\nCMSA V2 CORE MUTATION\n", FILE_APPEND | LOCK_EX )
	|| false === file_put_contents( $extra_core_file, "<?php\n// temporary core-root drift fixture\n", LOCK_EX ) ) {
	fwrite( STDERR, "Could not create disposable core-file drift.\n" );
	exit( 1 );
}
update_option( $marker_key, $after_marker, false );
clearstatcache();
if ( hash_file( 'sha256', $readme ) === $readme_before || ! is_file( $extra_core_file ) || get_option( $marker_key ) !== $after_marker ) {
	fwrite( STDERR, "Core/database drift was not established before restore.\n" );
	exit( 1 );
}

$result = $restore->execute( array( 'id' => $backup['id'] ) );
if ( is_wp_error( $result ) || empty( $result['restored'] ) || empty( $result['rollback_backup_id'] ) || empty( $result['database'] ) || empty( $result['core'] ) ) {
	fwrite( STDERR, 'Core rollback failed: ' . ( is_wp_error( $result ) ? $result->get_error_code() . ' ' . $result->get_error_message() : 'invalid result' ) . "\n" );
	exit( 1 );
}
clearstatcache();
if ( ! is_file( $readme ) || hash_file( 'sha256', $readme ) !== $readme_before ) {
	fwrite( STDERR, "Core rollback did not restore the original readme bytes.\n" );
	exit( 1 );
}
if ( is_file( $extra_core_file ) ) {
	fwrite( STDERR, "Core rollback did not remove a core-root file created after the snapshot.\n" );
	exit( 1 );
}
if ( get_option( $marker_key ) !== $before_marker ) {
	fwrite( STDERR, "Core rollback did not restore the database state captured with the core snapshot.\n" );
	exit( 1 );
}

$disk_version = (string) ( $result['version'] ?? '' );
if ( $disk_version !== (string) $backup['wordpress'] ) {
	fwrite( STDERR, "Core rollback version verification did not match the snapshot.\n" );
	exit( 1 );
}

$current_version = wp_get_wp_version();
$noop = $update->execute( array( 'version' => $current_version ) );
if ( is_wp_error( $noop ) || ! empty( $noop['updated'] ) || 'already-current' !== ( $noop['reason'] ?? '' ) ) {
	fwrite( STDERR, "Exact-current core update did not close as an idempotent no-op.\n" );
	exit( 1 );
}
$invalid = $update->execute( array( 'version' => '../7.1' ) );
if ( ! is_wp_error( $invalid ) || 'cmsa_core_version_invalid' !== $invalid->get_error_code() ) {
	fwrite( STDERR, "Invalid core version input was not rejected before update discovery.\n" );
	exit( 1 );
}

$inventory = $list->execute();
if ( is_wp_error( $inventory ) || empty( $inventory['backups'] ) ) {
	fwrite( STDERR, "Backup inventory was unavailable after core rollback.\n" );
	exit( 1 );
}
$ids = array_map( static function ( $item ) { return $item['id'] ?? ''; }, $inventory['backups'] );
if ( ! in_array( $backup['id'], $ids, true ) || ! in_array( $result['rollback_backup_id'], $ids, true ) ) {
	fwrite( STDERR, "Core rollback manifests were not integrated into the common backup inventory.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( false !== $restore->check_permissions( array( 'id' => $backup['id'] ) )
	|| false !== $update->check_permissions( array( 'version' => $current_version ) ) ) {
	fwrite( STDERR, "Anonymous core administration was not blocked.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
delete_option( $marker_key );
@unlink( $extra_core_file );

echo "cmsa-v2-core-rollback: PASS core_snapshot=verified database_snapshot=verified whole_tree_digest=verified root_drift=removed database_state=restored rollback_checkpoint=verified inventory=integrated current_update=idempotent invalid_version=blocked admin_boundary=verified\n";
exit( 0 );
