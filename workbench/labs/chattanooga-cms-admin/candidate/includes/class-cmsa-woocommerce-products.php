<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typed, bounded WooCommerce product-catalog administration.
 *
 * Products are always read and written through WooCommerce product objects.
 * Orders, customers, coupons, taxes, shipping, payments, arbitrary metadata,
 * publication transitions, product deletion and non-simple product mutation are
 * intentionally outside this service.
 */
final class CMSA_WooCommerce_Products {
	const SUPPORTED_WC_VERSION = '11.0.1';

	private $allowed_statuses = array( 'any', 'publish', 'draft', 'pending', 'future', 'private', 'trash' );
	private $allowed_catalog_visibility = array( 'visible', 'catalog', 'search', 'hidden' );
	private $allowed_stock_statuses = array( 'instock', 'outofstock', 'onbackorder' );

	public function list_items( array $input = array() ) {
		$dependency = $this->dependency();
		if ( is_wp_error( $dependency ) ) {
			return $dependency;
		}
		if ( ! $this->can_manage_catalog() ) {
			return new WP_Error( 'cmsa_woocommerce_product_permission', 'Current user cannot inspect WooCommerce products.' );
		}

		$page = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 20;
		$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'any';
		if ( ! in_array( $status, $this->allowed_statuses, true ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_status', 'Unsupported WooCommerce product status filter.' );
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'future', 'private', 'trash' ) : $status,
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => false,
				's'              => ! empty( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '',
				'author'         => current_user_can( 'edit_others_products' ) ? '' : get_current_user_id(),
			)
		);

		$items = array();
		foreach ( $query->posts as $product_id ) {
			if ( ! current_user_can( 'edit_post', $product_id ) ) {
				continue;
			}
			$product = wc_get_product( $product_id );
			if ( $product && $this->is_catalog_product( $product ) ) {
				$items[] = $this->normalize_product( $product, false );
			}
		}

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => (int) $query->found_posts,
			'pages'    => (int) $query->max_num_pages,
			'items'    => $items,
		);
	}

	public function get_item( $id ) {
		$dependency = $this->dependency();
		if ( is_wp_error( $dependency ) ) {
			return $dependency;
		}
		$product = $this->checked_product( $id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		return array( 'product' => $this->normalize_product( $product, true ) );
	}

	public function create_simple_draft( array $input ) {
		$dependency = $this->dependency();
		if ( is_wp_error( $dependency ) ) {
			return $dependency;
		}
		if ( ! $this->can_manage_catalog() ) {
			return new WP_Error( 'cmsa_woocommerce_product_permission', 'Current user cannot create WooCommerce products.' );
		}
		if ( ! array_key_exists( 'name', $input ) || '' === trim( sanitize_text_field( (string) $input['name'] ) ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_name', 'A non-empty product name is required.' );
		}

		$product = new WC_Product_Simple();
		$product->set_status( 'draft' );
		$applied = $this->apply_requested_fields( $product, $input, true );
		if ( is_wp_error( $applied ) ) {
			return $applied;
		}
		$target = $this->managed_snapshot( $product );

		$product_id = 0;
		try {
			$product_id = (int) $product->save();
		} catch ( Throwable $error ) {
			$rolled_back = $product_id > 0 ? $this->cleanup_created_product( $product_id ) : true;
			return new WP_Error( 'cmsa_woocommerce_product_create', 'WooCommerce could not create the product draft.', array( 'rolled_back' => $rolled_back ) );
		}
		if ( $product_id < 1 ) {
			return new WP_Error( 'cmsa_woocommerce_product_create', 'WooCommerce did not return a product ID for the draft.' );
		}

		$after = wc_get_product( $product_id );
		$verified = $after
			&& 'WC_Product_Simple' === get_class( $after )
			&& 'draft' === (string) $after->get_status()
			&& $this->managed_snapshot( $after ) === $target;
		if ( $verified && (bool) apply_filters( 'cmsa_woocommerce_product_force_verify_failure', false, 'create', $product_id, $target, $after ? $this->managed_snapshot( $after ) : array() ) ) {
			$verified = false;
		}
		if ( ! $verified ) {
			$rolled_back = $this->cleanup_created_product( $product_id );
			return new WP_Error( 'cmsa_woocommerce_product_create_verify', 'Created WooCommerce product did not pass readback verification.', array( 'rolled_back' => $rolled_back ) );
		}

		$normalized = $this->normalize_product( $after, true );
		CMSA_Audit::record( 'create-simple-product-draft', (string) $product_id, 'success', array( 'state_token' => $normalized['state_token'] ) );
		return array( 'created' => true, 'product' => $normalized );
	}

	public function update_simple( array $input ) {
		$dependency = $this->dependency();
		if ( is_wp_error( $dependency ) ) {
			return $dependency;
		}
		$product = $this->checked_product( isset( $input['id'] ) ? $input['id'] : 0 );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		if ( 'WC_Product_Simple' !== get_class( $product ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_type', 'Only core WooCommerce simple products can be mutated by this ability.' );
		}

		$current = $this->normalize_product( $product, true );
		$expected = isset( $input['expected_state_token'] ) ? sanitize_text_field( (string) $input['expected_state_token'] ) : '';
		if ( '' === $expected ) {
			return new WP_Error( 'cmsa_woocommerce_product_expected_state', 'Expected product state token is required.' );
		}
		if ( ! hash_equals( $current['state_token'], $expected ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_conflict', 'Product changed after it was read; update was not attempted.', array( 'current_state_token' => $current['state_token'] ) );
		}

		$editable_fields = $this->editable_fields();
		$provided = array_intersect( $editable_fields, array_keys( $input ) );
		if ( empty( $provided ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_no_fields', 'At least one editable product field is required.' );
		}

		$before = $this->managed_snapshot( $product );
		$before_token = $current['state_token'];
		$applied = $this->apply_requested_fields( $product, $input, false );
		if ( is_wp_error( $applied ) ) {
			return $applied;
		}
		$target = $this->managed_snapshot( $product );
		if ( $target === $before ) {
			return new WP_Error( 'cmsa_woocommerce_product_no_change', 'Requested WooCommerce product fields already match the current state.' );
		}

		try {
			$saved_id = (int) $product->save();
		} catch ( Throwable $error ) {
			$rolled_back = $this->restore_product( (int) $product->get_id(), $before, $before_token );
			return new WP_Error( 'cmsa_woocommerce_product_update', 'WooCommerce could not update the product.', array( 'rolled_back' => $rolled_back ) );
		}
		if ( $saved_id !== (int) $product->get_id() ) {
			$rolled_back = $this->restore_product( (int) $product->get_id(), $before, $before_token );
			return new WP_Error( 'cmsa_woocommerce_product_update', 'WooCommerce returned an unexpected product identity after update.', array( 'rolled_back' => $rolled_back ) );
		}

		$after = wc_get_product( $saved_id );
		$after_snapshot = $after ? $this->managed_snapshot( $after ) : array();
		$verified = $after
			&& 'WC_Product_Simple' === get_class( $after )
			&& $after_snapshot === $target;
		if ( $verified && (bool) apply_filters( 'cmsa_woocommerce_product_force_verify_failure', false, 'update', $saved_id, $target, $after_snapshot ) ) {
			$verified = false;
		}
		if ( ! $verified ) {
			$rolled_back = $this->restore_product( $saved_id, $before, $before_token );
			return new WP_Error( 'cmsa_woocommerce_product_update_verify', 'Updated WooCommerce product did not pass readback verification.', array( 'rolled_back' => $rolled_back ) );
		}

		$normalized = $this->normalize_product( $after, true );
		CMSA_Audit::record( 'update-simple-product', (string) $saved_id, 'success', array( 'state_token' => $normalized['state_token'] ) );
		return array(
			'updated'              => true,
			'previous_state_token' => $before_token,
			'product'              => $normalized,
		);
	}

	private function dependency() {
		if ( ! defined( 'WC_VERSION' ) || ! class_exists( 'WC_Product' ) || ! class_exists( 'WC_Product_Simple' ) || ! class_exists( 'WC_Data_Store' ) ) {
			return new WP_Error( 'cmsa_woocommerce_dependency', 'WooCommerce product administration is not available.' );
		}
		if ( self::SUPPORTED_WC_VERSION !== (string) WC_VERSION ) {
			return new WP_Error( 'cmsa_woocommerce_dependency_version', 'The installed WooCommerce version is outside the verified product adapter contract.' );
		}
		foreach ( array( 'wc_get_product', 'wc_get_products' ) as $function ) {
			if ( ! function_exists( $function ) ) {
				return new WP_Error( 'cmsa_woocommerce_dependency_contract', 'The WooCommerce product adapter contract is incomplete.' );
			}
		}
		foreach ( array( 'get_id', 'get_name', 'set_name', 'get_status', 'set_status', 'get_description', 'set_description', 'get_short_description', 'set_short_description', 'get_sku', 'set_sku', 'get_regular_price', 'set_regular_price', 'get_sale_price', 'set_sale_price', 'get_catalog_visibility', 'set_catalog_visibility', 'get_manage_stock', 'set_manage_stock', 'get_stock_quantity', 'set_stock_quantity', 'get_stock_status', 'set_stock_status', 'get_category_ids', 'set_category_ids', 'get_tag_ids', 'set_tag_ids', 'get_image_id', 'set_image_id', 'get_gallery_image_ids', 'set_gallery_image_ids', 'save', 'delete' ) as $method ) {
			if ( ! method_exists( 'WC_Product', $method ) ) {
				return new WP_Error( 'cmsa_woocommerce_dependency_contract', 'The WooCommerce product adapter contract is incomplete.' );
			}
		}
		try {
			$store = WC_Data_Store::load( 'product' );
			if ( ! is_object( $store ) || ! method_exists( $store, 'get_current_class_name' ) || 'WC_Product_Data_Store_CPT' !== (string) $store->get_current_class_name() ) {
				return new WP_Error( 'cmsa_woocommerce_dependency_store', 'The active WooCommerce product data store is outside the verified adapter contract.' );
			}
		} catch ( Throwable $error ) {
			return new WP_Error( 'cmsa_woocommerce_dependency_store', 'The active WooCommerce product data store could not be verified.' );
		}
		return true;
	}

	private function can_manage_catalog() {
		return current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_products' );
	}

	private function checked_product( $id ) {
		if ( ! $this->can_manage_catalog() ) {
			return new WP_Error( 'cmsa_woocommerce_product_permission', 'Current user cannot manage WooCommerce products.' );
		}
		$id = (int) $id;
		if ( $id < 1 ) {
			return new WP_Error( 'cmsa_woocommerce_product_id', 'A valid WooCommerce product ID is required.' );
		}
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return new WP_Error( 'cmsa_woocommerce_product_not_found', 'Requested WooCommerce product does not exist.' );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_permission', 'Current user cannot edit this WooCommerce product.' );
		}
		$product = wc_get_product( $id );
		if ( ! $product || ! $this->is_catalog_product( $product ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_not_found', 'Requested WooCommerce product could not be loaded through the native product model.' );
		}
		return $product;
	}

	private function is_catalog_product( $product ) {
		if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}
		$post = get_post( (int) $product->get_id() );
		return $post instanceof WP_Post && 'product' === $post->post_type;
	}

	private function editable_fields() {
		return array(
			'name', 'description', 'short_description', 'sku', 'regular_price', 'sale_price',
			'catalog_visibility', 'manage_stock', 'stock_quantity', 'stock_status',
			'category_ids', 'tag_ids', 'image_id', 'gallery_image_ids',
		);
	}

	private function apply_requested_fields( $product, array $input, $creating ) {
		try {
			if ( array_key_exists( 'name', $input ) ) {
				$name = trim( sanitize_text_field( (string) $input['name'] ) );
				if ( '' === $name ) {
					return new WP_Error( 'cmsa_woocommerce_product_name', 'Product name cannot be empty.' );
				}
				$product->set_name( $name );
			}
			if ( array_key_exists( 'description', $input ) ) {
				$product->set_description( wp_kses_post( (string) $input['description'] ) );
			}
			if ( array_key_exists( 'short_description', $input ) ) {
				$product->set_short_description( wp_kses_post( (string) $input['short_description'] ) );
			}
			if ( array_key_exists( 'sku', $input ) ) {
				$product->set_sku( trim( sanitize_text_field( (string) $input['sku'] ) ) );
			}
			foreach ( array( 'regular_price', 'sale_price' ) as $price_field ) {
				if ( array_key_exists( $price_field, $input ) ) {
					$price = $this->normalize_price( $input[ $price_field ] );
					if ( is_wp_error( $price ) ) {
						return $price;
					}
					$setter = 'set_' . $price_field;
					$product->{$setter}( $price );
				}
			}
			if ( array_key_exists( 'catalog_visibility', $input ) ) {
				$visibility = sanitize_key( (string) $input['catalog_visibility'] );
				if ( ! in_array( $visibility, $this->allowed_catalog_visibility, true ) ) {
					return new WP_Error( 'cmsa_woocommerce_product_visibility', 'Unsupported WooCommerce catalog visibility.' );
				}
				$product->set_catalog_visibility( $visibility );
			}
			if ( array_key_exists( 'manage_stock', $input ) ) {
				$product->set_manage_stock( (bool) $input['manage_stock'] );
			}
			if ( array_key_exists( 'stock_quantity', $input ) ) {
				if ( ! $this->integer_input( $input['stock_quantity'] ) ) {
					return new WP_Error( 'cmsa_woocommerce_product_stock_quantity', 'Stock quantity must be a non-negative integer.' );
				}
				$product->set_stock_quantity( (int) $input['stock_quantity'] );
			}
			if ( array_key_exists( 'stock_status', $input ) ) {
				$stock_status = sanitize_key( (string) $input['stock_status'] );
				if ( ! in_array( $stock_status, $this->allowed_stock_statuses, true ) ) {
					return new WP_Error( 'cmsa_woocommerce_product_stock_status', 'Unsupported WooCommerce stock status.' );
				}
				$product->set_stock_status( $stock_status );
			}
			if ( array_key_exists( 'category_ids', $input ) ) {
				$categories = $this->validated_term_ids( $input['category_ids'], 'product_cat' );
				if ( is_wp_error( $categories ) ) {
					return $categories;
				}
				$product->set_category_ids( $categories );
			}
			if ( array_key_exists( 'tag_ids', $input ) ) {
				$tags = $this->validated_term_ids( $input['tag_ids'], 'product_tag' );
				if ( is_wp_error( $tags ) ) {
					return $tags;
				}
				$product->set_tag_ids( $tags );
			}
			if ( array_key_exists( 'image_id', $input ) ) {
				$image_id = max( 0, (int) $input['image_id'] );
				if ( $image_id && ! $this->valid_image_attachment( $image_id ) ) {
					return new WP_Error( 'cmsa_woocommerce_product_image', 'Product image must reference an editable image attachment.' );
				}
				$product->set_image_id( $image_id );
			}
			if ( array_key_exists( 'gallery_image_ids', $input ) ) {
				$gallery = $this->validated_image_ids( $input['gallery_image_ids'] );
				if ( is_wp_error( $gallery ) ) {
					return $gallery;
				}
				$product->set_gallery_image_ids( $gallery );
			}
		} catch ( Throwable $error ) {
			return new WP_Error( 'cmsa_woocommerce_product_invalid', 'WooCommerce rejected one or more requested product fields.' );
		}

		if ( ! $product->get_manage_stock() && array_key_exists( 'stock_quantity', $input ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_stock_mode', 'Stock quantity cannot be changed while stock management is disabled.' );
		}
		if ( $product->get_manage_stock() && null === $product->get_stock_quantity() ) {
			return new WP_Error( 'cmsa_woocommerce_product_stock_quantity', 'Managed stock requires an explicit or existing stock quantity.' );
		}
		if ( $creating && 'draft' !== (string) $product->get_status() ) {
			return new WP_Error( 'cmsa_woocommerce_product_create_status', 'Product creation is restricted to drafts.' );
		}
		return true;
	}

	private function normalize_price( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^(?:0|[1-9]\d*)(?:\.\d+)?$/', $value ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_price', 'Product prices must be empty or non-negative decimal strings.' );
		}
		return $value;
	}

	private function integer_input( $value ) {
		return ( is_int( $value ) && $value >= 0 ) || ( is_string( $value ) && ctype_digit( $value ) );
	}

	private function validated_term_ids( $value, $taxonomy ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_terms', 'Product term relationships must be arrays of existing term IDs.' );
		}
		if ( ! current_user_can( 'assign_product_terms' ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_terms_permission', 'Current user cannot assign WooCommerce product terms.' );
		}
		$ids = array();
		foreach ( $value as $raw_id ) {
			if ( ! $this->positive_integer_input( $raw_id ) ) {
				return new WP_Error( 'cmsa_woocommerce_product_terms', 'Product term IDs must be positive integers.' );
			}
			$id = (int) $raw_id;
			if ( ! term_exists( $id, $taxonomy ) ) {
				return new WP_Error( 'cmsa_woocommerce_product_term_not_found', 'Requested WooCommerce product term does not exist.' );
			}
			$ids[ $id ] = $id;
		}
		$ids = array_values( $ids );
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}

	private function validated_image_ids( $value ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'cmsa_woocommerce_product_gallery', 'Product gallery must be an array of image attachment IDs.' );
		}
		$ids = array();
		foreach ( $value as $raw_id ) {
			if ( ! $this->positive_integer_input( $raw_id ) ) {
				return new WP_Error( 'cmsa_woocommerce_product_gallery', 'Product gallery IDs must be positive integers.' );
			}
			$id = (int) $raw_id;
			if ( ! $this->valid_image_attachment( $id ) ) {
				return new WP_Error( 'cmsa_woocommerce_product_image', 'Product gallery must reference editable image attachments.' );
			}
			if ( ! isset( $ids[ $id ] ) ) {
				$ids[ $id ] = $id;
			}
		}
		return array_values( $ids );
	}

	private function positive_integer_input( $value ) {
		return ( is_int( $value ) && $value > 0 ) || ( is_string( $value ) && ctype_digit( $value ) && (int) $value > 0 );
	}

	private function valid_image_attachment( $id ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return false;
		}
		$attachment = get_post( (int) $id );
		return $attachment instanceof WP_Post
			&& 'attachment' === $attachment->post_type
			&& 0 === strpos( (string) $attachment->post_mime_type, 'image/' )
			&& current_user_can( 'edit_post', (int) $id );
	}

	private function normalize_product( $product, $detailed ) {
		$state = $this->managed_snapshot( $product );
		$post = get_post( (int) $product->get_id() );
		$item = array(
			'id'                 => (int) $product->get_id(),
			'name'               => $state['name'],
			'status'             => $state['status'],
			'product_type'       => $state['product_type'],
			'sku'                => $state['sku'],
			'regular_price'      => $state['regular_price'],
			'sale_price'         => $state['sale_price'],
			'catalog_visibility' => $state['catalog_visibility'],
			'manage_stock'       => $state['manage_stock'],
			'stock_quantity'     => $state['stock_quantity'],
			'stock_status'       => $state['stock_status'],
			'category_ids'       => $state['category_ids'],
			'tag_ids'            => $state['tag_ids'],
			'image_id'           => $state['image_id'],
			'modified_gmt'       => $post instanceof WP_Post ? (string) $post->post_modified_gmt : '',
			'state_token'        => $this->state_token( (int) $product->get_id(), $state ),
		);
		if ( $detailed ) {
			$item['description'] = $state['description'];
			$item['short_description'] = $state['short_description'];
			$item['gallery_image_ids'] = $state['gallery_image_ids'];
		}
		return $item;
	}

	private function managed_snapshot( $product ) {
		$categories = array_values( array_unique( array_map( 'intval', (array) $product->get_category_ids() ) ) );
		$tags = array_values( array_unique( array_map( 'intval', (array) $product->get_tag_ids() ) ) );
		sort( $categories, SORT_NUMERIC );
		sort( $tags, SORT_NUMERIC );
		$gallery = array_values( array_unique( array_map( 'intval', (array) $product->get_gallery_image_ids() ) ) );
		$quantity = $product->get_stock_quantity();

		return array(
			'product_type'       => $this->product_type( $product ),
			'status'             => (string) $product->get_status(),
			'name'               => (string) $product->get_name(),
			'description'        => (string) $product->get_description(),
			'short_description'  => (string) $product->get_short_description(),
			'sku'                => (string) $product->get_sku(),
			'regular_price'      => (string) $product->get_regular_price(),
			'sale_price'         => (string) $product->get_sale_price(),
			'catalog_visibility' => (string) $product->get_catalog_visibility(),
			'manage_stock'       => (bool) $product->get_manage_stock(),
			'stock_quantity'     => null === $quantity ? null : (int) $quantity,
			'stock_status'       => (string) $product->get_stock_status(),
			'category_ids'       => $categories,
			'tag_ids'            => $tags,
			'image_id'           => (int) $product->get_image_id(),
			'gallery_image_ids'  => $gallery,
		);
	}

	private function product_type( $product ) {
		$class = is_object( $product ) ? get_class( $product ) : '';
		$map = array(
			'WC_Product_Simple'   => 'simple',
			'WC_Product_Variable' => 'variable',
			'WC_Product_Grouped'  => 'grouped',
			'WC_Product_External' => 'external',
		);
		return isset( $map[ $class ] ) ? $map[ $class ] : 'other';
	}

	private function state_token( $product_id, array $state ) {
		return hash(
			'sha256',
			wp_json_encode(
				array(
					'id'    => (int) $product_id,
					'state' => $state,
				),
				JSON_UNESCAPED_SLASHES
			)
		);
	}

	private function restore_product( $product_id, array $before, $before_token ) {
		$product = wc_get_product( (int) $product_id );
		if ( ! $product || 'WC_Product_Simple' !== get_class( $product ) ) {
			return false;
		}
		try {
			$this->apply_snapshot( $product, $before );
			$saved = (int) $product->save();
			if ( $saved !== (int) $product_id ) {
				return false;
			}
		} catch ( Throwable $error ) {
			return false;
		}
		$after = wc_get_product( (int) $product_id );
		if ( ! $after || 'WC_Product_Simple' !== get_class( $after ) ) {
			return false;
		}
		$after_state = $this->managed_snapshot( $after );
		return $after_state === $before && hash_equals( (string) $before_token, $this->state_token( (int) $product_id, $after_state ) );
	}

	private function apply_snapshot( $product, array $state ) {
		$product->set_status( $state['status'] );
		$product->set_name( $state['name'] );
		$product->set_description( $state['description'] );
		$product->set_short_description( $state['short_description'] );
		$product->set_sku( $state['sku'] );
		$product->set_regular_price( $state['regular_price'] );
		$product->set_sale_price( $state['sale_price'] );
		$product->set_catalog_visibility( $state['catalog_visibility'] );
		$product->set_manage_stock( $state['manage_stock'] );
		$product->set_stock_quantity( $state['stock_quantity'] );
		$product->set_stock_status( $state['stock_status'] );
		$product->set_category_ids( $state['category_ids'] );
		$product->set_tag_ids( $state['tag_ids'] );
		$product->set_image_id( $state['image_id'] );
		$product->set_gallery_image_ids( $state['gallery_image_ids'] );
	}

	private function cleanup_created_product( $product_id ) {
		$product = wc_get_product( (int) $product_id );
		if ( ! $product ) {
			return null === get_post( (int) $product_id );
		}
		try {
			$deleted = $product->delete( true );
		} catch ( Throwable $error ) {
			return false;
		}
		return false !== $deleted && false === wc_get_product( (int) $product_id ) && null === get_post( (int) $product_id );
	}
}
