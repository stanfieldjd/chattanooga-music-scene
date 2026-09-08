<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Weekend_Feature {
	const SUPPORTED_CORE_VERSION = '0.2.2';

	public function get_status() {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error( 'cmsa_weekend_permission', 'Current user cannot inspect Weekend Feature administration state.' );
		}

		$dependency = $this->dependency();
		if ( is_wp_error( $dependency ) ) {
			return $dependency;
		}

		$window = $this->weekend_window();
		$settings = $this->settings_snapshot();
		$feature = $this->current_feature_snapshot( $window['key'] );
		$event_count = $this->event_count( $window );
		$scheduled = $this->schedule_snapshot();
		$last_run = get_option( 'cms_weekend_last_run', array() );

		return array(
			'dependency' => array(
				'available' => true,
				'version'   => CMS_CORE_VERSION,
			),
			'settings'             => $settings['settings'],
			'settings_state_token' => $settings['state_token'],
			'schedule'             => $scheduled,
			'weekend'              => array(
				'key'   => $window['key'],
				'start' => $window['start']->format( DATE_ATOM ),
				'end'   => $window['end']->format( DATE_ATOM ),
			),
			'events_manager_available' => class_exists( 'EM_Events' ),
			'event_count'              => $event_count,
			'feature'                  => $feature,
			'last_run'                 => $this->safe_last_run( $last_run ),
		);
	}

	public function update_settings( array $input ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error( 'cmsa_weekend_settings_permission', 'Current user cannot change Weekend Feature settings.' );
		}

		$dependency = $this->dependency();
		if ( is_wp_error( $dependency ) ) {
			return $dependency;
		}

		$before = $this->settings_snapshot();
		$expected = isset( $input['expected_settings_state_token'] ) ? (string) $input['expected_settings_state_token'] : '';
		if ( '' === $expected || ! hash_equals( $before['state_token'], $expected ) ) {
			return new WP_Error( 'cmsa_weekend_settings_conflict', 'Weekend Feature settings or schedule changed after they were read; update was not attempted.', array( 'current_settings_state_token' => $before['state_token'] ) );
		}

		$validation = $this->validate_requested_settings( $input );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$requested = $dependency->sanitize_settings(
			array(
				'enabled'      => ! empty( $input['enabled'] ) ? 1 : 0,
				'publish_time' => (string) $input['publish_time'],
				'post_author'  => (int) $input['post_author'],
				'introduction' => (string) $input['introduction'],
				'closing'      => (string) $input['closing'],
			)
		);
		$requested = $this->normalize_settings( $requested );

		if ( $requested === $before['settings'] ) {
			return new WP_Error( 'cmsa_weekend_settings_no_change', 'Requested Weekend Feature settings already match the current state.' );
		}
		if ( ! empty( $input['enabled'] ) && empty( $requested['enabled'] ) ) {
			return new WP_Error( 'cmsa_weekend_settings_invalid', 'Requested enabled Weekend Feature settings were rejected by the source plugin.' );
		}

		$had_option = array_key_exists( CMS_Weekend_Posts::OPTION_SETTINGS, wp_load_alloptions() ) || false !== get_option( CMS_Weekend_Posts::OPTION_SETTINGS, false );
		$raw_before = get_option( CMS_Weekend_Posts::OPTION_SETTINGS, array() );
		$schedule_before = $this->raw_schedule_snapshot();

		$updated = update_option( CMS_Weekend_Posts::OPTION_SETTINGS, $requested, false );
		if ( ! $updated ) {
			return new WP_Error( 'cmsa_weekend_settings_update', 'WordPress did not persist the requested Weekend Feature settings.' );
		}

		$after = $this->settings_snapshot();
		$verified = $after['settings'] === $requested && $this->schedule_matches_settings( $requested, $after['schedule'] );
		$verified = (bool) apply_filters( 'cmsa_weekend_feature_verify_settings', $verified, $requested, $after );
		if ( ! $verified ) {
			$rolled_back = $this->restore_settings( $had_option, $raw_before, $schedule_before );
			return new WP_Error( 'cmsa_weekend_settings_verify', 'Weekend Feature settings verification failed.', array( 'rolled_back' => $rolled_back ) );
		}

		CMSA_Audit::record( 'update-weekend-feature-settings', 'weekend-feature', 'success' );
		return array(
			'updated'                       => true,
			'previous_settings_state_token' => $before['state_token'],
			'settings'                      => $after['settings'],
			'settings_state_token'          => $after['state_token'],
			'schedule'                      => $after['schedule'],
		);
	}

	public function generate( array $input, $status ) {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error( 'cmsa_weekend_generate_permission', 'Current user cannot generate Weekend Features.' );
		}

		$dependency = $this->dependency();
		if ( is_wp_error( $dependency ) ) {
			return $dependency;
		}
		$status = 'publish' === $status ? 'publish' : 'draft';

		$window = $this->weekend_window();
		$expected_week_key = isset( $input['expected_week_key'] ) ? (string) $input['expected_week_key'] : '';
		if ( '' === $expected_week_key || ! hash_equals( $window['key'], $expected_week_key ) ) {
			return new WP_Error( 'cmsa_weekend_week_conflict', 'Current Weekend Feature window changed after it was read; generation was not attempted.', array( 'current_week_key' => $window['key'] ) );
		}

		$feature_before = $this->current_feature_snapshot( $window['key'] );
		$expected_id = isset( $input['expected_feature_id'] ) ? max( 0, (int) $input['expected_feature_id'] ) : 0;
		$current_id = $feature_before ? (int) $feature_before['id'] : 0;
		if ( $expected_id !== $current_id ) {
			return new WP_Error( 'cmsa_weekend_feature_conflict', 'Current Weekend Feature identity changed after it was read; generation was not attempted.', array( 'current_feature_id' => $current_id ) );
		}

		$expected_feature_token = isset( $input['expected_feature_state_token'] ) ? (string) $input['expected_feature_state_token'] : '';
		if ( $feature_before ) {
			if ( '' === $expected_feature_token || ! hash_equals( $feature_before['state_token'], $expected_feature_token ) ) {
				return new WP_Error( 'cmsa_weekend_feature_conflict', 'Current Weekend Feature changed after it was read; generation was not attempted.', array( 'current_feature_state_token' => $feature_before['state_token'] ) );
			}
			if ( 'publish' === $feature_before['status'] ) {
				return new WP_Error( 'cmsa_weekend_feature_published', 'The current Weekend Feature is already published and will not be overwritten.' );
			}
		} elseif ( '' !== $expected_feature_token ) {
			return new WP_Error( 'cmsa_weekend_feature_conflict', 'A feature state token was supplied for an absent current Weekend Feature.' );
		}

		$event_count = $this->event_count( $window );
		if ( null === $event_count ) {
			return new WP_Error( 'cmsa_weekend_events_manager', 'Events Manager is not available to generate the Weekend Feature.' );
		}
		if ( $event_count < 1 ) {
			return new WP_Error( 'cmsa_weekend_no_events', 'No published events are available in the current Weekend Feature window.' );
		}

		$semantic_before = $feature_before ? $this->capture_feature_semantic_state( $current_id ) : null;
		$result = $dependency->generate( $status );
		if ( is_wp_error( $result ) || ! is_array( $result ) || empty( $result['post_id'] ) ) {
			return new WP_Error( 'cmsa_weekend_generate', 'The Weekend Feature source plugin could not generate the requested feature.' );
		}

		$post_id = (int) $result['post_id'];
		$after = $this->feature_snapshot_by_id( $post_id, $window['key'] );
		$verified = $after
			&& $window['key'] === (string) $result['week_key']
			&& $status === (string) $result['status']
			&& $status === $after['status']
			&& $window['key'] === $after['week_key']
			&& (int) $result['event_count'] === $event_count;
		$verified = (bool) apply_filters( 'cmsa_weekend_feature_verify_generation', $verified, $status, $result, $after );
		if ( ! $verified ) {
			$rolled_back = $this->rollback_generated_feature( $post_id, $current_id, $semantic_before );
			return new WP_Error( 'cmsa_weekend_generate_verify', 'Weekend Feature generation verification failed.', array( 'rolled_back' => $rolled_back ) );
		}

		CMSA_Audit::record( 'publish' === $status ? 'publish-weekend-feature-now' : 'generate-weekend-feature-draft', (string) $post_id, 'success', array( 'week_key' => $window['key'], 'event_count' => $event_count ) );
		return array(
			'generated'                    => true,
			'published'                    => 'publish' === $status,
			'week_key'                     => $window['key'],
			'event_count'                  => $event_count,
			'previous_feature_state_token' => $feature_before ? $feature_before['state_token'] : '',
			'feature'                      => $after,
		);
	}

	private function dependency() {
		if ( ! class_exists( 'CMS_Weekend_Posts' ) || ! defined( 'CMS_CORE_VERSION' ) ) {
			return new WP_Error( 'cmsa_weekend_dependency', 'Chattanooga Music Scene Weekend Feature is not available.' );
		}
		if ( self::SUPPORTED_CORE_VERSION !== (string) CMS_CORE_VERSION ) {
			return new WP_Error( 'cmsa_weekend_dependency_version', 'The installed Weekend Feature version is outside the verified adapter contract.' );
		}
		foreach ( array( 'OPTION_SETTINGS', 'CRON_HOOK', 'POST_TYPE', 'META_WEEK_KEY', 'META_GENERATED' ) as $constant ) {
			if ( ! defined( 'CMS_Weekend_Posts::' . $constant ) ) {
				return new WP_Error( 'cmsa_weekend_dependency_contract', 'The Weekend Feature source contract is incomplete.' );
			}
		}
		if ( ! method_exists( 'CMS_Weekend_Posts', 'instance' ) || ! method_exists( 'CMS_Weekend_Posts', 'sanitize_settings' ) || ! method_exists( 'CMS_Weekend_Posts', 'settings_updated' ) || ! method_exists( 'CMS_Weekend_Posts', 'generate' ) ) {
			return new WP_Error( 'cmsa_weekend_dependency_contract', 'The Weekend Feature source contract is incomplete.' );
		}
		return CMS_Weekend_Posts::instance();
	}

	private function settings_snapshot() {
		$raw = get_option( CMS_Weekend_Posts::OPTION_SETTINGS, array() );
		$settings = $this->normalize_settings( is_array( $raw ) ? $raw : array() );
		$schedule = $this->schedule_snapshot();
		return array(
			'settings'    => $settings,
			'schedule'    => $schedule,
			'state_token' => $this->state_token( array( 'raw' => is_array( $raw ) ? $raw : array(), 'schedule' => $schedule ) ),
		);
	}

	private function normalize_settings( array $settings ) {
		$defaults = array(
			'enabled'      => 0,
			'publish_time' => '',
			'post_author'  => 0,
			'introduction' => 'Chattanooga’s stages come alive every weekend. Explore the shows happening across the city, then open any event for its complete details.',
			'closing'      => 'Plans can change. Open the individual event page for the latest details before heading out.',
		);
		$settings = wp_parse_args( $settings, $defaults );
		return array(
			'enabled'      => empty( $settings['enabled'] ) ? 0 : 1,
			'publish_time' => isset( $settings['publish_time'] ) ? (string) $settings['publish_time'] : '',
			'post_author'  => isset( $settings['post_author'] ) ? absint( $settings['post_author'] ) : 0,
			'introduction' => isset( $settings['introduction'] ) ? (string) $settings['introduction'] : $defaults['introduction'],
			'closing'      => isset( $settings['closing'] ) ? (string) $settings['closing'] : $defaults['closing'],
		);
	}

	private function validate_requested_settings( array $input ) {
		foreach ( array( 'enabled', 'publish_time', 'post_author', 'introduction', 'closing' ) as $field ) {
			if ( ! array_key_exists( $field, $input ) ) {
				return new WP_Error( 'cmsa_weekend_settings_input', 'All Weekend Feature settings fields are required for exact replacement.' );
			}
		}
		$time = (string) $input['publish_time'];
		if ( '' !== $time && ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			return new WP_Error( 'cmsa_weekend_settings_time', 'Weekend Feature publish time must be empty or a valid 24-hour HH:MM value.' );
		}
		$author_id = max( 0, (int) $input['post_author'] );
		if ( $author_id ) {
			$author = get_user_by( 'id', $author_id );
			if ( ! $author || ! user_can( $author, 'publish_posts' ) ) {
				return new WP_Error( 'cmsa_weekend_settings_author', 'Weekend Feature post author must be an existing user allowed to publish posts.' );
			}
		}
		if ( ! empty( $input['enabled'] ) && ( '' === $time || ! $author_id ) ) {
			return new WP_Error( 'cmsa_weekend_settings_enabled', 'Enabled Weekend Feature scheduling requires both a publish time and valid post author.' );
		}
		return true;
	}

	private function raw_schedule_snapshot() {
		$event = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( CMS_Weekend_Posts::CRON_HOOK ) : false;
		return array(
			'scheduled' => (bool) $event,
			'timestamp' => $event ? (int) $event->timestamp : 0,
			'schedule'  => $event ? (string) $event->schedule : '',
			'args'      => $event && isset( $event->args ) ? (array) $event->args : array(),
		);
	}

	private function schedule_snapshot() {
		$raw = $this->raw_schedule_snapshot();
		return array(
			'scheduled' => $raw['scheduled'],
			'timestamp' => $raw['timestamp'],
			'schedule'  => $raw['schedule'],
			'next_local' => $raw['scheduled'] ? wp_date( DATE_ATOM, $raw['timestamp'], wp_timezone() ) : '',
			'args_hash' => $this->state_token( $raw['args'] ),
		);
	}

	private function schedule_matches_settings( array $settings, array $schedule ) {
		if ( empty( $settings['enabled'] ) ) {
			return empty( $schedule['scheduled'] );
		}
		if ( empty( $schedule['scheduled'] ) || 'cms_weekly' !== $schedule['schedule'] || empty( $schedule['timestamp'] ) ) {
			return false;
		}
		$local = ( new DateTimeImmutable( '@' . (int) $schedule['timestamp'] ) )->setTimezone( wp_timezone() );
		return 4 === (int) $local->format( 'N' ) && $settings['publish_time'] === $local->format( 'H:i' );
	}

	private function restore_settings( $had_option, $raw_before, array $schedule_before ) {
		if ( $had_option ) {
			update_option( CMS_Weekend_Posts::OPTION_SETTINGS, is_array( $raw_before ) ? $raw_before : array(), false );
		} else {
			delete_option( CMS_Weekend_Posts::OPTION_SETTINGS );
		}

		wp_clear_scheduled_hook( CMS_Weekend_Posts::CRON_HOOK );
		$schedule_restored = true;
		if ( ! empty( $schedule_before['scheduled'] ) ) {
			$scheduled = wp_schedule_event(
				(int) $schedule_before['timestamp'],
				(string) $schedule_before['schedule'],
				CMS_Weekend_Posts::CRON_HOOK,
				isset( $schedule_before['args'] ) ? (array) $schedule_before['args'] : array(),
				true
			);
			$schedule_restored = ! is_wp_error( $scheduled ) && false !== $scheduled;
		}

		$raw_after = get_option( CMS_Weekend_Posts::OPTION_SETTINGS, array() );
		$current_schedule = $this->raw_schedule_snapshot();
		$option_ok = $had_option ? $raw_after === $raw_before : array() === $raw_after;
		$schedule_ok = $this->schedule_exactly_equal( $schedule_before, $current_schedule );
		return $option_ok && $schedule_restored && $schedule_ok;
	}

	private function schedule_exactly_equal( array $a, array $b ) {
		return (bool) $a['scheduled'] === (bool) $b['scheduled']
			&& (string) $a['schedule'] === (string) $b['schedule']
			&& (int) $a['timestamp'] === (int) $b['timestamp']
			&& ( isset( $a['args'] ) ? (array) $a['args'] : array() ) === ( isset( $b['args'] ) ? (array) $b['args'] : array() );
	}

	private function weekend_window() {
		$timezone = wp_timezone();
		$now = new DateTimeImmutable( 'now', $timezone );
		$friday = $now->modify( 'friday this week' )->setTime( 0, 0, 0 );
		if ( $now > $friday->modify( 'sunday this week' )->setTime( 23, 59, 59 ) ) {
			$friday = $friday->modify( '+1 week' );
		}
		return array(
			'start' => $friday,
			'end'   => $friday->modify( '+2 days' )->setTime( 23, 59, 59 ),
			'key'   => $friday->format( 'Y-m-d' ),
		);
	}

	private function event_count( array $window ) {
		if ( ! class_exists( 'EM_Events' ) ) {
			return null;
		}
		$events = EM_Events::get(
			array(
				'scope'      => $window['start']->format( 'Y-m-d' ) . ',' . $window['end']->format( 'Y-m-d' ),
				'status'     => 1,
				'orderby'    => 'event_start_date,event_start_time,event_name',
				'order'      => 'ASC',
				'limit'      => 0,
				'pagination' => false,
			)
		);
		if ( ! is_array( $events ) ) {
			return null;
		}
		$unique = array();
		foreach ( $events as $event ) {
			$key = ! empty( $event->event_id ) ? 'event-' . absint( $event->event_id ) : 'post-' . absint( $event->post_id );
			$unique[ $key ] = true;
		}
		return count( $unique );
	}

	private function current_feature_snapshot( $week_key ) {
		$ids = get_posts(
			array(
				'post_type'      => CMS_Weekend_Posts::POST_TYPE,
				'post_status'    => array( 'draft', 'pending', 'future', 'publish' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => CMS_Weekend_Posts::META_WEEK_KEY,
				'meta_value'     => (string) $week_key,
			)
		);
		return $ids ? $this->feature_snapshot_by_id( (int) $ids[0], $week_key ) : null;
	}

	private function feature_snapshot_by_id( $post_id, $expected_week_key ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || CMS_Weekend_Posts::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$week_key = (string) get_post_meta( $post_id, CMS_Weekend_Posts::META_WEEK_KEY, true );
		if ( (string) $expected_week_key !== $week_key ) {
			return null;
		}
		$semantic = $this->capture_feature_semantic_state( $post_id );
		return array(
			'id'                => (int) $post->ID,
			'status'            => (string) $post->post_status,
			'title'             => (string) $post->post_title,
			'author'            => (int) $post->post_author,
			'week_key'          => $week_key,
			'generated_at'      => (string) get_post_meta( $post_id, CMS_Weekend_Posts::META_GENERATED, true ),
			'modified_gmt'      => (string) $post->post_modified_gmt,
			'permalink'         => 'publish' === $post->post_status ? get_permalink( $post_id ) : '',
			'state_token'       => $this->state_token( $semantic ),
		);
	}

	private function capture_feature_semantic_state( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		return array(
			'post_type'    => (string) $post->post_type,
			'post_status'  => (string) $post->post_status,
			'post_title'   => (string) $post->post_title,
			'post_name'    => (string) $post->post_name,
			'post_content' => (string) $post->post_content,
			'post_excerpt' => (string) $post->post_excerpt,
			'post_author'  => (int) $post->post_author,
			'week_key'     => (string) get_post_meta( $post_id, CMS_Weekend_Posts::META_WEEK_KEY, true ),
			'generated'    => (string) get_post_meta( $post_id, CMS_Weekend_Posts::META_GENERATED, true ),
		);
	}

	private function rollback_generated_feature( $post_id, $previous_id, $semantic_before ) {
		if ( ! $previous_id ) {
			$deleted = wp_delete_post( $post_id, true );
			return false !== $deleted && null === get_post( $post_id );
		}
		if ( $post_id !== $previous_id || ! is_array( $semantic_before ) ) {
			return false;
		}
		$update = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_status'  => $semantic_before['post_status'],
					'post_title'   => $semantic_before['post_title'],
					'post_name'    => $semantic_before['post_name'],
					'post_content' => $semantic_before['post_content'],
					'post_excerpt' => $semantic_before['post_excerpt'],
					'post_author'  => $semantic_before['post_author'],
				)
			),
			true
		);
		if ( is_wp_error( $update ) || ! $update ) {
			return false;
		}
		$this->restore_meta_value( $post_id, CMS_Weekend_Posts::META_WEEK_KEY, $semantic_before['week_key'] );
		$this->restore_meta_value( $post_id, CMS_Weekend_Posts::META_GENERATED, $semantic_before['generated'] );
		$after = $this->capture_feature_semantic_state( $post_id );
		return $after === $semantic_before;
	}

	private function restore_meta_value( $post_id, $key, $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}

	private function safe_last_run( $last_run ) {
		if ( ! is_array( $last_run ) || empty( $last_run['time'] ) ) {
			return array( 'available' => false );
		}
		$safe = array(
			'available' => true,
			'time'      => (string) $last_run['time'],
			'ok'        => ! empty( $last_run['ok'] ),
		);
		if ( ! empty( $last_run['ok'] ) && isset( $last_run['result'] ) && is_array( $last_run['result'] ) ) {
			$result = $last_run['result'];
			$safe['result'] = array(
				'post_id'     => isset( $result['post_id'] ) ? (int) $result['post_id'] : 0,
				'event_count' => isset( $result['event_count'] ) ? (int) $result['event_count'] : 0,
				'status'      => isset( $result['status'] ) ? sanitize_key( $result['status'] ) : '',
				'week_key'    => isset( $result['week_key'] ) ? (string) $result['week_key'] : '',
			);
		}
		return $safe;
	}

	private function state_token( $value ) {
		return hash( 'sha256', (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}
}
