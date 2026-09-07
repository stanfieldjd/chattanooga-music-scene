<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded before this probe runs.\n" );
	exit( 1 );
}

require __DIR__ . '/runtime-capabilities.php';

if ( ! function_exists( 'cmsa_workbench_runtime_capabilities' ) ) {
	fwrite( STDERR, "Runtime capability probe did not load.\n" );
	exit( 1 );
}

$result = cmsa_workbench_runtime_capabilities();
echo json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
