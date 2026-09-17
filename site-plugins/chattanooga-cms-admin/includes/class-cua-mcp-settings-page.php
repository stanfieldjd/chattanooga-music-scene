<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_MCP_Settings_Page {
	const OPTION_ENABLED = 'cua_mcp_enabled';
	const OPTION_ORIGINS = 'cua_mcp_allowed_origins';
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
	}

	public static function is_enabled() {
		return (bool) get_option( self::OPTION_ENABLED, true );
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
