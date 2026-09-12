<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMS_Weekend_Abilities {
	const CATEGORY = 'chattanooga-music-scene';
	const GENERATE_ABILITY = 'chattanooga-music-scene/generate-weekend-feature';

	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Chattanooga Music Scene', 'chattanooga-music-scene-core' ),
				'description' => __( 'Declared site capabilities for Chattanooga Music Scene.', 'chattanooga-music-scene-core' ),
			)
		);
	}

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::GENERATE_ABILITY,
			array(
				'label'               => __( 'Generate Weekend Feature', 'chattanooga-music-scene-core' ),
				'description'         => __( 'Generates the current Chattanooga Music Scene Weekend Feature from published Events Manager events.', 'chattanooga-music-scene-core' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'status' => array(
							'type' => 'string',
							'enum' => array( 'draft', 'publish' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'event_count' => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string' ),
						'week_key' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'generate' ),
				'permission_callback' => static function () {
					return current_user_can( 'publish_posts' );
				},
				'meta'                => array(
					'public'      => true,
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);
	}

	public static function generate( $input = array() ) {
		$status = is_array( $input ) && 'publish' === ( $input['status'] ?? '' ) ? 'publish' : 'draft';
		return CMS_Weekend_Posts::instance()->generate( $status );
	}
}
