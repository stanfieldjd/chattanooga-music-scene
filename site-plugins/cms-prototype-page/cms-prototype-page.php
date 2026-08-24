<?php
/**
 * Plugin Name: Chattanooga Music Scene Prototype Page
 * Description: Adds the functional Chattanooga Music Scene artwork as a dedicated Prototype page.
 * Version: 1.0.0
 * Author: Chattanooga Music Scene
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMS_Prototype_Page {
	private const VERSION = '1.0.0';
	private const PAGE_SLUG = 'prototype';
	private const PAGE_OPTION = 'cms_prototype_page_id';
	private const SHORTCODE = 'cms_prototype_artwork';

	public static function init(): void {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_artwork' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'template_include', array( __CLASS__, 'use_prototype_template' ), 99 );
	}

	public static function activate(): void {
		$page = get_posts(
			array(
				'name'           => self::PAGE_SLUG,
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
				'posts_per_page' => 1,
			)
		);

		$page_id = $page ? (int) $page[0]->ID : 0;
		$content = '<!-- cms-prototype-page -->[' . self::SHORTCODE . ']';

		if ( $page_id ) {
			if ( 'trash' === get_post_status( $page_id ) ) {
				wp_untrash_post( $page_id );
			}

			wp_update_post(
				array(
					'ID'           => $page_id,
					'post_title'   => 'Prototype',
					'post_name'    => self::PAGE_SLUG,
					'post_content' => $content,
					'post_status'  => 'draft',
				)
			);
		} else {
			$page_id = wp_insert_post(
				array(
					'post_title'   => 'Prototype',
					'post_name'    => self::PAGE_SLUG,
					'post_content' => $content,
					'post_status'  => 'draft',
					'post_type'    => 'page',
				),
				true
			);
		}

		if ( ! is_wp_error( $page_id ) && $page_id ) {
			update_option( self::PAGE_OPTION, (int) $page_id, false );
		}
	}

	public static function enqueue_assets(): void {
		if ( ! self::is_prototype_page() ) {
			return;
		}

		$base = plugin_dir_url( __FILE__ ) . 'assets/';
		wp_enqueue_style( 'cms-prototype-page', $base . 'prototype.css', array(), self::VERSION );
		wp_enqueue_script( 'cms-prototype-page', $base . 'prototype.js', array(), self::VERSION, true );
	}

	public static function use_prototype_template( string $template ): string {
		if ( self::is_prototype_page() ) {
			return plugin_dir_path( __FILE__ ) . 'templates/prototype.php';
		}

		return $template;
	}

	private static function is_prototype_page(): bool {
		$page_id = (int) get_option( self::PAGE_OPTION, 0 );
		return is_page( self::PAGE_SLUG ) || ( $page_id && is_page( $page_id ) );
	}

	public static function render_artwork(): string {
		$assets = esc_url( plugin_dir_url( __FILE__ ) . 'assets/' );
		$items  = array( 'tonight', 'upcoming', 'venues', 'community', 'marketplace' );

		ob_start();
		?>
		<main class="cms-prototype-artwork" data-cms-prototype>
			<a class="cms-prototype-skip" href="#cms-prototype-navigation">Skip to scene navigation</a>
			<div class="cms-prototype-canvas">
				<img class="cms-prototype-collage" src="<?php echo $assets; ?>chattanooga-current-theme-blank.png" alt="" aria-hidden="true" fetchpriority="high">
				<nav id="cms-prototype-navigation" class="cms-prototype-navigation" aria-label="Explore Chattanooga Music Scene">
					<?php foreach ( $items as $item ) : ?>
						<a class="cms-prototype-letter cms-prototype-<?php echo esc_attr( $item ); ?>" href="#<?php echo esc_attr( $item ); ?>" aria-label="<?php echo esc_attr( ucfirst( $item ) ); ?>" data-cms-prototype-letter="<?php echo esc_attr( $item ); ?>">
							<img src="<?php echo $assets . 'lettering/' . esc_attr( $item ) . '.png'; ?>" alt="" aria-hidden="true" draggable="false">
						</a>
					<?php endforeach; ?>
				</nav>
			</div>
			<header class="cms-prototype-visually-hidden"><h1>Chattanooga Music Scene</h1></header>
			<p class="cms-prototype-visually-hidden" aria-live="polite" data-cms-prototype-status>Tonight selected</p>
		</main>
		<?php
		return (string) ob_get_clean();
	}
}

register_activation_hook( __FILE__, array( 'CMS_Prototype_Page', 'activate' ) );
CMS_Prototype_Page::init();

