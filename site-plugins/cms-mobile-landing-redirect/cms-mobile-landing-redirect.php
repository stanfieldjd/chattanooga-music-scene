<?php
/**
 * Plugin Name: CMS Mobile Splash Redirect
 * Description: Redirects phone visitors to The Scene after the landing-page welcome video finishes.
 * Version: 1.2.0
 * Author: Chattanooga Music Scene
 * Requires at least: 6.2
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

/** Add the mobile splash completion handler only to the landing page. */
function cms_mobile_splash_redirect_script(): void {
	if ( ! is_page( 2819 ) ) {
		return;
	}

	$scene_url = wp_json_encode( home_url( '/scene/' ) );
	?>
	<script id="cms-mobile-splash-redirect">
	(function () {
		'use strict';

		var liveTest = window.location.search.indexOf('cms-mobile-splash-test=1') !== -1;
		if (!liveTest && (!window.matchMedia || !window.matchMedia('(max-width: 782px)').matches)) return;

		var destination = <?php echo $scene_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded URL. ?>;
		var sourceNeedle = 'cms-original-crt-snow-bleed-v7';
		var redirected = false;

		function goToScene() {
			if (redirected) return;
			redirected = true;
			window.location.replace(destination);
		}

		function isWelcomeVideo(video) {
			var urls = [video.currentSrc || '', video.getAttribute('src') || ''];
			video.querySelectorAll('source').forEach(function (source) {
				urls.push(source.getAttribute('src') || '');
			});
			return urls.some(function (url) { return url.indexOf(sourceNeedle) !== -1; });
		}

		function prepareVideo(video) {
			if (!video || !isWelcomeVideo(video) || video.dataset.cmsSplashRedirectReady === '1') return;
			video.dataset.cmsSplashRedirectReady = '1';
			video.setAttribute('playsinline', '');
			var finishTimer = 0;

			function nearEnd() {
				return Number.isFinite(video.duration) && video.duration > 0 && video.currentTime >= video.duration - 0.5;
			}

			function scheduleFallback() {
				window.clearTimeout(finishTimer);
				if (!Number.isFinite(video.duration) || video.duration <= 0) return;
				finishTimer = window.setTimeout(goToScene, Math.max(0, (video.duration - video.currentTime) * 1000) + 750);
			}

			video.addEventListener('ended', goToScene);
			video.addEventListener('playing', scheduleFallback);
			video.addEventListener('loadedmetadata', function () {
				if (!video.paused) scheduleFallback();
			});
			video.addEventListener('timeupdate', function () {
				if (nearEnd()) goToScene();
			});
			video.addEventListener('pause', function () {
				window.clearTimeout(finishTimer);
				if (nearEnd()) goToScene();
			});
			video.addEventListener('webkitendfullscreen', function () {
				if (nearEnd() || video.ended) goToScene();
			});
		}

		function scan(root) {
			if (!root || root.nodeType !== 1) return;
			if (root.matches && root.matches('video')) prepareVideo(root);
			if (root.querySelectorAll) root.querySelectorAll('video').forEach(prepareVideo);
		}

		function start() {
			scan(document.documentElement);
			new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) { mutation.addedNodes.forEach(scan); });
			}).observe(document.documentElement, { childList: true, subtree: true });
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', start, { once: true });
		} else {
			start();
		}
	}());
	</script>
	<?php
}
add_action( 'wp_footer', 'cms_mobile_splash_redirect_script', 100 );
