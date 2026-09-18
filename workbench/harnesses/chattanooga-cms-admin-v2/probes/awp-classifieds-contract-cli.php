<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

function cmsa_v2_awp_fail( $message ) {
	fwrite( STDERR, (string) $message . "\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

if ( ! post_type_exists( 'awpcp_listing' ) ) {
	cmsa_v2_awp_fail( 'AWP Classifieds did not register its classified post type.' );
}

$target_name = 'mosmcp/cpt-list-types';
$native = wp_get_ability( $target_name );
if ( ! $native instanceof WP_Ability ) {
	cmsa_v2_awp_fail( 'miniOrange did not register the generic CPT ability used by the live MCP environment.' );
}

$meta = $native->get_meta();
if ( true === ( $meta['public'] ?? false ) ) {
	cmsa_v2_awp_fail( 'Generic CPT ability unexpectedly became public; revisit the universal exposure boundary.' );
}
if ( true === ( $meta['show_in_rest'] ?? false ) ) {
	cmsa_v2_awp_fail( 'Generic CPT ability unexpectedly became REST-public; revisit the universal exposure boundary.' );
}
if ( isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && true === ( $meta['mcp']['public'] ?? false ) ) {
	cmsa_v2_awp_fail( 'Generic CPT ability unexpectedly became MCP-public at the WordPress Ability metadata layer.' );
}

$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
if ( ! $catalog instanceof WP_Ability ) {
	cmsa_v2_awp_fail( 'Universal catalog is unavailable.' );
}
$result = $catalog->execute( array() );
if ( is_wp_error( $result ) || empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
	cmsa_v2_awp_fail( 'Universal catalog could not be inspected.' );
}

foreach ( $result['items'] as $item ) {
	if ( 'ability' === ( $item['contract'] ?? '' ) && $target_name === ( $item['target'] ?? '' ) ) {
		cmsa_v2_awp_fail( 'Chattanooga CMS Admin exposed a provider ability that explicitly declares itself non-public.' );
	}
}

wp_set_current_user( 0 );
if ( true === $catalog->check_permissions( array() ) ) {
	cmsa_v2_awp_fail( 'Anonymous access to the universal catalog was not blocked.' );
}

echo "cmsa-v2-awp-boundary: PASS awpcp_post_type=present provider_contract=private universal_facade=excluded admin_boundary=verified candidate_awp_adapter=absent\n";
exit( 0 );
