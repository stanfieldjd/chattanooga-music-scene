<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Ability_Bridge {
	const NAMESPACE_PREFIX = 'chattanooga-cms-admin/';
	const CATEGORY = 'chattanooga-cms-admin';

	private static $bridges = array();

	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Chattanooga CMS Admin', 'chattanooga-cms-admin' ),
				'description' => __( 'Runtime-discovered administrative abilities exposed through public WordPress contracts.', 'chattanooga-cms-admin' ),
			)
		);
	}

	public static function register_catalog_ability() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::NAMESPACE_PREFIX . 'catalog',
			array(
				'label'               => __( 'Universal capability catalog', 'chattanooga-cms-admin' ),
				'description'         => __( 'Lists runtime-generated facade abilities discovered from public WordPress contracts in the active environment.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'catalog' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	public static function register_external_bridges() {
		if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_get_ability' ) || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$abilities = wp_get_abilities();
		foreach ( $abilities as $ability ) {
			if ( ! $ability instanceof WP_Ability || ! self::is_bridgeable( $ability ) ) {
				continue;
			}

			$target_name = $ability->get_name();
			$bridge_name = self::bridge_name( $target_name );
			if ( wp_get_ability( $bridge_name ) instanceof WP_Ability ) {
				continue;
			}

			$args = array(
				'label'               => sprintf( __( 'Universal bridge: %s', 'chattanooga-cms-admin' ), $ability->get_label() ),
				'description'         => sprintf( __( 'Permission-preserving facade for the public WordPress ability %s.', 'chattanooga-cms-admin' ), $target_name ),
				'category'            => self::CATEGORY,
				'execute_callback'    => static function ( $input = null ) use ( $target_name ) {
					return CUA_Ability_Bridge::execute_target( $target_name, $input );
				},
				'permission_callback' => static function ( $input = null ) use ( $target_name ) {
					return CUA_Ability_Bridge::target_permission( $target_name, $input );
				},
				'meta'                => self::bridge_meta( $ability ),
			);

			$input_schema = $ability->get_input_schema();
			if ( is_array( $input_schema ) ) {
				$args['input_schema'] = $input_schema;
			}

			$output_schema = $ability->get_output_schema();
			if ( is_array( $output_schema ) ) {
				$args['output_schema'] = $output_schema;
			}

			wp_register_ability( $bridge_name, $args );
			if ( wp_get_ability( $bridge_name ) instanceof WP_Ability ) {
				self::$bridges[ $bridge_name ] = $target_name;
			}
		}
	}

	public static function catalog() {
		$items = array();
		foreach ( self::$bridges as $bridge_name => $target_name ) {
			$target = wp_get_ability( $target_name );
			if ( ! $target instanceof WP_Ability || ! self::is_bridgeable( $target ) ) {
				continue;
			}

			$meta = $target->get_meta();
			$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();
			$items[] = array(
				'contract'    => 'ability',
				'bridge'      => $bridge_name,
				'target'      => $target_name,
				'label'       => $target->get_label(),
				'description' => $target->get_description(),
				'category'    => $target->get_category(),
				'annotations' => array(
					'readonly'    => array_key_exists( 'readonly', $annotations ) && null !== $annotations['readonly'] ? (bool) $annotations['readonly'] : null,
					'destructive' => array_key_exists( 'destructive', $annotations ) && null !== $annotations['destructive'] ? (bool) $annotations['destructive'] : null,
					'idempotent'  => array_key_exists( 'idempotent', $annotations ) && null !== $annotations['idempotent'] ? (bool) $annotations['idempotent'] : null,
				),
			);
		}

		if ( class_exists( 'CUA_REST_Bridge' ) ) {
			$items = array_merge( $items, CUA_REST_Bridge::catalog_items() );
		}

		usort(
			$items,
			static function ( $left, $right ) {
				return strcmp( (string) $left['target'], (string) $right['target'] );
			}
		);

		return array(
			'count' => count( $items ),
			'items' => $items,
		);
	}

	public static function target_permission( $target_name, $input = null ) {
		$target = function_exists( 'wp_get_ability' ) ? wp_get_ability( $target_name ) : null;
		if ( ! $target instanceof WP_Ability || ! self::is_bridgeable( $target ) || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( is_array( $target->get_input_schema() ) ) {
			return $target->check_permissions( $input );
		}

		return $target->check_permissions();
	}

	public static function execute_target( $target_name, $input = null ) {
		$target = function_exists( 'wp_get_ability' ) ? wp_get_ability( $target_name ) : null;
		if ( ! $target instanceof WP_Ability || ! self::is_bridgeable( $target ) ) {
			return new WP_Error( 'cua_target_unavailable', 'The discovered target ability is no longer available.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'cua_target_forbidden', 'The current user is not permitted to use the universal administration bridge.' );
		}

		$has_input = is_array( $target->get_input_schema() );
		$permission = $has_input ? $target->check_permissions( $input ) : $target->check_permissions();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( ! $permission ) {
			return new WP_Error( 'cua_target_forbidden', 'The target ability denied the current request.' );
		}

		return $has_input ? $target->execute( $input ) : $target->execute();
	}

	private static function is_bridgeable( WP_Ability $ability ) {
		$name = $ability->get_name();
		if ( 0 === strpos( $name, self::NAMESPACE_PREFIX ) ) {
			return false;
		}

		$meta = $ability->get_meta();
		return ! empty( $meta['public'] );
	}

	private static function bridge_name( $target_name ) {
		return self::NAMESPACE_PREFIX . 'bridge-' . substr( hash( 'sha256', $target_name ), 0, 24 );
	}

	private static function bridge_meta( WP_Ability $ability ) {
		$meta = $ability->get_meta();
		$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();

		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => array_key_exists( 'readonly', $annotations ) && null !== $annotations['readonly'] ? (bool) $annotations['readonly'] : null,
				'destructive' => array_key_exists( 'destructive', $annotations ) && null !== $annotations['destructive'] ? (bool) $annotations['destructive'] : null,
				'idempotent'  => array_key_exists( 'idempotent', $annotations ) && null !== $annotations['idempotent'] ? (bool) $annotations['idempotent'] : null,
			),
		);
	}
}
