<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OAuth token-endpoint request validation shared by the native OAuth server.
 */
final class CUA_OAuth_Token_Request {
	public static function parse( WP_REST_Request $request ) {
		$content_type = strtolower( trim( (string) $request->get_header( 'content-type' ) ) );
		$content_type_parts = explode( ';', $content_type, 2 );
		$content_type = trim( (string) $content_type_parts[0] );
		if ( 'application/x-www-form-urlencoded' !== $content_type ) {
			return new WP_Error( 'cmsa_oauth_token_content_type', 'OAuth token requests must use application/x-www-form-urlencoded.' );
		}

		$params = $request->get_body_params();
		return is_array( $params ) ? $params : array();
	}
}
