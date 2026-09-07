<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Content_Status {
	private $content;

	public function __construct( CMSA_Content $content ) {
		$this->content = $content;
	}

	public function set_status( $post_type, array $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$current = $this->content->get_item( $post_type, $id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$item = $current['item'];

		$expected_modified = isset( $input['expected_modified_gmt'] ) ? (string) $input['expected_modified_gmt'] : '';
		if ( '' === $expected_modified ) {
			return new WP_Error( 'cmsa_content_expected_modified', 'Expected modified timestamp is required.' );
		}
		if ( $expected_modified !== (string) $item['modified_gmt'] ) {
			return new WP_Error(
				'cmsa_content_conflict',
				'Content changed after it was read; status mutation was not attempted.',
				array( 'current_modified_gmt' => (string) $item['modified_gmt'] )
			);
		}

		$expected_status = isset( $input['expected_status'] ) ? sanitize_key( $input['expected_status'] ) : '';
		if ( '' === $expected_status ) {
			return new WP_Error( 'cmsa_content_expected_status', 'Expected content status is required.' );
		}
		if ( $expected_status !== (string) $item['status'] ) {
			return new WP_Error(
				'cmsa_content_status_conflict',
				'Content status changed after it was read; status mutation was not attempted.',
				array( 'current_status' => (string) $item['status'] )
			);
		}

		$target_status = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : '';
		if ( ! in_array( $target_status, array( 'draft', 'pending', 'publish', 'private', 'future' ), true ) ) {
			return new WP_Error( 'cmsa_content_status', 'Unsupported target content status.' );
		}
		if ( 'trash' === $item['status'] ) {
			return new WP_Error( 'cmsa_content_status_trash', 'Restore content from trash before changing its publication status.' );
		}
		if ( $target_status === $item['status'] ) {
			return new WP_Error( 'cmsa_content_status_no_change', 'Content already has the requested status.' );
		}

		$publish_capability = $this->publish_capability( $post_type );
		if ( is_wp_error( $publish_capability ) ) {
			return $publish_capability;
		}
		if ( in_array( $target_status, array( 'publish', 'private', 'future' ), true ) && ! current_user_can( $publish_capability ) ) {
			return new WP_Error( 'cmsa_content_publish_permission', 'Current user cannot publish or schedule this content type.' );
		}

		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || $post_type !== $post->post_type ) {
			return new WP_Error( 'cmsa_content_type', 'Requested content item does not exist in the expected type.' );
		}
		$previous = array(
			'status'   => (string) $post->post_status,
			'date'     => (string) $post->post_date,
			'date_gmt' => (string) $post->post_date_gmt,
		);

		$update = array(
			'ID'          => $post->ID,
			'post_status' => $target_status,
		);
		$target_date_gmt = '';
		if ( 'future' === $target_status ) {
			$target_date_gmt = isset( $input['date_gmt'] ) ? trim( (string) $input['date_gmt'] ) : '';
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $target_date_gmt ) ) {
				return new WP_Error( 'cmsa_content_schedule_date', 'A future UTC date in YYYY-MM-DD HH:MM:SS format is required for scheduling.' );
			}
			$timestamp = strtotime( $target_date_gmt . ' UTC' );
			if ( false === $timestamp || $timestamp <= current_time( 'timestamp', true ) ) {
				return new WP_Error( 'cmsa_content_schedule_date', 'Scheduled publication time must be in the future.' );
			}
			$update['post_date_gmt'] = $target_date_gmt;
			$update['post_date'] = get_date_from_gmt( $target_date_gmt, 'Y-m-d H:i:s' );
		} elseif ( 'publish' === $target_status && 'future' === $previous['status'] ) {
			$update['post_date_gmt'] = current_time( 'mysql', true );
			$update['post_date'] = current_time( 'mysql' );
		}

		$result = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $result ) || ! $result ) {
			return new WP_Error( 'cmsa_content_status_update', 'Could not change content status.' );
		}

		$after = get_post( $post->ID );
		$verified = $after instanceof WP_Post && $target_status === $after->post_status;
		if ( $verified && 'future' === $target_status ) {
			$verified = $target_date_gmt === (string) $after->post_date_gmt;
		}
		if ( ! $verified ) {
			$rolled_back = $this->restore_previous_state( $post->ID, $previous );
			return new WP_Error(
				'cmsa_content_status_verify',
				'Content status verification failed.',
				array( 'rolled_back' => $rolled_back, 'previous_status' => $previous['status'] )
			);
		}

		$read_after = $this->content->get_item( $post_type, $post->ID );
		if ( is_wp_error( $read_after ) ) {
			$rolled_back = $this->restore_previous_state( $post->ID, $previous );
			return new WP_Error(
				'cmsa_content_status_readback',
				'Content status changed but readback verification failed.',
				array( 'rolled_back' => $rolled_back, 'previous_status' => $previous['status'] )
			);
		}

		CMSA_Audit::record(
			'set-' . $post_type . '-status',
			(string) $post->ID,
			'success',
			array( 'previous_status' => $previous['status'], 'status' => $target_status )
		);
		return array(
			'transitioned'    => true,
			'previous_status' => $previous['status'],
			'status'          => $target_status,
			'item'            => $read_after['item'],
		);
	}

	private function publish_capability( $post_type ) {
		if ( 'post' === $post_type ) {
			return 'publish_posts';
		}
		if ( 'page' === $post_type ) {
			return 'publish_pages';
		}
		return new WP_Error( 'cmsa_content_type', 'Only posts and pages are supported by this status service.' );
	}

	private function restore_previous_state( $id, array $previous ) {
		$result = wp_update_post(
			wp_slash(
				array(
					'ID'            => (int) $id,
					'post_status'   => $previous['status'],
					'post_date'     => $previous['date'],
					'post_date_gmt' => $previous['date_gmt'],
				)
			),
			true
		);
		if ( is_wp_error( $result ) || ! $result ) {
			return false;
		}
		$restored = get_post( $id );
		return $restored instanceof WP_Post
			&& $previous['status'] === (string) $restored->post_status
			&& $previous['date'] === (string) $restored->post_date
			&& $previous['date_gmt'] === (string) $restored->post_date_gmt;
	}
}
