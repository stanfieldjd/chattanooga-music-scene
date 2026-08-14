<?php
/**
 * Plugin Name: Chattanooga Music Scene Legal and Registration
 * Description: Terms links, signup agreement enforcement, and legacy legal-page routing for Chattanooga Music Scene.
 * Version: 1.0.0
 * Author: Chattanooga Music Scene
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMS_Site_Legal {
	private const TERMS_PATH = '/terms-and-conditions/';
	private const HELP_PAGE_ID = 7622;
	private const LEGACY_TERMS_PAGE_ID = 36;

	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_terms_page' ), 20 );
		add_filter( 'the_content', array( __CLASS__, 'append_help_terms_link' ), 20 );
		add_action( 'bp_before_registration_submit_buttons', array( __CLASS__, 'render_signup_terms_agreement' ) );
		add_action( 'bp_signup_validate', array( __CLASS__, 'validate_buddypress_terms_agreement' ) );
		add_action( 'register_form', array( __CLASS__, 'render_signup_terms_agreement' ) );
		add_filter( 'registration_errors', array( __CLASS__, 'validate_wordpress_terms_agreement' ), 10, 3 );
		add_action( 'woocommerce_register_form', array( __CLASS__, 'render_signup_terms_agreement' ) );
		add_filter( 'woocommerce_registration_errors', array( __CLASS__, 'validate_woocommerce_terms_agreement' ), 10, 3 );
	}

	private static function terms_url(): string {
		return home_url( self::TERMS_PATH );
	}

	public static function redirect_legacy_terms_page(): void {
		if ( is_page( self::LEGACY_TERMS_PAGE_ID ) ) {
			wp_safe_redirect( self::terms_url(), 301 );
			exit;
		}
	}

	public static function append_help_terms_link( string $content ): string {
		if ( ! is_page( self::HELP_PAGE_ID ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$link = sprintf(
			'<p class="cms-help-legal" style="margin-top:32px;padding-top:22px;border-top:2px solid #1f5148;font-weight:700">By using Chattanooga Music Scene or creating an account, you agree to the <a href="%s">Terms and Conditions</a>. The consolidated page covers accounts, community participation, events, marketplace listings, and store purchases.</p>',
			esc_url( self::terms_url() )
		);
		return $content . $link;
	}

	public static function render_signup_terms_agreement(): void {
		$checked = ! empty( $_POST['cms_terms_agreement'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		?>
		<p class="cms-signup-terms">
			<label>
				<input type="checkbox" name="cms_terms_agreement" value="1" <?php checked( $checked ); ?> required>
				I have read and agree to the <a href="<?php echo esc_url( self::terms_url() ); ?>" target="_blank" rel="noopener">Terms and Conditions</a>.
			</label>
		</p>
		<?php
	}

	private static function terms_were_accepted(): bool {
		return ! empty( $_POST['cms_terms_agreement'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public static function validate_buddypress_terms_agreement(): void {
		if ( self::terms_were_accepted() || ! function_exists( 'buddypress' ) ) {
			return;
		}
		buddypress()->signup->errors['cms_terms_agreement'] = 'You must agree to the Terms and Conditions to create an account.';
	}

	public static function validate_wordpress_terms_agreement( WP_Error $errors, string $sanitized_user_login, string $user_email ): WP_Error {
		unset( $sanitized_user_login, $user_email );
		if ( ! self::terms_were_accepted() ) {
			$errors->add( 'cms_terms_agreement_required', 'You must agree to the Terms and Conditions to create an account.' );
		}
		return $errors;
	}

	public static function validate_woocommerce_terms_agreement( WP_Error $errors, string $username, string $email ): WP_Error {
		unset( $username, $email );
		if ( ! self::terms_were_accepted() ) {
			$errors->add( 'cms_terms_agreement_required', 'You must agree to the Terms and Conditions to create an account.' );
		}
		return $errors;
	}
}

CMS_Site_Legal::init();
