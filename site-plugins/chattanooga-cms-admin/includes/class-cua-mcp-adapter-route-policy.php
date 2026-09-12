<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves ownership of the standard WordPress MCP Adapter REST endpoint.
 */
final class CUA_MCP_Adapter_Route_Policy {
	public static function register_route() {
		$route_path = '/' . CUA_MCP_Adapter_Compat::REST_NAMESPACE . CUA_MCP_Adapter_Compat::REST_ROUTE;
		$routes     = rest_get_server()->get_routes();

		// The official WordPress MCP Adapter is authoritative when installed.
		if ( class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) && isset( $routes[ $route_path ] ) ) {
			return;
		}

		if ( ! isset( $routes[ $route_path ] ) ) {
			CUA_MCP_Adapter_Compat::register_route();
			return;
		}

		// A different plugin already owns the adapter route. Replace only the route
		// contract, not that plugin's source or settings, so compatible clients reach
		// Chattanooga's verified ability surface instead of the incompatible handler.
		register_rest_route(
			CUA_MCP_Adapter_Compat::REST_NAMESPACE,
			CUA_MCP_Adapter_Compat::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'CUA_MCP_Adapter_Compat', 'handle_request' ),
				'permission_callback' => array( 'CUA_MCP_Adapter_Compat', 'authorize_request' ),
			),
			true
		);
	}
}
