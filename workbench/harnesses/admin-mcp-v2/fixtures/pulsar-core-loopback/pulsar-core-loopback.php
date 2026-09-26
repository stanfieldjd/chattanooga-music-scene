<?php
/**
 * Plugin Name: Pulsar Core Loopback Fixture
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		if ( false !== strpos( (string) $url, '/wp-admin/upgrade.php?step=upgrade_db' ) ) {
			update_option( 'cmsa_v2_core_db_loopback_seen', (int) get_option( 'cmsa_v2_core_db_loopback_seen', 0 ) + 1, false );
			return array(
				'headers'  => array(),
				'body'     => '',
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		return $preempt;
	},
	5,
	3
);
