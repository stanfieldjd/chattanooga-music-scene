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
	if ( ! $error instanceof WP_Error ) {
		continue;
	}
	fwrite( STDERR, $error->get_error_code() . ': ' . $error->get_error_message() . "\n" );
	$data = $error->get_error_data();
	if ( is_array( $data ) ) {
		if ( isset( $data['internal_exception_class'] ) ) {
			fwrite( STDERR, '  exception=' . (string) $data['internal_exception_class'] . "\n" );
		}
		if ( isset( $data['internal_exception_message'] ) ) {
			fwrite( STDERR, '  message=' . (string) $data['internal_exception_message'] . "\n" );
		}
	}
}
exit( 1 );
