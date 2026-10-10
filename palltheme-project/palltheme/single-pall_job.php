<?php
/**
 * Single Job Opening.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$id    = get_the_ID();
	$apply = (string) palltheme_meta( $id, 'apply_url' );
	$email = (string) palltheme_meta( $id, 'apply_email' ) ?: palltheme_biz( 'careers_email' );
	$href  = $apply ?: ( $email ? 'mailto:' . antispambot( $email ) . '?subject=' . rawurlencode( get_the_title() ) : '' );
	palltheme_page_header( get_the_title(), '', __( 'Careers', 'palltheme' ) );
	?>
	<main id="primary" class="pt-main pt-section">
		<div class="pt-container pt-split pt-split--content">
			<div class="pt-prose entry-content"><?php the_content(); ?></div>
			<aside class="pt-split__aside">
				<div class="pt-card pt-list-card">
					<dl class="pt-dl">
						<?php
						$facts = array(
							__( 'Department', 'palltheme' ) => palltheme_meta( $id, 'department' ),
							__( 'Location', 'palltheme' )   => palltheme_meta( $id, 'location' ),
							__( 'Job type', 'palltheme' )   => palltheme_meta( $id, 'job_type' ),
							__( 'Salary', 'palltheme' )     => palltheme_meta( $id, 'salary' ),
							__( 'Apply by', 'palltheme' )   => palltheme_meta( $id, 'deadline' ),
						);
						foreach ( $facts as $label => $value ) :
							if ( ! $value ) {
								continue;
							}
							?>
							<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( (string) $value ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
					<?php if ( $href ) : ?>
						<a class="pt-btn pt-btn--primary pt-btn--block" href="<?php echo esc_url( $href ); ?>"><?php esc_html_e( 'Apply now', 'palltheme' ); ?></a>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	</main>
	<?php
endwhile;

get_footer();
