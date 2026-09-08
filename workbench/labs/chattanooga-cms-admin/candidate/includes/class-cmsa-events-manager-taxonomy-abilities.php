<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Events_Manager_Taxonomy_Abilities {
	private $taxonomy;

	public function __construct( CMSA_Events_Manager_Taxonomy $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	public function register() {
		$taxonomy_property = array(
			'type' => 'string',
			'enum' => array( 'event-categories', 'event-tags' ),
		);
		$list_schema = $this->object_schema(
			array(
				'taxonomy' => $taxonomy_property,
				'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
				'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ),
				'search'   => array( 'type' => 'string', 'default' => '' ),
				'parent'   => array( 'type' => 'integer', 'minimum' => 0 ),
			),
			array( 'taxonomy' )
		);
		$get_schema = $this->object_schema(
			array(
				'id'       => array( 'type' => 'integer', 'minimum' => 1 ),
				'taxonomy' => $taxonomy_property,
			),
			array( 'id', 'taxonomy' )
		);
		$set_schema = $this->object_schema(
			array(
				'id'                         => array( 'type' => 'integer', 'minimum' => 1 ),
				'taxonomy'                   => $taxonomy_property,
				'expected_event_state_token' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
				'expected_term_ids'          => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'uniqueItems' => true ),
				'term_ids'                   => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'uniqueItems' => true ),
			),
			array( 'id', 'taxonomy', 'expected_event_state_token', 'expected_term_ids', 'term_ids' )
		);

		$this->register_ability(
			'list-event-taxonomy-terms',
			'List event taxonomy terms',
			'Returns bounded existing Events Manager event categories or tags without changing term state.',
			$list_schema,
			function ( $input ) { return $this->taxonomy->list_terms( $input['taxonomy'], is_array( $input ) ? $input : array() ); },
			true,
			false,
			true
		);
		$this->register_ability(
			'get-event-taxonomy',
			'Get event taxonomy',
			'Returns the exact current category or tag relationship set for one ordinary single event together with the event conflict token.',
			$get_schema,
			function ( $input ) { return $this->taxonomy->get_event_terms( $input['taxonomy'], $input['id'] ); },
			true,
			false,
			true
		);
		$this->register_ability(
			'set-event-taxonomy',
			'Set event taxonomy',
			'Replaces one ordinary single event category or tag relationship set only when both the event state and prior relationship set still match; verification failure restores the prior relationship set.',
			$set_schema,
			function ( $input ) { return $this->taxonomy->set_event_terms( $input['taxonomy'], $input ); },
			false,
			true,
			false
		);
	}

	private function register_ability( $slug, $label, $description, $input_schema, $callback, $readonly, $destructive, $idempotent ) {
		wp_register_ability(
			'chattanooga-cms-admin/' . $slug,
			array(
				'label'               => __( $label, 'chattanooga-cms-admin' ),
				'description'         => __( $description, 'chattanooga-cms-admin' ),
				'category'            => 'chattanooga-cms-admin',
				'input_schema'        => $input_schema,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => $callback,
				'permission_callback' => function () { return current_user_can( 'edit_events' ); },
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
