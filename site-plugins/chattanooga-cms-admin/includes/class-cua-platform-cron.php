<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_Platform_Cron {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX   = 'chattanooga-cms-admin/';

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::PREFIX . 'list-cron-events',
			array(
				'label'               => __( 'List scheduled WordPress cron events', 'chattanooga-cms-admin' ),
				'description'         => __( 'Returns a bounded inventory of scheduled WordPress cron events without exposing raw hook arguments.', 'chattanooga-cms-admin' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'hook' => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 191,
						),
						'limit' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 500,
							'default' => 200,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'list_cron_events' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
						'open_world'  => false,
					),
				),
			)
		);
	}

	public static function list_cron_events( $input = array() ) {
		$hook_filter = is_array( $input ) && isset( $input['hook'] ) ? trim( (string) $input['hook'] ) : '';
		$limit       = is_array( $input ) && isset( $input['limit'] ) ? min( 500, max( 1, (int) $input['limit'] ) ) : 200;

		$cron = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();
		if ( ! is_array( $cron ) ) {
			return new WP_Error( 'cmsa_cron_unavailable', 'WordPress cron storage could not be read.' );
		}

		$items = array();
		foreach ( $cron as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}

			foreach ( $hooks as $hook => $events ) {
				if ( '' !== $hook_filter && $hook_filter !== (string) $hook ) {
					continue;
				}
				if ( ! is_array( $events ) ) {
					continue;
				}

				foreach ( $events as $event ) {
					if ( ! is_array( $event ) ) {
						continue;
					}

					$args = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();
					$encoded_args = wp_json_encode( $args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
					$items[] = array(
						'hook'         => (string) $hook,
						'timestamp'    => (int) $timestamp,
						'scheduled_at' => gmdate( 'c', (int) $timestamp ),
						'schedule'     => isset( $event['schedule'] ) ? (string) $event['schedule'] : '',
						'interval'     => isset( $event['interval'] ) ? (int) $event['interval'] : 0,
						'args_count'   => count( $args ),
						'args_sha256'  => false === $encoded_args ? '' : hash( 'sha256', $encoded_args ),
					);
					if ( count( $items ) >= $limit ) {
						break 3;
					}
				}
			}
		}

		usort(
			$items,
			static function ( $left, $right ) {
				$by_time = (int) $left['timestamp'] <=> (int) $right['timestamp'];
				return 0 !== $by_time ? $by_time : strcmp( (string) $left['hook'], (string) $right['hook'] );
			}
		);

		return array(
			'count'       => count( $items ),
			'limit'       => $limit,
			'hook_filter' => $hook_filter,
			'items'       => $items,
		);
	}
}
