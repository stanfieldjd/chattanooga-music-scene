<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_oauth_token_policy_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_oauth_token_policy_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_oauth_token_policy_fail( $message );
	}
}

cmsa_oauth_token_policy_assert( class_exists( 'CUA_MCP_OAuth' ), 'Native OAuth class did not load.' );

$json_request = new WP_REST_Request( 'POST', '/' . CUA_MCP_Server::REST_NAMESPACE . '/oauth/token' );
$json_request->set_header( 'Content-Type', 'application/json' );
$json_request->set_body( wp_json_encode( array( 'grant_type' => 'refresh_token' ) ) );
$json_response = CUA_MCP_OAuth::token_endpoint( $json_request );
cmsa_oauth_token_policy_assert( $json_response instanceof WP_REST_Response, 'JSON token request did not return a REST response.' );
cmsa_oauth_token_policy_assert( 400 === $json_response->get_status(), 'JSON token request did not return HTTP 400.' );
cmsa_oauth_token_policy_assert( 'invalid_request' === ( $json_response->get_data()['error'] ?? '' ), 'JSON token request was not rejected as invalid_request.' );

$missing_request = new WP_REST_Request( 'POST', '/' . CUA_MCP_Server::REST_NAMESPACE . '/oauth/token' );
$missing_request->set_header( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
$missing_request->set_body_params( array() );
$missing_response = CUA_MCP_OAuth::token_endpoint( $missing_request );
cmsa_oauth_token_policy_assert( $missing_response instanceof WP_REST_Response, 'Missing-grant token request did not return a REST response.' );
cmsa_oauth_token_policy_assert( 400 === $missing_response->get_status(), 'Missing-grant token request did not return HTTP 400.' );
cmsa_oauth_token_policy_assert( 'invalid_request' === ( $missing_response->get_data()['error'] ?? '' ), 'Missing grant_type was not rejected as invalid_request.' );

$query_request = new WP_REST_Request( 'POST', '/' . CUA_MCP_Server::REST_NAMESPACE . '/oauth/token' );
$query_request->set_header( 'Content-Type', 'application/x-www-form-urlencoded' );
$query_request->set_query_params( array( 'grant_type' => 'authorization_code' ) );
$query_request->set_body_params( array() );
$query_response = CUA_MCP_OAuth::token_endpoint( $query_request );
cmsa_oauth_token_policy_assert( $query_response instanceof WP_REST_Response, 'Query-only token request did not return a REST response.' );
cmsa_oauth_token_policy_assert( 400 === $query_response->get_status(), 'Query-only token request did not return HTTP 400.' );
cmsa_oauth_token_policy_assert( 'invalid_request' === ( $query_response->get_data()['error'] ?? '' ), 'Query-string grant_type was accepted as token request content.' );

$unsupported_request = new WP_REST_Request( 'POST', '/' . CUA_MCP_Server::REST_NAMESPACE . '/oauth/token' );
$unsupported_request->set_header( 'Content-Type', 'Application/X-WWW-Form-Urlencoded; Charset=UTF-8' );
$unsupported_request->set_body_params( array( 'grant_type' => 'client_credentials' ) );
$unsupported_response = CUA_MCP_OAuth::token_endpoint( $unsupported_request );
cmsa_oauth_token_policy_assert( $unsupported_response instanceof WP_REST_Response, 'Unsupported-grant token request did not return a REST response.' );
cmsa_oauth_token_policy_assert( 400 === $unsupported_response->get_status(), 'Unsupported grant did not return HTTP 400.' );
cmsa_oauth_token_policy_assert( 'unsupported_grant_type' === ( $unsupported_response->get_data()['error'] ?? '' ), 'Valid form media type with unsupported grant did not preserve unsupported_grant_type semantics.' );

echo "cmsa-oauth-token-request-policy: PASS media_type=form_only params=body_only missing_grant=invalid_request unsupported_grant=preserved\n";
