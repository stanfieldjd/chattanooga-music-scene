<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_manual_token_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_manual_token_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_manual_token_fail( $message );
	}
}

function cmsa_manual_token_mcp_request( $token = null, $method = 'server/discover', $id = 601 ) {
	wp_set_current_user( 0 );
	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', $method );
	if ( null !== $token ) {
		$request->set_query_params( array( CUA_Manual_Token::QUERY_ARG => $token ) );
	}
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => $method,
				'params'  => array(
					'_meta' => array(
						'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
						'io.modelcontextprotocol/clientInfo'      => array(
							'name'    => 'cmsa-manual-token-probe',
							'version' => '1.0.0',
						),
					),
				),
			)
		)
	);
	return rest_do_request( $request );
}

function cmsa_manual_token_header( WP_HTTP_Response $response, $name ) {
	$headers = $response->get_headers();
	foreach ( $headers as $key => $value ) {
		if ( strtolower( (string) $key ) === strtolower( (string) $name ) ) {
			return (string) $value;
		}
	}
	return '';
}

wp_set_current_user( 1 );
cmsa_manual_token_assert( current_user_can( 'manage_options' ), 'Administrator fixture is unavailable.' );
cmsa_manual_token_assert( class_exists( 'CUA_Manual_Token' ), 'Manual token class did not load.' );
cmsa_manual_token_assert( defined( 'CUA_VERSION' ) && '1.2.3' === CUA_VERSION, 'Chattanooga CMS Admin 1.2.3 did not load.' );

CUA_Manual_Token::revoke();
$token = CUA_Manual_Token::generate_for_user( 1 );
cmsa_manual_token_assert( is_string( $token ) && 43 === strlen( $token ), 'Manual token was not generated with the expected length.' );
cmsa_manual_token_assert( 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/', $token ), 'Manual token contains an unexpected character.' );
$record = get_option( CUA_Manual_Token::OPTION_NAME, array() );
cmsa_manual_token_assert( is_array( $record ) && ! empty( $record['digest'] ) && 1 === (int) ( $record['user_id'] ?? 0 ), 'Manual token record was not stored correctly.' );
cmsa_manual_token_assert( false === strpos( serialize( $record ), $token ), 'Clear-text manual token was stored persistently.' );
$url = CUA_Manual_Token::manual_endpoint_url( $token );
$base_url = rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE );
$url_parts = wp_parse_url( $url );
$base_parts = wp_parse_url( $base_url );
cmsa_manual_token_assert( is_array( $url_parts ) && is_array( $base_parts ), 'Manual MCP URL could not be parsed.' );
foreach ( array( 'scheme', 'host', 'port', 'path' ) as $part ) {
	cmsa_manual_token_assert(
		( $base_parts[ $part ] ?? null ) === ( $url_parts[ $part ] ?? null ),
		'Manual MCP URL does not target the canonical MCP endpoint.'
	);
}
$base_query = array();
$url_query  = array();
wp_parse_str( (string) ( $base_parts['query'] ?? '' ), $base_query );
wp_parse_str( (string) ( $url_parts['query'] ?? '' ), $url_query );
cmsa_manual_token_assert(
	isset( $url_query[ CUA_Manual_Token::QUERY_ARG ] ) && hash_equals( $token, (string) $url_query[ CUA_Manual_Token::QUERY_ARG ] ),
	'Manual MCP URL does not carry the generated token.'
);
unset( $url_query[ CUA_Manual_Token::QUERY_ARG ] );
cmsa_manual_token_assert( $base_query === $url_query, 'Manual MCP URL changed the canonical endpoint query parameters.' );

$valid = cmsa_manual_token_mcp_request( $token, 'server/discover', 601 );
cmsa_manual_token_assert( 200 === $valid->get_status(), 'Valid manual token did not authorize MCP discovery.' );
cmsa_manual_token_assert( 'no-store' === cmsa_manual_token_header( $valid, 'Cache-Control' ), 'Manual MCP response is not marked no-store.' );
cmsa_manual_token_assert( 'no-referrer' === cmsa_manual_token_header( $valid, 'Referrer-Policy' ), 'Manual MCP response does not suppress referrer transmission.' );

$list = cmsa_manual_token_mcp_request( $token, 'tools/list', 602 );
cmsa_manual_token_assert( 200 === $list->get_status(), 'Valid manual token did not authorize tools/list.' );
$tools = $list->get_data()['result']['tools'] ?? null;
cmsa_manual_token_assert( is_array( $tools ) && ! empty( $tools ), 'Manual tools/list returned no tools.' );
foreach ( $tools as $tool ) {
	cmsa_manual_token_assert( 'noauth' === ( $tool['securitySchemes'][0]['type'] ?? '' ), 'Manual tools/list still advertises an OAuth requirement.' );
}

$tampered = cmsa_manual_token_mcp_request( $token . 'x', 'server/discover', 603 );
cmsa_manual_token_assert( 401 === $tampered->get_status(), 'Tampered manual token was accepted.' );
cmsa_manual_token_assert( '' === cmsa_manual_token_header( $tampered, 'WWW-Authenticate' ), 'Invalid manual-token request triggered an OAuth challenge.' );
$tampered_data = $tampered->get_data();
cmsa_manual_token_assert( empty( $tampered_data['_meta']['mcp/www_authenticate'] ), 'Invalid manual-token request returned OAuth challenge metadata.' );

$rotated = CUA_Manual_Token::generate_for_user( 1 );
cmsa_manual_token_assert( is_string( $rotated ) && $rotated !== $token, 'Manual token rotation did not create a new credential.' );
cmsa_manual_token_assert( 401 === cmsa_manual_token_mcp_request( $token, 'server/discover', 604 )->get_status(), 'Rotated manual token remained valid.' );
cmsa_manual_token_assert( 200 === cmsa_manual_token_mcp_request( $rotated, 'server/discover', 605 )->get_status(), 'Rotated manual token did not authorize MCP.' );

CUA_Manual_Token::revoke();
cmsa_manual_token_assert( 401 === cmsa_manual_token_mcp_request( $rotated, 'server/discover', 606 )->get_status(), 'Revoked manual token remained valid.' );

$anonymous = cmsa_manual_token_mcp_request( null, 'server/discover', 607 );
cmsa_manual_token_assert( 401 === $anonymous->get_status(), 'Anonymous MCP access was accepted after manual-token support was added.' );
cmsa_manual_token_assert( false !== strpos( cmsa_manual_token_header( $anonymous, 'WWW-Authenticate' ), 'oauth-protected-resource' ), 'OAuth fallback challenge was lost for non-manual clients.' );

$subscriber_id = wp_create_user( 'cmsa-manual-token-subscriber', wp_generate_password( 24 ), 'cmsa-manual-token-subscriber@example.invalid' );
cmsa_manual_token_assert( ! is_wp_error( $subscriber_id ), 'Could not create non-administrator fixture.' );
$subscriber_token = CUA_Manual_Token::generate_for_user( $subscriber_id );
cmsa_manual_token_assert( is_wp_error( $subscriber_token ), 'Non-administrator was allowed to issue a manual MCP token.' );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $subscriber_id );

delete_option( CUA_Manual_Token::OPTION_NAME );
wp_set_current_user( 1 );
echo "cmsa-manual-token: PASS version=1.2.3 query_token=verified plaintext_storage=absent tools_security=noauth tamper=denied rotate=verified revoke=verified oauth_fallback=preserved admin_boundary=verified\n";
exit( 0 );
