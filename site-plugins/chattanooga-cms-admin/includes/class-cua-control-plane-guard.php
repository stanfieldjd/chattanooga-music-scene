<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Control_Plane_Guard {
	public static function validate_rest_request( WP_REST_Request $request, $method ) {
		$method = strtoupper( (string) $method );
		if ( in_array( $method, array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) {
			return true;
		}

		$identity = self::identity();
		$plugin = isset( $request['plugin'] ) ? self::normalize_plugin_file( $request['plugin'] ) : '';
		$slug = isset( $request['slug'] ) ? sanitize_key( (string) $request['slug'] ) : '';

		if ( '' !== $plugin && $identity['plugin_file'] === $plugin ) {
			return new WP_Error(
				'cmsa_control_plane_self_mutation_forbidden',
				'Chattanooga CMS Admin cannot deactivate, delete, replace, or otherwise mutate its own plugin through a discovered REST facade.'
			);
		}

		if ( '' !== $slug && $identity['slug'] === $slug ) {
			return new WP_Error(
				'cmsa_control_plane_self_mutation_forbidden',
				'Chattanooga CMS Admin cannot install or mutate a plugin package using its own plugin slug through a discovered REST facade.'
			);
		}

		return true;
	}

	public static function validate_ability_input( WP_Ability $ability, $input ) {
		$meta = $ability->get_meta();
		$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();
		if ( ! empty( $annotations['readonly'] ) || ! is_array( $input ) ) {
			return true;
		}

		$identity = self::identity();
		$candidates = self::identity_candidates( $input );
		foreach ( $candidates as $candidate ) {
			if ( $identity['plugin_file'] === self::normalize_plugin_file( $candidate ) || $identity['slug'] === sanitize_key( (string) $candidate ) ) {
				return new WP_Error(
					'cmsa_control_plane_self_mutation_forbidden',
					'Chattanooga CMS Admin cannot be targeted by a discovered mutating ability.'
				);
			}
		}

		return true;
	}

	private static function identity() {
		$plugin_file = plugin_basename( CUA_DIR . 'chattanooga-cms-admin.php' );
		$slug = dirname( $plugin_file );
		if ( '.' === $slug ) {
			$slug = basename( $plugin_file, '.php' );
		}

		return array(
			'plugin_file' => self::normalize_plugin_file( $plugin_file ),
			'slug'        => sanitize_key( $slug ),
		);
	}

	private static function normalize_plugin_file( $value ) {
		$value = rawurldecode( trim( (string) $value ) );
		$value = str_replace( '\\', '/', $value );
		$value = ltrim( $value, '/' );
		if ( '' !== $value && '.php' !== substr( $value, -4 ) && false !== strpos( $value, '/' ) ) {
			$value .= '.php';
		}
		return $value;
	}

	private static function identity_candidates( array $input ) {
		$candidates = array();
		foreach ( array( 'plugin', 'plugin_file', 'slug' ) as $key ) {
			if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) {
				$candidates[] = (string) $input[ $key ];
			}
		}
		return $candidates;
	}
}
