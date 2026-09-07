<?php

$lab = dirname( __DIR__ );
require $lab . '/tests/bootstrap/wp-stubs.php';
require_once $lab . '/candidate/includes/class-cmsa-backups.php';

final class CMSA_Test_Partial_Write_Stream {
	public static $mode = 'progress';
	public static $buffer = '';
	public static $writes = 0;
	private $position = 0;

	public function stream_open( $path, $mode, $options, &$opened_path ) {
		self::$buffer = '';
		self::$writes = 0;
		$this->position = 0;
		return true;
	}

	public function stream_write( $data ) {
		self::$writes++;
		if ( 'stall' === self::$mode && self::$writes > 1 ) {
			return 0;
		}
		$length = min( 2, strlen( $data ) );
		self::$buffer .= substr( $data, 0, $length );
		$this->position += $length;
		return $length;
	}

	public function stream_tell() {
		return $this->position;
	}

	public function stream_eof() {
		return false;
	}

	public function stream_stat() {
		return array();
	}
};

if ( ! in_array( 'cmsapartial', stream_get_wrappers(), true ) ) {
	stream_wrapper_register( 'cmsapartial', 'CMSA_Test_Partial_Write_Stream' );
}

$backups = new CMSA_Backups();
$reflection = new ReflectionClass( $backups );
if ( ! $reflection->hasMethod( 'write_stream_all' ) ) {
	fwrite( STDERR, "backup-write-integrity-test: candidate lacks a complete-write helper.\n" );
	exit( 1 );
}

$method = $reflection->getMethod( 'write_stream_all' );
$method->setAccessible( true );

CMSA_Test_Partial_Write_Stream::$mode = 'progress';
$handle = fopen( 'cmsapartial://progress', 'wb' );
$complete = $method->invoke( $backups, $handle, 'abcdef' );
fclose( $handle );
if ( true !== $complete || 'abcdef' !== CMSA_Test_Partial_Write_Stream::$buffer || CMSA_Test_Partial_Write_Stream::$writes < 3 ) {
	fwrite( STDERR, "backup-write-integrity-test: progressive partial writes were not completed exactly.\n" );
	exit( 1 );
}

CMSA_Test_Partial_Write_Stream::$mode = 'stall';
$handle = fopen( 'cmsapartial://stall', 'wb' );
$stalled = $method->invoke( $backups, $handle, 'abcdef' );
fclose( $handle );
if ( false !== $stalled ) {
	fwrite( STDERR, "backup-write-integrity-test: stalled partial write was accepted.\n" );
	exit( 1 );
}

$source = file_get_contents( $lab . '/candidate/includes/class-cmsa-backups.php' );
$dump_start = strpos( $source, 'private function dump_database' );
$helper_start = strpos( $source, 'private function write_stream_all', $dump_start );
if ( false === $dump_start || false === $helper_start || $helper_start <= $dump_start ) {
	fwrite( STDERR, "backup-write-integrity-test: could not isolate database dump implementation.\n" );
	exit( 1 );
}
$dump_source = substr( $source, $dump_start, $helper_start - $dump_start );
if ( false !== strpos( $dump_source, 'fwrite(' ) ) {
	fwrite( STDERR, "backup-write-integrity-test: database dump still contains unchecked raw fwrite calls.\n" );
	exit( 1 );
}
if ( false === strpos( $dump_source, '$this->write_stream_all(' ) ) {
	fwrite( STDERR, "backup-write-integrity-test: database dump does not use complete-write enforcement.\n" );
	exit( 1 );
}

echo "backup-write-integrity-test: PASS progressive-partials=completed stalled-write=rejected database-dump=guarded\n";
