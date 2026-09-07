<?php

$root = realpath( __DIR__ . '/../candidate' );
if ( ! $root || ! is_dir( $root ) ) {
	fwrite( STDERR, "privacy-boundary-test: candidate source missing\n" );
	exit( 1 );
}

$source = '';
$sources = array();
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
		$path = $file->getPathname();
		$content = file_get_contents( $path );
		$sources[ $path ] = $content;
		$source .= "\n" . $content;
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
	'member metadata read'                         => '/\\bget_user_meta\\s*\\(/i',
	'member metadata write'                        => '/\\b(?:update_user_meta|add_user_meta|delete_user_meta)\\s*\\(/i',
	'direct users table access'                    => '/\\$wpdb->users\\b/i',
	'credential field access'                      => '/\\b(?:user_pass|user_activation_key)\\b/i',
	'session-token API access'                     => '/\\b(?:WP_Session_Tokens|wp_get_session_token)\\b/i',
	'WordPress secret constant access'             => '/\\b(?:DB_PASSWORD|AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY)\\b/',
);

$failures = array();
foreach ( $forbidden as $label => $pattern ) {
	if ( preg_match( $pattern, $source ) ) {
		$failures[] = $label;
	}
}

$member_service = realpath( $root . '/includes/class-cmsa-members.php' );
foreach ( $sources as $path => $content ) {
	if ( $path !== $member_service && preg_match( '/\\bWP_User_Query\\b/i', $content ) ) {
		$failures[] = 'WP_User_Query outside typed member service';
		break;
	}
}
if ( $member_service && isset( $sources[ $member_service ] ) ) {
	$member_source = $sources[ $member_service ];
	$member_forbidden = array(
		'whole WP_User object export' => '/(?:->to_array\\s*\\(|get_object_vars\\s*\\()/i',
		'arbitrary member meta surface' => '/\\b(?:meta_key|meta_value)\\b/i',
	);
	foreach ( $member_forbidden as $label => $pattern ) {
		if ( preg_match( $pattern, $member_source ) ) {
			$failures[] = $label;
		}
	}
}

if ( $failures ) {
	$failures = array_values( array_unique( $failures ) );
	fwrite( STDERR, "privacy-boundary-test: FAIL\n - " . implode( "\n - ", $failures ) . "\n" );
	exit( 1 );
}

printf( "privacy-boundary-test: PASS (%d PHP bytes scanned; member query isolated)\n", strlen( $source ) );
