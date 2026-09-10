<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Audit {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';
	const MAX_READ = 500;

	private static $pending = array();

	public static function bootstrap() {
		add_action( 'wp_ability_invoked', array( __CLASS__, 'on_invoked' ), PHP_INT_MIN, 3 );
		add_filter( 'wp_pre_execute_ability', array( __CLASS__, 'on_pre_execute' ), PHP_INT_MAX, 4 );
		add_filter( 'wp_ability_validate_input', array( __CLASS__, 'on_input_validation' ), PHP_INT_MAX, 3 );
		add_filter( 'wp_ability_permission_result', array( __CLASS__, 'on_permission_result' ), PHP_INT_MAX, 4 );
		add_filter( 'wp_ability_execute_result', array( __CLASS__, 'on_execute_result' ), PHP_INT_MAX, 4 );
		add_filter( 'wp_ability_validate_output', array( __CLASS__, 'on_output_validation' ), PHP_INT_MAX, 3 );
		add_action( 'wp_after_execute_ability', array( __CLASS__, 'on_success' ), PHP_INT_MAX, 4 );
	}

	public static function register_ability() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'get-audit-log',
			array(
				'label'               => __( 'Get administration audit log', 'chattanooga-cms-admin' ),
				'description'         => __( 'Returns recent local ability execution metadata without storing raw ability inputs, outputs, credentials, secrets, or member records.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_READ, 'default' => 100 ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'read' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);
	}

	public static function on_invoked( $ability_name, $input, $ability ) {
		if ( ! is_string( $ability_name ) || '' === $ability_name ) {
			return;
		}

		if ( ! isset( self::$pending[ $ability_name ] ) ) {
			self::$pending[ $ability_name ] = array();
		}

		self::$pending[ $ability_name ][] = array(
			'id'           => wp_generate_uuid4(),
			'time'         => gmdate( 'c' ),
			'started'      => microtime( true ),
			'user_id'      => get_current_user_id(),
			'ability'      => substr( sanitize_text_field( $ability_name ), 0, 191 ),
			'input_sha256' => self::input_hash( $input ),
		);
	}

	public static function on_pre_execute( $pre, $ability_name, $input, $ability ) {
		if ( class_exists( 'WP_Filter_Sentinel' ) && $pre instanceof WP_Filter_Sentinel ) {
			return $pre;
		}

		self::finish(
			$ability_name,
			is_wp_error( $pre ) ? 'failed' : 'short_circuited',
			is_wp_error( $pre ) ? (string) $pre->get_error_code() : ''
		);
		return $pre;
	}

	public static function on_input_validation( $validity, $input, $ability_name ) {
		if ( true !== $validity ) {
			self::finish(
				$ability_name,
				'invalid_input',
				is_wp_error( $validity ) ? (string) $validity->get_error_code() : 'ability_invalid_input'
			);
		}
		return $validity;
	}

	public static function on_permission_result( $permission, $ability_name, $input, $ability ) {
		if ( true !== $permission ) {
			self::finish(
				$ability_name,
				'denied',
				is_wp_error( $permission ) ? (string) $permission->get_error_code() : 'ability_invalid_permissions'
			);
		}
		return $permission;
	}

	public static function on_execute_result( $result, $ability_name, $input, $ability ) {
		if ( is_wp_error( $result ) ) {
			self::finish( $ability_name, 'failed', (string) $result->get_error_code() );
		}
		return $result;
	}

	public static function on_output_validation( $validity, $output, $ability_name ) {
		if ( true !== $validity ) {
			self::finish(
				$ability_name,
				'invalid_output',
				is_wp_error( $validity ) ? (string) $validity->get_error_code() : 'ability_invalid_output'
			);
		}
		return $validity;
	}

	public static function on_success( $ability_name, $input, $result, $ability ) {
		self::finish( $ability_name, 'success', '' );
	}

	public static function read( $input = array() ) {
		$limit = is_array( $input ) && isset( $input['limit'] ) ? (int) $input['limit'] : 100;
		$limit = max( 1, min( self::MAX_READ, $limit ) );
		$path = CUA_Local_Storage::path( 'audit.jsonl', 'audit' );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! is_file( $path ) ) {
			return array( 'entries' => array() );
		}

		$file = new SplFileObject( $path, 'rb' );
		$entries = array();
		while ( ! $file->eof() ) {
			$line = trim( (string) $file->fgets() );
			if ( '' === $line ) {
				continue;
			}
			$entry = json_decode( $line, true );
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$entries[] = $entry;
			if ( count( $entries ) > $limit ) {
				array_shift( $entries );
			}
		}

		return array( 'entries' => array_reverse( $entries ) );
	}

	private static function finish( $ability_name, $status, $error_code ) {
		$ability_name = (string) $ability_name;
		if ( empty( self::$pending[ $ability_name ] ) ) {
			return;
		}

		$entry = array_pop( self::$pending[ $ability_name ] );
		if ( empty( self::$pending[ $ability_name ] ) ) {
			unset( self::$pending[ $ability_name ] );
		}

		$entry['status'] = sanitize_key( (string) $status );
		$entry['error_code'] = '' === (string) $error_code ? '' : sanitize_key( (string) $error_code );
		$entry['duration_ms'] = max( 0, (int) round( ( microtime( true ) - (float) $entry['started'] ) * 1000 ) );
		unset( $entry['started'] );

		self::append( $entry );
	}

	private static function append( array $entry ) {
		$path = CUA_Local_Storage::path( 'audit.jsonl', 'audit' );
		if ( is_wp_error( $path ) ) {
			return false;
		}
		$encoded = wp_json_encode( $entry, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) ) {
			return false;
		}
		return false !== @file_put_contents( $path, $encoded . "\n", FILE_APPEND | LOCK_EX );
	}

	private static function input_hash( $input ) {
		$encoded = wp_json_encode( $input, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $encoded ) ) {
			$encoded = gettype( $input );
		}
		return hash( 'sha256', $encoded );
	}
}
