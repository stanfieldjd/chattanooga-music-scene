<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "awpcp-marketplace-candidate-read-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/user.php';

$failures = array();
function cmsa_marketplace_candidate_check( $condition, $code ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $code;
	}
}

if ( '7.1' !== (string) get_bloginfo( 'version' ) ) {
	$failures[] = 'unexpected_wordpress_version';
}
if ( ! defined( 'AWPCP_VERSION' ) || '4.4.8' !== (string) AWPCP_VERSION ) {
	$failures[] = 'unexpected_awpcp_version';
}
if ( ! defined( 'WC_VERSION' ) || '11.0.1' !== (string) WC_VERSION ) {
	$failures[] = 'unexpected_woocommerce_version';
}
if ( ! defined( 'CMS_MARKETPLACE_VERSION' ) || '0.1.1' !== (string) CMS_MARKETPLACE_VERSION || ! class_exists( 'CMS_Unified_Marketplace' ) ) {
	$failures[] = 'marketplace_coexistence_contract';
}

if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$expected_path = dirname( __DIR__ ) . '/fixtures/expected-marketplace-listing-abilities.json';
$expected = json_decode( (string) file_get_contents( $expected_path ), true );
if ( ! is_array( $expected ) || 2 !== count( $expected ) ) {
	$failures[] = 'expected_fixture_invalid';
	$expected = array();
}
$abilities = array();
foreach ( $expected as $name ) {
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
	cmsa_marketplace_candidate_check( $ability && method_exists( $ability, 'check_permissions' ), 'missing_ability:' . $name );
	if ( $ability && method_exists( $ability, 'check_permissions' ) ) {
		$abilities[ $name ] = $ability;
	}
}

$permission_allowed = static function ( $ability ) {
	$result = $ability->check_permissions();
	return ! is_wp_error( $result ) && true === $result;
};

wp_set_current_user( 0 );
foreach ( $abilities as $name => $ability ) {
	cmsa_marketplace_candidate_check( ! $permission_allowed( $ability ), 'anonymous_permission_leak:' . $name );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	$failures[] = 'administrator_missing';
}
if ( $admin ) {
	wp_set_current_user( $admin->ID );
	foreach ( $abilities as $name => $ability ) {
		cmsa_marketplace_candidate_check( $permission_allowed( $ability ), 'administrator_denied:' . $name );
	}
}

$owner_id = 0;
$listing_ids = array();
$listings_api = null;
$payload = array(
	'abilities' => array_values( $expected ),
	'permission_boundary' => array(),
	'list_contract' => array(),
	'get_contract' => array(),
	'rest_isolation' => array(),
	'cleanup' => array(),
);

try {
	$owner_login = 'cmsa_market_owner_' . strtolower( wp_generate_password( 8, false, false ) );
	$owner_id = wp_create_user( $owner_login, wp_generate_password( 28, true, true ), $owner_login . '@example.invalid' );
	if ( is_wp_error( $owner_id ) ) {
		throw new RuntimeException( 'owner_create_failed' );
	}
	$owner = new WP_User( $owner_id );
	$owner->set_role( 'subscriber' );

	wp_set_current_user( $owner_id );
	$payload['permission_boundary']['subscriber_manage_awpcp'] = (bool) current_user_can( 'manage_awpcp' );
	$payload['permission_boundary']['subscriber_edit_others'] = (bool) current_user_can( 'edit_others_awpcp_classified_ads' );
	foreach ( $abilities as $name => $ability ) {
		cmsa_marketplace_candidate_check( ! $permission_allowed( $ability ), 'subscriber_permission_leak:' . $name );
	}

	$owner->add_cap( 'manage_awpcp', true );
	clean_user_cache( $owner_id );
	wp_set_current_user( 0 );
	wp_set_current_user( $owner_id );
	foreach ( $abilities as $name => $ability ) {
		cmsa_marketplace_candidate_check( ! $permission_allowed( $ability ), 'manage_awpcp_alone_opened:' . $name );
	}
	$owner->remove_cap( 'manage_awpcp' );
	$owner->add_cap( 'edit_others_awpcp_classified_ads', true );
	clean_user_cache( $owner_id );
	wp_set_current_user( 0 );
	wp_set_current_user( $owner_id );
	foreach ( $abilities as $name => $ability ) {
		cmsa_marketplace_candidate_check( ! $permission_allowed( $ability ), 'edit_others_alone_opened:' . $name );
	}
	$owner->remove_cap( 'edit_others_awpcp_classified_ads' );
	clean_user_cache( $owner_id );

	wp_set_current_user( $admin->ID );
	$listings_api = awpcp_listings_api();
	foreach ( array( 'Alpha', 'Beta' ) as $suffix ) {
		$listing = $listings_api->create_listing(
			array(
				'post_fields' => array(
					'post_title'   => 'CMSA Adapter Fixture ' . $suffix,
					'post_content' => 'CMSA adapter detail content ' . $suffix,
					'post_excerpt' => 'CMSA adapter excerpt ' . $suffix,
					'post_author'  => (int) $owner_id,
				),
				'metadata' => array(),
			)
		);
		if ( ! $listing instanceof WP_Post || $listing->ID < 1 ) {
			throw new RuntimeException( 'listing_create_failed:' . $suffix );
		}
		$listing_ids[] = (int) $listing->ID;
	}

	$service = new CMSA_Marketplace_Listings();
	$page_one = $service->list_items(
		array(
			'page' => 1,
			'per_page' => 1,
			'search' => 'CMSA Adapter Fixture',
			'status' => 'disabled',
		)
	);
	$page_two = $service->list_items(
		array(
			'page' => 2,
			'per_page' => 1,
			'search' => 'CMSA Adapter Fixture',
			'status' => 'disabled',
		)
	);
	cmsa_marketplace_candidate_check( ! is_wp_error( $page_one ) && ! is_wp_error( $page_two ), 'bounded_list_failed' );
	if ( ! is_wp_error( $page_one ) && ! is_wp_error( $page_two ) ) {
		cmsa_marketplace_candidate_check( 2 === (int) $page_one['total'], 'bounded_total_mismatch' );
		cmsa_marketplace_candidate_check( 2 === (int) $page_one['pages'], 'bounded_pages_mismatch' );
		cmsa_marketplace_candidate_check( 1 === count( $page_one['items'] ) && 1 === count( $page_two['items'] ), 'bounded_page_size_mismatch' );
		$seen = array_merge(
			array_map( static function ( $item ) { return (int) $item['id']; }, $page_one['items'] ),
			array_map( static function ( $item ) { return (int) $item['id']; }, $page_two['items'] )
		);
		sort( $seen, SORT_NUMERIC );
		$expected_ids = $listing_ids;
		sort( $expected_ids, SORT_NUMERIC );
		cmsa_marketplace_candidate_check( $expected_ids === $seen, 'bounded_pagination_identity_mismatch' );
		$payload['list_contract'] = array(
			'total' => (int) $page_one['total'],
			'pages' => (int) $page_one['pages'],
			'ids' => $seen,
		);
	}

	$list_keys = array( 'id', 'title', 'status', 'price', 'category_ids', 'views', 'start_date', 'end_date', 'is_public', 'is_disabled', 'has_expired', 'is_featured', 'is_flagged', 'is_pending_approval', 'is_verified', 'needs_review', 'view_url', 'modified_gmt' );
	sort( $list_keys );
	if ( ! is_wp_error( $page_one ) && ! empty( $page_one['items'][0] ) ) {
		$actual_keys = array_keys( $page_one['items'][0] );
		sort( $actual_keys );
		cmsa_marketplace_candidate_check( $list_keys === $actual_keys, 'list_allowlist_mismatch' );
	}

	$detail = $service->get_item( $listing_ids[0] );
	cmsa_marketplace_candidate_check( ! is_wp_error( $detail ) && isset( $detail['listing'] ), 'get_listing_failed' );
	if ( ! is_wp_error( $detail ) && isset( $detail['listing'] ) ) {
		$detail_keys = array_merge( $list_keys, array( 'content', 'excerpt' ) );
		sort( $detail_keys );
		$actual_keys = array_keys( $detail['listing'] );
		sort( $actual_keys );
		cmsa_marketplace_candidate_check( $detail_keys === $actual_keys, 'detail_allowlist_mismatch' );
		cmsa_marketplace_candidate_check( 'CMSA adapter detail content Alpha' === $detail['listing']['content'], 'detail_content_mismatch' );
		cmsa_marketplace_candidate_check( 'CMSA adapter excerpt Alpha' === $detail['listing']['excerpt'], 'detail_excerpt_mismatch' );
		$payload['get_contract'] = array(
			'id' => (int) $detail['listing']['id'],
			'keys' => $actual_keys,
		);
	}

	$invalid_status = $service->list_items( array( 'status' => 'not-a-real-status' ) );
	cmsa_marketplace_candidate_check( is_wp_error( $invalid_status ) && 'cmsa_marketplace_listing_status' === $invalid_status->get_error_code(), 'invalid_status_not_rejected' );
	$missing = $service->get_item( 2147483647 );
	cmsa_marketplace_candidate_check( is_wp_error( $missing ) && 'cmsa_marketplace_listing_not_found' === $missing->get_error_code(), 'missing_listing_not_rejected' );

	if ( ! function_exists( 'rest_get_server' ) || ! function_exists( 'rest_do_request' ) ) {
		$failures[] = 'rest_functions_unavailable';
	} else {
		$server = rest_get_server();
		if ( 0 === did_action( 'rest_api_init' ) ) {
			do_action( 'rest_api_init', $server );
		}
		$routes = $server->get_routes();
		$collection_routes = array();
		foreach ( array_keys( $routes ) as $route ) {
			if ( false !== stripos( $route, 'abilit' ) && false === strpos( $route, '(?P<' ) && preg_match( '#/abilities/?$#', $route ) ) {
				$collection_routes[] = rtrim( $route, '/' );
			}
		}
		cmsa_marketplace_candidate_check( ! empty( $collection_routes ), 'ability_collection_route_missing' );

		$response_contains_marketplace_ability = static function ( $value ) use ( &$response_contains_marketplace_ability, $expected ) {
			if ( is_string( $value ) ) {
				foreach ( $expected as $name ) {
					if ( false !== strpos( $value, $name ) ) {
						return true;
					}
				}
				return false;
			}
			if ( is_array( $value ) ) {
				foreach ( $value as $key => $item ) {
					if ( $response_contains_marketplace_ability( $key ) || $response_contains_marketplace_ability( $item ) ) {
						return true;
					}
				}
			}
			if ( is_object( $value ) ) {
				return $response_contains_marketplace_ability( get_object_vars( $value ) );
			}
			return false;
		};

		foreach ( $collection_routes as $collection_route ) {
			$collection_response = rest_do_request( new WP_REST_Request( 'GET', $collection_route ) );
			cmsa_marketplace_candidate_check( ! $response_contains_marketplace_ability( $collection_response->get_data() ), 'rest_collection_leak:' . $collection_route );
			foreach ( $expected as $name ) {
				$direct_route = $collection_route . '/' . $name;
				foreach ( array( 'GET', 'POST' ) as $method ) {
					$response = rest_do_request( new WP_REST_Request( $method, $direct_route ) );
					cmsa_marketplace_candidate_check( ! $response_contains_marketplace_ability( $response->get_data() ), 'rest_direct_leak:' . $method . ':' . $name );
					if ( 'POST' === $method ) {
						cmsa_marketplace_candidate_check( $response->get_status() >= 400, 'rest_direct_execution:' . $name );
					}
				}
			}
		}
		$payload['rest_isolation']['collection_routes'] = $collection_routes;
	}
} catch ( Throwable $error ) {
	$failures[] = 'runtime_exception:' . get_class( $error ) . ':' . $error->getMessage();
} finally {
	if ( $admin ) {
		wp_set_current_user( $admin->ID );
	}
	if ( $listings_api ) {
		foreach ( $listing_ids as $listing_id ) {
			$post = get_post( $listing_id );
			if ( $post instanceof WP_Post ) {
				$deleted = (bool) $listings_api->delete_listing( $post );
				$payload['cleanup']['listing_' . $listing_id] = $deleted && null === get_post( $listing_id );
				cmsa_marketplace_candidate_check( $payload['cleanup']['listing_' . $listing_id], 'listing_cleanup_failed:' . $listing_id );
			}
		}
	}
	if ( $owner_id && ! is_wp_error( $owner_id ) ) {
		$payload['cleanup']['owner_deleted'] = (bool) wp_delete_user( (int) $owner_id );
		cmsa_marketplace_candidate_check( $payload['cleanup']['owner_deleted'], 'owner_cleanup_failed' );
	}
}

echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
if ( $failures ) {
	fwrite( STDERR, 'awpcp-marketplace-candidate-read-cli: FAIL ' . implode( ',', $failures ) . "\n" );
	exit( 1 );
}

echo "awpcp-marketplace-candidate-read-cli: PASS abilities=2 permission=bounded list=get=verified rest=isolated coexistence=verified cleanup=verified\n";
