<?php
/**
 * The home page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function sl_render_home() {
	$o = sl_opt();
	?>

	<section class="hero on-dark">
		<div class="hero__media" aria-hidden="true">
			<?php foreach ( array( 'aun-band', 'koh-band', 'breo-band' ) as $band ) : ?>
				<img src="<?php echo esc_url( sl_img( $band . '.webp' ) ); ?>"
					srcset="<?php echo esc_url( sl_img( $band . '-900.webp' ) ); ?> 900w, <?php echo esc_url( sl_img( $band . '.webp' ) ); ?> 1600w"
					sizes="100vw" alt="" width="1600" height="938"
					data-no-lazy="1" decoding="async">
			<?php endforeach; ?>
		</div>
		<div class="hero__veil" aria-hidden="true"></div>
		<canvas class="hero__canvas" data-trace data-live aria-hidden="true"></canvas>

		<div class="wrap hero__in stack stack-28">
			<p class="eyebrow fx fx--1">Smart Living Bangladesh</p>
			<h1>
				<span class="ln"><span>Three brands.</span></span>
				<span class="ln"><span class="gold">One standard.</span></span>
			</h1>
			<hr class="rule hero__rule">
			<p class="hero__lede fx fx--2">We import, distribute and support smart projection, contemporary women&rsquo;s bags and wellness technology across Bangladesh &mdash; and we are still reachable long after the sale.</p>
			<div class="btns fx fx--3">
				<a class="btn btn--gold" href="<?php echo esc_url( sl_url( 'brands' ) ); ?>">See our brands <span class="arw">&rarr;</span></a>
				<a class="btn btn--ghost" href="<?php echo esc_url( sl_url( 'corporate' ) ); ?>">For business</a>
			</div>
		</div>
		<div class="wrap cue fx fx--5" aria-hidden="true"><i></i> Scroll</div>
	</section>

	<div class="portfolio">
		<div class="wrap portfolio__in">
			<span class="portfolio__lbl">The group</span>
			<div class="portfolio__set">
				<a class="pbrand" href="<?php echo esc_url( $o['aun_url'] ); ?>" target="_blank" rel="noopener">
					<img src="<?php echo esc_url( sl_img( 'aun-mark.webp' ) ); ?>" alt="" width="240" height="240" loading="lazy">
					<span class="pbrand__t">AUN Projector</span>
				</a>
				<a class="pbrand" href="<?php echo esc_url( $o['koh_url'] ); ?>" target="_blank" rel="noopener">
					<img src="<?php echo esc_url( sl_img( 'koh-mark.webp' ) ); ?>" alt="" width="240" height="240" loading="lazy">
					<span class="pbrand__t">Kohthai Bags</span>
				</a>
				<a class="pbrand" href="<?php echo esc_url( $o['breo_url'] ); ?>" target="_blank" rel="noopener">
					<span class="bmark" aria-hidden="true"><?php echo sl_breo_mark(); ?></span>
					<span class="pbrand__t">Breo Bangladesh</span>
				</a>
			</div>
		</div>
	</div>

	<section class="band band--white band--tight">
		<div class="wrap">
			<div class="stats rv">
				<div class="stat">
					<span class="stat__n" data-count="<?php echo esc_attr( sl_years() ); ?>"><?php echo esc_html( sl_years() ); ?></span>
					<span class="stat__k">Years trading</span>
					<span class="stat__s">Since <?php echo esc_html( sl_founded_long() ); ?></span>
				</div>
				<div class="stat">
					<span class="stat__n" data-count="3">3</span>
					<span class="stat__k">Brand divisions</span>
					<span class="stat__s">Projection, fashion, wellness</span>
				</div>
				<?php $cust = sl_customers(); ?>
				<div class="stat">
					<span class="stat__n" data-count="<?php echo esc_attr( $cust['num'] ); ?>" data-suffix="<?php echo esc_attr( $cust['suffix'] ); ?>"><?php
						echo esc_html( number_format_i18n( $cust['num'] ) );
						if ( $cust['suffix'] ) {
							echo '<sup>' . esc_html( $cust['suffix'] ) . '</sup>';
						}
					?></span>
					<span class="stat__k">Customers served</span>
					<span class="stat__s">AUN division to date</span>
				</div>
				<div class="stat">
					<span class="stat__n" data-count="2">2</span>
					<span class="stat__k">Distributorships</span>
					<span class="stat__s">Authorized for AUN and Breo</span>
				</div>
			</div>
		</div>
	</section>

	<section class="band band--ink on-dark band--quote">
		<canvas class="phead__canvas" data-trace aria-hidden="true"></canvas>
		<div class="wrap sl-rel">
			<blockquote class="quote rv">
				<span class="quote__mark" aria-hidden="true">&ldquo;</span>
				<div>
					<p class="quote__t">Anyone can import a container. The hard part is being <em>findable</em> three years later, when a part fails and the customer still has our number.</p>
					<p class="quote__by">Our operating principle since 2019</p>
				</div>
			</blockquote>
		</div>
	</section>

	<section class="band band--paper">
		<div class="wrap stack stack-64">
			<?php
			sl_shead(
				'Our divisions',
				'An importer that answers the phone afterwards.',
				'Each division runs its own storefront, its own warranty and its own audience &mdash; backed by one company, one warehouse and one service bench in Dhaka.'
			);
			?>
			<div>
				<?php
				$i = 0;
				foreach ( sl_c_divisions() as $d ) :
					$flip = ( 1 === $i % 2 );
					$i++;
					?>
					<article class="div rv<?php echo $flip ? ' div--flip' : ''; ?>">
						<div class="div__media">
							<span class="div__tag"><?php echo $d['tag']; ?></span>
							<?php sl_band_img( $d['img'], $d['alt'] ); ?>
						</div>
						<div class="div__body">
							<div class="div__brand">
								<?php if ( $d['mark'] ) : ?>
									<img src="<?php echo esc_url( sl_img( $d['mark'] ) ); ?>" alt="" width="240" height="240" loading="lazy">
								<?php else : ?>
									<span class="bmark" aria-hidden="true"><?php echo sl_breo_mark(); ?></span>
								<?php endif; ?>
								<div>
									<div class="div__name"><?php echo $d['name']; ?></div>
									<div class="div__what"><?php echo $d['what']; ?></div>
								</div>
							</div>
							<p><?php echo $d['body']; ?></p>
							<div class="div__facts">
								<?php
								foreach ( $d['chips'] as $chip ) {
									if ( 0 === strpos( $chip, 'NEW:' ) ) {
										printf( '<span class="chip chip--new">%s</span>', substr( $chip, 4 ) );
									} else {
										printf( '<span class="chip">%s</span>', $chip );
									}
								}
								?>
							</div>
							<a class="tlink" href="<?php echo esc_url( $o[ $d['url'] ] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $d['link'] ); ?> <span class="arw">&rarr;</span></a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="band band--dark on-dark">
		<canvas class="phead__canvas" data-trace aria-hidden="true"></canvas>
		<div class="wrap stack stack-56 sl-rel">
			<?php
			sl_shead(
				'How a product reaches you',
				'Four steps, and we own all four.',
				'Most importers stop at step three. The fourth is the reason customers come back to us.',
				true
			);
			?>
			<div class="chain rv">
				<?php
				$n = 0;
				foreach ( sl_c_chain() as $step ) :
					$n++;
					?>
					<div class="step">
						<span class="step__n"><?php echo esc_html( sprintf( '%02d', $n ) ); ?></span>
						<h3><?php echo $step[0]; ?></h3>
						<p><?php echo $step[1]; ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="band band--white">
		<div class="wrap stack stack-56">
			<?php
			sl_shead(
				'How we do business',
				'Proof, not promises.',
				'Consumer electronics in Bangladesh runs on inflated numbers. We took the other path &mdash; publish the manufacturer&rsquo;s specs plainly labelled, then show you what the product actually does and let you judge before you spend a taka.'
			);
			?>
			<div class="cols3 rv">
				<?php foreach ( sl_c_proof() as $p ) : ?>
					<div class="proof"><h3><?php echo $p[0]; ?></h3><p><?php echo $p[1]; ?></p></div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="band band--ink on-dark">
		<canvas class="phead__canvas" data-trace aria-hidden="true"></canvas>
		<div class="wrap split rv sl-rel">
			<div class="stack stack-28">
				<p class="eyebrow">After the sale</p>
				<h2 class="on-white">We built an app so support does not depend on remembering who sold it to you.</h2>
				<hr class="rule">
				<p class="lede">AUN Care Bangladesh is free on Android, in Bangla and English. It is how a customer reaches us years after the purchase &mdash; and it is the clearest example of what we mean by staying.</p>
				<div class="btns">
					<a class="btn btn--gold" href="<?php echo esc_url( $o['app_url'] ); ?>" target="_blank" rel="noopener">Get the app <span class="arw">&rarr;</span></a>
				</div>
			</div>
			<ul class="applist">
				<?php foreach ( sl_c_app() as $row ) : ?>
					<li><?php echo sl_icon( 'tick', 'tick' ); ?><span><b><?php echo $row[0]; ?></b> <?php echo $row[1]; ?></span></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<section class="band band--dark on-dark">
		<canvas class="phead__canvas" data-trace aria-hidden="true"></canvas>
		<div class="wrap stack stack-40 sl-rel">
			<?php sl_shead( 'Credentials', 'What stands behind the three brands.', '', true ); ?>
			<div class="creds3 rv">
				<?php foreach ( sl_c_credentials() as $c ) : ?>
					<div class="credc">
						<?php echo sl_icon( $c[0], 'credc__ico' ); ?>
						<h3><?php echo $c[1]; ?></h3>
						<p><?php echo $c[2]; ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="band band--paper">
		<div class="wrap stack stack-40">
			<?php sl_shead( 'Work with us', 'Two ways to do business with Smart Living.' ); ?>
			<div class="cols2 rv">
				<div class="bizcard">
					<p class="eyebrow">For organisations</p>
					<h3>Corporate Solutions</h3>
					<p class="bizcard__p">Equipment and lifestyle products supplied to offices, schools, event spaces and procurement teams &mdash; with the paperwork your finance department needs.</p>
					<ul>
						<li>Meeting-room and training-room projection</li>
						<li>Classroom and seminar-hall systems</li>
						<li>Corporate gifting from Kohthai and Breo</li>
						<li>Bulk supply with corporate invoicing</li>
					</ul>
					<a class="tlink" href="<?php echo esc_url( sl_url( 'corporate' ) ); ?>">Corporate solutions <span class="arw">&rarr;</span></a>
				</div>
				<div class="bizcard">
					<p class="eyebrow">For resellers</p>
					<h3>Dealer &amp; Partnership</h3>
					<p class="bizcard__p">Distribution and resale for electronics retailers, IT suppliers, AV contractors, boutiques and regional distributors across Bangladesh.</p>
					<ul>
						<li>AUN projector dealer programme</li>
						<li>Breo wellness retail partnership</li>
						<li>Kohthai boutique and online resale</li>
						<li>Dealer pricing with consistent stock</li>
					</ul>
					<a class="tlink" href="<?php echo esc_url( sl_url( 'dealers' ) ); ?>">Dealer &amp; partnership <span class="arw">&rarr;</span></a>
				</div>
			</div>
		</div>
	</section>

	<?php
	/*
	 * The page closes on an action, not on the footer's data.
	 *
	 * This band used to repeat the address, phone, email and opening hours in
	 * a four-column grid — directly above a footer carrying the same four
	 * things. Measured on the live page: the address appeared twice, the
	 * phone twice and the opening hours three times, all within one screen.
	 * Reference details belong in the footer; the last thing before it should
	 * ask for the enquiry. The phone stays because a tap-to-call button is a
	 * call to action, not a repeated fact.
	 */
	?>
	<section class="band band--ink band--close on-dark">
		<canvas class="phead__canvas" data-trace aria-hidden="true"></canvas>
		<div class="wrap sl-rel">
			<div class="close rv">
				<p class="eyebrow">Get in touch</p>
				<h2>Talk to a person, not a form reply.</h2>
				<hr class="rule">
				<p class="lede">Sales, support, corporate supply or a dealership &mdash; one desk answers all four, and it answers within a working day.</p>
				<div class="btns">
					<a class="btn btn--gold" href="<?php echo esc_url( sl_url( 'contact' ) ); ?>">Contact us <span class="arw">&rarr;</span></a>
					<a class="btn btn--ghost btn--phone" href="tel:<?php echo esc_attr( $o['phone_link'] ); ?>"><?php echo esc_html( $o['phone'] ); ?></a>
				</div>
			</div>
		</div>
	</section>

	<?php
}
