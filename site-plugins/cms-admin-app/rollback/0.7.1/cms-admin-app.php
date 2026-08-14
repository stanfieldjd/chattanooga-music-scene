<?php
/**
 * Plugin Name: Chattanooga Music Scene Admin App
 * Description: Installable administrator dashboard and configurable phone notifications for Chattanooga Music Scene.
 * Version: 0.7.1
 * Author: Chattanooga Music Scene
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMS_Admin_App {
	private const VERSION = '0.7.1';
	private const PAGE_SLUG = 'cms-admin-app';
	private const OPTION_SETTINGS = 'cms_admin_notification_settings';
	private const OPTION_FIREBASE_KEY = 'cms_admin_firebase_service_key';
	private const TOKEN_META = 'cms_admin_push_tokens';
	private const MEMBER_SETTINGS_META = 'cms_member_notification_settings';
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
		add_action( 'wp_ajax_cms_member_disconnect_push', array( __CLASS__, 'disconnect_push_token' ) );
		add_action( 'wp_ajax_cms_member_test_push', array( __CLASS__, 'test_member_push' ) );
		add_action( 'wp_ajax_cms_admin_save_firebase_key', array( __CLASS__, 'save_firebase_key' ) );
		add_action( 'wp_ajax_cms_admin_test_push', array( __CLASS__, 'test_push' ) );
		add_action( 'admin_post_cms_admin_save_notifications', array( __CLASS__, 'save_notification_settings' ) );
		add_action( 'admin_post_cms_member_save_notifications', array( __CLASS__, 'save_member_notification_settings' ) );
		add_action( 'bp_setup_nav', array( __CLASS__, 'register_member_notifications_tab' ), 100 );
		add_shortcode( 'cms_site_help', array( __CLASS__, 'render_site_help' ) );
		add_shortcode( 'cms_member_notifications_help', array( __CLASS__, 'render_site_help' ) );

		add_action( 'user_register', array( __CLASS__, 'notice_user_registered' ) );
		add_action( 'profile_update', array( __CLASS__, 'notice_user_updated' ), 10, 2 );
		add_action( 'delete_user', array( __CLASS__, 'notice_user_deleted' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'notice_post_transition' ), 10, 3 );
		add_action( 'wp_insert_comment', array( __CLASS__, 'notice_comment_created' ), 10, 2 );
		add_action( 'transition_comment_status', array( __CLASS__, 'notice_comment_transition' ), 10, 3 );
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

	private static function member_catalog(): array {
		return array(
			'mentions' => 'Someone mentions me',
			'private_messages' => 'I receive a private message',
			'activity_replies' => 'Someone replies to my activity or comment',
			'follows' => 'Someone follows me or a followed member posts',
			'groups' => 'Group invitations, requests, approvals and updates',
			'forums' => 'New discussions and replies in subscribed forums',
			'connections' => 'Connection requests and acceptances',
			'post_replies' => 'Someone replies to my website post comment',
			'account' => 'Important changes to my account',
			'other_community' => 'Other community notifications',
		);
	}

	private static function member_settings( int $user_id ): array {
		$saved = get_user_meta( $user_id, self::MEMBER_SETTINGS_META, true );
		$saved = is_array( $saved ) ? $saved : array();
		$defaults = array_fill_keys( array_keys( self::member_catalog() ), true );
		return array(
			'master' => array_key_exists( 'master', $saved ) ? (bool) $saved['master'] : true,
			'events' => array_merge( $defaults, isset( $saved['events'] ) && is_array( $saved['events'] ) ? $saved['events'] : array() ),
		);
	}

	private static function member_event_enabled( int $user_id, string $event ): bool {
		$settings = self::member_settings( $user_id );
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

	public static function register_member_notifications_tab(): void {
		if ( ! is_user_logged_in() || ! function_exists( 'bp_core_new_subnav_item' ) ) {
			return;
		}
		bp_core_new_subnav_item(
			array(
				'name' => 'Phone Notifications',
				'slug' => 'phone-notifications',
				'parent_slug' => 'settings',
				'parent_url' => trailingslashit( bp_loggedin_user_domain() . 'settings' ),
				'screen_function' => array( __CLASS__, 'member_notifications_screen' ),
				'position' => 95,
				'user_has_access' => bp_is_my_profile(),
			)
		);
	}

	public static function member_notifications_screen(): void {
		if ( ! bp_is_my_profile() ) {
			bp_core_no_access();
			return;
		}
		add_action( 'bp_template_content', array( __CLASS__, 'render_member_notifications' ) );
		bp_core_load_template( 'members/single/plugins' );
	}

	public static function save_member_notification_settings(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'cms-admin-app' ) );
		}
		check_admin_referer( 'cms_member_save_notifications' );
		$user_id = get_current_user_id();
		$posted = isset( $_POST['events'] ) && is_array( $_POST['events'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['events'] ) ) : array();
		$selected = array();
		foreach ( self::member_catalog() as $key => $label ) {
			$selected[ $key ] = in_array( $key, $posted, true );
		}
		update_user_meta( $user_id, self::MEMBER_SETTINGS_META, array( 'master' => ! empty( $_POST['master'] ), 'events' => $selected ) );
		$url = function_exists( 'bp_loggedin_user_domain' ) ? trailingslashit( bp_loggedin_user_domain() . 'settings/phone-notifications' ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'settings-updated', '1', $url ) );
		exit;
	}

	public static function render_member_notifications(): void {
		$user_id = get_current_user_id();
		$settings = self::member_settings( $user_id );
		$tokens = get_user_meta( $user_id, self::TOKEN_META, true );
		$connected = is_array( $tokens ) && ! empty( $tokens );
		?>
		<div class="cms-member-notifications">
			<h2>Phone Notifications</h2>
			<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="bp-feedback success"><span class="bp-icon" aria-hidden="true"></span><p>Your phone-notification choices were saved.</p></div>
			<?php endif; ?>
			<p>Connect this device, then choose which community notices you want sent to your phone.</p>
			<div class="cms-member-device">
				<p><strong>Device status:</strong> <span id="cms-member-push-status"><?php echo $connected ? 'A device is connected to your account.' : 'No device is connected yet.'; ?></span></p>
				<button type="button" class="button" id="cms-member-enable-push"><?php echo $connected ? 'Connect or Refresh This Device' : 'Connect This Device'; ?></button>
				<button type="button" class="button" id="cms-member-test-push">Send Test to My Device</button>
				<button type="button" class="button" id="cms-member-disconnect-push">Disconnect All My Devices</button>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cms_member_save_notifications">
				<?php wp_nonce_field( 'cms_member_save_notifications' ); ?>
				<label class="cms-member-master"><input type="checkbox" name="master" value="1" <?php checked( $settings['master'] ); ?>> Enable phone notifications for my account</label>
				<div class="cms-member-choices">
				<?php foreach ( self::member_catalog() as $key => $label ) : ?>
					<label><input type="checkbox" name="events[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $settings['events'][ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
				</div>
				<p><button type="submit" class="button submit">Save Phone Notification Choices</button></p>
			</form>
		</div>
		<style>.cms-member-notifications{max-width:760px}.cms-member-device{padding:16px;border:1px solid #d6d6d6;margin:16px 0}.cms-member-master{display:block;font-weight:700;margin:18px 0}.cms-member-choices label{display:block;padding:9px 0;border-bottom:1px solid #eee}</style>
		<script type="module">
		import { initializeApp } from 'https://www.gstatic.com/firebasejs/11.10.0/firebase-app.js';
		import { getMessaging, getToken, isSupported } from 'https://www.gstatic.com/firebasejs/11.10.0/firebase-messaging.js';
		const connect=document.getElementById('cms-member-enable-push'),test=document.getElementById('cms-member-test-push'),disconnect=document.getElementById('cms-member-disconnect-push'),status=document.getElementById('cms-member-push-status');
		const config=<?php echo wp_json_encode( self::firebase_config() ); ?>,vapid=<?php echo wp_json_encode( self::FIREBASE_VAPID_KEY ); ?>,ajax=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,nonce=<?php echo wp_json_encode( wp_create_nonce( 'cms_admin_push' ) ); ?>;
		async function currentToken(){if(!(await isSupported()))throw Error('Phone notifications are not supported by this browser.');const permission=await Notification.requestPermission();if(permission!=='granted')throw Error('Notification permission was not granted.');const registration=window.cmsAdminAppRegistration||await navigator.serviceWorker.ready;return getToken(getMessaging(initializeApp(config)),{vapidKey:vapid,serviceWorkerRegistration:registration});}
		connect.onclick=async()=>{connect.disabled=true;try{const token=await currentToken(),response=await fetch(ajax,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams({action:'cms_admin_register_push',nonce,token})}),result=await response.json();if(!response.ok||!result.success)throw Error(result?.data?.message||'This device could not be connected.');status.textContent=result.data.message;}catch(error){status.textContent=error.message;}connect.disabled=false;};
		test.onclick=async()=>{test.disabled=true;try{const response=await fetch(ajax,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams({action:'cms_member_test_push',nonce})}),result=await response.json();if(!response.ok||!result.success)throw Error(result?.data?.message||'The test could not be sent.');status.textContent=result.data.message;}catch(error){status.textContent=error.message;}test.disabled=false;};
		disconnect.onclick=async()=>{if(!window.confirm('Disconnect every phone and browser from your notification account?'))return;disconnect.disabled=true;try{const response=await fetch(ajax,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams({action:'cms_member_disconnect_push',nonce})}),result=await response.json();if(!response.ok||!result.success)throw Error(result?.data?.message||'Your devices could not be disconnected.');status.textContent=result.data.message;}catch(error){status.textContent=error.message;}disconnect.disabled=false;};
		</script>
		<?php
	}

	public static function render_site_help(): string {
		$logged_in = is_user_logged_in();
		$settings_url = $logged_in && function_exists( 'bp_loggedin_user_domain' ) ? trailingslashit( bp_loggedin_user_domain() . 'settings/phone-notifications' ) : '';
		ob_start();
		?>
		<section class="cms-notification-help" aria-labelledby="cms-notification-help-title">
			<h1 id="cms-notification-help-title">Chattanooga Music Scene Help Center</h1>
			<p class="cms-help-lead">Learn how to use your account, find and submit events, connect with the community, use the marketplace, and install website notifications.</p>
			<nav class="cms-help-nav" aria-label="Help topics"><a href="#accounts">Accounts</a><a href="#profiles">Profiles</a><a href="#events">Events &amp; Venues</a><a href="#community">Community</a><a href="#marketplace">Marketplace</a><a href="#notifications">Notification App</a><a href="#privacy">Privacy</a><a href="#troubleshooting">Troubleshooting</a></nav>

			<div class="cms-help-grid cms-help-main-grid">
				<article id="accounts"><h2>Accounts and Signing In</h2><ul><li>Create an account from the Register page.</li><li>Confirm your email if the site requests verification.</li><li>Use the login page to sign in with your username, email, or an available social-login option.</li><li>Open your account settings to change your email, password, privacy choices, and notification preferences.</li></ul></article>
				<article id="profiles"><h2>Your Profile</h2><ul><li>Add a profile photo, cover image, biography, and the information you want other members to see.</li><li>Musicians, fans, and subscribers use the same community tools while keeping their selected profile identity.</li><li>Use the Members directory to find people, follow members, or request a connection.</li></ul></article>
				<article id="events"><h2>Events and Venues</h2><ul><li>Browse Events to find upcoming Chattanooga-area music and community events.</li><li>Open an event for its date, time, venue, description, and original ticket or information link.</li><li>Logged-in members with permission can use Edit Events or Edit Venues to submit information.</li><li>Submitted information may remain pending until an administrator reviews it.</li></ul></article>
				<article><h2>Posts, Photos, and Videos</h2><ul><li>Read site posts and community updates from the appropriate navigation pages.</li><li>Use activity feeds to share updates and reply to other members.</li><li>Upload only media you own or have permission to share.</li><li>Use comments and replies respectfully; moderation controls apply throughout the site.</li></ul></article>
				<article id="community"><h2>Groups, Forums, and Messages</h2><ul><li>Join groups that match your interests and follow their activity.</li><li>Use discussion forums for organized conversations and subscribe when you want updates.</li><li>Private messages are visible only to conversation participants.</li><li>Mentions, follows, invitations, requests, replies, and messages can generate notifications.</li></ul></article>
				<article id="marketplace"><h2>Chattanooga Music Marketplace</h2><ul><li>Browse listings without mixing the marketplace into the event calendar.</li><li>Logged-in members can place, edit, renew, and reply to ads when their account has permission.</li><li>Review the listing description, seller information, price, and terms carefully.</li><li>Use the checkout page only when a listing provides an approved purchase option.</li></ul></article>
			</div>

			<h2 id="notifications">Install the Member Notification App</h2>
			<p>Install Chattanooga Music Scene Notifications and receive only the community alerts you choose—even when the website is closed.</p>
			<?php if ( ! $logged_in ) : ?>
				<div class="cms-help-callout"><p>You must log in before connecting a phone to your account.</p><p><a class="cms-help-button" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log In to Continue</a></p></div>
			<?php else : ?>
				<div class="cms-help-actions">
					<button type="button" class="cms-help-button" id="cms-member-install-app">Install Notification App</button>
					<a class="cms-help-button cms-help-secondary" href="<?php echo esc_url( $settings_url ); ?>">Choose My Notifications</a>
				</div>
				<p id="cms-member-install-status" class="cms-help-status" role="status">Checking installation options for this device…</p>
			<?php endif; ?>

			<div class="cms-help-grid">
				<article><h2>Android or Chromebook</h2><ol><li>Tap <strong>Install Notification App</strong>.</li><li>Approve the installation message.</li><li>Open <strong>Choose My Notifications</strong>.</li><li>Tap <strong>Connect This Device</strong> and allow notifications.</li></ol></article>
				<article><h2>iPhone or iPad</h2><ol><li>Open this page in Safari.</li><li>Tap Safari’s <strong>Share</strong> button.</li><li>Choose <strong>Add to Home Screen</strong>, then tap <strong>Add</strong>.</li><li>Open the new CMS Notices icon, choose your notifications, connect the device, and allow notifications.</li></ol></article>
			</div>

			<h2>What You Can Choose</h2>
			<p>Mentions, private messages, activity replies, followed-member activity, groups, forums, connections, replies to your website comments, account notices, and other community notifications can each be turned on or off.</p>

			<h2>Will Notifications Work When the App Is Closed?</h2>
			<p>Yes. Background notifications work when another app is open, the screen is locked, or the Chattanooga Music Scene app is inactive. They will stop if notifications are disabled in the phone settings or the app is force-stopped.</p>

			<h2>Test or Disconnect a Phone</h2>
			<p>Open <strong>Choose My Notifications</strong>. Use <strong>Send Test to My Device</strong> to check delivery. Use <strong>Disconnect All My Devices</strong> to remove every phone and browser connected to your account.</p>

			<h2>Accessibility and Navigation</h2>
			<ul><li>Use the main menu to reach Events, Venues, Members, Groups, Forums, the Marketplace, and your account.</li><li>Browser zoom and the phone’s text-size settings can enlarge the website without changing your account.</li><li>Buttons and links on this help page use large text and strong contrast.</li><li>If a page is difficult to use with a screen reader, magnifier, keyboard, or touch device, report the page and the problem to the site administrator.</li></ul>

			<h2 id="troubleshooting">Troubleshooting</h2>
			<ul><li><strong>Cannot sign in:</strong> check the email or username, reset the password, and confirm the account’s email if required.</li><li><strong>Cannot find a feature:</strong> sign in first; some account, submission, messaging, and marketplace tools are hidden from logged-out visitors.</li><li><strong>Event or listing is not visible:</strong> it may still be awaiting review, expired, or filtered from the current view.</li><li><strong>Page looks outdated:</strong> refresh the page once and reopen it from the main navigation.</li><li><strong>Notification does not arrive:</strong> confirm phone permission, reconnect the device, verify the category is selected, and send a test.</li></ul>

			<h2 id="privacy">Privacy and Safety</h2>
			<p class="cms-help-privacy">Member phones receive only notifications assigned to that member’s account. Administrator alerts and another member’s notices are never included. Do not publish passwords, payment information, private addresses, or sensitive personal information in profiles, posts, messages, events, or marketplace listings.</p>
		</section>
		<style>.cms-notification-help{max-width:980px;margin:0 auto;font-size:1.12rem;line-height:1.65}.cms-notification-help h1{font-size:clamp(2rem,5vw,3.4rem);line-height:1.1}.cms-notification-help h2{font-size:1.55rem;margin-top:1.6em;scroll-margin-top:90px}.cms-help-lead{font-size:1.3rem}.cms-help-nav{display:flex;flex-wrap:wrap;gap:10px;margin:24px 0;padding:16px;background:#1f5148}.cms-help-nav a{color:#fff!important;font-weight:700;padding:7px 10px}.cms-help-actions{display:flex;flex-wrap:wrap;gap:14px;margin:24px 0 12px}.cms-help-button{display:inline-block;min-height:52px;padding:13px 22px;border:0;border-radius:6px;background:#1f5148;color:#fff!important;font-size:1.05rem;font-weight:700;text-decoration:none;cursor:pointer}.cms-help-secondary{background:#9a3324}.cms-help-status,.cms-help-callout,.cms-help-privacy{padding:16px;border-left:5px solid #1f5148;background:#f4eedc}.cms-help-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:26px}.cms-help-grid article{padding:20px;border:1px solid #d4cdbd;background:#fff}.cms-help-main-grid article{scroll-margin-top:90px}.cms-notification-help li{margin:.6em 0}@media(max-width:600px){.cms-help-actions{display:block}.cms-help-button{display:block;width:100%;margin:10px 0;text-align:center}.cms-help-nav{display:block}.cms-help-nav a{display:block}}</style>
		<?php if ( $logged_in ) : ?>
		<script>(()=>{let promptEvent;const button=document.getElementById('cms-member-install-app'),status=document.getElementById('cms-member-install-status');const standalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;if(standalone){status.textContent='The notification app is already installed on this device.';button.hidden=true;return;}window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();promptEvent=event;status.textContent='This device is ready to install the notification app.';});button.addEventListener('click',async()=>{if(!promptEvent){status.textContent=/iPhone|iPad|iPod/.test(navigator.userAgent)?'On iPhone or iPad, use Safari’s Share button and choose Add to Home Screen.':'Open your browser menu and choose Install app or Add to Home screen.';return;}promptEvent.prompt();const choice=await promptEvent.userChoice;status.textContent=choice.outcome==='accepted'?'Installation started. Open CMS Notices from your home screen.':'Installation was not completed.';promptEvent=null;});window.addEventListener('appinstalled',()=>{status.textContent='The notification app is installed.';button.hidden=true;});})();</script>
		<?php endif; ?>
		<?php
		return (string) ob_get_clean();
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
		if ( ! is_object( $notification ) || empty( $notification->user_id ) ) {
			return;
		}
		$user_id = (int) $notification->user_id;
		$component = isset( $notification->component_name ) ? sanitize_key( (string) $notification->component_name ) : '';
		$action = isset( $notification->component_action ) ? sanitize_key( (string) $notification->component_action ) : '';
		$event = self::map_member_event( $component, $action );
		if ( ! self::member_event_enabled( $user_id, $event ) ) {
			return;
		}
		$labels = self::member_catalog();
		$title = isset( $labels[ $event ] ) ? $labels[ $event ] : 'New community notification';
		$link = function_exists( 'bp_core_get_user_domain' ) ? trailingslashit( bp_core_get_user_domain( $user_id ) . 'notifications' ) : home_url( '/' );
		self::send_push( 'Chattanooga Music Scene', $title . '.', $link, $user_id );
	}

	private static function map_member_event( string $component, string $action ): string {
		$combined = $component . ' ' . $action;
		if ( false !== strpos( $combined, 'mention' ) ) {
			return 'mentions';
		}
		if ( false !== strpos( $combined, 'message' ) ) {
			return 'private_messages';
		}
		if ( false !== strpos( $combined, 'friend' ) || false !== strpos( $combined, 'connection' ) ) {
			return 'connections';
		}
		if ( false !== strpos( $combined, 'follow' ) ) {
			return 'follows';
		}
		if ( false !== strpos( $combined, 'group' ) ) {
			return 'groups';
		}
		if ( false !== strpos( $combined, 'forum' ) || false !== strpos( $combined, 'topic' ) || false !== strpos( $combined, 'bbp_' ) ) {
			return 'forums';
		}
		if ( false !== strpos( $combined, 'comment' ) && false !== strpos( $combined, 'post' ) ) {
			return 'post_replies';
		}
		if ( false !== strpos( $combined, 'activity' ) || false !== strpos( $combined, 'reply' ) ) {
			return 'activity_replies';
		}
		if ( false !== strpos( $combined, 'account' ) || false !== strpos( $combined, 'password' ) || false !== strpos( $combined, 'settings' ) ) {
			return 'account';
		}
		return 'other_community';
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
		if ( ! is_user_logged_in() ) {
			return;
		}
		$member_help = ! is_admin() && is_page( 'help' );
		$manifest = $member_help || ! current_user_can( 'manage_options' ) ? home_url( '/?cms_member_manifest=1' ) : home_url( '/?cms_admin_manifest=1' );
		printf( '<link rel="manifest" href="%s">' . "\n" . '<meta name="theme-color" content="#1f5148">' . "\n", esc_url( $manifest ) );
	}

	public static function print_registration_script(): void {
		if ( ! is_user_logged_in() ) {
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
		if ( isset( $_GET['cms_member_manifest'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::serve_member_manifest();
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

	private static function serve_member_manifest(): void {
		$icons = array();
		foreach ( array( 192, 512 ) as $size ) {
			$url = get_site_icon_url( $size );
			if ( $url ) {
				$icons[] = array( 'src' => $url, 'sizes' => $size . 'x' . $size, 'type' => 'image/png' );
			}
		}
		$start_url = is_user_logged_in() && function_exists( 'bp_loggedin_user_domain' ) ? trailingslashit( bp_loggedin_user_domain() . 'settings/phone-notifications' ) : home_url( '/phone-notifications-help/' );
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( array( 'id' => home_url( '/member-notifications-app' ), 'name' => 'Chattanooga Music Scene Notifications', 'short_name' => 'CMS Notices', 'description' => 'Community notifications for Chattanooga Music Scene members.', 'start_url' => $start_url, 'scope' => home_url( '/' ), 'display' => 'standalone', 'background_color' => '#f4eedc', 'theme_color' => '#1f5148', 'icons' => $icons ), JSON_UNESCAPED_SLASHES );
		exit;
	}

	private static function serve_service_worker(): void {
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		?>
		importScripts('https://www.gstatic.com/firebasejs/11.10.0/firebase-app-compat.js');
		importScripts('https://www.gstatic.com/firebasejs/11.10.0/firebase-messaging-compat.js');
		firebase.initializeApp(<?php echo wp_json_encode( self::firebase_config() ); ?>);const cmsMessaging=firebase.messaging();cmsMessaging.onBackgroundMessage(payload=>{const d=payload.data||{};return self.registration.showNotification(d.title||'Chattanooga Music Scene',{body:d.body||'',data:{url:d.link||<?php echo wp_json_encode( home_url( '/' ) ); ?>}});});self.addEventListener('install',()=>self.skipWaiting());self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));self.addEventListener('fetch',()=>{});self.addEventListener('notificationclick',e=>{e.notification.close();e.waitUntil(clients.openWindow((e.notification.data&&e.notification.data.url)||<?php echo wp_json_encode( home_url( '/' ) ); ?>));});
		<?php
		exit;
	}

	private static function firebase_config(): array {
		return array( 'apiKey' => self::FIREBASE_API_KEY, 'authDomain' => self::FIREBASE_AUTH_DOMAIN, 'projectId' => self::FIREBASE_PROJECT_ID, 'storageBucket' => self::FIREBASE_STORAGE_BUCKET, 'messagingSenderId' => self::FIREBASE_SENDER_ID, 'appId' => self::FIREBASE_APP_ID );
	}

	public static function register_push_token(): void {
		check_ajax_referer( 'cms_admin_push', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ), 403 );
		}
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( '' === $token || strlen( $token ) > 4096 ) {
			wp_send_json_error( array( 'message' => 'The notification token was invalid.' ), 400 );
		}
		$tokens = get_user_meta( get_current_user_id(), self::TOKEN_META, true );
		$tokens = is_array( $tokens ) ? $tokens : array();
		$tokens[ hash( 'sha256', $token ) ] = array( 'token' => $token, 'updated' => time() );
		update_user_meta( get_current_user_id(), self::TOKEN_META, array_slice( $tokens, -10, 10, true ) );
		wp_send_json_success( array( 'message' => current_user_can( 'manage_options' ) ? 'This administrator device is connected.' : 'This device is connected to your account.' ) );
	}

	public static function disconnect_push_token(): void {
		check_ajax_referer( 'cms_admin_push', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ), 403 );
		}
		delete_user_meta( get_current_user_id(), self::TOKEN_META );
		wp_send_json_success( array( 'message' => 'All devices are disconnected from your account.' ) );
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

	public static function test_member_push(): void {
		check_ajax_referer( 'cms_admin_push', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in.' ), 403 );
		}
		$user_id = get_current_user_id();
		$link = function_exists( 'bp_core_get_user_domain' ) ? trailingslashit( bp_core_get_user_domain( $user_id ) . 'settings/phone-notifications' ) : home_url( '/' );
		$sent = self::send_push( 'Chattanooga Music Scene', 'Your member phone notifications are connected.', $link, $user_id );
		$sent ? wp_send_json_success( array( 'message' => sprintf( 'Test sent to %d connected device(s).', $sent ) ) ) : wp_send_json_error( array( 'message' => 'Firebase did not deliver the member test notification.' ), 502 );
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
