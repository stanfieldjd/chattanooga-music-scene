<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Audit {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';
	const MAX_READ = 500;
	const MAX_OAUTH_TRACE_READ = 100;

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

	/**
	 * Record MCP boundary metadata without retaining request bodies, arguments,
	 * responses, credentials, or session identifiers.
	 *
	 * @param array $entry Sanitized MCP request metadata.
	 * @return bool Whether the entry was written.
	 */
	public static function log_mcp_request( array $entry ) {
		$allowed = array(
			'surface',
			'time',
			'user_id',
			'method',
			'tool',
			'auth_mode',
			'session_sha256',
			'request_id_sha256',
			'protocol_version',
			'outcome',
			'http_status',
			'error_code',
			'duration_ms',
		);
		$sanitized = array( 'surface' => 'mcp' );
		foreach ( $allowed as $key ) {
			if ( 'surface' === $key || ! array_key_exists( $key, $entry ) ) {
				continue;
			}
			$sanitized[ $key ] = $entry[ $key ];
		}

		$sanitized['time'] = isset( $sanitized['time'] ) ? sanitize_text_field( (string) $sanitized['time'] ) : gmdate( 'c' );
		$sanitized['user_id'] = isset( $sanitized['user_id'] ) ? (int) $sanitized['user_id'] : get_current_user_id();
		foreach ( array( 'method', 'tool', 'auth_mode', 'protocol_version', 'outcome', 'error_code' ) as $key ) {
			if ( isset( $sanitized[ $key ] ) ) {
				$sanitized[ $key ] = substr( sanitize_text_field( (string) $sanitized[ $key ] ), 0, 191 );
			}
		}
		foreach ( array( 'http_status', 'duration_ms' ) as $key ) {
			if ( isset( $sanitized[ $key ] ) ) {
				$sanitized[ $key ] = max( 0, (int) $sanitized[ $key ] );
			}
		}
		foreach ( array( 'session_sha256', 'request_id_sha256' ) as $key ) {
			if ( isset( $sanitized[ $key ] ) ) {
				$sanitized[ $key ] = preg_match( '/^[a-f0-9]{64}$/', (string) $sanitized[ $key ] ) ? (string) $sanitized[ $key ] : '';
			}
		}

		return self::append( $sanitized );
	}

	/**
	 * Record a bounded, secret-free OAuth/MCP authorization handshake event.
	 *
	 * Callers may only supply the fixed metadata fields below. Tokens, codes,
	 * PKCE values, state, request bodies, redirect URIs, and credentials are
	 * never retained.
	 *
	 * @param array $entry Sanitized authorization trace metadata.
	 * @return bool Whether the entry was written.
	 */
	public static function log_oauth_trace( array $entry ) {
		$allowed = array(
			'time',
			'stage',
			'outcome',
			'http_status',
			'error_code',
			'method',
			'path',
			'grant_type',
			'client_mode',
			'client_host',
			'redirect_scheme',
			'auth_mode',
			'protocol_version',
			'mcp_method',
			'refresh_issued',
		);
		$sanitized = array( 'surface' => 'oauth_trace' );
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $entry ) ) {
				$sanitized[ $key ] = $entry[ $key ];
			}
		}

		$sanitized['time'] = isset( $sanitized['time'] ) ? sanitize_text_field( (string) $sanitized['time'] ) : gmdate( 'c' );
		foreach ( array( 'stage', 'outcome', 'error_code', 'method', 'path', 'grant_type', 'client_mode', 'client_host', 'redirect_scheme', 'auth_mode', 'protocol_version', 'mcp_method' ) as $key ) {
			if ( isset( $sanitized[ $key ] ) ) {
				$sanitized[ $key ] = substr( sanitize_text_field( (string) $sanitized[ $key ] ), 0, 191 );
			}
		}
		if ( isset( $sanitized['http_status'] ) ) {
			$sanitized['http_status'] = max( 0, (int) $sanitized['http_status'] );
		}
		if ( isset( $sanitized['refresh_issued'] ) ) {
			$sanitized['refresh_issued'] = (bool) $sanitized['refresh_issued'];
		}

		$written = self::append( $sanitized );
		if ( $written ) {
			self::trim_oauth_trace_storage( self::MAX_OAUTH_TRACE_READ );
		}
		return $written;
	}

	public static function read_oauth_trace( $limit = 50 ) {
		$limit = max( 1, min( self::MAX_OAUTH_TRACE_READ, (int) $limit ) );
		$all = self::read( array( 'limit' => self::MAX_READ ) );
		if ( is_wp_error( $all ) ) {
			return $all;
		}
		$entries = array();
		foreach ( $all['entries'] ?? array() as $entry ) {
			if ( ! is_array( $entry ) || 'oauth_trace' !== ( $entry['surface'] ?? '' ) ) {
				continue;
			}
			$entries[] = $entry;
			if ( count( $entries ) >= $limit ) {
				break;
			}
		}
		return array( 'entries' => $entries );
	}

	public static function clear_oauth_trace() {
		$path = CUA_Local_Storage::path( 'audit.jsonl', 'audit' );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! is_file( $path ) ) {
			return true;
		}
		$lines = @file( $path, FILE_IGNORE_NEW_LINES );
		if ( false === $lines ) {
			return new WP_Error( 'cmsa_oauth_trace_read_failed', 'The authorization trace could not be read.' );
		}
		$kept = array();
		foreach ( $lines as $line ) {
			$entry = json_decode( trim( (string) $line ), true );
			if ( is_array( $entry ) && 'oauth_trace' === ( $entry['surface'] ?? '' ) ) {
				continue;
			}
			if ( '' !== trim( (string) $line ) ) {
				$kept[] = (string) $line;
			}
		}
		$encoded = empty( $kept ) ? '' : implode( "\n", $kept ) . "\n";
		return false !== @file_put_contents( $path, $encoded, LOCK_EX )
			? true
			: new WP_Error( 'cmsa_oauth_trace_write_failed', 'The authorization trace could not be cleared.' );
	}

	private static function trim_oauth_trace_storage( $limit ) {
		$limit = max( 1, (int) $limit );
		$path = CUA_Local_Storage::path( 'audit.jsonl', 'audit' );
		if ( is_wp_error( $path ) || ! is_file( $path ) ) {
			return;
		}
		$lines = @file( $path, FILE_IGNORE_NEW_LINES );
		if ( false === $lines ) {
			return;
		}
		$oauth_seen = 0;
		$kept_reversed = array();
		for ( $index = count( $lines ) - 1; $index >= 0; --$index ) {
			$line = trim( (string) $lines[ $index ] );
			if ( '' === $line ) {
				continue;
			}
			$entry = json_decode( $line, true );
			if ( is_array( $entry ) && 'oauth_trace' === ( $entry['surface'] ?? '' ) ) {
				++$oauth_seen;
				if ( $oauth_seen > $limit ) {
					continue;
				}
			}
			$kept_reversed[] = $line;
		}
		$kept = array_reverse( $kept_reversed );
		$encoded = empty( $kept ) ? '' : implode( "\n", $kept ) . "\n";
		@file_put_contents( $path, $encoded, LOCK_EX );
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
