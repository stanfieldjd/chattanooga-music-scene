<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Abilities {
	private $health;
	private $backups;
	private $updates;

	public function __construct( CMSA_Health $health, CMSA_Backups $backups, CMSA_Updates $updates ) {
		$this->health = $health;
		$this->backups = $backups;
		$this->updates = $updates;
	}

	public function register() {
		$this->register_ability( 'get-health', 'Get CMS health', 'Returns local WordPress, database, filesystem, backup, cache, and maintenance health information.', null, array( $this->health, 'get_health' ), 'manage_options', true, false, true );
		$this->register_ability( 'list-updates', 'List updates', 'Refreshes and returns WordPress core, plugin, and theme update state.', null, array( $this->updates, 'list_updates' ), 'manage_options', true, false, false );
		$this->register_ability( 'list-plugins', 'List plugins', 'Returns installed plugins, activation state, versions, auto-update state, and offered updates.', null, array( $this->updates, 'list_plugins' ), 'manage_options', true, false, false );
		$this->register_ability( 'list-themes', 'List themes', 'Returns installed themes, active state, versions, auto-update state, and offered updates.', null, array( $this->updates, 'list_themes' ), 'manage_options', true, false, false );
		$this->register_ability( 'list-backups', 'List local backups', 'Lists locally stored Chattanooga CMS Admin rollback snapshots and their metadata.', null, array( $this->backups, 'list_backups' ), 'manage_options', true, false, true );

		$this->register_ability(
			'create-backup',
			'Create local backup',
			'Creates a local database, wp-content, or combined backup without transmitting backup data to an external service.',
			$this->object_schema(
				array(
					'scope' => array( 'type' => 'string', 'enum' => array( 'database', 'wp-content', 'full' ), 'default' => 'database' ),
					'label' => array( 'type' => 'string', 'default' => '' ),
				)
			),
			function ( $input ) {
				return $this->backups->create_backup( isset( $input['scope'] ) ? $input['scope'] : 'database', isset( $input['label'] ) ? $input['label'] : '' );
			},
			'manage_options', false, false, false
		);

		$this->register_ability(
			'verify-backup',
			'Verify local backup',
			'Recomputes SHA-256 hashes for a local backup and reports whether every recorded artifact is intact.',
			$this->object_schema( array( 'id' => array( 'type' => 'string', 'minLength' => 1 ) ), array( 'id' ) ),
			function ( $input ) { return $this->backups->verify_backup( $input['id'] ); },
			'manage_options', true, false, true
		);

		$this->register_ability(
			'update-plugin',
			'Update plugin transactionally',
			'Updates one installed plugin only after creating and verifying a local component rollback archive. Automatically attempts rollback if update validation fails.',
			$this->object_schema(
				array(
					'plugin'           => array( 'type' => 'string', 'minLength' => 1 ),
					'expected_version' => array( 'type' => 'string', 'default' => '' ),
				),
				array( 'plugin' )
			),
			function ( $input ) { return $this->updates->update_plugin( $input['plugin'], isset( $input['expected_version'] ) ? $input['expected_version'] : '' ); },
			'update_plugins', false, true, false
		);

		$this->register_ability(
			'update-theme',
			'Update theme transactionally',
			'Updates one installed theme only after creating and verifying a local rollback archive. Automatically attempts rollback if update validation fails.',
			$this->object_schema(
				array(
					'stylesheet'       => array( 'type' => 'string', 'minLength' => 1 ),
					'expected_version' => array( 'type' => 'string', 'default' => '' ),
				),
				array( 'stylesheet' )
			),
			function ( $input ) { return $this->updates->update_theme( $input['stylesheet'], isset( $input['expected_version'] ) ? $input['expected_version'] : '' ); },
			'update_themes', false, true, false
		);

		$this->register_ability(
			'update-core',
			'Update WordPress core transactionally',
			'Updates WordPress core after creating and verifying a local database and core-file rollback snapshot. Attempts full core rollback when validation fails.',
			$this->object_schema( array( 'version' => array( 'type' => 'string', 'default' => '' ) ) ),
			function ( $input ) { return $this->updates->update_core( isset( $input['version'] ) ? $input['version'] : '' ); },
			'update_core', false, true, false
		);

		$this->register_ability(
			'install-plugin',
			'Install WordPress.org plugin',
			'Installs a plugin package obtained from the official WordPress.org plugin API by slug. It does not activate the plugin.',
			$this->object_schema( array( 'slug' => array( 'type' => 'string', 'pattern' => '^[a-z0-9-]+$' ) ), array( 'slug' ) ),
			function ( $input ) { return $this->updates->install_plugin( $input['slug'] ); },
			'install_plugins', false, false, false
		);

		$this->register_ability(
			'activate-plugin',
			'Activate plugin',
			'Activates an installed plugin and verifies that activation persisted.',
			$this->object_schema(
				array(
					'plugin'       => array( 'type' => 'string', 'minLength' => 1 ),
					'network_wide' => array( 'type' => 'boolean', 'default' => false ),
				),
				array( 'plugin' )
			),
			function ( $input ) { return $this->updates->activate_plugin( $input['plugin'], ! empty( $input['network_wide'] ) ); },
			'activate_plugins', false, false, false
		);

		$this->register_ability(
			'deactivate-plugin',
			'Deactivate plugin',
			'Deactivates an installed plugin and verifies that deactivation persisted.',
			$this->object_schema(
				array(
					'plugin'       => array( 'type' => 'string', 'minLength' => 1 ),
					'network_wide' => array( 'type' => 'boolean', 'default' => false ),
				),
				array( 'plugin' )
			),
			function ( $input ) { return $this->updates->deactivate_plugin( $input['plugin'], ! empty( $input['network_wide'] ) ); },
			'activate_plugins', false, true, false
		);

		$this->register_ability(
			'install-theme',
			'Install WordPress.org theme',
			'Installs a theme package obtained from the official WordPress.org theme API by slug. It does not switch the active theme.',
			$this->object_schema( array( 'slug' => array( 'type' => 'string', 'pattern' => '^[a-z0-9-]+$' ) ), array( 'slug' ) ),
			function ( $input ) { return $this->updates->install_theme( $input['slug'] ); },
			'install_themes', false, false, false
		);

		$this->register_ability(
			'switch-theme',
			'Switch active theme',
			'Switches the active WordPress theme to an already installed stylesheet and verifies the resulting active theme.',
			$this->object_schema( array( 'stylesheet' => array( 'type' => 'string', 'minLength' => 1 ) ), array( 'stylesheet' ) ),
			function ( $input ) { return $this->updates->switch_theme( $input['stylesheet'] ); },
			'switch_themes', false, true, false
		);

		$this->register_ability( 'clear-cache', 'Clear site cache', 'Clears WP Super Cache when available, the WordPress object cache, and WordPress blog object state.', null, array( $this->health, 'clear_cache' ), 'manage_options', false, false, false );

		$this->register_ability(
			'restore-component-backup',
			'Restore plugin or theme rollback',
			'Restores an exact locally stored plugin or theme component rollback archive after verifying its recorded SHA-256 hash.',
			$this->object_schema( array( 'id' => array( 'type' => 'string', 'minLength' => 1 ) ), array( 'id' ) ),
			function ( $input ) { return $this->backups->restore_component_backup( $input['id'] ); },
			'manage_options', false, true, false
		);

		$this->register_ability(
			'restore-database-backup',
			'Restore database backup',
			'Replaces the current WordPress database tables with a verified local database snapshot. This is destructive and intended only for an explicitly authorized rollback.',
			$this->object_schema( array( 'id' => array( 'type' => 'string', 'minLength' => 1 ) ), array( 'id' ) ),
			function ( $input ) { return $this->backups->restore_database_backup( $input['id'] ); },
			'manage_options', false, true, false
		);

		$this->register_ability(
			'restore-core-backup',
			'Restore WordPress core rollback',
			'Restores verified WordPress core files and the associated database snapshot from a local core rollback backup. This is destructive and intended only for an explicitly authorized rollback.',
			$this->object_schema( array( 'id' => array( 'type' => 'string', 'minLength' => 1 ) ), array( 'id' ) ),
			function ( $input ) { return $this->backups->restore_core_backup( $input['id'] ); },
			'update_core', false, true, false
		);
	}

	private function register_ability( $slug, $label, $description, $input_schema, $callback, $capability, $readonly, $destructive, $idempotent ) {
		$args = array(
			'label'               => __( $label, 'chattanooga-cms-admin' ),
			'description'         => __( $description, 'chattanooga-cms-admin' ),
			'category'            => 'chattanooga-cms-admin',
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => $callback,
			'permission_callback' => function () use ( $capability ) {
				return current_user_can( $capability );
			},
			'meta'                => array(
				'public'       => true,
				'show_in_rest' => false,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => (bool) $readonly,
					'destructive' => (bool) $destructive,
					'idempotent'  => (bool) $idempotent,
				),
			),
		);
		if ( null !== $input_schema ) {
			$args['input_schema'] = $input_schema;
		}

		wp_register_ability( 'chattanooga-cms-admin/' . $slug, $args );
	}

	private function object_schema( array $properties, array $required = array() ) {
		$schema = array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
		if ( $required ) {
			$schema['required'] = $required;
		}
		return $schema;
	}
}
