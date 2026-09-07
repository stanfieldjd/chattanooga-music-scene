<?php

$root = realpath( __DIR__ . '/../candidate' );
if ( ! $root || ! is_dir( $root ) ) {
	fwrite( STDERR, "privacy-boundary-test: candidate source missing\n" );
	exit( 1 );
}

$source = '';
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
		$source .= "\n" . file_get_contents( $file->getPathname() );
	}
}

$forbidden = array(
	'direct outbound HTTP via wp_remote_get'      => '/\\bwp_remote_get\\s*\\(/i',
	'direct outbound HTTP via wp_remote_post'     => '/\\bwp_remote_post\\s*\\(/i',
	'direct outbound HTTP via wp_remote_request'  => '/\\bwp_remote_request\\s*\\(/i',
	'direct cURL transport'                        => '/\\bcurl_(?:init|exec|multi_exec)\\s*\\(/i',
	'direct socket transport'                      => '/\\b(?:fsockopen|pfsockopen|stream_socket_client)\\s*\\(/i',
	'literal external URL embedded in PHP'         => '/https?:\\/\\/[^\\s\'\"]+/i',
	'bulk member enumeration via get_users'        => '/\\bget_users\\s*\\(/i',
	'bulk member enumeration via WP_User_Query'    => '/\\bWP_User_Query\\b/i',
	'member metadata read'                         => '/\\bget_user_meta\\s*\\(/i',
	'member metadata write'                        => '/\\b(?:update_user_meta|add_user_meta|delete_user_meta)\\s*\\(/i',
	'direct users table access'                    => '/\\$wpdb->users\\b/i',
	'credential field access'                      => '/\\b(?:user_pass|user_activation_key)\\b/i',
	'WordPress secret constant access'             => '/\\b(?:DB_PASSWORD|AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY)\\b/',
);

$failures = array();
foreach ( $forbidden as $label => $pattern ) {
	if ( preg_match( $pattern, $source ) ) {
		$failures[] = $label;
	}
}

if ( $failures ) {
	fwrite( STDERR, "privacy-boundary-test: FAIL\n - " . implode( "\n - ", $failures ) . "\n" );
	exit( 1 );
}

printf( "privacy-boundary-test: PASS (%d PHP bytes scanned)\n", strlen( $source ) );
