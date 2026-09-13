<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Administrator-issued manual MCP authorization links.
 *
 * The clear-text token is shown only immediately after generation. Persistent
 * storage contains only an HMAC digest bound to the administrator who issued it.
 */
final class CUA_Manual_Token {
	const OPTION_NAME       = 'cua_manual_mcp_token';
	const PAGE_SLUG         = 'chattanooga-cms-admin-mcp';
	const QUERY_ARG         = 'token';
	const REVEAL_TTL        = 300;

	private static $manual_request = false;

	public static function bootstrap() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'admin_post_cua_manual_token_generate', array( __CLASS__, 'handle_generate' ) );
		add_action( 'admin_post_cua_manual_token_revoke', array( __CLASS__, 'handle_revoke' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'finalize_tokenized_mcp_response' ), 20, 3 );
	}

	public static function register_admin_page() {
		add_management_page(
			esc_html__( 'Chattanooga CMS Admin MCP', 'chattanooga-cms-admin' ),
			esc_html__( 'CMS Admin MCP', 'chattanooga-cms-admin' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator authority is required.', 'chattanooga-cms-admin' ), '', array( 'response' => 403 ) );
		}

		$user_id = get_current_user_id();
		$record  = self::stored_record();
		$token   = get_transient( self::reveal_key( $user_id ) );
		if ( is_string( $token ) && '' !== $token ) {
			delete_transient( self::reveal_key( $user_id ) );
		} else {
			$token = '';
		}

		$endpoint = self::canonical_endpoint();
		$created  = ! empty( $record['created_at'] ) ? wp_date( 'Y-m-d H:i:s T', (int) $record['created_at'] ) : '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Chattanooga CMS Admin MCP', 'chattanooga-cms-admin' ); ?></h1>
			<p><?php echo esc_html__( 'Use a manually authorized MCP URL when a client accepts an endpoint containing its authorization token.', 'chattanooga-cms-admin' ); ?></p>

			<table class="widefat striped" style="max-width:1000px;margin:18px 0">
				<tbody>
					<tr>
						<th scope="row" style="width:220px"><?php echo esc_html__( 'MCP endpoint', 'chattanooga-cms-admin' ); ?></th>
						<td><code><?php echo esc_html( $endpoint ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Manual token', 'chattanooga-cms-admin' ); ?></th>
						<td><?php echo ! empty( $record ) ? esc_html__( 'Active', 'chattanooga-cms-admin' ) : esc_html__( 'Not generated', 'chattanooga-cms-admin' ); ?></td>
					</tr>
					<?php if ( '' !== $created ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Generated', 'chattanooga-cms-admin' ); ?></th>
						<td><?php echo esc_html( $created ); ?></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( '' !== $token ) : ?>
				<div class="notice notice-success inline" style="max-width:1000px;padding:12px 16px">
					<p><strong><?php echo esc_html__( 'Copy this manual MCP URL now. The token will not be shown again.', 'chattanooga-cms-admin' ); ?></strong></p>
					<p><input type="text" readonly class="large-text code" value="<?php echo esc_attr( self::manual_endpoint_url( $token ) ); ?>" onclick="this.select();"></p>
				</div>
			<?php elseif ( ! empty( $record ) ) : ?>
				<p><?php echo esc_html__( 'The clear-text token is not stored. If the URL was lost, rotate the token to create a new one.', 'chattanooga-cms-admin' ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
				<input type="hidden" name="action" value="cua_manual_token_generate">
				<?php wp_nonce_field( 'cua_manual_token_generate' ); ?>
				<?php submit_button( empty( $record ) ? __( 'Generate manual MCP URL', 'chattanooga-cms-admin' ) : __( 'Rotate manual MCP URL', 'chattanooga-cms-admin' ), 'primary', 'submit', false ); ?>
			</form>

			<?php if ( ! empty( $record ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
					<input type="hidden" name="action" value="cua_manual_token_revoke">
					<?php wp_nonce_field( 'cua_manual_token_revoke' ); ?>
					<?php submit_button( __( 'Revoke manual MCP URL', 'chattanooga-cms-admin' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_generate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator authority is required.', 'chattanooga-cms-admin' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cua_manual_token_generate' );

		$user_id = get_current_user_id();
		$token   = self::generate_for_user( $user_id );
		if ( is_wp_error( $token ) ) {
			wp_die( esc_html( $token->get_error_message() ), '', array( 'response' => 500 ) );
		}
		set_transient( self::reveal_key( $user_id ), $token, self::REVEAL_TTL );

		wp_safe_redirect( self::admin_page_url() );
		exit;
	}

	public static function handle_revoke() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator authority is required.', 'chattanooga-cms-admin' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cua_manual_token_revoke' );

		self::revoke();
		delete_transient( self::reveal_key( get_current_user_id() ) );
		wp_safe_redirect( self::admin_page_url() );
		exit;
	}

	/**
	 * Generate and persist a new token for an administrator.
	 *
	 * @return string|WP_Error Clear-text token, returned once to the caller.
	 */
	public static function generate_for_user( $user_id ) {
		$user_id = (int) $user_id;
		$user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;
		if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'cmsa_manual_token_forbidden', 'An administrator account is required to issue a manual MCP token.' );
		}

		try {
			$token = self::random_token( 32 );
		} catch ( Throwable $error ) {
			return new WP_Error( 'cmsa_manual_token_generation_failed', 'The manual MCP token could not be generated.' );
		}

		$record = array(
			'digest'     => self::digest( $token ),
			'user_id'    => $user_id,
			'created_at' => time(),
			'version'    => 1,
		);

		if ( ! update_option( self::OPTION_NAME, $record, false ) && $record !== get_option( self::OPTION_NAME ) ) {
			return new WP_Error( 'cmsa_manual_token_storage_failed', 'The manual MCP token could not be stored.' );
		}

		return $token;
	}

	public static function revoke() {
		delete_option( self::OPTION_NAME );
	}

	public static function manual_endpoint_url( $token ) {
		return add_query_arg( self::QUERY_ARG, (string) $token, self::canonical_endpoint() );
	}

	public static function has_token_parameter( WP_REST_Request $request ) {
		$query = $request->get_query_params();
		return array_key_exists( self::QUERY_ARG, $query );
	}

	public static function authenticate_request( WP_REST_Request $request ) {
		self::$manual_request = true;
		$query = $request->get_query_params();
		$token = isset( $query[ self::QUERY_ARG ] ) ? trim( (string) $query[ self::QUERY_ARG ] ) : '';

		if ( ! self::token_is_valid( $token ) ) {
			return new WP_Error(
				'cmsa_manual_token_invalid',
				'The manual MCP authorization token is invalid or revoked.',
				array( 'status' => 401 )
			);
		}

		$record = self::stored_record();
		$user   = get_user_by( 'id', (int) $record['user_id'] );
		if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error(
				'cmsa_manual_token_forbidden',
				'The administrator account that issued this manual MCP token no longer has administrator authority.',
				array( 'status' => 403 )
			);
		}

		wp_set_current_user( $user->ID );
		return true;
	}

	public static function manual_request_active() {
		return self::$manual_request;
	}

	public static function finalize_tokenized_mcp_response( $response, $server, $request ) {
		if ( ! self::$manual_request || ! $request instanceof WP_REST_Request || self::mcp_route() !== $request->get_route() || ! $response instanceof WP_HTTP_Response ) {
			return $response;
		}

		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Referrer-Policy', 'no-referrer' );

		$data = $response->get_data();
		if ( is_array( $data ) && isset( $data['result']['tools'] ) && is_array( $data['result']['tools'] ) ) {
			foreach ( $data['result']['tools'] as &$tool ) {
				if ( is_array( $tool ) ) {
					$tool['securitySchemes'] = array( array( 'type' => 'noauth' ) );
				}
			}
			unset( $tool );
			$response->set_data( $data );
		}

		self::$manual_request = false;
		return $response;
	}

	private static function token_is_valid( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $token ) ) {
			return false;
		}

		$record = self::stored_record();
		if ( empty( $record['digest'] ) || empty( $record['user_id'] ) || ! hash_equals( (string) $record['digest'], self::digest( $token ) ) ) {
			return false;
		}

		$user = get_user_by( 'id', (int) $record['user_id'] );
		return $user && user_can( $user, 'manage_options' );
	}

	private static function stored_record() {
		$record = get_option( self::OPTION_NAME, array() );
		return is_array( $record ) ? $record : array();
	}

	private static function canonical_endpoint() {
		return rest_url( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE );
	}

	private static function mcp_route() {
		return '/' . trim( CUA_MCP_Server::REST_NAMESPACE . CUA_MCP_Server::REST_ROUTE, '/' );
	}

	private static function admin_page_url() {
		return add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'tools.php' ) );
	}

	private static function reveal_key( $user_id ) {
		return 'cua_manual_mcp_reveal_' . (int) $user_id;
	}

	private static function digest( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	private static function random_token( $bytes ) {
		return rtrim( strtr( base64_encode( random_bytes( (int) $bytes ) ), '+/', '-_' ), '=' );
	}
}
