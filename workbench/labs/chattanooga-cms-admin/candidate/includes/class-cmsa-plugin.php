<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Plugin {
	private static $instance;

	private $abilities;
	private $content_abilities;
	private $content_status_abilities;
	private $content_taxonomy_abilities;

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
			$this->abilities = new CMSA_Abilities(
				new CMSA_Health(),
				new CMSA_Backups(),
				new CMSA_Updates(),
				new CMSA_Lifecycle()
			);
		}
		if ( ! $this->content_abilities || ! $this->content_status_abilities || ! $this->content_taxonomy_abilities ) {
			$content = new CMSA_Content();
			$this->content_abilities = new CMSA_Content_Abilities( $content );
			$this->content_status_abilities = new CMSA_Content_Status_Abilities( new CMSA_Content_Status( $content ) );
			$this->content_taxonomy_abilities = new CMSA_Content_Taxonomy_Abilities( new CMSA_Content_Taxonomy( $content ) );
		}

		$this->abilities->register();
		$this->content_abilities->register();
		$this->content_status_abilities->register();
		$this->content_taxonomy_abilities->register();
	}
}
