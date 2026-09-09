<?php
/**
 * Plugin Name: CMSA Universal Fixture One
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function () {
		register_post_type(
			'fixture_one_record',
			array(
				'label'        => 'Fixture one records',
				'description'  => 'Disposable administratively exposed post type.',
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor' ),
			)
		);

		register_post_type(
			'fixture_one_hidden',
			array(
				'label'        => 'Fixture one hidden',
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
			)
		);
	}
);

add_filter(
	'rest_pre_insert_fixture_one_record',
	static function ( $prepared_post, $request ) {
		if ( ! $request instanceof WP_REST_Request || 'approved' !== (string) $request->get_param( 'fixture_contract' ) ) {
			return new WP_Error( 'fixture_one_rest_contract', 'Fixture one requires its REST mutation contract.' );
		}
		return $prepared_post;
	},
	10,
	2
);

add_action(
	'rest_after_insert_fixture_one_record',
	static function ( $post, $request, $creating ) {
		update_option(
			'cmsa_universal_fixture_one_rest_contract',
			array(
				'id'       => $post instanceof WP_Post ? (int) $post->ID : 0,
				'creating' => (bool) $creating,
				'contract' => $request instanceof WP_REST_Request ? (string) $request->get_param( 'fixture_contract' ) : '',
			),
			false
		);
	},
	10,
	3
);

add_action(
	'wp_abilities_api_categories_init',
	static function () {
		wp_register_ability_category(
			'fixture-one',
			array(
				'label'       => 'Fixture One',
				'description' => 'Disposable universal-control-plane fixture.',
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	static function () {
		wp_register_ability(
			'fixture-one/read',
			array(
				'label'               => 'Fixture one read',
				'description'         => 'Public disposable read ability.',
				'category'            => 'fixture-one',
				'execute_callback'    => static function () { return array( 'fixture' => 'one' ); },
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'fixture-one/private',
			array(
				'label'               => 'Fixture one private',
				'description'         => 'Private disposable ability that must not appear in the universal catalog.',
				'category'            => 'fixture-one',
				'execute_callback'    => static function () { return true; },
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => array(
					'public'       => false,
					'show_in_rest' => false,
				),
			)
		);
	}
);
