<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_MCP_Settings_Page {
	const OPTION_ENABLED = 'cua_mcp_enabled';
	const OPTION_ORIGINS = 'cua_mcp_allowed_origins';
	const OPTION_AUTH_MODE = 'cua_mcp_auth_mode';
	const OPTION_MANUAL_TOKEN = 'cua_mcp_manual_token';
	const AUTH_MODE_OAUTH = 'oauth';
	const AUTH_MODE_MANUAL = 'manual';
	const PAGE_SLUG      = 'chattanooga-cms-admin-mcp';

	public static function register_admin_hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_menu() {
		add_options_page(
			__( 'Chattanooga MCP Settings', 'chattanooga-cms-admin' ),
			__( 'Chattanooga MCP', 'chattanooga-cms-admin' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		add_settings_section(
			'cua_mcp_connection',
			__( 'MCP connection', 'chattanooga-cms-admin' ),
			array( __CLASS__, 'render_connection_section' ),
			self::PAGE_SLUG
		);
		add_settings_field(
			self::OPTION_ENABLED,
			__( 'MCP endpoint', 'chattanooga-cms-admin' ),
			array( __CLASS__, 'render_enabled_field' ),
			self::PAGE_SLUG,
			'cua_mcp_connection'
		);
		add_settings_field(
			self::OPTION_ORIGINS,
			__( 'Allowed browser origins', 'chattanooga-cms-admin' ),
			array( __CLASS__, 'render_origins_field' ),
			self::PAGE_SLUG,
			'cua_mcp_connection'
		);
		add_settings_field(
			self::OPTION_AUTH_MODE,
			__( 'Authorization mode', 'chattanooga-cms-admin' ),
			array( __CLASS__, 'render_auth_mode_field' ),
			self::PAGE_SLUG,
			'cua_mcp_connection'
		);
		add_settings_field(
			self::OPTION_MANUAL_TOKEN,
			__( 'Manual authorization token', 'chattanooga-cms-admin' ),
			array( __CLASS__, 'render_manual_token_field' ),
			self::PAGE_SLUG,
			'cua_mcp_connection'
		);

		register_setting(
			'chattanooga_mcp',
			self::OPTION_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_enabled' ),
				'default'           => true,
			)
		);
		register_setting(
			'chattanooga_mcp',
			self::OPTION_ORIGINS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_origins' ),
				'default'           => self::default_origins(),
			)
		);
		register_setting(
			'chattanooga_mcp',
			self::OPTION_AUTH_MODE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_auth_mode' ),
				'default'           => self::AUTH_MODE_OAUTH,
			)
		);
		register_setting(
			'chattanooga_mcp',
			self::OPTION_MANUAL_TOKEN,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_manual_token' ),
				'default'           => array(),
			)
		);
	}

	public static function is_enabled() {
		return (bool) get_option( self::OPTION_ENABLED, true );
	}

	public static function auth_mode() {
		return self::sanitize_auth_mode( get_option( self::OPTION_AUTH_MODE, self::AUTH_MODE_OAUTH ) );
	}

	public static function is_manual_auth() {
		return self::AUTH_MODE_MANUAL === self::auth_mode();
	}

	public static function allowed_origins() {
		$stored = get_option( self::OPTION_ORIGINS, null );
		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return self::default_origins();
		}
		return self::sanitize_origins( $stored );
	}

	public static function is_origin_allowed( $origin ) {
		$candidate = self::normalize_origin( $origin );
		if ( '' === $candidate ) {
			return false;
		}
		return in_array( $candidate, self::allowed_origins(), true );
	}

	public static function sanitize_enabled( $value ) {
		return (bool) $value;
	}

	public static function sanitize_auth_mode( $value ) {
		return in_array( (string) $value, array( self::AUTH_MODE_OAUTH, self::AUTH_MODE_MANUAL ), true ) ? (string) $value : self::AUTH_MODE_OAUTH;
	}

	public static function sanitize_manual_token( $value ) {
		if ( is_array( $value ) ) {
			if ( ! empty( $value['digest'] ) && preg_match( '/^[a-f0-9]{64}$/', (string) $value['digest'] ) && ! empty( $value['user_id'] ) ) {
				return array(
					'digest'     => (string) $value['digest'],
					'user_id'    => (int) $value['user_id'],
					'created_at' => isset( $value['created_at'] ) ? (int) $value['created_at'] : time(),
				);
			}
			return array();
		}
		$token = trim( (string) $value );
		$existing = get_option( self::OPTION_MANUAL_TOKEN, array() );
		if ( '' === $token ) {
			return is_array( $existing ) ? $existing : array();
		}
		if ( strlen( $token ) < 32 || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return is_array( $existing ) ? $existing : array();
		}
		return array(
			'digest'     => hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ),
			'user_id'    => get_current_user_id(),
			'created_at' => time(),
		);
	}

	public static function authenticate_manual_token( $token ) {
		$record = get_option( self::OPTION_MANUAL_TOKEN, array() );
		if ( ! is_array( $record ) || empty( $record['digest'] ) || empty( $record['user_id'] ) ) {
			return false;
		}
		$digest = hash_hmac( 'sha256', trim( (string) $token ), wp_salt( 'auth' ) );
		if ( ! hash_equals( (string) $record['digest'], $digest ) ) {
			return false;
		}
		return (int) $record['user_id'];
	}

	public static function sanitize_origins( $value ) {
		$values = is_array( $value ) ? $value : preg_split( '/\r?\n/', (string) $value );
		$origins = array();
		foreach ( $values as $origin ) {
			$normalized = self::normalize_origin( $origin );
			if ( '' !== $normalized ) {
				$origins[] = $normalized;
			}
		}
		$origins = array_values( array_unique( $origins ) );
		return empty( $origins ) ? self::default_origins() : $origins;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Chattanooga MCP settings.', 'chattanooga-cms-admin' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Chattanooga MCP Settings', 'chattanooga-cms-admin' ); ?></h1>
			<p><?php echo esc_html__( 'Configure the native WordPress MCP endpoint without changing its authentication or administrator permission boundary.', 'chattanooga-cms-admin' ); ?></p>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'chattanooga_mcp' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function render_connection_section() {
		printf(
			'<p>%s</p><p><strong>%s</strong> <code>%s</code><br /><strong>%s</strong> <code>%s</code></p>',
			esc_html__( 'The endpoint URL and protocol are fixed by the plugin. Authentication remains WordPress authentication and the endpoint still requires manage_options.', 'chattanooga-cms-admin' ),
			esc_html__( 'Endpoint:', 'chattanooga-cms-admin' ),
		esc_url( rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE ) ),
		esc_html__( 'Protocol:', 'chattanooga-cms-admin' ),
		esc_html( CUA_MCP_Server::PROTOCOL_VERSION )
		);
	}

	public static function render_enabled_field() {
		printf(
			'<input type="hidden" name="%1$s" value="0" /><label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_ENABLED ),
		checked( self::is_enabled(), true, false ),
			esc_html__( 'Enable the native MCP endpoint', 'chattanooga-cms-admin' )
		);
	}

	public static function render_auth_mode_field() {
		$mode = self::auth_mode();
		foreach ( array( self::AUTH_MODE_OAUTH => __( 'Automatic OAuth authorization', 'chattanooga-cms-admin' ), self::AUTH_MODE_MANUAL => __( 'Manual authorization token', 'chattanooga-cms-admin' ) ) as $value => $label ) {
			printf(
				'<label style="display:block;margin-bottom:6px"><input type="radio" name="%1$s" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( self::OPTION_AUTH_MODE ),
				esc_attr( $value ),
				checked( $mode, $value, false ),
				esc_html( $label )
			);
		}
		printf( '<p class="description">%s</p>', esc_html__( 'Manual mode disables OAuth registration and token issuance. The MCP endpoint then expects Authorization: Bearer with the configured manual token.', 'chattanooga-cms-admin' ) );
	}

	public static function render_manual_token_field() {
		printf(
			'<input type="password" class="regular-text" name="%1$s" value="" autocomplete="new-password" aria-describedby="%1$s-description" />',
			esc_attr( self::OPTION_MANUAL_TOKEN )
		);
		printf(
			'<p id="%1$s-description" class="description">%2$s</p>',
			esc_attr( self::OPTION_MANUAL_TOKEN . '-description' ),
			esc_html__( 'Enter a new high-entropy token of at least 32 characters and save. The token is stored only as a digest and is never displayed again. Leave blank to keep the existing token.', 'chattanooga-cms-admin' )
		);
	}

	public static function render_origins_field() {
		printf(
			'<textarea class="large-text code" name="%1$s[]" rows="5" aria-describedby="%1$s-description">%2$s</textarea>',
			esc_attr( self::OPTION_ORIGINS ),
			esc_textarea( implode( "\n", self::allowed_origins() ) ),
		);
		printf(
			'<p id="%1$s-description" class="description">%2$s</p>',
			esc_attr( self::OPTION_ORIGINS . '-description' ),
			esc_html__( 'One origin per line, including scheme (for example, https://example.com). Any path is stripped because browser origins do not include paths. Empty or invalid entries restore the site’s own origins.', 'chattanooga-cms-admin' )
		);
	}

	private static function default_origins() {
		$origins = array();
		foreach ( array( home_url( '/' ), site_url( '/' ) ) as $url ) {
			$origin = self::normalize_origin( $url );
			if ( '' !== $origin ) {
				$origins[] = $origin;
			}
		}
		return array_values( array_unique( $origins ) );
	}

	private static function normalize_origin( $url ) {
		$parts = parse_url( trim( (string) $url ) );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( (string) $parts['host'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}

		$origin = $scheme . '://' . $host;
		if ( isset( $parts['port'] ) ) {
			$port = (int) $parts['port'];
			if ( ( 'http' === $scheme && 80 !== $port ) || ( 'https' === $scheme && 443 !== $port ) ) {
				$origin .= ':' . $port;
			}
		}
		return $origin;
	}
}
