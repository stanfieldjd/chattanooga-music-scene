<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Universal_Control_Plane {
	const OWN_NAMESPACE = 'chattanooga-cms-admin/';

	public function inspect( array $input ) {
		if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error( 'cmsa_universal_unavailable', 'The WordPress Abilities API is unavailable.' );
		}

		$name = isset( $input['name'] ) ? trim( (string) $input['name'] ) : '';
		if ( '' !== $name ) {
			return $this->inspect_exact( $name );
		}

		$namespace = isset( $input['namespace'] ) ? trim( (string) $input['namespace'] ) : '';
		$category  = isset( $input['category'] ) ? trim( (string) $input['category'] ) : '';
		$search    = isset( $input['search'] ) ? trim( (string) $input['search'] ) : '';
		$page      = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page  = isset( $input['per_page'] ) ? min( 100, max( 1, (int) $input['per_page'] ) ) : 50;

		$items = array();
		foreach ( wp_get_abilities() as $ability ) {
			if ( ! $ability instanceof WP_Ability || ! $this->is_discoverable( $ability ) ) {
				continue;
			}

			$item = $this->describe( $ability );
			if ( '' !== $namespace && $item['namespace'] !== $namespace ) {
				continue;
			}
			if ( '' !== $category && $item['category'] !== $category ) {
				continue;
			}
			if ( '' !== $search ) {
				$haystack = strtolower( $item['name'] . "\n" . $item['label'] . "\n" . $item['description'] );
				if ( false === strpos( $haystack, strtolower( $search ) ) ) {
					continue;
				}
			}
			$items[] = $item;
		}

		usort(
			$items,
			static function ( $left, $right ) {
				return strcmp( $left['name'], $right['name'] );
			}
		);

		$total  = count( $items );
		$offset = ( $page - 1 ) * $per_page;
		$items  = array_slice( $items, $offset, $per_page );

		return array(
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'items'    => $items,
		);
	}

	private function inspect_exact( $name ) {
		if ( 0 === strpos( $name, self::OWN_NAMESPACE ) ) {
			return new WP_Error( 'cmsa_universal_self', 'Chattanooga CMS Admin abilities are not mirrored through the universal extension catalog.' );
		}

		$ability = wp_get_ability( $name );
		if ( ! $ability instanceof WP_Ability || ! $this->is_discoverable( $ability ) ) {
			return new WP_Error( 'cmsa_universal_not_found', 'A public native extension ability with that name was not found.' );
		}

		return array( 'item' => $this->describe( $ability ) );
	}

	private function is_discoverable( WP_Ability $ability ) {
		$name = $ability->get_name();
		if ( 0 === strpos( $name, self::OWN_NAMESPACE ) ) {
			return false;
		}

		$meta = $ability->get_meta();
		return ! empty( $meta['public'] );
	}

	private function describe( WP_Ability $ability ) {
		$name = $ability->get_name();
		$slash = strpos( $name, '/' );
		$namespace = false === $slash ? '' : substr( $name, 0, $slash );
		$meta = $ability->get_meta();
		$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();

		return array(
			'name'          => $name,
			'namespace'     => $namespace,
			'label'         => $ability->get_label(),
			'description'   => $ability->get_description(),
			'category'      => $ability->get_category(),
			'input_schema'  => $ability->get_input_schema(),
			'output_schema' => $ability->get_output_schema(),
			'public'        => ! empty( $meta['public'] ),
			'show_in_rest'  => ! empty( $meta['show_in_rest'] ),
			'mcp_public'    => ! empty( $meta['mcp']['public'] ),
			'annotations'   => array(
				'readonly'    => array_key_exists( 'readonly', $annotations ) ? $annotations['readonly'] : null,
				'destructive' => array_key_exists( 'destructive', $annotations ) ? $annotations['destructive'] : null,
				'idempotent'  => array_key_exists( 'idempotent', $annotations ) ? $annotations['idempotent'] : null,
			),
		);
	}
}
