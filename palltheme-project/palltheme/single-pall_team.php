<?php
/**
 * Single Team Member.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$id       = get_the_ID();
	$position = (string) palltheme_meta( $id, 'position' );
	$email    = (string) palltheme_meta( $id, 'email' );
	$phone    = (string) palltheme_meta( $id, 'phone' );
	$skills   = palltheme_pairs( $id, 'skills' );
	$socials  = array_filter(
		array(
			'linkedin' => palltheme_meta( $id, 'linkedin' ),
			'x'        => palltheme_meta( $id, 'x' ),
			'facebook' => palltheme_meta( $id, 'facebook' ),
		)
	);
	palltheme_page_header( get_the_title(), '', $position );
	?>
	<main id="primary" class="pt-main pt-section">
		<div class="pt-container pt-team-single">
			<figure class="pt-team-single__photo pt-card">
				<?php if ( has_post_thumbnail() ) : ?>
					<?php the_post_thumbnail( 'palltheme-square', array( 'loading' => 'eager' ) ); ?>
				<?php else : ?>
					<span class="pt-media-fallback"><?php palltheme_the_icon( 'user' ); ?></span>
				<?php endif; ?>
				<figcaption>
					<?php if ( $email ) : ?>
						<a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php palltheme_the_icon( 'mail' ); ?><?php echo esc_html( antispambot( $email ) ); ?></a>
					<?php endif; ?>
					<?php if ( $phone ) : ?>
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php palltheme_the_icon( 'phone' ); ?><?php echo esc_html( $phone ); ?></a>
					<?php endif; ?>
					<?php if ( $socials ) : ?>
						<ul class="pt-social">
							<?php foreach ( $socials as $network => $url ) : ?>
								<li><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( palltheme_social_label( $network ) ); ?>"><?php echo palltheme_social_icon( $network ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</figcaption>
			</figure>
			<div>
				<div class="pt-prose entry-content"><?php the_content(); ?></div>
				<?php if ( $skills ) : ?>
					<h2 class="pt-section-title pt-mt"><?php esc_html_e( 'Expertise', 'palltheme' ); ?></h2>
					<ul class="pt-skills">
						<?php foreach ( $skills as $row ) : ?>
							<?php $pct = max( 0, min( 100, (int) ( $row[1] ?? 0 ) ) ); ?>
							<li>
								<span class="pt-skills__label"><?php echo esc_html( $row[0] ); ?><?php if ( $pct ) : ?><span><?php echo esc_html( $pct . '%' ); ?></span><?php endif; ?></span>
								<?php if ( $pct ) : ?>
									<span class="pt-skills__bar" role="presentation"><span style="width:<?php echo esc_attr( (string) $pct ); ?>%"></span></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</main>
	<?php
endwhile;

get_footer();
