<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded ordinary-event and physical-location mutations for Events Manager.
 * Recurrence, booking, ticket, payment, media and deletion operations are
 * intentionally outside this service.
 */
final class CMSA_Events_Manager_Mutations {
	private $events;

	public function __construct( CMSA_Events_Manager $events ) {
		$this->events = $events;
	}

	public function create_event( array $input ) {
		if ( ! class_exists( 'EM_Event' ) ) {
			return $this->unavailable();
		}
		if ( ! current_user_can( 'edit_events' ) ) {
			return new WP_Error( 'cmsa_event_create_permission', 'Current user cannot create Events Manager events.' );
		}
		$target = $this->prepare_event_target( array(), $input, true );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		if ( 'publish' === $target['post_status'] && ! current_user_can( 'publish_events' ) ) {
			return new WP_Error( 'cmsa_event_publish_permission', 'Current user cannot publish Events Manager events.' );
		}

		$event = new EM_Event();
		$event->event_owner = get_current_user_id();
		$this->apply_event_target( $event, $target, true );
		if ( ! $event->save() || empty( $event->event_id ) ) {
			return new WP_Error( 'cmsa_event_create_failed', 'Events Manager could not create the event.', array( 'cleaned_up' => $this->cleanup_created_event( $event ) ) );
		}
		if ( ! $this->ensure_event_post_status( $event, $target['post_status'] ) ) {
			return new WP_Error( 'cmsa_event_create_status', 'Events Manager could not apply the requested event publication state.', array( 'cleaned_up' => $this->cleanup_created_event( $event ) ) );
		}

		do_action( 'cmsa_events_manager_event_written', (int) $event->event_id, 'create' );
		$after = $this->events->load_event( $event->event_id );
		if ( is_wp_error( $after ) || ! $this->is_ordinary_event( $after ) || ! $this->event_matches( $after, $target ) ) {
			return new WP_Error( 'cmsa_event_create_verify', 'Created event did not pass readback verification.', array( 'cleaned_up' => $this->cleanup_created_event( $event ) ) );
		}
		$normalized = $this->events->normalize_event( $after );
		CMSA_Audit::record( 'create-event', (string) $after->event_id, 'success', array( 'state_token' => $normalized['state_token'] ) );
		return array( 'created' => true, 'event' => $normalized );
	}

	public function update_event( array $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$event = $this->events->load_event( $id );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( ! $this->is_ordinary_event( $event ) ) {
			return new WP_Error( 'cmsa_event_type_unsupported', 'This ability only updates ordinary single Events Manager events.' );
		}
		if ( ! $event->can_manage( 'edit_events', 'edit_others_events' ) ) {
			return new WP_Error( 'cmsa_event_update_permission', 'Current user cannot edit this Events Manager event.' );
		}
		$expected = isset( $input['expected_state_token'] ) ? sanitize_text_field( (string) $input['expected_state_token'] ) : '';
		$current = $this->events->event_state( $event );
		if ( '' === $expected ) {
			return new WP_Error( 'cmsa_event_expected_state', 'Expected event state token is required.' );
		}
		if ( ! hash_equals( $current, $expected ) ) {
			return new WP_Error( 'cmsa_event_conflict', 'Event changed after it was read; update was not attempted.', array( 'current_state_token' => $current ) );
		}

		$previous = $this->event_snapshot( $event );
		$target = $this->prepare_event_target( $previous, $input, false );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		if ( $target === $previous ) {
			return new WP_Error( 'cmsa_event_no_change', 'No event change was requested.' );
		}
		if ( 'publish' === $target['post_status'] && 'publish' !== $previous['post_status'] && ! current_user_can( 'publish_events' ) ) {
			return new WP_Error( 'cmsa_event_publish_permission', 'Current user cannot publish Events Manager events.' );
		}

		$this->apply_event_target( $event, $target, false );
		if ( ! $event->save() || ! $this->ensure_event_post_status( $event, $target['post_status'] ) ) {
			return new WP_Error( 'cmsa_event_update_failed', 'Events Manager could not update the event.', array( 'rolled_back' => $this->restore_event( $id, $previous ) ) );
		}
		do_action( 'cmsa_events_manager_event_written', $id, 'update' );
		$after = $this->events->load_event( $id );
		if ( is_wp_error( $after ) || ! $this->is_ordinary_event( $after ) || ! $this->event_matches( $after, $target ) ) {
			return new WP_Error( 'cmsa_event_update_verify', 'Updated event did not pass readback verification.', array( 'rolled_back' => $this->restore_event( $id, $previous ) ) );
		}
		$normalized = $this->events->normalize_event( $after );
		CMSA_Audit::record( 'update-event', (string) $id, 'success', array( 'state_token' => $normalized['state_token'] ) );
		return array( 'updated' => true, 'event' => $normalized );
	}

	public function create_location( array $input ) {
		if ( ! class_exists( 'EM_Location' ) ) {
			return $this->unavailable();
		}
		if ( ! current_user_can( 'edit_locations' ) ) {
			return new WP_Error( 'cmsa_location_create_permission', 'Current user cannot create Events Manager locations.' );
		}
		$target = $this->prepare_location_target( array(), $input, true );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		if ( 'publish' === $target['post_status'] && ! current_user_can( 'publish_locations' ) ) {
			return new WP_Error( 'cmsa_location_publish_permission', 'Current user cannot publish Events Manager locations.' );
		}

		$location = new EM_Location();
		$location->location_owner = get_current_user_id();
		$this->apply_location_target( $location, $target );
		if ( ! $location->save() || empty( $location->location_id ) ) {
			return new WP_Error( 'cmsa_location_create_failed', 'Events Manager could not create the location.', array( 'cleaned_up' => $this->cleanup_created_location( $location ) ) );
		}
		if ( ! $this->ensure_location_post_status( $location, $target['post_status'] ) ) {
			return new WP_Error( 'cmsa_location_create_status', 'Events Manager could not apply the requested location publication state.', array( 'cleaned_up' => $this->cleanup_created_location( $location ) ) );
		}

		do_action( 'cmsa_events_manager_location_written', (int) $location->location_id, 'create' );
		$after = $this->events->load_location( $location->location_id );
		if ( is_wp_error( $after ) || ! $this->location_matches( $after, $target ) ) {
			return new WP_Error( 'cmsa_location_create_verify', 'Created location did not pass readback verification.', array( 'cleaned_up' => $this->cleanup_created_location( $location ) ) );
		}
		$normalized = $this->events->normalize_location( $after );
		CMSA_Audit::record( 'create-location', (string) $after->location_id, 'success', array( 'state_token' => $normalized['state_token'] ) );
		return array( 'created' => true, 'location' => $normalized );
	}

	public function update_location( array $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$location = $this->events->load_location( $id );
		if ( is_wp_error( $location ) ) {
			return $location;
		}
		if ( ! $location->can_manage( 'edit_locations', 'edit_others_locations' ) ) {
			return new WP_Error( 'cmsa_location_update_permission', 'Current user cannot edit this Events Manager location.' );
		}
		$expected = isset( $input['expected_state_token'] ) ? sanitize_text_field( (string) $input['expected_state_token'] ) : '';
		$current = $this->events->location_state( $location );
		if ( '' === $expected ) {
			return new WP_Error( 'cmsa_location_expected_state', 'Expected location state token is required.' );
		}
		if ( ! hash_equals( $current, $expected ) ) {
			return new WP_Error( 'cmsa_location_conflict', 'Location changed after it was read; update was not attempted.', array( 'current_state_token' => $current ) );
		}

		$previous = $this->location_snapshot( $location );
		$target = $this->prepare_location_target( $previous, $input, false );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		if ( $target === $previous ) {
			return new WP_Error( 'cmsa_location_no_change', 'No location change was requested.' );
		}
		if ( 'publish' === $target['post_status'] && 'publish' !== $previous['post_status'] && ! current_user_can( 'publish_locations' ) ) {
			return new WP_Error( 'cmsa_location_publish_permission', 'Current user cannot publish Events Manager locations.' );
		}

		$this->apply_location_target( $location, $target );
		if ( ! $location->save() || ! $this->ensure_location_post_status( $location, $target['post_status'] ) ) {
			return new WP_Error( 'cmsa_location_update_failed', 'Events Manager could not update the location.', array( 'rolled_back' => $this->restore_location( $id, $previous ) ) );
		}
		do_action( 'cmsa_events_manager_location_written', $id, 'update' );
		$after = $this->events->load_location( $id );
		if ( is_wp_error( $after ) || ! $this->location_matches( $after, $target ) ) {
			return new WP_Error( 'cmsa_location_update_verify', 'Updated location did not pass readback verification.', array( 'rolled_back' => $this->restore_location( $id, $previous ) ) );
		}
		$normalized = $this->events->normalize_location( $after );
		CMSA_Audit::record( 'update-location', (string) $id, 'success', array( 'state_token' => $normalized['state_token'] ) );
		return array( 'updated' => true, 'location' => $normalized );
	}

	private function prepare_event_target( array $base, array $input, $creating ) {
		$target = $creating ? array(
			'event_name' => '', 'post_content' => '', 'post_status' => 'draft',
			'event_start_date' => '', 'event_end_date' => '',
			'event_start_time' => '00:00:00', 'event_end_time' => '00:00:00',
			'event_all_day' => false, 'event_timezone' => wp_timezone_string(), 'location_id' => 0,
		) : $base;
		$fields = array(
			'event_name' => 'event_name', 'content' => 'post_content', 'post_status' => 'post_status',
			'event_start_date' => 'event_start_date', 'event_end_date' => 'event_end_date',
			'event_timezone' => 'event_timezone', 'location_id' => 'location_id',
		);
		foreach ( $fields as $input_key => $target_key ) {
			if ( ! array_key_exists( $input_key, $input ) ) {
				continue;
			}
			if ( 'content' === $input_key ) {
				$target[ $target_key ] = wp_kses_post( (string) $input[ $input_key ] );
			} elseif ( 'location_id' === $input_key ) {
				$target[ $target_key ] = max( 0, (int) $input[ $input_key ] );
			} elseif ( 'post_status' === $input_key ) {
				$target[ $target_key ] = sanitize_key( (string) $input[ $input_key ] );
			} else {
				$target[ $target_key ] = sanitize_text_field( (string) $input[ $input_key ] );
			}
		}
		foreach ( array( 'event_start_time', 'event_end_time' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$value = $this->normalize_time( $input[ $key ] );
				if ( is_wp_error( $value ) ) {
					return $value;
				}
				$target[ $key ] = $value;
			}
		}
		if ( array_key_exists( 'event_all_day', $input ) ) {
			$target['event_all_day'] = (bool) $input['event_all_day'];
		}

		if ( '' === $target['event_name'] ) {
			return new WP_Error( 'cmsa_event_name_required', 'Event name is required.' );
		}
		if ( ! in_array( $target['post_status'], array( 'draft', 'pending', 'publish' ), true ) ) {
			return new WP_Error( 'cmsa_event_post_status', 'Event post status must be draft, pending, or publish.' );
		}
		if ( ! $this->valid_date( $target['event_start_date'] ) ) {
			return new WP_Error( 'cmsa_event_start_date', 'Event start date must use YYYY-MM-DD.' );
		}
		if ( '' === $target['event_end_date'] ) {
			$target['event_end_date'] = $target['event_start_date'];
		}
		if ( ! $this->valid_date( $target['event_end_date'] ) || $target['event_end_date'] < $target['event_start_date'] ) {
			return new WP_Error( 'cmsa_event_end_date', 'Event end date must be a valid date on or after the start date.' );
		}
		if ( $target['event_all_day'] ) {
			$target['event_start_time'] = '00:00:00';
			$target['event_end_time'] = '00:00:00';
		} elseif ( $target['event_start_date'] === $target['event_end_date'] && $target['event_end_time'] < $target['event_start_time'] ) {
			return new WP_Error( 'cmsa_event_time_order', 'For a same-day event, end time cannot precede start time.' );
		}
		if ( '' === $target['event_timezone'] || ! $this->valid_timezone( $target['event_timezone'] ) ) {
			return new WP_Error( 'cmsa_event_timezone', 'Event timezone must be a valid timezone identifier or UTC offset.' );
		}
		if ( $target['location_id'] > 0 && is_wp_error( $this->events->load_location( $target['location_id'] ) ) ) {
			return new WP_Error( 'cmsa_event_location_not_found', 'The requested event location was not found.' );
		}
		return $target;
	}

	private function prepare_location_target( array $base, array $input, $creating ) {
		$target = $creating ? array(
			'location_name' => '', 'post_content' => '', 'post_status' => 'draft',
			'location_address' => '', 'location_town' => '', 'location_state' => '',
			'location_postcode' => '', 'location_region' => '', 'location_country' => '',
			'location_latitude' => '0', 'location_longitude' => '0',
		) : $base;
		$fields = array(
			'location_name' => 'location_name', 'content' => 'post_content', 'post_status' => 'post_status',
			'location_address' => 'location_address', 'location_town' => 'location_town',
			'location_state' => 'location_state', 'location_postcode' => 'location_postcode',
			'location_region' => 'location_region', 'location_country' => 'location_country',
		);
		foreach ( $fields as $input_key => $target_key ) {
			if ( ! array_key_exists( $input_key, $input ) ) {
				continue;
			}
			$value = 'content' === $input_key ? wp_kses_post( (string) $input[ $input_key ] ) : sanitize_text_field( (string) $input[ $input_key ] );
			if ( 'post_status' === $input_key ) {
				$value = sanitize_key( $value );
			} elseif ( 'location_country' === $input_key ) {
				$value = strtoupper( $value );
			}
			$target[ $target_key ] = $value;
		}
		foreach ( array( 'location_latitude', 'location_longitude' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$target[ $key ] = $this->coordinate_string( $input[ $key ] );
			}
		}
		if ( '' === $target['location_name'] ) {
			return new WP_Error( 'cmsa_location_name_required', 'Location name is required.' );
		}
		if ( '' === $target['location_address'] ) {
			return new WP_Error( 'cmsa_location_address_required', 'Location street address is required.' );
		}
		if ( '' === $target['location_town'] ) {
			return new WP_Error( 'cmsa_location_town_required', 'Location town or city is required.' );
		}
		if ( ! preg_match( '/^[A-Z]{2}$/', $target['location_country'] ) ) {
			return new WP_Error( 'cmsa_location_country', 'Location country must be a two-letter ISO country code.' );
		}
		if ( ! in_array( $target['post_status'], array( 'draft', 'pending', 'publish' ), true ) ) {
			return new WP_Error( 'cmsa_location_post_status', 'Location post status must be draft, pending, or publish.' );
		}
		if ( '' !== $target['location_latitude'] && ( (float) $target['location_latitude'] < -90 || (float) $target['location_latitude'] > 90 ) ) {
			return new WP_Error( 'cmsa_location_latitude', 'Location latitude must be between -90 and 90.' );
		}
		if ( '' !== $target['location_longitude'] && ( (float) $target['location_longitude'] < -180 || (float) $target['location_longitude'] > 180 ) ) {
			return new WP_Error( 'cmsa_location_longitude', 'Location longitude must be between -180 and 180.' );
		}
		return $target;
	}

	private function event_snapshot( EM_Event $event ) {
		$post = ! empty( $event->post_id ) ? get_post( (int) $event->post_id ) : null;
		return array(
			'event_name' => (string) $event->event_name,
			'post_content' => $post instanceof WP_Post ? (string) $post->post_content : ( isset( $event->post_content ) ? (string) $event->post_content : '' ),
			'post_status' => $post instanceof WP_Post ? (string) $post->post_status : 'draft',
			'event_start_date' => (string) $event->event_start_date,
			'event_end_date' => (string) $event->event_end_date,
			'event_start_time' => (string) $event->event_start_time,
			'event_end_time' => (string) $event->event_end_time,
			'event_all_day' => ! empty( $event->event_all_day ),
			'event_timezone' => isset( $event->event_timezone ) ? (string) $event->event_timezone : '',
			'location_id' => isset( $event->location_id ) ? (int) $event->location_id : 0,
		);
	}

	private function location_snapshot( EM_Location $location ) {
		$post = ! empty( $location->post_id ) ? get_post( (int) $location->post_id ) : null;
		return array(
			'location_name' => (string) $location->location_name,
			'post_content' => $post instanceof WP_Post ? (string) $post->post_content : ( isset( $location->post_content ) ? (string) $location->post_content : '' ),
			'post_status' => $post instanceof WP_Post ? (string) $post->post_status : 'draft',
			'location_address' => (string) $location->location_address,
			'location_town' => (string) $location->location_town,
			'location_state' => (string) $location->location_state,
			'location_postcode' => (string) $location->location_postcode,
			'location_region' => (string) $location->location_region,
			'location_country' => (string) $location->location_country,
			'location_latitude' => $this->coordinate_string( isset( $location->location_latitude ) ? $location->location_latitude : '' ),
			'location_longitude' => $this->coordinate_string( isset( $location->location_longitude ) ? $location->location_longitude : '' ),
		);
	}

	private function apply_event_target( EM_Event $event, array $target, $creating ) {
		if ( $creating ) {
			$event->event_archetype = 'event';
			$event->event_type = 'single';
			$event->event_active_status = 1;
			$event->event_rsvp = 0;
			$event->event_private = 0;
		}
		$event->event_name = $target['event_name'];
		$event->post_content = $target['post_content'];
		$event->post_status = $target['post_status'];
		$event->event_start_date = $target['event_start_date'];
		$event->event_end_date = $target['event_end_date'];
		$event->event_start_time = $target['event_start_time'];
		$event->event_end_time = $target['event_end_time'];
		$event->event_all_day = $target['event_all_day'] ? 1 : 0;
		$event->event_timezone = $target['event_timezone'];
		$event->location_id = $target['location_id'];
	}

	private function apply_location_target( EM_Location $location, array $target ) {
		foreach ( array( 'location_name', 'post_content', 'post_status', 'location_address', 'location_town', 'location_state', 'location_postcode', 'location_region', 'location_country', 'location_latitude', 'location_longitude' ) as $key ) {
			$location->$key = $target[ $key ];
		}
	}

	private function ensure_event_post_status( EM_Event $event, $status ) {
		if ( empty( $event->post_id ) ) {
			return false;
		}
		$post = get_post( (int) $event->post_id );
		if ( $post instanceof WP_Post && $status === (string) $post->post_status ) {
			return true;
		}
		$result = 'draft' === $status ? $event->set_status( null, true ) : $event->set_status( 'publish' === $status ? 1 : 0, true );
		$post = get_post( (int) $event->post_id );
		return false !== $result && $post instanceof WP_Post && $status === (string) $post->post_status;
	}

	private function ensure_location_post_status( EM_Location $location, $status ) {
		if ( empty( $location->post_id ) ) {
			return false;
		}
		$post = get_post( (int) $location->post_id );
		if ( $post instanceof WP_Post && $status === (string) $post->post_status ) {
			return true;
		}
		$result = 'draft' === $status ? $location->set_status( null, true ) : $location->set_status( 'publish' === $status ? 1 : 0, true );
		$post = get_post( (int) $location->post_id );
		return false !== $result && $post instanceof WP_Post && $status === (string) $post->post_status;
	}

	private function event_matches( EM_Event $event, array $target ) {
		return $this->event_snapshot( $event ) === $target;
	}

	private function location_matches( EM_Location $location, array $target ) {
		return $this->location_snapshot( $location ) === $target;
	}

	private function restore_event( $id, array $previous ) {
		$event = $this->events->load_event( $id );
		if ( is_wp_error( $event ) ) {
			return false;
		}
		$this->apply_event_target( $event, $previous, false );
		if ( ! $event->save() || ! $this->ensure_event_post_status( $event, $previous['post_status'] ) ) {
			return false;
		}
		$restored = $this->events->load_event( $id );
		return ! is_wp_error( $restored ) && $this->event_matches( $restored, $previous );
	}

	private function restore_location( $id, array $previous ) {
		$location = $this->events->load_location( $id );
		if ( is_wp_error( $location ) ) {
			return false;
		}
		$this->apply_location_target( $location, $previous );
		if ( ! $location->save() || ! $this->ensure_location_post_status( $location, $previous['post_status'] ) ) {
			return false;
		}
		$restored = $this->events->load_location( $id );
		return ! is_wp_error( $restored ) && $this->location_matches( $restored, $previous );
	}

	private function cleanup_created_event( $event ) {
		if ( ! $event instanceof EM_Event || empty( $event->event_id ) ) {
			return true;
		}
		$id = (int) $event->event_id;
		$event->delete( true );
		return is_wp_error( $this->events->load_event( $id ) );
	}

	private function cleanup_created_location( $location ) {
		if ( ! $location instanceof EM_Location || empty( $location->location_id ) ) {
			return true;
		}
		$id = (int) $location->location_id;
		$location->delete( true );
		return is_wp_error( $this->events->load_location( $id ) );
	}

	private function is_ordinary_event( EM_Event $event ) {
		return 'event' === ( isset( $event->event_archetype ) ? (string) $event->event_archetype : 'event' )
			&& 'single' === ( isset( $event->event_type ) ? (string) $event->event_type : 'single' );
	}

	private function valid_date( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}
		$parts = array_map( 'intval', explode( '-', $value ) );
		return 3 === count( $parts ) && checkdate( $parts[1], $parts[2], $parts[0] );
	}

	private function valid_timezone( $value ) {
		try {
			new DateTimeZone( (string) $value );
			return true;
		} catch ( Exception $error ) {
			return false;
		}
	}

	private function normalize_time( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d{2}):(\d{2})$/', $value ) ) {
			$value .= ':00';
		}
		if ( ! preg_match( '/^(\d{2}):(\d{2}):(\d{2})$/', $value, $matches ) || (int) $matches[1] > 23 || (int) $matches[2] > 59 || (int) $matches[3] > 59 ) {
			return new WP_Error( 'cmsa_event_time', 'Event time must use valid 24-hour HH:MM or HH:MM:SS.' );
		}
		return sprintf( '%02d:%02d:%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3] );
	}

	private function coordinate_string( $value ) {
		if ( null === $value || '' === (string) $value ) {
			return '';
		}
		$value = rtrim( rtrim( sprintf( '%.8F', (float) $value ), '0' ), '.' );
		return '-0' === $value ? '0' : $value;
	}

	private function unavailable() {
		return new WP_Error( 'cmsa_events_manager_unavailable', 'Events Manager is not available in this WordPress runtime.' );
	}
}
