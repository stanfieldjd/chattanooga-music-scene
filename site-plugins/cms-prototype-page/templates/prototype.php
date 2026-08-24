<?php
/**
 * Full-screen template for the Chattanooga Music Scene Prototype page.
 *
 * @package CMS_Prototype_Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'cms-prototype-page' ); ?>>
<?php wp_body_open(); ?>
<?php echo do_shortcode( '[cms_prototype_artwork]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php wp_footer(); ?>
</body>
</html>

