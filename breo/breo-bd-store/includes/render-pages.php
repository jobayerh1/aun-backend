<?php
/**
 * Content pages (About, Contact, policies…) and the shop / category grid.
 */
defined( 'ABSPATH' ) || exit;

function breo_bd_render_page() {
	ob_start();
	while ( have_posts() ) :
		the_post();
		$slug  = get_post_meta( get_the_ID(), '_breo_page', true );
		$intro = has_excerpt() ? get_the_excerpt() : '';
		?>
		<article class="breo-page breo-page--<?php echo esc_attr( $slug ); ?>">
			<header class="breo-page__hero">
				<div class="breo-wrap breo-narrow">
					<p class="breo-eyebrow"><?php echo esc_html( in_array( $slug, array( 'about-breo' ), true ) ? 'Breo Bangladesh' : 'Help & policies' ); ?></p>
					<h1 class="breo-page__title"><?php the_title(); ?></h1>
					<?php if ( $intro ) : ?>
						<p class="breo-lead"><?php echo esc_html( $intro ); ?></p>
					<?php endif; ?>
				</div>
			</header>
			<div class="breo-wrap breo-narrow breo-prose">
				<?php the_content(); ?>
			</div>
			<?php if ( 'contact' !== $slug ) : ?>
				<div class="breo-wrap breo-narrow"><?php echo breo_bd_help_band( 'Hi Breo Bangladesh' ); // phpcs:ignore ?></div>
			<?php endif; ?>
		</article>
		<?php
	endwhile;
	return ob_get_clean();
}

function breo_bd_render_shop() {
	$term  = is_product_category() ? get_queried_object() : null;
	$title = $term ? $term->name : 'All products';
	$cats  = breo_bd_categories();
	ob_start();
	?>
	<div class="breo-shop">
		<header class="breo-page__hero">
			<div class="breo-wrap">
				<p class="breo-eyebrow">Breo Bangladesh</p>
				<h1 class="breo-page__title"><?php echo esc_html( $title ); ?></h1>
				<p class="breo-lead"><?php echo esc_html( $term && $term->description ? $term->description : 'Portable wellness for the neck, eyes, back and muscles, with official warranty and delivery across Bangladesh.' ); ?></p>
				<nav class="breo-chips" aria-label="Categories">
					<a class="<?php echo $term ? '' : 'is-active'; ?>" href="<?php echo esc_url( breo_bd_shop_url() ); ?>">All</a>
					<?php foreach ( $cats as $slug => $c ) :
						$t = get_term_by( 'slug', $slug, 'product_cat' );
						if ( ! $t || ! $t->count ) {
							continue;
						} ?>
						<a class="<?php echo ( $term && $term->term_id === $t->term_id ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $c[1] ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
		</header>
		<div class="breo-wrap breo-s--tight">
			<?php if ( have_posts() ) : ?>
				<div class="breo-grid breo-grid--4">
					<?php
					while ( have_posts() ) :
						the_post();
						$p = wc_get_product( get_the_ID() );
						if ( $p ) {
							echo breo_bd_product_card( $p ); // phpcs:ignore
						}
					endwhile;
					?>
				</div>
				<?php the_posts_pagination(); ?>
			<?php else : ?>
				<p class="breo-lead">No products here yet.</p>
			<?php endif; ?>
			<?php echo breo_bd_help_band( 'Hi Breo Bangladesh, I need help choosing a massager' ); // phpcs:ignore ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/* [breo_contact] — the Contact page cards. */
add_shortcode( 'breo_contact', function () {
	$cards = array();
	$wa    = breo_bd_whatsapp_url( 'Hi Breo Bangladesh' );
	if ( $wa ) {
		$cards[] = array( breo_bd_social_icon( 'whatsapp' ), 'WhatsApp', 'Fastest way to reach us, for orders, advice and warranty.', '<a class="breo-btn breo-btn--sm" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">Start a chat</a>' );
	}
	if ( breo_bd_opt( 'phone' ) ) {
		$cards[] = array( breo_bd_icon( 'phone', 26 ), 'Call us', esc_html( breo_bd_opt( 'phone' ) ), '<a class="breo-btn breo-btn--sm breo-btn--ghost" href="' . esc_url( breo_bd_tel( breo_bd_opt( 'phone' ) ) ) . '">Call now</a>' );
	}
	if ( breo_bd_opt( 'email' ) ) {
		$e       = breo_bd_opt( 'email' );
		$cards[] = array( breo_bd_icon( 'mail', 26 ), 'Email', antispambot( esc_html( $e ) ), '<a class="breo-btn breo-btn--sm breo-btn--ghost" href="mailto:' . esc_attr( $e ) . '">Send an email</a>' );
	}
	if ( breo_bd_opt( 'address' ) ) {
		$cards[] = array( breo_bd_icon( 'pin', 26 ), 'Visit', esc_html( breo_bd_opt( 'address' ) ), '<a class="breo-btn breo-btn--sm breo-btn--ghost" href="' . esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( breo_bd_opt( 'address' ) ) ) . '" target="_blank" rel="noopener">Open in Maps</a>' );
	}
	if ( ! $cards ) {
		return '<p><em>Contact details will appear here once they are added in Tools → Breo BD Setup.</em></p>';
	}
	$h = '<div class="breo-contact">';
	foreach ( $cards as $c ) {
		$h .= '<div class="breo-contact__card"><span class="breo-contact__ico">' . $c[0] . '</span><h3>' . esc_html( $c[1] ) . '</h3><p>' . $c[2] . '</p>' . $c[3] . '</div>';
	}
	$h .= '</div>';
	if ( breo_bd_opt( 'hours' ) ) {
		$h .= '<p class="breo-contact__hours">' . breo_bd_icon( 'clock', 18 ) . ' Support hours: ' . esc_html( breo_bd_opt( 'hours' ) ) . '</p>';
	}
	return $h;
} );

/* [breo_home] — kept so the Home page still renders if the canvas is bypassed. */
add_shortcode( 'breo_home', function () {
	return 'home' === breo_bd_view() ? '' : breo_bd_render_home();
} );
