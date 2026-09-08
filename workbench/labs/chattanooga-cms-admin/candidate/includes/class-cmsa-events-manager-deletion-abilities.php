<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Events_Manager_Deletion_Abilities {
	private $deletion;

	public function __construct( CMSA_Events_Manager_Deletion $deletion ) {
		$this->deletion = $deletion;
	}

	public function register() {
		$this->register_ability(
			'trash-event',
			'Trash event',
			'Moves one ordinary single Events Manager event to WordPress trash after an exact event-state conflict check. The referenced venue and booking data are not changed.',
			$this->object_schema(
				array(
					'id'                   => array( 'type' => 'integer', 'minimum' => 1 ),
					'expected_state_token' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
				),
				array( 'id', 'expected_state_token' )
			),
			function ( $input ) { return $this->deletion->trash_event( $input ); },
			false,
			false,
			false
		);

		$this->register_ability(
			'restore-event',
			'Restore event',
			'Restores one ordinary single Events Manager event from WordPress trash to draft after an exact event-state conflict check. Restoration never republishes the event; the referenced venue and booking data are preserved.',
			$this->object_schema(
				array(
					'id'                   => array( 'type' => 'integer', 'minimum' => 1 ),
					'expected_state_token' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
				),
				array( 'id', 'expected_state_token' )
			),
			function ( $input ) { return $this->deletion->restore_event( $input ); },
			false,
			false,
			false
		);

		$this->register_ability(
			'delete-event',
			'Permanently delete event',
			'Permanently deletes one ordinary single Events Manager event only after it is already in trash, the exact trashed event state still matches, explicit permanent-delete confirmation is supplied, and zero bookings are present. The referenced venue is preserved.',
			$this->object_schema(
				array(
					'id'                       => array( 'type' => 'integer', 'minimum' => 1 ),
					'expected_state_token'     => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
					'confirm_permanent_delete' => array( 'type' => 'boolean' ),
				),
				array( 'id', 'expected_state_token', 'confirm_permanent_delete' )
			),
			function ( $input ) { return $this->deletion->delete_event( $input ); },
			false,
			true,
			false
		);
	}

	private function register_ability( $slug, $label, $description, $input_schema, $callback, $readonly, $destructive, $idempotent ) {
		$args = array(
			'label'               => __( $label, 'chattanooga-cms-admin' ),
			'description'         => __( $description, 'chattanooga-cms-admin' ),
			'category'            => 'chattanooga-cms-admin',
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => $callback,
			'permission_callback' => static function () {
				return current_user_can( 'delete_events' );
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
		);
		if ( null !== $input_schema ) {
			$args['input_schema'] = $input_schema;
		}
		wp_register_ability( 'chattanooga-cms-admin/' . $slug, $args );
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
