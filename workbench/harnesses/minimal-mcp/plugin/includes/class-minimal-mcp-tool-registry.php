<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Minimal_MCP_Tool_Registry {
	private const TOOL_SITE_PROBE = 'probe.site';

	/**
	 * Return the MCP-visible tool inventory.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		return array(
			array(
				'name'        => self::TOOL_SITE_PROBE,
				'title'       => 'Probe WordPress Site',
				'description' => 'Read-only proof that the MCP tunnel reached the WordPress runtime.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'annotations' => array(
					'readOnlyHint'    => true,
					'destructiveHint' => false,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			),
		);
	}

	/**
	 * Execute a registered tool.
	 *
	 * @param string              $name Tool name.
	 * @param array<string,mixed> $arguments Tool arguments.
	 * @return array<string,mixed>
	 */
	public static function call( string $name, array $arguments ): array {
		if ( self::TOOL_SITE_PROBE !== $name ) {
			return self::tool_error( 'Unknown tool.' );
		}

		if ( ! empty( $arguments ) ) {
			return self::tool_error( 'probe.site accepts an empty JSON object.' );
		}

		global $wp_version;
		$result = array(
			'ok'               => true,
			'siteTitle'        => get_bloginfo( 'name' ),
			'homeUrl'          => home_url( '/' ),
			'wordpressVersion' => (string) $wp_version,
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

	/**
	 * @return array<string,mixed>
	 */
	private static function tool_error( string $message ): array {
		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
			'isError' => true,
		);
	}
}
