<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_MCP_Official_Transport {
	private static $registration_error = null;
	const SERVER_ID       = 'chattanooga-cms-admin';
	const ROUTE_NAMESPACE = 'chattanooga-cms-admin/v1';
	const ROUTE            = 'mcp';

	private const TOOLS = array(
		'chattanooga-cms-admin/discovery',
		'chattanooga-cms-admin/stability-check',
		'chattanooga-cms-admin/read-bridge',
		'chattanooga-cms-admin/write-bridge',
	);

	public static function bootstrap() {
		add_filter( 'mcp_adapter_create_default_server', '__return_false', PHP_INT_MIN );
		add_filter( 'mcp_adapter_tool_name', array( __CLASS__, 'tool_name' ), 10, 2 );
		add_action( 'mcp_adapter_init', array( __CLASS__, 'register_server' ), 10, 1 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'decorate_auth_response' ), 20, 3 );

		if ( class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
			\WP\MCP\Core\McpAdapter::instance();
		}
	}

	public static function register_server( $adapter ) {
		if ( ! CUA_MCP_Settings_Page::is_enabled() || ! $adapter instanceof \WP\MCP\Core\McpAdapter ) {
			return;
		}

		$result = $adapter->create_server(
			self::SERVER_ID,
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			'Chattanooga CMS Admin',
			'Authenticated Chattanooga Music Scene administration through WordPress abilities.',
			CUA_VERSION,
			array( \WP\MCP\Transport\HttpTransport::class ),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			self::TOOLS,
			array(),
			array(),
			array( __CLASS__, 'check_permission' )
		);
		if ( is_wp_error( $result ) ) {
			self::$registration_error = $result;
		}
	}

	public static function registration_error() {
		return self::$registration_error;
	}

	public static function check_permission( $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( ! $request instanceof WP_REST_Request ) {
			return false;
		}

		$authenticated = CUA_OAuth_Server::authenticate_bearer( $request );
		if ( true !== $authenticated ) {
			return false;
		}

		return current_user_can( 'manage_options' );
	}

	public static function tool_name( $name, $ability ) {
		if ( ! $ability instanceof WP_Ability ) {
			return $name;
		}

		$map = array(
			'chattanooga-cms-admin/discovery'       => 'cmsa.discovery',
			'chattanooga-cms-admin/stability-check' => 'cmsa.stability-check',
			'chattanooga-cms-admin/read-bridge'     => 'cmsa.read-bridge',
			'chattanooga-cms-admin/write-bridge'    => 'cmsa.write-bridge',
		);
		$ability_name = $ability->get_name();

		return isset( $map[ $ability_name ] ) ? $map[ $ability_name ] : $name;
	}

	public static function decorate_auth_response( $response, $server, $request ) {
		unset( $server );
		if ( ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		if ( '/' . self::ROUTE_NAMESPACE . '/' . self::ROUTE !== rtrim( $request->get_route(), '/' ) ) {
			return $response;
		}

		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
		$response->header( 'Pragma', 'no-cache' );
		if ( 401 === $response->get_status() || ( 403 === $response->get_status() && ! is_user_logged_in() ) ) {
			$response->set_status( 401 );
			$response->header( 'WWW-Authenticate', CUA_OAuth_Server::resource_challenge( 'cmsa_oauth_token_missing' ) );
		}
		return $response;
	}
}
