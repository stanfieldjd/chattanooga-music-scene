<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded Events Manager event/location reads and stable conflict-state tokens.
 */
final class CMSA_Events_Manager {
	const MAX_PER_PAGE = 100;

	public function list_events( array $input = array() ) {
		if ( ! class_exists( 'EM_Events' ) ) {
			return $this->unavailable();
		}
		$page = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? max( 1, min( self::MAX_PER_PAGE, (int) $input['per_page'] ) ) : 20;
		$args = array(
			'limit'   => $per_page,
			'page'    => $page,
			'scope'   => 'all',
			'orderby' => 'event_start_date,event_start_time',
			'order'   => 'ASC',
		);
		if ( isset( $input['search'] ) && '' !== trim( (string) $input['search'] ) ) {
			$args['search'] = sanitize_text_field( (string) $input['search'] );
		}
		$events = EM_Events::get( $args );
		if ( ! is_array( $events ) ) {
			return new WP_Error( 'cmsa_events_read_failed', 'Events Manager did not return an event collection.' );
		}
		$items = array();
		foreach ( $events as $event ) {
			if ( $event instanceof EM_Event ) {
				$items[] = $this->normalize_event( $event );
			}
		}
		return array( 'page' => $page, 'per_page' => $per_page, 'items' => $items );
	}

	public function get_event( $id ) {
		if ( ! class_exists( 'EM_Event' ) ) {
			return $this->unavailable();
		}
		$event = $this->load_event( $id );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		return array( 'event' => $this->normalize_event( $event ) );
	}

	public function list_locations( array $input = array() ) {
		if ( ! class_exists( 'EM_Locations' ) ) {
			return $this->unavailable();
		}
		$page = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? max( 1, min( self::MAX_PER_PAGE, (int) $input['per_page'] ) ) : 20;
		$args = array( 'limit' => $per_page, 'page' => $page, 'orderby' => 'location_name', 'order' => 'ASC' );
		if ( isset( $input['search'] ) && '' !== trim( (string) $input['search'] ) ) {
			$args['search'] = sanitize_text_field( (string) $input['search'] );
		}
		$locations = EM_Locations::get( $args );
		if ( ! is_array( $locations ) ) {
			return new WP_Error( 'cmsa_locations_read_failed', 'Events Manager did not return a location collection.' );
		}
		$items = array();
		foreach ( $locations as $location ) {
			if ( $location instanceof EM_Location ) {
				$items[] = $this->normalize_location( $location );
			}
		}
		return array( 'page' => $page, 'per_page' => $per_page, 'items' => $items );
	}

	public function get_location( $id ) {
		if ( ! class_exists( 'EM_Location' ) ) {
			return $this->unavailable();
		}
		$location = $this->load_location( $id );
		if ( is_wp_error( $location ) ) {
			return $location;
		}
		return array( 'location' => $this->normalize_location( $location ) );
	}

	public function load_event( $id ) {
		$id = (int) $id;
		if ( $id < 1 || ! class_exists( 'EM_Event' ) ) {
			return new WP_Error( 'cmsa_event_not_found', 'The requested event was not found.' );
		}
		$event = new EM_Event( $id, 'event_id' );
		if ( ! $event instanceof EM_Event || empty( $event->event_id ) || (int) $event->event_id !== $id ) {
			return new WP_Error( 'cmsa_event_not_found', 'The requested event was not found.' );
		}
		return $event;
	}

	public function load_location( $id ) {
		$id = (int) $id;
		if ( $id < 1 || ! class_exists( 'EM_Location' ) ) {
			return new WP_Error( 'cmsa_location_not_found', 'The requested location was not found.' );
		}
		$location = new EM_Location( $id, 'location_id' );
		if ( ! $location instanceof EM_Location || empty( $location->location_id ) || (int) $location->location_id !== $id ) {
			return new WP_Error( 'cmsa_location_not_found', 'The requested location was not found.' );
		}
		return $location;
	}

	public function event_state( EM_Event $event ) {
		return hash( 'sha256', wp_json_encode( $this->event_state_payload( $event ) ) );
	}

	public function location_state( EM_Location $location ) {
		return hash( 'sha256', wp_json_encode( $this->location_state_payload( $location ) ) );
	}

	public function normalize_event( EM_Event $event ) {
		$post = ! empty( $event->post_id ) ? get_post( (int) $event->post_id ) : null;
		return array(
			'id'            => (int) $event->event_id,
			'post_id'       => isset( $event->post_id ) ? (int) $event->post_id : 0,
			'name'          => sanitize_text_field( (string) $event->event_name ),
			'content'       => $post instanceof WP_Post ? (string) $post->post_content : ( isset( $event->post_content ) ? (string) $event->post_content : '' ),
			'post_status'   => $post instanceof WP_Post ? (string) $post->post_status : '',
			'status'        => isset( $event->event_status ) ? (int) $event->event_status : 0,
			'active_status' => isset( $event->event_active_status ) ? (int) $event->event_active_status : 0,
			'start_date'    => sanitize_text_field( (string) $event->event_start_date ),
			'start_time'    => sanitize_text_field( (string) $event->event_start_time ),
			'end_date'      => sanitize_text_field( (string) $event->event_end_date ),
			'end_time'      => sanitize_text_field( (string) $event->event_end_time ),
			'all_day'       => ! empty( $event->event_all_day ),
			'timezone'      => isset( $event->event_timezone ) ? sanitize_text_field( (string) $event->event_timezone ) : '',
			'location_id'   => isset( $event->location_id ) ? (int) $event->location_id : 0,
			'state_token'   => $this->event_state( $event ),
		);
	}

	public function normalize_location( EM_Location $location ) {
		$post = ! empty( $location->post_id ) ? get_post( (int) $location->post_id ) : null;
		return array(
			'id'          => (int) $location->location_id,
			'post_id'     => isset( $location->post_id ) ? (int) $location->post_id : 0,
			'name'        => sanitize_text_field( (string) $location->location_name ),
			'content'     => $post instanceof WP_Post ? (string) $post->post_content : ( isset( $location->post_content ) ? (string) $location->post_content : '' ),
			'post_status' => $post instanceof WP_Post ? (string) $post->post_status : '',
			'address'     => sanitize_text_field( (string) $location->location_address ),
			'town'        => sanitize_text_field( (string) $location->location_town ),
			'state'       => sanitize_text_field( (string) $location->location_state ),
			'postcode'    => sanitize_text_field( (string) $location->location_postcode ),
			'region'      => sanitize_text_field( (string) $location->location_region ),
			'country'     => sanitize_text_field( (string) $location->location_country ),
			'latitude'    => isset( $location->location_latitude ) && '' !== (string) $location->location_latitude ? (float) $location->location_latitude : null,
			'longitude'   => isset( $location->location_longitude ) && '' !== (string) $location->location_longitude ? (float) $location->location_longitude : null,
			'state_token' => $this->location_state( $location ),
		);
	}

	private function event_state_payload( EM_Event $event ) {
		$post = ! empty( $event->post_id ) ? get_post( (int) $event->post_id ) : null;
		return array(
			'id'                  => (int) $event->event_id,
			'post_id'             => isset( $event->post_id ) ? (int) $event->post_id : 0,
			'event_archetype'     => isset( $event->event_archetype ) ? (string) $event->event_archetype : '',
			'event_type'          => isset( $event->event_type ) ? (string) $event->event_type : '',
			'event_name'          => (string) $event->event_name,
			'post_content'        => $post instanceof WP_Post ? (string) $post->post_content : ( isset( $event->post_content ) ? (string) $event->post_content : '' ),
			'post_status'         => $post instanceof WP_Post ? (string) $post->post_status : '',
			'event_status'        => isset( $event->event_status ) ? (int) $event->event_status : 0,
			'event_active_status' => isset( $event->event_active_status ) ? (int) $event->event_active_status : 0,
			'event_private'       => isset( $event->event_private ) ? (int) $event->event_private : 0,
			'event_start_date'    => (string) $event->event_start_date,
			'event_end_date'      => (string) $event->event_end_date,
			'event_start_time'    => (string) $event->event_start_time,
			'event_end_time'      => (string) $event->event_end_time,
			'event_all_day'       => ! empty( $event->event_all_day ),
			'event_timezone'      => isset( $event->event_timezone ) ? (string) $event->event_timezone : '',
			'location_id'         => isset( $event->location_id ) ? (int) $event->location_id : 0,
		);
	}

	private function location_state_payload( EM_Location $location ) {
		$post = ! empty( $location->post_id ) ? get_post( (int) $location->post_id ) : null;
		return array(
			'id'                 => (int) $location->location_id,
			'post_id'            => isset( $location->post_id ) ? (int) $location->post_id : 0,
			'location_name'      => (string) $location->location_name,
			'post_content'       => $post instanceof WP_Post ? (string) $post->post_content : ( isset( $location->post_content ) ? (string) $location->post_content : '' ),
			'post_status'        => $post instanceof WP_Post ? (string) $post->post_status : '',
			'location_address'   => (string) $location->location_address,
			'location_town'      => (string) $location->location_town,
			'location_state'     => (string) $location->location_state,
			'location_postcode'  => (string) $location->location_postcode,
			'location_region'    => (string) $location->location_region,
			'location_country'   => (string) $location->location_country,
			'location_latitude'  => isset( $location->location_latitude ) ? (string) $location->location_latitude : '',
			'location_longitude' => isset( $location->location_longitude ) ? (string) $location->location_longitude : '',
		);
	}

	private function unavailable() {
		return new WP_Error( 'cmsa_events_manager_unavailable', 'Events Manager is not available in this WordPress runtime.' );
	}
}
