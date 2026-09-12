<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_native_direct_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . (string) $message . "\n" );
	exit( 1 );
}

function cmsa_native_direct_assert( $condition, $message ) {
	if ( ! $condition ) {
		cmsa_native_direct_fail( $message );
	}
}

function cmsa_native_direct_request( $method, array $params, $id ) {
	$params['_meta'] = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
	$params['_meta']['io.modelcontextprotocol/protocolVersion'] = '2026-07-28';
	$params['_meta']['io.modelcontextprotocol/clientCapabilities'] = (object) array();
	$params['_meta']['io.modelcontextprotocol/clientInfo'] = array(
		'name'    => 'cmsa-native-direct-boundary',
		'version' => '1.0.0',
	);

	$request = new WP_REST_Request( 'POST', '/chattanooga-cms-admin/v1/mcp' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'MCP-Protocol-Version', '2026-07-28' );
	$request->set_header( 'Mcp-Method', $method );
	if ( 'tools/call' === $method && isset( $params['name'] ) ) {
		$request->set_header( 'Mcp-Name', (string) $params['name'] );
	}
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'method'  => $method,
				'params'  => $params,
			)
		)
	);

	return rest_do_request( $request );
}

wp_set_current_user( 1 );

cmsa_native_direct_assert( defined( 'CUA_VERSION' ) && '1.1.0' === CUA_VERSION, 'Chattanooga CMS Admin 1.1.0 did not load.' );
cmsa_native_direct_assert( class_exists( 'CUA_MCP_Server' ), 'Built-in MCP server class did not load.' );
cmsa_native_direct_assert( ! class_exists( 'CUA_MCP_Adapter_Compat' ), 'Adapter compatibility transport is present in the native-only build.' );
cmsa_native_direct_assert( ! class_exists( 'CUA_MCP_Adapter_Route_Policy' ), 'Adapter route policy is present in the native-only build.' );

$active_plugins = array_map( 'strtolower', (array) get_option( 'active_plugins', array() ) );
foreach ( $active_plugins as $plugin_basename ) {
	cmsa_native_direct_assert(
		false === strpos( $plugin_basename, 'miniorange-secure-mcp-server' ),
		'Third-party miniOrange MCP plugin is active in the native-only environment: ' . $plugin_basename
	);
	cmsa_native_direct_assert(
		false === strpos( $plugin_basename, 'mcp-adapter' ),
		'Third-party WordPress MCP Adapter plugin is active in the native-only environment: ' . $plugin_basename
	);
}
if ( function_exists( 'wp_get_ability' ) ) {
	cmsa_native_direct_assert(
		! wp_get_ability( 'mosmcp/cpt-list-types' ) instanceof WP_Ability,
		'miniOrange MCP Ability surface is registered in the native-only environment.'
	);
}

$routes = rest_get_server()->get_routes();
cmsa_native_direct_assert( isset( $routes['/chattanooga-cms-admin/v1/mcp'] ), 'Built-in MCP route is not registered.' );
cmsa_native_direct_assert( ! isset( $routes['/mcp/mcp-adapter-default-server'] ), 'Third-party adapter route is present in the disposable native-only environment.' );

// The plugin source itself must not acquire a WordPress MCP Adapter dependency.
$root = realpath( CUA_DIR );
cmsa_native_direct_assert( false !== $root, 'Unable to resolve Chattanooga CMS Admin source directory.' );
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}
	$source = file_get_contents( $file->getPathname() );
	cmsa_native_direct_assert( false !== $source, 'Unable to read plugin source file: ' . $file->getPathname() );
	cmsa_native_direct_assert(
		false === stripos( $source, 'mcp-adapter' ) && false === stripos( $source, 'McpAdapter' ),
		'WordPress MCP Adapter dependency found in native plugin source: ' . $file->getFilename()
	);
}

$discover = cmsa_native_direct_request( 'server/discover', array(), 501 );
cmsa_native_direct_assert( 200 === $discover->get_status(), 'Direct server/discover failed.' );
$discover_data = $discover->get_data();
cmsa_native_direct_assert( 'complete' === ( $discover_data['result']['resultType'] ?? '' ), 'Direct discovery did not return a complete result.' );
cmsa_native_direct_assert(
	'chattanooga-cms-admin' === ( $discover_data['result']['_meta']['io.modelcontextprotocol/serverInfo']['name'] ?? '' ),
	'Direct discovery returned the wrong server identity.'
);

$list = cmsa_native_direct_request( 'tools/list', array(), 502 );
cmsa_native_direct_assert( 200 === $list->get_status(), 'Direct tools/list failed.' );
$list_data = $list->get_data();
$tools = $list_data['result']['tools'] ?? null;
cmsa_native_direct_assert( is_array( $tools ) && ! empty( $tools ), 'Direct tools/list returned no tools.' );

$names = array();
foreach ( $tools as $tool ) {
	cmsa_native_direct_assert( is_array( $tool ) && isset( $tool['name'] ), 'Direct tools/list returned a malformed tool.' );
	$name = (string) $tool['name'];
	cmsa_native_direct_assert( 0 === strpos( $name, 'cmsa.' ), 'Non-Chattanooga tool leaked into the built-in MCP surface: ' . $name );
	$names[] = $name;
}
foreach ( array( 'cmsa.catalog', 'cmsa.get-health', 'cmsa.read-bridge', 'cmsa.write-bridge' ) as $required ) {
	cmsa_native_direct_assert( in_array( $required, $names, true ), 'Required direct MCP tool is missing: ' . $required );
}

$health = cmsa_native_direct_request(
	'tools/call',
	array(
		'name'      => 'cmsa.get-health',
		'arguments' => array(),
	),
	503
);
cmsa_native_direct_assert( 200 === $health->get_status(), 'Direct get-health tools/call failed.' );
$health_data = $health->get_data();
cmsa_native_direct_assert( false === ( $health_data['result']['isError'] ?? true ), 'Direct get-health tools/call returned an MCP tool error.' );
cmsa_native_direct_assert(
	get_bloginfo( 'version' ) === ( $health_data['result']['structuredContent']['wordpress_version'] ?? '' ),
	'Direct get-health returned the wrong WordPress version.'
);

echo 'cmsa-native-mcp-direct-boundary: PASS route=native-only third_party_mcp=absent adapter_dependency=absent discover=verified tools=' . count( $names ) . " read_call=verified envelope=verified\n";
exit( 0 );