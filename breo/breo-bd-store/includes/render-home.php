<?php
/**
 * Homepage: full-screen product slider, "where do you feel it" product row,
 * lifestyle banners, brand story, FAQ. Built from the products' own media.
 */
defined( 'ABSPATH' ) || exit;

function breo_bd_home_slides() {
	return array(
		array( 'sku' => 'N990000631', 'media' => 'desk', 'layout' => 'bleed', 'theme' => 'light',
			'title' => 'Your shoulders, finally off duty', 'text' => 'Heated, hands-free kneading for the neck and shoulders.' ),
		array( 'sku' => 'N990000360', 'media' => 'hero', 'layout' => 'bleed', 'theme' => 'light',
			'title' => 'Heat and deep percussion in your palm', 'text' => 'A 405 g massage gun that warms in seconds.' ),
		array( 'sku' => 'N910200212', 'media' => 'glow', 'layout' => 'split', 'theme' => 'warm',
			'title' => 'Ten minutes. Eyes closed. Reset.', 'text' => 'Warm compress and airbag massage for screen-tired eyes.' ),
		array( 'sku' => 'N990000981', 'media' => 'rug', 'layout' => 'split', 'theme' => 'sand',
			'title' => 'Lean back. Let it knead.', 'text' => 'A cordless kneading pillow for your neck and lower back.' ),
	);
}

function breo_bd_render_home() {
	$ps     = breo_bd_products();
	$slides = array_filter( breo_bd_home_slides(), function ( $s ) use ( $ps ) {
		return isset( $ps[ $s['sku'] ] ) && breo_bd_media_id( $ps[ $s['sku'] ], $s['media'] );
	} );
	$wa = breo_bd_whatsapp_url( 'Hi Breo Bangladesh, I need help choosing a massager' );

	ob_start();
	?>
	<div class="breo-home">

		<?php if ( $slides ) : ?>
		<section class="breo-slider" data-breo-slider aria-roledescription="carousel" aria-label="Featured products">
			<div class="breo-slider__track">
				<?php $n = 0; foreach ( $slides as $s ) :
					$p = $ps[ $s['sku'] ];
					$d = breo_bd_product_data( $p ); ?>
					<article class="breo-slide layout-<?php echo esc_attr( $s['layout'] ); ?> theme-<?php echo esc_attr( $s['theme'] ); ?><?php echo 0 === $n ? ' is-active' : ''; ?>" style="--accent:<?php echo esc_attr( $d['accent'] ); ?>" aria-roledescription="slide" aria-label="<?php echo esc_attr( ( $n + 1 ) . ' / ' . count( $slides ) ); ?>">
						<div class="breo-slide__media"><?php echo breo_bd_media( $p, $s['media'], array( 'size' => 'full', 'loading' => 0 === $n ? 'eager' : 'lazy', 'sizes' => 'split' === $s['layout'] ? '(max-width: 900px) 100vw, 50vw' : '100vw' ) ); // phpcs:ignore ?></div>
						<div class="breo-wrap breo-slide__content">
							<div class="breo-slide__in">
								<p class="breo-hero__model"><?php echo breo_bd_logo( 'breo-logo--inline' ); // phpcs:ignore ?><span><?php echo esc_html( $d['short_name'] ); ?></span></p>
								<h2 class="breo-slide__title"><?php echo esc_html( $s['title'] ); ?></h2>
								<p class="breo-slide__text"><?php echo esc_html( $s['text'] ); ?></p>
								<div class="breo-slide__btns">
									<a class="breo-btn" href="<?php echo esc_url( $p->get_permalink() ); ?>">Learn more</a>
									<?php $buy = breo_bd_buy_url( $p ); if ( $buy ) : ?>
										<a class="breo-btn breo-btn--ghost" href="<?php echo esc_url( $buy ); ?>" rel="nofollow"><?php echo esc_html( 'Buy now · ' . breo_bd_plain_price( $p ) ); ?></a>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</article>
				<?php $n++; endforeach; ?>
			</div>
			<?php if ( count( $slides ) > 1 ) : ?>
				<div class="breo-slider__ui">
					<button type="button" class="breo-slider__arrow" data-dir="-1" aria-label="Previous slide"><?php echo breo_bd_icon( 'chevl', 22 ); // phpcs:ignore ?></button>
					<div class="breo-slider__dots" role="tablist">
						<?php for ( $i = 0; $i < count( $slides ); $i++ ) : ?>
							<button type="button" class="<?php echo 0 === $i ? 'is-active' : ''; ?>" data-go="<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr( 'Go to slide ' . ( $i + 1 ) ); ?>"></button>
						<?php endfor; ?>
					</div>
					<button type="button" class="breo-slider__arrow" data-dir="1" aria-label="Next slide"><?php echo breo_bd_icon( 'chevr', 22 ); // phpcs:ignore ?></button>
				</div>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<?php if ( $ps ) : ?>
		<section class="breo-s breo-popular">
			<div class="breo-wrap">
				<header class="breo-sec-head" data-reveal>
					<h1 class="breo-eyebrow breo-h1">Official Breo massagers in Bangladesh</h1>
					<h2 class="breo-title">Where do you feel it?</h2>
				</header>
				<div class="breo-popular__grid">
					<?php foreach ( $ps as $p ) :
						$d   = breo_bd_product_data( $p );
						$cat = breo_bd_categories()[ $d['category'] ]; ?>
						<a class="breo-ptile" href="<?php echo esc_url( $p->get_permalink() ); ?>" style="--accent:<?php echo esc_attr( $d['accent'] ); ?>" data-reveal>
							<span class="breo-ptile__for">For <?php echo esc_html( strtolower( $cat[1] ) ); ?></span>
							<span class="breo-ptile__img"><?php echo breo_bd_media( $p, 'tile', array( 'size' => 'woocommerce_single', 'sizes' => '(max-width: 700px) 50vw, 300px' ) ); // phpcs:ignore ?></span>
							<span class="breo-ptile__name"><?php echo esc_html( $d['short_name'] ); ?></span>
							<span class="breo-ptile__sub"><?php echo esc_html( $d['tagline'] ); ?></span>
							<span class="breo-ptile__price"><?php echo wp_kses_post( breo_bd_price_html( $p ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php endif; ?>

		<?php
		$scenes = array(
			array( 'N990000631', 'ondesk', 'split', 'left', 'cream', 'At your desk', 'Relief between meetings', 'Cordless, quiet and hands-free: the N6 mini works on your shoulders while you work on everything else.' ),
			array( 'N990000360', 'calf', 'banner', 'right', 'light', 'After training', 'Recover after every session', 'Heat plus 2,800 strokes a minute for tight calves and sore shoulders, in a 405 g body that fits any gym bag.' ),
			array( 'N910200212', 'night', 'split', 'left', 'dark', 'Before bed', 'Screens off. Eyes, rest.', 'Ten minutes of warm airbag massage with calming music to close a long day of screens.' ),
			array( 'N990000631', 'gift', 'split', 'right', 'warm', 'The gift of rest', 'For the people who carry the most', 'A Breo device is a present that gets used every day, for parents, partners and anyone who works too hard.' ),
		);
		foreach ( $scenes as $sc ) {
			if ( empty( $ps[ $sc[0] ] ) || ! breo_bd_media_id( $ps[ $sc[0] ], $sc[1] ) ) {
				continue;
			}
			$p = $ps[ $sc[0] ];
			$s = array( 'media' => $sc[1], 'eyebrow' => $sc[5], 'title' => $sc[6], 'text' => $sc[7] );
			if ( 'banner' === $sc[2] ) {
				$s += array( 'align' => $sc[3], 'theme' => $sc[4] );
				$html = breo_bd_sec_banner( $p, breo_bd_product_data( $p ), $s );
			} else {
				$s += array( 'side' => $sc[3], 'theme' => $sc[4] );
				$html = breo_bd_sec_split( $p, breo_bd_product_data( $p ), $s );
			}
			// Each scene links to its product.
			echo str_replace( '</div></div></section>', '<a class="breo-link" href="' . esc_url( $p->get_permalink() ) . '">Discover ' . esc_html( breo_bd_product_data( $p )['short_name'] ) . ' ' . breo_bd_icon( 'arrow', 16 ) . '</a></div></div></section>', $html ); // phpcs:ignore
		}
		?>

		<section class="breo-s breo-brand theme-dark">
			<div class="breo-wrap">
				<div class="breo-brand__head" data-reveal>
					<p class="breo-eyebrow">About Breo</p>
					<h2 class="breo-title">Two decades of making relaxation portable</h2>
					<p class="breo-lead">Breo was founded in Shenzhen in 2000 with one idea: the relief of a good massage shouldn't be limited to a spa. It pioneered compact, rechargeable massagers for the eyes, head, neck and body, pairing traditional acupressure know-how with modern engineering.</p>
					<a class="breo-btn breo-btn--light" href="<?php echo esc_url( breo_bd_page_url( 'about-breo' ) ); ?>">Our story</a>
				</div>
				<div class="breo-brand__stats">
					<div data-reveal><strong>2000</strong><span>Founded in Shenzhen</span></div>
					<div data-reveal><strong>Millions</strong><span>of Breo devices in use worldwide</span></div>
					<div data-reveal><strong>iF</strong><span>Design Award-winning products</span></div>
					<div data-reveal><strong>Official</strong><span>imports, warranty and service in Bangladesh</span></div>
				</div>
			</div>
		</section>

		<section class="breo-s breo-why">
			<div class="breo-wrap">
				<header class="breo-sec-head" data-reveal>
					<p class="breo-eyebrow">Breo Bangladesh</p>
					<h2 class="breo-title">Why buy from us</h2>
				</header>
				<div class="breo-why__grid">
					<div data-reveal><?php echo breo_bd_icon( 'shield', 30 ); // phpcs:ignore ?><h3>Genuine and authorized</h3><p>Imported directly through Breo, never grey-market.</p></div>
					<div data-reveal><?php echo breo_bd_icon( 'badge', 30 ); // phpcs:ignore ?><h3>Local warranty</h3><p><?php echo esc_html( breo_bd_warranty_period() ? 'A ' . breo_bd_warranty_period() . ' official warranty, handled here in Bangladesh.' : 'Official warranty, handled here in Bangladesh.' ); ?></p></div>
					<div data-reveal><?php echo breo_bd_icon( 'truck', 30 ); // phpcs:ignore ?><h3>Delivered to your door</h3><p><?php echo esc_html( 'Inside Dhaka in ' . breo_bd_opt( 'dhaka_days' ) . ' working days, the rest of the country in ' . breo_bd_opt( 'outside_days' ) . '.' ); ?></p></div>
					<div data-reveal><?php echo breo_bd_icon( 'chat', 30 ); // phpcs:ignore ?><h3>Real advice</h3><p>Tell us where it hurts and we'll recommend the right device.</p></div>
				</div>
			</div>
		</section>

		<section class="breo-s breo-home-faq">
			<div class="breo-wrap breo-narrow">
				<h2 class="breo-title" data-reveal>Frequently asked questions</h2>
				<div class="breo-faq" data-reveal>
					<details><summary>Are these genuine Breo products?</summary><p>Yes. Breo Bangladesh is an authorized distributor. Every device is officially imported and covered by our local warranty.</p></details>
					<details><summary>Which massager should I choose?</summary><p>Choose by where you feel it: N6 mini for the neck and shoulders, See KE for tired eyes, P2 for the neck and lower back, and No.7 for muscle soreness after exercise. Still unsure? Message us.</p></details>
					<details><summary>How fast is delivery?</summary><p><?php echo esc_html( 'Inside Dhaka within ' . breo_bd_opt( 'dhaka_days' ) . ' working days and outside Dhaka within ' . breo_bd_opt( 'outside_days' ) . ' working days.' ); ?> <a href="<?php echo esc_url( breo_bd_page_url( 'shipping-delivery' ) ); ?>">Shipping &amp; Delivery</a></p></details>
					<details><summary>How can I pay?</summary><p><?php echo esc_html( breo_bd_opt( 'payments' ) . '.' ); ?></p></details>
					<details><summary>Can anyone use a massager?</summary><p>They are made for everyday relaxation. If you are pregnant, have a pacemaker, a recent injury or surgery, or a medical condition, ask your doctor before use.</p></details>
				</div>
				<p class="breo-center-link"><a class="breo-link" href="<?php echo esc_url( breo_bd_page_url( 'faq' ) ); ?>">All questions <?php echo breo_bd_icon( 'arrow', 16 ); // phpcs:ignore ?></a></p>
			</div>
		</section>

		<section class="breo-s breo-s--tight">
			<div class="breo-wrap"><?php echo breo_bd_help_band( 'Hi Breo Bangladesh, I need help choosing a massager' ); // phpcs:ignore ?></div>
		</section>
	</div>
	<?php
	return ob_get_clean();
}
