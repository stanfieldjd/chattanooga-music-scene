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
