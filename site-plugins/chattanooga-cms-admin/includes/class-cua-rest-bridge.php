<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CUA_REST_Bridge {
	const NAMESPACE_PREFIX = 'chattanooga-cms-admin/';
	const CATEGORY = 'chattanooga-cms-admin';

	private static $bridges = array();

	public static function register_external_bridges() {
		if ( ! function_exists( 'rest_get_server' ) || ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_get_ability' ) ) {
			return;
		}

		$server = rest_get_server();
		if ( ! $server instanceof WP_REST_Server ) {
			return;
		}

		$routes = $server->get_routes();
		foreach ( $routes as $route_regex => $handlers ) {
			if ( ! is_string( $route_regex ) || ! is_array( $handlers ) ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				if ( ! self::handler_is_bridgeable( $handler ) ) {
					continue;
				}

				foreach ( self::supported_methods( $handler['methods'] ) as $method ) {
					$bridge_name = self::bridge_name( $method, $route_regex );
					if ( wp_get_ability( $bridge_name ) instanceof WP_Ability ) {
						continue;
					}

					wp_register_ability(
						$bridge_name,
						array(
							'label'               => sprintf(
								/* translators: 1: HTTP method, 2: registered REST route pattern. */
								__( 'REST %1$s %2$s', 'chattanooga-cms-admin' ),
								$method,
								$route_regex
							),
							'description'         => sprintf(
								/* translators: 1: HTTP method, 2: registered REST route pattern. */
								__( 'Permission-preserving facade for the registered WordPress REST endpoint %1$s %2$s.', 'chattanooga-cms-admin' ),
								$method,
								$route_regex
							),
							'category'            => self::CATEGORY,
							'input_schema'        => self::input_schema(),
							'output_schema'       => array( 'type' => 'object' ),
							'execute_callback'    => static function ( $input ) use ( $route_regex, $method ) {
								return CUA_REST_Bridge::execute_route( $route_regex, $method, is_array( $input ) ? $input : array() );
							},
							'permission_callback' => static function ( $input ) use ( $route_regex, $method, $handler ) {
								return CUA_REST_Bridge::target_permission( $route_regex, $method, $handler, is_array( $input ) ? $input : array() );
							},
							'meta'                => self::bridge_meta( $method ),
						)
					);

					if ( wp_get_ability( $bridge_name ) instanceof WP_Ability ) {
						self::$bridges[ $bridge_name ] = array(
							'method' => $method,
							'route'  => $route_regex,
						);
					}
				}
			}
		}
	}

	public static function catalog_items() {
		$items = array();
		foreach ( self::$bridges as $bridge_name => $route ) {
			$method = $route['method'];
			$route_regex = $route['route'];
			$items[] = array(
				'contract'    => 'rest',
				'bridge'      => $bridge_name,
				'target'      => $method . ' ' . $route_regex,
				'method'      => $method,
				'route'       => $route_regex,
				'label'       => $method . ' ' . $route_regex,
				'description' => 'Registered WordPress REST endpoint exposed through a route-locked universal facade.',
				'category'    => self::CATEGORY,
				'annotations' => self::annotations( $method ),
			);
		}

		return $items;
	}

	public static function target_permission( $route_regex, $method, array $handler, array $input ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$request = self::build_request( $route_regex, $method, $handler, $input );
		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$prepared = self::validate_and_guard_request( $request, $method );
		if ( is_wp_error( $prepared ) || false === $prepared ) {
			return $prepared;
		}

		if ( empty( $handler['permission_callback'] ) || ! is_callable( $handler['permission_callback'] ) ) {
			return false;
		}

		return call_user_func( $handler['permission_callback'], $request );
	}

	public static function execute_route( $route_regex, $method, array $input ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'cua_rest_forbidden', 'The current user is not permitted to use the universal administration bridge.' );
		}

		$server = function_exists( 'rest_get_server' ) ? rest_get_server() : null;
		if ( ! $server instanceof WP_REST_Server ) {
			return new WP_Error( 'cua_rest_unavailable', 'The WordPress REST server is unavailable.' );
		}

		$handler = self::find_live_handler( $server, $route_regex, $method );
		if ( is_wp_error( $handler ) ) {
			return $handler;
		}

		$request = self::build_request( $route_regex, $method, $handler, $input );
		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$prepared = self::validate_and_guard_request( $request, $method );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		if ( false === $prepared ) {
			return new WP_Error( 'cua_rest_forbidden', 'The control-plane guard denied the current REST request.' );
		}

		$response = rest_do_request( $request );
		if ( ! $response instanceof WP_REST_Response ) {
			return new WP_Error( 'cua_rest_invalid_response', 'The registered REST endpoint did not return a WordPress REST response.' );
		}
		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return array(
			'status' => (int) $response->get_status(),
			'data'   => $response->get_data(),
		);
	}

	private static function validate_and_guard_request( WP_REST_Request $request, $method ) {
		$valid = $request->has_valid_params();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$sanitized = $request->sanitize_params();
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}

		if ( ! class_exists( 'CUA_Control_Plane_Guard' ) ) {
			return false;
		}

		return CUA_Control_Plane_Guard::validate_rest_request( $request, $method );
	}

	private static function handler_is_bridgeable( $handler ) {
		if ( ! is_array( $handler ) ) {
			return false;
		}
		if ( isset( $handler['show_in_index'] ) && false === $handler['show_in_index'] ) {
			return false;
		}
		if ( empty( $handler['methods'] ) || ! is_array( $handler['methods'] ) ) {
			return false;
		}
		if ( empty( $handler['callback'] ) || ! is_callable( $handler['callback'] ) ) {
			return false;
		}
		if ( empty( $handler['permission_callback'] ) || ! is_callable( $handler['permission_callback'] ) ) {
			return false;
		}

		return ! empty( self::supported_methods( $handler['methods'] ) );
	}

	private static function supported_methods( array $methods ) {
		$supported = array();
		foreach ( array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) as $method ) {
			if ( ! empty( $methods[ $method ] ) ) {
				$supported[] = $method;
			}
		}
		return $supported;
	}

	private static function find_live_handler( WP_REST_Server $server, $route_regex, $method ) {
		$routes = $server->get_routes();
		if ( empty( $routes[ $route_regex ] ) || ! is_array( $routes[ $route_regex ] ) ) {
			return new WP_Error( 'cua_rest_route_unavailable', 'The discovered REST route is no longer registered.' );
		}

		foreach ( $routes[ $route_regex ] as $handler ) {
			if ( ! self::handler_is_bridgeable( $handler ) ) {
				continue;
			}
			if ( ! empty( $handler['methods'][ $method ] ) ) {
				return $handler;
			}
		}

		return new WP_Error( 'cua_rest_route_unavailable', 'The discovered REST method is no longer registered for this route.' );
	}

	private static function build_request( $route_regex, $method, array $handler, array $input ) {
		$path = isset( $input['path'] ) ? (string) $input['path'] : '';
		if ( '' === $path || '/' !== $path[0] || strlen( $path ) > 2048 || false !== strpos( $path, '?' ) || false !== strpos( $path, '#' ) ) {
			return new WP_Error( 'cua_rest_invalid_path', 'A concrete REST path without a query string or fragment is required.' );
		}

		$matches = array();
		$matched = preg_match( '@^' . $route_regex . '$@i', $path, $matches );
		if ( 1 !== $matched ) {
			return new WP_Error( 'cua_rest_path_mismatch', 'The supplied path does not match this facade route.' );
		}

		$params = isset( $input['params'] ) ? $input['params'] : array();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'cua_rest_invalid_params', 'REST parameters must be an object.' );
		}

		$url_params = array();
		foreach ( $matches as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			$url_params[ $key ] = $value;
			if ( array_key_exists( $key, $params ) ) {
				if ( (string) $params[ $key ] !== (string) $value ) {
					return new WP_Error( 'cua_rest_path_param_conflict', 'A supplied parameter conflicts with the concrete route path.' );
				}
				unset( $params[ $key ] );
			}
		}

		$request = new WP_REST_Request( $method, $path );
		$request->set_url_params( $url_params );
		$request->set_attributes( $handler );

		$defaults = array();
		foreach ( isset( $handler['args'] ) && is_array( $handler['args'] ) ? $handler['args'] : array() as $arg => $options ) {
			if ( is_array( $options ) && array_key_exists( 'default', $options ) ) {
				$defaults[ $arg ] = $options['default'];
			}
		}
		$request->set_default_params( $defaults );

		if ( in_array( $method, array( 'GET', 'DELETE' ), true ) ) {
			$request->set_query_params( $params );
		} else {
			$request->set_body_params( $params );
		}

		return $request;
	}

	private static function bridge_name( $method, $route_regex ) {
		return self::NAMESPACE_PREFIX . 'rest-' . substr( hash( 'sha256', $method . '|' . $route_regex ), 0, 24 );
	}

	private static function input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'path' => array(
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 2048,
				),
				'params' => array(
					'type' => 'object',
				),
			),
			'required'             => array( 'path' ),
			'additionalProperties' => false,
		);
	}

	private static function bridge_meta( $method ) {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true ),
			'annotations'  => self::annotations( $method ),
		);
	}

	private static function annotations( $method ) {
		$readonly = 'GET' === $method;
		return array(
			'readonly'    => $readonly,
			'destructive' => ! $readonly,
			'idempotent'  => $readonly,
		);
	}
}
