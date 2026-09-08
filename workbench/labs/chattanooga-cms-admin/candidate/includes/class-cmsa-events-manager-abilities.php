<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Events_Manager_Abilities {
	private $events;

	public function __construct( CMSA_Events_Manager $events ) {
		$this->events = $events;
	}

	public function register() {
		$list_schema = $this->object_schema(
			array(
				'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
				'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
				'search'   => array( 'type' => 'string', 'default' => '' ),
			)
		);
		$id_schema = $this->object_schema( array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ) );

		$this->register_ability( 'list-events', 'List events', 'Returns a bounded Events Manager event collection using a fixed field allowlist.', $list_schema, function ( $input ) { return $this->events->list_events( is_array( $input ) ? $input : array() ); }, 'edit_events' );
		$this->register_ability( 'get-event', 'Get event', 'Returns one Events Manager event using a fixed field allowlist.', $id_schema, function ( $input ) { return $this->events->get_event( $input['id'] ); }, 'edit_events' );
		$this->register_ability( 'list-locations', 'List locations', 'Returns a bounded Events Manager location collection using a fixed field allowlist.', $list_schema, function ( $input ) { return $this->events->list_locations( is_array( $input ) ? $input : array() ); }, 'edit_locations' );
		$this->register_ability( 'get-location', 'Get location', 'Returns one Events Manager location using a fixed field allowlist.', $id_schema, function ( $input ) { return $this->events->get_location( $input['id'] ); }, 'edit_locations' );
	}

	private function register_ability( $slug, $label, $description, $input_schema, $callback, $capability ) {
		wp_register_ability(
			'chattanooga-cms-admin/' . $slug,
			array(
				'label'               => __( $label, 'chattanooga-cms-admin' ),
				'description'         => __( $description, 'chattanooga-cms-admin' ),
				'category'            => 'chattanooga-cms-admin',
				'input_schema'        => $input_schema,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => $callback,
				'permission_callback' => function () use ( $capability ) { return current_user_can( $capability ); },
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);
	}

	private function object_schema( array $properties, array $required = array() ) {
		$schema = array( 'type' => 'object', 'properties' => $properties, 'additionalProperties' => false );
		if ( $required ) {
			$schema['required'] = $required;
		}
		return $schema;
	}
}
