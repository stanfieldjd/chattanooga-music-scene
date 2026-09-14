<?php
/**
 * Plugin Name: Minimal MCP WordPress Ability Bridge
 * Description: Workbench-only layered bridge from the proven MCP registry to the WordPress Abilities API.
 * Version: 0.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Minimal_MCP_WordPress_Ability_Bridge {
	private const TOOL_DISCOVER = 'wordpress.discover-abilities';
	private const TOOL_INFO     = 'wordpress.get-ability-info';
	private const TOOL_EXECUTE  = 'wordpress.execute-ability';

	public static function bootstrap(): void {
		add_action( 'minimal_mcp_register_tools', array( __CLASS__, 'register_tools' ), 20, 1 );
	}

	public static function register_tools( string $registry_class ): void {
		if ( ! is_callable( array( $registry_class, 'register' ) ) || ! self::abilities_available() ) {
			return;
		}

		$registry_class::register(
			array(
				'name'        => self::TOOL_DISCOVER,
				'title'       => 'Discover WordPress Abilities',
				'description' => 'Lists WordPress abilities that are explicitly exposed to external clients. Use this before requesting a full ability schema.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'outputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'abilities' => array( 'type' => 'array' ),
					),
					'required' => array( 'abilities' ),
				),
				'annotations' => array(
					'readOnlyHint'    => true,
					'destructiveHint' => false,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			),
			array( __CLASS__, 'discover' )
		);

		$registry_class::register(
			array(
				'name'        => self::TOOL_INFO,
				'title'       => 'Inspect WordPress Ability',
				'description' => 'Returns the complete client-facing metadata and schemas for one public WordPress ability.',
				'inputSchema' => self::ability_name_input_schema(),
				'outputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'ability' => array( 'type' => 'object' ),
					),
					'required' => array( 'ability' ),
				),
				'annotations' => array(
					'readOnlyHint'    => true,
					'destructiveHint' => false,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			),
			array( __CLASS__, 'get_info' )
		);

		$registry_class::register(
			array(
				'name'        => self::TOOL_EXECUTE,
				'title'       => 'Execute WordPress Ability',
				'description' => 'Executes one public WordPress ability. WordPress validates the target input, checks the target ability permission callback, executes it, and validates its output.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'ability_name' => array(
							'type'         => 'string',
							'minLength'    => 3,
							'x-mcp-header' => 'Ability-Name',
						),
						'input' => array(
							'description' => 'Optional JSON input passed unchanged to the target WordPress ability. Omit for no-input abilities.',
						),
					),
					'required'             => array( 'ability_name' ),
					'additionalProperties' => false,
				),
				'outputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'ability_name' => array( 'type' => 'string' ),
						'result'       => array(),
					),
					'required' => array( 'ability_name', 'result' ),
				),
				'annotations' => array(
					'readOnlyHint'   => false,
					'openWorldHint'  => false,
				),
			),
			array( __CLASS__, 'execute' )
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function ability_name_input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'ability_name' => array(
					'type'         => 'string',
					'minLength'    => 3,
					'x-mcp-header' => 'Ability-Name',
				),
			),
			'required'             => array( 'ability_name' ),
			'additionalProperties' => false,
		);
	}

	/** @return array<string,mixed> */
	public static function discover( array $arguments ): array {
		if ( ! empty( $arguments ) ) {
			return self::tool_error( 'wordpress.discover-abilities accepts an empty JSON object.' );
		}

		$items = array();
		foreach ( self::public_abilities() as $ability ) {
			$items[] = array(
				'name'        => $ability->get_name(),
				'label'       => $ability->get_label(),
				'description' => $ability->get_description(),
				'category'    => $ability->get_category(),
			);
		}

		return self::tool_success( array( 'abilities' => $items ) );
	}

	/** @return array<string,mixed> */
	public static function get_info( array $arguments ): array {
		$ability = self::ability_from_arguments( $arguments );
		if ( is_wp_error( $ability ) ) {
			return self::tool_error( $ability->get_error_message(), $ability->get_error_code() );
		}

		return self::tool_success(
			array(
				'ability' => array(
					'name'          => $ability->get_name(),
					'label'         => $ability->get_label(),
					'description'   => $ability->get_description(),
					'category'      => $ability->get_category(),
					'input_schema'  => $ability->get_input_schema(),
					'output_schema' => $ability->get_output_schema(),
					'meta'          => $ability->get_meta(),
				)
		);
	}

	/** @return array<string,mixed> */
	public static function execute( array $arguments ): array {
		$ability = self::ability_from_arguments( $arguments );
		if ( is_wp_error( $ability ) ) {
			return self::tool_error( $ability->get_error_message(), $ability->get_error_code() );
		}

		try {
			$result = array_key_exists( 'input', $arguments )
				? $ability->execute( $arguments['input'] )
				: $ability->execute();
		} catch ( Throwable $error ) {
			return self::tool_error( 'The WordPress ability raised an exception.', 'ability_exception' );
		}

		if ( is_wp_error( $result ) ) {
			return self::tool_error( $result->get_error_message(), $result->get_error_code() );
		}

		return self::tool_success(
			array(
				'ability_name' => $ability->get_name(),
				'result'       => $result,
			)
		);
	}

	private static function abilities_available(): bool {
		return class_exists( 'WP_Ability' ) && function_exists( 'wp_get_abilities' ) && function_exists( 'wp_get_ability' );
	}

	/** @return array<int,WP_Ability> */
	private static function public_abilities(): array {
		$abilities = array();
		foreach ( wp_get_abilities() as $ability ) {
			if ( $ability instanceof WP_Ability && self::is_public( $ability ) ) {
				$abilities[] = $ability;
			}
		}
		usort( $abilities, static fn( WP_Ability $a, WP_Ability $b ): int => strcmp( $a->get_name(), $b->get_name() ) );
		return $abilities;
	}

	private static function is_public( WP_Ability $ability ): bool {
		$meta = $ability->get_meta();
		$public = false;
		if ( isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && array_key_exists( 'public', $meta['mcp'] ) ) {
			$public = true === $meta['mcp']['public'];
		} else {
			$public = true === ( $meta['public'] ?? false );
		}
		return (bool) apply_filters( 'minimal_mcp_ability_is_public', $public, $ability );
	}

	/** @return WP_Ability|WP_Error */
	private static function ability_from_arguments( array $arguments ) {
		$name = isset( $arguments['ability_name'] ) ? trim( (string) $arguments['ability_name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'ability_name_required', 'ability_name is required.' );
		}

		$ability = wp_get_ability( $name );
		if ( ! $ability instanceof WP_Ability ) {
			return new WP_Error( 'ability_not_found', 'The requested WordPress ability is not registered.' );
		}
		if ( ! self::is_public( $ability ) ) {
			return new WP_Error( 'ability_not_public', 'The requested WordPress ability is not exposed to MCP clients.' );
		}
		return $ability;
	}

	/** @return array<string,mixed> */
	private static function tool_success( array $data ): array {
		return array(
			'content' => array(
				array( 'type' => 'text', 'text' => wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ),
			),
			'structuredContent' => $data,
			'isError' => false,
		);
	}

	/** @return array<string,mixed> */
	private static function tool_error( string $message, string $code = 'ability_bridge_error' ): array {
		return array(
			'content' => array(
				array( 'type' => 'text', 'text' => $message ),
			),
			'isError' => true,
			'_meta' => array( 'wordpress/errorCode' => $code ),
		);
	}
}

CMSA_Minimal_MCP_WordPress_Ability_Bridge::bootstrap();
