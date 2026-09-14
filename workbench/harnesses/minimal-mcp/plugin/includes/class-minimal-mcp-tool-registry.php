<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Minimal_MCP_Tool_Registry {
	private const TOOL_SITE_PROBE = 'probe.site';

	/** @var array<string,array<string,mixed>> */
	private static array $definitions = array();

	/** @var array<string,callable> */
	private static array $callbacks = array();

	private static bool $loaded = false;

	/**
	 * Register one MCP tool without modifying the transport or router.
	 *
	 * External WordPress components should register on the
	 * `minimal_mcp_register_tools` action.
	 *
	 * @param array<string,mixed> $definition MCP tool definition.
	 * @param callable            $callback Tool executor receiving one arguments array.
	 * @return true|WP_Error
	 */
	public static function register( array $definition, callable $callback ) {
		$name = isset( $definition['name'] ) ? trim( (string) $definition['name'] ) : '';
		if ( '' === $name || strlen( $name ) > 128 || ! preg_match( '/^[A-Za-z0-9_.-]+$/', $name ) ) {
			return new WP_Error( 'minimal_mcp_invalid_tool_name', 'MCP tool names must be 1-128 characters using only letters, digits, underscore, hyphen, or dot.' );
		}

		if ( isset( self::$definitions[ $name ] ) ) {
			return new WP_Error( 'minimal_mcp_duplicate_tool', 'An MCP tool with this name is already registered.' );
		}

		if ( ! isset( $definition['inputSchema'] ) || ! is_array( $definition['inputSchema'] ) ) {
			return new WP_Error( 'minimal_mcp_invalid_input_schema', 'Each MCP tool must provide an inputSchema object.' );
		}

		if ( isset( $definition['outputSchema'] ) && ! is_array( $definition['outputSchema'] ) ) {
			return new WP_Error( 'minimal_mcp_invalid_output_schema', 'outputSchema must be a JSON Schema object when provided.' );
		}

		if ( isset( $definition['annotations'] ) && ! is_array( $definition['annotations'] ) ) {
			return new WP_Error( 'minimal_mcp_invalid_annotations', 'Tool annotations must be an object when provided.' );
		}

		$definition['name'] = $name;
		self::$definitions[ $name ] = $definition;
		self::$callbacks[ $name ]   = $callback;
		return true;
	}

	/**
	 * Return the MCP-visible tool inventory.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		self::ensure_loaded();
		$definitions = self::$definitions;
		ksort( $definitions, SORT_STRING );
		return array_values( $definitions );
	}

	/**
	 * Execute a registered tool.
	 *
	 * @param string              $name Tool name.
	 * @param array<string,mixed> $arguments Tool arguments.
	 * @return array<string,mixed>
	 */
	public static function call( string $name, array $arguments ): array {
		self::ensure_loaded();
		if ( ! isset( self::$callbacks[ $name ] ) ) {
			return self::tool_error( 'Unknown tool.' );
		}

		try {
			$result = call_user_func( self::$callbacks[ $name ], $arguments );
		} catch ( Throwable $error ) {
			return self::tool_error( 'Tool execution failed.' );
		}

		if ( ! is_array( $result ) || ! isset( $result['content'] ) || ! is_array( $result['content'] ) ) {
			return self::tool_error( 'Tool returned an invalid MCP result.' );
		}

		return $result;
	}

	private static function ensure_loaded(): void {
		if ( self::$loaded ) {
			return;
		}

		self::$loaded = true;
		self::register(
			array(
				'name'        => self::TOOL_SITE_PROBE,
				'title'       => 'Probe WordPress Site',
				'description' => 'Read-only proof that the MCP tunnel reached the WordPress runtime.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'outputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'ok'               => array( 'type' => 'boolean' ),
						'siteTitle'        => array( 'type' => 'string' ),
						'homeUrl'          => array( 'type' => 'string' ),
						'wordpressVersion' => array( 'type' => 'string' ),
					),
					'required'   => array( 'ok', 'siteTitle', 'homeUrl', 'wordpressVersion' ),
				),
				'annotations' => array(
					'readOnlyHint'    => true,
					'destructiveHint' => false,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			),
			array( __CLASS__, 'site_probe' )
		);

		/**
		 * Register additional tools with the minimal MCP registry.
		 *
		 * The registry class name is passed instead of the registry internals so
		 * external components use the same validated registration path.
		 *
		 * @param class-string $registry_class
		 */
		do_action( 'minimal_mcp_register_tools', __CLASS__ );
	}

	/**
	 * @param array<string,mixed> $arguments Tool arguments.
	 * @return array<string,mixed>
	 */
	public static function site_probe( array $arguments ): array {
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
