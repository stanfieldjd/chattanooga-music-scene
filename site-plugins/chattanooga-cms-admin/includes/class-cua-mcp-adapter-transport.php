<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_MCP_Adapter_Transport {
	const SERVER_ID = 'chattanooga-cms-admin';

	private const TOOL_NAMES = array(
		'chattanooga-cms-admin/discovery'       => 'cmsa.discovery',
		'chattanooga-cms-admin/stability-check' => 'cmsa.stability-check',
		'chattanooga-cms-admin/read-bridge'     => 'cmsa.read-bridge',
		'chattanooga-cms-admin/write-bridge'    => 'cmsa.write-bridge',
	);

	public static function bootstrap() {
		if ( ! class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'missing_adapter_notice' ) );
			return;
		}

		add_filter( 'mcp_adapter_create_default_server', '__return_false' );
		add_filter( 'mcp_adapter_tool_name', array( __CLASS__, 'tool_name' ), 10, 2 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'decorate_response' ), 10, 3 );
		add_filter( 'rest_exposed_cors_headers', array( __CLASS__, 'exposed_cors_headers' ) );
		add_filter( 'rest_allowed_cors_headers', array( __CLASS__, 'allowed_cors_headers' ) );

		\\WP\\MCP\\Core\\McpAdapter::instance();
		add_action( 'mcp_adapter_init', array( __CLASS__, 'register_server' ), 10, 1 );
	}

	public static function register_server( $adapter ) {
		if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
			return;
		}
		if ( method_exists( $adapter, 'get_server' ) && null !== $adapter->get_server( self::SERVER_ID ) ) {
			return;
		}

		$adapter->create_server(
			self::SERVER_ID,
			'chattanooga-cms-admin/v1',
			'mcp',
			'Chattanooga CMS Admin',
			'Stable Chattanooga WordPress administration gateway.',
			CUA_VERSION,
			array( \\WP\\MCP\\Transport\\HttpTransport::class ),
			\\WP\\MCP\\Infrastructure\\ErrorHandling\\ErrorLogMcpErrorHandler::class,
			\\WP\\MCP\\Infrastructure\\Observability\\NullMcpObservabilityHandler::class,
			array_keys( self::TOOL_NAMES ),
			array(),
			array(),
			array( __CLASS__, 'authenticate_transport' )
		);
	}

	public static function authenticate_transport( $request ) {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( ! $request instanceof WP_REST_Request ) {
			return new WP_Error( 'cmsa_oauth_token_missing', 'Authentication is required.', array( 'status' => 401 ) );
		}
		return CUA_OAuth_Server::authenticate_bearer( $request );
	}

	public static function tool_name( $name, $ability ) {
		if ( is_object( $ability ) && method_exists( $ability, 'get_name' ) ) {
			$ability_name = (string) $ability->get_name();
			if ( isset( self::TOOL_NAMES[ $ability_name ] ) ) {
				return self::TOOL_NAMES[ $ability_name ];
			}
		}
		return $name;
	}

	public static function decorate_response( $response, $server, $request ) {
		unset( $server );
		if ( ! $request instanceof WP_REST_Request || ! self::is_mcp_request( $request ) || ! $response instanceof WP_HTTP_Response ) {
			return $response;
		}

		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );

		if ( 401 === (int) $response->get_status() ) {
			$response->header( 'WWW-Authenticate', CUA_OAuth_Server::resource_challenge( 'cmsa_oauth_token_missing' ) );
		}
		return $response;
	}

	public static function exposed_cors_headers( $headers ) {
		$headers = is_array( $headers ) ? $headers : array();
		foreach ( array( 'WWW-Authenticate', 'Mcp-Session-Id', 'MCP-Protocol-Version' ) as $header ) {
			if ( ! in_array( $header, $headers, true ) ) {
				$headers[] = $header;
			}
		}
		return $headers;
	}

	public static function allowed_cors_headers( $headers ) {
		$headers = is_array( $headers ) ? $headers : array();
		foreach ( array( 'Authorization', 'Mcp-Session-Id', 'MCP-Protocol-Version' ) as $header ) {
			if ( ! in_array( $header, $headers, true ) ) {
				$headers[] = $header;
			}
		}
		return $headers;
	}

	private static function is_mcp_request( WP_REST_Request $request ) {
		return '/chattanooga-cms-admin/v1/mcp' === untrailingslashit( $request->get_route() );
	}

	public static function missing_adapter_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Chattanooga CMS Admin could not load the official WordPress MCP Adapter runtime. Reinstall the plugin package.', 'chattanooga-cms-admin' ) . '</p></div>';
	}
}
