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

	/** Object-valued maps whose entries are themselves JSON Schemas. */
	private const SCHEMA_MAP_KEYWORDS = array(
		'$defs',
		'definitions',
		'dependentSchemas',
		'patternProperties',
		'properties',
	);

	/** Object-valued maps whose entries are not JSON Schemas. */
	private const OBJECT_MAP_KEYWORDS = array(
		'$vocabulary',
		'dependentRequired',
	);

	/** Keywords whose value is one JSON Schema. */
	private const SINGLE_SCHEMA_KEYWORDS = array(
		'additionalProperties',
		'contains',
		'contentSchema',
		'else',
		'if',
		'items',
		'not',
		'propertyNames',
		'then',
		'unevaluatedItems',
		'unevaluatedProperties',
	);

	/** Keywords whose value is an array of JSON Schemas. */
	private const SCHEMA_ARRAY_KEYWORDS = array(
		'allOf',
		'anyOf',
		'oneOf',
		'prefixItems',
	);

	private static ?CompliantValidator $validator = null;

	/**
	 * Canonicalize PHP schema data so JSON object/schema positions keep their
	 * JSON type. In PHP, an empty array otherwise serializes as `[]`, even when
	 * JSON Schema requires `{}` (for example properties:{}, or a permissive {}).
	 *
	 * @param array<string,mixed> $schema
	 * @return array<mixed>|stdClass
	 */
	public static function canonicalize( array $schema ) {
		return self::canonicalize_schema( $schema );
	}

	/** @param array<string,mixed> $schema @return true|WP_Error */
	public static function validate_definition( array $schema, bool $input_schema = false ) {
		if ( ! class_exists( CompliantValidator::class ) ) {
			return new WP_Error( 'robust_mcp_schema_dependency_missing', 'The JSON Schema 2020-12 validator is unavailable.' );
		}

		$canonical = self::canonicalize( $schema );
		$encoded   = wp_json_encode( $canonical, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) ) {
			return new WP_Error( 'robust_mcp_schema_encode_failed', 'The JSON Schema could not be encoded.' );
		}
		if ( strlen( $encoded ) > self::MAX_SCHEMA_BYTES ) {
			return new WP_Error( 'robust_mcp_schema_too_large', 'The JSON Schema exceeds the configured size limit.' );
		}
		if ( self::depth( $canonical ) > self::MAX_SCHEMA_DEPTH ) {
			return new WP_Error( 'robust_mcp_schema_too_deep', 'The JSON Schema exceeds the configured nesting limit.' );
		}
		if ( $input_schema && ! self::root_allows_object( $canonical ) ) {
			return new WP_Error( 'robust_mcp_input_schema_root', 'MCP tool inputSchema must declare an object root.' );
		}

		$reference_error = self::reject_external_refs( $canonical );
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
			return new WP_Error(
				'robust_mcp_invalid_schema',
				'The JSON Schema is not valid Draft 2020-12 syntax.',
				array(
					'internal_exception_class'   => get_class( $error ),
					'internal_exception_message' => $error->getMessage(),
				)
			);
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

		$canonical     = self::canonicalize( $schema );
		$schema_object = json_decode( wp_json_encode( $canonical, JSON_UNESCAPED_SLASHES ) );
		try {
			$result = self::validator()->validate( $data, $schema_object );
		} catch ( Throwable $error ) {
			return new WP_Error(
				'robust_mcp_schema_validation_failed',
				'JSON Schema validation failed unexpectedly.',
				array(
					'internal_exception_class'   => get_class( $error ),
					'internal_exception_message' => $error->getMessage(),
				)
			);
		}

		if ( $result->isValid() ) {
			return true;
		}

		$error   = $result->error();
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

	/** @param mixed $schema */
	private static function root_allows_object( $schema ): bool {
		if ( is_object( $schema ) ) {
			$schema = get_object_vars( $schema );
		}
		if ( ! is_array( $schema ) ) {
			return false;
		}
		$type = $schema['type'] ?? null;
		if ( 'object' === $type ) {
			return true;
		}
		return is_array( $type ) && in_array( 'object', $type, true );
	}

	/**
	 * @param mixed $value A value occupying a JSON Schema position.
	 * @return mixed
	 */
	private static function canonicalize_schema( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( empty( $value ) ) {
			return new stdClass();
		}
		if ( self::is_list_array( $value ) ) {
			// A non-empty JSON array in a schema position is deliberately preserved;
			// the standards validator will reject it as an invalid schema shape.
			return self::canonicalize_generic_array( $value );
		}

		$output = array();
		foreach ( $value as $key => $child ) {
			if ( in_array( $key, self::SCHEMA_MAP_KEYWORDS, true ) ) {
				$output[ $key ] = self::canonicalize_schema_map( $child );
				continue;
			}
			if ( in_array( $key, self::OBJECT_MAP_KEYWORDS, true ) ) {
				$output[ $key ] = self::canonicalize_object_map( $child );
				continue;
			}
			if ( in_array( $key, self::SINGLE_SCHEMA_KEYWORDS, true ) ) {
				$output[ $key ] = self::canonicalize_schema( $child );
				continue;
			}
			if ( in_array( $key, self::SCHEMA_ARRAY_KEYWORDS, true ) ) {
				$output[ $key ] = self::canonicalize_schema_array( $child );
				continue;
			}
			$output[ $key ] = self::canonicalize_generic( $child );
		}
		return $output;
	}

	/** @param mixed $value @return mixed */
	private static function canonicalize_schema_map( $value ) {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( empty( $value ) ) {
			return new stdClass();
		}
		if ( self::is_list_array( $value ) ) {
			return self::canonicalize_generic_array( $value );
		}
		$output = array();
		foreach ( $value as $key => $schema ) {
			$output[ $key ] = self::canonicalize_schema( $schema );
		}
		return $output;
	}

	/** @param mixed $value @return mixed */
	private static function canonicalize_object_map( $value ) {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( empty( $value ) ) {
			return new stdClass();
		}
		if ( self::is_list_array( $value ) ) {
			return self::canonicalize_generic_array( $value );
		}
		$output = array();
		foreach ( $value as $key => $child ) {
			$output[ $key ] = self::canonicalize_generic( $child );
		}
		return $output;
	}

	/** @param mixed $value @return mixed */
	private static function canonicalize_schema_array( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$output = array();
		foreach ( $value as $schema ) {
			$output[] = self::canonicalize_schema( $schema );
		}
		return $output;
	}

	/** @param mixed $value @return mixed */
	private static function canonicalize_generic( $value ) {
		if ( is_object( $value ) ) {
			$output = new stdClass();
			foreach ( get_object_vars( $value ) as $key => $child ) {
				$output->{$key} = self::canonicalize_generic( $child );
			}
			return $output;
		}
		if ( is_array( $value ) ) {
			return self::canonicalize_generic_array( $value );
		}
		return $value;
	}

	/** @param array<mixed> $value @return array<mixed> */
	private static function canonicalize_generic_array( array $value ): array {
		$output = array();
		foreach ( $value as $key => $child ) {
			$output[ $key ] = self::canonicalize_generic( $child );
		}
		return $output;
	}

	/** @param mixed $value */
	private static function depth( $value, int $level = 1 ): int {
		if ( ! is_array( $value ) && ! is_object( $value ) ) {
			return $level;
		}
		$children = is_array( $value ) ? $value : get_object_vars( $value );
		$max      = $level;
		foreach ( $children as $child ) {
			$max = max( $max, self::depth( $child, $level + 1 ) );
		}
		return $max;
	}

	/** @param mixed $value @return true|WP_Error */
	private static function reject_external_refs( $value ) {
		if ( ! is_array( $value ) && ! is_object( $value ) ) {
			return true;
		}
		$children = is_array( $value ) ? $value : get_object_vars( $value );
		foreach ( $children as $key => $child ) {
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

	private static function is_list_array( array $value ): bool {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
