<?php
/**
 * Hero band for Service / Solution / Case Study / Team singles.
 *
 * Args: eyebrow (string), meta (array label=>value), image (bool, default true).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$id      = get_the_ID();
$eyebrow = $args['eyebrow'] ?? '';
$meta    = $args['meta'] ?? array();
$cta_url = palltheme_meta( $id, 'cta_url' ) ?: palltheme_biz( 'quote_url', home_url( '/contact/' ) );
?>
<header class="pt-single-hero">
	<div class="pt-page-header__bg" aria-hidden="true"></div>
	<div class="pt-container pt-single-hero__inner">
		<div class="pt-single-hero__content">
			<?php palltheme_breadcrumbs(); ?>
			<div class="pt-single-hero__eyebrow">
				<span class="pt-icon-tile"><?php echo wp_kses( palltheme_content_icon( $id ), palltheme_svg_kses() ); ?></span>
				<?php if ( $eyebrow ) : ?>
					<span class="pt-eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
				<?php endif; ?>
			</div>
			<h1 class="pt-single-hero__title"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="pt-single-hero__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
			<?php if ( $meta ) : ?>
				<dl class="pt-single-hero__meta">
					<?php foreach ( $meta as $label => $value ) : ?>
						<?php if ( $value ) : ?>
							<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
						<?php endif; ?>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
			<div class="pt-btn-row">
				<a class="pt-btn pt-btn--primary" href="<?php echo esc_url( $cta_url ); ?>"><?php esc_html_e( 'Talk to an expert', 'palltheme' ); ?><?php palltheme_the_icon( 'arrow' ); ?></a>
			</div>
		</div>
		<?php if ( has_post_thumbnail() && ( $args['image'] ?? true ) ) : ?>
			<figure class="pt-single-hero__media">
				<?php the_post_thumbnail( 'palltheme-card', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
			</figure>
		<?php endif; ?>
	</div>
</header>
