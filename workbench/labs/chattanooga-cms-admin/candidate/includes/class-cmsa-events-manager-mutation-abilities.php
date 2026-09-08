<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Events_Manager_Mutation_Abilities {
	private $mutations;

	public function __construct( CMSA_Events_Manager_Mutations $mutations ) {
		$this->mutations = $mutations;
	}

	public function register() {
		$event_properties = array(
			'event_name'       => array( 'type' => 'string', 'minLength' => 1 ),
			'content'          => array( 'type' => 'string' ),
			'post_status'      => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ) ),
			'event_start_date' => array( 'type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}$' ),
			'event_end_date'   => array( 'type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}$' ),
			'event_start_time' => array( 'type' => 'string', 'pattern' => '^\\d{2}:\\d{2}(:\\d{2})?$' ),
			'event_end_time'   => array( 'type' => 'string', 'pattern' => '^\\d{2}:\\d{2}(:\\d{2})?$' ),
			'event_all_day'    => array( 'type' => 'boolean' ),
			'event_timezone'   => array( 'type' => 'string' ),
			'location_id'      => array( 'type' => 'integer', 'minimum' => 0 ),
		);
		$location_properties = array(
			'location_name'      => array( 'type' => 'string', 'minLength' => 1 ),
			'content'            => array( 'type' => 'string' ),
			'post_status'        => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ) ),
			'location_address'   => array( 'type' => 'string', 'minLength' => 1 ),
			'location_town'      => array( 'type' => 'string', 'minLength' => 1 ),
			'location_state'     => array( 'type' => 'string' ),
			'location_postcode'  => array( 'type' => 'string' ),
			'location_region'    => array( 'type' => 'string' ),
			'location_country'   => array( 'type' => 'string', 'pattern' => '^[A-Za-z]{2}$' ),
			'location_latitude'  => array( 'type' => 'number', 'minimum' => -90, 'maximum' => 90 ),
			'location_longitude' => array( 'type' => 'number', 'minimum' => -180, 'maximum' => 180 ),
		);

		$this->register_ability(
			'create-event',
			'Create ordinary event',
			'Creates one ordinary single Events Manager event. Recurrences, bookings and tickets are outside this ability.',
			$this->object_schema( $event_properties, array( 'event_name', 'event_start_date' ) ),
			function ( $input ) { return $this->mutations->create_event( is_array( $input ) ? $input : array() ); },
			'edit_events'
		);

		$update_event_properties = array_merge(
			array(
				'id'                   => array( 'type' => 'integer', 'minimum' => 1 ),
				'expected_state_token' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
			),
			$event_properties
		);
		$this->register_ability(
			'update-event',
			'Update ordinary event',
			'Conflict-checks and updates one ordinary single Events Manager event, with readback verification and rollback on verification failure.',
			$this->object_schema( $update_event_properties, array( 'id', 'expected_state_token' ) ),
			function ( $input ) { return $this->mutations->update_event( is_array( $input ) ? $input : array() ); },
			'edit_events'
		);

		$this->register_ability(
			'create-location',
			'Create venue location',
			'Creates one physical Events Manager venue/location using a bounded address and coordinate contract.',
			$this->object_schema( $location_properties, array( 'location_name', 'location_address', 'location_town', 'location_country' ) ),
			function ( $input ) { return $this->mutations->create_location( is_array( $input ) ? $input : array() ); },
			'edit_locations'
		);

		$update_location_properties = array_merge(
			array(
				'id'                   => array( 'type' => 'integer', 'minimum' => 1 ),
				'expected_state_token' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
			),
			$location_properties
		);
		$this->register_ability(
			'update-location',
			'Update venue location',
			'Conflict-checks and updates one physical Events Manager venue/location, with readback verification and rollback on verification failure.',
			$this->object_schema( $update_location_properties, array( 'id', 'expected_state_token' ) ),
			function ( $input ) { return $this->mutations->update_location( is_array( $input ) ? $input : array() ); },
			'edit_locations'
		);
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
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
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
