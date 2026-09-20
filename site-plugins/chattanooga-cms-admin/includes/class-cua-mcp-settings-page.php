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
		add_action( 'admin_post_cua_clear_oauth_trace', array( __CLASS__, 'clear_oauth_trace' ) );
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
			<?php self::render_oauth_trace_panel(); ?>
			<?php self::render_ingestion_diagnostics_panel(); ?>
		</div>
		<?php
	}

	public static function clear_oauth_trace() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to clear the authorization trace.', 'chattanooga-cms-admin' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cua_clear_oauth_trace' );
		$result = class_exists( 'CUA_Audit' ) ? CUA_Audit::clear_oauth_trace() : new WP_Error( 'cmsa_oauth_trace_unavailable', 'Authorization tracing is unavailable.' );
		$query = array(
			'page' => self::PAGE_SLUG,
			'oauth_trace' => is_wp_error( $result ) ? 'error' : 'cleared',
		);
		wp_safe_redirect( add_query_arg( $query, admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function render_oauth_trace_panel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$trace = class_exists( 'CUA_Audit' ) ? CUA_Audit::read_oauth_trace( 50 ) : array( 'entries' => array() );
		$entries = is_wp_error( $trace ) ? array() : ( $trace['entries'] ?? array() );
		$status = isset( $_GET['oauth_trace'] ) ? sanitize_key( wp_unslash( $_GET['oauth_trace'] ) ) : '';
		if ( 'cleared' === $status ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Authorization trace cleared.', 'chattanooga-cms-admin' ) . '</p></div>';
		} elseif ( 'error' === $status || is_wp_error( $trace ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Authorization trace could not be read or cleared.', 'chattanooga-cms-admin' ) . '</p></div>';
		}
		echo '<hr><h2>' . esc_html__( 'Authorization trace', 'chattanooga-cms-admin' ) . '</h2>';
		echo '<p>' . esc_html__( 'Shows the most recent OAuth and MCP handshake stages without storing bearer tokens, authorization codes, PKCE values, state, passwords, request bodies, or redirect URLs.', 'chattanooga-cms-admin' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Diagnostics:', 'chattanooga-cms-admin' ) . '</strong> <code>' . esc_html( rest_url( CUA_OAuth_Server::REST_NAMESPACE . '/oauth/diagnostics' ) ) . '</code></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:12px 0">';
		echo '<input type="hidden" name="action" value="cua_clear_oauth_trace">';
		wp_nonce_field( 'cua_clear_oauth_trace' );
		submit_button( __( 'Clear authorization trace', 'chattanooga-cms-admin' ), 'secondary', 'submit', false );
		echo '</form>';
		if ( empty( $entries ) ) {
			echo '<p><em>' . esc_html__( 'No authorization activity has been recorded yet.', 'chattanooga-cms-admin' ) . '</em></p>';
			return;
		}
		echo '<div style="overflow:auto;max-height:520px"><table class="widefat striped"><thead><tr>';
		foreach ( array( 'Time', 'Stage', 'Outcome', 'HTTP', 'Error', 'Grant', 'Client', 'MCP method', 'Protocol' ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $entries as $entry ) {
			$client = trim( (string) ( $entry['client_mode'] ?? '' ) );
			if ( ! empty( $entry['client_host'] ) ) {
				$client .= ( '' !== $client ? ' / ' : '' ) . (string) $entry['client_host'];
			}
			echo '<tr>';
			foreach ( array(
				$entry['time'] ?? '',
				$entry['stage'] ?? '',
				$entry['outcome'] ?? '',
				$entry['http_status'] ?? '',
				$entry['error_code'] ?? '',
				$entry['grant_type'] ?? '',
				$client,
				$entry['mcp_method'] ?? '',
				$entry['protocol_version'] ?? '',
			) as $value ) {
				echo '<td><code>' . esc_html( (string) $value ) . '</code></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function render_ingestion_diagnostics_panel() {
		if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'CUA_MCP_Diagnostics' ) ) {
			return;
		}

		$report = CUA_MCP_Diagnostics::admin_report();
		$catalog = is_array( $report['catalog'] ?? null ) ? $report['catalog'] : array();
		$summary = is_array( $catalog['descriptorSummary'] ?? null ) ? $catalog['descriptorSummary'] : array();
		$descriptors = is_array( $catalog['descriptors'] ?? null ) ? $catalog['descriptors'] : array();
		$recent = is_array( $report['recentExchanges'] ?? null ) ? $report['recentExchanges'] : array();

		echo '<hr><h2>' . esc_html__( 'MCP ingestion diagnostics', 'chattanooga-cms-admin' ) . '</h2>';
		echo '<p>' . esc_html__( 'Verifies the exact MCP tool catalog and records secret-free request/response fingerprints so a successful HTTP tools/list can be distinguished from ChatGPT action ingestion.', 'chattanooga-cms-admin' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Full diagnostics JSON:', 'chattanooga-cms-admin' ) . '</strong> <code>' . esc_html( (string) ( $report['diagnosticsEndpoint'] ?? '' ) ) . '</code><br>';
		echo '<strong>' . esc_html__( 'Read-only canary endpoint:', 'chattanooga-cms-admin' ) . '</strong> <code>' . esc_html( (string) ( $report['canaryEndpoint'] ?? '' ) ) . '</code></p>';

		echo '<table class="widefat striped" style="max-width:1000px"><tbody>';
		$rows = array(
			__( 'Plugin version', 'chattanooga-cms-admin' ) => $report['pluginVersion'] ?? '',
			__( 'Protocol', 'chattanooga-cms-admin' ) => $report['protocolVersion'] ?? '',
			__( 'Tools', 'chattanooga-cms-admin' ) => $catalog['toolCount'] ?? 0,
			__( 'Tool pages', 'chattanooga-cms-admin' ) => $catalog['pageCount'] ?? 0,
			__( 'Catalog JSON bytes', 'chattanooga-cms-admin' ) => $catalog['catalogJsonBytes'] ?? 0,
			__( 'Catalog SHA-256', 'chattanooga-cms-admin' ) => $catalog['catalogSha256'] ?? '',
			__( 'Descriptor PASS', 'chattanooga-cms-admin' ) => $summary['pass'] ?? 0,
			__( 'Descriptor FAIL', 'chattanooga-cms-admin' ) => $summary['fail'] ?? 0,
		);
		foreach ( $rows as $label => $value ) {
			echo '<tr><th style="width:220px">' . esc_html( (string) $label ) . '</th><td><code>' . esc_html( (string) $value ) . '</code></td></tr>';
		}
		echo '</tbody></table>';

		$failures = array();
		foreach ( $descriptors as $descriptor ) {
			if ( is_array( $descriptor ) && empty( $descriptor['pass'] ) ) {
				$failures[] = $descriptor;
			}
		}
		if ( empty( $failures ) ) {
			echo '<p><strong>' . esc_html__( 'Descriptor conformance:', 'chattanooga-cms-admin' ) . '</strong> ' . esc_html__( 'PASS — every exposed tool passed the built-in descriptor checks.', 'chattanooga-cms-admin' ) . '</p>';
		} else {
			echo '<p><strong>' . esc_html__( 'Descriptor conformance failures', 'chattanooga-cms-admin' ) . '</strong></p><ul>';
			foreach ( $failures as $failure ) {
				echo '<li><code>' . esc_html( (string) ( $failure['name'] ?? '' ) ) . '</code>: ' . esc_html( implode( ', ', array_map( 'strval', $failure['issues'] ?? array() ) ) ) . '</li>';
			}
			echo '</ul>';
		}

		echo '<h3>' . esc_html__( 'Recent MCP ingestion exchanges', 'chattanooga-cms-admin' ) . '</h3>';
		echo '<p>' . esc_html__( 'Hashes and byte counts are recorded instead of raw request or response bodies. Authorization presence is a boolean only; credentials are never retained.', 'chattanooga-cms-admin' ) . '</p>';
		if ( empty( $recent ) ) {
			echo '<p><em>' . esc_html__( 'No MCP ingestion exchanges have been recorded yet.', 'chattanooga-cms-admin' ) . '</em></p>';
			return;
		}

		echo '<div style="overflow:auto;max-height:520px"><table class="widefat striped"><thead><tr>';
		foreach ( array( 'Time', 'Surface', 'MCP method', 'HTTP', 'Client', 'Auth', 'Req bytes', 'Resp bytes', 'Tools', 'Desc P/F', 'Correlation', 'Response SHA' ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $recent as $entry ) {
			$descriptor_counts = (string) ( $entry['descriptor_pass'] ?? 0 ) . '/' . (string) ( $entry['descriptor_fail'] ?? 0 );
			$correlation = (string) ( $entry['correlation_sha256'] ?? '' );
			$response_sha = (string) ( $entry['response_sha256'] ?? '' );
			$values = array(
				$entry['time'] ?? '',
				$entry['mcp_surface'] ?? '',
				$entry['mcp_method'] ?? '',
				$entry['http_status'] ?? '',
				$entry['client_class'] ?? '',
				! empty( $entry['authorization_present'] ) ? 'yes' : 'no',
				$entry['request_bytes'] ?? '',
				$entry['response_bytes'] ?? '',
				$entry['tool_count'] ?? '',
				$descriptor_counts,
				'' !== $correlation ? substr( $correlation, 0, 16 ) : '',
				'' !== $response_sha ? substr( $response_sha, 0, 16 ) : '',
			);
			echo '<tr>';
			foreach ( $values as $value ) {
				echo '<td><code>' . esc_html( (string) $value ) . '</code></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
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
		foreach ( array( self::AUTH_MODE_OAUTH => __( 'Automatic OAuth authorization', 'chattanooga-cms-admin' ), self::AUTH_MODE_MANUAL => __( 'OAuth plus manual bearer fallback', 'chattanooga-cms-admin' ) ) as $value => $label ) {
			printf(
				'<label style="display:block;margin-bottom:6px"><input type="radio" name="%1$s" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( self::OPTION_AUTH_MODE ),
				esc_attr( $value ),
				checked( $mode, $value, false ),
				esc_html( $label )
			);
		}
		printf( '<p class="description">%s</p>', esc_html__( 'OAuth remains available in both modes for ChatGPT compatibility. The fallback mode additionally accepts the configured manual Bearer token from MCP clients that can supply a static Authorization header.', 'chattanooga-cms-admin' ) );
	}

	public static function render_manual_token_field() {
		printf(
			'<input type="password" class="regular-text" name="%1$s" value="" autocomplete="new-password" aria-describedby="%1$s-description" />',
			esc_attr( self::OPTION_MANUAL_TOKEN )
		);
		printf(
			'<p id="%1$s-description" class="description">%2$s</p>',
			esc_attr( self::OPTION_MANUAL_TOKEN . '-description' ),
			esc_html__( 'Optional compatibility fallback for MCP clients that can send a static Bearer token. Enter a new high-entropy token of at least 32 characters and save. The token is stored only as a digest and is never displayed again. Leave blank to keep the existing token.', 'chattanooga-cms-admin' )
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
