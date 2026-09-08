<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Plugin {
	private static $instance;

	private $abilities;
	private $content_abilities;
	private $content_deletion_abilities;
	private $content_status_abilities;
	private $content_taxonomy_abilities;
	private $member_abilities;
	private $member_mutation_abilities;
	private $events_manager_abilities;
	private $events_manager_mutation_abilities;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			'chattanooga-cms-admin',
			array(
				'label'       => __( 'Chattanooga CMS Admin', 'chattanooga-cms-admin' ),
				'description' => __( 'Site-owned administration abilities for Chattanooga Music Scene.', 'chattanooga-cms-admin' ),
			)
		);
	}

	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		if ( ! $this->abilities ) {
			$this->abilities = new CMSA_Abilities( new CMSA_Health(), new CMSA_Backups(), new CMSA_Updates(), new CMSA_Lifecycle() );
		}
		if ( ! $this->content_abilities || ! $this->content_deletion_abilities || ! $this->content_status_abilities || ! $this->content_taxonomy_abilities ) {
			$content = new CMSA_Content();
			$this->content_abilities = new CMSA_Content_Abilities( $content );
			$this->content_deletion_abilities = new CMSA_Content_Deletion_Abilities( new CMSA_Content_Deletion() );
			$this->content_status_abilities = new CMSA_Content_Status_Abilities( new CMSA_Content_Status( $content ) );
			$this->content_taxonomy_abilities = new CMSA_Content_Taxonomy_Abilities( new CMSA_Content_Taxonomy( $content ) );
		}
		if ( ! $this->member_abilities || ! $this->member_mutation_abilities ) {
			$members = new CMSA_Members();
			$this->member_abilities = new CMSA_Member_Abilities( $members );
			$this->member_mutation_abilities = new CMSA_Member_Mutation_Abilities( new CMSA_Member_Mutations( $members ) );
		}
		if ( ! $this->events_manager_abilities || ! $this->events_manager_mutation_abilities ) {
			$events = new CMSA_Events_Manager();
			$this->events_manager_abilities = new CMSA_Events_Manager_Abilities( $events );
			$this->events_manager_mutation_abilities = new CMSA_Events_Manager_Mutation_Abilities( new CMSA_Events_Manager_Mutations( $events ) );
		}

		$this->abilities->register();
		$this->content_abilities->register();
		$this->content_deletion_abilities->register();
		$this->content_status_abilities->register();
		$this->content_taxonomy_abilities->register();
		$this->member_abilities->register();
		$this->member_mutation_abilities->register();
		$this->events_manager_abilities->register();
		$this->events_manager_mutation_abilities->register();
	}
}
