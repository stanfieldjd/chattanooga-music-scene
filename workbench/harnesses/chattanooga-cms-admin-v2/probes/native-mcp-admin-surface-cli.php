<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_native_mcp_surface_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_native_mcp_surface_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_native_mcp_surface_fail( $message );
	}
}

function cmsa_native_mcp_expected_tool_name( $ability_name ) {
	$prefix = 'chattanooga-cms-admin/';
	$short  = substr( (string) $ability_name, strlen( $prefix ) );
	return 'cmsa.' . preg_replace( '/[^A-Za-z0-9_.-]/', '-', $short );
}

wp_set_current_user( 1 );

$expected = array(
	'cmsa.activate-plugin',
	'cmsa.catalog',
	'cmsa.clear-cache',
	'cmsa.create-backup',
	'cmsa.deactivate-plugin',
	'cmsa.delete-plugin',
	'cmsa.delete-theme',
	'cmsa.get-audit-log',
	'cmsa.get-health',
	'cmsa.get-registered-setting',
	'cmsa.install-plugin',
	'cmsa.install-plugin-package',
	'cmsa.install-theme',
	'cmsa.list-backups',
	'cmsa.list-plugins',
	'cmsa.list-registered-settings',
	'cmsa.list-themes',
	'cmsa.list-updates',
	'cmsa.read-bridge',
	'cmsa.restore-component-backup',
	'cmsa.restore-core-backup',
	'cmsa.restore-database-backup',
	'cmsa.set-plugin-auto-update',
	'cmsa.set-theme-auto-update',
	'cmsa.stability-check',
	'cmsa.switch-theme',
	'cmsa.uninstall-plugin',
	'cmsa.update-core',
	'cmsa.update-plugin',
	'cmsa.update-registered-setting',
	'cmsa.update-theme',
	'cmsa.verify-backup',
	'cmsa.write-bridge',
);
if ( ! function_exists( 'wp_get_abilities' ) ) {
	cmsa_native_mcp_surface_fail( 'WordPress Abilities API is unavailable.' );
}
$registered_count = 0;
$public_names = array();
$hidden_facades = array();
foreach ( wp_get_abilities() as $ability ) {
	if ( ! $ability instanceof WP_Ability ) { continue; }
	$ability_name = $ability->get_name();
	if ( 0 !== strpos( $ability_name, 'chattanooga-cms-admin/' ) ) { continue; }
	++$registered_count;
	$meta = $ability->get_meta();
	$mcp_public = isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && array_key_exists( 'public', $meta['mcp'] ) && null !== $meta['mcp']['public']
		? true === $meta['mcp']['public']
		: true === ( $meta['public'] ?? false );
	if ( $mcp_public ) { $public_names[] = cmsa_native_mcp_expected_tool_name( $ability_name ); }
	if ( preg_match( '/^chattanooga-cms-admin\\/(?:bridge|rest)-[a-f0-9]{24}$/', $ability_name ) ) {
		$hidden_facades[] = cmsa_native_mcp_expected_tool_name( $ability_name );
	}
}
sort( $expected, SORT_STRING );

foreach ( $expected as $expected_tool ) {
	$expected_short = substr( $expected_tool, strlen( 'cmsa.' ) );
	$expected_ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'chattanooga-cms-admin/' . $expected_short ) : null;
	if ( $expected_ability instanceof WP_Ability ) {
		$expected_meta = $expected_ability->get_meta();
		$expected_public = isset( $expected_meta['mcp'] ) && is_array( $expected_meta['mcp'] ) && array_key_exists( 'public', $expected_meta['mcp'] ) && null !== $expected_meta['mcp']['public']
			? true === $expected_meta['mcp']['public']
			: true === ( $expected_meta['public'] ?? false );
		if ( $expected_public ) {
			$public_names[] = $expected_tool;
		}
	}
}
$public_names = array_values( array_unique( $public_names ) );
sort( $public_names, SORT_STRING );
sort( $hidden_facades, SORT_STRING );
cmsa_native_mcp_surface_assert( ! empty( $public_names ), 'No MCP-public Chattanooga administrator abilities were registered.' );
cmsa_native_mcp_surface_assert( ! empty( $hidden_facades ), 'No generated internal facade abilities were registered for bounded-surface verification.' );
foreach ( $expected as $core_tool ) {
	cmsa_native_mcp_surface_assert( in_array( $core_tool, $public_names, true ), 'Bounded core tool has no registered public ability: ' . $core_tool );
}
$names  = array();
$cursor = '';
$id     = 301;
do {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', 'tools/list' );
	$params = array(
		'_meta' => array(
			'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
			'io.modelcontextprotocol/clientCapabilities' => array(),
			'io.modelcontextprotocol/clientInfo' => array( 'name' => 'cmsa-admin-surface-probe', 'version' => '1.0.0' ),
		),
	);
	if ( '' !== $cursor ) { $params['cursor'] = $cursor; }
	$request->set_body( wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => $id++, 'method' => 'tools/list', 'params' => $params ) ) );
	$response = rest_do_request( $request );
	cmsa_native_mcp_surface_assert( 200 === $response->get_status(), 'MCP tools/list failed.' );
	$data = $response->get_data();
	$tools = $data['result']['tools'] ?? null;
	cmsa_native_mcp_surface_assert( is_array( $tools ), 'MCP tools/list did not return a tools array.' );
	foreach ( $tools as $tool ) { if ( is_array( $tool ) && isset( $tool['name'] ) ) { $names[] = (string) $tool['name']; } }
	$cursor = isset( $data['result']['nextCursor'] ) ? (string) $data['result']['nextCursor'] : '';
} while ( '' !== $cursor );
$names = array_values( array_unique( $names ) );
sort( $names, SORT_STRING );
$unexpected = array_values( array_diff( $names, $expected ) );
$leaked_facades = array_values( array_intersect( $names, $hidden_facades ) );
cmsa_native_mcp_surface_assert( empty( $unexpected ), 'MCP surface contains tools outside the bounded core set: ' . implode( ', ', $unexpected ) );
cmsa_native_mcp_surface_assert( empty( $leaked_facades ), 'MCP surface leaked generated internal facade tools: ' . implode( ', ', $leaked_facades ) );
cmsa_native_mcp_surface_assert( $names === $expected, 'MCP tool list does not exactly match the bounded deterministic core set.' );

$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
cmsa_native_mcp_surface_assert( $catalog instanceof WP_Ability, 'Universal capability catalog is unavailable.' );
$catalog_result = $catalog->execute( array() );
cmsa_native_mcp_surface_assert(
	! is_wp_error( $catalog_result ) && is_array( $catalog_result['items'] ?? null ) && ! empty( $catalog_result['items'] ),
	'Universal capability catalog did not preserve access to hidden provider contracts.'
);

echo 'cmsa-native-mcp-admin-surface: PASS registered=' . $registered_count . ' public_exposed=' . count( $names ) . ' hidden_facades=' . count( $hidden_facades ) . " administrator_surface=bounded catalog=available\n";
exit( 0 );
