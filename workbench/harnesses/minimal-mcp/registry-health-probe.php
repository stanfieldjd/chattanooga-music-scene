<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress bootstrap required.\n" );
	exit( 1 );
}

$health = CMSA_Minimal_MCP_Tool_Registry::health();
if ( true === $health ) {
	fwrite( STDOUT, "robust-mcp-registry-health: PASS\n" );
	return;
}

$reflection = new ReflectionClass( CMSA_Minimal_MCP_Tool_Registry::class );
$property   = $reflection->getProperty( 'registration_errors' );
$property->setAccessible( true );
$errors = $property->getValue();

fwrite( STDERR, "robust-mcp-registry-health: FAIL\n" );
foreach ( $errors as $error ) {
	if ( $error instanceof WP_Error ) {
		fwrite( STDERR, $error->get_error_code() . ': ' . $error->get_error_message() . "\n" );
	}
}
exit( 1 );
