<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Minimal_MCP_Tool_Registry {
	private const TOOL_SITE_PROBE  = 'probe.site';
	private const MAX_SAFE_INTEGER = 9007199254740991;

	/** @var array<string,array<string,mixed>> */
	private static array $definitions = array();

	/** @var array<string,callable> */
	private static array $callbacks = array();

	/** @var array<string,array<int,array<string,mixed>>> */
	private static array $header_mirrors = array();

	/** @var array<int,WP_Error> */
	private static array $registration_errors = array();

	private static bool $loaded = false;

	/**
	 * Register one MCP tool without modifying the transport or router.
	 *
	 * External WordPress components should register on the
	 * `minimal_mcp_register_tools` action while this workbench hook name remains
	 * compatible with the proven checkpoint.
	 *
	 * @param array<string,mixed> $definition MCP tool definition.
	 * @param callable            $callback Tool executor receiving one arguments array.
	 * @return true|WP_Error
	 */
	public static function register( array $definition, callable $callback ) {
		$name = isset( $definition['name'] ) ? trim( (string) $definition['name'] ) : '';
		if ( '' === $name || strlen( $name ) > 128 || ! preg_match( '/^[A-Za-z0-9_.-]+$/', $name ) ) {
			return new WP_Error( 'robust_mcp_invalid_tool_name', 'MCP tool names must be 1-128 characters using only letters, digits, underscore, hyphen, or dot.' );
		}

		if ( isset( self::$definitions[ $name ] ) ) {
			return new WP_Error( 'robust_mcp_duplicate_tool', 'An MCP tool with this name is already registered.' );
		}

		if ( ! isset( $definition['inputSchema'] ) || ! is_array( $definition['inputSchema'] ) ) {
			return new WP_Error( 'robust_mcp_invalid_input_schema', 'Each MCP tool must provide an inputSchema object.' );
		}
		$input_schema_error = CMSA_Robust_MCP_Schema_Validator::validate_definition( $definition['inputSchema'], true );
		if ( is_wp_error( $input_schema_error ) ) {
			return $input_schema_error;
		}

		if ( isset( $definition['outputSchema'] ) ) {
			if ( ! is_array( $definition['outputSchema'] ) ) {
				return new WP_Error( 'robust_mcp_invalid_output_schema', 'outputSchema must be a JSON Schema object when provided.' );
			}
			$output_schema_error = CMSA_Robust_MCP_Schema_Validator::validate_definition( $definition['outputSchema'], false );
			if ( is_wp_error( $output_schema_error ) ) {
				return $output_schema_error;
			}
		}

		if ( isset( $definition['annotations'] ) && ! is_array( $definition['annotations'] ) ) {
			return new WP_Error( 'robust_mcp_invalid_annotations', 'Tool annotations must be an object when provided.' );
		}

		$mirror_scan = self::scan_header_mirrors( $definition['inputSchema'] );
		if ( is_wp_error( $mirror_scan ) ) {
			return $mirror_scan;
		}

		$definition['name']            = $name;
		self::$definitions[ $name ]    = $definition;
		self::$callbacks[ $name ]      = $callback;
		self::$header_mirrors[ $name ] = $mirror_scan;
		return true;
	}

	/** @return true|WP_Error */
	public static function health() {
		self::ensure_loaded();
		if ( empty( self::$registration_errors ) ) {
			return true;
		}
		return new WP_Error( 'robust_mcp_registry_unhealthy', 'One or more MCP tools failed registration.' );
	}

	/**
	 * Return the MCP-visible tool inventory in deterministic name order.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		self::ensure_loaded();
		$definitions = self::$definitions;
		ksort( $definitions, SORT_STRING );
		return array_values( $definitions );
	}

	/** @return array<string,mixed>|null */
	public static function get( string $name ): ?array {
		self::ensure_loaded();
		return self::$definitions[ $name ] ?? null;
	}

	/** @return array<int,array<string,mixed>> */
	public static function header_mirrors( string $name ): array {
		self::ensure_loaded();
		return self::$header_mirrors[ $name ] ?? array();
	}

	/** @param mixed $native_arguments @return true|WP_Error */
	public static function validate_input( string $name, $native_arguments ) {
		self::ensure_loaded();
		$definition = self::$definitions[ $name ] ?? null;
		if ( null === $definition ) {
			return new WP_Error( 'robust_mcp_unknown_tool', 'Unknown tool.' );
		}
		if ( ! is_object( $native_arguments ) ) {
			return new WP_Error( 'robust_mcp_arguments_not_object', 'MCP tool arguments must be a JSON object.' );
		}
		return CMSA_Robust_MCP_Schema_Validator::validate_value( $native_arguments, $definition['inputSchema'], 'input' );
	}

	/**
	 * Execute a registered tool after schema validation.
	 *
	 * @param string              $name Tool name.
	 * @param array<string,mixed> $arguments Tool arguments for PHP callbacks.
	 * @param mixed               $native_arguments Exact JSON-shaped arguments used for schema validation.
	 * @return array<string,mixed>
	 */
	public static function call( string $name, array $arguments, $native_arguments = null ): array {
		self::ensure_loaded();
		if ( ! isset( self::$callbacks[ $name ], self::$definitions[ $name ] ) ) {
			return self::tool_error( 'Unknown tool.' );
		}

		if ( null === $native_arguments ) {
			$native_arguments = json_decode( wp_json_encode( (object) $arguments, JSON_UNESCAPED_SLASHES ) );
		}
		$input_error = self::validate_input( $name, $native_arguments );
		if ( is_wp_error( $input_error ) ) {
			return self::tool_error( 'Tool input failed schema validation.' );
		}

		try {
			$result = call_user_func( self::$callbacks[ $name ], $arguments );
		} catch ( Throwable $error ) {
			return self::tool_error( 'Tool execution failed.' );
		}

		if ( ! is_array( $result ) || ! isset( $result['content'] ) || ! is_array( $result['content'] ) ) {
			return self::tool_error( 'Tool returned an invalid MCP result.' );
		}

		$definition = self::$definitions[ $name ];
		if ( isset( $definition['outputSchema'] ) ) {
			if ( ! array_key_exists( 'structuredContent', $result ) ) {
				return self::tool_error( 'Tool declares outputSchema but returned no structuredContent.' );
			}
			$native_output = json_decode( wp_json_encode( $result['structuredContent'], JSON_UNESCAPED_SLASHES ) );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return self::tool_error( 'Tool structuredContent could not be encoded for schema validation.' );
			}
			$output_error = CMSA_Robust_MCP_Schema_Validator::validate_value( $native_output, $definition['outputSchema'], 'output' );
			if ( is_wp_error( $output_error ) ) {
				return self::tool_error( 'Tool output failed schema validation.' );
			}
		}

		return $result;
	}

	private static function ensure_loaded(): void {
		if ( self::$loaded ) {
			return;
		}

		self::$loaded = true;
		$result = self::register(
			array(
				'name'        => self::TOOL_SITE_PROBE,
				'title'       => 'Probe WordPress Site',
				'description' => 'Read-only proof that the MCP server reached the WordPress runtime.',
				'inputSchema' => array(
					'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'outputSchema' => array(
					'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
					'type'       => 'object',
					'properties' => array(
						'ok'               => array( 'type' => 'boolean' ),
						'siteTitle'        => array( 'type' => 'string' ),
						'homeUrl'          => array( 'type' => 'string' ),
						'wordpressVersion' => array( 'type' => 'string' ),
					),
					'required'             => array( 'ok', 'siteTitle', 'homeUrl', 'wordpressVersion' ),
					'additionalProperties' => false,
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
		if ( is_wp_error( $result ) ) {
			self::$registration_errors[] = $result;
		}

		/** @param class-string $registry_class */
		do_action( 'minimal_mcp_register_tools', __CLASS__ );
	}

	/**
	 * Validate and index x-mcp-header annotations reachable through properties.
	 *
	 * @param array<string,mixed> $schema Input schema.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	private static function scan_header_mirrors( array $schema ) {
		if ( isset( $schema['x-mcp-header'] ) ) {
			return new WP_Error( 'robust_mcp_misplaced_header_annotation', 'x-mcp-header must be placed on a statically reachable input property.' );
		}

		$mirrors = array();
		$seen    = array();
		$error   = self::scan_properties( $schema, array(), $mirrors, $seen );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		return $mirrors;
	}

	/**
	 * @param array<string,mixed>            $schema Schema node.
	 * @param array<int,string>              $path Current property path.
	 * @param array<int,array<string,mixed>> $mirrors Output descriptors.
	 * @param array<string,bool>             $seen Case-insensitive suffix index.
	 * @return true|WP_Error
	 */
	private static function scan_properties( array $schema, array $path, array &$mirrors, array &$seen ) {
		$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();

		foreach ( $properties as $property_name => $property_schema ) {
			if ( ! is_string( $property_name ) || ! is_array( $property_schema ) ) {
				continue;
			}

			$property_path = array_merge( $path, array( $property_name ) );
			if ( array_key_exists( 'x-mcp-header', $property_schema ) ) {
				$suffix = $property_schema['x-mcp-header'];
				$type   = $property_schema['type'] ?? null;

				if ( ! is_string( $suffix ) || '' === $suffix || ! preg_match( '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $suffix ) ) {
					return new WP_Error( 'robust_mcp_invalid_header_annotation', 'x-mcp-header values must be non-empty valid HTTP field-name tokens.' );
				}
				if ( ! is_string( $type ) || ! in_array( $type, array( 'string', 'integer', 'boolean' ), true ) ) {
					return new WP_Error( 'robust_mcp_invalid_header_parameter_type', 'x-mcp-header may only annotate string, integer, or boolean properties.' );
				}

				$key = strtolower( $suffix );
				if ( isset( $seen[ $key ] ) ) {
					return new WP_Error( 'robust_mcp_duplicate_header_annotation', 'x-mcp-header values must be case-insensitively unique within a tool input schema.' );
				}
				$seen[ $key ] = true;
				$mirrors[]    = array(
					'suffix' => $suffix,
					'path'   => $property_path,
					'type'   => $type,
				);
			}

			$error = self::reject_misplaced_annotations( $property_schema );
			if ( is_wp_error( $error ) ) {
				return $error;
			}

			$error = self::scan_properties( $property_schema, $property_path, $mirrors, $seen );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
		}

		return true;
	}

	/** @param array<string,mixed> $schema @return true|WP_Error */
	private static function reject_misplaced_annotations( array $schema ) {
		foreach ( $schema as $key => $value ) {
			if ( in_array( $key, array( 'x-mcp-header', 'properties' ), true ) ) {
				continue;
			}
			if ( is_array( $value ) && self::contains_header_annotation( $value ) ) {
				return new WP_Error( 'robust_mcp_misplaced_header_annotation', 'x-mcp-header must be statically reachable through properties and cannot be hidden behind items, composition, conditionals, or references.' );
			}
		}
		return true;
	}

	private static function contains_header_annotation( array $value ): bool {
		if ( array_key_exists( 'x-mcp-header', $value ) ) {
			return true;
		}
		foreach ( $value as $child ) {
			if ( is_array( $child ) && self::contains_header_annotation( $child ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $arguments @return array<string,mixed> */
	public static function site_probe( array $arguments ): array {
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

	/** @return array<string,mixed> */
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
