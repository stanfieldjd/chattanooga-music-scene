<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_v2_rm_fail( $message ) {
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

function cmsa_v2_rm_rank_math_hook_count( $hook_name ) {
	global $wp_filter;
	if ( empty( $wp_filter[ $hook_name ] ) || empty( $wp_filter[ $hook_name ]->callbacks ) ) {
		return 0;
	}

	$count = 0;
	foreach ( $wp_filter[ $hook_name ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = $callback['function'] ?? null;
			if ( is_array( $function ) && isset( $function[0] ) && is_object( $function[0] ) && 0 === strpos( get_class( $function[0] ), 'RankMath\\Abilities\\' ) ) {
				++$count;
			}
		}
	}
	return $count;
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
		$direct = wp_get_ability( $target );
		cmsa_v2_rm_fail(
			sprintf(
				'Expected one bridged Rank Math ability for %1$s; found %2$d. direct=%3$s init=%4$d abilities_init=%5$d class=%6$s file=%7$s rank_math_ability_hooks=%8$d rank_math_category_hooks=%9$d',
				$target,
				count( $matches ),
				$direct instanceof WP_Ability ? 'present' : 'absent',
				did_action( 'init' ),
				did_action( 'wp_abilities_api_init' ),
				class_exists( 'RankMath\\Abilities\\Abilities' ) ? 'present' : 'absent',
				file_exists( WP_PLUGIN_DIR . '/seo-by-rank-math/includes/abilities/class-abilities.php' ) ? 'present' : 'absent',
				cmsa_v2_rm_rank_math_hook_count( 'wp_abilities_api_init' ),
				cmsa_v2_rm_rank_math_hook_count( 'wp_abilities_api_categories_init' )
			)
		);
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

$pre_registry_ability_hooks = cmsa_v2_rm_rank_math_hook_count( 'wp_abilities_api_init' );
$pre_registry_category_hooks = cmsa_v2_rm_rank_math_hook_count( 'wp_abilities_api_categories_init' );
if ( 0 === $pre_registry_ability_hooks || 0 === $pre_registry_category_hooks ) {
	cmsa_v2_rm_fail(
		sprintf(
			'Rank Math did not attach its Abilities API subscribers before registry access. init=%1$d abilities_init=%2$d class=%3$s file=%4$s ability_hooks=%5$d category_hooks=%6$d',
			did_action( 'init' ),
			did_action( 'wp_abilities_api_init' ),
			class_exists( 'RankMath\\Abilities\\Abilities' ) ? 'present' : 'absent',
			file_exists( WP_PLUGIN_DIR . '/seo-by-rank-math/includes/abilities/class-abilities.php' ) ? 'present' : 'absent',
			$pre_registry_ability_hooks,
			$pre_registry_category_hooks
		)
	);
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
