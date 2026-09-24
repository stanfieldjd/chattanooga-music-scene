<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_persistence_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_persistence_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_persistence_fail( $message );
	}
}

function cmsa_persistence_modern( $method, array $params = array(), $id = 501 ) {
	$params['_meta'] = array(
		'io.modelcontextprotocol/protocolVersion'    => '2026-07-28',
		'io.modelcontextprotocol/clientCapabilities' => array(),
		'io.modelcontextprotocol/clientInfo'         => array(
			'name'    => 'cmsa-persistence-probe',
			'version' => '1.0.0',
		),
	);

	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', $method );
	if ( 'tools/call' === $method ) {
		$request->set_header( 'Mcp-Name', (string) ( $params['name'] ?? '' ) );
	}
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => $method,
				'params'  => $params,
			)
		)
	);
	return rest_do_request( $request );
}

function cmsa_persistence_legacy_initialize( $id ) {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2025-11-25' );
	$request->set_header( 'Mcp-Method', 'initialize' );
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => 'initialize',
				'params'  => array(
					'protocolVersion' => '2025-11-25',
					'capabilities'    => array(),
					'clientInfo'      => array(
						'name'    => 'cmsa-persistence-probe',
						'version' => '1.0.0',
					),
				),
			)
		)
	);
	return rest_do_request( $request );
}

function cmsa_persistence_legacy_list( $session_id, $id ) {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2025-11-25' );
	$request->set_header( 'Mcp-Method', 'tools/list' );
	$request->set_header( 'Mcp-Session-Id', $session_id );
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => 'tools/list',
				'params'  => array(),
			)
		)
	);
	return rest_do_request( $request );
}

wp_set_current_user( 1 );
cmsa_persistence_assert( current_user_can( 'manage_options' ), 'Probe user is not an administrator.' );

$session_meta_key = 'chattanooga_cms_admin_mcp_sessions';
delete_user_meta( get_current_user_id(), $session_meta_key );

$initialize = cmsa_persistence_legacy_initialize( 510 );
$initialize_headers = array_change_key_case( $initialize->get_headers(), CASE_LOWER );
$session_id = trim( (string) ( $initialize_headers['mcp-session-id'] ?? '' ) );
cmsa_persistence_assert( 200 === $initialize->get_status(), 'Durable legacy initialize failed.' );
cmsa_persistence_assert( '' !== $session_id, 'Durable legacy initialize emitted no session id.' );

wp_cache_delete( get_current_user_id(), 'user_meta' );
$sessions = get_user_meta( get_current_user_id(), $session_meta_key, true );
cmsa_persistence_assert( is_array( $sessions ) && isset( $sessions[ $session_id ] ), 'Issued legacy session was not durably persisted in user meta.' );
cmsa_persistence_assert( '2025-11-25' === ( $sessions[ $session_id ]['protocolVersion'] ?? '' ), 'Persisted legacy session has the wrong protocol version.' );

wp_cache_flush();
$legacy_after_flush = cmsa_persistence_legacy_list( $session_id, 511 );
cmsa_persistence_assert( 200 === $legacy_after_flush->get_status(), 'Legacy session did not survive an object-cache flush.' );
cmsa_persistence_assert( ! empty( $legacy_after_flush->get_data()['result']['tools'] ?? array() ), 'Legacy tools/list returned no tools after cache flush.' );

delete_user_meta( get_current_user_id(), $session_meta_key );
$block_session_writes = static function ( $check, $object_id, $meta_key ) use ( $session_meta_key ) {
	if ( (int) $object_id === get_current_user_id() && $session_meta_key === (string) $meta_key ) {
		return false;
	}
	return $check;
};
add_filter( 'update_user_metadata', $block_session_writes, 10, 5 );
$failed_initialize = cmsa_persistence_legacy_initialize( 512 );
remove_filter( 'update_user_metadata', $block_session_writes, 10 );
$failed_headers = array_change_key_case( $failed_initialize->get_headers(), CASE_LOWER );
$failed_data = $failed_initialize->get_data();
cmsa_persistence_assert( 500 === $failed_initialize->get_status(), 'Session-store write failure did not fail initialize honestly.' );
cmsa_persistence_assert( -32603 === ( $failed_data['error']['code'] ?? null ), 'Session-store write failure returned the wrong JSON-RPC error.' );
cmsa_persistence_assert( empty( $failed_headers['mcp-session-id'] ), 'Failed session persistence still emitted a phantom Mcp-Session-Id.' );

$expected_names = array(
	'cmsa.discovery',
	'cmsa.stability-check',
	'cmsa.read-bridge',
	'cmsa.write-bridge',
);
$fingerprint = '';
for ( $iteration = 0; $iteration < 20; ++$iteration ) {
	wp_cache_flush();
	$response = cmsa_persistence_modern( 'tools/list', array(), 520 + $iteration );
	$data = $response->get_data();
	$headers = array_change_key_case( $response->get_headers(), CASE_LOWER );
	cmsa_persistence_assert( 200 === $response->get_status(), 'Modern tools/list failed during repeated reconnect simulation.' );
	cmsa_persistence_assert( ! isset( $headers['mcp-session-id'] ), 'Modern reconnect simulation emitted a session id.' );
	cmsa_persistence_assert( 0 === ( $data['result']['ttlMs'] ?? null ), 'Modern tools/list is not immediately stale (ttlMs must be 0).' );
	cmsa_persistence_assert( 'private' === ( $data['result']['cacheScope'] ?? null ), 'Modern tools/list cacheScope is not private.' );
	$names = array_map(
		static function ( $tool ) {
			return (string) ( $tool['name'] ?? '' );
		},
		(array) ( $data['result']['tools'] ?? array() )
	);
	cmsa_persistence_assert( $expected_names === $names, 'Modern bootstrap tool ABI changed during reconnect simulation.' );
	$current_fingerprint = hash( 'sha256', wp_json_encode( $data['result']['tools'] ) );
	if ( '' === $fingerprint ) {
		$fingerprint = $current_fingerprint;
	} else {
		cmsa_persistence_assert( hash_equals( $fingerprint, $current_fingerprint ), 'Modern tool descriptor fingerprint changed across reconnects.' );
	}
}

$discover = cmsa_persistence_modern( 'server/discover', array(), 550 );
$discover_data = $discover->get_data();
cmsa_persistence_assert( 200 === $discover->get_status(), 'Modern server/discover failed.' );
cmsa_persistence_assert( 0 === ( $discover_data['result']['ttlMs'] ?? null ), 'Modern server/discover is not immediately stale.' );
cmsa_persistence_assert( 'private' === ( $discover_data['result']['cacheScope'] ?? null ), 'Modern server/discover cacheScope is not private.' );

echo "cmsa-mcp-persistence: PASS modern_reconnects=20 modern_ttl=0 legacy_store=durable legacy_cache_flush=survived phantom_session=blocked fingerprint=stable\n";
exit( 0 );
