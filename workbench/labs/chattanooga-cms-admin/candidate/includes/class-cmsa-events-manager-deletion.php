<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded two-stage deletion for ordinary single Events Manager events.
 *
 * Soft deletion uses Events Manager's native trash path. Permanent deletion is
 * allowed only from trash, requires explicit confirmation, and refuses events
 * with bookings so an event-only administration action cannot cascade into
 * attendee booking data.
 */
final class CMSA_Events_Manager_Deletion {
	private $events;

	public function __construct( CMSA_Events_Manager $events ) {
		$this->events = $events;
	}

	public function trash_event( array $input ) {
		$event = $this->load_authorized_event( $input );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		$post = $this->event_post( $event );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'trash' === $post->post_status ) {
			return new WP_Error( 'cmsa_event_already_trashed', 'Event is already in trash.' );
		}

		$before = $this->events->normalize_event( $event );
		$conflict = $this->verify_expected_state( $before, $input );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}
		$location_guard = $this->location_guard( $event );
		if ( is_wp_error( $location_guard ) ) {
			return $location_guard;
		}

		$result = $event->delete( false );
		if ( true !== $result ) {
			return new WP_Error( 'cmsa_event_trash_failed', 'Events Manager could not move the event to trash.' );
		}

		$after_event = $this->events->load_event( (int) $before['id'] );
		$after_post = get_post( (int) $before['post_id'] );
		if ( is_wp_error( $after_event ) || ! $after_post instanceof WP_Post || 'trash' !== $after_post->post_status ) {
			return new WP_Error( 'cmsa_event_trash_verify', 'Event trash operation could not be verified.' );
		}
		if ( ! $this->location_guard_unchanged( $location_guard ) ) {
			return new WP_Error( 'cmsa_event_trash_location_changed', 'Referenced venue changed during event trash operation.' );
		}

		$after = $this->events->normalize_event( $after_event );
		CMSA_Audit::record( 'trash-event', 'event:' . $before['id'], 'success', array( 'previous_state_token' => $before['state_token'], 'state_token' => $after['state_token'] ) );
		return array( 'trashed' => true, 'previous_state_token' => $before['state_token'], 'event' => $after );
	}

	public function delete_event( array $input ) {
		$event = $this->load_authorized_event( $input );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		$post = $this->event_post( $event );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'trash' !== $post->post_status ) {
			return new WP_Error( 'cmsa_event_delete_requires_trash', 'Permanent event deletion requires the event to already be in trash.' );
		}

		$before = $this->events->normalize_event( $event );
		$conflict = $this->verify_expected_state( $before, $input );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}
		if ( empty( $input['confirm_permanent_delete'] ) || true !== (bool) $input['confirm_permanent_delete'] ) {
			return new WP_Error( 'cmsa_event_delete_confirmation', 'Permanent event deletion requires explicit confirmation.' );
		}

		$booking_count = $this->booking_count( $event );
		if ( is_wp_error( $booking_count ) ) {
			return $booking_count;
		}
		if ( $booking_count > 0 ) {
			return new WP_Error( 'cmsa_event_delete_has_bookings', 'Permanent event deletion is refused while the event has bookings.', array( 'booking_count' => $booking_count ) );
		}

		$location_guard = $this->location_guard( $event );
		if ( is_wp_error( $location_guard ) ) {
			return $location_guard;
		}
		$receipt = array(
			'id'          => (int) $before['id'],
			'post_id'     => (int) $before['post_id'],
			'name'        => (string) $before['name'],
			'location_id' => (int) $before['location_id'],
			'state_token' => (string) $before['state_token'],
		);

		$result = $event->delete( true );
		if ( true !== $result ) {
			return new WP_Error( 'cmsa_event_delete_failed', 'Events Manager could not permanently delete the event.' );
		}

		$after_event = $this->events->load_event( (int) $before['id'] );
		$after_post = get_post( (int) $before['post_id'] );
		if ( ! is_wp_error( $after_event ) || $after_post instanceof WP_Post ) {
			return new WP_Error( 'cmsa_event_delete_verify', 'Permanent event deletion could not be verified.' );
		}
		if ( ! $this->location_guard_unchanged( $location_guard ) ) {
			return new WP_Error( 'cmsa_event_delete_location_changed', 'Referenced venue changed during permanent event deletion.' );
		}

		CMSA_Audit::record( 'delete-event', 'event:' . $before['id'], 'success', array( 'state_token' => $before['state_token'], 'booking_count' => 0 ) );
		return array( 'deleted' => true, 'event' => $receipt, 'booking_count' => 0 );
	}

	private function load_authorized_event( array $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$event = $this->events->load_event( $id );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( ! $this->is_ordinary_event( $event ) ) {
			return new WP_Error( 'cmsa_event_delete_type', 'Only ordinary single Events Manager events are supported by this deletion contract.' );
		}
		if ( ! current_user_can( 'delete_events' ) || ! method_exists( $event, 'can_manage' ) || ! $event->can_manage( 'delete_events', 'delete_others_events' ) ) {
			return new WP_Error( 'cmsa_event_delete_permission', 'Current user cannot delete this event.' );
		}
		return $event;
	}

	private function event_post( EM_Event $event ) {
		$post_id = isset( $event->post_id ) ? (int) $event->post_id : 0;
		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof WP_Post || 'event' !== $post->post_type ) {
			return new WP_Error( 'cmsa_event_delete_post', 'Event backing WordPress post is unavailable.' );
		}
		return $post;
	}

	private function verify_expected_state( array $current, array $input ) {
		$expected = isset( $input['expected_state_token'] ) ? (string) $input['expected_state_token'] : '';
		if ( '' === $expected || ! hash_equals( (string) $current['state_token'], $expected ) ) {
			return new WP_Error( 'cmsa_event_delete_conflict', 'Event changed after it was read; deletion was not attempted.', array( 'current_state_token' => $current['state_token'] ) );
		}
		return true;
	}

	private function booking_count( EM_Event $event ) {
		if ( ! class_exists( 'EM_Bookings' ) || ! method_exists( 'EM_Bookings', 'count' ) ) {
			return new WP_Error( 'cmsa_event_delete_booking_guard', 'Events Manager booking model is unavailable; permanent deletion was not attempted.' );
		}
		$count = EM_Bookings::count(
			array(
				'event'  => (int) $event->event_id,
				'scope'  => false,
				'status' => false,
				'owner'  => false,
				'limit'  => 0,
			)
		);
		if ( ! is_numeric( $count ) || (int) $count < 0 ) {
			return new WP_Error( 'cmsa_event_delete_booking_guard', 'Event booking state could not be verified; permanent deletion was not attempted.' );
		}
		return (int) $count;
	}

	private function location_guard( EM_Event $event ) {
		$location_id = isset( $event->location_id ) ? (int) $event->location_id : 0;
		if ( $location_id < 1 ) {
			return array( 'id' => 0, 'state_token' => '' );
		}
		$location = $this->events->load_location( $location_id );
		if ( is_wp_error( $location ) ) {
			return new WP_Error( 'cmsa_event_delete_location_guard', 'Referenced venue could not be read before event deletion.' );
		}
		return array( 'id' => $location_id, 'state_token' => $this->events->location_state( $location ) );
	}

	private function location_guard_unchanged( array $guard ) {
		if ( empty( $guard['id'] ) ) {
			return true;
		}
		$location = $this->events->load_location( (int) $guard['id'] );
		return ! is_wp_error( $location ) && hash_equals( (string) $guard['state_token'], $this->events->location_state( $location ) );
	}

	private function is_ordinary_event( EM_Event $event ) {
		$archetype = isset( $event->event_archetype ) && '' !== (string) $event->event_archetype ? (string) $event->event_archetype : 'event';
		$type = isset( $event->event_type ) && '' !== (string) $event->event_type ? (string) $event->event_type : 'single';
		return 'event' === $archetype && 'single' === $type;
	}
}
