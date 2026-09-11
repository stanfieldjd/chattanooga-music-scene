<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMS_Unified_Marketplace {
	const MARKETPLACE_PAGE_ID = 12;
	const SHORTCODE           = 'cms_marketplace';

	private static $instance;

	private $catalog_products = null;
	private $integration_ready = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AWP Classifieds owns this public extension hook.
		add_filter( 'awpcp-browse-listings-content-replacement', array( $this, 'replace_browse_listings_content' ), 20, 2 );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AWP Classifieds owns this public extension hook.
		add_filter( 'awpcp-search-listings-content-replacement', array( $this, 'replace_search_listings_content' ), 20, 2 );
	}

	public function enqueue_styles() {
		if ( ! $this->is_marketplace_surface_request() ) {
			return;
		}

		wp_enqueue_style(
			'cms-unified-marketplace',
			CMS_MARKETPLACE_URL . 'assets/marketplace.css',
			array(),
			CMS_MARKETPLACE_VERSION
		);
	}

	public function render_shortcode() {
		if ( ! $this->is_marketplace_request() || ! $this->dependencies_ready() ) {
			return '';
		}

		$awpcp_page_name = sanitize_title( awpcp_get_current_page_name() );

		if ( ! get_awpcp_option( 'main_page_display' ) ) {
			return awpcp_display_the_classifieds_page_body( $awpcp_page_name );
		}

		awpcp_enqueue_main_script();

		$query = array(
			'context' => 'public-listings',
			'limit'   => awpcp_get_var(
				array(
					'param'    => 'results',
					'default'  => get_awpcp_option( 'adresultsperpage', 10 ),
					'sanitize' => 'absint',
				)
			),
			'offset'  => awpcp_get_var(
				array(
					'param'    => 'offset',
					'default'  => 0,
					'sanitize' => 'absint',
				)
			),
			'orderby' => get_awpcp_option( 'groupbrowseadsby' ),
		);

		return $this->render_unified_listings_in_page( $query, 'main-page' );
	}

	public function replace_browse_listings_content( $output, $category_id ) {
		if ( null !== $output || ! $this->integration_ready() || ! $this->dependencies_ready() ) {
			return $output;
		}

		$query = array(
			'classifieds_query' => array(
				'context' => 'public-listings',
			),
			'orderby'           => get_awpcp_option( 'groupbrowseadsby' ),
		);

		$category_id = absint( $category_id );
		if ( $category_id > 0 ) {
			$query['classifieds_query']['category'] = $category_id;
		}

		$page    = awpcp_browse_listings_page();
		$options = array( 'page' => $page->page );

		return $this->render_unified_listings_in_page( $query, 'browse-listings', $options );
	}

	public function replace_search_listings_content( $output, $form ) {
		if ( null !== $output || ! is_array( $form ) || ! $this->integration_ready() || ! $this->dependencies_ready() ) {
			return $output;
		}

		$query = array(
			's'                 => isset( $form['query'] ) ? $form['query'] : '',
			'classifieds_query' => array(
				'context'      => 'public-listings',
				'category'     => isset( $form['category'] ) ? $form['category'] : null,
				'contact_name' => isset( $form['name'] ) ? $form['name'] : '',
				'min_price'    => isset( $form['min_price'] ) ? $form['min_price'] : null,
				'max_price'    => isset( $form['max_price'] ) ? $form['max_price'] : null,
				'regions'      => isset( $form['regions'] ) ? $form['regions'] : null,
			),
			'posts_per_page'    => awpcp_get_var(
				array(
					'param'    => 'results',
					'default'  => get_awpcp_option( 'adresultsperpage', 10 ),
					'sanitize' => 'absint',
				)
			),
			'offset'            => awpcp_get_var(
				array(
					'param'    => 'offset',
					'default'  => 0,
					'sanitize' => 'absint',
				)
			),
			'orderby'           => get_awpcp_option( 'search-results-order' ),
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AWP Classifieds owns this public extension hook.
		$query = apply_filters( 'awpcp-search-listings-query', $query, $form );

		$page    = awpcp_search_listings_page();
		$options = array(
			'show_intro_message'         => true,
			'show_menu_items'            => false,
			'show_category_selector'     => false,
			'show_pagination'            => true,
			'classifieds_bar_components' => array( 'search_bar' => false ),
		);

		$position = get_awpcp_option( 'search-form-in-results' );
		if ( 'above' === $position ) {
			$options['before_pagination'] = $this->render_search_form( $form );
		}
		if ( 'below' === $position ) {
			$options['after_pagination'] = $this->render_search_form( $form );
		}
		if ( 'none' === $position ) {
			$options['before_list'] = $page->build_return_link();
		}

		$search_results = $this->render_unified_listings( $query, 'search', $options );

		return $page->render( 'content', $search_results );
	}

	private function dependencies_ready() {
		$required_functions = array(
			'awpcp_array_merge_recursive',
			'awpcp_browse_listings_page',
			'awpcp_categories_switcher',
			'awpcp_current_url',
			'awpcp_display_the_classifieds_page_body',
			'awpcp_enqueue_main_script',
			'awpcp_flatten_array',
			'awpcp_get_current_page_name',
			'awpcp_get_option',
			'awpcp_get_page_id_by_ref',
			'awpcp_get_results_offset',
			'awpcp_get_results_per_page',
			'awpcp_get_var',
			'awpcp_listings_collection',
			'awpcp_pagination',
			'awpcp_render_listings_items',
			'awpcp_search_listings_page',
			'awpcp_template_renderer',
			'wc_get_products',
		);

		foreach ( $required_functions as $function_name ) {
			if ( ! function_exists( $function_name ) ) {
				return false;
			}
		}

		return defined( 'AWPCP_DIR' );
	}

	private function is_marketplace_request() {
		return ! is_admin() && is_page( self::MARKETPLACE_PAGE_ID ) && $this->integration_ready();
	}

	private function is_marketplace_surface_request() {
		if ( is_admin() || ! $this->integration_ready() ) {
			return false;
		}

		if ( is_page( self::MARKETPLACE_PAGE_ID ) ) {
			return true;
		}

		if ( ! function_exists( 'awpcp_get_page_id_by_ref' ) ) {
			return false;
		}

		$page_ids = array(
			absint( awpcp_get_page_id_by_ref( 'browse-ads-page-name' ) ),
			absint( awpcp_get_page_id_by_ref( 'search-ads-page-name' ) ),
		);

		foreach ( array_filter( $page_ids ) as $page_id ) {
			if ( is_page( $page_id ) ) {
				return true;
			}
		}

		return false;
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

		$this->integration_ready = has_shortcode( $content, self::SHORTCODE )
			&& ! has_shortcode( $content, 'AWPCPCLASSIFIEDSUI' )
			&& ! has_shortcode( $content, 'products' );

		return $this->integration_ready;
	}

	private function render_search_form( $form ) {
		$errors = array();
		$ui     = array(
			'module-extra-fields'                      => ! empty( $GLOBALS['hasextrafieldsmodule'] ),
			'posted-by-field'                          => get_awpcp_option( 'displaypostedbyfield' ),
			'price-field'                              => get_awpcp_option( 'display_price_field_on_search_form' ),
			'allow-user-to-search-in-multiple-regions' => get_awpcp_option( 'allow-user-to-search-in-multiple-regions' ),
		);

		$url_params = wp_parse_args( wp_parse_url( awpcp_current_url(), PHP_URL_QUERY ) );
		foreach ( $form as $name => $value ) {
			if ( isset( $url_params[ $name ] ) ) {
				unset( $url_params[ $name ] );
			}
		}
		unset( $url_params['searchcategory'] );

		$action_url = awpcp_current_url();
		$hidden     = array_merge( $url_params, array( 'awpcp-step' => 'dosearch' ) );
		$params     = compact( 'action_url', 'ui', 'form', 'hidden', 'errors' );
		$template   = AWPCP_DIR . '/frontend/templates/page-search-ads.tpl.php';

		return awpcp_template_renderer()->render_template( $template, $params );
	}

	private function render_unified_listings_in_page( $query, $context, $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'show_intro_message'     => true,
				'show_menu_items'        => true,
				'show_category_selector' => ! awpcp_get_option( 'hide-categories-selector', false ),
				'show_pagination'        => true,
			)
		);

		return $this->render_unified_listings( $query, $context, $options );
	}

	private function render_unified_listings( $query_vars, $context, $options ) {
		$options = wp_parse_args(
			$options,
			array(
				'page'                       => false,
				'show_intro_message'         => false,
				'show_menu_items'            => false,
				'show_category_selector'     => false,
				'show_pagination'            => false,
				'featured'                   => false,
				'classifieds_bar_components' => array(),
				'before_content'             => '',
				'before_pagination'          => '',
				'before_list'                => '',
				'after_pagination'           => '',
				'after_content'              => '',
			)
		);

		if ( isset( $query_vars['context'] ) && ! isset( $query_vars['classifieds_query'] ) ) {
			$query_vars['classifieds_query'] = array(
				'context' => $query_vars['context'],
			);
		}

		$results_per_page = awpcp_get_results_per_page( $query_vars );
		$results_offset   = awpcp_get_results_offset( $results_per_page, $query_vars );

		$query_vars['posts_per_page'] = $results_per_page;
		$query_vars['paged']          = 1 + floor( $results_offset / $results_per_page );

		unset( $query_vars['results'], $query_vars['limit'], $query_vars['offset'] );

		$listings_collection = awpcp_listings_collection();
		$listings            = $listings_collection->find_enabled_listings( $query_vars );
		$query               = $listings_collection->get_last_query();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AWP Classifieds owns this public extension hook.
		$before_content = apply_filters( 'awpcp-content-before-listings-page', $options['before_content'], $context );

		$before_pagination = array();
		if ( $options['show_category_selector'] ) {
			$before_pagination[15]['category-selector'] = awpcp_categories_switcher()->render( array( 'required' => false ) );
		}
		if ( is_array( $options['before_pagination'] ) ) {
			$before_pagination = awpcp_array_merge_recursive( $before_pagination, $options['before_pagination'] );
		} else {
			$before_pagination[20]['user-content'] = $options['before_pagination'];
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AWP Classifieds owns this public extension hook.
		$before_pagination = apply_filters( 'awpcp-content-before-listings-pagination', $before_pagination, $context, $listings, $query_vars );
		ksort( $before_pagination );
		$before_pagination = awpcp_flatten_array( $before_pagination );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AWP Classifieds owns this public extension hook.
		$before_list = apply_filters( 'awpcp-content-before-listings-list', $options['before_list'], $context );

		$top_pagination    = '';
		$bottom_pagination = '';
		$listing_items     = array();

		if ( $query->found_posts > 0 ) {
			if ( $options['show_pagination'] ) {
				$top_pagination_options = array(
					'query'         => $query,
					'results'       => $results_per_page,
					'offset'        => $results_offset,
					'total'         => $query->found_posts,
					'show_dropdown' => false,
				);

				$bottom_pagination_options = $top_pagination_options;
				unset( $bottom_pagination_options['show_dropdown'] );

				$top_pagination    = awpcp_pagination( $top_pagination_options, awpcp_current_url() );
				$bottom_pagination = awpcp_pagination( $bottom_pagination_options, awpcp_current_url() );
			}

			$listing_items = awpcp_render_listings_items( $listings, $context, $options );
		}

		$product_items = array();
		if ( $this->is_first_results_page( $query_vars ) ) {
			foreach ( $this->get_products_for_query( $context, $query_vars ) as $product ) {
				$product_items[] = $this->render_product_card( $product );
			}
		}

		$items = $this->merge_rendered_items( $listing_items, $product_items );

		$after_pagination = array( 'user-content' => $options['after_pagination'] );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AWP Classifieds owns this public extension hook.
		$after_pagination = apply_filters( 'awpcp-content-after-listings-pagination', $after_pagination, $context );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AWP Classifieds owns this public extension hook.
		$after_content = apply_filters( 'awpcp-content-after-listings-page', $options['after_content'], $context );

		ob_start();
		include AWPCP_DIR . '/templates/frontend/listings.tpl.php';
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	private function merge_rendered_items( $listing_items, $product_items ) {
		$listing_items = is_array( $listing_items ) ? array_values( $listing_items ) : array();
		$product_items = is_array( $product_items ) ? array_values( $product_items ) : array();

		if ( empty( $listing_items ) ) {
			return $product_items;
		}

		if ( empty( $product_items ) ) {
			return $listing_items;
		}

		$merged        = array();
		$listing_count = count( $listing_items );
		$product_count = count( $product_items );
		$base          = intdiv( $product_count, $listing_count );
		$remainder     = $product_count % $listing_count;
		$product_index = 0;

		foreach ( $listing_items as $position => $listing_item ) {
			$merged[] = $listing_item;
			$count    = $base + ( $position < $remainder ? 1 : 0 );

			for ( $i = 0; $i < $count; $i++ ) {
				if ( ! isset( $product_items[ $product_index ] ) ) {
					break;
				}

				$merged[] = $product_items[ $product_index ];
				$product_index++;
			}
		}

		while ( isset( $product_items[ $product_index ] ) ) {
			$merged[] = $product_items[ $product_index ];
			$product_index++;
		}

		return $merged;
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
			$products = $this->filter_products_by_search( $products, $search );
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

	private function filter_products_by_search( $products, $search ) {
		if ( empty( $products ) ) {
			return array();
		}

		$product_ids = array();
		foreach ( $products as $product ) {
			if ( $product instanceof WC_Product ) {
				$product_id = absint( $product->get_id() );
				if ( $product_id > 0 ) {
					$product_ids[] = $product_id;
				}
			}
		}

		if ( empty( $product_ids ) ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'post__in'               => $product_ids,
				'posts_per_page'         => count( $product_ids ),
				'orderby'                => 'post__in',
				'fields'                 => 'ids',
				's'                      => $search,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$matching_ids = array_fill_keys( array_map( 'absint', $query->posts ), true );

		return array_values(
			array_filter(
				$products,
				function ( $product ) use ( $matching_ids ) {
					return $product instanceof WC_Product && isset( $matching_ids[ absint( $product->get_id() ) ] );
				}
			)
		);
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
				'limit'   => -1,
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

		$description = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $description ) ), 28, '…' );
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
