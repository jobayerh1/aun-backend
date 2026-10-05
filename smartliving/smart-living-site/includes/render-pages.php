<?php
/**
 * The five inner pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ===========================================================================
 * About Us
 * ======================================================================== */
function sl_render_about() {
	$o = sl_opt();
	sl_page_head(
		'About us',
		'The company behind three brands you may already own.',
		'Smart Living Bangladesh is an import and distribution company in Dhaka. We bring home technology, wellness devices and contemporary fashion into Bangladesh &mdash; and we keep supporting them after the sale.'
	);
	?>

	<section class="band band--paper">
		<div class="wrap cols2 cols2--top rv">
			<div class="stack stack-20">
				<p class="eyebrow">Who we are</p>
				<h2>We started with one projector brand and a service bench.</h2>
				<hr class="rule">
			</div>
			<div class="stack stack-20 prose">
				<p>We began trading on <b><?php echo esc_html( sl_founded_long() ); ?></b> as AUN Projector Bangladesh &mdash; the official importer of AUN projectors. The decision that shaped everything after it was an unglamorous one: we set up our own repair bench in Dhaka instead of shipping faults back to the manufacturer.</p>
				<p>That is why customers stay. More than <b><?php echo esc_html( $o['customers'] ); ?> people</b> have bought an AUN projector from us, and a meaningful share of them come back for a second unit, a spare part or a repair years later.</p>
				<p>Two divisions followed. <b>Kohthai Bags</b> took the same approach into women&rsquo;s fashion, and in 2026 we were appointed the authorized <b>Breo</b> distributor in Bangladesh, adding wellness technology to the group.</p>
				<p>Each brand has its own storefront and its own audience. They share a warehouse, a service bench, a warranty desk and a standard.</p>
			</div>
		</div>
	</section>

	<section class="band band--white">
		<div class="wrap stack stack-40">
			<?php sl_shead( 'How we grew', sl_years() . ' years, three divisions.' ); ?>
			<ul class="tl rv">
				<?php foreach ( sl_c_timeline() as $row ) : ?>
					<li>
						<span class="tl__y"><?php echo esc_html( $row[0] ); ?></span>
						<div class="tl__b"><h3><?php echo $row[1]; ?></h3><p><?php echo $row[2]; ?></p></div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<section class="band band--dark on-dark">
		<canvas class="phead__canvas" data-trace aria-hidden="true"></canvas>
		<div class="wrap cols2 rv sl-rel">
			<div class="stack stack-16">
				<p class="eyebrow">Mission</p>
				<h3 class="bigstate">To make everyday living in Bangladesh smarter and more comfortable &mdash; with products that are worth what they cost and support that outlasts the sale.</h3>
			</div>
			<div class="stack stack-16">
				<p class="eyebrow">Vision</p>
				<h3 class="bigstate">To be the distributor Bangladeshi buyers name when they want something that will still be serviceable in five years.</h3>
			</div>
		</div>
	</section>

	<section class="band band--paper">
		<div class="wrap stack stack-40">
			<?php
			sl_shead(
				'What we hold ourselves to',
				'Five values, stated as behaviour.',
				'Values are only worth printing if you can tell when they have been broken. These are written so you can.'
			);
			?>
			<div class="vals rv">
				<?php foreach ( sl_c_values() as $v ) : ?>
					<div class="val"><h3><?php echo $v[0]; ?></h3><p><?php echo $v[1]; ?></p></div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="band band--white">
		<div class="wrap cols2 cols2--top rv">
			<div class="stack stack-20">
				<p class="eyebrow">Company details</p>
				<h2>On the record.</h2>
				<hr class="rule">
				<p class="lede">If you are checking us before ordering or partnering, this is everything you would reasonably want to see.</p>
			</div>
			<ul class="facts">
				<li><span class="k">Legal entity</span><span class="v">Smart Living Bangladesh</span></li>
				<li><span class="k">Trading since</span><span class="v"><?php echo esc_html( sl_founded_long() ); ?></span></li>
				<li><span class="k">Head office</span><span class="v"><?php echo esc_html( $o['address_1'] . ', ' . $o['address_2'] ); ?></span></li>
				<li><span class="k">Divisions</span><span class="v">AUN Projector Bangladesh &middot; Kohthai Bags &middot; Breo Bangladesh</span></li>
				<li><span class="k">Distributorships</span><span class="v">Authorized for AUN and Breo in Bangladesh</span></li>
				<li><span class="k">Phone</span><span class="v"><a href="tel:<?php echo esc_attr( $o['phone_link'] ); ?>"><?php echo esc_html( $o['phone'] ); ?></a></span></li>
				<li><span class="k">Email</span><span class="v"><a href="mailto:<?php echo esc_attr( $o['email'] ); ?>"><?php echo esc_html( $o['email'] ); ?></a></span></li>
				<li><span class="k">Hours</span><span class="v"><?php echo esc_html( $o['hours_days'] . ', ' . $o['hours_time'] ); ?></span></li>
			</ul>
		</div>
	</section>
	<?php
}

/* ===========================================================================
 * Our Brands
 * ======================================================================== */
function sl_render_brands() {
	$o = sl_opt();
	sl_page_head(
		'Our brands',
		'Three divisions, one company.',
		'Smart projection, contemporary women&rsquo;s bags and wellness technology. Different buyers, different storefronts &mdash; the same warehouse, warranty desk and service bench behind them.'
	);
	?>

	<section class="band band--paper">
		<div class="wrap">

			<!-- AUN -->
			<div class="bsec stack stack-28 rv">
				<div class="div__brand">
					<img class="bigmark" src="<?php echo esc_url( sl_img( 'aun-mark.webp' ) ); ?>" alt="" width="240" height="240">
					<div>
						<h2 class="div__name div__name--lg">AUN Projector Bangladesh</h2>
						<div class="div__what">Authorized AUN distributor in Bangladesh &middot; since 2019</div>
					</div>
				</div>
				<div class="cols2 cols2--top">
					<div class="stack stack-16 prose">
						<p>Our founding division and still our largest. We import AUN projectors directly and sell them with a local warranty, same-day Dhaka delivery and nationwide courier with cash on delivery.</p>
						<p>The range runs from a first projector for a bedroom wall to Full HD and Android TV models for living rooms, classrooms and meeting rooms &mdash; plus ALR screens, mounts and genuine spare parts.</p>
						<p>What actually separates us is after the sale: a Dhaka service point, parts held in stock, and the AUN Care app that lets an owner register warranty, order a part or book a repair without needing to find their receipt.</p>
					</div>
					<div class="stack stack-20">
						<div class="div__media"><?php sl_band_img( 'aun-band', 'The AUN projector range' ); ?></div>
						<ul class="rangelist">
							<li>Home theatre</li><li>Portable</li><li>Classroom</li><li>Office &amp; meeting room</li>
							<li>ALR screens</li><li>Mounts</li><li>Genuine spare parts</li>
						</ul>
						<ul class="facts">
							<li><span class="k">Price range</span><span class="v">BDT 7,590 &ndash; 32,900+</span></li>
							<li><span class="k">Customers</span><span class="v"><?php echo esc_html( $o['customers'] ); ?></span></li>
							<li><span class="k">Warranty</span><span class="v">Official local warranty, serviced in Dhaka</span></li>
						</ul>
						<a class="tlink" href="<?php echo esc_url( $o['aun_url'] ); ?>" target="_blank" rel="noopener">Visit aun-projector.com.bd <span class="arw">&rarr;</span></a>
					</div>
				</div>
			</div>

			<!-- Kohthai -->
			<div class="bsec stack stack-28 rv">
				<div class="div__brand">
					<img class="bigmark" src="<?php echo esc_url( sl_img( 'koh-mark.webp' ) ); ?>" alt="" width="240" height="240" loading="lazy">
					<div>
						<h2 class="div__name div__name--lg">Kohthai Bags</h2>
						<div class="div__what">Women&rsquo;s handbags in Bangladesh &middot; our own label</div>
					</div>
				</div>
				<div class="cols2 cols2--top">
					<div class="stack stack-16 prose">
						<p>Kohthai is our women&rsquo;s handbag brand. Styles are imported and curated rather than mass-listed &mdash; we choose for material feel, hardware quality and how a bag actually wears day to day in Dhaka.</p>
						<p>The pricing is deliberate: premium-feeling bags at a price a working woman can justify, without the markup a boutique window carries.</p>
						<p>Orders ship nationwide with cash on delivery, and returns are accepted within seven days where an item arrives damaged, wrong or not as described.</p>
					</div>
					<div class="stack stack-20">
						<div class="div__media"><?php sl_band_img( 'koh-band', 'Kohthai handbags' ); ?></div>
						<ul class="rangelist">
							<li>Tote bags</li><li>Crossbody</li><li>Clutch</li><li>Shoulder bags</li><li>Saddle bags</li><li>Accessories</li>
						</ul>
						<ul class="facts">
							<li><span class="k">Returns</span><span class="v">7 days, for damaged, wrong or not-as-described items</span></li>
							<li><span class="k">Delivery</span><span class="v">Nationwide courier, cash on delivery available</span></li>
						</ul>
						<a class="tlink" href="<?php echo esc_url( $o['koh_url'] ); ?>" target="_blank" rel="noopener">Visit kohthaibd.com <span class="arw">&rarr;</span></a>
					</div>
				</div>
			</div>

			<!-- Breo -->
			<div class="bsec stack stack-28 rv">
				<div class="div__brand">
					<span class="bmark bmark--lg" aria-hidden="true"><?php echo sl_breo_mark(); ?></span>
					<div>
						<h2 class="div__name div__name--lg">Breo Bangladesh</h2>
						<div class="div__what">Authorized Breo distributor in Bangladesh &middot; newly launched</div>
					</div>
				</div>
				<div class="cols2 cols2--top">
					<div class="stack stack-16 prose">
						<p>Breo is an international wellness-device brand making massagers for the neck, eyes, back and limbs. Smart Living Bangladesh is its <b>authorized distributor</b> here &mdash; which means official warranty, genuine units and service you can reach locally.</p>
						<p>This is our newest division and the one we are building out now. Four models are in the market, with the wider Breo range to follow as demand settles.</p>
						<p>Every unit is sold with an official warranty, a manual in English, and the same Dhaka support desk that backs our projectors.</p>
					</div>
					<div class="stack stack-20">
						<div class="div__media"><?php sl_band_img( 'breo-band', 'A Breo massager on a desk' ); ?></div>
						<div class="prods">
							<?php foreach ( sl_c_breo_products() as $p ) : ?>
								<a class="prod" href="<?php echo esc_url( trailingslashit( $o['breo_url'] ) . $p[2] ); ?>" target="_blank" rel="noopener">
									<span class="prod__n"><?php echo $p[0]; ?></span>
									<span class="prod__d"><?php echo $p[1]; ?></span>
								</a>
							<?php endforeach; ?>
						</div>
						<a class="tlink" href="<?php echo esc_url( $o['breo_url'] ); ?>" target="_blank" rel="noopener">Visit breo.bd <span class="arw">&rarr;</span></a>
					</div>
				</div>
			</div>

		</div>
	</section>

	<section class="band band--dark on-dark">
		<canvas class="phead__canvas" data-trace aria-hidden="true"></canvas>
		<div class="wrap stack stack-20 rv sl-rel">
			<p class="eyebrow">What comes next</p>
			<h2 class="on-white">More divisions will follow.</h2>
			<hr class="rule">
			<p class="lede">We add a category when we can support it properly &mdash; parts, warranty and a bench &mdash; not when it is merely easy to import.</p>
		</div>
	</section>
	<?php
}

/* ===========================================================================
 * Corporate Solutions
 * ======================================================================== */
function sl_render_corporate() {
	sl_page_head(
		'Corporate solutions',
		'Equipment supplied with the paperwork your finance team needs.',
		'We supply offices, schools, event spaces and procurement teams across Bangladesh &mdash; with proper invoicing, warranty documentation and a service point you can reach when something fails mid-term.',
		'Request corporate assistance'
	);
	?>

	<section class="band band--paper">
		<div class="wrap stack stack-40">
			<?php sl_shead( 'What we supply', 'Five corporate offerings.' ); ?>
			<div class="rv">
				<?php foreach ( sl_c_corporate_offers() as $r ) : ?>
					<article class="offer">
						<?php echo sl_icon( $r[0], 'offer__ico' ); ?>
						<div>
							<h3><?php echo $r[1]; ?></h3>
							<p><?php echo $r[2]; ?></p>
							<p class="offer__meta"><?php echo $r[3]; ?></p>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="band band--dark on-dark">
		<canvas class="phead__canvas" data-trace aria-hidden="true"></canvas>
		<div class="wrap stack stack-40 sl-rel">
			<?php sl_shead( 'Why buy from us', 'The six things procurement teams ask about.', '', true ); ?>
			<div class="cols3 rv">
				<?php foreach ( sl_c_corporate_why() as $r ) : ?>
					<div class="proof"><h3><?php echo $r[0]; ?></h3><p><?php echo $r[1]; ?></p></div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="band band--white">
		<div class="wrap cols2 cols2--top rv">
			<div class="stack stack-20">
				<p class="eyebrow">Common questions</p>
				<h2>Before you send an enquiry.</h2>
				<hr class="rule">
				<p class="lede">The five things procurement and admin teams ask us most often, answered plainly.</p>
			</div>
			<?php sl_faq( sl_c_corporate_faq() ); ?>
		</div>
	</section>

	<section class="band band--paper">
		<div class="wrap rv">
			<?php
			sl_callout(
				'Tell us the room, the headcount or the gift list.',
				'Send the details and we will come back with a specification and a quotation &mdash; including the honest note about where you could spend less.',
				'Request corporate assistance'
			);
			?>
		</div>
	</section>
	<?php
}

/* ===========================================================================
 * Dealer & Partnership
 * ======================================================================== */
function sl_render_dealers() {
	sl_page_head(
		'Dealer &amp; partnership',
		'Sell brands that do not come back to you broken.',
		'We work with electronics retailers, IT suppliers, AV contractors, boutiques and regional distributors. The pitch is simple: dealer pricing, stock that is actually there, and a warranty desk that takes the service burden off your counter.',
		'Apply or request information'
	);
	?>

	<section class="band band--paper">
		<div class="wrap stack stack-40">
			<?php sl_shead( 'Programmes', 'Four ways to partner.' ); ?>
			<div class="rv">
				<?php foreach ( sl_c_dealer_programmes() as $r ) : ?>
					<article class="offer">
						<?php echo sl_icon( $r[0], 'offer__ico' ); ?>
						<div>
							<h3><?php echo $r[1]; ?></h3>
							<p><?php echo $r[2]; ?></p>
							<p class="offer__meta"><?php echo $r[3]; ?></p>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="band band--dark on-dark">
		<canvas class="phead__canvas" data-trace aria-hidden="true"></canvas>
		<div class="wrap stack stack-40 sl-rel">
			<?php sl_shead( 'What a partner gets', 'Seven commitments.', '', true ); ?>
			<div class="cols3 rv">
				<?php foreach ( sl_c_dealer_commitments() as $r ) : ?>
					<div class="proof"><h3><?php echo $r[0]; ?></h3><p><?php echo $r[1]; ?></p></div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="band band--white">
		<div class="wrap cols2 cols2--top rv">
			<div class="stack stack-20">
				<p class="eyebrow">Common questions</p>
				<h2>What partners ask first.</h2>
				<hr class="rule">
				<p class="lede">If your question is not here, send it &mdash; a person will answer it, not a template.</p>
			</div>
			<?php sl_faq( sl_c_dealer_faq() ); ?>
		</div>
	</section>

	<section class="band band--paper">
		<div class="wrap rv">
			<?php
			sl_callout(
				'Tell us your business category and your territory.',
				'Send those two things and we will come back with the programme that fits, the pricing tier and what stock we can commit to. Territories for Breo are still open.',
				'Apply or request information'
			);
			?>
		</div>
	</section>
	<?php
}

/* ===========================================================================
 * Contact
 * ======================================================================== */
function sl_render_contact() {
	$o = sl_opt();
	sl_page_head(
		'Contact',
		'Get in touch.',
		'Sales, support, corporate supply or a dealership enquiry &mdash; all four reach the same desk. We reply within one working day.'
	);
	?>

	<section class="band band--dark on-dark band--tight">
		<canvas class="phead__canvas" data-trace aria-hidden="true"></canvas>
		<div class="wrap sl-rel"><?php sl_contact_grid(); ?></div>
	</section>

	<section class="band band--paper">
		<div class="wrap cols2 cols2--top">
			<div class="stack stack-28 rv">
				<div class="shead">
					<p class="eyebrow">Send a message</p>
					<h2>Tell us what you need.</h2>
					<hr class="rule">
				</div>
				<div class="sl-form">
					<?php
					if ( $o['cf7'] ) {
						echo do_shortcode( $o['cf7'] );
					} else {
						?>
						<p class="formnote formnote--warn">
							The contact form is not connected yet. Add your Contact&nbsp;Form&nbsp;7 shortcode under
							<b>Settings &rarr; Smart Living Site</b> and it will appear here, styled to match the page.
						</p>
						<div class="btns">
							<a class="btn btn--solid" href="mailto:<?php echo esc_attr( $o['email'] ); ?>">Email us instead <span class="arw">&rarr;</span></a>
						</div>
						<?php
					}
					?>
				</div>
			</div>

			<div class="stack stack-20 rv">
				<div class="mapcard">
					<p class="eyebrow">Visit us</p>
					<h3><?php echo esc_html( $o['address_1'] ); ?></h3>
					<p class="mapcard__p"><?php echo esc_html( $o['address_2'] ); ?>, Bangladesh. Street parking is available on Mazar Road; the office is a short walk from the Mazar Road junction.</p>
					<hr class="rule rule--wide">
					<ul class="facts">
						<li><span class="k"><?php echo esc_html( $o['hours_days'] ); ?></span><span class="v"><?php echo esc_html( $o['hours_time'] ); ?></span></li>
						<li><span class="k"><?php echo esc_html( $o['closed_day'] ); ?></span><span class="v">Closed</span></li>
					</ul>
					<a class="tlink" href="<?php echo esc_url( $o['maps_url'] ); ?>" target="_blank" rel="noopener">Open in Google Maps <span class="arw">&rarr;</span></a>
				</div>

				<div class="mapcard">
					<p class="eyebrow">Brand support</p>
					<h3>Already own something of ours?</h3>
					<p class="mapcard__p">Warranty, spare parts and repairs are fastest through the brand&rsquo;s own site &mdash; that is where your order and serial already are.</p>
					<ul class="facts">
						<li><span class="k">Projectors</span><span class="v"><a href="<?php echo esc_url( $o['aun_url'] ); ?>" target="_blank" rel="noopener">aun-projector.com.bd</a></span></li>
						<li><span class="k">Bags</span><span class="v"><a href="<?php echo esc_url( $o['koh_url'] ); ?>" target="_blank" rel="noopener">kohthaibd.com</a></span></li>
						<li><span class="k">Massagers</span><span class="v"><a href="<?php echo esc_url( $o['breo_url'] ); ?>" target="_blank" rel="noopener">breo.bd</a></span></li>
					</ul>
				</div>
			</div>
		</div>
	</section>
	<?php
}

/* ===========================================================================
 * 404
 * ======================================================================== */
function sl_render_404() {
	sl_page_head(
		'Page not found',
		'That page is not here.',
		'The link may be old, or the address may have a typo in it. Everything on this site is one click away below.'
	);
	?>
	<section class="band band--paper">
		<div class="wrap stack stack-40">
			<?php sl_shead( 'Try these', 'Where you were probably going.' ); ?>
			<div class="cols2 rv">
				<div class="bizcard">
					<p class="eyebrow">This site</p>
					<h3>Smart Living Bangladesh</h3>
					<ul>
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
					<a class="tlink" href="<?php echo esc_url( sl_url( 'home' ) ); ?>">Back to the home page <span class="arw">&rarr;</span></a>
				</div>
				<div class="bizcard">
					<p class="eyebrow">Looking for a product?</p>
					<h3>Our three brands</h3>
					<p class="bizcard__p">Products, prices, warranty and support all live on the brand sites rather than here.</p>
					<ul>
						<li><a href="<?php echo esc_url( sl_opt( 'aun_url' ) ); ?>" target="_blank" rel="noopener">AUN Projector &mdash; projectors and screens</a></li>
						<li><a href="<?php echo esc_url( sl_opt( 'koh_url' ) ); ?>" target="_blank" rel="noopener">Kohthai Bags &mdash; women&rsquo;s handbags</a></li>
						<li><a href="<?php echo esc_url( sl_opt( 'breo_url' ) ); ?>" target="_blank" rel="noopener">Breo Bangladesh &mdash; massagers</a></li>
					</ul>
					<a class="tlink" href="<?php echo esc_url( sl_url( 'contact' ) ); ?>">Or just ask us <span class="arw">&rarr;</span></a>
				</div>
			</div>
		</div>
	</section>
	<?php
}
