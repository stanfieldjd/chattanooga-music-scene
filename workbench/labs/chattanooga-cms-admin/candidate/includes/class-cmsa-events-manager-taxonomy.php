<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded event category/tag reads and exact relationship replacement for
 * ordinary single Events Manager events. Term lifecycle is intentionally
 * outside this service.
 */
final class CMSA_Events_Manager_Taxonomy {
	private $events;
	private $allowed_taxonomies = array( 'event-categories', 'event-tags' );

	public function __construct( CMSA_Events_Manager $events ) {
		$this->events = $events;
	}

	public function list_terms( $taxonomy, array $input = array() ) {
		$tax = $this->taxonomy_object( $taxonomy );
		if ( is_wp_error( $tax ) ) {
			return $tax;
		}
		if ( ! current_user_can( $tax->cap->assign_terms ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_read_permission', 'Current user cannot inspect this event taxonomy.' );
		}

		$page = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 50;
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'orderby'    => 'term_id',
			'order'      => 'ASC',
		);
		if ( isset( $input['search'] ) && '' !== trim( (string) $input['search'] ) ) {
			$args['search'] = sanitize_text_field( (string) $input['search'] );
		}
		if ( $tax->hierarchical && isset( $input['parent'] ) ) {
			$args['parent'] = max( 0, (int) $input['parent'] );
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_list', 'Could not list event taxonomy terms.' );
		}
		$count_args = $args;
		unset( $count_args['number'], $count_args['offset'], $count_args['orderby'], $count_args['order'] );
		$count_args['fields'] = 'count';
		$total = get_terms( $count_args );
		if ( is_wp_error( $total ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_count', 'Could not count event taxonomy terms.' );
		}

		$items = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$items[] = $this->normalize_term( $term );
			}
		}
		return array(
			'taxonomy' => $taxonomy,
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => (int) $total,
			'items'    => $items,
		);
	}

	public function get_event_terms( $taxonomy, $event_id ) {
		$tax = $this->taxonomy_object( $taxonomy );
		if ( is_wp_error( $tax ) ) {
			return $tax;
		}
		$event = $this->load_managed_event( $event_id, false );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( ! current_user_can( $tax->cap->assign_terms ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_read_permission', 'Current user cannot inspect this event taxonomy.' );
		}
		$term_ids = $this->object_term_ids( (int) $event->post_id, $taxonomy );
		if ( is_wp_error( $term_ids ) ) {
			return $term_ids;
		}
		return array(
			'taxonomy'         => $taxonomy,
			'event_id'         => (int) $event->event_id,
			'event_state_token'=> $this->events->event_state( $event ),
			'term_ids'         => $term_ids,
		);
	}

	public function set_event_terms( $taxonomy, array $input ) {
		$tax = $this->taxonomy_object( $taxonomy );
		if ( is_wp_error( $tax ) ) {
			return $tax;
		}
		$event_id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$event = $this->load_managed_event( $event_id, true );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( ! current_user_can( $tax->cap->assign_terms ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_assign_permission', 'Current user cannot assign this event taxonomy.' );
		}

		$expected_event_state = isset( $input['expected_event_state_token'] ) ? sanitize_text_field( (string) $input['expected_event_state_token'] ) : '';
		$current_event_state = $this->events->event_state( $event );
		if ( '' === $expected_event_state ) {
			return new WP_Error( 'cmsa_event_taxonomy_expected_event_state', 'Expected event state token is required.' );
		}
		if ( ! hash_equals( $current_event_state, $expected_event_state ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_event_conflict', 'Event changed after it was read; taxonomy mutation was not attempted.', array( 'current_event_state_token' => $current_event_state ) );
		}

		$current = $this->object_term_ids( (int) $event->post_id, $taxonomy );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$expected = isset( $input['expected_term_ids'] ) && is_array( $input['expected_term_ids'] ) ? $this->normalize_ids( $input['expected_term_ids'] ) : array();
		if ( $expected !== $current ) {
			return new WP_Error( 'cmsa_event_taxonomy_relationship_conflict', 'Event taxonomy relationships changed after they were read; mutation was not attempted.', array( 'current_term_ids' => $current ) );
		}
		$target = isset( $input['term_ids'] ) && is_array( $input['term_ids'] ) ? $this->normalize_ids( $input['term_ids'] ) : array();
		foreach ( $target as $term_id ) {
			if ( ! term_exists( $term_id, $taxonomy ) ) {
				return new WP_Error( 'cmsa_event_taxonomy_term', 'One or more requested event taxonomy terms do not exist.' );
			}
		}
		if ( $target === $current ) {
			return new WP_Error( 'cmsa_event_taxonomy_no_change', 'Requested event taxonomy state already matches the current relationship set.' );
		}

		$location_guard = $this->capture_location_state( $event );
		$result = wp_set_object_terms( (int) $event->post_id, $target, $taxonomy, false );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_update', 'Could not update event taxonomy relationships.' );
		}

		do_action( 'cmsa_events_manager_taxonomy_written', $event_id, $taxonomy );

		$after_event = $this->events->load_event( $event_id );
		$after_terms = $this->object_term_ids( (int) $event->post_id, $taxonomy );
		$event_preserved = ! is_wp_error( $after_event ) && hash_equals( $current_event_state, $this->events->event_state( $after_event ) );
		$location_preserved = $this->location_state_matches( $location_guard );
		if ( is_wp_error( $after_terms ) || $after_terms !== $target || ! $event_preserved || ! $location_preserved ) {
			$rollback_result = wp_set_object_terms( (int) $event->post_id, $current, $taxonomy, false );
			$rollback_state = $this->object_term_ids( (int) $event->post_id, $taxonomy );
			$rolled_back = ! is_wp_error( $rollback_result ) && ! is_wp_error( $rollback_state ) && $rollback_state === $current;
			return new WP_Error(
				'cmsa_event_taxonomy_verify',
				'Event taxonomy update verification failed.',
				array(
					'rolled_back'        => $rolled_back,
					'previous_term_ids'  => $current,
					'event_preserved'    => $event_preserved,
					'location_preserved' => $location_preserved,
				)
			);
		}

		CMSA_Audit::record(
			'set-event-taxonomy',
			$event_id . ':' . $taxonomy,
			'success',
			array( 'previous_term_ids' => $current, 'term_ids' => $after_terms )
		);
		return array(
			'updated'            => true,
			'taxonomy'           => $taxonomy,
			'event_id'           => $event_id,
			'event_state_token'  => $current_event_state,
			'previous_term_ids'  => $current,
			'term_ids'           => $after_terms,
		);
	}

	private function taxonomy_object( $taxonomy ) {
		$taxonomy = sanitize_key( $taxonomy );
		if ( ! in_array( $taxonomy, $this->allowed_taxonomies, true ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_not_allowed', 'Only Events Manager event categories and event tags are supported.' );
		}
		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax instanceof WP_Taxonomy || ! in_array( 'event', (array) $tax->object_type, true ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_unavailable', 'Requested Events Manager event taxonomy is not registered for events.' );
		}
		return $tax;
	}

	private function load_managed_event( $event_id, $reject_trash ) {
		$event = $this->events->load_event( $event_id );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( ! $this->is_ordinary_event( $event ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_type_unsupported', 'This ability only manages taxonomy for ordinary single Events Manager events.' );
		}
		if ( empty( $event->post_id ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_post_missing', 'Events Manager event does not have a backing WordPress event post.' );
		}
		$post = get_post( (int) $event->post_id );
		if ( ! $post instanceof WP_Post || 'event' !== $post->post_type ) {
			return new WP_Error( 'cmsa_event_taxonomy_post_missing', 'Events Manager event backing post could not be verified.' );
		}
		if ( $reject_trash && 'trash' === $post->post_status ) {
			return new WP_Error( 'cmsa_event_taxonomy_trashed', 'Restore the event before changing its taxonomy relationships.' );
		}
		if ( ! $event->can_manage( 'edit_events', 'edit_others_events' ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_event_permission', 'Current user cannot edit this Events Manager event.' );
		}
		return $event;
	}

	private function object_term_ids( $post_id, $taxonomy ) {
		$ids = wp_get_object_terms( (int) $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $ids ) ) {
			return new WP_Error( 'cmsa_event_taxonomy_relationship_read', 'Could not read event taxonomy relationships.' );
		}
		return $this->normalize_ids( $ids );
	}

	private function normalize_ids( array $ids ) {
		$normalized = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$normalized[ $id ] = $id;
			}
		}
		$normalized = array_values( $normalized );
		sort( $normalized, SORT_NUMERIC );
		return $normalized;
	}

	private function normalize_term( WP_Term $term ) {
		return array(
			'id'          => (int) $term->term_id,
			'name'        => (string) $term->name,
			'slug'        => (string) $term->slug,
			'description' => (string) $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	private function capture_location_state( EM_Event $event ) {
		$location_id = isset( $event->location_id ) ? (int) $event->location_id : 0;
		if ( $location_id < 1 ) {
			return array( 'id' => 0, 'state_token' => '' );
		}
		$location = $this->events->load_location( $location_id );
		if ( is_wp_error( $location ) ) {
			return array( 'id' => $location_id, 'state_token' => null );
		}
		return array( 'id' => $location_id, 'state_token' => $this->events->location_state( $location ) );
	}

	private function location_state_matches( array $guard ) {
		if ( 0 === (int) $guard['id'] ) {
			return true;
		}
		if ( null === $guard['state_token'] ) {
			return false;
		}
		$location = $this->events->load_location( (int) $guard['id'] );
		return ! is_wp_error( $location ) && hash_equals( (string) $guard['state_token'], $this->events->location_state( $location ) );
	}

	private function is_ordinary_event( EM_Event $event ) {
		return 'event' === ( isset( $event->event_archetype ) ? (string) $event->event_archetype : 'event' )
			&& 'single' === ( isset( $event->event_type ) ? (string) $event->event_type : 'single' );
	}
}
