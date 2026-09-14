<?php
/**
 * Plugin Name: Minimal MCP Dynamic Tool Fixture
 * Description: Disposable CI fixture proving external WordPress components can extend the minimal MCP registry.
 * Version: 0.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'minimal_mcp_register_tools',
	static function ( string $registry_class ): void {
		if ( ! is_callable( array( $registry_class, 'register' ) ) ) {
			return;
		}

		$registry_class::register(
			array(
				'name'        => 'fixture.echo',
				'title'       => 'Echo Fixture',
				'description' => 'Read-only external-plugin fixture proving MCP tools can register without changing the MCP core.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'text' => array( 'type' => 'string' ),
					),
					'required'             => array( 'text' ),
					'additionalProperties' => false,
				),
				'outputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'echo'   => array( 'type' => 'string' ),
						'source' => array( 'type' => 'string' ),
					),
					'required'   => array( 'echo', 'source' ),
				),
				'annotations' => array(
					'readOnlyHint'    => true,
					'destructiveHint' => false,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			),
			static function ( array $arguments ): array {
				if ( ! isset( $arguments['text'] ) || ! is_string( $arguments['text'] ) ) {
					return array(
						'content' => array(
							array( 'type' => 'text', 'text' => 'fixture.echo requires a string text argument.' ),
						),
						'isError' => true,
					);
				}

				$result = array(
					'echo'   => $arguments['text'],
					'source' => 'external-wordpress-plugin',
				);

				return array(
					'content' => array(
						array(
							'type' => 'text',
							'text' => wp_json_encode( $result, JSON_UNESCAPED_SLASHES ),
						),
					),
					'structuredContent' => $result,
					'isError'           => false,
				);
			}
		);
	},
	10,
	1
);
