<?php

$lab = dirname( __DIR__ );
$plugin_dir = $lab . '/plugin';
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_dir, FilesystemIterator::SKIP_DOTS ) );

$prohibited = array(
	'eval'                => '/\beval\s*\(/i',
	'shell_exec'          => '/\bshell_exec\s*\(/i',
	'exec'                => '/(?<![A-Za-z0-9_])exec\s*\(/i',
	'system'              => '/(?<![A-Za-z0-9_])system\s*\(/i',
	'passthru'            => '/\bpassthru\s*\(/i',
	'proc_open'           => '/\bproc_open\s*\(/i',
	'popen'               => '/\bpopen\s*\(/i',
	'WP_CLI::runcommand'  => '/WP_CLI\s*::\s*runcommand/i',
	'direct REST route'   => '/\bregister_rest_route\s*\(/i',
	'direct cURL'         => '/\bcurl_[a-z0-9_]+\s*\(/i',
);

$network_markers = array(
	'wp_remote_' => '/\bwp_remote_[a-z0-9_]+\s*\(/i',
	'plugins_api' => '/\bplugins_api\s*\(/i',
	'themes_api' => '/\bthemes_api\s*\(/i',
);

$network_hits = array();
foreach ( $iterator as $item ) {
	if ( ! $item->isFile() || 'php' !== strtolower( $item->getExtension() ) ) {
		continue;
	}
	$content = file_get_contents( $item->getPathname() );
	$relative = substr( $item->getPathname(), strlen( $plugin_dir ) + 1 );
	foreach ( $prohibited as $label => $pattern ) {
		if ( preg_match( $pattern, $content ) ) {
			fwrite( STDERR, "Prohibited execution/exposure primitive '{$label}' found in {$relative}.\n" );
			exit( 1 );
		}
	}
	foreach ( $network_markers as $label => $pattern ) {
		if ( preg_match( $pattern, $content ) ) {
			$network_hits[] = $label . ':' . $relative;
		}
	}
}

$abilities = file_get_contents( $plugin_dir . '/includes/class-cmsa-abilities.php' );
foreach ( array( 'run-command', 'execute-code', 'shell', 'sql-query', 'arbitrary-query' ) as $forbidden_ability ) {
	if ( false !== stripos( $abilities, "'" . $forbidden_ability . "'" ) ) {
		fwrite( STDERR, "Generic arbitrary-execution ability found: {$forbidden_ability}\n" );
		exit( 1 );
	}
}

echo "security-test: PASS\n";
if ( $network_hits ) {
	echo 'network-surface: ' . implode( ', ', array_unique( $network_hits ) ) . "\n";
} else {
	echo "network-surface: no direct network primitives detected\n";
}
