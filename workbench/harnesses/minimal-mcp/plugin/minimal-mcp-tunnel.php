<?php
/**
 * Plugin Name: Minimal MCP Tunnel
 * Description: Workbench-only MCP transport proof for Chattanooga Music Scene.
 * Version: 0.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-minimal-mcp-tool-registry.php';
require_once __DIR__ . '/includes/class-minimal-mcp-request-router.php';
require_once __DIR__ . '/includes/class-minimal-mcp-http-transport.php';

CMSA_Minimal_MCP_HTTP_Transport::bootstrap();
