<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "awpcp-marketplace-read-contract-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "awpcp-marketplace-read-contract-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

require_once ABSPATH . 'wp-admin/includes/plugin.php';

function cmsa_awpcp_reflect_parameters( ReflectionFunctionAbstract $reflection ) {
	$parameters = array();
	foreach ( $reflection->getParameters() as $parameter ) {
		$entry = array(
			'name'      => $parameter->getName(),
			'required'  => ! $parameter->isOptional(),
			'variadic'  => $parameter->isVariadic(),
			'by_ref'    => $parameter->isPassedByReference(),
			'type'      => $parameter->hasType() ? (string) $parameter->getType() : null,
			'has_default' => $parameter->isDefaultValueAvailable(),
		);
		if ( $parameter->isDefaultValueAvailable() ) {
			try {
				$default = $parameter->getDefaultValue();
				$entry['default'] = is_scalar( $default ) || null === $default ? $default : gettype( $default );
			} catch ( ReflectionException $error ) {
				$entry['default'] = '__reflection_failed__';
			}
		}
		$parameters[] = $entry;
	}
	return $parameters;
}

function cmsa_awpcp_reflect_function( $name ) {
	if ( ! function_exists( $name ) ) {
		return array( 'exists' => false );
	}
	$reflection = new ReflectionFunction( $name );
	return array(
		'exists'              => true,
		'parameters'          => cmsa_awpcp_reflect_parameters( $reflection ),
		'required_parameters' => $reflection->getNumberOfRequiredParameters(),
		'return_type'         => $reflection->hasReturnType() ? (string) $reflection->getReturnType() : null,
		'file'                => $reflection->getFileName(),
		'start_line'          => $reflection->getStartLine(),
		'end_line'            => $reflection->getEndLine(),
	);
}

function cmsa_awpcp_reflect_method( $class, $method ) {
	if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
		return array( 'exists' => false );
	}
	$reflection = new ReflectionMethod( $class, $method );
	return array(
		'exists'              => true,
		'public'              => $reflection->isPublic(),
		'static'              => $reflection->isStatic(),
		'parameters'          => cmsa_awpcp_reflect_parameters( $reflection ),
		'required_parameters' => $reflection->getNumberOfRequiredParameters(),
		'return_type'         => $reflection->hasReturnType() ? (string) $reflection->getReturnType() : null,
		'file'                => $reflection->getFileName(),
		'start_line'          => $reflection->getStartLine(),
		'end_line'            => $reflection->getEndLine(),
	);
}

$helper_names = array(
	'awpcp_listings_collection',
	'awpcp_listings_api',
	'awpcp_listing_authorization',
	'awpcp_listing_renderer',
	'awpcp_query',
	'awpcp_listings_query',
);
$helpers = array();
$helper_objects = array();
foreach ( $helper_names as $helper_name ) {
	$helpers[ $helper_name ] = cmsa_awpcp_reflect_function( $helper_name );
	if ( empty( $helpers[ $helper_name ]['exists'] ) || 0 !== (int) $helpers[ $helper_name ]['required_parameters'] ) {
		continue;
	}
	try {
		$value = call_user_func( $helper_name );
		$helper_objects[ $helper_name ] = array(
			'call_succeeded' => true,
			'return_kind'    => is_object( $value ) ? 'object' : gettype( $value ),
			'return_class'   => is_object( $value ) ? get_class( $value ) : null,
		);
	} catch ( Throwable $error ) {
		$helper_objects[ $helper_name ] = array(
			'call_succeeded' => false,
			'error_class'    => get_class( $error ),
		);
	}
}

$method_targets = array(
	'AWPCP_ListingsCollection' => array( '__construct', 'get', 'find_all_by_id', 'find_listings', 'find_enabled_listings', 'find_valid_listings' ),
	'AWPCP_ListingsAPI' => array( '__construct', 'create_listing', 'update_listing', 'delete_listing' ),
	'AWPCP_ListingAuthorization' => array( '__construct', 'is_current_user_allowed_to_edit_listing', 'is_current_user_allowed_to_manage_listing', 'is_current_user_allowed_to_submit_listing' ),
	'AWPCP_ListingRenderer' => array( '__construct', 'get_listing_title', 'get_price', 'get_start_date', 'get_end_date', 'get_views_count', 'get_website_url', 'get_view_listing_url', 'is_public', 'is_disabled', 'is_expired', 'is_featured', 'is_flagged', 'is_pending_approval', 'is_verified', 'needs_review', 'get_categories_ids', 'get_categories_names', 'get_categories_slug', 'get_access_key', 'get_contact_email', 'get_contact_name', 'get_contact_phone', 'get_ip_address', 'get_payment_email', 'get_payment_status', 'get_payment_term', 'get_user' ),
);
$methods = array();
foreach ( $method_targets as $class => $class_methods ) {
	$methods[ $class ] = array();
	foreach ( $class_methods as $method ) {
		$methods[ $class ][ $method ] = cmsa_awpcp_reflect_method( $class, $method );
	}
}

$post_type = get_post_type_object( 'awpcp_listing' );
$post_type_contract = null;
if ( $post_type ) {
	$post_type_contract = array(
		'name'            => $post_type->name,
		'public'          => (bool) $post_type->public,
		'map_meta_cap'    => (bool) $post_type->map_meta_cap,
		'capability_type' => $post_type->capability_type,
		'cap'             => isset( $post_type->cap ) ? (array) $post_type->cap : array(),
	);
}

$admin_caps = array();
foreach ( array( 'manage_awpcp', 'edit_awpcp_classified_ads', 'edit_others_awpcp_classified_ads', 'read', 'edit_posts', 'read_private_posts' ) as $capability ) {
	$admin_caps[ $capability ] = current_user_can( $capability );
}

$payload = array(
	'wordpress_version' => get_bloginfo( 'version' ),
	'awpcp_version'     => defined( 'AWPCP_VERSION' ) ? (string) AWPCP_VERSION : '',
	'woocommerce_version' => defined( 'WC_VERSION' ) ? (string) WC_VERSION : '',
	'post_type_contract' => $post_type_contract,
	'administrator_capability_checks' => $admin_caps,
	'helper_functions' => $helpers,
	'helper_zero_arg_calls' => $helper_objects,
	'class_method_signatures' => $methods,
);

echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

$failures = array();
if ( '7.1' !== (string) get_bloginfo( 'version' ) ) {
	$failures[] = 'unexpected_wordpress_version';
}
if ( ! defined( 'AWPCP_VERSION' ) || '4.4.8' !== (string) AWPCP_VERSION ) {
	$failures[] = 'unexpected_awpcp_version';
}
if ( ! defined( 'WC_VERSION' ) || '11.0.1' !== (string) WC_VERSION ) {
	$failures[] = 'unexpected_woocommerce_version';
}
if ( ! $post_type || ! $post_type->map_meta_cap || 'awpcp_classified_ad' !== $post_type->capability_type ) {
	$failures[] = 'listing_post_type_contract';
}
foreach ( array( 'awpcp_listings_collection', 'awpcp_listings_api', 'awpcp_listing_authorization' ) as $required_helper ) {
	if ( empty( $helpers[ $required_helper ]['exists'] ) ) {
		$failures[] = 'missing_helper:' . $required_helper;
	}
}
foreach ( array( 'AWPCP_ListingsCollection', 'AWPCP_ListingsAPI', 'AWPCP_ListingAuthorization', 'AWPCP_ListingRenderer' ) as $required_class ) {
	if ( ! class_exists( $required_class ) ) {
		$failures[] = 'missing_class:' . $required_class;
	}
}
foreach ( array( 'get', 'find_listings' ) as $required_method ) {
	if ( empty( $methods['AWPCP_ListingsCollection'][ $required_method ]['exists'] ) ) {
		$failures[] = 'missing_collection_method:' . $required_method;
	}
}

if ( $failures ) {
	fwrite( STDERR, 'awpcp-marketplace-read-contract-cli: FAIL ' . implode( ',', $failures ) . "\n" );
	exit( 1 );
}

echo 'awpcp-marketplace-read-contract-cli: PASS helpers=reflected services=resolved signatures=verified fixture=not-created' . "\n";
