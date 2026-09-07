<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "health-cache-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$health = new CMSA_Health();
$report = $health->get_health();

$checks = array(
	'wordpress_version'   => isset( $report['wordpress_version'] ) && 0 === strpos( (string) $report['wordpress_version'], '7.1' ),
	'database_responding' => ! empty( $report['database_responding'] ),
	'plugin_dir_writable' => ! empty( $report['plugin_dir_writable'] ),
	'theme_dir_writable'  => ! empty( $report['theme_dir_writable'] ),
	'content_writable'    => ! empty( $report['content_writable'] ),
	'backup_storage'      => ! empty( $report['backup_storage']['available'] ) && ! empty( $report['backup_storage']['writable'] ),
	'ziparchive'          => ! empty( $report['ziparchive'] ),
);

foreach ( $checks as $label => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "health-cache-cli: {$label} check failed.\n" );
		exit( 1 );
	}
}

$cache = $health->clear_cache();
if ( is_wp_error( $cache ) || empty( $cache['cleared'] ) || empty( $cache['methods'] ) ) {
	fwrite( STDERR, "health-cache-cli: cache clear failed.\n" );
	exit( 1 );
}

$wordpress_cache_method = is_multisite() ? 'wordpress-blog-cache' : 'wordpress-options-cache';
if ( ! in_array( $wordpress_cache_method, $cache['methods'], true ) ) {
	fwrite( STDERR, "health-cache-cli: WordPress cache invalidation method was not reported.\n" );
	exit( 1 );
}

printf( "health-cache-cli: PASS methods=%s\n", implode( ',', $cache['methods'] ) );
