<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ErrorFormatter;

final class CMSA_Robust_MCP_Schema_Validator {
	private const MAX_SCHEMA_BYTES = 131072;
	private const MAX_SCHEMA_DEPTH = 48;
	private const MAX_ERRORS       = 8;

	private static ?CompliantValidator $validator = null;

	/** @param array<string,mixed> $schema @return true|WP_Error */
	public static function validate_definition( array $schema, bool $input_schema = false ) {
		if ( ! class_exists( CompliantValidator::class ) ) {
			return new WP_Error( 'robust_mcp_schema_dependency_missing', 'The JSON Schema 2020-12 validator is unavailable.' );
		}

		$encoded = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) ) {
			return new WP_Error( 'robust_mcp_schema_encode_failed', 'The JSON Schema could not be encoded.' );
		}
		if ( strlen( $encoded ) > self::MAX_SCHEMA_BYTES ) {
			return new WP_Error( 'robust_mcp_schema_too_large', 'The JSON Schema exceeds the configured size limit.' );
		}
		if ( self::depth( $schema ) > self::MAX_SCHEMA_DEPTH ) {
			return new WP_Error( 'robust_mcp_schema_too_deep', 'The JSON Schema exceeds the configured nesting limit.' );
		}
		if ( $input_schema && ! self::root_allows_object( $schema ) ) {
			return new WP_Error( 'robust_mcp_input_schema_root', 'MCP tool inputSchema must declare an object root.' );
		}

		$reference_error = self::reject_external_refs( $schema );
		if ( is_wp_error( $reference_error ) ) {
			return $reference_error;
		}

		$schema_object = json_decode( $encoded );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_object( $schema_object ) ) {
			return new WP_Error( 'robust_mcp_schema_decode_failed', 'The JSON Schema could not be decoded as an object.' );
		}

		try {
			// Force Opis to parse the schema now so malformed keyword shapes fail at registration time.
			self::validator()->validate( new stdClass(), $schema_object );
		} catch ( Throwable $error ) {
			return new WP_Error( 'robust_mcp_invalid_schema', 'The JSON Schema is not valid Draft 2020-12 syntax.' );
		}

		return true;
	}

	/**
	 * @param mixed               $data Native JSON-shaped value (stdClass/list/scalar/null).
	 * @param array<string,mixed> $schema
	 * @return true|WP_Error
	 */
	public static function validate_value( $data, array $schema, string $context ) {
		$definition = self::validate_definition( $schema, 'input' === $context );
		if ( is_wp_error( $definition ) ) {
			return $definition;
		}

		$schema_object = json_decode( wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) );
		try {
			$result = self::validator()->validate( $data, $schema_object );
		} catch ( Throwable $error ) {
			return new WP_Error( 'robust_mcp_schema_validation_failed', 'JSON Schema validation failed unexpectedly.' );
		}

		if ( $result->isValid() ) {
			return true;
		}

		$error = $result->error();
		$details = $error ? ( new ErrorFormatter() )->formatOutput( $error, 'basic' ) : array( 'valid' => false );

		return new WP_Error(
			'robust_mcp_schema_mismatch',
			'MCP tool ' . $context . ' does not match its declared JSON Schema.',
			array( 'details' => $details )
		);
	}

	private static function validator(): CompliantValidator {
		if ( null === self::$validator ) {
			self::$validator = new CompliantValidator();
			self::$validator->setMaxErrors( self::MAX_ERRORS );
			self::$validator->setStopAtFirstError( false );
		}
		return self::$validator;
	}

	/** @param array<string,mixed> $schema */
	private static function root_allows_object( array $schema ): bool {
		$type = $schema['type'] ?? null;
		if ( 'object' === $type ) {
			return true;
		}
		return is_array( $type ) && in_array( 'object', $type, true );
	}

	/** @param mixed $value */
	private static function depth( $value, int $level = 1 ): int {
		if ( ! is_array( $value ) ) {
			return $level;
		}
		$max = $level;
		foreach ( $value as $child ) {
			$max = max( $max, self::depth( $child, $level + 1 ) );
		}
		return $max;
	}

	/** @param mixed $value @return true|WP_Error */
	private static function reject_external_refs( $value ) {
		if ( ! is_array( $value ) ) {
			return true;
		}
		foreach ( $value as $key => $child ) {
			if ( '$ref' === $key ) {
				if ( ! is_string( $child ) || '' === $child || '#' !== $child[0] ) {
					return new WP_Error( 'robust_mcp_external_ref_rejected', 'External JSON Schema $ref values are not permitted.' );
				}
				continue;
			}
			$error = self::reject_external_refs( $child );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
		}
		return true;
	}
}
