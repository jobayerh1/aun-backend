<?php
/**
 * Content pages (About, Contact, policies…) and the shop / category grid.
 * Each page gets an image hero, "at a glance" cards built from the business
 * settings, the editable page content, and (About) brand sections.
 */
defined( 'ABSPATH' ) || exit;

/** Hero image + summary cards per page. Values come from the settings. */
function breo_bd_page_decor( $slug ) {
	$w    = breo_bd_warranty_period();
	$cod  = false !== stripos( breo_bd_opt( 'payments' ), 'cash' );
	$days = function ( $key, $unit ) {
		$v = breo_bd_opt( $key );
		return '' === $v ? '' : $v . ' ' . $unit;
	};
	$decor = array(
		'about-breo'        => array( 'img' => 'site:about_hero', 'eyebrow' => 'Breo Bangladesh' ),
		'contact'           => array( 'img' => 'N990000631:gift', 'eyebrow' => 'We\'re here to help' ),
		'faq'               => array( 'img' => 'N990000631:stretch', 'eyebrow' => 'Help centre', 'chips' => array(
			'orders'   => 'Orders & payment',
			'delivery' => 'Delivery',
			'products' => 'Products & usage',
			'warranty' => 'Warranty & returns',
		) ),
		'warranty-policy'   => array( 'img' => 'N990000631:core', 'glance' => array(
			array( 'badge', $w ? ucfirst( $w ) : '', 'official warranty' ),
			array( 'tools', 'Repair or replace', 'for manufacturing defects' ),
			array( 'chat', 'Local support', 'claims handled in Bangladesh' ),
			array( 'check', 'No reset', 'the remaining warranty carries over' ),
		) ),
		'shipping-delivery' => array( 'img' => 'N910200212:boxed', 'glance' => array(
			array( 'truck', $days( 'dhaka_days', 'days' ), 'delivery inside Dhaka' ),
			array( 'truck', $days( 'outside_days', 'days' ), 'delivery outside Dhaka' ),
			array( 'clock', breo_bd_opt( 'cutoff' ), 'same-day order cut-off' ),
			array( 'cash', $cod ? 'Cash on Delivery' : '', 'pay when it arrives' ),
		) ),
		'returns-refunds'   => array( 'img' => 'N990000631:ondesk', 'glance' => array(
			array( 'box', $days( 'return_faulty_days', 'days' ), 'to report damage or a fault' ),
			array( 'return', $days( 'return_unopened_hours', 'hours' ), 'for unopened change-of-mind returns' ),
			array( 'cash', $days( 'refund_days', 'days' ), 'refund after inspection' ),
			array( 'chat', 'Ask first', 'contact us before sending anything back' ),
		) ),
		'privacy-policy'    => array( 'img' => 'site:ocean', 'glance' => array(
			array( 'shield', 'Never sold', 'we don\'t sell your data' ),
			array( 'lock', 'Encrypted', 'HTTPS on every page' ),
			array( 'user', 'Your choice', 'view, correct or delete your data' ),
			array( 'mail', 'Opt-in only', 'for marketing messages' ),
		) ),
		'terms-conditions'  => array( 'img' => 'N910200212:night', 'glance' => array(
			array( 'shield', 'Genuine', 'officially imported Breo' ),
			array( 'badge', 'Warranty', 'handled locally' ),
			array( 'pin', 'Bangladesh', 'governed by local law' ),
			array( 'chat', 'Talk to us', 'we resolve issues first' ),
		) ),
		// Pages created by Breo Live Tracking / Breo EMI Plans.
		'track-order'       => array( 'img' => 'N910200212:boxed', 'eyebrow' => 'Order status', 'glance' => array(
			array( 'truck', 'Live updates', 'straight from Pathao Courier' ),
			array( 'search', 'Order no. or phone', 'either one finds your order' ),
			array( 'lock', 'Private', 'personal details stay partly hidden' ),
			array( 'chat', 'Need help?', 'message us any time' ),
		) ),
		'emi-plans'         => array( 'img' => 'N990000631:desk', 'eyebrow' => 'Easy payments' ),
	);
	return isset( $decor[ $slug ] ) ? $decor[ $slug ] : array( 'img' => 'N990000631:desk' );
}

function breo_bd_render_page() {
	ob_start();
	while ( have_posts() ) :
		the_post();
		$slug  = get_post_meta( get_the_ID(), '_breo_page', true );
		$slug  = $slug ? $slug : breo_bd_plugin_page_slug( get_the_ID() );
		$intro = has_excerpt() ? get_the_excerpt() : '';
		$dec   = breo_bd_page_decor( $slug );
		$img   = ! empty( $dec['img'] ) ? breo_bd_img_ref( $dec['img'], array( 'size' => 'full', 'loading' => 'eager', 'sizes' => '(max-width: 900px) 100vw, 50vw' ) ) : '';
		?>
		<article class="breo-page breo-page--<?php echo esc_attr( $slug ); ?>">
			<header class="breo-phero<?php echo $img ? '' : ' no-media'; ?>">
				<div class="breo-phero__text">
					<div class="breo-phero__in" data-reveal>
						<p class="breo-eyebrow"><?php echo esc_html( isset( $dec['eyebrow'] ) ? $dec['eyebrow'] : 'Help & policies' ); ?></p>
						<h1 class="breo-page__title"><?php the_title(); ?></h1>
						<?php if ( $intro ) : ?>
							<p class="breo-lead"><?php echo esc_html( $intro ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $dec['chips'] ) ) : ?>
							<nav class="breo-chips" aria-label="Jump to">
								<?php foreach ( $dec['chips'] as $id => $label ) : ?>
									<a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></a>
								<?php endforeach; ?>
							</nav>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( $img ) : ?>
					<div class="breo-phero__media"><?php echo $img; // phpcs:ignore ?></div>
				<?php endif; ?>
			</header>

			<?php
			$cards = array_filter( isset( $dec['glance'] ) ? $dec['glance'] : array(), function ( $c ) {
				return '' !== trim( (string) $c[1] );
			} );
			if ( $cards ) :
				?>
				<section class="breo-glance" aria-label="At a glance">
					<div class="breo-wrap breo-glance__grid">
						<?php foreach ( $cards as $c ) : ?>
							<div class="breo-glance__card" data-reveal>
								<?php echo breo_bd_icon( $c[0], 26 ); // phpcs:ignore ?>
								<p class="breo-glance__v"><?php echo esc_html( $c[1] ); ?></p>
								<p class="breo-glance__l"><?php echo esc_html( $c[2] ); ?></p>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( 'about-breo' === $slug ) : ?>
				<?php echo breo_bd_about_stats(); // phpcs:ignore ?>
			<?php endif; ?>

			<div class="breo-wrap breo-narrow breo-prose">
				<?php the_content(); ?>
			</div>

			<?php if ( 'about-breo' === $slug ) : ?>
				<?php echo breo_bd_about_sections(); // phpcs:ignore ?>
			<?php endif; ?>

			<?php if ( 'contact' !== $slug ) : ?>
				<div class="breo-wrap breo-narrow breo-page__help"><?php echo breo_bd_help_band( 'Hi Breo Bangladesh' ); // phpcs:ignore ?></div>
			<?php endif; ?>
		</article>
		<?php
	endwhile;
	return ob_get_clean();
}

/* ---------- About: brand numbers (Breo's published figures) ---------- */
function breo_bd_about_stats() {
	$s = array(
		'type'  => 'stats',
		'theme' => 'dark',
		'items' => array(
			array( '2000', '', 'Founded in Shenzhen, China' ),
			array( '10M+', '', 'Breo customers worldwide' ),
			array( '200+', '', 'Breo store locations worldwide' ),
			array( '100+', '', 'International design & innovation awards' ),
		),
	);
	return str_replace( '</section>', '<p class="breo-wrap breo-stats__src">Figures published by Breo.</p></section>', breo_bd_sec_stats( null, null, $s ) );
}

/* ---------- About: tradition, the wider Breo range, products here ---------- */
function breo_bd_about_sections() {
	$h = '';

	$trad = breo_bd_img_ref( 'site:tradition', array( 'size' => 'full', 'sizes' => '(max-width: 900px) 100vw, 50vw' ) );
	if ( $trad ) {
		$h .= '<section class="breo-s breo-split theme-cream media-left"><div class="breo-split__media">' . $trad . '</div>'
			. '<div class="breo-split__text" data-reveal><div class="breo-split__in">'
			. '<p class="breo-eyebrow">Tradition, engineered</p><h2 class="breo-title">Old wisdom, new technology</h2>'
			. '<p class="breo-lead">Breo\'s designs start from time-tested techniques like acupressure, kneading and warm compresses, then recreate them with quiet motors, precise sensors and soft materials, so a few minutes of real relief fit into an ordinary day.</p>'
			. '</div></div></section>';
	}

	$fam = array( 'fam_neck' => 'Neck', 'fam_eyes' => 'Eyes', 'fam_scalp' => 'Scalp', 'fam_head' => 'Head', 'fam_feet' => 'Feet' );
	$row = '';
	foreach ( $fam as $k => $label ) {
		$img = breo_bd_img_ref( 'site:' . $k, array( 'size' => 'large', 'sizes' => '(max-width: 900px) 62vw, 240px' ) );
		if ( $img ) {
			$row .= '<figure class="breo-family__item" data-reveal>' . $img . '<figcaption>' . esc_html( $label ) . '</figcaption></figure>';
		}
	}
	if ( $row ) {
		$h .= '<section class="breo-s breo-family"><div class="breo-wrap">'
			. '<header class="breo-sec-head" data-reveal><p class="breo-eyebrow">The Breo range</p><h2 class="breo-title">One brand, head to toe</h2>'
			. '<p class="breo-lead">Around the world, Breo makes devices for the scalp, head, eyes, neck, back and feet. We are starting with four and will bring more to Bangladesh.</p></header>'
			. '<div class="breo-family__row">' . $row . '</div></div></section>';
	}

	$ps = breo_bd_products();
	if ( $ps ) {
		$h .= '<section class="breo-s breo-popular breo-popular--about"><div class="breo-wrap">'
			. '<header class="breo-sec-head" data-reveal><p class="breo-eyebrow">Now in Bangladesh</p><h2 class="breo-title">Our launch collection</h2></header>'
			. '<div class="breo-popular__grid">';
		foreach ( $ps as $p ) {
			$h .= breo_bd_ptile( $p );
		}
		$h .= '</div></div></section>';
	}
	return $h;
}

/** Product tile used on the homepage and the About page. */
function breo_bd_ptile( $p ) {
	$d   = breo_bd_product_data( $p );
	$cat = breo_bd_categories()[ $d['category'] ];
	return '<a class="breo-ptile" href="' . esc_url( $p->get_permalink() ) . '" style="--accent:' . esc_attr( $d['accent'] ) . '" data-reveal>'
		. '<span class="breo-ptile__for">For ' . esc_html( strtolower( $cat[1] ) ) . '</span>'
		. '<span class="breo-ptile__img">' . breo_bd_media( $p, 'tile', array( 'size' => 'woocommerce_single', 'sizes' => '(max-width: 700px) 50vw, 300px' ) ) . '</span>'
		. '<span class="breo-ptile__name">' . esc_html( $d['short_name'] ) . '</span>'
		. '<span class="breo-ptile__sub">' . esc_html( $d['tagline'] ) . '</span>'
		. '<span class="breo-ptile__price">' . wp_kses_post( breo_bd_price_html( $p ) ) . '</span>'
		. '</a>';
}

function breo_bd_render_shop() {
	$term  = is_product_category() ? get_queried_object() : null;
	$title = $term ? $term->name : 'All Breo massagers';
	$seo   = function_exists( 'breo_bd_category_seo' ) ? breo_bd_category_seo() : array();
	$seo   = isset( $seo[ $term ? $term->slug : '' ] ) ? $seo[ $term ? $term->slug : '' ] : null;
	$cats  = breo_bd_categories();
	ob_start();
	?>
	<div class="breo-shop">
		<header class="breo-page__hero">
			<div class="breo-wrap">
				<p class="breo-eyebrow">Breo Bangladesh</p>
				<h1 class="breo-page__title"><?php echo esc_html( $title ); ?></h1>
				<p class="breo-lead"><?php echo esc_html( $seo ? $seo['lead'] : ( $term && $term->description ? $term->description : 'Portable wellness for the neck, eyes, back and muscles, with official warranty and delivery across Bangladesh.' ) ); ?></p>
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
		</div>
		<?php echo $seo ? breo_bd_category_guide_html( $term ) : ''; // phpcs:ignore ?>
		<div class="breo-wrap">
			<?php echo breo_bd_help_band( 'Hi Breo Bangladesh, I need help choosing a massager' ); // phpcs:ignore ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/** Branded "page not found" with a way back into the shop. */
function breo_bd_render_404() {
	$h  = '<div class="breo-404"><header class="breo-page__hero"><div class="breo-wrap breo-narrow">';
	$h .= '<p class="breo-eyebrow">Error 404</p><h1 class="breo-page__title">This page is taking a day off</h1>';
	$h .= '<p class="breo-lead">The page you are looking for has moved or doesn\'t exist. These might help instead.</p>';
	$h .= '<div class="breo-404__btns"><a class="breo-btn" href="' . esc_url( home_url( '/' ) ) . '">Go to the homepage</a><a class="breo-btn breo-btn--ghost" href="' . esc_url( breo_bd_shop_url() ) . '">All products</a></div>';
	$h .= '</div></header>';
	$ps = breo_bd_products();
	if ( $ps ) {
		$h .= '<section class="breo-s breo-popular"><div class="breo-wrap"><div class="breo-popular__grid">';
		foreach ( $ps as $p ) {
			$h .= breo_bd_ptile( $p );
		}
		$h .= '</div></div></section>';
	}
	return $h . '</div>';
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
	$map = function_exists( 'breo_bd_maps_link' ) ? breo_bd_maps_link() : '';
	if ( breo_bd_opt( 'address' ) || $map ) {
		$where = breo_bd_opt( 'address' ) ? esc_html( breo_bd_opt( 'address' ) ) : 'Find us on Google Maps';
		$cta   = $map ? '<a class="breo-btn breo-btn--sm breo-btn--ghost" href="' . esc_url( $map ) . '" target="_blank" rel="noopener">Open in Maps</a>' : '';
		$cards[] = array( breo_bd_icon( 'pin', 26 ), 'Visit', $where, $cta );
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
