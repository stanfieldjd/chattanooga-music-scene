<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "awpcp-marketplace-model-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "awpcp-marketplace-model-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb, $awpcp;

$plugin_file = WP_PLUGIN_DIR . '/another-wordpress-classifieds-plugin/awpcp.php';
$plugin_data = file_exists( $plugin_file ) ? get_plugin_data( $plugin_file, false, false ) : array();
$awpcp_version = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '';
$woocommerce_version = defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';

$defined_constants = get_defined_constants( true );
$user_constants = isset( $defined_constants['user'] ) ? $defined_constants['user'] : array();
$awpcp_constants = array();
foreach ( $user_constants as $name => $value ) {
	if ( 0 === strpos( $name, 'AWPCP_' ) ) {
		if ( is_scalar( $value ) || null === $value ) {
			$awpcp_constants[ $name ] = $value;
		} else {
			$awpcp_constants[ $name ] = gettype( $value );
		}
	}
}
ksort( $awpcp_constants );

$functions = get_defined_functions();
$awpcp_functions = array();
foreach ( isset( $functions['user'] ) ? $functions['user'] : array() as $function ) {
	if ( 0 === strpos( strtolower( $function ), 'awpcp_' ) ) {
		$awpcp_functions[] = $function;
	}
}
sort( $awpcp_functions, SORT_STRING );
$awpcp_functions = array_slice( $awpcp_functions, 0, 250 );

$awpcp_classes = array();
$class_contracts = array();
foreach ( get_declared_classes() as $class ) {
	if ( 0 !== strpos( $class, 'AWPCP_' ) ) {
		continue;
	}
	$awpcp_classes[] = $class;
	if ( false !== stripos( $class, 'listing' ) || false !== stripos( $class, 'advert' ) || false !== stripos( $class, 'payment' ) ) {
		try {
			$reflection = new ReflectionClass( $class );
			$public_methods = array();
			foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
				if ( $method->getDeclaringClass()->getName() !== $class ) {
					continue;
				}
				$public_methods[] = $method->getName();
			}
			sort( $public_methods, SORT_STRING );
			$class_contracts[ $class ] = array_slice( $public_methods, 0, 120 );
		} catch ( Throwable $error ) {
			$class_contracts[ $class ] = array( '__reflection_failed__' );
		}
	}
}
sort( $awpcp_classes, SORT_STRING );
$awpcp_classes = array_slice( $awpcp_classes, 0, 300 );
ksort( $class_contracts );

$all_tables = $wpdb->get_col( 'SHOW TABLES' );
$awpcp_tables = array();
$table_contracts = array();
foreach ( $all_tables as $table ) {
	if ( false === stripos( (string) $table, 'awpcp' ) ) {
		continue;
	}
	$awpcp_tables[] = (string) $table;
	if ( count( $awpcp_tables ) > 25 ) {
		break;
	}
	$escaped_table = str_replace( '`', '``', (string) $table );
	$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$escaped_table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- disposable schema introspection only; table name comes from SHOW TABLES and is identifier-escaped.
	$table_contracts[ (string) $table ] = array();
	foreach ( array_slice( $columns, 0, 80 ) as $column ) {
		$table_contracts[ (string) $table ][] = array(
			'field' => isset( $column['Field'] ) ? (string) $column['Field'] : '',
			'type'  => isset( $column['Type'] ) ? (string) $column['Type'] : '',
			'null'  => isset( $column['Null'] ) ? (string) $column['Null'] : '',
			'key'   => isset( $column['Key'] ) ? (string) $column['Key'] : '',
		);
	}
}
sort( $awpcp_tables, SORT_STRING );
ksort( $table_contracts );

$post_types = array();
foreach ( get_post_types( array(), 'objects' ) as $name => $object ) {
	$haystack = strtolower( $name . ' ' . ( isset( $object->label ) ? $object->label : '' ) . ' ' . ( isset( $object->labels->singular_name ) ? $object->labels->singular_name : '' ) );
	if ( false === strpos( $haystack, 'awpcp' ) && false === strpos( $haystack, 'classified' ) && false === strpos( $haystack, 'listing' ) && false === strpos( $haystack, 'advert' ) ) {
		continue;
	}
	$post_types[ $name ] = array(
		'label'           => isset( $object->label ) ? (string) $object->label : '',
		'public'          => isset( $object->public ) ? (bool) $object->public : null,
		'map_meta_cap'    => isset( $object->map_meta_cap ) ? (bool) $object->map_meta_cap : null,
		'capability_type' => isset( $object->capability_type ) ? $object->capability_type : null,
	);
}
ksort( $post_types );

$taxonomies = array();
foreach ( get_taxonomies( array(), 'objects' ) as $name => $object ) {
	$haystack = strtolower( $name . ' ' . ( isset( $object->label ) ? $object->label : '' ) . ' ' . implode( ' ', (array) $object->object_type ) );
	if ( false === strpos( $haystack, 'awpcp' ) && false === strpos( $haystack, 'classified' ) && false === strpos( $haystack, 'listing' ) && false === strpos( $haystack, 'advert' ) ) {
		continue;
	}
	$taxonomies[ $name ] = array(
		'object_type' => array_values( (array) $object->object_type ),
		'hierarchical' => isset( $object->hierarchical ) ? (bool) $object->hierarchical : null,
		'capabilities' => isset( $object->cap ) ? array(
			'manage_terms' => isset( $object->cap->manage_terms ) ? (string) $object->cap->manage_terms : '',
			'edit_terms'   => isset( $object->cap->edit_terms ) ? (string) $object->cap->edit_terms : '',
			'delete_terms' => isset( $object->cap->delete_terms ) ? (string) $object->cap->delete_terms : '',
			'assign_terms' => isset( $object->cap->assign_terms ) ? (string) $object->cap->assign_terms : '',
		) : array(),
	);
}
ksort( $taxonomies );

$admin_caps = array();
$user = wp_get_current_user();
foreach ( (array) $user->allcaps as $capability => $allowed ) {
	$lower = strtolower( (string) $capability );
	if ( false !== strpos( $lower, 'awpcp' ) || false !== strpos( $lower, 'classified' ) || false !== strpos( $lower, 'listing' ) ) {
		$admin_caps[ (string) $capability ] = (bool) $allowed;
	}
}
ksort( $admin_caps );

$option_names = $wpdb->get_col(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'awpcp%' ORDER BY option_name ASC LIMIT 100"
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed prefix discovery in disposable runtime, no user input.

$global_contract = array(
	'awpcp_global_present' => is_object( $awpcp ),
	'awpcp_global_class'   => is_object( $awpcp ) ? get_class( $awpcp ) : '',
);

$payload = array(
	'wordpress_version' => get_bloginfo( 'version' ),
	'awpcp' => array(
		'version' => $awpcp_version,
		'active'  => is_plugin_active( 'another-wordpress-classifieds-plugin/awpcp.php' ),
		'plugin_file' => 'another-wordpress-classifieds-plugin/awpcp.php',
	),
	'woocommerce' => array(
		'version' => $woocommerce_version,
		'active'  => is_plugin_active( 'woocommerce/woocommerce.php' ),
	),
	'global_contract' => $global_contract,
	'awpcp_constants' => $awpcp_constants,
	'awpcp_functions' => $awpcp_functions,
	'awpcp_classes' => $awpcp_classes,
	'listing_related_class_public_methods' => $class_contracts,
	'awpcp_tables' => $awpcp_tables,
	'awpcp_table_columns' => $table_contracts,
	'candidate_listing_post_types' => $post_types,
	'candidate_listing_taxonomies' => $taxonomies,
	'administrator_awpcp_capabilities' => $admin_caps,
	'awpcp_option_names' => array_values( array_map( 'strval', $option_names ) ),
);

echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

$failures = array();
if ( '7.1' !== (string) get_bloginfo( 'version' ) ) {
	$failures[] = 'unexpected_wordpress_version';
}
if ( '4.4.8' !== $awpcp_version ) {
	$failures[] = 'unexpected_awpcp_version:' . $awpcp_version;
}
if ( ! is_plugin_active( 'another-wordpress-classifieds-plugin/awpcp.php' ) ) {
	$failures[] = 'awpcp_not_active';
}
if ( '11.0.1' !== $woocommerce_version || ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	$failures[] = 'woocommerce_contract';
}
if ( ! is_object( $awpcp ) ) {
	$failures[] = 'awpcp_runtime_global_missing';
}
if ( empty( $awpcp_tables ) ) {
	$failures[] = 'awpcp_storage_not_discovered';
}
if ( empty( $awpcp_functions ) || empty( $awpcp_classes ) ) {
	$failures[] = 'awpcp_runtime_api_not_discovered';
}

if ( $failures ) {
	fwrite( STDERR, 'awpcp-marketplace-model-cli: FAIL ' . implode( ',', $failures ) . "\n" );
	exit( 1 );
}

echo 'awpcp-marketplace-model-cli: PASS version=' . $awpcp_version . ' tables=' . count( $awpcp_tables ) . ' functions=' . count( $awpcp_functions ) . ' classes=' . count( $awpcp_classes ) . ' model=discovered-not-yet-admitted' . "\n";
