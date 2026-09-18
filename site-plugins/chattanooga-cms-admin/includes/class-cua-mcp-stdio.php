<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First-party stdio transport for local MCP clients.
 *
 * This is an explicit WP-CLI command, so it never creates a public endpoint
 * or an automatic network route. Each input line is one JSON-RPC request and
 * each output line is the corresponding JSON-RPC response.
 */
final class CUA_MCP_Stdio {
	public static function register_command() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'chattanooga-mcp', array( __CLASS__, 'command' ) );
		}
	}

	public static function command( $args, $assoc_args ) {
		if ( empty( $args ) || 'stdio' !== $args[0] ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::error( 'Use: wp chattanooga-mcp stdio [--user=<administrator-id>]' );
			}
			return;
		}

		$user_id = isset( $assoc_args['user'] ) ? (int) $assoc_args['user'] : 0;
		if ( $user_id > 0 ) {
			wp_set_current_user( $user_id );
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			self::write_error( null, -32001, 'An administrator WordPress user is required for the Chattanooga MCP stdio transport.' );
			return;
		}

		$session_id = '';
		while ( false !== ( $line = fgets( STDIN ) ) ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$payload = json_decode( $line, true );
			if ( ! is_array( $payload ) ) {
				self::write_error( null, -32700, 'The stdio request is not valid JSON.' );
				continue;
			}

			$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_header( 'Accept', 'application/json' );
			$request->set_header( 'Mcp-Method', (string) ( $payload['method'] ?? '' ) );
			$protocol_version = isset( $payload['params']['protocolVersion'] ) ? (string) $payload['params']['protocolVersion'] : CUA_MCP_Server::PROTOCOL_VERSION;
			$request->set_header( 'MCP-Protocol-Version', $protocol_version );
			if ( '' !== $session_id ) {
				$request->set_header( CUA_MCP_Server::SESSION_HEADER, $session_id );
			}
			$request->set_body( $line );

			$response = CUA_MCP_Server::handle_request( $request );
			if ( $response instanceof WP_REST_Response ) {
				$headers = array_change_key_case( $response->get_headers(), CASE_LOWER );
				if ( isset( $headers[ strtolower( CUA_MCP_Server::SESSION_HEADER ) ] ) ) {
					$session_id = (string) $headers[ strtolower( CUA_MCP_Server::SESSION_HEADER ) ];
				}
				$data = $response->get_data();
				if ( null !== $data ) {
					self::write( $data );
				}
			} else {
				self::write_error( $payload['id'] ?? null, -32603, 'The stdio MCP request did not return a response.' );
			}
		}
	}

	private static function write_error( $id, $code, $message ) {
		self::write(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => array(
					'code'    => (int) $code,
					'message' => (string) $message,
				),
			)
		);
	}

	private static function write( $value ) {
		fwrite( STDOUT, (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
		fflush( STDOUT );
	}
}
