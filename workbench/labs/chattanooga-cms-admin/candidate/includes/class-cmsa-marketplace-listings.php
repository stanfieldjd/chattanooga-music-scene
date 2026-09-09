<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded, read-only administration of AWP Classifieds Marketplace listings.
 *
 * The adapter is pinned to the exact AWP Classifieds contract verified for the
 * Chattanooga Marketplace. It exposes only non-sensitive listing inventory
 * fields and never reads arbitrary listing metadata or payment/contact state.
 */
final class CMSA_Marketplace_Listings {
	const SUPPORTED_AWPCP_VERSION = '4.4.8';
	const MAX_PER_PAGE            = 100;

	private $allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'disabled', 'auto-draft' );

	public function list_items( array $input = array() ) {
		$services = $this->services();
		if ( is_wp_error( $services ) ) {
			return $services;
		}
		if ( ! $this->can_administer() ) {
			return new WP_Error( 'cmsa_marketplace_listing_permission', 'Current user cannot inspect Marketplace listings.' );
		}

		$page = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? max( 1, min( self::MAX_PER_PAGE, (int) $input['per_page'] ) ) : 20;
		$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'any';
		if ( 'any' !== $status && ! in_array( $status, $this->allowed_statuses, true ) ) {
			return new WP_Error( 'cmsa_marketplace_listing_status', 'Unsupported Marketplace listing status filter.' );
		}

		$query = array(
			'post_type'           => AWPCP_LISTING_POST_TYPE,
			'post_status'         => 'any' === $status ? $this->allowed_statuses : $status,
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'orderby'             => 'modified',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
			'suppress_filters'    => false,
			's'                   => isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '',
		);

		try {
			$listings = $services['collection']->find_listings( $query );
		} catch ( Throwable $error ) {
			return new WP_Error( 'cmsa_marketplace_listing_read', 'Marketplace listing collection could not be read.' );
		}
		if ( ! is_array( $listings ) ) {
			return new WP_Error( 'cmsa_marketplace_listing_read', 'Marketplace listing collection returned an unexpected result.' );
		}

		$items = array();
		foreach ( $listings as $listing ) {
			if ( ! $listing instanceof WP_Post || ! $this->can_read_listing( $listing, $services['authorization'] ) ) {
				continue;
			}
			$items[] = $this->normalize_listing( $listing, $services['renderer'], false );
		}

		$last_query = $services['collection']->get_last_query();
		$total = $last_query instanceof WP_Query ? (int) $last_query->found_posts : count( $items );
		$pages = $last_query instanceof WP_Query ? (int) $last_query->max_num_pages : ( $items ? 1 : 0 );

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => $total,
			'pages'    => $pages,
			'items'    => $items,
		);
	}

	public function get_item( $id ) {
		$services = $this->services();
		if ( is_wp_error( $services ) ) {
			return $services;
		}
		if ( ! $this->can_administer() ) {
			return new WP_Error( 'cmsa_marketplace_listing_permission', 'Current user cannot inspect Marketplace listings.' );
		}

		$id = (int) $id;
		if ( $id < 1 ) {
			return new WP_Error( 'cmsa_marketplace_listing_id', 'A valid Marketplace listing ID is required.' );
		}

		try {
			$listing = $services['collection']->get( $id );
		} catch ( Throwable $error ) {
			return new WP_Error( 'cmsa_marketplace_listing_not_found', 'Requested Marketplace listing does not exist.' );
		}
		if ( ! $listing instanceof WP_Post || AWPCP_LISTING_POST_TYPE !== $listing->post_type ) {
			return new WP_Error( 'cmsa_marketplace_listing_not_found', 'Requested Marketplace listing does not exist.' );
		}
		if ( ! $this->can_read_listing( $listing, $services['authorization'] ) ) {
			return new WP_Error( 'cmsa_marketplace_listing_permission', 'Current user cannot inspect this Marketplace listing.' );
		}

		return array( 'listing' => $this->normalize_listing( $listing, $services['renderer'], true ) );
	}

	private function services() {
		if ( ! defined( 'AWPCP_VERSION' ) || ! defined( 'AWPCP_LISTING_POST_TYPE' ) || ! defined( 'AWPCP_CATEGORY_TAXONOMY' ) ) {
			return new WP_Error( 'cmsa_marketplace_dependency', 'Marketplace listing administration is not available.' );
		}
		if ( self::SUPPORTED_AWPCP_VERSION !== (string) AWPCP_VERSION ) {
			return new WP_Error( 'cmsa_marketplace_dependency_version', 'The installed Marketplace listing version is outside the verified adapter contract.' );
		}
		if ( 'awpcp_listing' !== (string) AWPCP_LISTING_POST_TYPE || 'awpcp_listing_category' !== (string) AWPCP_CATEGORY_TAXONOMY ) {
			return new WP_Error( 'cmsa_marketplace_dependency_contract', 'The Marketplace listing adapter contract is incompatible.' );
		}
		foreach ( array( 'awpcp_listings_collection', 'awpcp_listing_renderer', 'awpcp_listing_authorization' ) as $function ) {
			if ( ! function_exists( $function ) ) {
				return new WP_Error( 'cmsa_marketplace_dependency_contract', 'The Marketplace listing adapter contract is incomplete.' );
			}
		}
		foreach ( array( 'AWPCP_ListingsCollection', 'AWPCP_ListingRenderer', 'AWPCP_ListingAuthorization' ) as $class ) {
			if ( ! class_exists( $class ) ) {
				return new WP_Error( 'cmsa_marketplace_dependency_contract', 'The Marketplace listing adapter contract is incomplete.' );
			}
		}

		$post_type = get_post_type_object( AWPCP_LISTING_POST_TYPE );
		if ( ! $post_type || ! $post_type->map_meta_cap || 'awpcp_classified_ad' !== (string) $post_type->capability_type || ! taxonomy_exists( AWPCP_CATEGORY_TAXONOMY ) ) {
			return new WP_Error( 'cmsa_marketplace_dependency_contract', 'The Marketplace listing object model is outside the verified adapter contract.' );
		}

		try {
			$collection = awpcp_listings_collection();
			$renderer = awpcp_listing_renderer();
			$authorization = awpcp_listing_authorization();
		} catch ( Throwable $error ) {
			return new WP_Error( 'cmsa_marketplace_dependency_contract', 'The Marketplace listing adapter services could not be loaded.' );
		}
		if ( ! $collection instanceof AWPCP_ListingsCollection || ! $renderer instanceof AWPCP_ListingRenderer || ! $authorization instanceof AWPCP_ListingAuthorization ) {
			return new WP_Error( 'cmsa_marketplace_dependency_contract', 'The Marketplace listing adapter services are incompatible.' );
		}

		foreach ( array( 'get', 'find_listings', 'get_last_query' ) as $method ) {
			if ( ! method_exists( $collection, $method ) ) {
				return new WP_Error( 'cmsa_marketplace_dependency_contract', 'The Marketplace listing collection contract is incomplete.' );
			}
		}
		if ( ! method_exists( $authorization, 'is_current_user_allowed_to_edit_listing' ) ) {
			return new WP_Error( 'cmsa_marketplace_dependency_contract', 'The Marketplace listing authorization contract is incomplete.' );
		}
		foreach ( array( 'get_listing_title', 'get_price', 'get_categories_ids', 'get_views_count', 'get_plain_start_date', 'get_plain_end_date', 'is_public', 'is_disabled', 'has_expired', 'is_featured', 'is_flagged', 'is_pending_approval', 'is_verified', 'needs_review', 'get_view_listing_url' ) as $method ) {
			if ( ! method_exists( $renderer, $method ) ) {
				return new WP_Error( 'cmsa_marketplace_dependency_contract', 'The Marketplace listing renderer contract is incomplete.' );
			}
		}

		return array(
			'collection'    => $collection,
			'renderer'      => $renderer,
			'authorization' => $authorization,
		);
	}

	private function can_administer() {
		return current_user_can( 'manage_awpcp' ) && current_user_can( 'edit_others_awpcp_classified_ads' );
	}

	private function can_read_listing( WP_Post $listing, $authorization ) {
		return $this->can_administer()
			&& $authorization->is_current_user_allowed_to_edit_listing( $listing )
			&& current_user_can( 'read_post', (int) $listing->ID )
			&& current_user_can( 'edit_post', (int) $listing->ID );
	}

	private function normalize_listing( WP_Post $listing, $renderer, $detailed ) {
		$category_ids = array_values( array_unique( array_map( 'intval', (array) $renderer->get_categories_ids( $listing ) ) ) );
		sort( $category_ids, SORT_NUMERIC );

		$item = array(
			'id'                  => (int) $listing->ID,
			'title'               => (string) $renderer->get_listing_title( $listing ),
			'status'              => (string) $listing->post_status,
			'price'               => (int) $renderer->get_price( $listing ),
			'category_ids'        => $category_ids,
			'views'               => (int) $renderer->get_views_count( $listing ),
			'start_date'          => (string) $renderer->get_plain_start_date( $listing ),
			'end_date'            => (string) $renderer->get_plain_end_date( $listing ),
			'is_public'           => (bool) $renderer->is_public( $listing ),
			'is_disabled'         => (bool) $renderer->is_disabled( $listing ),
			'has_expired'         => (bool) $renderer->has_expired( $listing ),
			'is_featured'         => (bool) $renderer->is_featured( $listing ),
			'is_flagged'          => (bool) $renderer->is_flagged( $listing ),
			'is_pending_approval' => (bool) $renderer->is_pending_approval( $listing ),
			'is_verified'         => (bool) $renderer->is_verified( $listing ),
			'needs_review'        => (bool) $renderer->needs_review( $listing ),
			'view_url'            => esc_url_raw( (string) $renderer->get_view_listing_url( $listing ) ),
			'modified_gmt'        => (string) $listing->post_modified_gmt,
		);
		if ( $detailed ) {
			$item['content'] = (string) $listing->post_content;
			$item['excerpt'] = (string) $listing->post_excerpt;
		}
		return $item;
	}
}
