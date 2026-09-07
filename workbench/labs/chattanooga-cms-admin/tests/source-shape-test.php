<?php

$lab = dirname( __DIR__ );
$plugin = file_get_contents( $lab . '/candidate/chattanooga-cms-admin.php' );
$coordinator = file_get_contents( $lab . '/candidate/includes/class-cmsa-plugin.php' );
$abilities = file_get_contents( $lab . '/candidate/includes/class-cmsa-abilities.php' );
$content_abilities = file_get_contents( $lab . '/candidate/includes/class-cmsa-content-abilities.php' );
$status_abilities = file_get_contents( $lab . '/candidate/includes/class-cmsa-content-status-abilities.php' );

$required_header_fragments = array(
	'Plugin Name: Chattanooga CMS Admin',
	'Version: 0.1.0',
	'Requires at least: 6.9',
	'Requires PHP: 7.4',
);
foreach ( $required_header_fragments as $fragment ) {
	if ( false === strpos( $plugin, $fragment ) ) {
		fwrite( STDERR, "Missing plugin header requirement: {$fragment}\n" );
		exit( 1 );
	}
}

foreach ( array( 'class-cmsa-errors.php', 'class-cmsa-audit.php', 'class-cmsa-backups.php', 'class-cmsa-health.php', 'class-cmsa-updates.php', 'class-cmsa-lifecycle.php', 'class-cmsa-content.php', 'class-cmsa-content-status.php', 'class-cmsa-abilities.php', 'class-cmsa-content-abilities.php', 'class-cmsa-content-status-abilities.php', 'class-cmsa-plugin.php' ) as $required_include ) {
	if ( false === strpos( $plugin, $required_include ) ) {
		fwrite( STDERR, "Plugin bootstrap does not load {$required_include}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'wp_abilities_api_categories_init', 'wp_abilities_api_init' ) as $hook ) {
	if ( false === strpos( $coordinator, $hook ) ) {
		fwrite( STDERR, "Abilities coordinator lacks expected hook {$hook}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $coordinator, "function_exists( 'wp_register_ability' )" ) ) {
	fwrite( STDERR, "Abilities API availability guard is missing.\n" );
	exit( 1 );
}
foreach ( array( 'new CMSA_Content()', 'new CMSA_Content_Abilities', 'new CMSA_Content_Status', 'new CMSA_Content_Status_Abilities' ) as $wiring ) {
	if ( false === strpos( $coordinator, $wiring ) ) {
		fwrite( STDERR, "Content coordinator wiring missing: {$wiring}.\n" );
		exit( 1 );
	}
}

foreach ( array( $abilities, $content_abilities, $status_abilities ) as $registration_source ) {
	if ( false === strpos( $registration_source, "'permission_callback'" ) || false === strpos( $registration_source, 'current_user_can' ) ) {
		fwrite( STDERR, "Central capability gate is missing from an ability registrar.\n" );
		exit( 1 );
	}
	if ( false === strpos( $registration_source, "'show_in_rest' => false" ) ) {
		fwrite( STDERR, "An ability registrar is not explicitly marked show_in_rest=false.\n" );
		exit( 1 );
	}
	if ( false === strpos( $registration_source, "'mcp'          => array( 'public' => true )" ) ) {
		fwrite( STDERR, "MCP discovery metadata is missing from an ability registrar.\n" );
		exit( 1 );
	}
}

echo "source-shape-test: PASS\n";
