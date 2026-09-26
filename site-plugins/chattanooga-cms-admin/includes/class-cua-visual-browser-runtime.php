<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class CUA_Visual_Browser_Runtime {
	const TTL = 1800;
	const MAX_FULL_PAGE_DIMENSION = 16384;
	const MAX_FULL_PAGE_PIXELS = 50000000;

	public static function resolve_url( array $input ) {
		$has_path = isset( $input['path'] ) && '' !== trim( (string) $input['path'] );
		$has_url = isset( $input['url'] ) && '' !== trim( (string) $input['url'] );
		if ( $has_path && $has_url ) { return new WP_Error( 'cmsa_visual_target_ambiguous', 'Provide either path or url, not both.' ); }
		if ( ! $has_path && ! $has_url ) { return new WP_Error( 'cmsa_visual_target_required', 'A site-relative path or site-local absolute url is required.' ); }
		$home = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $home ) || empty( $home['host'] ) ) { return new WP_Error( 'cmsa_visual_home_url_invalid', 'The WordPress home URL could not be validated.' ); }
		if ( $has_path ) {
			$path = trim( (string) $input['path'] );
			if ( '' === $path || '/' !== $path[0] || str_contains( $path, "\n" ) || str_contains( $path, "\r" ) ) { return new WP_Error( 'cmsa_visual_path_invalid', 'The path must begin with /.' ); }
			$url = home_url( $path );
		} else {
			$url = esc_url_raw( trim( (string) $input['url'] ) );
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) { return new WP_Error( 'cmsa_visual_url_invalid', 'The requested URL is invalid.' ); }
		if ( ! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true ) ) { return new WP_Error( 'cmsa_visual_url_scheme_forbidden', 'Only HTTP and HTTPS URLs are allowed.' ); }
		$home_port = isset( $home['port'] ) ? (int) $home['port'] : self::default_port( (string) $home['scheme'] );
		$url_port = isset( $parts['port'] ) ? (int) $parts['port'] : self::default_port( (string) $parts['scheme'] );
		if ( strtolower( (string) $home['host'] ) !== strtolower( (string) $parts['host'] ) || $home_port !== $url_port ) { return new WP_Error( 'cmsa_visual_cross_origin_forbidden', 'Visual inspection is restricted to this WordPress site origin.' ); }
		return $url;
	}

	public static function start( $width, $height ) {
		$binary = self::browser_binary();
		if ( is_wp_error( $binary ) ) { return $binary; }
		if ( ! function_exists( 'exec' ) || self::function_disabled( 'exec' ) ) { return new WP_Error( 'cmsa_visual_exec_unavailable', 'The host does not permit the process execution required for headless browser inspection.' ); }
		$root = self::root();
		if ( is_wp_error( $root ) ) { return $root; }
		$id = bin2hex( random_bytes( 16 ) );
		$profile = trailingslashit( $root ) . $id;
		if ( ! wp_mkdir_p( $profile ) ) { return new WP_Error( 'cmsa_visual_session_dir_failed', 'The browser session directory could not be created.' ); }
		$port = self::free_port();
		if ( is_wp_error( $port ) ) { self::delete_tree( $profile ); return $port; }
		$log = trailingslashit( $profile ) . 'browser.log';
		$cmd = 'nohup ' . escapeshellarg( $binary )
			. ' --headless=new --disable-gpu --no-first-run --no-default-browser-check --hide-scrollbars'
			. ' --remote-debugging-address=127.0.0.1 --remote-debugging-port=' . (int) $port
			. ' --remote-allow-origins=http://127.0.0.1'
			. ' --user-data-dir=' . escapeshellarg( $profile )
			. ' --window-size=' . (int) $width . ',' . (int) $height
			. ' about:blank >' . escapeshellarg( $log ) . ' 2>&1 & echo $!';
		$out = array(); $status = 0; exec( $cmd, $out, $status );
		$pid = isset( $out[0] ) ? absint( trim( (string) $out[0] ) ) : 0;
		if ( 0 !== $status || $pid < 1 ) { self::delete_tree( $profile ); return new WP_Error( 'cmsa_visual_browser_start_failed', 'Headless Chromium could not be started.' ); }
		$session = array( 'session_id' => $id, 'pid' => $pid, 'port' => (int) $port, 'profile_dir' => $profile, 'width' => (int) $width, 'height' => (int) $height, 'created_at' => time(), 'updated_at' => time() );
		$ready = CUA_Visual_CDP::wait_until_ready( $session );
		if ( is_wp_error( $ready ) ) { self::close( $session ); return $ready; }
		$target = CUA_Visual_CDP::page_target( $session );
		if ( is_wp_error( $target ) ) { self::close( $session ); return $target; }
		$session['target_id'] = (string) $target['id'];
		return $session;
	}

	public static function navigate( array &$session, $url, $wait_ms ) {
		$result = CUA_Visual_CDP::command( $session, 'Page.navigate', array( 'url' => $url ) );
		if ( is_wp_error( $result ) ) { return $result; }
		self::sleep_ms( $wait_ms );
		$target = CUA_Visual_CDP::page_target( $session );
		if ( is_wp_error( $target ) ) { return $target; }
		$current = (string) ( $target['url'] ?? '' );
		$guard = self::resolve_url( array( 'url' => $current ) );
		if ( is_wp_error( $guard ) ) { return new WP_Error( 'cmsa_visual_redirect_forbidden', 'The page redirected outside the permitted WordPress site origin.' ); }
		$session['url'] = $current;
		$session['updated_at'] = time();
		return true;
	}

	public static function capture( array $session, $full_page, $mode ) {
		$params = array( 'format' => 'png', 'fromSurface' => true, 'captureBeyondViewport' => (bool) $full_page );
		$capture_width = (int) $session['width'];
		$capture_height = (int) $session['height'];
		if ( $full_page ) {
			$metrics = CUA_Visual_CDP::command( $session, 'Page.getLayoutMetrics' );
			if ( is_wp_error( $metrics ) ) { return $metrics; }
			$size = isset( $metrics['cssContentSize'] ) && is_array( $metrics['cssContentSize'] ) ? $metrics['cssContentSize'] : ( isset( $metrics['contentSize'] ) && is_array( $metrics['contentSize'] ) ? $metrics['contentSize'] : array() );
			$capture_width = (int) ceil( (float) ( $size['width'] ?? 0 ) );
			$capture_height = (int) ceil( (float) ( $size['height'] ?? 0 ) );
			if ( $capture_width < 1 || $capture_height < 1 ) { return new WP_Error( 'cmsa_visual_layout_invalid', 'Chromium did not return valid full-page dimensions.' ); }
			if ( $capture_width > self::MAX_FULL_PAGE_DIMENSION || $capture_height > self::MAX_FULL_PAGE_DIMENSION || ( $capture_width * $capture_height ) > self::MAX_FULL_PAGE_PIXELS ) { return new WP_Error( 'cmsa_visual_full_page_too_large', 'The rendered page exceeds the full-page screenshot safety limit.' ); }
			$params['clip'] = array( 'x' => 0, 'y' => 0, 'width' => $capture_width, 'height' => $capture_height, 'scale' => 1 );
		}
		$result = CUA_Visual_CDP::command( $session, 'Page.captureScreenshot', $params );
		if ( is_wp_error( $result ) ) { return $result; }
		$data = (string) ( $result['data'] ?? '' );
		$bytes = base64_decode( $data, true );
		if ( false === $bytes || 8 > strlen( $bytes ) || "\x89PNG\r\n\x1a\n" !== substr( $bytes, 0, 8 ) ) { return new WP_Error( 'cmsa_visual_capture_invalid', 'Chromium did not return a valid PNG screenshot.' ); }
		$meta = array( 'mode' => $mode, 'session_id' => (string) $session['session_id'], 'url' => (string) ( $session['url'] ?? '' ), 'width' => (int) $session['width'], 'height' => (int) $session['height'], 'capture_width' => $capture_width, 'capture_height' => $capture_height, 'full_page' => (bool) $full_page, 'captured_at_gmt' => gmdate( 'c' ) );
		return array( '__mcp_visual' => array( 'mimeType' => 'image/png', 'data' => $data ), 'metadata' => $meta );
	}

	public static function save( array $session ) {
		$root = self::root(); if ( is_wp_error( $root ) ) { return false; }
		return false !== file_put_contents( trailingslashit( $root ) . $session['session_id'] . '.json', wp_json_encode( $session ), LOCK_EX );
	}

	public static function load( $id ) {
		$root = self::root(); if ( is_wp_error( $root ) ) { return $root; }
		$file = trailingslashit( $root ) . $id . '.json';
		if ( ! is_file( $file ) ) { return new WP_Error( 'cmsa_visual_session_not_found', 'The live browser session does not exist or has expired.' ); }
		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $data ) || (string) ( $data['session_id'] ?? '' ) !== $id ) { return new WP_Error( 'cmsa_visual_session_invalid', 'The stored live browser session record is invalid.' ); }
		if ( time() - (int) ( $data['updated_at'] ?? 0 ) > self::TTL ) { self::close( $data ); self::delete_record( $id ); return new WP_Error( 'cmsa_visual_session_expired', 'The live browser session has expired.' ); }
		return $data;
	}

	public static function cleanup() {
		$root = self::root(); if ( is_wp_error( $root ) ) { return; }
		foreach ( glob( trailingslashit( $root ) . '*.json' ) ?: array() as $file ) {
			$data = json_decode( (string) @file_get_contents( $file ), true );
			if ( is_array( $data ) && time() - (int) ( $data['updated_at'] ?? 0 ) > self::TTL ) { self::close( $data ); @unlink( $file ); }
		}
	}

	public static function close( array $session ) {
		if ( ! empty( $session['port'] ) ) { CUA_Visual_CDP::command( $session, 'Browser.close' ); }
		if ( ! empty( $session['pid'] ) && function_exists( 'exec' ) && ! self::function_disabled( 'exec' ) ) { @exec( 'kill ' . absint( $session['pid'] ) . ' >/dev/null 2>&1' ); }
		if ( ! empty( $session['profile_dir'] ) ) { self::delete_tree( (string) $session['profile_dir'] ); }
	}

	public static function delete_record( $id ) {
		$root = self::root(); if ( is_wp_error( $root ) ) { return; }
		$file = trailingslashit( $root ) . $id . '.json'; if ( is_file( $file ) ) { @unlink( $file ); }
	}

	public static function sleep_ms( $ms ) { $ms = max( 0, min( 5000, (int) $ms ) ); if ( $ms ) { usleep( $ms * 1000 ); } }

	private static function browser_binary() {
		$configured = defined( 'CMSA_BROWSER_EXECUTABLE' ) ? trim( (string) CMSA_BROWSER_EXECUTABLE ) : trim( (string) getenv( 'CMSA_BROWSER_EXECUTABLE' ) );
		$candidates = array_filter( array( $configured, '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/local/bin/chromium', '/usr/local/bin/google-chrome' ) );
		foreach ( array_unique( $candidates ) as $candidate ) { if ( is_file( $candidate ) && is_executable( $candidate ) ) { return $candidate; } }
		return new WP_Error( 'cmsa_visual_browser_unavailable', 'No supported local Chromium executable was found. Set CMSA_BROWSER_EXECUTABLE to its absolute path.' );
	}

	private static function root() {
		$root = trailingslashit( get_temp_dir() ) . 'chattanooga-cms-admin-browser';
		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) { return new WP_Error( 'cmsa_visual_temp_unavailable', 'The server temp directory is not writable for browser inspection.' ); }
		return $root;
	}

	private static function free_port() {
		$errno = 0; $errstr = ''; $server = @stream_socket_server( 'tcp://127.0.0.1:0', $errno, $errstr );
		if ( false === $server ) { return new WP_Error( 'cmsa_visual_port_unavailable', 'A local browser debugging port could not be reserved.' ); }
		$name = stream_socket_get_name( $server, false ); fclose( $server ); $pos = strrpos( (string) $name, ':' ); $port = false === $pos ? 0 : absint( substr( (string) $name, $pos + 1 ) );
		return $port > 0 ? $port : new WP_Error( 'cmsa_visual_port_invalid', 'The local browser debugging port could not be determined.' );
	}

	private static function function_disabled( $name ) { return in_array( $name, array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true ); }
	private static function default_port( $scheme ) { return 'https' === strtolower( (string) $scheme ) ? 443 : 80; }

	private static function delete_tree( $path ) {
		$root = self::root(); if ( is_wp_error( $root ) ) { return; }
		$rr = realpath( $root ); $rp = realpath( $path );
		if ( false === $rr || false === $rp || 0 !== strpos( $rp, $rr . DIRECTORY_SEPARATOR ) ) { return; }
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $rp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $item ) { $item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() ); }
		@rmdir( $rp );
	}
}
