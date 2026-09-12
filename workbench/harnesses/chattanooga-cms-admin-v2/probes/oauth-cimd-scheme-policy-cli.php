<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_oauth_cimd_scheme_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_oauth_cimd_scheme_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_oauth_cimd_scheme_fail( $message );
	}
}

cmsa_oauth_cimd_scheme_assert( class_exists( 'CUA_MCP_OAuth' ), 'Native OAuth class did not load.' );

$client_id = 'HTTPS://client.example/mcp-client.json';
$fetches = 0;
$filter = static function ( $preempt, $args, $url ) use ( $client_id, &$fetches ) {
	if ( $url !== $client_id ) {
		return $preempt;
	}
	$fetches++;
	return array(
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'client_id'                  => $client_id,
				'client_name'                => 'CMSA CIMD Scheme Probe',
				'redirect_uris'              => array( 'https://client.example/callback' ),
				'grant_types'                => array( 'authorization_code' ),
				'response_types'             => array( 'code' ),
				'token_endpoint_auth_method' => 'none',
				'application_type'           => 'web',
			)
		),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $filter, 10, 3 );

try {
	$resolver = Closure::bind(
		static function ( $id ) {
			return CUA_MCP_OAuth::resolve_client( $id );
		},
		null,
		CUA_MCP_OAuth::class
	);
	cmsa_oauth_cimd_scheme_assert( $resolver instanceof Closure, 'Could not bind CIMD resolver probe.' );
	$result = $resolver( $client_id );
	cmsa_oauth_cimd_scheme_assert( ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : 'CIMD resolution failed.' );
	cmsa_oauth_cimd_scheme_assert( is_array( $result ), 'CIMD resolver did not return client metadata.' );
	cmsa_oauth_cimd_scheme_assert( 1 === $fetches, 'Uppercase HTTPS client_id did not use CIMD metadata retrieval exactly once.' );
	cmsa_oauth_cimd_scheme_assert( $client_id === ( $result['client_id'] ?? '' ), 'CIMD client_id was normalized instead of preserved exactly.' );
	cmsa_oauth_cimd_scheme_assert( 'CMSA CIMD Scheme Probe' === ( $result['client_name'] ?? '' ), 'CIMD client_name was not preserved.' );
	cmsa_oauth_cimd_scheme_assert( array( 'https://client.example/callback' ) === ( $result['redirect_uris'] ?? null ), 'CIMD redirect_uris were not validated.' );
} finally {
	remove_filter( 'pre_http_request', $filter, 10 );
}

echo "cmsa-oauth-cimd-scheme-policy: PASS https_scheme=case_insensitive client_id=exact metadata=validated fetch=mocked\n";
