<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "weekend-feature-permission-cli: WordPress is not loaded.\n" );
	exit( 1 );
}

if ( 0 === did_action( 'wp_abilities_api_categories_init' ) ) {
	do_action( 'wp_abilities_api_categories_init' );
}
if ( 0 === did_action( 'wp_abilities_api_init' ) ) {
	do_action( 'wp_abilities_api_init' );
}

$fixture = dirname( __DIR__ ) . '/fixtures/expected-weekend-feature-abilities.json';
$expected = json_decode( (string) file_get_contents( $fixture ), true );
if ( ! is_array( $expected ) || 4 !== count( $expected ) ) {
	fwrite( STDERR, "weekend-feature-permission-cli: expected ability fixture is unreadable.\n" );
	exit( 1 );
}

$abilities = array();
foreach ( $expected as $name ) {
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
	if ( ! $ability || ! method_exists( $ability, 'check_permissions' ) ) {
		fwrite( STDERR, "weekend-feature-permission-cli: missing registered ability {$name}.\n" );
		exit( 1 );
	}
	$abilities[ $name ] = $ability;
}

$allowed = static function ( $ability ) {
	return true === $ability->check_permissions();
};

wp_set_current_user( 0 );
foreach ( $abilities as $name => $ability ) {
	if ( $allowed( $ability ) ) {
		fwrite( STDERR, "weekend-feature-permission-cli: anonymous access leaked for {$name}.\n" );
		exit( 1 );
	}
}

$subscriber_login = 'cmsa_weekend_sub_' . strtolower( wp_generate_password( 8, false, false ) );
$subscriber_id = wp_create_user( $subscriber_login, wp_generate_password( 20, true, true ), $subscriber_login . '@example.invalid' );
if ( is_wp_error( $subscriber_id ) ) {
	fwrite( STDERR, "weekend-feature-permission-cli: subscriber fixture failed.\n" );
	exit( 1 );
}
$subscriber = new WP_User( $subscriber_id );
$subscriber->set_role( 'subscriber' );
wp_set_current_user( $subscriber_id );
foreach ( $abilities as $name => $ability ) {
	if ( $allowed( $ability ) ) {
		fwrite( STDERR, "weekend-feature-permission-cli: subscriber unexpectedly allowed {$name}.\n" );
		exit( 1 );
	}
}

$editor_login = 'cmsa_weekend_editor_' . strtolower( wp_generate_password( 8, false, false ) );
$editor_id = wp_create_user( $editor_login, wp_generate_password( 20, true, true ), $editor_login . '@example.invalid' );
if ( is_wp_error( $editor_id ) ) {
	fwrite( STDERR, "weekend-feature-permission-cli: editor fixture failed.\n" );
	exit( 1 );
}
$editor = new WP_User( $editor_id );
$editor->set_role( 'editor' );
wp_set_current_user( $editor_id );
foreach ( array(
	'chattanooga-cms-admin/get-weekend-feature-status',
	'chattanooga-cms-admin/generate-weekend-feature-draft',
	'chattanooga-cms-admin/publish-weekend-feature-now',
) as $name ) {
	if ( ! $allowed( $abilities[ $name ] ) ) {
		fwrite( STDERR, "weekend-feature-permission-cli: editor denied {$name}.\n" );
		exit( 1 );
	}
}
if ( $allowed( $abilities['chattanooga-cms-admin/update-weekend-feature-settings'] ) ) {
	fwrite( STDERR, "weekend-feature-permission-cli: editor unexpectedly allowed settings mutation.\n" );
	exit( 1 );
}

$subscriber->add_cap( 'manage_options', true );
clean_user_cache( $subscriber_id );
wp_set_current_user( 0 );
wp_set_current_user( $subscriber_id );
if ( $allowed( $abilities['chattanooga-cms-admin/update-weekend-feature-settings'] ) ) {
	fwrite( STDERR, "weekend-feature-permission-cli: manage_options without publish_posts opened settings mutation.\n" );
	exit( 1 );
}

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	fwrite( STDERR, "weekend-feature-permission-cli: administrator fixture missing.\n" );
	exit( 1 );
}
wp_set_current_user( $admin->ID );
foreach ( $abilities as $name => $ability ) {
	if ( ! $allowed( $ability ) ) {
		fwrite( STDERR, "weekend-feature-permission-cli: administrator denied {$name}.\n" );
		exit( 1 );
	}
}

$dependency_probe = ( new CMSA_Weekend_Feature() )->get_status();
if ( ! is_wp_error( $dependency_probe ) || 'cmsa_weekend_dependency' !== $dependency_probe->get_error_code() ) {
	fwrite( STDERR, "weekend-feature-permission-cli: missing dependency did not fail closed.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $editor_id );
wp_delete_user( $subscriber_id );

echo "weekend-feature-permission-cli: PASS abilities=4 dependency-absent=fail-closed permissions=verified\n";
