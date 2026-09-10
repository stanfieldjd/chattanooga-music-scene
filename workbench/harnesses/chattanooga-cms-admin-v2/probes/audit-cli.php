<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

$secrets = array(
	'CMSA-V2-SECRET-ALPHA-91357',
	'CMSA-V2-SECRET-BETA-24680',
	'CMSA-V2-SECRET-GAMMA-73195',
);

wp_set_current_user( 1 );
$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
$audit   = wp_get_ability( 'chattanooga-cms-admin/get-audit-log' );
if ( ! $catalog instanceof WP_Ability || ! $audit instanceof WP_Ability ) {
	fwrite( STDERR, "Catalog or audit ability is missing.\n" );
	exit( 1 );
}

$catalog_result = $catalog->execute( array() );
if ( is_wp_error( $catalog_result ) || empty( $catalog_result['items'] ) ) {
	fwrite( STDERR, "Catalog failed while locating the audit fixture bridge.\n" );
	exit( 1 );
}

$bridge_name = '';
foreach ( $catalog_result['items'] as $item ) {
	if ( 'quasar-audit/process' === ( $item['target'] ?? '' ) ) {
		$bridge_name = (string) ( $item['bridge'] ?? '' );
		break;
	}
}
if ( '' === $bridge_name ) {
	fwrite( STDERR, "Audit fixture was not dynamically bridged.\n" );
	exit( 1 );
}

$bridge = wp_get_ability( $bridge_name );
if ( ! $bridge instanceof WP_Ability ) {
	fwrite( STDERR, "Audit fixture bridge ability is missing.\n" );
	exit( 1 );
}

$success = $bridge->execute( array( 'secret' => $secrets[0], 'fail' => false ) );
if ( is_wp_error( $success ) || empty( $success['processed'] ) || strlen( $secrets[0] ) !== (int) ( $success['length'] ?? 0 ) ) {
	fwrite( STDERR, "Audited successful provider execution failed.\n" );
	exit( 1 );
}

$failure = $bridge->execute( array( 'secret' => $secrets[1], 'fail' => true ) );
if ( ! is_wp_error( $failure ) || 'quasar_fixture_failure' !== $failure->get_error_code() ) {
	fwrite( STDERR, "Audited provider failure did not propagate correctly.\n" );
	exit( 1 );
}

$invalid = $bridge->execute( array( 'fail' => false ) );
if ( ! is_wp_error( $invalid ) || 'ability_invalid_input' !== $invalid->get_error_code() ) {
	fwrite( STDERR, "Audited invalid input did not fail before execution.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
$denied = $bridge->execute( array( 'secret' => $secrets[2], 'fail' => false ) );
if ( ! is_wp_error( $denied ) || 'ability_invalid_permissions' !== $denied->get_error_code() ) {
	fwrite( STDERR, "Audited permission denial did not fail closed.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
$log = $audit->execute( array( 'limit' => 200 ) );
if ( is_wp_error( $log ) || empty( $log['entries'] ) || ! is_array( $log['entries'] ) ) {
	fwrite( STDERR, "Audit log could not be read.\n" );
	exit( 1 );
}

$found = array(
	'bridge_success' => false,
	'target_success' => false,
	'bridge_failure' => false,
	'target_failure' => false,
	'bridge_invalid' => false,
	'bridge_denied'  => false,
);

foreach ( $log['entries'] as $entry ) {
	if ( ! is_array( $entry ) ) {
		fwrite( STDERR, "Audit log contains a malformed entry.\n" );
		exit( 1 );
	}
	$allowed_keys = array( 'id', 'time', 'user_id', 'ability', 'input_sha256', 'status', 'error_code', 'duration_ms' );
	if ( array_diff( array_keys( $entry ), $allowed_keys ) ) {
		fwrite( STDERR, "Audit entry contains unapproved persisted fields.\n" );
		exit( 1 );
	}
	if ( ! isset( $entry['input_sha256'] ) || ! preg_match( '/^[a-f0-9]{64}$/', (string) $entry['input_sha256'] ) ) {
		fwrite( STDERR, "Audit entry does not contain a bounded one-way input digest.\n" );
		exit( 1 );
	}

	$name   = (string) ( $entry['ability'] ?? '' );
	$status = (string) ( $entry['status'] ?? '' );
	$error  = (string) ( $entry['error_code'] ?? '' );
	if ( $bridge_name === $name && 'success' === $status ) {
		$found['bridge_success'] = true;
	}
	if ( 'quasar-audit/process' === $name && 'success' === $status ) {
		$found['target_success'] = true;
	}
	if ( $bridge_name === $name && 'failed' === $status && 'quasar_fixture_failure' === $error ) {
		$found['bridge_failure'] = true;
	}
	if ( 'quasar-audit/process' === $name && 'failed' === $status && 'quasar_fixture_failure' === $error ) {
		$found['target_failure'] = true;
	}
	if ( $bridge_name === $name && 'invalid_input' === $status && 'ability_invalid_input' === $error ) {
		$found['bridge_invalid'] = true;
	}
	if ( $bridge_name === $name && 'denied' === $status && 'ability_invalid_permissions' === $error && 0 === (int) ( $entry['user_id'] ?? -1 ) ) {
		$found['bridge_denied'] = true;
	}
}

foreach ( $found as $case => $present ) {
	if ( ! $present ) {
		fwrite( STDERR, "Audit outcome was not recorded: {$case}.\n" );
		exit( 1 );
	}
}

$path = CUA_Local_Storage::path( 'audit.jsonl', 'audit' );
if ( is_wp_error( $path ) || ! is_file( $path ) ) {
	fwrite( STDERR, "Audit storage file is unavailable.\n" );
	exit( 1 );
}
$raw = (string) file_get_contents( $path );
foreach ( $secrets as $secret ) {
	if ( false !== strpos( $raw, $secret ) ) {
		fwrite( STDERR, "Raw secret input was persisted in the audit log.\n" );
		exit( 1 );
	}
}
if ( false !== strpos( $raw, '"secret"' ) || false !== strpos( $raw, '"processed"' ) ) {
	fwrite( STDERR, "Raw input/output structure leaked into the audit log.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( false !== $audit->check_permissions( array( 'limit' => 10 ) ) ) {
	fwrite( STDERR, "Anonymous audit-log access was not blocked.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
echo "cmsa-v2-audit: PASS facade_success=logged provider_success=logged facade_failure=logged provider_failure=logged invalid_input=logged permission_denial=logged raw_inputs=absent raw_outputs=absent secrets=absent admin_boundary=verified\n";
exit( 0 );
