<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_oauth_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_oauth_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_oauth_fail( $message );
	}
}

function cmsa_oauth_private( $method, array $args = array() ) {
	$reflection = new ReflectionMethod( 'CUA_OAuth_Server', $method );
	$reflection->setAccessible( true );
	return $reflection->invokeArgs( null, $args );
}

function cmsa_oauth_token_request( array $params ) {
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/oauth/token' );
	$request->set_body_params( $params );
	return rest_do_request( $request );
}

function cmsa_oauth_mcp_request( $token ) {
	wp_set_current_user( 0 );
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'authorization', 'Bearer ' . $token );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', 'server/discover' );
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => 501,
				'method'  => 'server/discover',
				'params'  => array(
					'_meta' => array(
						'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
					),
				),
			)
		)
	);
	return rest_do_request( $request );
}

wp_set_current_user( 1 );
cmsa_oauth_assert( current_user_can( 'manage_options' ), 'Administrator fixture is unavailable.' );

// Dynamic registration rejects unsafe redirects and confidential-client authentication.
$unsafe = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/oauth/register' );
$unsafe->set_header( 'content-type', 'application/json' );
$unsafe->set_body( wp_json_encode( array( 'redirect_uris' => array( 'http://attacker.invalid/callback' ) ) ) );
cmsa_oauth_assert( 400 === rest_do_request( $unsafe )->get_status(), 'Insecure redirect URI was accepted.' );

$confidential = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/oauth/register' );
$confidential->set_header( 'content-type', 'application/json' );
$confidential->set_body( wp_json_encode( array( 'redirect_uris' => array( 'https://chatgpt.com/callback' ), 'token_endpoint_auth_method' => 'client_secret_basic' ) ) );
cmsa_oauth_assert( 400 === rest_do_request( $confidential )->get_status(), 'Unsupported client authentication was accepted.' );

// Authorization codes are bound to client, redirect URI, and PKCE S256, and are single-use.
$client_id = 'cmsa_probe_client';
$redirect_uri = 'https://chatgpt.com/connector/oauth/callback';
$verifier = str_repeat( 'v', 64 );
$challenge = cmsa_oauth_private( 'pkce_challenge', array( $verifier ) );
$code = 'valid-authorization-code';
$code_key = cmsa_oauth_private( 'transient_key', array( 'code', $code ) );
set_transient( $code_key, array( 'client_id' => $client_id, 'redirect_uri' => $redirect_uri, 'user_id' => 1, 'scope' => CUA_OAuth_Server::SCOPE, 'code_challenge' => $challenge ), 300 );

$exchange = cmsa_oauth_token_request( array( 'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $client_id, 'redirect_uri' => $redirect_uri, 'code_verifier' => $verifier ) );
$tokens = $exchange->get_data();
cmsa_oauth_assert( 200 === $exchange->get_status() && ! empty( $tokens['access_token'] ) && ! empty( $tokens['refresh_token'] ), 'Valid PKCE exchange did not issue tokens.' );
cmsa_oauth_assert( 400 === cmsa_oauth_token_request( array( 'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $client_id, 'redirect_uri' => $redirect_uri, 'code_verifier' => $verifier ) )->get_status(), 'Authorization code replay was accepted.' );

$wrong_code = 'wrong-pkce-code';
set_transient( cmsa_oauth_private( 'transient_key', array( 'code', $wrong_code ) ), array( 'client_id' => $client_id, 'redirect_uri' => $redirect_uri, 'user_id' => 1, 'scope' => CUA_OAuth_Server::SCOPE, 'code_challenge' => $challenge ), 300 );
cmsa_oauth_assert( 400 === cmsa_oauth_token_request( array( 'grant_type' => 'authorization_code', 'code' => $wrong_code, 'client_id' => $client_id, 'redirect_uri' => $redirect_uri, 'code_verifier' => str_repeat( 'x', 64 ) ) )->get_status(), 'Incorrect PKCE verifier was accepted.' );

// A valid bearer token authorizes MCP, while tampered and expired tokens do not.
cmsa_oauth_assert( 200 === cmsa_oauth_mcp_request( $tokens['access_token'] )->get_status(), 'Valid bearer token did not authorize MCP discovery.' );
cmsa_oauth_assert( 401 === cmsa_oauth_mcp_request( $tokens['access_token'] . 'tampered' )->get_status(), 'Tampered bearer token was accepted.' );
delete_transient( cmsa_oauth_private( 'transient_key', array( 'access', $tokens['access_token'] ) ) );
cmsa_oauth_assert( 401 === cmsa_oauth_mcp_request( $tokens['access_token'] )->get_status(), 'Expired bearer token was accepted.' );

// Refresh tokens rotate and cannot be replayed.
$refresh = cmsa_oauth_token_request( array( 'grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $client_id ) );
$refreshed = $refresh->get_data();
cmsa_oauth_assert( 200 === $refresh->get_status() && ! empty( $refreshed['access_token'] ), 'Valid refresh token did not rotate.' );
cmsa_oauth_assert( 400 === cmsa_oauth_token_request( array( 'grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $client_id ) )->get_status(), 'Refresh token replay was accepted.' );

// Tokens lose authority immediately if the authorizing user loses administrator capability.
$subscriber_id = wp_create_user( 'cmsa-oauth-subscriber', wp_generate_password( 24 ), 'cmsa-oauth-subscriber@example.invalid' );
cmsa_oauth_assert( ! is_wp_error( $subscriber_id ), 'Could not create non-administrator fixture.' );
$subscriber_tokens = cmsa_oauth_private( 'issue_tokens', array( array( 'client_id' => $client_id, 'user_id' => $subscriber_id, 'scope' => CUA_OAuth_Server::SCOPE ) ) )->get_data();
cmsa_oauth_assert( 403 === cmsa_oauth_mcp_request( $subscriber_tokens['access_token'] )->get_status(), 'Non-administrator bearer token was accepted.' );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $subscriber_id );

delete_option( CUA_OAuth_Server::CLIENT_OPTION );
echo "cmsa-oauth: PASS metadata=verified registration_boundary=verified pkce=verified code_replay=denied bearer=verified tamper=denied expiry=denied refresh_rotation=verified refresh_replay=denied privilege_revocation=verified\n";
exit( 0 );
