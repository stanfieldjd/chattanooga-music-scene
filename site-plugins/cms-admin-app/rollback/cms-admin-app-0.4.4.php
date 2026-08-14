<?php
/**
 * Plugin Name: Chattanooga Music Scene Admin App
 * Description: Installable administrator app foundation for Chattanooga Music Scene.
 * Version: 0.4.4
 * Author: Chattanooga Music Scene
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMS_Admin_App {
	private const VERSION = '0.4.4';
	private const PAGE_SLUG = 'cms-admin-app';
	private const FIREBASE_API_KEY = 'AIzaSyC-SaF0QTN2KzPgGfLlIQINMwzVnTiPRYI';
	private const FIREBASE_AUTH_DOMAIN = 'cms-admin-79199.firebaseapp.com';
	private const FIREBASE_PROJECT_ID = 'cms-admin-79199';
	private const FIREBASE_STORAGE_BUCKET = 'cms-admin-79199.firebasestorage.app';
	private const FIREBASE_SENDER_ID = '915180591918';
	private const FIREBASE_APP_ID = '1:915180591918:web:3df24111fda2f7b4c7b8bf';
	private const FIREBASE_VAPID_KEY = 'BLNRgCTdURre4SI2XvFQTruaRxDVHSOTLKlAvdOnmm8-yQIqli5TC-ebLkxh6fwjFNy--O0WTZd94IPB6EL1D_s';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_head', array( __CLASS__, 'print_manifest_link' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_manifest_link' ) );
		add_action( 'admin_footer', array( __CLASS__, 'print_registration_script' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_registration_script' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve_app_asset' ), 0 );
		add_action( 'wp_ajax_cms_admin_register_push', array( __CLASS__, 'register_push_token' ) );
		add_action( 'wp_ajax_cms_admin_save_firebase_key', array( __CLASS__, 'save_firebase_key' ) );
		add_action( 'wp_ajax_cms_admin_test_push', array( __CLASS__, 'test_push' ) );
	}

	public static function register_page(): void {
		add_menu_page(
			__( 'CMS Admin App', 'cms-admin-app' ),
			__( 'Admin App', 'cms-admin-app' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-smartphone',
			3
		);
	}

	public static function print_manifest_link(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<link rel="manifest" href="%s">' . "\n" . '<meta name="theme-color" content="#1f5148">' . "\n",
			esc_url( home_url( '/?cms_admin_manifest=1' ) )
		);
	}

	public static function print_registration_script(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$worker_url = add_query_arg( array( 'cms_admin_sw' => '1', 'v' => self::VERSION ), home_url( '/' ) );
		?>
		<script>
		(() => {
			if (!('serviceWorker' in navigator)) return;
			window.addEventListener('load', () => {
				navigator.serviceWorker.register(<?php echo wp_json_encode( $worker_url ); ?>, { scope: '/' })
					.then((registration) => {
						window.cmsAdminAppRegistration = registration;
						document.dispatchEvent(new CustomEvent('cms-admin-app-ready'));
					})
					.catch(() => {});
			});
		})();
		</script>
		<?php
	}

	public static function serve_app_asset(): void {
		if ( isset( $_GET['cms_admin_manifest'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::serve_manifest();
		}

		if ( isset( $_GET['cms_admin_sw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::serve_service_worker();
		}
	}

	private static function serve_manifest(): void {
		$icons = array();
		foreach ( array( 192, 512 ) as $size ) {
			$url = get_site_icon_url( $size );
			if ( $url ) {
				$icons[] = array(
					'src'   => $url,
					'sizes' => $size . 'x' . $size,
					'type'  => 'image/png',
				);
			}
		}

		$manifest = array(
			'id'               => home_url( '/wp-admin/admin.php?page=' . self::PAGE_SLUG ),
			'name'             => 'Chattanooga Music Scene Admin',
			'short_name'       => 'CMS Admin',
			'description'      => 'Administrator tools for Chattanooga Music Scene.',
			'start_url'        => home_url( '/wp-admin/admin.php?page=' . self::PAGE_SLUG ),
			'scope'            => home_url( '/' ),
			'display'          => 'standalone',
			'background_color' => '#f4eedc',
			'theme_color'      => '#1f5148',
			'icons'            => $icons,
		);

		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES );
		exit;
	}

	private static function serve_service_worker(): void {
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		?>
		importScripts('https://www.gstatic.com/firebasejs/11.10.0/firebase-app-compat.js');
		importScripts('https://www.gstatic.com/firebasejs/11.10.0/firebase-messaging-compat.js');
		firebase.initializeApp(<?php echo wp_json_encode( self::firebase_config() ); ?>);
		const cmsMessaging = firebase.messaging();
		cmsMessaging.onBackgroundMessage((payload) => {
			const details = payload.data || {};
			return self.registration.showNotification(details.title || 'CMS Admin', {
				body: details.body || '',
				data: { url: details.link || <?php echo wp_json_encode( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?> }
			});
		});
		self.addEventListener('install', () => self.skipWaiting());
		self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
		self.addEventListener('fetch', () => {});
		self.addEventListener('notificationclick', (event) => {
			event.notification.close();
			const target = event.notification.data && event.notification.data.url
				? event.notification.data.url
				: <?php echo wp_json_encode( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>;
			event.waitUntil(clients.openWindow(target));
		});
		<?php
		exit;
	}

	private static function firebase_config(): array {
		return array(
			'apiKey'            => self::FIREBASE_API_KEY,
			'authDomain'        => self::FIREBASE_AUTH_DOMAIN,
			'projectId'         => self::FIREBASE_PROJECT_ID,
			'storageBucket'     => self::FIREBASE_STORAGE_BUCKET,
			'messagingSenderId' => self::FIREBASE_SENDER_ID,
			'appId'             => self::FIREBASE_APP_ID,
		);
	}

	public static function register_push_token(): void {
		check_ajax_referer( 'cms_admin_push', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Administrator access is required.', 'cms-admin-app' ) ), 403 );
		}

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( '' === $token || strlen( $token ) > 4096 ) {
			wp_send_json_error( array( 'message' => __( 'The notification token was invalid.', 'cms-admin-app' ) ), 400 );
		}

		$user_id = get_current_user_id();
		$tokens  = get_user_meta( $user_id, 'cms_admin_push_tokens', true );
		$tokens  = is_array( $tokens ) ? $tokens : array();
		$tokens[ hash( 'sha256', $token ) ] = array(
			'token'   => $token,
			'updated' => time(),
		);
		update_user_meta( $user_id, 'cms_admin_push_tokens', array_slice( $tokens, -10, 10, true ) );

		wp_send_json_success( array( 'message' => __( 'This administrator device is connected.', 'cms-admin-app' ) ) );
	}

	public static function save_firebase_key(): void {
		check_ajax_referer( 'cms_admin_push', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Administrator access is required.', 'cms-admin-app' ) ), 403 );
		}
		if ( empty( $_FILES['service_key']['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $_FILES['service_key']['error'] ) {
			wp_send_json_error( array( 'message' => __( 'Choose the Firebase JSON key file.', 'cms-admin-app' ) ), 400 );
		}

		$tmp = $_FILES['service_key']['tmp_name'];
		if ( ! is_uploaded_file( $tmp ) || filesize( $tmp ) > 20480 ) {
			wp_send_json_error( array( 'message' => __( 'The Firebase key file was invalid.', 'cms-admin-app' ) ), 400 );
		}
		$key = json_decode( (string) file_get_contents( $tmp ), true );
		if (
			! is_array( $key ) ||
			self::FIREBASE_PROJECT_ID !== ( $key['project_id'] ?? '' ) ||
			'service_account' !== ( $key['type'] ?? '' ) ||
			empty( $key['client_email'] ) ||
			empty( $key['private_key'] ) ||
			empty( $key['token_uri'] )
		) {
			wp_send_json_error( array( 'message' => __( 'This key does not belong to the CMS Admin Firebase project.', 'cms-admin-app' ) ), 400 );
		}

		update_option( 'cms_admin_firebase_service_key', wp_json_encode( $key ), false );
		delete_transient( 'cms_admin_firebase_access_token' );
		wp_send_json_success( array( 'message' => __( 'Firebase sending is securely connected.', 'cms-admin-app' ) ) );
	}

	private static function base64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	private static function access_token() {
		$cached = get_transient( 'cms_admin_firebase_access_token' );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		$key = json_decode( (string) get_option( 'cms_admin_firebase_service_key', '' ), true );
		if ( ! is_array( $key ) || empty( $key['client_email'] ) || empty( $key['private_key'] ) || empty( $key['token_uri'] ) ) {
			return new WP_Error( 'cms_admin_missing_key', __( 'Firebase sending has not been connected yet.', 'cms-admin-app' ) );
		}
		$now    = time();
		$header = self::base64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claims = self::base64url(
			wp_json_encode(
				array(
					'iss'   => $key['client_email'],
					'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
					'aud'   => $key['token_uri'],
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);
		$unsigned = $header . '.' . $claims;
		if ( ! openssl_sign( $unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'cms_admin_sign_failed', __( 'The Firebase key could not sign the request.', 'cms-admin-app' ) );
		}
		$response = wp_remote_post(
			$key['token_uri'],
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $unsigned . '.' . self::base64url( $signature ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $data['access_token'] ) ) {
			return new WP_Error( 'cms_admin_token_failed', __( 'Firebase rejected the sending credentials.', 'cms-admin-app' ) );
		}
		set_transient( 'cms_admin_firebase_access_token', $data['access_token'], 3300 );
		return $data['access_token'];
	}

	public static function test_push(): void {
		check_ajax_referer( 'cms_admin_push', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Administrator access is required.', 'cms-admin-app' ) ), 403 );
		}
		$tokens = get_user_meta( get_current_user_id(), 'cms_admin_push_tokens', true );
		$tokens = is_array( $tokens ) ? $tokens : array();
		if ( empty( $tokens ) ) {
			wp_send_json_error( array( 'message' => __( 'Enable notifications on an administrator device first.', 'cms-admin-app' ) ), 400 );
		}
		$access_token = self::access_token();
		if ( is_wp_error( $access_token ) ) {
			wp_send_json_error( array( 'message' => $access_token->get_error_message() ), 500 );
		}

		$sent = 0;
		foreach ( $tokens as $token_data ) {
			if ( empty( $token_data['token'] ) ) {
				continue;
			}
			$response = wp_remote_post(
				'https://fcm.googleapis.com/v1/projects/' . self::FIREBASE_PROJECT_ID . '/messages:send',
				array(
					'timeout' => 15,
					'headers' => array( 'Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'message' => array(
								'token'        => $token_data['token'],
								'data'         => array(
									'title' => 'CMS Admin',
									'body'  => 'Administrator notifications are connected.',
									'link'  => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
								),
							),
						)
					),
				)
			);
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				++$sent;
			}
		}
		if ( 0 === $sent ) {
			wp_send_json_error( array( 'message' => __( 'Firebase did not deliver the test notification.', 'cms-admin-app' ) ), 502 );
		}
		wp_send_json_success( array( 'message' => sprintf( _n( 'Test sent to %d administrator device.', 'Test sent to %d administrator devices.', $sent, 'cms-admin-app' ), $sent ) ) );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to use this app.', 'cms-admin-app' ) );
		}

		$post_counts     = wp_count_posts( 'post' );
		$event_counts    = post_type_exists( 'event' ) ? wp_count_posts( 'event' ) : null;
		$location_counts = post_type_exists( 'location' ) ? wp_count_posts( 'location' ) : null;
		$pending_posts   = isset( $post_counts->pending ) ? (int) $post_counts->pending : 0;
		$pending_events  = $event_counts && isset( $event_counts->pending ) ? (int) $event_counts->pending : 0;
		$pending_places  = $location_counts && isset( $location_counts->pending ) ? (int) $location_counts->pending : 0;
		$saved_tokens    = get_user_meta( get_current_user_id(), 'cms_admin_push_tokens', true );
		$push_connected  = is_array( $saved_tokens ) && ! empty( $saved_tokens );
		?>
		<div class="wrap cms-admin-app">
			<h1><?php esc_html_e( 'Chattanooga Music Scene Admin', 'cms-admin-app' ); ?></h1>
			<p><?php esc_html_e( 'An installable administrator dashboard. Only WordPress administrators can access these tools.', 'cms-admin-app' ); ?></p>

			<div class="cms-admin-cards">
				<?php self::render_card( __( 'Pending Events', 'cms-admin-app' ), $pending_events, admin_url( 'edit.php?post_status=pending&post_type=event' ) ); ?>
				<?php self::render_card( __( 'Pending Locations', 'cms-admin-app' ), $pending_places, admin_url( 'edit.php?post_status=pending&post_type=location' ) ); ?>
				<?php self::render_card( __( 'Pending Posts', 'cms-admin-app' ), $pending_posts, admin_url( 'edit.php?post_status=pending' ) ); ?>
			</div>

			<div class="card cms-admin-status">
				<h2><?php esc_html_e( 'App Status', 'cms-admin-app' ); ?></h2>
				<p id="cms-admin-install-status"><?php esc_html_e( 'Checking installation support…', 'cms-admin-app' ); ?></p>
				<button type="button" class="button button-primary" id="cms-admin-install" hidden><?php esc_html_e( 'Install Admin App', 'cms-admin-app' ); ?></button>
				<p><strong><?php esc_html_e( 'Push notifications:', 'cms-admin-app' ); ?></strong> <span id="cms-admin-push-status"><?php echo $push_connected ? esc_html__( 'An administrator device is connected.', 'cms-admin-app' ) : esc_html__( 'This device is not connected yet.', 'cms-admin-app' ); ?></span></p>
				<button type="button" class="button button-primary" id="cms-admin-enable-push"><?php echo $push_connected ? esc_html__( 'Refresh Admin Notifications', 'cms-admin-app' ) : esc_html__( 'Enable Admin Notifications', 'cms-admin-app' ); ?></button>
				<?php if ( $push_connected ) : ?>
					<p class="description"><?php esc_html_e( 'Only use this if notifications stop working or you change phones.', 'cms-admin-app' ); ?></p>
				<?php endif; ?>
				<hr>
				<p><strong><?php esc_html_e( 'Firebase sending:', 'cms-admin-app' ); ?></strong> <span id="cms-admin-key-status"><?php echo get_option( 'cms_admin_firebase_service_key' ) ? esc_html__( 'Connected.', 'cms-admin-app' ) : esc_html__( 'Private key not installed.', 'cms-admin-app' ); ?></span></p>
				<input type="file" id="cms-admin-service-key" accept="application/json,.json">
				<button type="button" class="button" id="cms-admin-save-key"><?php esc_html_e( 'Install Firebase Key', 'cms-admin-app' ); ?></button>
				<button type="button" class="button" id="cms-admin-test-push"><?php esc_html_e( 'Send Test Notification', 'cms-admin-app' ); ?></button>
			</div>
		</div>
		<style>
			.cms-admin-app{max-width:1000px}.cms-admin-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px;margin:24px 0}.cms-admin-card{background:#fff;border-left:5px solid #1f5148;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.12)}.cms-admin-card strong{display:block;font-size:2rem;color:#9a3324}.cms-admin-card a{text-decoration:none}.cms-admin-status{max-width:none;padding:22px}.cms-admin-app h1{color:#1f5148}
		</style>
		<script>
		(() => {
			let installPrompt;
			const button = document.getElementById('cms-admin-install');
			const status = document.getElementById('cms-admin-install-status');
			window.addEventListener('beforeinstallprompt', (event) => {
				event.preventDefault();
				installPrompt = event;
				button.hidden = false;
				status.textContent = 'This device can install the administrator app.';
			});
			button.addEventListener('click', async () => {
				if (!installPrompt) return;
				installPrompt.prompt();
				await installPrompt.userChoice;
				installPrompt = null;
				button.hidden = true;
			});
			window.addEventListener('appinstalled', () => {
				status.textContent = 'The administrator app is installed on this device.';
				button.hidden = true;
			});
			if (window.matchMedia('(display-mode: standalone)').matches) {
				status.textContent = 'Running as the installed administrator app.';
			}
		})();
		</script>
		<script type="module">
		import { initializeApp } from 'https://www.gstatic.com/firebasejs/11.10.0/firebase-app.js';
		import { getMessaging, getToken, isSupported, onMessage } from 'https://www.gstatic.com/firebasejs/11.10.0/firebase-messaging.js';

		const pushButton = document.getElementById('cms-admin-enable-push');
		const pushStatus = document.getElementById('cms-admin-push-status');
		const firebaseConfig = <?php echo wp_json_encode( self::firebase_config() ); ?>;
		const vapidKey = <?php echo wp_json_encode( self::FIREBASE_VAPID_KEY ); ?>;
		const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const nonce = <?php echo wp_json_encode( wp_create_nonce( 'cms_admin_push' ) ); ?>;
		const keyInput = document.getElementById('cms-admin-service-key');
		const keyButton = document.getElementById('cms-admin-save-key');
		const keyStatus = document.getElementById('cms-admin-key-status');
		const testButton = document.getElementById('cms-admin-test-push');

		pushButton.addEventListener('click', async () => {
			pushButton.disabled = true;
			pushStatus.textContent = 'Waiting for notification permission…';
			try {
				if (!(await isSupported())) throw new Error('Push notifications are not supported by this browser.');
				const permission = await Notification.requestPermission();
				if (permission !== 'granted') throw new Error('Notification permission was not granted.');
				const registration = window.cmsAdminAppRegistration || await navigator.serviceWorker.ready;
				const messaging = getMessaging(initializeApp(firebaseConfig));
				onMessage(messaging, (payload) => {
					const details = payload.data || payload.notification || {};
					registration.showNotification(details.title || 'CMS Admin', { body: details.body || '', data: { url: details.link } });
				});
				const token = await getToken(messaging, { vapidKey, serviceWorkerRegistration: registration });
				if (!token) throw new Error('Firebase did not return a notification token.');
				const body = new URLSearchParams({ action: 'cms_admin_register_push', nonce, token });
				const response = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body });
				const result = await response.json();
				if (!response.ok || !result.success) throw new Error(result?.data?.message || 'WordPress could not save this device.');
				pushStatus.textContent = result.data.message;
				pushButton.textContent = 'Admin Notifications Enabled';
			} catch (error) {
				pushStatus.textContent = error.message || 'This device could not be connected.';
				pushButton.disabled = false;
			}
		});

		keyButton.addEventListener('click', async () => {
			if (!keyInput.files.length) { keyStatus.textContent = 'Choose the Firebase JSON key file.'; return; }
			keyButton.disabled = true;
			const data = new FormData();
			data.append('action', 'cms_admin_save_firebase_key'); data.append('nonce', nonce); data.append('service_key', keyInput.files[0]);
			try {
				const response = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data });
				const result = await response.json();
				if (!response.ok || !result.success) throw new Error(result?.data?.message || 'The Firebase key could not be installed.');
				keyStatus.textContent = result.data.message;
				keyInput.value = '';
			} catch (error) { keyStatus.textContent = error.message; }
			keyButton.disabled = false;
		});

		testButton.addEventListener('click', async () => {
			testButton.disabled = true;
			try {
				const body = new URLSearchParams({ action: 'cms_admin_test_push', nonce });
				const response = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body });
				const result = await response.json();
				if (!response.ok || !result.success) throw new Error(result?.data?.message || 'The test could not be sent.');
				pushStatus.textContent = result.data.message;
			} catch (error) { pushStatus.textContent = error.message; }
			testButton.disabled = false;
		});
		</script>
		<?php
	}

	private static function render_card( string $label, int $count, string $url ): void {
		?>
		<div class="cms-admin-card">
			<strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong>
			<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
		</div>
		<?php
	}
}

CMS_Admin_App::init();
