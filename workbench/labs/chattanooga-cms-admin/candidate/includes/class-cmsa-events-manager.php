<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded Events Manager event/location reads.
 *
 * This adapter uses Events Manager's public model/helper layer and returns a
 * deliberately small field projection rather than exposing raw plugin objects.
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
		if ( ! function_exists( 'em_get_event' ) ) {
			return $this->unavailable();
		}
		$event = em_get_event( (int) $id );
		if ( ! $event instanceof EM_Event || empty( $event->event_id ) ) {
			return new WP_Error( 'cmsa_event_not_found', 'The requested event was not found.' );
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
		if ( ! function_exists( 'em_get_location' ) ) {
			return $this->unavailable();
		}
		$location = em_get_location( (int) $id );
		if ( ! $location instanceof EM_Location || empty( $location->location_id ) ) {
			return new WP_Error( 'cmsa_location_not_found', 'The requested location was not found.' );
		}
		return array( 'location' => $this->normalize_location( $location ) );
	}

	private function normalize_event( EM_Event $event ) {
		return array(
			'id'         => (int) $event->event_id,
			'name'       => sanitize_text_field( (string) $event->event_name ),
			'status'     => (int) $event->event_status,
			'start_date' => sanitize_text_field( (string) $event->event_start_date ),
			'start_time' => sanitize_text_field( (string) $event->event_start_time ),
			'end_date'   => sanitize_text_field( (string) $event->event_end_date ),
			'end_time'   => sanitize_text_field( (string) $event->event_end_time ),
			'location_id'=> isset( $event->location_id ) ? (int) $event->location_id : 0,
			'post_id'    => isset( $event->post_id ) ? (int) $event->post_id : 0,
		);
	}

	private function normalize_location( EM_Location $location ) {
		return array(
			'id'       => (int) $location->location_id,
			'name'     => sanitize_text_field( (string) $location->location_name ),
			'address'  => sanitize_text_field( (string) $location->location_address ),
			'town'     => sanitize_text_field( (string) $location->location_town ),
			'state'    => sanitize_text_field( (string) $location->location_state ),
			'postcode' => sanitize_text_field( (string) $location->location_postcode ),
			'region'   => sanitize_text_field( (string) $location->location_region ),
			'country'  => sanitize_text_field( (string) $location->location_country ),
			'post_id'  => isset( $location->post_id ) ? (int) $location->post_id : 0,
		);
	}

	private function unavailable() {
		return new WP_Error( 'cmsa_events_manager_unavailable', 'Events Manager is not available in this WordPress runtime.' );
	}
}
