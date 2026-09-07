<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public error boundary for external/upstream failures.
 *
 * Upstream diagnostic messages can contain paths, SQL fragments, URLs, headers,
 * credentials, or other environment data. CMS Admin returns bounded messages
 * and explicitly selected non-sensitive state instead of forwarding those
 * diagnostics through abilities.
 */
final class CMSA_Errors {
	public static function external( $code, $message ) {
		return new WP_Error( sanitize_key( (string) $code ), sanitize_text_field( (string) $message ) );
	}

	public static function rollback( $code, $message, $backup_id, $rollback ) {
		return new WP_Error(
			sanitize_key( (string) $code ),
			sanitize_text_field( (string) $message ),
			array(
				'backup_id'       => sanitize_text_field( (string) $backup_id ),
				'rolled_back'     => ! is_wp_error( $rollback ),
				'rollback_failed' => is_wp_error( $rollback ),
			)
		);
	}
}
