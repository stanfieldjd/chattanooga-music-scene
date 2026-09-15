<?php
/**
 * Plugin Name: Robust MCP Schema Fixture
 * Description: Disposable CI fixture for JSON Schema 2020-12 input/output enforcement.
 * Version: 0.1.0
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
				'name'        => 'fixture.schema',
				'title'       => 'Schema 2020-12 Fixture',
				'description' => 'Exercises local $ref, $defs, oneOf, and conditional validation.',
				'inputSchema' => array(
					'$schema' => 'https://json-schema.org/draft/2020-12/schema',
					'$defs'   => array(
						'payload' => array(
							'type'       => 'object',
							'properties' => array(
								'mode'  => array( 'enum' => array( 'text', 'count' ) ),
								'value' => array(
									'oneOf' => array(
										array( 'type' => 'string' ),
										array( 'type' => 'integer' ),
									),
								),
							),
							'required'             => array( 'mode', 'value' ),
							'additionalProperties' => false,
							'allOf'                => array(
								array(
									'if'   => array( 'properties' => array( 'mode' => array( 'const' => 'text' ) ) ),
									'then' => array( 'properties' => array( 'value' => array( 'type' => 'string', 'minLength' => 2 ) ) ),
									'else' => array( 'properties' => array( 'value' => array( 'type' => 'integer', 'minimum' => 1 ) ) ),
								),
							),
						),
					),
					'type'       => 'object',
					'properties' => array(
						'payload' => array( '$ref' => '#/$defs/payload' ),
					),
					'required'             => array( 'payload' ),
					'additionalProperties' => false,
				),
				'outputSchema' => array(
					'$schema' => 'https://json-schema.org/draft/2020-12/schema',
					'type'       => 'object',
					'properties' => array(
						'mode'  => array( 'enum' => array( 'text', 'count' ) ),
						'value' => array(),
					),
					'required'             => array( 'mode', 'value' ),
					'additionalProperties' => false,
				),
				'annotations' => array(
					'readOnlyHint'    => true,
					'destructiveHint' => false,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			),
			static function ( array $arguments ): array {
				$result = array(
					'mode'  => $arguments['payload']['mode'],
					'value' => $arguments['payload']['value'],
				);
				return array(
					'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) ) ),
					'structuredContent' => $result,
					'isError'           => false,
				);
			}
		);

		$registry_class::register(
			array(
				'name'        => 'fixture.bad-output',
				'title'       => 'Bad Output Fixture',
				'description' => 'Deliberately violates outputSchema so the registry must contain the defect.',
				'inputSchema' => array(
					'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'outputSchema' => array(
					'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
					'type'       => 'object',
					'properties' => array( 'ok' => array( 'type' => 'boolean' ) ),
					'required'             => array( 'ok' ),
					'additionalProperties' => false,
				),
			),
			static function ( array $arguments ): array {
				unset( $arguments );
				return array(
					'content' => array( array( 'type' => 'text', 'text' => 'This callback deliberately returns the wrong output type.' ) ),
					'structuredContent' => array( 'ok' => 'not-a-boolean' ),
					'isError'           => false,
				);
			}
		);
	},
	10,
	1
);
