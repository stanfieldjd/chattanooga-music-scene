<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Events Manager status transitions update wp_posts directly. Keep the
 * WordPress object cache coherent before Chattanooga CMS Admin readback.
 */
final class CMSA_Events_Manager_Cache {
	public static function register() {
		add_filter( 'em_event_set_status', array( __CLASS__, 'clean_event_post_cache' ), 10, 3 );
		add_filter( 'em_location_set_status', array( __CLASS__, 'clean_location_post_cache' ), 10, 3 );
	}

	public static function clean_event_post_cache( $result, $status, $event ) {
		if ( $event instanceof EM_Event && ! empty( $event->post_id ) ) {
			clean_post_cache( (int) $event->post_id );
		}
		return $result;
	}

	public static function clean_location_post_cache( $result, $status, $location ) {
		if ( $location instanceof EM_Location && ! empty( $location->post_id ) ) {
			clean_post_cache( (int) $location->post_id );
		}
		return $result;
	}
}
