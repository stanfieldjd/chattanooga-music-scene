<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class CUA_Visual_Browser {
	const CATEGORY = 'chattanooga-cms-admin';
	const PREFIX = 'chattanooga-cms-admin/';
	const DEFAULT_WIDTH = 1440;
	const DEFAULT_HEIGHT = 900;
	const MAX_WAIT_MS = 5000;

	public static function bootstrap() {
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'inject_mcp_image_content' ), 50, 3 );
	}

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) { return; }
		wp_register_ability(
			self::PREFIX . 'capture-page-screenshot',
			array(
				'label' => __( 'Capture live page screenshot', 'chattanooga-cms-admin' ),
				'description' => __( 'Renders a site-local page in headless Chromium and returns the current pixels as MCP image content. This is the stateless fallback visual-inspection path.', 'chattanooga-cms-admin' ),
				'category' => self::CATEGORY,
				'input_schema' => self::capture_schema(),
				'output_schema' => array( 'type' => 'object' ),
				'execute_callback' => array( __CLASS__, 'capture_page_screenshot' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta' => self::readonly_meta(),
			)
		);
		wp_register_ability(
			self::PREFIX . 'browser-session',
			array(
				'label' => __( 'Inspect live page in browser session', 'chattanooga-cms-admin' ),
				'description' => __( 'Maintains a temporary site-local headless-browser session for visual inspection. Supports open, navigate, capture, scroll, resize, refresh, and close; it does not click controls or submit forms.', 'chattanooga-cms-admin' ),
				'category' => self::CATEGORY,
				'input_schema' => self::session_schema(),
				'output_schema' => array( 'type' => 'object' ),
				'execute_callback' => array( __CLASS__, 'browser_session' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta' => self::readonly_meta(),
			)
		);
	}

	public static function capture_page_screenshot( $input ) {
		$input = is_array( $input ) ? $input : array();
		$url = CUA_Visual_Browser_Runtime::resolve_url( $input );
		if ( is_wp_error( $url ) ) { return $url; }
		$session = CUA_Visual_Browser_Runtime::start( self::number( $input, 'width', self::DEFAULT_WIDTH, 320, 2560 ), self::number( $input, 'height', self::DEFAULT_HEIGHT, 240, 2000 ) );
		if ( is_wp_error( $session ) ) { return $session; }
		try {
			$nav = CUA_Visual_Browser_Runtime::navigate( $session, $url, self::number( $input, 'wait_ms', 1000, 0, self::MAX_WAIT_MS ) );
			if ( is_wp_error( $nav ) ) { return $nav; }
			return CUA_Visual_Browser_Runtime::capture( $session, ! empty( $input['full_page'] ), 'fallback' );
		} finally {
			CUA_Visual_Browser_Runtime::close( $session );
		}
	}

	public static function browser_session( $input ) {
		$input = is_array( $input ) ? $input : array();
		CUA_Visual_Browser_Runtime::cleanup();
		$action = sanitize_key( (string) ( $input['action'] ?? '' ) );
		if ( 'open' === $action ) {
			$url = CUA_Visual_Browser_Runtime::resolve_url( $input );
			if ( is_wp_error( $url ) ) { return $url; }
			$session = CUA_Visual_Browser_Runtime::start( self::number( $input, 'width', self::DEFAULT_WIDTH, 320, 2560 ), self::number( $input, 'height', self::DEFAULT_HEIGHT, 240, 2000 ) );
			if ( is_wp_error( $session ) ) { return $session; }
			$nav = CUA_Visual_Browser_Runtime::navigate( $session, $url, self::number( $input, 'wait_ms', 1000, 0, self::MAX_WAIT_MS ) );
			if ( is_wp_error( $nav ) ) { CUA_Visual_Browser_Runtime::close( $session ); return $nav; }
			CUA_Visual_Browser_Runtime::save( $session );
			return CUA_Visual_Browser_Runtime::capture( $session, ! empty( $input['full_page'] ), 'live' );
		}

		$id = strtolower( trim( (string) ( $input['session_id'] ?? '' ) ) );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) { return new WP_Error( 'cmsa_visual_session_required', 'A valid browser session_id is required.' ); }
		$session = CUA_Visual_Browser_Runtime::load( $id );
		if ( is_wp_error( $session ) ) { return $session; }
		if ( 'close' === $action ) {
			CUA_Visual_Browser_Runtime::close( $session ); CUA_Visual_Browser_Runtime::delete_record( $id );
			return array( 'closed' => true, 'session_id' => $id, 'mode' => 'live' );
		}
		if ( 'navigate' === $action ) {
			$url = CUA_Visual_Browser_Runtime::resolve_url( $input );
			if ( is_wp_error( $url ) ) { return $url; }
			$result = CUA_Visual_Browser_Runtime::navigate( $session, $url, self::number( $input, 'wait_ms', 750, 0, self::MAX_WAIT_MS ) );
			if ( is_wp_error( $result ) ) { return $result; }
		} elseif ( 'refresh' === $action ) {
			$result = CUA_Visual_CDP::command( $session, 'Page.reload', array( 'ignoreCache' => false ) );
			if ( is_wp_error( $result ) ) { return $result; }
			CUA_Visual_Browser_Runtime::sleep_ms( self::number( $input, 'wait_ms', 750, 0, self::MAX_WAIT_MS ) );
		} elseif ( 'scroll' === $action ) {
			$x = self::number( $input, 'scroll_x', 0, -10000, 10000 ); $y = self::number( $input, 'scroll_y', 0, -100000, 100000 );
			$result = CUA_Visual_CDP::command( $session, 'Runtime.evaluate', array( 'expression' => 'window.scrollBy(' . $x . ',' . $y . ');({x:window.scrollX,y:window.scrollY})', 'returnByValue' => true ) );
			if ( is_wp_error( $result ) ) { return $result; }
			CUA_Visual_Browser_Runtime::sleep_ms( self::number( $input, 'wait_ms', 250, 0, self::MAX_WAIT_MS ) );
		} elseif ( 'resize' === $action ) {
			$width = self::number( $input, 'width', (int) $session['width'], 320, 2560 ); $height = self::number( $input, 'height', (int) $session['height'], 240, 2000 );
			$result = CUA_Visual_CDP::command( $session, 'Emulation.setDeviceMetricsOverride', array( 'width' => $width, 'height' => $height, 'deviceScaleFactor' => 1, 'mobile' => false ) );
			if ( is_wp_error( $result ) ) { return $result; }
			$session['width'] = $width; $session['height'] = $height;
		} elseif ( 'capture' !== $action ) {
			return new WP_Error( 'cmsa_visual_invalid_action', 'The browser-session action is not supported.' );
		}
		$session['updated_at'] = time(); CUA_Visual_Browser_Runtime::save( $session );
		return CUA_Visual_Browser_Runtime::capture( $session, ! empty( $input['full_page'] ), 'live' );
	}

	public static function inject_mcp_image_content( $response, $server, $request ) {
		if ( ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request || '/chattanooga-cms-admin/v1/mcp' !== $request->get_route() ) { return $response; }
		$payload = $request->get_json_params(); $tool = is_array( $payload ) ? (string) ( $payload['params']['name'] ?? '' ) : '';
		if ( 0 === strpos( $tool, 'chattanooga_music_scene.' ) ) { $tool = substr( $tool, strlen( 'chattanooga_music_scene.' ) ); }
		if ( ! is_array( $payload ) || 'tools/call' !== ( $payload['method'] ?? '' ) || 'cmsa.read-bridge' !== $tool ) { return $response; }
		$data = $response->get_data();
		$visual = is_array( $data ) ? ( $data['result']['structuredContent']['result']['__mcp_visual'] ?? null ) : null;
		if ( ! is_array( $visual ) || 'image/png' !== ( $visual['mimeType'] ?? '' ) || empty( $visual['data'] ) ) { return $response; }
		$bytes = base64_decode( (string) $visual['data'], true );
		if ( false === $bytes || 8 > strlen( $bytes ) || "\x89PNG\r\n\x1a\n" !== substr( $bytes, 0, 8 ) ) { return $response; }
		unset( $data['result']['structuredContent']['result']['__mcp_visual'] );
		$summary = wp_json_encode( $data['result']['structuredContent'], JSON_UNESCAPED_SLASHES );
		$data['result']['content'] = array(
			array( 'type' => 'text', 'text' => is_string( $summary ) ? $summary : 'Visual inspection capture completed.' ),
			array( 'type' => 'image', 'data' => (string) $visual['data'], 'mimeType' => 'image/png' ),
		);
		$response->set_data( $data ); return $response;
	}

	private static function readonly_meta() {
		return array( 'public' => true, 'show_in_rest' => false, 'mcp' => array( 'public' => true ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => false, 'open_world' => true ) );
	}
	private static function capture_schema() {
		return array( 'type' => 'object', 'properties' => array( 'path' => array( 'type' => 'string', 'maxLength' => 1024 ), 'url' => array( 'type' => 'string', 'maxLength' => 2048 ), 'width' => array( 'type' => 'integer', 'minimum' => 320, 'maximum' => 2560, 'default' => self::DEFAULT_WIDTH ), 'height' => array( 'type' => 'integer', 'minimum' => 240, 'maximum' => 2000, 'default' => self::DEFAULT_HEIGHT ), 'full_page' => array( 'type' => 'boolean', 'default' => false ), 'wait_ms' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => self::MAX_WAIT_MS, 'default' => 1000 ) ), 'additionalProperties' => false );
	}
	private static function session_schema() {
		return array( 'type' => 'object', 'properties' => array( 'action' => array( 'type' => 'string', 'enum' => array( 'open', 'navigate', 'capture', 'scroll', 'resize', 'refresh', 'close' ) ), 'session_id' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{32}$' ), 'path' => array( 'type' => 'string', 'maxLength' => 1024 ), 'url' => array( 'type' => 'string', 'maxLength' => 2048 ), 'width' => array( 'type' => 'integer', 'minimum' => 320, 'maximum' => 2560 ), 'height' => array( 'type' => 'integer', 'minimum' => 240, 'maximum' => 2000 ), 'scroll_x' => array( 'type' => 'integer', 'minimum' => -10000, 'maximum' => 10000 ), 'scroll_y' => array( 'type' => 'integer', 'minimum' => -100000, 'maximum' => 100000 ), 'full_page' => array( 'type' => 'boolean', 'default' => false ), 'wait_ms' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => self::MAX_WAIT_MS ) ), 'required' => array( 'action' ), 'additionalProperties' => false );
	}
	private static function number( array $input, $key, $default, $min, $max ) { $value = isset( $input[ $key ] ) ? (int) $input[ $key ] : (int) $default; return max( (int) $min, min( (int) $max, $value ) ); }
}
