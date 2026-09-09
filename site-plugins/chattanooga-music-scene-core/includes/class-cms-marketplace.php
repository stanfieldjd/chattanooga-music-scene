<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMS_Marketplace {
	const MARKETPLACE_PAGE_ID = 12;
	const PRODUCT_LIMIT       = 100;

	private static $instance;

	private $products = null;
	private $cursor = 0;
	private $remainder_rendered = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_filter( 'awpcp-render-listing-item', array( $this, 'interleave_product' ), 20, 3 );
		add_filter( 'awpcp-content-after-listings-page', array( $this, 'append_remaining_products' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	public function enqueue_styles() {
		if ( ! $this->is_marketplace_request() ) {
			return;
		}

		wp_enqueue_style(
			'cms-marketplace',
			CMS_CORE_URL . 'assets/marketplace.css',
			array(),
			CMS_CORE_VERSION
		);
	}

	public function interleave_product( $rendered_listing, $listing, $position ) {
		if ( ! $this->is_marketplace_request() ) {
			return $rendered_listing;
		}

		$product = $this->next_product();
		if ( ! $product ) {
			return $rendered_listing;
		}

		return $rendered_listing . $this->render_product_card( $product );
	}

	public function append_remaining_products( $content, $context ) {
		if ( ! $this->is_marketplace_request() || $this->remainder_rendered ) {
			return $content;
		}

		$this->remainder_rendered = true;

		$remaining = '';
		while ( $product = $this->next_product() ) {
			$remaining .= $this->render_product_card( $product );
		}

		return $content . $remaining;
	}

	private function is_marketplace_request() {
		return ! is_admin() && is_page( self::MARKETPLACE_PAGE_ID );
	}

	private function next_product() {
		$products = $this->get_products();
		if ( empty( $products ) || ! isset( $products[ $this->cursor ] ) ) {
			return null;
		}

		$product = $products[ $this->cursor ];
		$this->cursor++;

		return $product;
	}

	private function get_products() {
		if ( null !== $this->products ) {
			return $this->products;
		}

		$this->products = array();

		if ( ! function_exists( 'wc_get_products' ) ) {
			return $this->products;
		}

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => self::PRODUCT_LIMIT,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);

		foreach ( $products as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if ( ! $product->is_visible() ) {
				continue;
			}

			$this->products[] = $product;
		}

		return $this->products;
	}

	private function render_product_card( WC_Product $product ) {
		$url         = get_permalink( $product->get_id() );
		$title       = $product->get_name();
		$description = $product->get_short_description();

		if ( '' === trim( wp_strip_all_tags( $description ) ) ) {
			$description = $product->get_description();
		}

		$description = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $description ) ), 28, '&hellip;' );
		$price_html  = $product->get_price_html();
		$image_html  = $product->get_image(
			'medium',
			array(
				'loading' => 'lazy',
				'alt'     => $title,
			),
			false
		);
		$date = $product->get_date_created();

		ob_start();
		?>
		<article class="awpcp-listing-excerpt cms-marketplace-item cms-marketplace-product">
			<div class="cms-marketplace-item__image">
				<a href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
					<?php echo wp_kses_post( $image_html ); ?>
				</a>
			</div>
			<div class="cms-marketplace-item__content">
				<h4 class="cms-marketplace-item__title">
					<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
				</h4>
				<?php if ( $description ) : ?>
					<p class="cms-marketplace-item__excerpt"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
				<div class="cms-marketplace-item__meta">
					<?php if ( $date ) : ?>
						<span class="cms-marketplace-item__date"><?php echo esc_html( wp_date( get_option( 'date_format' ), $date->getTimestamp(), wp_timezone() ) ); ?></span>
					<?php endif; ?>
					<?php if ( $price_html ) : ?>
						<span class="cms-marketplace-item__price"><?php esc_html_e( 'Price:', 'chattanooga-music-scene-core' ); ?> <?php echo wp_kses_post( $price_html ); ?></span>
					<?php endif; ?>
					<?php if ( ! $product->is_in_stock() ) : ?>
						<span class="cms-marketplace-item__stock"><?php esc_html_e( 'Out of stock', 'chattanooga-music-scene-core' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php

		return (string) ob_get_clean();
	}
}
