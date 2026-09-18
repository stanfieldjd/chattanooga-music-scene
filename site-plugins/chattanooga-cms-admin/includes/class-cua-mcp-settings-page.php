<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_MCP_Settings_Page {
	const OPTION_ENABLED = 'cua_mcp_enabled';
	const OPTION_ORIGINS = 'cua_mcp_allowed_origins';
	const OPTION_AUTH_MODE = 'cua_mcp_auth_mode';
	const OPTION_MANUAL_TOKEN = 'cua_mcp_manual_token';
	const OPTION_RATE_LIMIT = 'cua_mcp_rate_limit';
	const OPTION_READ_ONLY = 'cua_mcp_read_only';
	const OPTION_ENVIRONMENT = 'cua_mcp_environment';
	const OPTION_ALLOWED_TOOLS = 'cua_mcp_allowed_tools';
	const AUTH_MODE_OAUTH = 'oauth';
	const AUTH_MODE_MANUAL = 'manual';
	const DEFAULT_RATE_LIMIT = 60;
	const MAX_RATE_LIMIT = 600;
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
		add_settings_field(
			self::OPTION_RATE_LIMIT,
			__( 'Requests per minute', 'chattanooga-cms-admin' ),
			array( __CLASS__, 'render_rate_limit_field' ),
			self::PAGE_SLUG,
			'cua_mcp_connection'
		);
		add_settings_field(
			self::OPTION_READ_ONLY,
			__( 'Read-only mode', 'chattanooga-cms-admin' ),
			array( __CLASS__, 'render_read_only_field' ),
			self::PAGE_SLUG,
			'cua_mcp_connection'
		);
		add_settings_field(
			self::OPTION_ENVIRONMENT,
			__( 'Environment identifier', 'chattanooga-cms-admin' ),
			array( __CLASS__, 'render_environment_field' ),
			self::PAGE_SLUG,
			'cua_mcp_connection'
		);
		add_settings_field(
			self::OPTION_ALLOWED_TOOLS,
			__( 'Enabled site capabilities', 'chattanooga-cms-admin' ),
			array( __CLASS__, 'render_allowed_tools_field' ),
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
			self::OPTION_ENVIRONMENT,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_environment' ),
				'default'           => self::default_environment(),
			)
		);
		register_setting(
			'chattanooga_mcp',
			self::OPTION_ALLOWED_TOOLS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_allowed_tools' ),
				'default'           => array(),
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
		register_setting(
			'chattanooga_mcp',
			self::OPTION_RATE_LIMIT,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_rate_limit' ),
				'default'           => self::DEFAULT_RATE_LIMIT,
			)
		);
		register_setting(
			'chattanooga_mcp',
			self::OPTION_READ_ONLY,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_enabled' ),
				'default'           => false,
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

	public static function rate_limit() {
		return self::sanitize_rate_limit( get_option( self::OPTION_RATE_LIMIT, self::DEFAULT_RATE_LIMIT ) );
	}

	public static function is_read_only() {
		return (bool) get_option( self::OPTION_READ_ONLY, false );
	}

	public static function environment_id() {
		$stored = self::sanitize_environment( get_option( self::OPTION_ENVIRONMENT, '' ) );
		return '' !== $stored ? $stored : self::default_environment();
	}

	public static function allowed_tools() {
		return self::sanitize_allowed_tools( get_option( self::OPTION_ALLOWED_TOOLS, array() ) );
	}

	public static function is_tool_allowed( $tool_name ) {
		$allowed = self::allowed_tools();
		return empty( $allowed ) || in_array( (string) $tool_name, $allowed, true );
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

	public static function sanitize_rate_limit( $value ) {
		$value = (int) $value;
		return max( 1, min( self::MAX_RATE_LIMIT, $value ) );
	}

	public static function sanitize_environment( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9._-]+/', '-', $value );
		$value = trim( (string) $value, '-._' );
		return substr( $value, 0, 64 );
	}

	public static function sanitize_allowed_tools( $value ) {
		$values = is_array( $value ) ? $value : preg_split( '/\r?\n|,/', (string) $value );
		$allowed = array();
		foreach ( $values as $tool_name ) {
			$tool_name = trim( (string) $tool_name );
			if ( in_array( $tool_name, array( 'cmsa.catalog', 'cmsa.read-bridge', 'cmsa.write-bridge' ), true ) || 1 === preg_match( '/^cmsa\.(?:bridge|rest)-[a-f0-9]{24}$/', $tool_name ) ) {
				$allowed[] = $tool_name;
			}
		}
		return array_values( array_unique( $allowed ) );
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
			<p><?php echo esc_html__( 'Configure the Chattanooga MCP plug-in and choose how its administrator-bound bearer authorization is supplied.', 'chattanooga-cms-admin' ); ?></p>
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
			'<p>%s</p><p><strong>%s</strong> <code>%s</code><br /><strong>%s</strong> <code>%s</code><br /><strong>%s</strong> <code>%s</code><br /><strong>%s</strong> <code>%s</code></p>',
			esc_html__( 'The endpoint URL and protocol are fixed by the Chattanooga MCP plug-in. Choose automatic OAuth authorization or supply a manually managed bearer token below. Every authenticated path still requires manage_options, and the MCP surface excludes administrator/control-plane abilities.', 'chattanooga-cms-admin' ),
			esc_html__( 'Endpoint:', 'chattanooga-cms-admin' ),
			esc_url( rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE ) ),
			esc_html__( 'Protocol:', 'chattanooga-cms-admin' ),
		esc_html( CUA_MCP_Server::PROTOCOL_VERSION ),
			esc_html__( 'Protected-resource metadata:', 'chattanooga-cms-admin' ),
			esc_url( home_url( '/.well-known/oauth-protected-resource' ) ),
			esc_html__( 'Authorization-server metadata:', 'chattanooga-cms-admin' ),
			esc_url( home_url( '/.well-known/oauth-authorization-server' ) )
		);
	}

	public static function render_enabled_field() {
		printf(
			'<input type="hidden" name="%1$s" value="0" /><label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_ENABLED ),
		checked( self::is_enabled(), true, false ),
			esc_html__( 'Enable the Chattanooga MCP endpoint', 'chattanooga-cms-admin' )
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
		printf( '<p class="description">%s</p>', esc_html__( 'Manual mode disables the OAuth authorization, registration, token, and metadata routes. The endpoint still expects Authorization: Bearer with the token configured below.', 'chattanooga-cms-admin' ) );
	}

	public static function render_manual_token_field() {
		printf(
			'<input type="password" class="regular-text" name="%1$s" value="" autocomplete="new-password" aria-describedby="%1$s-description" />',
			esc_attr( self::OPTION_MANUAL_TOKEN )
		);
		printf(
			'<p id="%1$s-description" class="description">%2$s</p>',
			esc_attr( self::OPTION_MANUAL_TOKEN . '-description' ),
			esc_html__( 'Enter a new high-entropy token of at least 32 characters and save. The token is stored only as a digest and is never displayed again. Configure the MCP client with Authorization: Bearer &lt;token&gt;. Leave blank to keep the existing token.', 'chattanooga-cms-admin' )
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

	public static function render_rate_limit_field() {
		printf(
			'<input type="number" class="small-text" name="%1$s" value="%2$d" min="1" max="%3$d" step="1" />',
			esc_attr( self::OPTION_RATE_LIMIT ),
			self::rate_limit(),
			self::MAX_RATE_LIMIT
		);
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Maximum authenticated MCP requests per source and WordPress user in a rolling one-minute window. A lower value is safer for exposed endpoints.', 'chattanooga-cms-admin' )
		);
	}

	public static function render_read_only_field() {
		printf(
			'<input type="hidden" name="%1$s" value="0" /><label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_READ_ONLY ),
		checked( self::is_read_only(), true, false ),
		esc_html__( 'Allow read-only MCP operations and reject mutating tools', 'chattanooga-cms-admin' )
		);
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Use this mode when the MCP endpoint may inspect the site but must not create, update, delete, publish, or otherwise mutate site data.', 'chattanooga-cms-admin' )
		);
	}

	public static function render_environment_field() {
		printf(
			'<input type="text" class="regular-text code" name="%1$s" value="%2$s" maxlength="64" />',
			esc_attr( self::OPTION_ENVIRONMENT ),
			esc_attr( self::environment_id() )
		);
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'A non-secret identifier for this site or environment. In multisite, configure it per site; it is reported in MCP discovery metadata.', 'chattanooga-cms-admin' )
		);
	}

	public static function render_allowed_tools_field() {
		printf(
			'<textarea class="large-text code" name="%1$s[]" rows="6" aria-describedby="%1$s-description">%2$s</textarea>',
			esc_attr( self::OPTION_ALLOWED_TOOLS ),
			esc_textarea( implode( "\n", self::allowed_tools() ) )
		);
		printf(
			'<p id="%1$s-description" class="description">%2$s</p>',
			esc_attr( self::OPTION_ALLOWED_TOOLS . '-description' ),
			esc_html__( 'One site-operation tool per line. Leave empty to expose all currently public site operations. Administrator and control-plane abilities are never eligible.', 'chattanooga-cms-admin' )
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

	private static function default_environment() {
		$host = parse_url( home_url( '/' ), PHP_URL_HOST );
		$host = self::sanitize_environment( $host ? $host : 'site' );
		return ( '' !== $host ? $host : 'site' ) . '-site-' . (int) get_current_blog_id();
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
