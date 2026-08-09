<?php
/**
 * Plugin Name: CMS Mobile Landing Redirect
 * Description: Sends phone visitors from the landing splash page to The Scene while preserving the desktop splash.
 * Version: 1.0.0
 * Author: Chattanooga Music Scene
 * Requires at least: 6.2
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

/** Redirect mobile requests before WordPress renders the landing page. */
function cms_mobile_landing_server_redirect(): void {
    if ( ! is_page( 2819 ) || ! wp_is_mobile() ) {
        return;
    }

    wp_safe_redirect( home_url( '/scene/' ), 302, 'CMS Mobile Landing Redirect' );
    exit;
}
add_action( 'template_redirect', 'cms_mobile_landing_server_redirect', 1 );

/**
 * Page caches can serve desktop markup to a phone, so retain a small
 * viewport-based fallback on the landing page itself.
 */
function cms_mobile_landing_browser_fallback(): void {
    if ( ! is_page( 2819 ) ) {
        return;
    }

    $scene_url = wp_json_encode( home_url( '/scene/' ) );
    echo '<script id="cms-mobile-landing-redirect">';
    echo 'if(window.matchMedia&&window.matchMedia("(max-width: 767px)").matches){window.location.replace(' . $scene_url . ');}';
    echo '</script>';
}
add_action( 'wp_head', 'cms_mobile_landing_browser_fallback', 1 );
