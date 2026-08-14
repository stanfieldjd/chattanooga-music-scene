<?php
/**
 * Plugin Name: Chattanooga Music Scene Admin App
 * Description: Installable administrator dashboard and configurable phone notifications for Chattanooga Music Scene.
 * Version: 0.5.0
 * Author: Chattanooga Music Scene
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMS_Admin_App {
	private const VERSION = '0.5.0';
	private const PAGE_SLUG = 'cms-admin-app';
	private const OPTION_SETTINGS = 'cms_admin_notification_settings';
	private const OPTION_FIREBASE_KEY = 'cms_admin_firebase_service_key';
	private const TOKEN_META = 'cms_admin_push_tokens';
	private const FIREBASE_API_KEY = 'AIzaSyC-SaF0QTN2KzPgGfLlIQINMwzVnTiPRYI';
	private const FIREBASE_AUTH_DOMAIN = 'cms-admin-79199.firebaseapp.com';
	private const FIREBASE_PROJECT_ID = 'cms-admin-79199';
	private const FIREBASE_STORAGE_BUCKET = 'cms-admin-79199.firebasestorage.app';
	private const FIREBASE_SENDER_ID = '915180591918';
	private const FIREBASE_APP_ID = '1:915180591918:web:3df24111fda2f7b4c7b8bf';
	private const FIREBASE_VAPID_KEY = 'BLNRgCTdURre4SI2XvFQTruaRxDVHSOTLKlAvdOnmm8-yQIqli5TC-ebLkxh6fwjFNy--O0WTZd94IPB6EL1D_s';

	private static array $request_dedupe = array();

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
		add_action( 'admin_post_cms_admin_save_notifications', array( __CLASS__, 'save_notification_settings' ) );

		add_action( 'user_register', array( __CLASS__, 'notice_user_registered' ) );
		add_action( 'profile_update', array( __CLASS__, 'notice_user_updated' ), 10, 2 );
		add_action( 'delete_user', array( __CLASS__, 'notice_user_deleted' ) );
		add_action( 'wp_login_failed', array( __CLASS__, 'notice_login_failed' ) );
		add_action( 'wp_login', array( __CLASS__, 'notice_login_success' ), 10, 2 );
		add_action( 'password_reset', array( __CLASS__, 'notice_password_reset' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'notice_post_transition' ), 10, 3 );
		add_action( 'wp_insert_comment', array( __CLASS__, 'notice_comment_created' ), 10, 2 );
		add_action( 'transition_comment_status', array( __CLASS__, 'notice_comment_transition' ), 10, 3 );
		add_action( 'add_attachment', array( __CLASS__, 'notice_media_added' ) );
		add_action( 'delete_attachment', array( __CLASS__, 'notice_media_deleted' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'notice_update_complete' ), 10, 2 );
		add_action( 'automatic_updates_complete', array( __CLASS__, 'notice_automatic_updates' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'notice_mail_failed' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'notice_order_status' ), 10, 4 );
		add_action( 'bp_notification_after_save', array( __CLASS__, 'notice_buddyboss' ) );
		add_action( 'wordfence_security_event', array( __CLASS__, 'notice_wordfence' ), 10, 2 );
		add_action( 'great_imports_run_failed', array( __CLASS__, 'notice_great_imports_failed' ), 10, 2 );
		add_action( 'great_imports_review_required', array( __CLASS__, 'notice_great_imports_review' ), 10, 2 );
		add_action( 'cms_admin_app_notify', array( __CLASS__, 'receive_custom_notification' ), 10, 4 );
	}

	private static function catalog(): array {
		return array(
			'Users and access' => array(
				'user_registered' => 'New user registered',
				'user_updated' => 'User profile changed',
				'user_deleted' => 'User deleted',
				'login_failed' => 'Failed administrator login',
				'login_success' => 'Successful administrator login',
				'password_reset' => 'Administrator password reset',
			),
			'Content' => array(
				'post_pending' => 'Post submitted for review',
				'post_published' => 'Post published',
				'post_updated' => 'Published post updated',
				'post_trashed' => 'Post moved to trash',
				'page_pending' => 'Page submitted for review',
				'page_published' => 'Page published',
				'page_updated' => 'Published page updated',
				'page_trashed' => 'Page moved to trash',
				'event_pending' => 'Event submitted for review',
				'event_published' => 'Event published',
				'event_updated' => 'Published event updated',
				'event_trashed' => 'Event moved to trash',
				'location_pending' => 'Location submitted for review',
				'location_published' => 'Location published',
				'location_updated' => 'Published location updated',
				'location_trashed' => 'Location moved to trash',
				'product_pending' => 'Product submitted for review',
				'product_published' => 'Product published',
				'product_updated' => 'Published product updated',
				'product_trashed' => 'Product moved to trash',
				'other_content' => 'Other content-type status change',
			),
			'Comments and community' => array(
				'comment_pending' => 'Comment awaiting moderation',
				'comment_approved' => 'Comment approved',
				'comment_spam' => 'Comment marked as spam',
				'comment_trashed' => 'Comment moved to trash',
				'buddyboss_notification' => 'BuddyBoss notification created',
			),
			'Media' => array(
				'media_added' => 'Media uploaded',
				'media_deleted' => 'Media deleted',
			),
			'Updates and delivery' => array(
				'plugin_updated' => 'Plugin update completed',
				'theme_updated' => 'Theme update completed',
				'core_updated' => 'WordPress core update completed',
				'automatic_update_failed' => 'Automatic update failed',
				'mail_failed' => 'Website email failed',
			),
			'Commerce' => array(
				'order_pending' => 'Order became pending',
				'order_processing' => 'Order is processing',
				'order_completed' => 'Order completed',
				'order_failed' => 'Order failed',
				'order_cancelled' => 'Order cancelled',
				'order_refunded' => 'Order refunded',
				'order_other' => 'Other order status change',
			),
			'Security and integrations' => array(
				'wordfence_security' => 'Wordfence security event',
				'great_imports_failed' => 'Great Imports run failed',
				'great_imports_review' => 'Great Imports needs review',
				'custom_plugin_notice' => 'Notice emitted by another plugin',
			),
		);
	}

	private static function defaults(): array {
		$enabled = array();
		foreach ( self::catalog() as $events ) {
			foreach ( $events as $key => $label ) {
				$enabled[ $key ] = true;
			}
		}
		return array( 'master' => true, 'events' => $enabled );
	}

	private static function settings(): array {
		$saved = get_option( self::OPTION_SETTINGS, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$defaults = self::defaults();
		return array(
			'master' => array_key_exists( 'master', $saved ) ? (bool) $saved['master'] : true,
			'events' => array_merge( $defaults['events'], isset( $saved['events'] ) && is_array( $saved['events'] ) ? $saved['events'] : array() ),
		);
	}

	private static function enabled( string $event ): bool {
		$settings = self::settings();
		return $settings['master'] && ! empty( $settings['events'][ $event ] );
	}

	public static function register_page(): void {
		add_menu_page( 'CMS Admin App', 'Admin App', 'manage_options', self::PAGE_SLUG, array( __CLASS__, 'render_page' ), 'dashicons-smartphone', 3 );
	}

	public static function save_notification_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator access is required.', 'cms-admin-app' ) );
		}
		check_admin_referer( 'cms_admin_save_notifications' );
		$posted = isset( $_POST['events'] ) && is_array( $_POST['events'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['events'] ) ) : array();
		$valid = array();
		foreach ( self::catalog() as $events ) {
			$valid += $events;
		}
		$selected = array();
		foreach ( $valid as $key => $label ) {
			$selected[ $key ] = in_array( $key, $posted, true );
		}
		update_option( self::OPTION_SETTINGS, array( 'master' => ! empty( $_POST['master'] ), 'events' => $selected ), false );
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'settings-updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function notify( string $event, string $title, string $body, string $link = '' ): void {
		if ( ! self::enabled( $event ) ) {
			return;
		}
		$fingerprint = md5( $event . '|' . $title . '|' . $body . '|' . $link );
		if ( isset( self::$request_dedupe[ $fingerprint ] ) ) {
			return;
		}
		self::$request_dedupe[ $fingerprint ] = true;
		self::send_push( $title, $body, $link );
	}

	private static function send_push( string $title, string $body, string $link = '', ?int $user_id = null ): int {
		$access_token = self::access_token();
		if ( is_wp_error( $access_token ) ) {
			return 0;
		}
		$users = null === $user_id ? get_users( array( 'role' => 'administrator', 'fields' => 'ids' ) ) : array( $user_id );
		$sent = 0;
		foreach ( $users as $admin_id ) {
			$tokens = get_user_meta( $admin_id, self::TOKEN_META, true );
			if ( ! is_array( $tokens ) ) {
				continue;
			}
			foreach ( $tokens as $token_data ) {
				if ( empty( $token_data['token'] ) ) {
					continue;
				}
				$response = wp_remote_post(
					'https://fcm.googleapis.com/v1/projects/' . self::FIREBASE_PROJECT_ID . '/messages:send',
					array(
						'timeout' => 15,
						'headers' => array( 'Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'application/json' ),
						'body' => wp_json_encode( array( 'message' => array( 'token' => $token_data['token'], 'data' => array( 'title' => wp_strip_all_tags( $title ), 'body' => wp_strip_all_tags( $body ), 'link' => $link ?: admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ) ) ),
					)
				);
				if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
					++$sent;
				}
			}
		}
		return $sent;
	}

	public static function notice_user_registered( int $user_id ): void {
		$user = get_userdata( $user_id );
		self::notify( 'user_registered', 'New website user', $user ? $user->display_name . ' registered.' : 'A new user registered.', admin_url( 'user-edit.php?user_id=' . $user_id ) );
	}

	public static function notice_user_updated( int $user_id, $old_user_data ): void {
		$user = get_userdata( $user_id );
		self::notify( 'user_updated', 'User profile changed', $user ? $user->display_name . ' was updated.' : 'A user profile was updated.', admin_url( 'user-edit.php?user_id=' . $user_id ) );
	}

	public static function notice_user_deleted( int $user_id ): void {
		$user = get_userdata( $user_id );
		self::notify( 'user_deleted', 'Website user deleted', $user ? $user->display_name . ' was deleted.' : 'A website user was deleted.', admin_url( 'users.php' ) );
	}

	public static function notice_login_failed( string $username ): void {
		$user = get_user_by( 'login', $username );
		if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) {
			self::notify( 'login_failed', 'Failed administrator login', 'A login attempt failed for an administrator account.', admin_url() );
		}
	}

	public static function notice_login_success( string $username, $user ): void {
		if ( $user instanceof WP_User && in_array( 'administrator', (array) $user->roles, true ) ) {
			self::notify( 'login_success', 'Administrator signed in', $user->display_name . ' signed in.', admin_url() );
		}
	}

	public static function notice_password_reset( $user ): void {
		if ( $user instanceof WP_User && in_array( 'administrator', (array) $user->roles, true ) ) {
			self::notify( 'password_reset', 'Administrator password reset', $user->display_name . "'s password was reset.", admin_url( 'users.php' ) );
		}
	}

	public static function notice_post_transition( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		$types = array( 'post', 'page', 'event', 'location', 'product' );
		$type = in_array( $post->post_type, $types, true ) ? $post->post_type : 'other';
		$verb = '';
		if ( 'pending' === $new_status ) {
			$verb = 'pending';
		} elseif ( 'publish' === $new_status && 'publish' !== $old_status ) {
			$verb = 'published';
		} elseif ( 'publish' === $new_status && 'publish' === $old_status ) {
			$verb = 'updated';
		} elseif ( 'trash' === $new_status ) {
			$verb = 'trashed';
		}
		if ( '' === $verb ) {
			if ( 'other' !== $type ) {
				return;
			}
			$event = 'other_content';
		} else {
			$event = 'other' === $type ? 'other_content' : $type . '_' . $verb;
		}
		$label = get_post_type_object( $post->post_type );
		$label = $label ? $label->labels->singular_name : 'Content';
		self::notify( $event, $label . ' status changed', '“' . get_the_title( $post ) . '” is now ' . $new_status . '.', get_edit_post_link( $post->ID, 'raw' ) ?: admin_url() );
	}

	public static function notice_comment_created( int $comment_id, $comment ): void {
		$status = wp_get_comment_status( $comment );
		$event = 'approved' === $status ? 'comment_approved' : 'comment_pending';
		self::notify( $event, 'New comment', 'A comment from ' . $comment->comment_author . ' is ' . $status . '.', admin_url( 'comment.php?action=editcomment&c=' . $comment_id ) );
	}

	public static function notice_comment_transition( string $new_status, string $old_status, $comment ): void {
		$map = array( 'approved' => 'comment_approved', 'spam' => 'comment_spam', 'trash' => 'comment_trashed', 'unapproved' => 'comment_pending' );
		if ( isset( $map[ $new_status ] ) ) {
			self::notify( $map[ $new_status ], 'Comment status changed', 'A comment from ' . $comment->comment_author . ' is now ' . $new_status . '.', admin_url( 'comment.php?action=editcomment&c=' . $comment->comment_ID ) );
		}
	}

	public static function notice_media_added( int $attachment_id ): void {
		self::notify( 'media_added', 'Media uploaded', get_the_title( $attachment_id ) ?: 'A media file was uploaded.', get_edit_post_link( $attachment_id, 'raw' ) ?: admin_url( 'upload.php' ) );
	}

	public static function notice_media_deleted( int $attachment_id ): void {
		self::notify( 'media_deleted', 'Media deleted', get_the_title( $attachment_id ) ?: 'A media file was deleted.', admin_url( 'upload.php' ) );
	}

	public static function notice_update_complete( $upgrader, array $options ): void {
		$type = isset( $options['type'] ) ? sanitize_key( $options['type'] ) : '';
		$event = in_array( $type, array( 'plugin', 'theme', 'core' ), true ) ? $type . '_updated' : '';
		if ( $event ) {
			self::notify( $event, ucfirst( $type ) . ' update completed', 'A ' . $type . ' update completed.', admin_url( 'update-core.php' ) );
		}
	}

	public static function notice_automatic_updates( array $results ): void {
		foreach ( $results as $items ) {
			if ( ! is_array( $items ) ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( isset( $item->result ) && is_wp_error( $item->result ) ) {
					self::notify( 'automatic_update_failed', 'Automatic update failed', $item->result->get_error_message(), admin_url( 'update-core.php' ) );
				}
			}
		}
	}

	public static function notice_mail_failed( $error ): void {
		self::notify( 'mail_failed', 'Website email failed', $error instanceof WP_Error ? $error->get_error_message() : 'WordPress could not send an email.', admin_url( 'admin.php?page=wp-mail-smtp' ) );
	}

	public static function notice_order_status( int $order_id, string $old_status, string $new_status, $order ): void {
		$known = array( 'pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded' );
		$event = in_array( $new_status, $known, true ) ? 'order_' . $new_status : 'order_other';
		self::notify( $event, 'Order status changed', 'Order #' . $order_id . ' changed from ' . $old_status . ' to ' . $new_status . '.', admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id ) );
	}

	public static function notice_buddyboss( $notification ): void {
		self::notify( 'buddyboss_notification', 'BuddyBoss notification', 'A new community notification was created.', admin_url( 'admin.php?page=bp-notices' ) );
	}

	public static function notice_wordfence( $event, $data = array() ): void {
		self::notify( 'wordfence_security', 'Wordfence security notice', is_scalar( $event ) ? (string) $event : 'Wordfence reported a security event.', admin_url( 'admin.php?page=Wordfence' ) );
	}

	public static function notice_great_imports_failed( $source = '', $message = '' ): void {
		self::notify( 'great_imports_failed', 'Great Imports run failed', $message ? (string) $message : 'A Great Imports source run failed.', admin_url( 'admin.php?page=great-imports' ) );
	}

	public static function notice_great_imports_review( $source = '', $count = 0 ): void {
		self::notify( 'great_imports_review', 'Great Imports needs review', $count ? (int) $count . ' imported items need review.' : 'Imported items need review.', admin_url( 'admin.php?page=great-imports' ) );
	}

	public static function receive_custom_notification( $title, $body = '', $link = '', $event = 'custom_plugin_notice' ): void {
		self::notify( sanitize_key( (string) $event ) ?: 'custom_plugin_notice', (string) $title, (string) $body, (string) $link );
	}

	public static function print_manifest_link(): void {
		if ( current_user_can( 'manage_options' ) ) {
			printf( '<link rel="manifest" href="%s">' . "\n" . '<meta name="theme-color" content="#1f5148">' . "\n", esc_url( home_url( '/?cms_admin_manifest=1' ) ) );
		}
	}

	public static function print_registration_script(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$worker_url = add_query_arg( array( 'cms_admin_sw' => '1', 'v' => self::VERSION ), home_url( '/' ) );
		?>
		<script>(()=>{if(!('serviceWorker'in navigator))return;window.addEventListener('load',()=>{navigator.serviceWorker.register(<?php echo wp_json_encode( $worker_url ); ?>,{scope:'/'}).then(r=>{window.cmsAdminAppRegistration=r;document.dispatchEvent(new CustomEvent('cms-admin-app-ready'));}).catch(()=>{});});})();</script>
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
				$icons[] = array( 'src' => $url, 'sizes' => $size . 'x' . $size, 'type' => 'image/png' );
			}
		}
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( array( 'id' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ), 'name' => 'Chattanooga Music Scene Admin', 'short_name' => 'CMS Admin', 'description' => 'Administrator tools for Chattanooga Music Scene.', 'start_url' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ), 'scope' => home_url( '/' ), 'display' => 'standalone', 'background_color' => '#f4eedc', 'theme_color' => '#1f5148', 'icons' => $icons ), JSON_UNESCAPED_SLASHES );
		exit;
	}

	private static function serve_service_worker(): void {
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		?>
		importScripts('https://www.gstatic.com/firebasejs/11.10.0/firebase-app-compat.js');
		importScripts('https://www.gstatic.com/firebasejs/11.10.0/firebase-messaging-compat.js');
		firebase.initializeApp(<?php echo wp_json_encode( self::firebase_config() ); ?>);const cmsMessaging=firebase.messaging();cmsMessaging.onBackgroundMessage(payload=>{const d=payload.data||{};return self.registration.showNotification(d.title||'CMS Admin',{body:d.body||'',data:{url:d.link||<?php echo wp_json_encode( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>}});});self.addEventListener('install',()=>self.skipWaiting());self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));self.addEventListener('fetch',()=>{});self.addEventListener('notificationclick',e=>{e.notification.close();e.waitUntil(clients.openWindow((e.notification.data&&e.notification.data.url)||<?php echo wp_json_encode( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>));});
		<?php
		exit;
	}

	private static function firebase_config(): array {
		return array( 'apiKey' => self::FIREBASE_API_KEY, 'authDomain' => self::FIREBASE_AUTH_DOMAIN, 'projectId' => self::FIREBASE_PROJECT_ID, 'storageBucket' => self::FIREBASE_STORAGE_BUCKET, 'messagingSenderId' => self::FIREBASE_SENDER_ID, 'appId' => self::FIREBASE_APP_ID );
	}

	public static function register_push_token(): void {
		check_ajax_referer( 'cms_admin_push', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Administrator access is required.' ), 403 );
		}
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( '' === $token || strlen( $token ) > 4096 ) {
			wp_send_json_error( array( 'message' => 'The notification token was invalid.' ), 400 );
		}
		$tokens = get_user_meta( get_current_user_id(), self::TOKEN_META, true );
		$tokens = is_array( $tokens ) ? $tokens : array();
		$tokens[ hash( 'sha256', $token ) ] = array( 'token' => $token, 'updated' => time() );
		update_user_meta( get_current_user_id(), self::TOKEN_META, array_slice( $tokens, -10, 10, true ) );
		wp_send_json_success( array( 'message' => 'This administrator device is connected.' ) );
	}

	public static function save_firebase_key(): void {
		check_ajax_referer( 'cms_admin_push', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Administrator access is required.' ), 403 );
		}
		if ( empty( $_FILES['service_key']['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $_FILES['service_key']['error'] ) {
			wp_send_json_error( array( 'message' => 'Choose the Firebase JSON key file.' ), 400 );
		}
		$tmp = $_FILES['service_key']['tmp_name'];
		if ( ! is_uploaded_file( $tmp ) || filesize( $tmp ) > 20480 ) {
			wp_send_json_error( array( 'message' => 'The Firebase key file was invalid.' ), 400 );
		}
		$key = json_decode( (string) file_get_contents( $tmp ), true );
		if ( ! is_array( $key ) || self::FIREBASE_PROJECT_ID !== ( $key['project_id'] ?? '' ) || 'service_account' !== ( $key['type'] ?? '' ) || empty( $key['client_email'] ) || empty( $key['private_key'] ) || empty( $key['token_uri'] ) ) {
			wp_send_json_error( array( 'message' => 'This key does not belong to the CMS Admin Firebase project.' ), 400 );
		}
		update_option( self::OPTION_FIREBASE_KEY, wp_json_encode( $key ), false );
		delete_transient( 'cms_admin_firebase_access_token' );
		wp_send_json_success( array( 'message' => 'Firebase sending is securely connected.' ) );
	}

	private static function base64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	private static function access_token() {
		$cached = get_transient( 'cms_admin_firebase_access_token' );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		$key = json_decode( (string) get_option( self::OPTION_FIREBASE_KEY, '' ), true );
		if ( ! is_array( $key ) || empty( $key['client_email'] ) || empty( $key['private_key'] ) || empty( $key['token_uri'] ) ) {
			return new WP_Error( 'cms_admin_missing_key', 'Firebase sending has not been connected yet.' );
		}
		$now = time();
		$header = self::base64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claims = self::base64url( wp_json_encode( array( 'iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => $key['token_uri'], 'iat' => $now, 'exp' => $now + 3600 ) ) );
		$unsigned = $header . '.' . $claims;
		if ( ! openssl_sign( $unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'cms_admin_sign_failed', 'The Firebase key could not sign the request.' );
		}
		$response = wp_remote_post( $key['token_uri'], array( 'timeout' => 15, 'body' => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . self::base64url( $signature ) ) ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $data['access_token'] ) ) {
			return new WP_Error( 'cms_admin_token_failed', 'Firebase rejected the sending credentials.' );
		}
		set_transient( 'cms_admin_firebase_access_token', $data['access_token'], 3300 );
		return $data['access_token'];
	}

	public static function test_push(): void {
		check_ajax_referer( 'cms_admin_push', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Administrator access is required.' ), 403 );
		}
		$sent = self::send_push( 'CMS Admin', 'Administrator notifications are connected.', admin_url( 'admin.php?page=' . self::PAGE_SLUG ), get_current_user_id() );
		$sent ? wp_send_json_success( array( 'message' => sprintf( 'Test sent to %d administrator device(s).', $sent ) ) ) : wp_send_json_error( array( 'message' => 'Firebase did not deliver the test notification.' ), 502 );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to use this app.', 'cms-admin-app' ) );
		}
		$post_counts = wp_count_posts( 'post' );
		$event_counts = post_type_exists( 'event' ) ? wp_count_posts( 'event' ) : null;
		$location_counts = post_type_exists( 'location' ) ? wp_count_posts( 'location' ) : null;
		$settings = self::settings();
		$saved_tokens = get_user_meta( get_current_user_id(), self::TOKEN_META, true );
		$push_connected = is_array( $saved_tokens ) && ! empty( $saved_tokens );
		?>
		<div class="wrap cms-admin-app">
			<h1>Chattanooga Music Scene Admin</h1>
			<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p>Notification choices saved.</p></div>
			<?php endif; ?>
			<p>An installable administrator dashboard. Only WordPress administrators can access these tools.</p>
			<div class="cms-admin-cards">
				<?php self::render_card( 'Pending Events', $event_counts && isset( $event_counts->pending ) ? (int) $event_counts->pending : 0, admin_url( 'edit.php?post_status=pending&post_type=event' ) ); ?>
				<?php self::render_card( 'Pending Locations', $location_counts && isset( $location_counts->pending ) ? (int) $location_counts->pending : 0, admin_url( 'edit.php?post_status=pending&post_type=location' ) ); ?>
				<?php self::render_card( 'Pending Posts', isset( $post_counts->pending ) ? (int) $post_counts->pending : 0, admin_url( 'edit.php?post_status=pending' ) ); ?>
			</div>
			<div class="card cms-admin-status"><h2>App Status</h2><p id="cms-admin-install-status">Checking installation support…</p><button type="button" class="button button-primary" id="cms-admin-install" hidden>Install Admin App</button><p><strong>Push notifications:</strong> <span id="cms-admin-push-status"><?php echo $push_connected ? 'An administrator device is connected.' : 'This device is not connected yet.'; ?></span></p><button type="button" class="button button-primary" id="cms-admin-enable-push"><?php echo $push_connected ? 'Refresh Admin Notifications' : 'Enable Admin Notifications'; ?></button><?php if ( $push_connected ) : ?><p class="description">Only use this if notifications stop working or you change phones.</p><?php endif; ?><hr><p><strong>Firebase sending:</strong> <span id="cms-admin-key-status"><?php echo get_option( self::OPTION_FIREBASE_KEY ) ? 'Connected.' : 'Private key not installed.'; ?></span></p><input type="file" id="cms-admin-service-key" accept="application/json,.json"><button type="button" class="button" id="cms-admin-save-key">Install Firebase Key</button> <button type="button" class="button" id="cms-admin-test-push">Send Test Notification</button></div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="card cms-admin-notifications">
				<h2>Phone Notification Choices</h2><p>Everything is enabled initially. Turn off any notice you do not want sent to administrator phones.</p>
				<input type="hidden" name="action" value="cms_admin_save_notifications"><?php wp_nonce_field( 'cms_admin_save_notifications' ); ?>
				<label class="cms-master"><input type="checkbox" name="master" value="1" <?php checked( $settings['master'] ); ?>> Enable phone notifications</label>
				<p><button type="button" class="button" id="cms-select-all">Select All</button> <button type="button" class="button" id="cms-select-none">Deselect All</button></p>
				<div class="cms-notification-groups">
				<?php foreach ( self::catalog() as $group => $events ) : ?><fieldset><legend><?php echo esc_html( $group ); ?></legend><label class="cms-group-toggle"><input type="checkbox" data-group-toggle> Select this group</label><?php foreach ( $events as $key => $label ) : ?><label><input class="cms-event-choice" type="checkbox" name="events[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $settings['events'][ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></fieldset><?php endforeach; ?>
				</div><p><button type="submit" class="button button-primary">Save Notification Choices</button></p>
			</form>
		</div>
		<style>.cms-admin-app{max-width:1100px}.cms-admin-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px;margin:24px 0}.cms-admin-card{background:#fff;border-left:5px solid #1f5148;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.12)}.cms-admin-card strong{display:block;font-size:2rem;color:#9a3324}.cms-admin-card a{text-decoration:none}.cms-admin-status,.cms-admin-notifications{max-width:none;padding:22px}.cms-admin-app h1{color:#1f5148}.cms-master{display:block;font-size:1.1rem;font-weight:700;margin:16px 0}.cms-notification-groups{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}.cms-notification-groups fieldset{border:1px solid #c3c4c7;padding:14px;background:#fff}.cms-notification-groups legend{font-weight:700;padding:0 6px}.cms-notification-groups label{display:block;margin:8px 0}.cms-notification-groups .cms-group-toggle{border-bottom:1px solid #ddd;padding-bottom:8px;font-weight:600}</style>
		<script>(()=>{let p;const b=document.getElementById('cms-admin-install'),s=document.getElementById('cms-admin-install-status');window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();p=e;b.hidden=false;s.textContent='This device can install the administrator app.';});b.addEventListener('click',async()=>{if(!p)return;p.prompt();await p.userChoice;p=null;b.hidden=true;});if(window.matchMedia('(display-mode: standalone)').matches){s.textContent='Running as the installed administrator app.';b.hidden=true;}const choices=[...document.querySelectorAll('.cms-event-choice')];document.getElementById('cms-select-all').onclick=()=>choices.forEach(c=>c.checked=true);document.getElementById('cms-select-none').onclick=()=>choices.forEach(c=>c.checked=false);document.querySelectorAll('[data-group-toggle]').forEach(t=>t.onchange=()=>t.closest('fieldset').querySelectorAll('.cms-event-choice').forEach(c=>c.checked=t.checked));})();</script>
		<script type="module">import{initializeApp}from'https://www.gstatic.com/firebasejs/11.10.0/firebase-app.js';import{getMessaging,getToken,isSupported,onMessage}from'https://www.gstatic.com/firebasejs/11.10.0/firebase-messaging.js';const b=document.getElementById('cms-admin-enable-push'),s=document.getElementById('cms-admin-push-status'),k=document.getElementById('cms-admin-service-key'),kb=document.getElementById('cms-admin-save-key'),ks=document.getElementById('cms-admin-key-status'),tb=document.getElementById('cms-admin-test-push'),a=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,n=<?php echo wp_json_encode( wp_create_nonce( 'cms_admin_push' ) ); ?>,config=<?php echo wp_json_encode( self::firebase_config() ); ?>,vapid=<?php echo wp_json_encode( self::FIREBASE_VAPID_KEY ); ?>;b.onclick=async()=>{b.disabled=true;s.textContent='Waiting for notification permission…';try{if(!(await isSupported()))throw Error('Push notifications are not supported by this browser.');if(await Notification.requestPermission()!=='granted')throw Error('Notification permission was not granted.');const r=window.cmsAdminAppRegistration||await navigator.serviceWorker.ready,m=getMessaging(initializeApp(config));onMessage(m,p=>{const d=p.data||p.notification||{};r.showNotification(d.title||'CMS Admin',{body:d.body||'',data:{url:d.link}});});const t=await getToken(m,{vapidKey:vapid,serviceWorkerRegistration:r}),q=await fetch(a,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams({action:'cms_admin_register_push',nonce:n,token:t})}),j=await q.json();if(!q.ok||!j.success)throw Error(j?.data?.message||'WordPress could not save this device.');s.textContent=j.data.message;b.textContent='Admin Notifications Enabled';}catch(e){s.textContent=e.message||'This device could not be connected.';b.disabled=false;}};kb.onclick=async()=>{if(!k.files.length){ks.textContent='Choose the Firebase JSON key file.';return;}kb.disabled=true;const d=new FormData();d.append('action','cms_admin_save_firebase_key');d.append('nonce',n);d.append('service_key',k.files[0]);try{const q=await fetch(a,{method:'POST',credentials:'same-origin',body:d}),j=await q.json();if(!q.ok||!j.success)throw Error(j?.data?.message||'The Firebase key could not be installed.');ks.textContent=j.data.message;k.value='';}catch(e){ks.textContent=e.message;}kb.disabled=false;};tb.onclick=async()=>{tb.disabled=true;try{const q=await fetch(a,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams({action:'cms_admin_test_push',nonce:n})}),j=await q.json();if(!q.ok||!j.success)throw Error(j?.data?.message||'The test could not be sent.');s.textContent=j.data.message;}catch(e){s.textContent=e.message;}tb.disabled=false;};</script>
		<?php
	}

	private static function render_card( string $label, int $count, string $url ): void {
		printf( '<div class="cms-admin-card"><strong>%s</strong><a href="%s">%s</a></div>', esc_html( number_format_i18n( $count ) ), esc_url( $url ), esc_html( $label ) );
	}
}

CMS_Admin_App::init();
