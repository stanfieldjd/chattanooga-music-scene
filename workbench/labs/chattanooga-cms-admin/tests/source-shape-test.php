<?php

$lab = dirname( __DIR__ );
$plugin = file_get_contents( $lab . '/candidate/chattanooga-cms-admin.php' );
$coordinator = file_get_contents( $lab . '/candidate/includes/class-cmsa-plugin.php' );
$registrars = array(
	file_get_contents( $lab . '/candidate/includes/class-cmsa-abilities.php' ),
	file_get_contents( $lab . '/candidate/includes/class-cmsa-content-abilities.php' ),
	file_get_contents( $lab . '/candidate/includes/class-cmsa-content-deletion-abilities.php' ),
	file_get_contents( $lab . '/candidate/includes/class-cmsa-content-status-abilities.php' ),
	file_get_contents( $lab . '/candidate/includes/class-cmsa-content-taxonomy-abilities.php' ),
	file_get_contents( $lab . '/candidate/includes/class-cmsa-member-abilities.php' ),
	file_get_contents( $lab . '/candidate/includes/class-cmsa-member-mutation-abilities.php' ),
	file_get_contents( $lab . '/candidate/includes/class-cmsa-events-manager-abilities.php' ),
	file_get_contents( $lab . '/candidate/includes/class-cmsa-events-manager-mutation-abilities.php' ),
);

foreach ( array( 'Plugin Name: Chattanooga CMS Admin', 'Version: 0.1.0', 'Requires at least: 6.9', 'Requires PHP: 7.4' ) as $fragment ) {
	if ( false === strpos( $plugin, $fragment ) ) {
		fwrite( STDERR, "Missing plugin header requirement: {$fragment}\n" );
		exit( 1 );
	}
}

foreach ( array( 'class-cmsa-errors.php', 'class-cmsa-audit.php', 'class-cmsa-backups.php', 'class-cmsa-health.php', 'class-cmsa-updates.php', 'class-cmsa-lifecycle.php', 'class-cmsa-content.php', 'class-cmsa-content-deletion.php', 'class-cmsa-content-status.php', 'class-cmsa-content-taxonomy.php', 'class-cmsa-members.php', 'class-cmsa-member-mutations.php', 'class-cmsa-events-manager.php', 'class-cmsa-events-manager-mutations.php', 'class-cmsa-abilities.php', 'class-cmsa-content-abilities.php', 'class-cmsa-content-deletion-abilities.php', 'class-cmsa-content-status-abilities.php', 'class-cmsa-content-taxonomy-abilities.php', 'class-cmsa-member-abilities.php', 'class-cmsa-member-mutation-abilities.php', 'class-cmsa-events-manager-abilities.php', 'class-cmsa-events-manager-mutation-abilities.php', 'class-cmsa-plugin.php' ) as $required_include ) {
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
foreach ( array( 'new CMSA_Content()', 'new CMSA_Content_Abilities', 'new CMSA_Content_Deletion', 'new CMSA_Content_Deletion_Abilities', 'new CMSA_Content_Status', 'new CMSA_Content_Status_Abilities', 'new CMSA_Content_Taxonomy', 'new CMSA_Content_Taxonomy_Abilities', 'new CMSA_Members()', 'new CMSA_Member_Abilities', 'new CMSA_Member_Mutations', 'new CMSA_Member_Mutation_Abilities', 'new CMSA_Events_Manager()', 'new CMSA_Events_Manager_Abilities', 'new CMSA_Events_Manager_Mutations', 'new CMSA_Events_Manager_Mutation_Abilities' ) as $wiring ) {
	if ( false === strpos( $coordinator, $wiring ) ) {
		fwrite( STDERR, "Coordinator wiring missing: {$wiring}.\n" );
		exit( 1 );
	}
}

foreach ( $registrars as $registration_source ) {
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

$deletion_source = file_get_contents( $lab . '/candidate/includes/class-cmsa-content-deletion.php' );
foreach ( array( "'trash' !== $post->post_status", "current_user_can( 'delete_post'", 'confirm_permanent_delete', 'expected_modified_gmt', 'wp_delete_post( $post->ID, true )' ) as $deletion_guard ) {
	if ( false === strpos( $deletion_source, $deletion_guard ) ) {
		fwrite( STDERR, "Permanent content deletion guard missing: {$deletion_guard}.\n" );
		exit( 1 );
	}
}

$candidate_sources = array_merge(
	array( $lab . '/candidate/chattanooga-cms-admin.php' ),
	glob( $lab . '/candidate/includes/*.php' ) ?: array()
);
$multisite_fragments = array( 'is_multisite(', 'is_plugin_active_for_network(', 'clean_blog_cache(', 'network_wide', 'network_active' );
foreach ( $candidate_sources as $source_path ) {
	$source = file_get_contents( $source_path );
	foreach ( $multisite_fragments as $fragment ) {
		if ( false !== strpos( $source, $fragment ) ) {
			fwrite( STDERR, 'Multisite-only behavior is not part of the Chattanooga single-site product: ' . basename( $source_path ) . " contains {$fragment}.\n" );
			exit( 1 );
		}
	}
}

$mutation_source = file_get_contents( $lab . '/candidate/includes/class-cmsa-events-manager-mutations.php' );
foreach ( array( 'EM_Booking', 'EM_Ticket', 'create_booking', 'update_booking', 'delete_booking', 'create_ticket', 'update_ticket', 'delete_ticket' ) as $out_of_scope_fragment ) {
	if ( false !== strpos( $mutation_source, $out_of_scope_fragment ) ) {
		fwrite( STDERR, "Events Manager mutation layer expanded beyond the current event/location gate: {$out_of_scope_fragment}.\n" );
		exit( 1 );
	}
}

echo "source-shape-test: PASS\n";
