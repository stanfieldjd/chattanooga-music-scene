<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Content_Taxonomy_Abilities {
	private $taxonomy;

	public function __construct( CMSA_Content_Taxonomy $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	public function register() {
		$this->register_term_group( 'category', 'category', 'categories', true );
		$this->register_term_group( 'post_tag', 'tag', 'tags', false );
		$this->register_relationship( 'category', 'categories' );
		$this->register_relationship( 'post_tag', 'tags' );
	}

	private function register_term_group( $taxonomy, $singular, $plural, $hierarchical ) {
		$list_properties = array(
			'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
			'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ),
			'search'   => array( 'type' => 'string', 'default' => '' ),
		);
		if ( $hierarchical ) {
			$list_properties['parent'] = array( 'type' => 'integer', 'minimum' => 0 );
		}
		$this->register_ability(
			'list-' . $plural,
			'List ' . $plural,
			'Returns bounded WordPress ' . $plural . ' with stable state tokens for conflict-aware administration.',
			$this->object_schema( $list_properties ),
			function ( $input ) use ( $taxonomy ) { return $this->taxonomy->list_terms( $taxonomy, is_array( $input ) ? $input : array() ); },
			'edit_posts', true, false, true
		);
		$this->register_ability(
			'get-' . $singular,
			'Get ' . $singular,
			'Returns one WordPress ' . $singular . ' and its conflict-detection state token.',
			$this->object_schema( array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ) ),
			function ( $input ) use ( $taxonomy ) { return $this->taxonomy->get_term( $taxonomy, $input['id'] ); },
			'edit_posts', true, false, true
		);

		$create_properties = array(
			'name'        => array( 'type' => 'string', 'minLength' => 1 ),
			'slug'        => array( 'type' => 'string', 'default' => '' ),
			'description' => array( 'type' => 'string', 'default' => '' ),
		);
		if ( $hierarchical ) {
			$create_properties['parent'] = array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 );
		}
		$this->register_ability(
			'create-' . $singular,
			'Create ' . $singular,
			'Creates one WordPress ' . $singular . ' in the bounded ' . $taxonomy . ' taxonomy and verifies the resulting term.',
			$this->object_schema( $create_properties, array( 'name' ) ),
			function ( $input ) use ( $taxonomy ) { return $this->taxonomy->create_term( $taxonomy, $input ); },
			'manage_categories', false, false, false
		);

		$update_properties = array(
			'id'                   => array( 'type' => 'integer', 'minimum' => 1 ),
			'expected_state_token' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
			'name'                 => array( 'type' => 'string' ),
			'slug'                 => array( 'type' => 'string' ),
			'description'          => array( 'type' => 'string' ),
		);
		if ( $hierarchical ) {
			$update_properties['parent'] = array( 'type' => 'integer', 'minimum' => 0 );
		}
		$this->register_ability(
			'update-' . $singular,
			'Update ' . $singular,
			'Updates one WordPress ' . $singular . ' only when its expected state token still matches, and automatically restores the prior term state if verification fails.',
			$this->object_schema( $update_properties, array( 'id', 'expected_state_token' ) ),
			function ( $input ) use ( $taxonomy ) { return $this->taxonomy->update_term( $taxonomy, $input ); },
			'manage_categories', false, true, false
		);
	}

	private function register_relationship( $taxonomy, $plural ) {
		$schema = $this->object_schema(
			array(
				'id'                => array( 'type' => 'integer', 'minimum' => 1 ),
				'expected_term_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'uniqueItems' => true ),
				'term_ids'          => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'minItems' => 1, 'uniqueItems' => true ),
			),
			array( 'id', 'expected_term_ids', 'term_ids' )
		);

		$this->register_ability(
			'assign-post-' . $plural,
			'Assign post ' . $plural,
			'Adds existing ' . $plural . ' to one editable post after verifying the exact expected relationship set. Verification failure restores the prior set.',
			$schema,
			function ( $input ) use ( $taxonomy ) { return $this->taxonomy->change_relationship( 'post', $taxonomy, $input, 'assign' ); },
			'edit_posts', false, false, false
		);
		$this->register_ability(
			'remove-post-' . $plural,
			'Remove post ' . $plural,
			'Removes selected ' . $plural . ' from one editable post after verifying the exact expected relationship set. Category removal preserves a valid default category when necessary.',
			$schema,
			function ( $input ) use ( $taxonomy ) { return $this->taxonomy->change_relationship( 'post', $taxonomy, $input, 'remove' ); },
			'edit_posts', false, true, false
		);
	}

	private function register_ability( $slug, $label, $description, $input_schema, $callback, $capability, $readonly, $destructive, $idempotent ) {
		wp_register_ability(
			'chattanooga-cms-admin/' . $slug,
			array(
				'label'               => __( $label, 'chattanooga-cms-admin' ),
				'description'         => __( $description, 'chattanooga-cms-admin' ),
				'category'            => 'chattanooga-cms-admin',
				'input_schema'        => $input_schema,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => $callback,
				'permission_callback' => function () use ( $capability ) {
					return current_user_can( $capability );
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
