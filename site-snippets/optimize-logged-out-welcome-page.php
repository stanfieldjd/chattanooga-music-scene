/**
 * Return whether the logged-out Welcome page is the current request.
 *
 * @return bool
 */
function cms_optimize_welcome_page_request() {
	return ! is_user_logged_in() && is_page( 2819 );
}

/**
 * Remove styles that have no rendered component on the Welcome page.
 */
function cms_optimize_welcome_page_styles() {
	if ( ! cms_optimize_welcome_page_request() ) {
		return;
	}

	$unused_styles = array(
		'bp-nouveau-icons-map',
		'bp-nouveau-bb-icons',
		'bp-nouveau',
		'bp-media-videojs-css',
		'bp-mentions-css',
		'events-manager',
		'simple-content-cards',
		'woocommerce-layout',
		'woocommerce-smallscreen',
		'woocommerce-general',
		'buddyx-site-loader',
		'buddyx-load-fontawesome',
		'buddyx-buddypress',
		'buddyx-platform',
		'buddyx-bbpress',
		'buddyx-woocommerce',
		'buddyx-slick',
		'buddyx-dark-mode',
		'awpcp-font-awesome',
		'awpcp-frontend-style',
		'wc-blocks-style',
	);

	foreach ( $unused_styles as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'cms_optimize_welcome_page_styles', 999 );
add_action( 'wp_print_styles', 'cms_optimize_welcome_page_styles', 0 );

/**
 * Remove scripts that have no rendered component on the Welcome page.
 */
function cms_optimize_welcome_page_scripts() {
	if ( ! cms_optimize_welcome_page_request() ) {
		return;
	}

	$unused_scripts = array(
		'bb-twemoji',
		'bb-emoji-loader',
		'jquery',
		'jquery-core',
		'jquery-migrate',
		'moment',
		'bp-nouveau-magnific-popup',
		'bp-media-dropzone',
		'bp-widget-members',
		'bp-jquery-query',
		'bp-jquery-cookie',
		'bp-jquery-scroll-to',
		'bp-exif',
		'bp-media-videojs',
		'bp-media-videojs-seek-buttons',
		'bp-media-videojs-flv',
		'bp-media-videojs-flash',
		'isInViewport',
		'jquery-ui-core',
		'jquery-ui-menu',
		'wp-dom-ready',
		'jquery-ui-mouse',
		'jquery-ui-sortable',
		'jquery-ui-datepicker',
		'jquery-ui-resizable',
		'jquery-ui-draggable',
		'jquery-ui-controlgroup',
		'jquery-ui-checkboxradio',
		'jquery-ui-button',
		'jquery-ui-dialog',
		'events-manager',
		'chart-js',
		'wc-jquery-blockui',
		'wc-add-to-cart',
		'wc-js-cookie',
		'woocommerce',
		'woocommerce-analytics',
		'woocommerce-analytics-client',
		'sourcebuster-js',
		'wc-order-attribution',
		'googlesitekit-events-provider-woocommerce',
		'bp-livestamp',
		'guillotine-js',
		'bp-nouveau-codemirror',
		'bp-nouveau-codemirror-css',
		'underscore',
		'wp-util',
		'wp-hooks',
		'wp-i18n',
		'wp-a11y',
		'jquery-ui-autocomplete',
		'bp-nouveau',
		'bp-nouveau-media',
		'bp-nouveau-video',
		'bp-nouveau-search',
		'bp-nouveau-moderation',
		'jquery-caret',
		'jquery-atwho',
		'bp-mentions',
		'buddyx-navigation',
		'buddyx-superfish',
		'buddyx-isotope-pkgd',
		'buddyx-fitvids',
		'buddyx-sticky-kit',
		'buddyx-jquery-cookie',
		'buddyx-slick',
		'buddyx-custom',
		'buddyx-buddypress',
		'buddyx-color-mode-toggle',
		'bcp-frontend',
		'comment-reply',
		'heartbeat',
	);

	if ( function_exists( 'wp_dequeue_script_module' ) ) {
		wp_dequeue_script_module( '@wordpress/block-library/navigation/view' );
	}

	foreach ( $unused_scripts as $handle ) {
		wp_dequeue_script( $handle );
		wp_deregister_script( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'cms_optimize_welcome_page_scripts', 999 );
add_action( 'wp_print_scripts', 'cms_optimize_welcome_page_scripts', 0 );
