<?php
/**
 * Plugin Name: Minimal MCP Ability Fixture
 * Description: Disposable WordPress Abilities API fixture for the layered MCP bridge.
 * Version: 0.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_categories_init',
	static function (): void {
		wp_register_ability_category(
			'mcp-fixture',
			array(
				'label'       => 'MCP Fixture',
				'description' => 'Disposable abilities used only by MCP bridge CI.',
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	static function (): void {
		wp_register_ability(
			'mcp-fixture/read-site-title',
			array(
				'label'               => 'Read Site Title',
				'description'         => 'Returns the current WordPress site title.',
				'category'            => 'mcp-fixture',
				'output_schema'       => array( 'type' => 'string' ),
				'execute_callback'    => static fn(): string => get_bloginfo( 'name' ),
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
				'meta'                => array(
					'public'      => true,
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'mcp-fixture/write-option',
			array(
				'label'       => 'Write Disposable Option',
				'description' => 'Writes a disposable WordPress option so CI can prove MCP write execution through the Abilities API.',
				'category'    => 'mcp-fixture',
				'input_schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'value' => array( 'type' => 'string' ),
					),
					'required'             => array( 'value' ),
					'additionalProperties' => false,
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'saved' => array( 'type' => 'boolean' ),
						'value' => array( 'type' => 'string' ),
					),
					'required' => array( 'saved', 'value' ),
				),
				'execute_callback' => static function ( array $input ): array {
					update_option( 'minimal_mcp_bridge_ci', $input['value'], false );
					return array(
						'saved' => true,
						'value' => (string) get_option( 'minimal_mcp_bridge_ci', '' ),
					);
				},
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
				'meta' => array(
					'public'      => true,
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'mcp-fixture/private-ability',
			array(
				'label'               => 'Private Fixture Ability',
				'description'         => 'Must never be discoverable or executable through the layered MCP bridge.',
				'category'            => 'mcp-fixture',
				'output_schema'       => array( 'type' => 'string' ),
				'execute_callback'    => static fn(): string => 'private-value',
				'permission_callback' => '__return_true',
				'meta'                => array( 'public' => false ),
			)
		);

		wp_register_ability(
			'mcp-fixture/public-but-mcp-private',
			array(
				'label'               => 'MCP Opt-Out Fixture',
				'description'         => 'Public generally but explicitly excluded from MCP.',
				'category'            => 'mcp-fixture',
				'output_schema'       => array( 'type' => 'string' ),
				'execute_callback'    => static fn(): string => 'must-not-be-exposed',
				'permission_callback' => '__return_true',
				'meta'                => array(
					'public' => true,
					'mcp'    => array( 'public' => false ),
				),
			)
		);
	}
);
