<?php

$lab = dirname( __DIR__ );
$manifest = json_decode( file_get_contents( $lab . '/fixtures/source-manifest.json' ), true );
if ( ! is_array( $manifest ) || empty( $manifest['files'] ) ) {
	fwrite( STDERR, "Invalid source manifest.\n" );
	exit( 1 );
}

function cmsa_git_blob_sha1( $content ) {
	return sha1( 'blob ' . strlen( $content ) . "\0" . $content );
}

foreach ( $manifest['files'] as $relative => $expected_sha ) {
	$path = $lab . '/' . $relative;
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, "Missing mirrored source file: {$relative}\n" );
		exit( 1 );
	}
	$actual_sha = cmsa_git_blob_sha1( file_get_contents( $path ) );
	if ( ! hash_equals( $expected_sha, $actual_sha ) ) {
		fwrite( STDERR, "Source blob mismatch for {$relative}: expected {$expected_sha}, got {$actual_sha}\n" );
		exit( 1 );
	}
}

echo 'source-manifest-test: PASS (' . count( $manifest['files'] ) . " files)\n";
