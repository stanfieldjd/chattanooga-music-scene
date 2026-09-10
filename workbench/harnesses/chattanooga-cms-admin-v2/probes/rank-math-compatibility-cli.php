<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_v2_rm_fail( $message ) {
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_rm_catalog() {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}

	$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
	if ( ! $catalog instanceof WP_Ability ) {
		cmsa_v2_rm_fail( 'Universal catalog is unavailable in Rank Math compatibility job.' );
	}

	$result = $catalog->execute( array() );
	if ( is_wp_error( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
		cmsa_v2_rm_fail( 'Universal catalog could not enumerate Rank Math contracts.' );
	}

	$items = $result['items'];
	return $items;
}

function cmsa_v2_rm_ability( $target ) {
	$matches = array();
	foreach ( cmsa_v2_rm_catalog() as $item ) {
		if ( 'ability' === ( $item['contract'] ?? '' ) && $target === ( $item['target'] ?? '' ) && ! empty( $item['bridge'] ) ) {
			$matches[] = (string) $item['bridge'];
		}
	}

	if ( 1 !== count( $matches ) ) {
		cmsa_v2_rm_fail( sprintf( 'Expected one bridged Rank Math ability for %1$s; found %2$d.', $target, count( $matches ) ) );
	}

	$ability = wp_get_ability( $matches[0] );
	if ( ! $ability instanceof WP_Ability ) {
		cmsa_v2_rm_fail( 'Discovered Rank Math facade is unavailable.' );
	}

	return $ability;
}

function cmsa_v2_rm_execute( $target, array $input ) {
	$ability = cmsa_v2_rm_ability( $target );
	$permission = $ability->check_permissions( $input );
	if ( true !== $permission ) {
		cmsa_v2_rm_fail( 'Rank Math provider permission was not preserved for ' . $target . '.' );
	}

	$result = $ability->execute( $input );
	if ( is_wp_error( $result ) ) {
		cmsa_v2_rm_fail( sprintf( '%1$s failed: %2$s %3$s', $target, $result->get_error_code(), $result->get_error_message() ) );
	}

	return $result;
}

wp_set_current_user( 1 );

$plugin_file = WP_PLUGIN_DIR . '/seo-by-rank-math/rank-math.php';
if ( ! file_exists( $plugin_file ) ) {
	cmsa_v2_rm_fail( 'Rank Math plugin entrypoint is missing.' );
}

if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$plugin_data = get_plugin_data( $plugin_file, false, false );
if ( '1.0.278' !== (string) ( $plugin_data['Version'] ?? '' ) ) {
	cmsa_v2_rm_fail( 'Rank Math SEO 1.0.278 is not the active compatibility target.' );
}

$get_settings = cmsa_v2_rm_ability( 'rank-math/get-settings' );
$get_post_meta = cmsa_v2_rm_ability( 'rank-math/get-post-seo-meta' );
$set_global = cmsa_v2_rm_ability( 'rank-math/set-global-seo-settings' );

$settings_input = array( 'sections' => array( 'titles' ) );
if ( true !== $get_settings->check_permissions( $settings_input ) ) {
	cmsa_v2_rm_fail( 'Rank Math settings-read permission was not preserved.' );
}

$before = cmsa_v2_rm_execute( 'rank-math/get-settings', $settings_input );
if ( empty( $before['titles'] ) || ! is_array( $before['titles'] ) || ! array_key_exists( 'title_separator', $before['titles'] ) ) {
	cmsa_v2_rm_fail( 'Rank Math settings read did not expose the titles contract.' );
}
$original_separator = (string) $before['titles']['title_separator'];
$alternate_separator = '|' === $original_separator ? '-' : '|';

$post_id = wp_insert_post(
	array(
		'post_title'   => 'CMSA v2 Rank Math disposable post',
		'post_content' => 'Disposable SEO compatibility content.',
		'post_status'  => 'draft',
		'post_type'    => 'post',
	),
	true
);
if ( is_wp_error( $post_id ) || (int) $post_id < 1 ) {
	cmsa_v2_rm_fail( 'Could not create disposable post for Rank Math metadata test.' );
}

$post_meta = cmsa_v2_rm_execute( 'rank-math/get-post-seo-meta', array( 'post_id' => (int) $post_id ) );
if ( ! is_array( $post_meta ) ) {
	wp_delete_post( (int) $post_id, true );
	cmsa_v2_rm_fail( 'Rank Math post SEO metadata did not return a structured result.' );
}

cmsa_v2_rm_execute( 'rank-math/set-global-seo-settings', array( 'title_separator' => $alternate_separator ) );
$changed = cmsa_v2_rm_execute( 'rank-math/get-settings', $settings_input );
if ( $alternate_separator !== (string) ( $changed['titles']['title_separator'] ?? '' ) ) {
	cmsa_v2_rm_execute( 'rank-math/set-global-seo-settings', array( 'title_separator' => $original_separator ) );
	wp_delete_post( (int) $post_id, true );
	cmsa_v2_rm_fail( 'Rank Math global SEO mutation did not persist through the universal facade.' );
}

cmsa_v2_rm_execute( 'rank-math/set-global-seo-settings', array( 'title_separator' => $original_separator ) );
$restored = cmsa_v2_rm_execute( 'rank-math/get-settings', $settings_input );
if ( $original_separator !== (string) ( $restored['titles']['title_separator'] ?? '' ) ) {
	wp_delete_post( (int) $post_id, true );
	cmsa_v2_rm_fail( 'Rank Math global SEO setting was not restored after compatibility test.' );
}

wp_set_current_user( 0 );
if ( false !== $set_global->check_permissions( array( 'title_separator' => $alternate_separator ) ) ) {
	wp_set_current_user( 1 );
	wp_delete_post( (int) $post_id, true );
	cmsa_v2_rm_fail( 'Anonymous Rank Math administration was not blocked.' );
}
wp_set_current_user( 1 );

wp_delete_post( (int) $post_id, true );

echo "cmsa-v2-rank-math: PASS version=1.0.278 native_abilities=bridged settings_read=verified post_seo_meta=verified global_seo_write=verified rollback=verified provider_permissions=preserved admin_boundary=verified candidate_provider_special_case=absent\n";
exit( 0 );
