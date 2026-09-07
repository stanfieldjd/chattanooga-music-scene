<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMSA_Plugin {
	private static $instance;

	private $abilities;

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

		$this->abilities->register();
	}
}
