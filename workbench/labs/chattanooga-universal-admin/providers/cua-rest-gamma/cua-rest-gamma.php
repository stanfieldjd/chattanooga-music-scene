<?php
/**
 * Plugin Name: CUA REST Gamma
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'cua-rest-gamma/v1',
			'/records/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => static function ( WP_REST_Request $request ) {
					return rest_ensure_response(
						array(
							'id'     => (int) $request['id'],
							'source' => 'gamma-rest',
						)
					);
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
				),
			)
		);

		register_rest_route(
			'cua-rest-gamma/v1',
			'/flag',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( WP_REST_Request $request ) {
					update_option( 'cua_rest_gamma_flag', (bool) $request['value'], false );
					return rest_ensure_response( array( 'value' => (bool) get_option( 'cua_rest_gamma_flag', false ) ) );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'value' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'cua-rest-gamma/v1',
			'/denied',
			array(
				'methods'             => 'POST',
				'callback'            => static function () {
					update_option( 'cua_rest_gamma_denied_executed', true, false );
					return rest_ensure_response( array( 'executed' => true ) );
				},
				'permission_callback' => static function () {
					return false;
				},
			)
		);

		register_rest_route(
			'cua-rest-gamma/v1',
			'/private',
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					return rest_ensure_response( array( 'private' => true ) );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'show_in_index'       => false,
			)
		);
	}
);
