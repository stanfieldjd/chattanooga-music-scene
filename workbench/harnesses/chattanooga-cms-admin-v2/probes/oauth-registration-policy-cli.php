<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_oauth_registration_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_oauth_registration_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_oauth_registration_fail( $message );
	}
}

function cmsa_oauth_registration_request( array $payload ) {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/oauth/register' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( $payload ) );
	return CUA_MCP_OAuth::register_client( $request );
}

function cmsa_oauth_registration_expect_error( array $payload, $expected_error, $message ) {
	$response = cmsa_oauth_registration_request( $payload );
	cmsa_oauth_registration_assert( $response instanceof WP_REST_Response, $message . ': response type.' );
	cmsa_oauth_registration_assert( 400 === $response->get_status(), $message . ': expected HTTP 400.' );
	$data = $response->get_data();
	cmsa_oauth_registration_assert( $expected_error === ( $data['error'] ?? '' ), $message . ': wrong OAuth error.' );
}

cmsa_oauth_registration_assert( class_exists( 'CUA_MCP_OAuth' ), 'Native OAuth class did not load.' );

$created_clients = array();
try {
	$default = cmsa_oauth_registration_request(
		array(
			'client_name'   => 'CMSA Registration Default Probe',
			'redirect_uris' => array( 'https://client.example/callback' ),
		)
	);
	cmsa_oauth_registration_assert( $default instanceof WP_REST_Response, 'Default registration did not return a REST response.' );
	cmsa_oauth_registration_assert( 201 === $default->get_status(), 'Default registration did not return HTTP 201.' );
	$default_data = $default->get_data();
	cmsa_oauth_registration_assert( ! empty( $default_data['client_id'] ), 'Default registration returned no client_id.' );
	$created_clients[] = (string) $default_data['client_id'];
	cmsa_oauth_registration_assert( array( 'authorization_code' ) === ( $default_data['grant_types'] ?? null ), 'Default grant_types did not remain authorization_code only.' );
	cmsa_oauth_registration_assert( array( 'code' ) === ( $default_data['response_types'] ?? null ), 'Default response_types did not remain code only.' );
	cmsa_oauth_registration_assert( 'none' === ( $default_data['token_endpoint_auth_method'] ?? '' ), 'Default token endpoint auth method was not none.' );
	cmsa_oauth_registration_assert( 'web' === ( $default_data['application_type'] ?? '' ), 'Default application_type was not web.' );

	$native = cmsa_oauth_registration_request(
		array(
			'client_name'                => 'CMSA Registration Native Probe',
			'redirect_uris'              => array( 'http://127.0.0.1:45678/callback' ),
			'grant_types'                => array( 'authorization_code', 'refresh_token' ),
			'response_types'             => array( 'code' ),
			'token_endpoint_auth_method' => 'none',
			'application_type'           => 'native',
		)
	);
	cmsa_oauth_registration_assert( $native instanceof WP_REST_Response, 'Native registration did not return a REST response.' );
	cmsa_oauth_registration_assert( 201 === $native->get_status(), 'Native registration did not return HTTP 201.' );
	$native_data = $native->get_data();
	cmsa_oauth_registration_assert( ! empty( $native_data['client_id'] ), 'Native registration returned no client_id.' );
	$created_clients[] = (string) $native_data['client_id'];
	cmsa_oauth_registration_assert( array( 'authorization_code', 'refresh_token' ) === ( $native_data['grant_types'] ?? null ), 'Explicit refresh-token grant was not preserved.' );
	cmsa_oauth_registration_assert( 'native' === ( $native_data['application_type'] ?? '' ), 'Native application_type was not preserved.' );

	cmsa_oauth_registration_expect_error(
		array(
			'redirect_uris' => array( 'https://client.example/callback' ),
			'grant_types'   => 'authorization_code',
		),
		'invalid_client_metadata',
		'Non-array grant_types was accepted'
	);
	cmsa_oauth_registration_expect_error(
		array(
			'redirect_uris'  => array( 'https://client.example/callback' ),
			'response_types' => 'code',
		),
		'invalid_client_metadata',
		'Non-array response_types was accepted'
	);
	cmsa_oauth_registration_expect_error(
		array(
			'redirect_uris'   => array( 'https://client.example/callback' ),
			'application_type' => 'desktop',
		),
		'invalid_client_metadata',
		'Invalid application_type was accepted'
	);
	cmsa_oauth_registration_expect_error(
		array(
			'redirect_uris' => array( array( 'https://client.example/callback' ) ),
		),
		'invalid_redirect_uri',
		'Non-string redirect URI was accepted'
	);
	cmsa_oauth_registration_expect_error(
		array(
			'redirect_uris'              => array( 'https://client.example/callback' ),
			'token_endpoint_auth_method' => 'client_secret_basic',
		),
		'invalid_client_metadata',
		'Confidential-client token authentication was accepted'
	);
	cmsa_oauth_registration_expect_error(
		array(
			'client_name'   => array( 'not-a-string' ),
			'redirect_uris' => array( 'https://client.example/callback' ),
		),
		'invalid_client_metadata',
		'Non-string client_name was accepted'
	);
} finally {
	foreach ( $created_clients as $client_id ) {
		delete_transient( CUA_MCP_OAuth::TRANSIENT_CLIENT . hash( 'sha256', $client_id ) );
	}
}

echo "cmsa-oauth-registration-policy: PASS defaults=authorization_code explicit_refresh=verified native_client=verified malformed_metadata=rejected cleanup=verified\n";
