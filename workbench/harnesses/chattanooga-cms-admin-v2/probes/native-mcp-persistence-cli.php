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

function cmsa_persistence_legacy_initialize( $version, $id ) {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'accept', 'application/json, text/event-stream' );
	$request->set_header( 'MCP-Protocol-Version', $version );
	$request->set_header( 'Mcp-Method', 'initialize' );
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => 'initialize',
				'params'  => array(
					'protocolVersion' => $version,
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

function cmsa_persistence_legacy_list( $version, $session_id, $id ) {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'accept', 'application/json, text/event-stream' );
	$request->set_header( 'MCP-Protocol-Version', $version );
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

$legacy_versions = array( '2025-11-25', '2025-06-18' );
$legacy_sessions = array();
$id = 510;

foreach ( $legacy_versions as $legacy_version ) {
	delete_user_meta( get_current_user_id(), $session_meta_key );
	$initialize = cmsa_persistence_legacy_initialize( $legacy_version, $id++ );
	$initialize_headers = array_change_key_case( $initialize->get_headers(), CASE_LOWER );
	$session_id = trim( (string) ( $initialize_headers['mcp-session-id'] ?? '' ) );
	$initialize_data = $initialize->get_data();
	cmsa_persistence_assert( 200 === $initialize->get_status(), $legacy_version . ' durable initialize failed.' );
	cmsa_persistence_assert( '' !== $session_id, $legacy_version . ' durable initialize emitted no session id.' );
	cmsa_persistence_assert( $legacy_version === ( $initialize_data['result']['protocolVersion'] ?? '' ), $legacy_version . ' initialize was not echoed exactly.' );

	wp_cache_delete( get_current_user_id(), 'user_meta' );
	$sessions = get_user_meta( get_current_user_id(), $session_meta_key, true );
	cmsa_persistence_assert( is_array( $sessions ) && isset( $sessions[ $session_id ] ), $legacy_version . ' session was not durably persisted in user meta.' );
	cmsa_persistence_assert( $legacy_version === ( $sessions[ $session_id ]['protocolVersion'] ?? '' ), $legacy_version . ' persisted session has the wrong protocol version.' );

	wp_cache_flush();
	$legacy_after_flush = cmsa_persistence_legacy_list( $legacy_version, $session_id, $id++ );
	cmsa_persistence_assert( 200 === $legacy_after_flush->get_status(), $legacy_version . ' session did not survive an object-cache flush.' );
	cmsa_persistence_assert( ! empty( $legacy_after_flush->get_data()['result']['tools'] ?? array() ), $legacy_version . ' tools/list returned no tools after cache flush.' );
	$legacy_sessions[ $legacy_version ] = $session_id;
}

delete_user_meta( get_current_user_id(), $session_meta_key );
$block_session_writes = static function ( $check, $object_id, $meta_key ) use ( $session_meta_key ) {
	if ( (int) $object_id === get_current_user_id() && $session_meta_key === (string) $meta_key ) {
		return false;
	}
	return $check;
};
add_filter( 'update_user_metadata', $block_session_writes, 10, 5 );
$failed_initialize = cmsa_persistence_legacy_initialize( '2025-06-18', $id++ );
remove_filter( 'update_user_metadata', $block_session_writes, 10 );
$failed_headers = array_change_key_case( $failed_initialize->get_headers(), CASE_LOWER );
$failed_data = $failed_initialize->get_data();
cmsa_persistence_assert( 500 === $failed_initialize->get_status(), '2025-06-18 session-store write failure did not fail initialize honestly.' );
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
	cmsa_persistence_assert( false !== strpos( (string) ( $headers['cache-control'] ?? '' ), 'no-store' ), 'Modern reconnect response is cacheable.' );
	cmsa_persistence_assert( false !== strpos( (string) ( $headers['cache-control'] ?? '' ), 'max-age=0' ), 'Modern reconnect response lacks max-age=0.' );
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
$discover_headers = array_change_key_case( $discover->get_headers(), CASE_LOWER );
cmsa_persistence_assert( false !== strpos( (string) ( $discover_headers['cache-control'] ?? '' ), 'no-store' ), 'Modern server/discover HTTP response is cacheable.' );
cmsa_persistence_assert( ! array_key_exists( 'discovery', $discover_data['result'] ?? array() ), 'Modern server/discover embedded the dynamic catalog.' );
cmsa_persistence_assert( 200 === $discover->get_status(), 'Modern server/discover failed.' );
cmsa_persistence_assert( 0 === ( $discover_data['result']['ttlMs'] ?? null ), 'Modern server/discover is not immediately stale.' );
cmsa_persistence_assert( 'private' === ( $discover_data['result']['cacheScope'] ?? null ), 'Modern server/discover cacheScope is not private.' );

echo "cmsa-mcp-persistence: PASS modern_reconnects=20 modern_ttl=0 legacy_2025_11=durable legacy_2025_06=durable legacy_cache_flush=survived negotiation=exact phantom_session=blocked fingerprint=stable\n";
exit( 0 );
