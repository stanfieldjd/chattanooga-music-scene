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

wp_set_current_user( 1 );

$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$request->set_header( 'content-type', 'application/json' );
$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
$request->set_header( 'Mcp-Method', 'tools/list' );
$request->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 301,
			'method'  => 'tools/list',
			'params'  => array(
				'_meta' => array(
					'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
					'io.modelcontextprotocol/clientInfo'      => array(
						'name'    => 'cmsa-admin-surface-probe',
						'version' => '1.0.0',
					),
				),
			),
		)
	)
);

$response = rest_do_request( $request );
cmsa_native_mcp_surface_assert( 200 === $response->get_status(), 'MCP tools/list failed.' );
$data  = $response->get_data();
$tools = $data['result']['tools'] ?? null;
cmsa_native_mcp_surface_assert( is_array( $tools ), 'MCP tools/list did not return a tools array.' );

$names = array();
foreach ( $tools as $tool ) {
	if ( is_array( $tool ) && isset( $tool['name'] ) ) {
		$names[] = (string) $tool['name'];
	}
}

$required = array(
	'cmsa.catalog',
	'cmsa.read-bridge',
	'cmsa.write-bridge',
	'cmsa.get-health',
	'cmsa.list-updates',
	'cmsa.list-plugins',
	'cmsa.list-themes',
	'cmsa.list-backups',
	'cmsa.get-audit-log',
	'cmsa.create-backup',
	'cmsa.verify-backup',
	'cmsa.update-plugin',
	'cmsa.update-theme',
	'cmsa.update-core',
	'cmsa.install-plugin',
	'cmsa.install-plugin-package',
	'cmsa.activate-plugin',
	'cmsa.deactivate-plugin',
	'cmsa.delete-plugin',
	'cmsa.set-plugin-auto-update',
	'cmsa.install-theme',
	'cmsa.switch-theme',
	'cmsa.delete-theme',
	'cmsa.set-theme-auto-update',
	'cmsa.clear-cache',
	'cmsa.restore-component-backup',
	'cmsa.restore-database-backup',
	'cmsa.restore-core-backup',
	'cmsa.list-registered-settings',
	'cmsa.get-registered-setting',
	'cmsa.update-registered-setting',
	'cmsa.upload-media',
	'cmsa.private-content-types',
	'cmsa.private-content-query',
	'cmsa.private-content-get',
	'cmsa.private-content-save',
	'cmsa.private-content-delete',
);

$missing = array_values( array_diff( $required, $names ) );
cmsa_native_mcp_surface_assert(
	empty( $missing ),
	'MCP administrator surface is incomplete. Missing: ' . implode( ', ', $missing )
);

foreach ( $names as $name ) {
	cmsa_native_mcp_surface_assert( 0 !== strpos( $name, 'cmsa.bridge-' ), 'Private dynamic Ability facade leaked into MCP: ' . $name );
	cmsa_native_mcp_surface_assert( 0 !== strpos( $name, 'cmsa.rest-' ), 'Private dynamic REST facade leaked into MCP: ' . $name );
}

echo 'cmsa-native-mcp-admin-surface: PASS required=' . count( $required ) . ' exposed=' . count( $names ) . " private_facades=hidden\n";
exit( 0 );
