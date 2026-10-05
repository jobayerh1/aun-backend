<?php
/**
 * Header, footer and the small shared blocks.
 *
 * Copy from content.php is developer-authored and carries deliberate HTML
 * entities, so it is echoed as-is. Anything that comes from the settings
 * screen is escaped at the point of output.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function sl_header() {
	$current = sl_current_page();
	?>
	<a class="sl-skip" href="#sl-main">Skip to content</a>
	<header class="hdr" id="sl-hdr">
		<div class="wrap hdr__in">
			<a class="hdr__logo" href="<?php echo esc_url( sl_url( 'home' ) ); ?>">
				<img src="<?php echo esc_url( sl_img( 'sl-logo.webp' ) ); ?>" alt="Smart Living Bangladesh" width="500" height="132">
			</a>
			<nav class="sl-nav" id="sl-nav" aria-label="Main">
				<?php
				foreach ( sl_pages() as $key => $page ) {
					if ( 'contact' === $key ) {
						continue;
					}
					printf(
						'<a class="sl-nav__a" href="%s"%s>%s</a>',
						esc_url( sl_url( $key ) ),
						$current === $key ? ' aria-current="page"' : '',
						$page['nav']
					);
				}
				printf(
					'<a class="btn btn--gold hdr__cta" href="%s"%s>Contact</a>',
					esc_url( sl_url( 'contact' ) ),
					'contact' === $current ? ' aria-current="page"' : ''
				);
				?>
			</nav>
			<button class="burger" id="sl-burger" aria-label="Menu" aria-expanded="false" aria-controls="sl-nav"><span></span></button>
		</div>
	</header>
	<?php
}

function sl_footer() {
	$o = sl_opt();
	?>
	<footer class="ftr">
		<canvas class="ftr__canvas" data-trace aria-hidden="true"></canvas>
		<div class="wrap">
			<div class="ftr__top">
				<div>
					<img class="mark" src="<?php echo esc_url( sl_img( 'sl-logo.webp' ) ); ?>" alt="Smart Living Bangladesh" width="500" height="132" loading="lazy">
					<p class="ftr__p">An import and distribution company in Dhaka, bringing smart projection, contemporary bags and wellness technology to Bangladesh &mdash; and supporting them long after the sale.</p>
				</div>
				<div>
					<div class="ftr__h">Company</div>
					<ul class="ftr__l">
						<?php
						foreach ( array( 'about', 'brands', 'corporate', 'dealers', 'contact' ) as $key ) {
							$pages = sl_pages();
							printf(
								'<li><a href="%s">%s</a></li>',
								esc_url( sl_url( $key ) ),
								esc_html( $pages[ $key ]['title'] )
							);
						}
						?>
					</ul>
				</div>
				<div>
					<div class="ftr__h">Our brands</div>
					<ul class="ftr__l">
						<li><a href="<?php echo esc_url( $o['aun_url'] ); ?>" target="_blank" rel="noopener">AUN Projector</a></li>
						<li><a href="<?php echo esc_url( $o['koh_url'] ); ?>" target="_blank" rel="noopener">Kohthai Bags</a></li>
						<li><a href="<?php echo esc_url( $o['breo_url'] ); ?>" target="_blank" rel="noopener">Breo Bangladesh</a></li>
						<li><a href="<?php echo esc_url( $o['app_url'] ); ?>" target="_blank" rel="noopener">AUN Care app</a></li>
					</ul>
				</div>
				<div>
					<div class="ftr__h">Hours &amp; info</div>
					<ul class="ftr__l">
						<li><a href="<?php echo esc_url( $o['maps_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $o['address_1'] ); ?>,<br><?php echo esc_html( $o['address_2'] ); ?></a></li>
						<li><a href="tel:<?php echo esc_attr( $o['phone_link'] ); ?>"><?php echo esc_html( $o['phone'] ); ?></a></li>
						<li><a href="mailto:<?php echo esc_attr( $o['email'] ); ?>"><?php echo esc_html( $o['email'] ); ?></a></li>
						<li class="ftr__q"><?php echo esc_html( $o['hours_days'] ); ?>, <?php echo esc_html( $o['hours_time'] ); ?></li>
					</ul>
					<?php if ( $o['facebook'] ) : ?>
					<div class="social">
						<a href="<?php echo esc_url( $o['facebook'] ); ?>" target="_blank" rel="noopener" aria-label="Smart Living Bangladesh on Facebook">
							<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 8.5V6.9c0-.8.2-1.2 1.4-1.2H17V3.1c-.3 0-1.2-.1-2.3-.1-2.4 0-4 1.4-4 4.1v1.4H8V12h2.7v9h3.2v-9h2.6l.4-3.5H14z"/></svg>
						</a>
					</div>
					<?php endif; ?>
				</div>
			</div>
			<div class="ftr__bot">
				<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Smart Living Bangladesh. All rights reserved.</span>
				<span>AUN Projector &middot; Kohthai Bags &middot; Breo Bangladesh</span>
			</div>
		</div>
	</footer>
	<?php
}

/**
 * The dark page head used by the five inner pages.
 */
function sl_page_head( $crumb, $h1, $lede, $cta = null ) {
	?>
	<section class="phead on-dark">
		<canvas class="phead__canvas" data-trace data-live aria-hidden="true"></canvas>
		<div class="wrap phead__in">
			<p class="crumb"><?php echo $crumb; ?></p>
			<h1><?php echo $h1; ?></h1>
			<hr class="rule">
			<p class="lede"><?php echo $lede; ?></p>
			<?php if ( $cta ) : ?>
			<div class="btns sl-mt">
				<a class="btn btn--gold" href="<?php echo esc_url( sl_url( 'contact' ) ); ?>"><?php echo $cta; ?> <span class="arw">&rarr;</span></a>
			</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * Section heading: eyebrow, h2, gold rule, optional lede.
 */
function sl_shead( $eyebrow, $h2, $lede = '', $dark = false ) {
	?>
	<div class="shead rv">
		<p class="eyebrow"><?php echo $eyebrow; ?></p>
		<h2<?php echo $dark ? ' class="on-white"' : ''; ?>><?php echo $h2; ?></h2>
		<hr class="rule">
		<?php if ( $lede ) : ?><p class="lede"><?php echo $lede; ?></p><?php endif; ?>
	</div>
	<?php
}

/**
 * The four contact boxes, used on the home page and the contact page.
 */
function sl_contact_grid() {
	$o = sl_opt();
	?>
	<div class="cgrid rv">
		<div class="cbox">
			<span class="cbox__k">Office</span>
			<a class="cbox__v" href="<?php echo esc_url( $o['maps_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $o['address_1'] ); ?><br><?php echo esc_html( $o['address_2'] ); ?></a>
			<span class="cbox__s">Open on Google Maps</span>
		</div>
		<div class="cbox">
			<span class="cbox__k">Phone</span>
			<a class="cbox__v" href="tel:<?php echo esc_attr( $o['phone_link'] ); ?>"><?php echo esc_html( $o['phone'] ); ?></a>
			<span class="cbox__s"><?php echo esc_html( $o['hours_days'] ); ?></span>
		</div>
		<div class="cbox">
			<span class="cbox__k">Email</span>
			<a class="cbox__v" href="mailto:<?php echo esc_attr( $o['email'] ); ?>"><?php echo esc_html( $o['email'] ); ?></a>
			<span class="cbox__s">Replies within one working day</span>
		</div>
		<div class="cbox">
			<span class="cbox__k">Hours</span>
			<span class="cbox__v"><?php echo esc_html( $o['hours_days'] ); ?></span>
			<span class="cbox__s"><?php echo esc_html( $o['hours_time'] ); ?> &middot; Closed <?php echo esc_html( $o['closed_day'] ); ?></span>
		</div>
	</div>
	<?php
}

/**
 * An FAQ block. Hand-rolled <details> — the same pattern the AUN info pages
 * use — and the source for the FAQPage schema in seo.php.
 */
function sl_faq( $items ) {
	echo '<div class="faq">';
	foreach ( $items as $item ) {
		printf(
			'<details><summary>%s</summary><p class="faq__a">%s</p></details>',
			$item[0],
			$item[1]
		);
	}
	echo '</div>';
}

/**
 * The gold-bordered closing callout.
 */
function sl_callout( $h3, $body, $cta ) {
	?>
	<div class="callout">
		<p class="eyebrow">Next step</p>
		<h3><?php echo $h3; ?></h3>
		<p><?php echo $body; ?></p>
		<div class="btns"><a class="btn btn--gold" href="<?php echo esc_url( sl_url( 'contact' ) ); ?>"><?php echo $cta; ?> <span class="arw">&rarr;</span></a></div>
	</div>
	<?php
}
