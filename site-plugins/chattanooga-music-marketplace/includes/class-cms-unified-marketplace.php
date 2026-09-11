<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMS_Unified_Marketplace {
	const MARKETPLACE_PAGE_ID = 12;
	const PRODUCT_LIMIT       = 100;

	private static $instance;

	private $catalog_products = null;
	private $products = array();
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
		$this->products    = array();

		if ( ! $this->is_marketplace_request() || ! $this->is_supported_listing_context( $context, $query_vars ) ) {
			return $before_pagination;
		}

		$this->products = $this->get_products_for_query( $context, $query_vars );

		$listing_count = is_countable( $listings ) ? count( $listings ) : 0;
		$product_count = count( $this->products );

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

		return in_array( $context, array( 'main-page', 'browse-listings', 'search' ), true );
	}

	private function is_first_results_page( $query_vars ) {
		$offset = isset( $query_vars['offset'] ) ? absint( $query_vars['offset'] ) : 0;
		$paged  = isset( $query_vars['paged'] ) ? absint( $query_vars['paged'] ) : 1;

		return 0 === $offset && $paged <= 1;
	}

	private function get_products_for_query( $context, $query_vars ) {
		$products = $this->get_catalog_products();
		if ( empty( $products ) || 'main-page' === $context ) {
			return $products;
		}

		$classifieds_query = isset( $query_vars['classifieds_query'] ) && is_array( $query_vars['classifieds_query'] )
			? $query_vars['classifieds_query']
			: array();

		if ( $this->has_location_filter( $classifieds_query ) || $this->has_meaningful_value( $classifieds_query, 'contact_name' ) ) {
			return array();
		}

		$category_ids = isset( $classifieds_query['category'] )
			? $this->normalize_category_ids( $classifieds_query['category'] )
			: array();
		if ( ! empty( $category_ids ) ) {
			$product_category_ids = $this->resolve_product_category_ids( $category_ids );
			if ( empty( $product_category_ids ) ) {
				return array();
			}

			$products = array_values(
				array_filter(
					$products,
					function ( $product ) use ( $product_category_ids ) {
						return ! empty( array_intersect( $product_category_ids, array_map( 'absint', $product->get_category_ids() ) ) );
					}
				)
			);
		}

		$search = '';
		if ( isset( $query_vars['s'] ) ) {
			$search = trim( (string) $query_vars['s'] );
		} elseif ( isset( $classifieds_query['title'] ) ) {
			$search = trim( (string) $classifieds_query['title'] );
		}

		if ( '' !== $search ) {
			$products = array_values(
				array_filter(
					$products,
					function ( $product ) use ( $search ) {
						return $this->product_matches_search( $product, $search );
					}
				)
			);
		}

		$min_price = $this->read_price_filter( $classifieds_query, 'min_price' );
		$max_price = $this->read_price_filter( $classifieds_query, 'max_price' );
		if ( null !== $min_price || null !== $max_price ) {
			$products = array_values(
				array_filter(
					$products,
					function ( $product ) use ( $min_price, $max_price ) {
						return $this->product_matches_price_range( $product, $min_price, $max_price );
					}
				)
			);
		}

		return $products;
	}

	private function has_location_filter( $classifieds_query ) {
		return $this->has_meaningful_value( $classifieds_query, 'region' ) || $this->has_meaningful_value( $classifieds_query, 'regions' );
	}

	private function has_meaningful_value( $values, $key ) {
		if ( ! is_array( $values ) || ! array_key_exists( $key, $values ) ) {
			return false;
		}

		return $this->value_is_meaningful( $values[ $key ] );
	}

	private function value_is_meaningful( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $nested_value ) {
				if ( $this->value_is_meaningful( $nested_value ) ) {
					return true;
				}
			}
			return false;
		}

		$normalized = trim( (string) $value );
		return '' !== $normalized && '0' !== $normalized;
	}

	private function normalize_category_ids( $categories ) {
		if ( ! is_array( $categories ) ) {
			$categories = array( $categories );
		}

		$category_ids = array();
		foreach ( $categories as $category ) {
			if ( is_array( $category ) ) {
				$category_ids = array_merge( $category_ids, $this->normalize_category_ids( $category ) );
				continue;
			}

			$category_id = absint( $category );
			if ( $category_id > 0 ) {
				$category_ids[] = $category_id;
			}
		}

		return array_values( array_unique( $category_ids ) );
	}

	private function resolve_product_category_ids( $awpcp_category_ids ) {
		if ( ! function_exists( 'get_term' ) || ! function_exists( 'get_term_by' ) ) {
			return array();
		}

		$category_ids = array();
		foreach ( $this->normalize_category_ids( $awpcp_category_ids ) as $awpcp_category_id ) {
			$awpcp_term = get_term( $awpcp_category_id, 'awpcp_listing_category' );
			if ( ! $awpcp_term || is_wp_error( $awpcp_term ) ) {
				continue;
			}

			$product_term = get_term_by( 'slug', $awpcp_term->slug, 'product_cat' );
			if ( ! $product_term ) {
				$product_term = get_term_by( 'name', $awpcp_term->name, 'product_cat' );
			}

			if ( ! $product_term || is_wp_error( $product_term ) ) {
				continue;
			}

			$category_ids[] = absint( $product_term->term_id );
			if ( function_exists( 'get_term_children' ) ) {
				$children = get_term_children( $product_term->term_id, 'product_cat' );
				if ( is_array( $children ) ) {
					$category_ids = array_merge( $category_ids, array_map( 'absint', $children ) );
				}
			}
		}

		return array_values( array_unique( array_filter( $category_ids ) ) );
	}

	private function product_matches_search( WC_Product $product, $search ) {
		$haystack = implode(
			' ',
			array(
				$product->get_name(),
				$product->get_short_description(),
				$product->get_description(),
			)
		);

		$haystack = wp_strip_all_tags( strip_shortcodes( $haystack ) );
		return false !== stripos( $haystack, $search );
	}

	private function read_price_filter( $classifieds_query, $key ) {
		if ( ! isset( $classifieds_query[ $key ] ) ) {
			return null;
		}

		$value = trim( (string) $classifieds_query[ $key ] );
		if ( '' === $value ) {
			return null;
		}

		return max( 0, (float) $value );
	}

	private function product_matches_price_range( WC_Product $product, $min_price, $max_price ) {
		$bounds = $this->get_product_price_bounds( $product );
		if ( null === $bounds ) {
			return false;
		}

		if ( null !== $min_price && $bounds['max'] < $min_price ) {
			return false;
		}

		if ( null !== $max_price && $bounds['min'] > $max_price ) {
			return false;
		}

		return true;
	}

	private function get_product_price_bounds( WC_Product $product ) {
		if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_price' ) ) {
			$min = $product->get_variation_price( 'min', true );
			$max = $product->get_variation_price( 'max', true );
		} else {
			$min = $product->get_price();
			$max = $min;
		}

		if ( '' === $min || null === $min || '' === $max || null === $max ) {
			return null;
		}

		return array(
			'min' => (float) $min,
			'max' => (float) $max,
		);
	}

	private function products_for_position( $position ) {
		$count = isset( $this->assignments[ $position ] ) ? absint( $this->assignments[ $position ] ) : 0;
		if ( $count < 1 ) {
			return array();
		}

		$assigned = array();

		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! isset( $this->products[ $this->cursor ] ) ) {
				break;
			}

			$assigned[] = $this->products[ $this->cursor ];
			$this->cursor++;
		}

		return $assigned;
	}

	private function render_remaining_products() {
		$rendered = '';

		while ( isset( $this->products[ $this->cursor ] ) ) {
			$rendered .= $this->render_product_card( $this->products[ $this->cursor ] );
			$this->cursor++;
		}

		return $rendered;
	}

	private function get_catalog_products() {
		if ( null !== $this->catalog_products ) {
			return $this->catalog_products;
		}

		$this->catalog_products = array();

		if ( ! function_exists( 'wc_get_products' ) ) {
			return $this->catalog_products;
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

			$this->catalog_products[] = $product;
		}

		return $this->catalog_products;
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
