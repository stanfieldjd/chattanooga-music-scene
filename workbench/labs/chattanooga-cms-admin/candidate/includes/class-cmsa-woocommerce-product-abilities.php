<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_WooCommerce_Product_Abilities {
	private $products;

	public function __construct( CMSA_WooCommerce_Products $products ) {
		$this->products = $products;
	}

	public function register() {
		$permission = function () {
			return current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_products' );
		};

		$this->register_ability(
			'list-products',
			'List WooCommerce products',
			'Returns a bounded WooCommerce product collection through the native product model. Output is restricted to allowlisted catalog fields and exact bounded-state tokens.',
			$this->object_schema(
				array(
					'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
					'search'   => array( 'type' => 'string', 'default' => '' ),
					'status'   => array( 'type' => 'string', 'enum' => array( 'any', 'publish', 'draft', 'pending', 'future', 'private', 'trash' ), 'default' => 'any' ),
				)
			),
			function ( $input ) { return $this->products->list_items( is_array( $input ) ? $input : array() ); },
			$permission,
			true,
			false,
			true
		);

		$this->register_ability(
			'get-product',
			'Get WooCommerce product',
			'Returns one editable WooCommerce product through the native product model using an explicit catalog-field allowlist and exact bounded-state token.',
			$this->object_schema( array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ) ),
			function ( $input ) { return $this->products->get_item( $input['id'] ); },
			$permission,
			true,
			false,
			true
		);

		$product_fields = $this->product_fields_schema();
		$create_fields = $product_fields;
		$this->register_ability(
			'create-simple-product-draft',
			'Create WooCommerce simple-product draft',
			'Creates only a core WooCommerce simple product in draft status through WooCommerce setters and its native data store. Publication, deletion, orders, customers, payments and arbitrary metadata are outside this ability.',
			$this->object_schema( $create_fields, array( 'name' ) ),
			function ( $input ) { return $this->products->create_simple_draft( $input ); },
			$permission,
			false,
			false,
			false
		);

		$update_fields = array_merge(
			array(
				'id'                   => array( 'type' => 'integer', 'minimum' => 1 ),
				'expected_state_token' => array( 'type' => 'string', 'minLength' => 1 ),
			),
			$product_fields
		);
		$this->register_ability(
			'update-simple-product',
			'Update WooCommerce simple product',
			'Updates only allowlisted fields on one core WooCommerce simple product after exact bounded-state conflict checks. The existing publication state is preserved, stale and no-change writes are rejected, and failed verification restores the prior bounded product state through WooCommerce.',
			$this->object_schema( $update_fields, array( 'id', 'expected_state_token' ) ),
			function ( $input ) { return $this->products->update_simple( $input ); },
			$permission,
			false,
			false,
			false
		);
	}

	private function product_fields_schema() {
		$id_array = array(
			'type'     => 'array',
			'maxItems' => 100,
			'items'    => array( 'type' => 'integer', 'minimum' => 1 ),
		);
		return array(
			'name'                => array( 'type' => 'string', 'maxLength' => 255 ),
			'description'         => array( 'type' => 'string' ),
			'short_description'   => array( 'type' => 'string' ),
			'sku'                 => array( 'type' => 'string', 'maxLength' => 255 ),
			'regular_price'       => array( 'type' => 'string', 'maxLength' => 32 ),
			'sale_price'          => array( 'type' => 'string', 'maxLength' => 32 ),
			'catalog_visibility'  => array( 'type' => 'string', 'enum' => array( 'visible', 'catalog', 'search', 'hidden' ) ),
			'manage_stock'        => array( 'type' => 'boolean' ),
			'stock_quantity'      => array( 'type' => 'integer', 'minimum' => 0 ),
			'stock_status'        => array( 'type' => 'string', 'enum' => array( 'instock', 'outofstock', 'onbackorder' ) ),
			'category_ids'        => $id_array,
			'tag_ids'             => $id_array,
			'image_id'            => array( 'type' => 'integer', 'minimum' => 0 ),
			'gallery_image_ids'   => $id_array,
		);
	}

	private function register_ability( $slug, $label, $description, $input_schema, $callback, $permission, $readonly, $destructive, $idempotent ) {
		wp_register_ability(
			'chattanooga-cms-admin/' . $slug,
			array(
				'label'               => __( $label, 'chattanooga-cms-admin' ),
				'description'         => __( $description, 'chattanooga-cms-admin' ),
				'category'            => 'chattanooga-cms-admin',
				'input_schema'        => $input_schema,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => $callback,
				'permission_callback' => function () use ( $permission ) {
					return true === call_user_func( $permission );
				},
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => (bool) $readonly,
						'destructive' => (bool) $destructive,
						'idempotent'  => (bool) $idempotent,
					),
				),
			)
		);
	}

	private function object_schema( array $properties, array $required = array() ) {
		$schema = array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
		if ( $required ) {
			$schema['required'] = $required;
		}
		return $schema;
	}
}
