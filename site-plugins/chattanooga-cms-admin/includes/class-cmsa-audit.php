<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Audit {
	public static function record( $ability, $target, $status, array $context = array() ) {
		$directory = CMSA_Backups::get_storage_directory();
		if ( is_wp_error( $directory ) ) {
			return;
		}

		$entry = array(
			'time'    => gmdate( 'c' ),
			'user_id' => get_current_user_id(),
			'ability' => sanitize_key( str_replace( '/', '-', (string) $ability ) ),
			'target'  => sanitize_text_field( (string) $target ),
			'status'  => sanitize_key( (string) $status ),
			'context' => self::sanitize_context( $context ),
		);

		$line = wp_json_encode( $entry, JSON_UNESCAPED_SLASHES ) . "\n";
		@file_put_contents( trailingslashit( $directory ) . 'audit.jsonl', $line, FILE_APPEND | LOCK_EX );
	}

	public static function read( $limit = 100 ) {
		$limit = max( 1, min( 500, (int) $limit ) );
		$directory = CMSA_Backups::get_storage_directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$path = trailingslashit( $directory ) . 'audit.jsonl';
		if ( ! is_file( $path ) ) {
			return array( 'entries' => array() );
		}

		$lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( false === $lines ) {
			return new WP_Error( 'cmsa_audit_read', 'Could not read the local CMS Admin audit log.' );
		}

		$entries = array();
		foreach ( array_slice( $lines, -$limit ) as $line ) {
			$entry = json_decode( $line, true );
			if ( is_array( $entry ) ) {
				$entries[] = $entry;
			}
		}

		return array( 'entries' => array_reverse( $entries ) );
	}

	private static function sanitize_context( array $context ) {
		$allowed = array();
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( is_scalar( $value ) || null === $value ) {
				$allowed[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		return $allowed;
	}
}
