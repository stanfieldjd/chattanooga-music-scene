<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Bridge_Gateway {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX   = 'chattanooga-cms-admin/';

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'read-bridge',
			array(
				'label'               => __( 'Read universal bridge', 'chattanooga-cms-admin' ),
				'description'         => __( 'Executes one catalog-discovered read-only Chattanooga CMS Admin bridge while preserving the target permission callback and control-plane guard.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::input_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function ( $input ) {
					return CUA_Bridge_Gateway::execute( $input, true );
				},
				'permission_callback' => static function ( $input ) {
					return CUA_Bridge_Gateway::check_permissions( $input, true );
				},
				'meta'                => self::gateway_meta( true ),
			)
		);

		wp_register_ability(
			self::PREFIX . 'write-bridge',
			array(
				'label'               => __( 'Write universal bridge', 'chattanooga-cms-admin' ),
				'description'         => __( 'Executes one catalog-discovered mutating Chattanooga CMS Admin bridge while preserving the target permission callback and control-plane guard.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => self::input_schema(),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => static function ( $input ) {
					return CUA_Bridge_Gateway::execute( $input, false );
				},
				'permission_callback' => static function ( $input ) {
					return CUA_Bridge_Gateway::check_permissions( $input, false );
				},
				'meta'                => self::gateway_meta( false ),
			)
		);
	}

	public static function check_permissions( $input, $readonly ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$resolved = self::resolve_bridge( $input, $readonly );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		if ( 'rest' === $resolved['contract'] ) {
			return CUA_REST_Bridge::check_bridge_permissions( $resolved['bridge'], $resolved['arguments'] );
		}

		return CUA_Ability_Bridge::target_permission(
			$resolved['target'],
			$resolved['has_arguments'] ? $resolved['arguments'] : null
		);
	}

	public static function execute( $input, $readonly ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'cua_bridge_gateway_forbidden', 'The current user is not permitted to use the universal bridge gateway.' );
		}

		$resolved = self::resolve_bridge( $input, $readonly );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		if ( 'rest' === $resolved['contract'] ) {
			$result = CUA_REST_Bridge::execute_bridge( $resolved['bridge'], $resolved['arguments'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'bridge' => $resolved['bridge'],
				'result' => $result,
			);
		}

		$result = CUA_Ability_Bridge::execute_target(
			$resolved['target'],
			$resolved['has_arguments'] ? $resolved['arguments'] : null
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'bridge' => $resolved['bridge'],
			'result' => $result,
		);
	}

	private static function resolve_bridge( $input, $readonly ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'cua_bridge_gateway_invalid_input', 'Bridge gateway input must be an object.' );
		}

		$bridge = isset( $input['bridge'] ) ? (string) $input['bridge'] : '';
		if ( ! preg_match( '/^chattanooga-cms-admin\/(?:bridge|rest)-[a-f0-9]{24}$/', $bridge ) ) {
			return new WP_Error( 'cua_bridge_gateway_invalid_bridge', 'A catalog-issued Chattanooga CMS Admin bridge identifier is required.' );
		}

		$item = self::catalog_item( $bridge );
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$item_readonly = true === ( $item['annotations']['readonly'] ?? null );
		if ( (bool) $readonly !== $item_readonly ) {
			return new WP_Error(
				'cua_bridge_gateway_class_mismatch',
				$readonly
					? 'The selected bridge is not explicitly read-only and must use the write bridge gateway.'
					: 'The selected bridge is explicitly read-only and must use the read bridge gateway.'
			);
		}

		$has_arguments = array_key_exists( 'input', $input );
		$arguments = $has_arguments ? $input['input'] : null;
		if ( $has_arguments && ! is_array( $arguments ) ) {
			return new WP_Error( 'cua_bridge_gateway_invalid_arguments', 'Bridge arguments must be an object.' );
		}

		if ( 'rest' === ( $item['contract'] ?? '' ) ) {
			if ( ! class_exists( 'CUA_REST_Bridge' ) ) {
				return new WP_Error( 'cua_bridge_gateway_unavailable', 'The REST bridge resolver is unavailable.' );
			}
			if ( ! $has_arguments ) {
				return new WP_Error( 'cua_bridge_gateway_invalid_arguments', 'REST bridge execution requires a concrete path input.' );
			}

			return array(
				'bridge'        => $bridge,
				'contract'      => 'rest',
				'has_arguments' => true,
				'arguments'     => $arguments,
			);
		}

		if ( 'ability' !== ( $item['contract'] ?? '' ) || ! class_exists( 'CUA_Ability_Bridge' ) ) {
			return new WP_Error( 'cua_bridge_gateway_unavailable', 'The selected catalog bridge contract is unavailable.' );
		}

		$target = isset( $item['target'] ) ? trim( (string) $item['target'] ) : '';
		if ( '' === $target ) {
			return new WP_Error( 'cua_bridge_gateway_unavailable', 'The selected catalog ability target is unavailable.' );
		}

		return array(
			'bridge'        => $bridge,
			'contract'      => 'ability',
			'target'        => $target,
			'has_arguments' => $has_arguments,
			'arguments'     => $arguments,
		);
	}

	private static function catalog_item( $bridge ) {
		if ( ! class_exists( 'CUA_Ability_Bridge' ) ) {
			return new WP_Error( 'cua_bridge_gateway_catalog_unavailable', 'The universal bridge catalog is unavailable.' );
		}

		$catalog = CUA_Ability_Bridge::catalog();
		if ( ! is_array( $catalog ) || empty( $catalog['items'] ) || ! is_array( $catalog['items'] ) ) {
			return new WP_Error( 'cua_bridge_gateway_catalog_unavailable', 'The universal bridge catalog is unavailable.' );
		}

		foreach ( $catalog['items'] as $item ) {
			if ( is_array( $item ) && $bridge === (string) ( $item['bridge'] ?? '' ) ) {
				return $item;
			}
		}

		return new WP_Error( 'cua_bridge_gateway_unknown_bridge', 'The selected bridge is not present in the current universal catalog.' );
	}

	private static function input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'bridge' => array(
					'type'    => 'string',
					'pattern' => '^chattanooga-cms-admin/(?:bridge|rest)-[a-f0-9]{24}$',
				),
				'input' => array(
					'type' => 'object',
				),
			),
			'required'             => array( 'bridge' ),
			'additionalProperties' => false,
		);
	}

	private static function gateway_meta( $readonly ) {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => (bool) $readonly,
				'destructive' => ! $readonly,
				'idempotent'  => false,
			),
		);
	}
}
