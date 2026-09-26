<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class CUA_Visual_CDP {
	public static function command( array $session, $method, array $params = array() ) {
		$target = self::page_target( $session );
		if ( is_wp_error( $target ) ) { return $target; }
		$ws = (string) ( $target['webSocketDebuggerUrl'] ?? '' );
		if ( '' === $ws ) { return new WP_Error( 'cmsa_visual_cdp_target_invalid', 'Chromium did not expose a DevTools WebSocket URL.' ); }
		$socket = self::connect( $ws );
		if ( is_wp_error( $socket ) ) { return $socket; }
		$id = random_int( 1, 1000000000 );
		$payload = wp_json_encode( array( 'id' => $id, 'method' => (string) $method, 'params' => $params ), JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $payload ) || ! self::send( $socket, $payload ) ) {
			fclose( $socket );
			return new WP_Error( 'cmsa_visual_cdp_send_failed', 'The DevTools command could not be sent.' );
		}
		$deadline = microtime( true ) + 10;
		while ( microtime( true ) < $deadline ) {
			$message = self::receive( $socket );
			if ( is_wp_error( $message ) ) { fclose( $socket ); return $message; }
			if ( null === $message ) { continue; }
			$decoded = json_decode( $message, true );
			if ( ! is_array( $decoded ) || (int) ( $decoded['id'] ?? 0 ) !== $id ) { continue; }
			fclose( $socket );
			if ( isset( $decoded['error'] ) ) { return new WP_Error( 'cmsa_visual_cdp_error', 'Chromium rejected the visual-inspection command.' ); }
			return is_array( $decoded['result'] ?? null ) ? $decoded['result'] : array();
		}
		fclose( $socket );
		return new WP_Error( 'cmsa_visual_cdp_timeout', 'Chromium did not answer before the DevTools timeout.' );
	}

	public static function page_target( array $session ) {
		$list = self::json_get( (int) $session['port'], '/json/list' );
		if ( is_wp_error( $list ) ) { return $list; }
		$wanted = (string) ( $session['target_id'] ?? '' );
		foreach ( $list as $target ) {
			if ( ! is_array( $target ) || 'page' !== (string) ( $target['type'] ?? '' ) ) { continue; }
			if ( '' === $wanted || $wanted === (string) ( $target['id'] ?? '' ) ) { return $target; }
		}
		return new WP_Error( 'cmsa_visual_page_target_missing', 'The live Chromium page target no longer exists.' );
	}

	public static function wait_until_ready( array $session ) {
		for ( $i = 0; $i < 40; ++$i ) {
			$result = self::json_get( (int) $session['port'], '/json/version', 0.5 );
			if ( ! is_wp_error( $result ) ) { return true; }
			usleep( 100000 );
		}
		return new WP_Error( 'cmsa_visual_browser_not_ready', 'Chromium started but its local DevTools endpoint did not become ready.' );
	}

	private static function json_get( $port, $path, $timeout = 2 ) {
		$response = wp_remote_get( 'http://127.0.0.1:' . (int) $port . $path, array( 'timeout' => $timeout, 'redirection' => 0 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'cmsa_visual_session_unreachable', 'The local Chromium DevTools endpoint is not reachable.' );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : new WP_Error( 'cmsa_visual_cdp_json_invalid', 'Chromium returned invalid DevTools JSON.' );
	}

	private static function connect( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! in_array( (string) ( $parts['host'] ?? '' ), array( '127.0.0.1', 'localhost' ), true ) || empty( $parts['port'] ) || empty( $parts['path'] ) ) {
			return new WP_Error( 'cmsa_visual_ws_url_invalid', 'Chromium returned an invalid local DevTools WebSocket URL.' );
		}
		$errno = 0; $errstr = '';
		$socket = @stream_socket_client( 'tcp://127.0.0.1:' . (int) $parts['port'], $errno, $errstr, 3, STREAM_CLIENT_CONNECT );
		if ( false === $socket ) { return new WP_Error( 'cmsa_visual_ws_connect_failed', 'The local DevTools WebSocket could not be reached.' ); }
		stream_set_timeout( $socket, 2 );
		$key = base64_encode( random_bytes( 16 ) );
		$path = (string) $parts['path'] . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		$request = "GET {$path} HTTP/1.1\r\nHost: 127.0.0.1:{$parts['port']}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {$key}\r\nSec-WebSocket-Version: 13\r\nOrigin: http://127.0.0.1\r\n\r\n";
		fwrite( $socket, $request );
		$headers = '';
		while ( ! feof( $socket ) && false === strpos( $headers, "\r\n\r\n" ) && strlen( $headers ) < 16384 ) { $headers .= (string) fgets( $socket, 2048 ); }
		if ( ! preg_match( '#^HTTP/1\.[01] 101 #', $headers ) ) { fclose( $socket ); return new WP_Error( 'cmsa_visual_ws_handshake_failed', 'Chromium rejected the DevTools WebSocket handshake.' ); }
		return $socket;
	}

	private static function send( $socket, $payload ) {
		$payload = (string) $payload; $length = strlen( $payload ); $mask = random_bytes( 4 ); $header = chr( 0x81 );
		if ( $length <= 125 ) { $header .= chr( 0x80 | $length ); }
		elseif ( $length <= 65535 ) { $header .= chr( 0x80 | 126 ) . pack( 'n', $length ); }
		else { $header .= chr( 0x80 | 127 ) . pack( 'NN', 0, $length ); }
		$masked = '';
		for ( $i = 0; $i < $length; ++$i ) { $masked .= $payload[ $i ] ^ $mask[ $i % 4 ]; }
		return false !== fwrite( $socket, $header . $mask . $masked );
	}

	private static function receive( $socket ) {
		$message = ''; $started = false;
		for ( $i = 0; $i < 1024; ++$i ) {
			$frame = self::frame( $socket );
			if ( null === $frame || is_wp_error( $frame ) ) { return $frame; }
			$opcode = (int) $frame['opcode'];
			if ( 0x8 === $opcode ) { return new WP_Error( 'cmsa_visual_ws_closed', 'Chromium closed the DevTools WebSocket.' ); }
			if ( 0x9 === $opcode || 0xA === $opcode ) { continue; }
			if ( 0x1 === $opcode ) { $message = (string) $frame['payload']; $started = true; }
			elseif ( 0x0 === $opcode && $started ) { $message .= (string) $frame['payload']; }
			else { continue; }
			if ( ! empty( $frame['fin'] ) ) { return $message; }
		}
		return new WP_Error( 'cmsa_visual_ws_fragment_limit', 'The DevTools WebSocket exceeded the frame-fragment safety limit.' );
	}

	private static function frame( $socket ) {
		$head = self::read_exact( $socket, 2 );
		if ( null === $head || is_wp_error( $head ) ) { return $head; }
		$b1 = ord( $head[0] ); $b2 = ord( $head[1] ); $length = $b2 & 0x7f;
		if ( 126 === $length ) { $ext = self::read_exact( $socket, 2 ); if ( null === $ext || is_wp_error( $ext ) ) { return new WP_Error( 'cmsa_visual_ws_read_failed', 'Could not read a DevTools frame length.' ); } $length = unpack( 'nlen', $ext )['len']; }
		elseif ( 127 === $length ) { $ext = self::read_exact( $socket, 8 ); if ( null === $ext || is_wp_error( $ext ) ) { return new WP_Error( 'cmsa_visual_ws_read_failed', 'Could not read a DevTools frame length.' ); } $parts = unpack( 'Nhigh/Nlow', $ext ); if ( 0 !== (int) $parts['high'] ) { return new WP_Error( 'cmsa_visual_ws_frame_too_large', 'The DevTools frame is too large.' ); } $length = (int) $parts['low']; }
		if ( $length > 32 * 1024 * 1024 ) { return new WP_Error( 'cmsa_visual_ws_frame_too_large', 'The DevTools frame exceeded 32 MiB.' ); }
		$mask = ( $b2 & 0x80 ) ? self::read_exact( $socket, 4 ) : '';
		if ( is_wp_error( $mask ) || null === $mask ) { return new WP_Error( 'cmsa_visual_ws_read_failed', 'Could not read a DevTools frame mask.' ); }
		$payload = 0 === $length ? '' : self::read_exact( $socket, $length );
		if ( is_wp_error( $payload ) || null === $payload ) { return new WP_Error( 'cmsa_visual_ws_read_failed', 'Could not read a DevTools frame payload.' ); }
		if ( '' !== $mask ) { $decoded = ''; for ( $i = 0; $i < $length; ++$i ) { $decoded .= $payload[ $i ] ^ $mask[ $i % 4 ]; } $payload = $decoded; }
		return array( 'fin' => (bool) ( $b1 & 0x80 ), 'opcode' => $b1 & 0x0f, 'payload' => $payload );
	}

	private static function read_exact( $socket, $length ) {
		$data = '';
		while ( strlen( $data ) < $length && ! feof( $socket ) ) {
			$chunk = fread( $socket, $length - strlen( $data ) );
			if ( false === $chunk ) { return new WP_Error( 'cmsa_visual_ws_read_failed', 'The DevTools WebSocket could not be read.' ); }
			if ( '' === $chunk ) { $meta = stream_get_meta_data( $socket ); if ( ! empty( $meta['timed_out'] ) ) { return null; } usleep( 10000 ); continue; }
			$data .= $chunk;
		}
		return strlen( $data ) === $length ? $data : null;
	}
}
