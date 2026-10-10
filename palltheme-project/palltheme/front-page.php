<?php
/**
 * Front page.
 *
 * Priority:
 * 1. A static homepage built with Elementor → rendered as-is.
 * 2. A static homepage with block/shortcode content → rendered full width.
 * 3. No static homepage (or empty) → the default dynamic homepage made of
 *    Palltheme Core sections (services, products, case studies...).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

if ( 'posts' === get_option( 'show_on_front' ) ) {
	get_template_part( 'index' );
	return;
}

get_header();

$page_id = (int) get_option( 'page_on_front' );
$content = $page_id ? trim( (string) get_post_field( 'post_content', $page_id ) ) : '';
?>
<main id="primary" class="pt-main pt-main--home">
	<?php
	if ( $page_id && ( palltheme_is_elementor_page( $page_id ) || '' !== $content ) ) {
		while ( have_posts() ) {
			the_post();
			the_content();
		}
	} elseif ( function_exists( 'pallcore_default_homepage' ) ) {
		echo pallcore_default_homepage(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
	} else {
		get_template_part( 'template-parts/content/home-fallback' );
	}
	?>
</main>
<?php
get_footer();
