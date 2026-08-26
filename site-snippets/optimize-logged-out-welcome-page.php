add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_user_logged_in() || ! is_page( 2819 ) ) {
			return;
		}

		$unused_styles = array(
			'bp-nouveau-icons-map',
			'bp-nouveau-bb-icons',
			'bp-nouveau',
			'bcp-frontend',
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

		$unused_scripts = array(
			'twemoji',
			'bb-emoji-loader',
			'magnific-popup',
			'bp-widget-members',
			'jquery-query',
			'bp-jquery-cookie',
			'jquery-scroll-to',
			'exif-js',
			'bcp-frontend',
			'isInViewport',
			'moment',
			'livestamp',
			'bp-nouveau',
			'comment-reply',
			'heartbeat',
			'bp-css',
			'jquery-ui-core',
			'jquery-ui-menu',
			'wp-dom-ready',
			'wp-a11y',
			'jquery-ui-autocomplete',
			'bp-nouveau-search',
			'bp-nouveau-moderation',
			'jquery-caret',
			'jquery-atwho',
			'bp-mentions',
			'woocommerce',
			'sourcebuster-js',
			'wc-order-attribution',
			'woocommerce-analytics-client',
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
		);

		foreach ( $unused_styles as $handle ) {
			wp_dequeue_style( $handle );
		}

		foreach ( $unused_scripts as $handle ) {
			wp_dequeue_script( $handle );
		}
	},
	999
);
