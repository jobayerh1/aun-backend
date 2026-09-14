<?php
/**
 * Breo canvas: our header + one Breo view + our footer, independent of the
 * active theme (works the same on Extendable, Flatsome or any other).
 */
defined( 'ABSPATH' ) || exit;
$breo_view = breo_bd_view();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ( ! current_theme_supports( 'title-tag' ) && ! has_action( 'wp_head', '_block_template_render_title_tag' ) ) : ?>
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'breo-canvas breo-view-' . $breo_view ); ?>>
<?php
wp_body_open();
echo breo_bd_header_html(); // phpcs:ignore WordPress.Security.EscapeOutput
?>
<main id="breo-main" class="breo-main">
<?php
switch ( $breo_view ) {
	case 'home':
		echo breo_bd_render_home(); // phpcs:ignore WordPress.Security.EscapeOutput
		break;
	case 'product':
		echo breo_bd_render_product(); // phpcs:ignore WordPress.Security.EscapeOutput
		break;
	case 'page':
		echo breo_bd_render_page(); // phpcs:ignore WordPress.Security.EscapeOutput
		break;
	case 'shop':
		echo breo_bd_render_shop(); // phpcs:ignore WordPress.Security.EscapeOutput
		break;
}
?>
</main>
<?php
echo breo_bd_footer_html(); // phpcs:ignore WordPress.Security.EscapeOutput
wp_footer();
?>
</body>
</html>
