<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress must be loaded.\n" );
	exit( 1 );
}

$state_path = getenv( 'CMSA_V2_CORE_UPDATE_STATE' );
if ( ! is_string( $state_path ) || ! is_file( $state_path ) ) {
	fwrite( STDERR, "Synthetic core transition state file is missing.\n" );
	exit( 1 );
}
$state = json_decode( (string) file_get_contents( $state_path ), true );
if ( ! is_array( $state ) || empty( $state['baseline'] ) || empty( $state['post_update_health'] ) || empty( $state['restore_checkpoint_id'] ) ) {
	fwrite( STDERR, "Synthetic core transition state was not completed by the rollback phase.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );
if ( wp_get_wp_version() !== $state['baseline'] ) {
	fwrite( STDERR, "Fresh final WordPress process did not load the restored baseline core version.\n" );
	exit( 1 );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_plugin_active( 'chattanooga-cms-admin/chattanooga-cms-admin.php' ) ) {
	fwrite( STDERR, "Replacement Chattanooga CMS Admin was not active after final core rollback.\n" );
	exit( 1 );
}

$health = wp_get_ability( 'chattanooga-cms-admin/get-health' );
$catalog = wp_get_ability( 'chattanooga-cms-admin/catalog' );
$verify = wp_get_ability( 'chattanooga-cms-admin/verify-backup' );
if ( ! $health instanceof WP_Ability || ! $catalog instanceof WP_Ability || ! $verify instanceof WP_Ability ) {
	fwrite( STDERR, "Replacement ability registry was incomplete after final core rollback.\n" );
	exit( 1 );
}
if ( is_wp_error( $health->execute( array() ) ) || is_wp_error( $catalog->execute( array() ) ) ) {
	fwrite( STDERR, "Replacement abilities failed in the final restored process.\n" );
	exit( 1 );
}
$checkpoint = $verify->execute( array( 'id' => $state['restore_checkpoint_id'] ) );
if ( is_wp_error( $checkpoint ) || empty( $checkpoint['valid'] ) ) {
	fwrite( STDERR, "The automatic pre-restore core checkpoint did not remain verifiable.\n" );
	exit( 1 );
}
if ( get_option( $state['marker_key'] ) !== 'pre-update-database' ) {
	fwrite( STDERR, "Final restored process did not retain the original database marker.\n" );
	exit( 1 );
}
delete_option( $state['marker_key'] );

echo "cmsa-v2-core-update-final: PASS baseline_boot=verified cms_admin_active=verified ability_registry=verified health=verified catalog=verified restore_checkpoint=verified database_state=verified final_state=restored\n";
exit( 0 );
