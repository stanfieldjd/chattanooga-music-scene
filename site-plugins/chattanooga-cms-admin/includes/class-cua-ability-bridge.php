<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Ability_Bridge {
	const NAMESPACE_PREFIX = 'chattanooga-cms-admin/';
	const CATEGORY = 'chattanooga-cms-admin';

	private static $catalog_snapshot = null;
	private static $bridged_targets = array();
	private static $bridged_catalog_items = array();

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
					'properties'           => array(
						'cursor' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'limit' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 100,
						),
						'snapshot' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
					),
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
						'open_world'  => false,
					),
				),
			)
		);
	}

	public static function register_external_bridges() {
		if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_get_ability' ) || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		try {
			$abilities = self::registered_abilities();
		} catch ( Throwable $error ) {
			return;
		}

		foreach ( $abilities as $ability ) {
			try {
				if ( ! $ability instanceof WP_Ability || ! self::is_bridgeable( $ability ) ) {
					continue;
				}

			$target_name = $ability->get_name();
			$bridge_name = self::bridge_name( $target_name );
			if ( wp_get_ability( $bridge_name ) instanceof WP_Ability ) {
				self::remember_bridge( $target_name, $ability );
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

			$input_schema = self::normalize_schema_for_transport( $ability->get_input_schema() );
			if ( is_array( $input_schema ) ) {
				$args['input_schema'] = $input_schema;
			}

			$output_schema = self::normalize_schema_for_transport( $ability->get_output_schema() );
			if ( is_array( $output_schema ) ) {
				$args['output_schema'] = $output_schema;
			}

				try {
					$registration = wp_register_ability( $bridge_name, $args );
					if ( is_wp_error( $registration ) || false === $registration || ! ( wp_get_ability( $bridge_name ) instanceof WP_Ability ) ) {
						throw new RuntimeException( 'The provider facade descriptor was rejected.' );
					}
				} catch ( Throwable $registration_error ) {
					// Some third-party public abilities expose schemas accepted by their
					// own runtime but rejected by the WordPress registry when reused as a
					// facade descriptor. Keep the public contract discoverable and let the
					// target ability remain authoritative for validation and execution.
					unset( $args['input_schema'], $args['output_schema'] );
					wp_register_ability( $bridge_name, $args );
				}
				if ( wp_get_ability( $bridge_name ) instanceof WP_Ability ) {
					self::$bridged_targets[ $target_name ] = $ability;
				}
			} catch ( Throwable $error ) {
				// A malformed third-party ability must not abort core bridge registration.
				continue;
			}
		}

		self::prime_catalog_snapshot();
	}

	private static function remember_bridge( $target_name, $ability ) {
		self::$bridged_targets[ $target_name ] = $ability;
		try {
			self::$bridged_catalog_items[ $target_name ] = self::catalog_item_from_ability( $ability );
		} catch ( Throwable $error ) {
			// Keep the executable facade even if an optional descriptor field is malformed.
		}
	}

	private static function registered_abilities() {
		if ( class_exists( 'WP_Abilities_Registry' ) ) {
			$registry = WP_Abilities_Registry::get_instance();
			if ( $registry && method_exists( $registry, 'get_all_registered' ) ) {
				return $registry->get_all_registered();
			}
		}
		return wp_get_abilities();
	}

	public static function prime_catalog_snapshot() {
		// The first registry read initializes wp_abilities_api_init and returns
		// the pre-hook registry snapshot; read again after provider callbacks run.
		if ( function_exists( 'wp_get_abilities' ) ) {
			wp_get_abilities();
		}
		self::$catalog_snapshot = self::catalog_items();
	}

	public static function catalog( $input = array() ) {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return new WP_Error( 'cua_catalog_registry_unavailable', 'The WordPress public ability registry is unavailable.' );
		}
		// Discovery reads the settled registry at call time. A lifecycle snapshot can
		// become stale when a provider registers or reconciles an ability after the
		// plugin's initialization hook, so never let that stale snapshot hide a
		// currently registered public contract.
		$items = self::catalog_items();
		if ( empty( $items ) && is_array( self::$catalog_snapshot ) ) {
			$items = self::$catalog_snapshot;
		}

		if ( class_exists( 'CUA_REST_Bridge' ) ) {
			try {
				$rest_items = CUA_REST_Bridge::catalog_items();
				if ( is_array( $rest_items ) ) {
					$items = array_merge( $items, $rest_items );
				}
			} catch ( Throwable $error ) {
				// A malformed third-party REST route must not abort the ability catalog.
			}
		}

		$unique = array();
		foreach ( $items as $item ) {
			$key = implode( '|', array( (string) ( $item['contract'] ?? '' ), (string) ( $item['target'] ?? '' ), (string) ( $item['bridge'] ?? '' ) ) );
			$unique[ $key ] = $item;
		}
		$items = array_values( $unique );
		usort(
			$items,
			static function ( $left, $right ) {
				return strcmp( (string) $left['target'], (string) $right['target'] );
			}
		);

		$encoded_snapshot = wp_json_encode( $items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$snapshot = false === $encoded_snapshot ? '' : hash( 'sha256', (string) $encoded_snapshot );
		$requested_snapshot = is_array( $input ) && isset( $input['snapshot'] ) ? trim( (string) $input['snapshot'] ) : '';
		if ( '' !== $requested_snapshot && ( '' === $snapshot || ! hash_equals( $snapshot, $requested_snapshot ) ) ) {
			return new WP_Error( 'cua_catalog_snapshot_changed', 'The WordPress capability catalog changed between pages. Restart discovery from cursor 0.' );
		}

		$total  = count( $items );
		$cursor = is_array( $input ) && isset( $input['cursor'] ) ? max( 0, (int) $input['cursor'] ) : 0;
		$limit  = is_array( $input ) && isset( $input['limit'] ) ? min( 100, max( 1, (int) $input['limit'] ) ) : 100;
		$page   = array_slice( $items, $cursor, $limit );
		$next   = $cursor + count( $page ) < $total ? $cursor + count( $page ) : null;

		return array(
			'count'      => $total,
			'cursor'     => $cursor,
			'pageSize'   => $limit,
			'items'      => $page,
			'nextCursor' => $next,
			'snapshot'   => $snapshot,
		);
	}

	public static function catalog_items() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$items = array();
		try {
			$targets = array();
			$targets = self::registered_abilities();
		} catch ( Throwable $error ) {
			return $items;
		}
		foreach ( $targets as $target ) {
			if ( ! $target instanceof WP_Ability || ! self::is_bridgeable( $target ) ) {
				continue;
			}

			try {
				$target_name = $target->get_name();
				// Retain any confirmed provider target observed during catalog enumeration.
				// A provider may be hidden by a later re-entrant registry read, but its
				// already-registered facade must remain discoverable for this request.
				self::remember_bridge( $target_name, $target );
				$meta = array();
				try {
					$raw_meta = $target->get_meta();
					if ( is_array( $raw_meta ) ) {
						$meta = $raw_meta;
					}
				} catch ( Throwable $meta_error ) {
					// Metadata is optional; preserve the ability identity when it is malformed.
				}
				$label = $target_name;
				try {
					$label = (string) $target->get_label();
				} catch ( Throwable $label_error ) {
					// Preserve the target name as a stable fallback label.
				}
				$description = '';
				try {
					$description = (string) $target->get_description();
				} catch ( Throwable $description_error ) {
					// Preserve discoverability when an optional description is malformed.
				}
				$category = '';
				try {
					$category = (string) $target->get_category();
				} catch ( Throwable $category_error ) {
					// Preserve discoverability when an optional category is malformed.
				}
				$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();
				$item = array(
					'contract'    => 'ability',
					'bridge'      => self::bridge_name( $target_name ),
					'target'      => $target_name,
					'label'       => $label,
					'description' => $description,
					'category'    => $category,
					'annotations' => array(
						'readonly'    => array_key_exists( 'readonly', $annotations ) && null !== $annotations['readonly'] ? (bool) $annotations['readonly'] : null,
						'destructive' => array_key_exists( 'destructive', $annotations ) && null !== $annotations['destructive'] ? (bool) $annotations['destructive'] : null,
						'idempotent'  => array_key_exists( 'idempotent', $annotations ) && null !== $annotations['idempotent'] ? (bool) $annotations['idempotent'] : null,
						'open_world'  => array_key_exists( 'open_world', $annotations ) && null !== $annotations['open_world'] ? (bool) $annotations['open_world'] : null,
					),
				);
				try {
					$input_schema = self::normalize_schema_for_transport( $target->get_input_schema() );
					if ( is_array( $input_schema ) ) { $item['inputSchema'] = $input_schema; }
				} catch ( Throwable $schema_error ) {
					// Keep the provider identity discoverable when its optional schema is malformed.
				}
				try {
					$output_schema = self::normalize_schema_for_transport( $target->get_output_schema() );
					if ( is_array( $output_schema ) ) { $item['outputSchema'] = $output_schema; }
				} catch ( Throwable $schema_error ) {
					// Keep the provider identity discoverable when its optional schema is malformed.
				}
				$items[] = $item;
			} catch ( Throwable $error ) {
				// A malformed third-party ability must not abort the ability catalog.
			}
		}

		foreach ( self::$bridged_catalog_items as $target_name => $item ) {
			if ( ! isset( $known_targets[ $target_name ] ) && is_array( $item ) ) {
				$items[] = $item;
			}
		}

		// A provider can be visible through wp_get_ability() while its entry is
		// temporarily omitted from a filtered registry enumeration during a
		// re-entrant catalog call. Facades already registered by this bridge are
		// authoritative, so retain and merge their original ability objects.
		$known_targets = array();
		foreach ( $items as $item ) {
			$known_targets[ (string) ( $item['target'] ?? '' ) ] = true;
		}
		foreach ( self::$bridged_targets as $target_name => $target ) {
			if ( isset( $known_targets[ $target_name ] ) || ! $target instanceof WP_Ability || ! self::is_bridgeable( $target ) ) {
				continue;
			}
			try {
				$items[] = self::catalog_item_from_ability( $target );
			} catch ( Throwable $error ) {
				// Preserve the rest of the catalog when an optional provider field is malformed.
			}
		}
		usort(
			$items,
			static function ( $left, $right ) {
				return strcmp( (string) $left['target'], (string) $right['target'] );
			}
		);

		return $items;
	}

	private static function catalog_item_from_ability( WP_Ability $target ) {
		$name = $target->get_name();
		$meta = $target->get_meta();
		$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();
		$item = array(
			'contract'    => 'ability',
			'bridge'      => self::bridge_name( $name ),
			'target'      => $name,
			'label'       => (string) $target->get_label(),
			'description' => (string) $target->get_description(),
			'category'    => (string) $target->get_category(),
			'annotations' => array(
				'readonly'    => array_key_exists( 'readonly', $annotations ) && null !== $annotations['readonly'] ? (bool) $annotations['readonly'] : null,
				'destructive' => array_key_exists( 'destructive', $annotations ) && null !== $annotations['destructive'] ? (bool) $annotations['destructive'] : null,
				'idempotent'  => array_key_exists( 'idempotent', $annotations ) && null !== $annotations['idempotent'] ? (bool) $annotations['idempotent'] : null,
				'open_world'  => array_key_exists( 'open_world', $annotations ) && null !== $annotations['open_world'] ? (bool) $annotations['open_world'] : null,
			),
		);
		$input_schema = self::normalize_schema_for_transport( $target->get_input_schema() );
		if ( is_array( $input_schema ) ) { $item['inputSchema'] = $input_schema; }
		$output_schema = self::normalize_schema_for_transport( $target->get_output_schema() );
		if ( is_array( $output_schema ) ) { $item['outputSchema'] = $output_schema; }
		return $item;
	}

	private static function normalize_schema_for_transport( $value, $parent_key = '' ) {
		$object_keywords = array( '$defs', '$vocabulary', 'definitions', 'dependentRequired', 'dependentSchemas', 'patternProperties', 'properties' );
		if ( is_array( $value ) ) {
			if ( empty( $value ) && in_array( (string) $parent_key, $object_keywords, true ) ) { return new stdClass(); }
			$normalized = array();
			foreach ( $value as $key => $item ) { $normalized[ $key ] = self::normalize_schema_for_transport( $item, is_string( $key ) ? $key : '' ); }
			return $normalized;
		}
		return $value;
	}

	public static function target_permission( $target_name, $input = null ) {
		try {
			$target = function_exists( 'wp_get_ability' ) ? wp_get_ability( $target_name ) : null;
			if ( ! $target instanceof WP_Ability || ! self::is_bridgeable( $target ) || ! current_user_can( 'manage_options' ) ) {
				return false;
			}
		} catch ( Throwable $error ) {
			return new WP_Error( 'cua_target_resolution_exception', 'The discovered target could not be resolved safely.' );
		}

		$guard = self::guard_target( $target, $input );
		if ( is_wp_error( $guard ) || false === $guard ) {
			return $guard;
		}

		try {
			if ( is_array( $target->get_input_schema() ) ) {
				return $target->check_permissions( $input );
			}

			return $target->check_permissions();
		} catch ( Throwable $error ) {
			return new WP_Error( 'cua_target_permission_exception', 'The discovered target permission check failed.' );
		}
	}

	public static function execute_target( $target_name, $input = null ) {
		try {
			$target = function_exists( 'wp_get_ability' ) ? wp_get_ability( $target_name ) : null;
			if ( ! $target instanceof WP_Ability || ! self::is_bridgeable( $target ) ) {
				return new WP_Error( 'cua_target_unavailable', 'The discovered target ability is no longer available.' );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return new WP_Error( 'cua_target_forbidden', 'The current user is not permitted to use the universal administration bridge.' );
			}
		} catch ( Throwable $error ) {
			return new WP_Error( 'cua_target_resolution_exception', 'The discovered target could not be resolved safely.' );
		}

		try {
			$guard = self::guard_target( $target, $input );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
			if ( false === $guard ) {
				return new WP_Error( 'cua_target_forbidden', 'The control-plane guard denied the current request.' );
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
		} catch ( Throwable $error ) {
			return new WP_Error( 'cua_target_execution_exception', 'The discovered target execution failed.' );
		}
	}

	private static function guard_target( WP_Ability $target, $input ) {
		if ( ! class_exists( 'CUA_Control_Plane_Guard' ) ) {
			return false;
		}
		return CUA_Control_Plane_Guard::validate_ability_input( $target, $input );
	}

	private static function is_bridgeable( WP_Ability $ability ) {
		$name = $ability->get_name();
		if ( 0 === strpos( $name, self::NAMESPACE_PREFIX ) ) {
			return false;
		}

		$meta = $ability->get_meta();
		if ( isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && array_key_exists( 'public', $meta['mcp'] ) && null !== $meta['mcp']['public'] ) {
			return true === $meta['mcp']['public'];
		}

		return true === ( $meta['public'] ?? false );
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
			'mcp'          => array( 'public' => false ),
			'annotations'  => array(
				'readonly'    => array_key_exists( 'readonly', $annotations ) && null !== $annotations['readonly'] ? (bool) $annotations['readonly'] : null,
				'destructive' => array_key_exists( 'destructive', $annotations ) && null !== $annotations['destructive'] ? (bool) $annotations['destructive'] : null,
				'idempotent'  => array_key_exists( 'idempotent', $annotations ) && null !== $annotations['idempotent'] ? (bool) $annotations['idempotent'] : null,
			),
		);
	}
}
