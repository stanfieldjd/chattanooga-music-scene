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
	'cmsa.discovery',
	'cmsa.read-bridge',
	'cmsa.stability-check',
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

// Unadvertised legacy admin tool names must not remain callable directly.
$legacy_call = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
$legacy_call->set_header( 'content-type', 'application/json' );
$legacy_call->set_header( 'MCP-Protocol-Version', '2026-07-28' );
$legacy_call->set_header( 'Mcp-Method', 'tools/call' );
$legacy_call->set_header( 'Mcp-Name', 'cmsa.list-plugins' );
$legacy_call->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 350,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'cmsa.list-plugins',
				'arguments' => array(),
				'_meta'     => array(
					'io.modelcontextprotocol/protocolVersion'    => '2026-07-28',
					'io.modelcontextprotocol/clientCapabilities' => array(),
					'io.modelcontextprotocol/clientInfo'         => array( 'name' => 'cmsa-admin-surface-probe', 'version' => '1.0.0' ),
				),
			),
		)
	)
);
$legacy_response = rest_do_request( $legacy_call );
cmsa_native_mcp_surface_assert( 200 === $legacy_response->get_status(), 'Unadvertised legacy tool rejection did not return an MCP tool result.' );
$legacy_data = $legacy_response->get_data();
cmsa_native_mcp_surface_assert( true === ( $legacy_data['result']['isError'] ?? false ), 'Unadvertised legacy tool name remained directly callable.' );
cmsa_native_mcp_surface_assert(
	'cmsa_mcp_tool_not_found' === ( $legacy_data['result']['_meta']['chattanooga-cms-admin/errorCode'] ?? '' ),
	'Unadvertised legacy tool rejection returned the wrong error code.'
);

// Generic Settings API inspection must never disclose stored values, even for show_in_rest settings.
$secret_setting = 'cmsa_redteam_secret';
register_setting(
	'general',
	$secret_setting,
	array(
		'type'         => 'string',
		'show_in_rest' => true,
	)
);
update_option( $secret_setting, 'redteam-secret-must-not-escape' );
$settings_ability = wp_get_ability( 'chattanooga-cms-admin/get-registered-setting' );
cmsa_native_mcp_surface_assert( $settings_ability instanceof WP_Ability, 'Registered-setting inspection ability is unavailable.' );
$settings_result = $settings_ability->execute( array( 'setting' => $secret_setting ) );
cmsa_native_mcp_surface_assert( ! is_wp_error( $settings_result ), 'Registered-setting inspection failed.' );
cmsa_native_mcp_surface_assert( ! array_key_exists( 'value', $settings_result ), 'Generic setting inspection disclosed a stored value.' );
cmsa_native_mcp_surface_assert( false === ( $settings_result['value_exposed'] ?? null ), 'Generic setting inspection reported a stored value as exposed.' );

// The raw core settings endpoint must not be bridged because it can expose
// stored provider credentials that the dedicated settings abilities suppress.
$raw_settings_route = '/wp/v2/settings';
$raw_settings_bridge = 'chattanooga-cms-admin/rest-' . substr( hash( 'sha256', 'GET|' . $raw_settings_route ), 0, 24 );
$raw_settings_result = CUA_REST_Bridge::execute_bridge( $raw_settings_bridge, array( 'path' => $raw_settings_route ) );
cmsa_native_mcp_surface_assert( is_wp_error( $raw_settings_result ), 'Raw /wp/v2/settings remained executable through the generic REST bridge.' );
cmsa_native_mcp_surface_assert(
	'cua_rest_route_unavailable' === $raw_settings_result->get_error_code(),
	'Raw /wp/v2/settings rejection returned the wrong error code.'
);

delete_option( $secret_setting );
if ( function_exists( 'unregister_setting' ) ) {
	unregister_setting( 'general', $secret_setting );
}

// Optional REST arguments with null defaults must remain omitted when the caller
// does not supply them. Injecting null before validation breaks valid collection reads.
register_rest_route(
	'cmsa-redteam/v1',
	'/optional-null',
	array(
		'methods'             => 'GET',
		'callback'            => static function ( WP_REST_Request $request ) {
			return rest_ensure_response(
				array(
					'has_optional' => null !== $request->get_param( 'optional' ),
				)
			);
		},
		'permission_callback' => static function () { return true; },
		'args'                => array(
			'optional' => array(
				'type'    => 'string',
				'default' => null,
			),
		),
	)
);
CUA_REST_Bridge::register_external_bridges();
$optional_route = '/cmsa-redteam/v1/optional-null';
$optional_bridge = 'chattanooga-cms-admin/rest-' . substr( hash( 'sha256', 'GET|' . $optional_route ), 0, 24 );
$optional_result = CUA_REST_Bridge::execute_bridge( $optional_bridge, array( 'path' => $optional_route ) );
cmsa_native_mcp_surface_assert( ! is_wp_error( $optional_result ), 'REST bridge rejected an omitted optional argument with a null default.' );
cmsa_native_mcp_surface_assert( 200 === ( $optional_result['status'] ?? 0 ), 'REST bridge optional-null regression returned the wrong status.' );
cmsa_native_mcp_surface_assert( false === ( $optional_result['data']['has_optional'] ?? null ), 'REST bridge injected an omitted null-default argument.' );

// The MCP transport Authorization header must not leak into nested provider REST routes.
register_rest_route(
	'cmsa-redteam/v1',
	'/auth-isolation',
	array(
		'methods'             => 'GET',
		'permission_callback' => static function () {
			return empty( $_SERVER['HTTP_AUTHORIZATION'] ) && empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) && empty( $_SERVER['Authorization'] );
		},
		'callback'            => static function () {
			return rest_ensure_response(
				array(
					'authorization_present' => ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) || ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) || ! empty( $_SERVER['Authorization'] ),
					'user_id' => get_current_user_id(),
				)
			);
		},
	)
);
CUA_REST_Bridge::register_external_bridges();
$auth_route = '/cmsa-redteam/v1/auth-isolation';
$auth_bridge = 'chattanooga-cms-admin/rest-' . substr( hash( 'sha256', 'GET|' . $auth_route ), 0, 24 );
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer cmsa-redteam-outer-token';
$auth_user_before = get_current_user_id();
$auth_result = CUA_REST_Bridge::execute_bridge( $auth_bridge, array( 'path' => $auth_route ) );
cmsa_native_mcp_surface_assert( ! is_wp_error( $auth_result ), 'REST bridge failed Authorization-isolation regression.' );
cmsa_native_mcp_surface_assert( 200 === ( $auth_result['status'] ?? 0 ), 'Authorization-isolation regression returned the wrong status.' );
cmsa_native_mcp_surface_assert( false === ( $auth_result['data']['authorization_present'] ?? true ), 'Nested REST callback saw the outer MCP Authorization header.' );
cmsa_native_mcp_surface_assert( $auth_user_before === ( $auth_result['data']['user_id'] ?? -1 ), 'Authorization isolation changed the authenticated WordPress user.' );
cmsa_native_mcp_surface_assert( 'Bearer cmsa-redteam-outer-token' === ( $_SERVER['HTTP_AUTHORIZATION'] ?? '' ), 'Outer Authorization header was not restored after nested REST execution.' );
unset( $_SERVER['HTTP_AUTHORIZATION'] );

$cron_ability = wp_get_ability( 'chattanooga-cms-admin/list-cron-events' );
cmsa_native_mcp_surface_assert( $cron_ability instanceof WP_Ability, 'Cron inventory ability is unavailable.' );
$cron_result = $cron_ability->execute( array( 'limit' => 5 ) );
cmsa_native_mcp_surface_assert( ! is_wp_error( $cron_result ), 'Cron inventory ability failed.' );
cmsa_native_mcp_surface_assert( is_array( $cron_result['items'] ?? null ), 'Cron inventory ability did not return an items array.' );
foreach ( $cron_result['items'] as $cron_item ) {
	cmsa_native_mcp_surface_assert( is_array( $cron_item ), 'Cron inventory returned a malformed item.' );
	cmsa_native_mcp_surface_assert( ! array_key_exists( 'args', $cron_item ), 'Cron inventory disclosed raw hook arguments.' );
	cmsa_native_mcp_surface_assert( isset( $cron_item['args_sha256'] ), 'Cron inventory omitted the argument conflict fingerprint.' );
}

$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
cmsa_native_mcp_surface_assert( $catalog instanceof WP_Ability, 'Universal capability catalog is unavailable.' );
$catalog_result = $catalog->execute( array() );
cmsa_native_mcp_surface_assert(
	! is_wp_error( $catalog_result ) && is_array( $catalog_result['items'] ?? null ) && ! empty( $catalog_result['items'] ),
	'Universal capability catalog did not preserve access to hidden provider contracts.'
);

echo 'cmsa-native-mcp-admin-surface: PASS registered=' . $registered_count . ' public_exposed=' . count( $names ) . ' hidden_facades=' . count( $hidden_facades ) . " administrator_surface=bounded catalog=available\n";
exit( 0 );
