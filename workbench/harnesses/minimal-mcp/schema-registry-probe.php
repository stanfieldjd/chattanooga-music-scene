<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress bootstrap required.\n" );
	exit( 1 );
}

$good = array(
	'$schema' => 'https://json-schema.org/draft/2020-12/schema',
	'$defs'   => array(
		'value' => array(
			'oneOf' => array(
				array( 'type' => 'string', 'minLength' => 2 ),
				array( 'type' => 'integer', 'minimum' => 1 ),
			),
		),
	),
	'type'       => 'object',
	'properties' => array( 'value' => array( '$ref' => '#/$defs/value' ) ),
	'required'   => array( 'value' ),
);

$result = CMSA_Robust_MCP_Schema_Validator::validate_definition( $good, true );
if ( true !== $result ) {
	fwrite( STDERR, "A valid Draft 2020-12 schema was rejected.\n" );
	exit( 2 );
}

$external = array(
	'$schema' => 'https://json-schema.org/draft/2020-12/schema',
	'type'       => 'object',
	'properties' => array(
		'value' => array( '$ref' => 'https://attacker.example/schema.json' ),
	),
);
$result = CMSA_Robust_MCP_Schema_Validator::validate_definition( $external, true );
if ( ! is_wp_error( $result ) || 'robust_mcp_external_ref_rejected' !== $result->get_error_code() ) {
	fwrite( STDERR, "External JSON Schema references were not rejected.\n" );
	exit( 3 );
}

$deep = array( 'type' => 'object', 'properties' => array() );
$cursor =& $deep['properties'];
for ( $i = 0; $i < 60; $i++ ) {
	$cursor['level' . $i] = array( 'type' => 'object', 'properties' => array() );
	$cursor =& $cursor['level' . $i]['properties'];
}
unset( $cursor );
$result = CMSA_Robust_MCP_Schema_Validator::validate_definition( $deep, true );
if ( ! is_wp_error( $result ) || 'robust_mcp_schema_too_deep' !== $result->get_error_code() ) {
	fwrite( STDERR, "Excessive schema depth was not rejected.\n" );
	exit( 4 );
}

$large = array(
	'type'        => 'object',
	'description' => str_repeat( 'x', 140000 ),
);
$result = CMSA_Robust_MCP_Schema_Validator::validate_definition( $large, true );
if ( ! is_wp_error( $result ) || 'robust_mcp_schema_too_large' !== $result->get_error_code() ) {
	fwrite( STDERR, "Excessive schema size was not rejected.\n" );
	exit( 5 );
}

fwrite( STDOUT, "robust-mcp-schema-registry: PASS draft=2020-12 local-ref=yes external-ref=rejected depth-limit=yes size-limit=yes\n" );
