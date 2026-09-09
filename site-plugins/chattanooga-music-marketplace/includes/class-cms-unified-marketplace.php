<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMS_Unified_Marketplace {
	const MARKETPLACE_PAGE_ID = 12;
	const PRODUCT_LIMIT       = 100;

	private static $instance;

	private $products = null;
	private $cursor = 0;
	private $assignments = array();
	private $integration_ready = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_filter( 'awpcp-content-before-listings-pagination', array( $this, 'prepare_interleaving' ), 20, 4 );
		add_filter( 'awpcp-render-listing-item', array( $this, 'interleave_product' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	public function enqueue_styles() {
		if ( ! $this->is_marketplace_request() ) {
			return;
		}

		wp_enqueue_style(
			'cms-unified-marketplace',
			CMS_MARKETPLACE_URL . 'assets/marketplace.css',
			array(),
			CMS_MARKETPLACE_VERSION
		);
	}

	public function prepare_interleaving( $before_pagination, $context, $listings, $query_vars ) {
		$this->cursor      = 0;
		$this->assignments = array();

		if ( ! $this->is_marketplace_request() || ! $this->is_supported_listing_context( $context, $query_vars ) ) {
			return $before_pagination;
		}

		$listing_count = is_countable( $listings ) ? count( $listings ) : 0;
		$product_count = count( $this->get_products() );

		if ( $product_count < 1 ) {
			return $before_pagination;
		}

		if ( $listing_count < 1 ) {
			if ( ! is_array( $before_pagination ) ) {
				return $before_pagination;
			}

			if ( ! isset( $before_pagination[15] ) || ! is_array( $before_pagination[15] ) ) {
				$before_pagination[15] = array();
			}

			$before_pagination[15]['cms-marketplace-products'] = $this->render_remaining_products();
			return $before_pagination;
		}

		$base      = intdiv( $product_count, $listing_count );
		$remainder = $product_count % $listing_count;

		for ( $position = 1; $position <= $listing_count; $position++ ) {
			$this->assignments[ $position ] = $base + ( $position <= $remainder ? 1 : 0 );
		}

		return $before_pagination;
	}

	public function interleave_product( $rendered_listing, $listing, $position ) {
		if ( ! $this->is_marketplace_request() || empty( $this->assignments[ $position ] ) ) {
			return $rendered_listing;
		}

		$products = $this->products_for_position( $position );
		if ( empty( $products ) ) {
			return $rendered_listing;
		}

		foreach ( $products as $product ) {
			$rendered_listing .= $this->render_product_card( $product );
		}

		return $rendered_listing;
	}

	private function is_marketplace_request() {
		return ! is_admin() && is_page( self::MARKETPLACE_PAGE_ID ) && $this->integration_ready();
	}

	private function integration_ready() {
		if ( null !== $this->integration_ready ) {
			return $this->integration_ready;
		}

		$content = get_post_field( 'post_content', self::MARKETPLACE_PAGE_ID, 'raw' );
		if ( ! is_string( $content ) ) {
			$this->integration_ready = false;
			return $this->integration_ready;
		}

		$this->integration_ready = has_shortcode( $content, 'AWPCPCLASSIFIEDSUI' ) && ! has_shortcode( $content, 'products' );
		return $this->integration_ready;
	}

	private function is_supported_listing_context( $context, $query_vars ) {
		if ( ! is_array( $query_vars ) || ! $this->is_first_results_page( $query_vars ) ) {
			return false;
		}

		if ( 'main-page' === $context ) {
			return true;
		}

		if ( 'browse-listings' !== $context ) {
			return false;
		}

		$classifieds_query = isset( $query_vars['classifieds_query'] ) && is_array( $query_vars['classifieds_query'] )
			? $query_vars['classifieds_query']
			: array();

		return ! $this->has_active_listing_filter( $query_vars, $classifieds_query );
	}

	private function has_active_listing_filter( $query_vars, $classifieds_query ) {
		if ( ! empty( $query_vars['s'] ) ) {
			return true;
		}

		$filter_keys = array(
			'category',
			'contact_name',
			'min_price',
			'max_price',
			'region',
			'regions',
			'title',
		);

		foreach ( $filter_keys as $key ) {
			if ( ! isset( $classifieds_query[ $key ] ) ) {
				continue;
			}

			$value = $classifieds_query[ $key ];
			if ( is_array( $value ) ) {
				if ( ! empty( array_filter( $value ) ) ) {
					return true;
				}
				continue;
			}

			if ( '' !== trim( (string) $value ) && 0 !== (int) $value ) {
				return true;
			}
		}

		return false;
	}

	private function is_first_results_page( $query_vars ) {
		$offset = isset( $query_vars['offset'] ) ? absint( $query_vars['offset'] ) : 0;
		$paged  = isset( $query_vars['paged'] ) ? absint( $query_vars['paged'] ) : 1;

		return 0 === $offset && $paged <= 1;
	}

	private function products_for_position( $position ) {
		$count = isset( $this->assignments[ $position ] ) ? absint( $this->assignments[ $position ] ) : 0;
		if ( $count < 1 ) {
			return array();
		}

		$products = $this->get_products();
		$assigned = array();

		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! isset( $products[ $this->cursor ] ) ) {
				break;
			}

			$assigned[] = $products[ $this->cursor ];
			$this->cursor++;
		}

		return $assigned;
	}

	private function render_remaining_products() {
		$rendered = '';
		$products = $this->get_products();

		while ( isset( $products[ $this->cursor ] ) ) {
			$rendered .= $this->render_product_card( $products[ $this->cursor ] );
			$this->cursor++;
		}

		return $rendered;
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
						<span class="cms-marketplace-item__price"><?php esc_html_e( 'Price:', 'chattanooga-music-marketplace' ); ?> <?php echo wp_kses_post( $price_html ); ?></span>
					<?php endif; ?>
					<?php if ( ! $product->is_in_stock() ) : ?>
						<span class="cms-marketplace-item__stock"><?php esc_html_e( 'Out of stock', 'chattanooga-music-marketplace' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php

		return (string) ob_get_clean();
	}
}
