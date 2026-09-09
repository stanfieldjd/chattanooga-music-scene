<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Marketplace_Listing_Abilities {
	private $listings;

	public function __construct( CMSA_Marketplace_Listings $listings ) {
		$this->listings = $listings;
	}

	public function register() {
		$permission = function () {
			return current_user_can( 'manage_awpcp' ) && current_user_can( 'edit_others_awpcp_classified_ads' );
		};
		$status_values = array( 'any', 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'disabled', 'auto-draft' );

		$this->register_ability(
			'list-marketplace-listings',
			'List Marketplace listings',
			'Returns a bounded administrative collection of community Marketplace listings through the verified native listing model. Output is restricted to non-sensitive inventory fields.',
			$this->object_schema(
				array(
					'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
					'search'   => array( 'type' => 'string', 'default' => '' ),
					'status'   => array( 'type' => 'string', 'enum' => $status_values, 'default' => 'any' ),
				)
			),
			function ( $input ) { return $this->listings->list_items( is_array( $input ) ? $input : array() ); },
			$permission
		);

		$this->register_ability(
			'get-marketplace-listing',
			'Get Marketplace listing',
			'Returns one community Marketplace listing through the verified native listing model using an explicit non-sensitive field allowlist.',
			$this->object_schema( array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ) ),
			function ( $input ) { return $this->listings->get_item( $input['id'] ); },
			$permission
		);
	}

	private function register_ability( $slug, $label, $description, $input_schema, $callback, $permission ) {
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
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
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
