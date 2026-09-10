<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Settings {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'get_registered_settings' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'list-registered-settings',
			array(
				'label'               => __( 'List registered WordPress settings', 'chattanooga-cms-admin' ),
				'description'         => __( 'Lists Settings API registrations the current user may administer. Non-REST values are not disclosed.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::empty_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'list_settings' ),
				'permission_callback' => static function () { return is_user_logged_in(); },
				'meta'                => self::meta( true, true ),
			)
		);

		wp_register_ability(
			self::PREFIX . 'get-registered-setting',
			array(
				'label'               => __( 'Inspect registered WordPress setting', 'chattanooga-cms-admin' ),
				'description'         => __( 'Returns registration metadata, existence and an opaque conflict token for one Settings API option. The stored value is returned only when the setting is already declared show_in_rest.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::name_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'get_setting' ),
				'permission_callback' => array( __CLASS__, 'can_administer_input_setting' ),
				'meta'                => self::meta( true, true ),
			)
		);

		wp_register_ability(
			self::PREFIX . 'update-registered-setting',
			array(
				'label'               => __( 'Update registered WordPress setting', 'chattanooga-cms-admin' ),
				'description'         => __( 'Updates one currently registered Settings API option after an opaque state-token conflict check, preserving the registered capability and sanitizer contract with readback verification and option-state rollback on failure.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'setting'              => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 191 ),
						'value'                => array(),
						'expected_state_token' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
					),
					'required'             => array( 'setting', 'value', 'expected_state_token' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'update_setting' ),
				'permission_callback' => array( __CLASS__, 'can_administer_input_setting' ),
				'meta'                => self::meta( false, false ),
			)
		);
	}

	public static function list_settings() {
		$items = array();
		foreach ( get_registered_settings() as $name => $registration ) {
			if ( ! is_array( $registration ) || ! self::can_administer( $name, $registration ) ) {
				continue;
			}
			$snapshot = self::snapshot( $name );
			if ( is_wp_error( $snapshot ) ) {
				continue;
			}
			$items[] = self::public_state( $name, $registration, $snapshot, false );
		}
		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( (string) $a['setting'], (string) $b['setting'] );
			}
		);
		return array( 'items' => $items, 'count' => count( $items ) );
	}

	public static function get_setting( $input ) {
		$resolved = self::resolve_input( $input );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$snapshot = self::snapshot( $resolved['name'] );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		return self::public_state( $resolved['name'], $resolved['registration'], $snapshot, true );
	}

	public static function update_setting( $input ) {
		$resolved = self::resolve_input( $input );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( ! is_array( $input ) || ! array_key_exists( 'value', $input ) || empty( $input['expected_state_token'] ) ) {
			return new WP_Error( 'cmsa_setting_input', 'A value and expected state token are required.' );
		}

		$name = $resolved['name'];
		$registration = $resolved['registration'];
		$before = self::snapshot( $name );
		if ( is_wp_error( $before ) ) {
			return $before;
		}
		$expected = strtolower( trim( (string) $input['expected_state_token'] ) );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $expected ) || ! hash_equals( $before['state_token'], $expected ) ) {
			return new WP_Error(
				'cmsa_setting_state_conflict',
				'The registered setting changed before the update could begin.',
				array( 'current_state_token' => $before['state_token'] )
			);
		}

		$captured = array( 'seen' => false, 'value' => null );
		$capture = static function ( $value, $option, $old_value ) use ( $name, &$captured ) {
			if ( (string) $option === $name ) {
				$captured['seen'] = true;
				$captured['value'] = $value;
			}
			return $value;
		};
		add_filter( 'pre_update_option', $capture, PHP_INT_MAX, 3 );
		$updated = update_option( $name, $input['value'] );
		remove_filter( 'pre_update_option', $capture, PHP_INT_MAX );

		if ( empty( $captured['seen'] ) ) {
			$rollback = self::restore_snapshot( $name, $before );
			return is_wp_error( $rollback )
				? $rollback
				: new WP_Error( 'cmsa_setting_update_unobserved', 'WordPress did not expose a final sanitized setting value for verification; the prior option state was restored.' );
		}

		$after = self::snapshot( $name );
		if ( is_wp_error( $after ) ) {
			$rollback = self::restore_snapshot( $name, $before );
			return is_wp_error( $rollback ) ? $rollback : $after;
		}

		$target_matches = $after['exists'] && self::values_equal( $after['value'], $captured['value'] );
		$type_valid = $target_matches && self::value_matches_type( $after['value'], (string) ( $registration['type'] ?? '' ) );
		if ( ! $target_matches || ! $type_valid ) {
			$rollback = self::restore_snapshot( $name, $before );
			if ( is_wp_error( $rollback ) ) {
				return $rollback;
			}
			return new WP_Error(
				'cmsa_setting_update_verification_failed',
				'The registered setting did not match the final sanitized value and declared type after update; the prior option state was restored.'
			);
		}

		$no_change = hash_equals( $before['state_token'], $after['state_token'] );
		if ( false === $updated && ! $no_change ) {
			$rollback = self::restore_snapshot( $name, $before );
			return is_wp_error( $rollback )
				? $rollback
				: new WP_Error( 'cmsa_setting_update_failed', 'WordPress reported that the registered setting update failed; the prior option state was restored.' );
		}

		$result = self::public_state( $name, $registration, $after, true );
		$result['updated'] = ! $no_change;
		$result['sanitized'] = true;
		$result['rollback_scope'] = 'option-state-only';
		return $result;
	}

	public static function can_administer_input_setting( $input ) {
		$resolved = self::resolve_input( $input, false );
		return ! is_wp_error( $resolved ) && self::can_administer( $resolved['name'], $resolved['registration'] );
	}

	private static function resolve_input( $input, $require_permission = true ) {
		$name = is_array( $input ) && isset( $input['setting'] ) ? trim( (string) $input['setting'] ) : '';
		if ( '' === $name || strlen( $name ) > 191 || false !== strpos( $name, "\0" ) ) {
			return new WP_Error( 'cmsa_setting_name', 'A valid registered setting name is required.' );
		}
		$settings = get_registered_settings();
		if ( ! isset( $settings[ $name ] ) || ! is_array( $settings[ $name ] ) ) {
			return new WP_Error( 'cmsa_setting_not_registered', 'The requested option is not currently registered through the WordPress Settings API.' );
		}
		if ( $require_permission && ! self::can_administer( $name, $settings[ $name ] ) ) {
			return new WP_Error( 'cmsa_setting_permission', 'The current user cannot administer the registered setting group.' );
		}
		return array( 'name' => $name, 'registration' => $settings[ $name ] );
	}

	private static function can_administer( $name, array $registration ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$group = isset( $registration['group'] ) ? (string) $registration['group'] : '';
		if ( '' === $group ) {
			return false;
		}
		$capability = apply_filters( 'option_page_capability_' . $group, 'manage_options' );
		return is_string( $capability ) && '' !== $capability && current_user_can( $capability );
	}

	private static function snapshot( $name ) {
		$sentinel = new stdClass();
		$value = get_option( $name, $sentinel );
		$exists = $value !== $sentinel;
		$token = self::state_token( $name, $exists, $exists ? $value : null );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		return array( 'exists' => $exists, 'value' => $exists ? $value : null, 'state_token' => $token );
	}

	private static function state_token( $name, $exists, $value ) {
		$serialized = $exists ? @serialize( $value ) : '';
		if ( $exists && ! is_string( $serialized ) ) {
			return new WP_Error( 'cmsa_setting_state', 'The registered setting value could not be serialized for conflict detection.' );
		}
		$payload = (string) $name . "\n" . ( $exists ? '1' : '0' ) . "\n" . $serialized;
		return hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );
	}

	private static function restore_snapshot( $name, array $snapshot ) {
		if ( ! empty( $snapshot['exists'] ) ) {
			update_option( $name, $snapshot['value'] );
		} else {
			delete_option( $name );
		}
		$restored = self::snapshot( $name );
		if ( is_wp_error( $restored ) || ! isset( $restored['state_token'] ) || ! hash_equals( (string) $snapshot['state_token'], (string) $restored['state_token'] ) ) {
			return new WP_Error( 'cmsa_setting_rollback_failed', 'The registered setting update failed and the prior option state could not be verified restored.' );
		}
		return true;
	}

	private static function public_state( $name, array $registration, array $snapshot, $include_value ) {
		$show_in_rest = ! empty( $registration['show_in_rest'] );
		$result = array(
			'setting'       => (string) $name,
			'group'         => (string) ( $registration['group'] ?? '' ),
			'type'          => (string) ( $registration['type'] ?? '' ),
			'label'         => sanitize_text_field( (string) ( $registration['label'] ?? '' ) ),
			'description'   => sanitize_text_field( (string) ( $registration['description'] ?? '' ) ),
			'show_in_rest'  => $show_in_rest,
			'exists'        => (bool) $snapshot['exists'],
			'state_token'   => (string) $snapshot['state_token'],
			'value_exposed' => false,
		);
		if ( $include_value && $show_in_rest ) {
			$result['value'] = $snapshot['value'];
			$result['value_exposed'] = true;
		}
		return $result;
	}

	private static function values_equal( $a, $b ) {
		return @serialize( $a ) === @serialize( $b );
	}

	private static function value_matches_type( $value, $type ) {
		switch ( $type ) {
			case 'string':
				return is_string( $value );
			case 'boolean':
				return is_bool( $value );
			case 'integer':
				return is_int( $value );
			case 'number':
				return is_int( $value ) || is_float( $value );
			case 'array':
				return is_array( $value );
			case 'object':
				return is_object( $value ) || ( is_array( $value ) && self::is_associative_array( $value ) );
			case 'null':
				return null === $value;
			case '':
				return true;
			default:
				return false;
		}
	}

	private static function is_associative_array( array $value ) {
		if ( empty( $value ) ) {
			return true;
		}
		return array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}

	private static function name_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'setting' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 191 ),
			),
			'required'             => array( 'setting' ),
			'additionalProperties' => false,
		);
	}

	private static function empty_schema() {
		return array( 'type' => 'object', 'properties' => array(), 'additionalProperties' => false );
	}

	private static function meta( $readonly, $idempotent ) {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'readonly' => (bool) $readonly, 'destructive' => false, 'idempotent' => (bool) $idempotent ),
		);
	}
}
