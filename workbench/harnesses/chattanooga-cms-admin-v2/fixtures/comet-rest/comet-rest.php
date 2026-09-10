<?php
/**
 * Plugin Name: Comet REST Fixture
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'comet-fixture/v1',
			'/marker/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => static function ( WP_REST_Request $request ) {
					return rest_ensure_response(
						array(
							'id'     => (int) $request['id'],
							'marker' => 'comet',
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
					),
				),
			)
		);
	}
);