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

function cmsa_persistence_modern_request( $method, array $params, $id ) {
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

function cmsa_persistence_tool_fingerprint( array $tools ) {
	$encoded = wp_json_encode( $tools, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return false === $encoded ? '' : hash( 'sha256', $encoded );
}

function cmsa_persistence_assert_cors() {
	$request = new WP_REST_Request( 'OPTIONS', '/chattanooga-cms-admin/v1/mcp' );
	$allowed = apply_filters(
		'rest_allowed_cors_headers',
		array( 'Authorization', 'X-WP-Nonce', 'Content-Disposition', 'Content-MD5', 'Content-Type' ),
		$request
	);
	$exposed = apply_filters(
		'rest_exposed_cors_headers',
		array( 'X-WP-Total', 'X-WP-TotalPages', 'Link' ),
		$request
	);
	$allowed_lower = array_map( 'strtolower', $allowed );
	$exposed_lower = array_map( 'strtolower', $exposed );
	foreach ( array( 'mcp-protocol-version', 'mcp-method', 'mcp-name', 'mcp-session-id', 'last-event-id' ) as $header ) {
		cmsa_persistence_assert( in_array( $header, $allowed_lower, true ), 'MCP CORS request header is missing: ' . $header );
	}
	foreach ( array( 'www-authenticate', 'mcp-protocol-version', 'mcp-session-id' ) as $header ) {
		cmsa_persistence_assert( in_array( $header, $exposed_lower, true ), 'MCP CORS response header is missing: ' . $header );
	}
}

wp_set_current_user( 1 );
cmsa_persistence_assert( class_exists( 'CUA_MCP_Server' ), 'MCP server class is unavailable.' );
cmsa_persistence_assert( class_exists( 'CUA_OAuth_Server' ), 'OAuth server class is unavailable.' );

$phase = getenv( 'CMSA_PERSISTENCE_PHASE' );
$phase = is_string( $phase ) ? trim( $phase ) : '';

$discover = cmsa_persistence_modern_request( 'server/discover', array(), 701 );
cmsa_persistence_assert( 200 === $discover->get_status(), 'Modern server/discover failed.' );
$discover_data = $discover->get_data();
cmsa_persistence_assert( 0 === ( $discover_data['result']['ttlMs'] ?? null ), 'Modern discovery must be immediately stale (ttlMs=0).' );
cmsa_persistence_assert( 'private' === ( $discover_data['result']['cacheScope'] ?? '' ), 'Modern discovery cache scope must remain private.' );

$list = cmsa_persistence_modern_request( 'tools/list', array(), 702 );
cmsa_persistence_assert( 200 === $list->get_status(), 'Modern tools/list failed.' );
$list_data = $list->get_data();
$tools = $list_data['result']['tools'] ?? null;
cmsa_persistence_assert( is_array( $tools ) && ! empty( $tools ), 'Modern tools/list returned no tools.' );
cmsa_persistence_assert( 0 === ( $list_data['result']['ttlMs'] ?? null ), 'Modern tools/list must be immediately stale (ttlMs=0).' );
cmsa_persistence_assert( 'private' === ( $list_data['result']['cacheScope'] ?? '' ), 'Modern tools/list cache scope must remain private.' );
$fingerprint = cmsa_persistence_tool_fingerprint( $tools );
cmsa_persistence_assert( preg_match( '/^[a-f0-9]{64}$/', $fingerprint ), 'Tool fingerprint is malformed.' );

cmsa_persistence_assert_cors();

$stable_client_id = 'https://chatgpt.com/oauth/client.json';
$stable_redirect  = 'https://chatgpt.com/connector_platform_oauth_redirect';

if ( 'seed' === $phase ) {
	update_option(
		'cmsa_persistence_probe_seed',
		array(
			'fingerprint' => $fingerprint,
			'tools'       => $tools,
			'seeded_at'   => time(),
		),
		false
	);

	$resolve = new ReflectionMethod( 'CUA_OAuth_Server', 'resolve_client' );
	$resolve->setAccessible( true );
	$valid_document = static function ( $preempt, $args, $url ) use ( $stable_client_id, $stable_redirect ) {
		if ( $stable_client_id !== $url ) {
			return $preempt;
		}
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'client_id'     => $stable_client_id,
					'client_name'   => 'ChatGPT',
					'redirect_uris' => array( $stable_redirect ),
				)
			),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	};
	add_filter( 'pre_http_request', $valid_document, 10, 3 );
	$resolved = $resolve->invoke( null, $stable_client_id, $stable_redirect );
	remove_filter( 'pre_http_request', $valid_document, 10 );
	cmsa_persistence_assert( is_array( $resolved ) && $stable_client_id === ( $resolved['client_id'] ?? '' ), 'Valid CIMD metadata did not resolve through the production resolver.' );

	$records = get_option( 'cua_oauth_cimd_clients', array() );
	cmsa_persistence_assert( is_array( $records ) && ! empty( $records ), 'Valid CIMD resolution did not persist the client.' );

	echo 'cmsa-persistence-seed: PASS fingerprint=' . $fingerprint . " tools=" . count( $tools ) . "\n";
	exit( 0 );
}

if ( 'reconnect' === $phase ) {
	$seed = get_option( 'cmsa_persistence_probe_seed', array() );
	cmsa_persistence_assert( is_array( $seed ) && ! empty( $seed['fingerprint'] ), 'Seed state did not survive the process boundary.' );
	cmsa_persistence_assert( hash_equals( (string) $seed['fingerprint'], $fingerprint ), 'Tool fingerprint changed across fresh WordPress/PHP processes.' );
	cmsa_persistence_assert( ( $seed['tools'] ?? null ) === $tools, 'Tool descriptors changed across fresh WordPress/PHP processes.' );

	$resolve = new ReflectionMethod( 'CUA_OAuth_Server', 'resolve_client' );
	$resolve->setAccessible( true );
	$fresh_network_calls = 0;
	$fresh_network_guard = static function ( $preempt, $args, $url ) use ( $stable_client_id, &$fresh_network_calls ) {
		if ( $stable_client_id === $url ) {
			++$fresh_network_calls;
			return new WP_Error( 'cmsa_probe_unexpected_network', 'Fresh CIMD cache unexpectedly reached the network.' );
		}
		return $preempt;
	};
	add_filter( 'pre_http_request', $fresh_network_guard, 10, 3 );
	$client = $resolve->invoke( null, $stable_client_id, $stable_redirect );
	remove_filter( 'pre_http_request', $fresh_network_guard, 10 );
	cmsa_persistence_assert( is_array( $client ), 'Validated CIMD client did not survive the process boundary.' );
	cmsa_persistence_assert( 0 === $fresh_network_calls, 'Fresh persisted CIMD resolution performed a network request.' );
	cmsa_persistence_assert( $stable_client_id === ( $client['client_id'] ?? '' ), 'Persisted CIMD client identity changed.' );
	cmsa_persistence_assert( in_array( $stable_redirect, $client['redirect_uris'] ?? array(), true ), 'Persisted CIMD redirect allowlist changed.' );

	$records = get_option( 'cua_oauth_cimd_clients', array() );
	$key = is_array( $records ) ? array_key_first( $records ) : null;
	cmsa_persistence_assert( is_string( $key ) && isset( $records[ $key ] ) && is_array( $records[ $key ] ), 'CIMD cache record is unavailable for stale-fallback simulation.' );
	$records[ $key ]['expires_at'] = time() - 1;
	$records[ $key ]['stale_until'] = time() + 300;
	update_option( 'cua_oauth_cimd_clients', $records, false );

	$network_failure = static function ( $preempt, $args, $url ) use ( $stable_client_id ) {
		if ( $stable_client_id === $url ) {
			return new WP_Error( 'cmsa_probe_network_down', 'Simulated CIMD transport outage.' );
		}
		return $preempt;
	};
	add_filter( 'pre_http_request', $network_failure, 10, 3 );
	$stale = $resolve->invoke( null, $stable_client_id, $stable_redirect );
	remove_filter( 'pre_http_request', $network_failure, 10 );
	cmsa_persistence_assert( is_array( $stale ) && $stable_client_id === ( $stale['client_id'] ?? '' ), 'Bounded CIMD stale-on-network-error fallback failed through resolve_client().' );

	$records = get_option( 'cua_oauth_cimd_clients', array() );
	$key = is_array( $records ) ? array_key_first( $records ) : null;
	cmsa_persistence_assert( is_string( $key ) && isset( $records[ $key ] ), 'CIMD cache vanished before invalid-document fail-closed simulation.' );
	$records[ $key ]['expires_at'] = time() - 1;
	$records[ $key ]['stale_until'] = time() + 300;
	update_option( 'cua_oauth_cimd_clients', $records, false );

	$invalid_document = static function ( $preempt, $args, $url ) use ( $stable_client_id, $stable_redirect ) {
		if ( $stable_client_id !== $url ) {
			return $preempt;
		}
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'client_id'     => 'https://invalid.example/oauth/client.json',
					'redirect_uris' => array( $stable_redirect ),
				)
			),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	};
	add_filter( 'pre_http_request', $invalid_document, 10, 3 );
	$invalid = $resolve->invoke( null, $stable_client_id, $stable_redirect );
	remove_filter( 'pre_http_request', $invalid_document, 10 );
	cmsa_persistence_assert( is_wp_error( $invalid ), 'Invalid 200 CIMD metadata incorrectly used the stale cache.' );
	cmsa_persistence_assert( 'cmsa_oauth_client_metadata_invalid' === $invalid->get_error_code(), 'Invalid CIMD metadata returned the wrong error.' );
	cmsa_persistence_assert( empty( get_option( 'cua_oauth_cimd_clients', array() ) ), 'Invalid 200 CIMD metadata did not purge the stale cache.' );

	delete_option( 'cmsa_persistence_probe_seed' );
	delete_option( 'cua_oauth_cimd_clients' );

	echo 'cmsa-persistence-reconnect: PASS fingerprint=' . $fingerprint . ' tools=' . count( $tools ) . " cimd=durable ttl=0 cors=mcp\n";
	exit( 0 );
}

cmsa_persistence_fail( 'CMSA_PERSISTENCE_PHASE must be seed or reconnect.' );
