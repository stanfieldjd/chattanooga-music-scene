<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "awpcp-marketplace-fixture-contract-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "awpcp-marketplace-fixture-contract-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$failures = array();

function cmsa_awpcp_fixture_check( $condition, $code ) {
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

foreach ( array( 'awpcp_listings_collection', 'awpcp_listings_api', 'awpcp_listing_authorization', 'awpcp_listing_renderer' ) as $helper ) {
	if ( ! function_exists( $helper ) ) {
		$failures[] = 'missing_helper:' . $helper;
	}
}

$post_type = get_post_type_object( 'awpcp_listing' );
if ( ! $post_type || ! $post_type->map_meta_cap || 'awpcp_classified_ad' !== $post_type->capability_type ) {
	$failures[] = 'listing_post_type_contract';
}

if ( $failures ) {
	fwrite( STDERR, 'awpcp-marketplace-fixture-contract-cli: PRECONDITION_FAIL ' . implode( ',', $failures ) . "\n" );
	exit( 1 );
}

$collection    = awpcp_listings_collection();
$listings_api  = awpcp_listings_api();
$authorization = awpcp_listing_authorization();
$renderer      = awpcp_listing_renderer();

cmsa_awpcp_fixture_check( $collection instanceof AWPCP_ListingsCollection, 'collection_factory_type' );
cmsa_awpcp_fixture_check( $listings_api instanceof AWPCP_ListingsAPI, 'api_factory_type' );
cmsa_awpcp_fixture_check( $authorization instanceof AWPCP_ListingAuthorization, 'authorization_factory_type' );
cmsa_awpcp_fixture_check( $renderer instanceof AWPCP_ListingRenderer, 'renderer_factory_type' );

$owner_id    = 0;
$stranger_id = 0;
$listing     = null;
$listing_id  = 0;
$payload     = array(
	'wordpress_version'   => (string) get_bloginfo( 'version' ),
	'awpcp_version'       => (string) AWPCP_VERSION,
	'woocommerce_version' => (string) WC_VERSION,
	'fixture_scope'       => 'ephemeral_ci_only',
	'listing_contract'    => array(),
	'collection_contract' => array(),
	'authorization_contract' => array(),
	'renderer_allowlist_sample' => array(),
	'cleanup'             => array(),
);

try {
	$owner_id = wp_insert_user(
		array(
			'user_login' => 'cmsa_listing_owner',
			'user_pass'  => wp_generate_password( 32, true, true ),
			'role'       => 'subscriber',
		)
	);
	if ( is_wp_error( $owner_id ) ) {
		throw new RuntimeException( 'owner_fixture_create_failed' );
	}

	$stranger_id = wp_insert_user(
		array(
			'user_login' => 'cmsa_listing_stranger',
			'user_pass'  => wp_generate_password( 32, true, true ),
			'role'       => 'subscriber',
		)
	);
	if ( is_wp_error( $stranger_id ) ) {
		throw new RuntimeException( 'stranger_fixture_create_failed' );
	}

	wp_set_current_user( $admin->ID );
	$listing = $listings_api->create_listing(
		array(
			'post_fields' => array(
				'post_title'   => 'CMSA Marketplace Fixture Listing',
				'post_content' => 'Disposable AWP Classifieds runtime-contract fixture.',
				'post_author'  => (int) $owner_id,
			),
			'metadata' => array(),
		)
	);

	cmsa_awpcp_fixture_check( $listing instanceof WP_Post, 'create_return_not_wp_post' );
	if ( $listing instanceof WP_Post ) {
		$listing_id = (int) $listing->ID;
		cmsa_awpcp_fixture_check( $listing_id > 0, 'listing_id_not_positive' );
		cmsa_awpcp_fixture_check( 'awpcp_listing' === $listing->post_type, 'listing_post_type_mismatch' );
		cmsa_awpcp_fixture_check( (int) $owner_id === (int) $listing->post_author, 'listing_author_mismatch' );
		cmsa_awpcp_fixture_check( 'disabled' === $listing->post_status, 'listing_default_status_mismatch' );

		$payload['listing_contract'] = array(
			'create_return_class' => get_class( $listing ),
			'id_is_positive_integer' => $listing_id > 0,
			'post_type'           => $listing->post_type,
			'post_status'         => $listing->post_status,
			'author_is_owner_fixture' => (int) $owner_id === (int) $listing->post_author,
		);

		$fetched = $collection->get( $listing_id );
		cmsa_awpcp_fixture_check( $fetched instanceof WP_Post, 'collection_get_not_wp_post' );
		cmsa_awpcp_fixture_check( $fetched instanceof WP_Post && (int) $fetched->ID === $listing_id, 'collection_get_id_mismatch' );

		$bounded = $collection->find_listings(
			array(
				'post_type'      => AWPCP_LISTING_POST_TYPE,
				'post_status'    => $listing->post_status,
				'post__in'       => array( $listing_id ),
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		$last_query = $collection->get_last_query();
		cmsa_awpcp_fixture_check( is_array( $bounded ), 'collection_find_not_array' );
		cmsa_awpcp_fixture_check( 1 === count( $bounded ), 'collection_find_not_bounded_to_one' );
		cmsa_awpcp_fixture_check( isset( $bounded[0] ) && $bounded[0] instanceof WP_Post, 'collection_find_result_not_wp_post' );
		cmsa_awpcp_fixture_check( isset( $bounded[0] ) && (int) $bounded[0]->ID === $listing_id, 'collection_find_id_mismatch' );
		cmsa_awpcp_fixture_check( $last_query instanceof WP_Query, 'collection_last_query_not_wp_query' );
		if ( $last_query instanceof WP_Query ) {
			cmsa_awpcp_fixture_check( 1 === (int) $last_query->query_vars['posts_per_page'], 'query_posts_per_page_not_one' );
			cmsa_awpcp_fixture_check( 'awpcp_listing' === $last_query->query_vars['post_type'], 'query_post_type_mismatch' );
			cmsa_awpcp_fixture_check( array( $listing_id ) === array_map( 'intval', (array) $last_query->query_vars['post__in'] ), 'query_post_in_mismatch' );
		}

		$payload['collection_contract'] = array(
			'get_return_class' => is_object( $fetched ) ? get_class( $fetched ) : gettype( $fetched ),
			'get_id_matches'    => $fetched instanceof WP_Post && (int) $fetched->ID === $listing_id,
			'find_result_count' => is_array( $bounded ) ? count( $bounded ) : null,
			'find_result_class' => isset( $bounded[0] ) && is_object( $bounded[0] ) ? get_class( $bounded[0] ) : null,
			'find_id_matches'   => isset( $bounded[0] ) && (int) $bounded[0]->ID === $listing_id,
			'last_query_posts_per_page' => $last_query instanceof WP_Query ? (int) $last_query->query_vars['posts_per_page'] : null,
			'last_query_post_type'      => $last_query instanceof WP_Query ? $last_query->query_vars['post_type'] : null,
		);

		$read_meta_cap = $post_type->cap->read_post;
		$edit_meta_cap = $post_type->cap->edit_post;

		wp_set_current_user( $admin->ID );
		$admin_auth = array(
			'plugin_edit'  => (bool) $authorization->is_current_user_allowed_to_edit_listing( $listing ),
			'plugin_manage'=> (bool) $authorization->is_current_user_allowed_to_manage_listing( $listing ),
			'manage_awpcp' => (bool) current_user_can( 'manage_awpcp' ),
			'core_read_object' => (bool) current_user_can( $read_meta_cap, $listing_id ),
			'core_edit_object' => (bool) current_user_can( $edit_meta_cap, $listing_id ),
		);
		cmsa_awpcp_fixture_check( $admin_auth['plugin_edit'] && $admin_auth['plugin_manage'] && $admin_auth['manage_awpcp'], 'administrator_authorization_contract' );

		wp_set_current_user( $owner_id );
		$owner_auth = array(
			'plugin_edit'  => (bool) $authorization->is_current_user_allowed_to_edit_listing( $listing ),
			'plugin_manage'=> (bool) $authorization->is_current_user_allowed_to_manage_listing( $listing ),
			'manage_awpcp' => (bool) current_user_can( 'manage_awpcp' ),
			'core_read_object' => (bool) current_user_can( $read_meta_cap, $listing_id ),
			'core_edit_object' => (bool) current_user_can( $edit_meta_cap, $listing_id ),
		);
		cmsa_awpcp_fixture_check( $owner_auth['plugin_edit'] && $owner_auth['plugin_manage'] && ! $owner_auth['manage_awpcp'], 'owner_authorization_contract' );

		wp_set_current_user( $stranger_id );
		$stranger_auth = array(
			'plugin_edit'  => (bool) $authorization->is_current_user_allowed_to_edit_listing( $listing ),
			'plugin_manage'=> (bool) $authorization->is_current_user_allowed_to_manage_listing( $listing ),
			'manage_awpcp' => (bool) current_user_can( 'manage_awpcp' ),
			'core_read_object' => (bool) current_user_can( $read_meta_cap, $listing_id ),
			'core_edit_object' => (bool) current_user_can( $edit_meta_cap, $listing_id ),
		);
		cmsa_awpcp_fixture_check( ! $stranger_auth['plugin_edit'] && ! $stranger_auth['plugin_manage'] && ! $stranger_auth['manage_awpcp'], 'stranger_authorization_contract' );

		wp_set_current_user( 0 );
		$anonymous_auth = array(
			'plugin_edit'  => (bool) $authorization->is_current_user_allowed_to_edit_listing( $listing ),
			'plugin_manage'=> (bool) $authorization->is_current_user_allowed_to_manage_listing( $listing ),
			'manage_awpcp' => (bool) current_user_can( 'manage_awpcp' ),
			'core_read_object' => (bool) current_user_can( $read_meta_cap, $listing_id ),
			'core_edit_object' => (bool) current_user_can( $edit_meta_cap, $listing_id ),
		);
		cmsa_awpcp_fixture_check( ! $anonymous_auth['plugin_edit'] && ! $anonymous_auth['plugin_manage'] && ! $anonymous_auth['manage_awpcp'], 'anonymous_authorization_contract' );

		$payload['authorization_contract'] = array(
			'administrator' => $admin_auth,
			'owner_subscriber' => $owner_auth,
			'non_owner_subscriber' => $stranger_auth,
			'anonymous' => $anonymous_auth,
			'object_read_meta_cap' => $read_meta_cap,
			'object_edit_meta_cap' => $edit_meta_cap,
		);

		wp_set_current_user( $admin->ID );
		$payload['renderer_allowlist_sample'] = array(
			'title'        => $renderer->get_listing_title( $listing ),
			'views'        => $renderer->get_views_count( $listing ),
			'is_public'    => (bool) $renderer->is_public( $listing ),
			'is_disabled'  => (bool) $renderer->is_disabled( $listing ),
			'is_expired'   => (bool) $renderer->is_expired( $listing ),
			'is_featured'  => (bool) $renderer->is_featured( $listing ),
			'category_ids' => array_map( 'intval', (array) $renderer->get_categories_ids( $listing ) ),
		);
		cmsa_awpcp_fixture_check( 'CMSA Marketplace Fixture Listing' === $payload['renderer_allowlist_sample']['title'], 'renderer_title_mismatch' );
		cmsa_awpcp_fixture_check( true === $payload['renderer_allowlist_sample']['is_disabled'], 'renderer_disabled_state_mismatch' );
	}
} catch ( Throwable $error ) {
	$failures[] = 'runtime_exception:' . get_class( $error ) . ':' . $error->getMessage();
} finally {
	wp_set_current_user( $admin->ID );

	if ( $listing instanceof WP_Post ) {
		$delete_result = (bool) $listings_api->delete_listing( $listing );
		$payload['cleanup']['listing_delete_result'] = $delete_result;
		cmsa_awpcp_fixture_check( $delete_result, 'listing_cleanup_delete_failed' );

		$payload['cleanup']['post_absent_after_delete'] = null === get_post( $listing_id );
		cmsa_awpcp_fixture_check( $payload['cleanup']['post_absent_after_delete'], 'listing_cleanup_post_still_exists' );

		$get_throws_after_delete = false;
		try {
			$collection->get( $listing_id );
		} catch ( AWPCP_Exception $error ) {
			$get_throws_after_delete = true;
		}
		$payload['cleanup']['collection_get_rejects_deleted_id'] = $get_throws_after_delete;
		cmsa_awpcp_fixture_check( $get_throws_after_delete, 'listing_cleanup_collection_get_still_resolves' );
	}

	if ( $owner_id && ! is_wp_error( $owner_id ) ) {
		$payload['cleanup']['owner_user_deleted'] = (bool) wp_delete_user( (int) $owner_id );
		cmsa_awpcp_fixture_check( $payload['cleanup']['owner_user_deleted'], 'owner_cleanup_failed' );
	}
	if ( $stranger_id && ! is_wp_error( $stranger_id ) ) {
		$payload['cleanup']['stranger_user_deleted'] = (bool) wp_delete_user( (int) $stranger_id );
		cmsa_awpcp_fixture_check( $payload['cleanup']['stranger_user_deleted'], 'stranger_cleanup_failed' );
	}
}

echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

if ( $failures ) {
	fwrite( STDERR, 'awpcp-marketplace-fixture-contract-cli: FAIL ' . implode( ',', $failures ) . "\n" );
	exit( 1 );
}

echo 'awpcp-marketplace-fixture-contract-cli: PASS id=wp_post_id collection=wp_post bounded_query=verified authorization=observed cleanup=verified' . "\n";
