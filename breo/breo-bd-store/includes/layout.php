<?php
/**
 * Site shell: assets, template routing, the Breo header/footer (also swapped
 * into the theme's own templates), sharing tags and cart wiring.
 */
defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------------
 * Assets — everything is served from this site (fonts included).
 * --------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'breo-bd', BREO_BD_URL . 'assets/breo.css', array(), BREO_BD_VERSION );
	wp_enqueue_script( 'breo-bd', BREO_BD_URL . 'assets/breo.js', array(), BREO_BD_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	if ( function_exists( 'WC' ) ) {
		wp_enqueue_script( 'wc-cart-fragments' );
	}
}, 20 );

add_filter( 'body_class', function ( $c ) {
	$c[] = 'breo-bd';
	return $c;
} );

// Flag JS early so scroll-reveal elements start hidden (no flash), then fade in.
add_action( 'wp_head', function () {
	echo '<script data-no-optimize="1" data-no-minify="1" data-cfasync="false" data-no-defer="1">document.documentElement.classList.add("js-breo");</script>' . "\n";
}, 1 );

/* ------------------------------------------------------------------------
 * Which Breo view (if any) renders this request.
 * --------------------------------------------------------------------- */

function breo_bd_view() {
	static $view = null;
	if ( null !== $view ) {
		return $view;
	}
	$view = '';
	if ( is_admin() || is_feed() || is_embed() ) {
		return $view;
	}
	$id = get_queried_object_id();
	if ( is_front_page() && is_page() && get_post_meta( $id, '_breo_home', true ) ) {
		$view = 'home';
	} elseif ( function_exists( 'is_product' ) && is_product() && breo_bd_product_data( wc_get_product( $id ) ) ) {
		$view = 'product';
	} elseif ( is_page() && get_post_meta( $id, '_breo_page', true ) ) {
		$view = 'page';
	} elseif ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() ) && ! is_search() ) {
		$view = 'shop';
	}
	return $view;
}

add_filter( 'template_include', function ( $template ) {
	return breo_bd_view() ? BREO_BD_DIR . 'templates/canvas.php' : $template;
}, 99 );

/* ------------------------------------------------------------------------
 * On pages the theme still renders (cart, checkout, account, search, 404),
 * swap its header/footer template parts for ours.
 * --------------------------------------------------------------------- */

add_filter( 'render_block', function ( $html, $block ) {
	if ( is_admin() || wp_is_json_request() || empty( $block['blockName'] ) || 'core/template-part' !== $block['blockName'] ) {
		return $html;
	}
	$slug = isset( $block['attrs']['slug'] ) ? $block['attrs']['slug'] : '';
	if ( false !== strpos( $slug, 'header' ) ) {
		return breo_bd_header_html();
	}
	if ( false !== strpos( $slug, 'footer' ) ) {
		return breo_bd_footer_html();
	}
	return $html;
}, 10, 2 );

/* ------------------------------------------------------------------------
 * Header
 * --------------------------------------------------------------------- */

function breo_bd_nav_support() {
	return array(
		'warranty-policy'   => 'Warranty',
		'shipping-delivery' => 'Shipping & Delivery',
		'returns-refunds'   => 'Returns & Refunds',
		'faq'               => 'FAQ',
		'contact'           => 'Contact Us',
	);
}

function breo_bd_cart_count() {
	return ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
}

function breo_bd_header_html() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done     = true;
	$products = breo_bd_products();
	$count    = breo_bd_cart_count();
	$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
	$acct_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
	$wa       = breo_bd_whatsapp_url( 'Hi Breo Bangladesh' );

	ob_start();
	?>
	<header class="breo-hdr" data-breo-hdr>
		<a class="breo-skip" href="#breo-main">Skip to content</a>
		<div class="breo-hdr__bar">
			<a class="breo-hdr__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Breo Bangladesh home">
				<?php echo breo_bd_logo(); // phpcs:ignore ?>
				<span class="breo-hdr__loc">Bangladesh</span>
			</a>
			<nav class="breo-hdr__nav" aria-label="Main">
				<ul>
					<li class="breo-hdr__item is-mega" data-breo-drop>
						<button type="button" class="breo-hdr__link" aria-expanded="false" aria-controls="breo-mega">Products <?php echo breo_bd_icon( 'chev', 14 ); // phpcs:ignore ?></button>
						<div class="breo-mega" id="breo-mega">
							<div class="breo-mega__in">
								<?php foreach ( $products as $sku => $p ) :
									$d = breo_bd_product_data( $p ); ?>
									<a class="breo-mega__card" href="<?php echo esc_url( $p->get_permalink() ); ?>">
										<span class="breo-mega__img"><?php echo breo_bd_media( $p, 'tile', array( 'size' => 'woocommerce_thumbnail', 'sizes' => '220px' ) ); // phpcs:ignore ?></span>
										<span class="breo-mega__name"><?php echo esc_html( $d['short_name'] ); ?></span>
										<span class="breo-mega__sub"><?php echo esc_html( $d['tagline'] ); ?></span>
										<span class="breo-mega__price"><?php echo wp_kses_post( breo_bd_price_html( $p ) ); ?></span>
									</a>
								<?php endforeach; ?>
								<div class="breo-mega__side">
									<p class="breo-mega__label">Shop by need</p>
									<?php foreach ( breo_bd_categories() as $slug => $c ) :
										$t = get_term_by( 'slug', $slug, 'product_cat' );
										if ( ! $t ) {
											continue;
										} ?>
										<a href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $c[1] ); ?></a>
									<?php endforeach; ?>
									<a class="breo-mega__all" href="<?php echo esc_url( breo_bd_shop_url() ); ?>">All products <?php echo breo_bd_icon( 'arrow', 16 ); // phpcs:ignore ?></a>
								</div>
							</div>
						</div>
					</li>
					<li class="breo-hdr__item"><a class="breo-hdr__link" href="<?php echo esc_url( breo_bd_page_url( 'about-breo' ) ); ?>">About Breo</a></li>
					<li class="breo-hdr__item" data-breo-drop>
						<button type="button" class="breo-hdr__link" aria-expanded="false" aria-controls="breo-drop-support">Support <?php echo breo_bd_icon( 'chev', 14 ); // phpcs:ignore ?></button>
						<div class="breo-drop" id="breo-drop-support">
							<?php foreach ( breo_bd_nav_support() as $slug => $label ) : ?>
								<a href="<?php echo esc_url( breo_bd_page_url( $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
							<?php endforeach; ?>
						</div>
					</li>
					<li class="breo-hdr__item"><a class="breo-hdr__link" href="<?php echo esc_url( breo_bd_page_url( 'contact' ) ); ?>">Contact</a></li>
				</ul>
			</nav>
			<div class="breo-hdr__icons">
				<?php if ( $wa ) : ?>
					<a class="breo-hdr__icon breo-hdr__wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><?php echo breo_bd_social_icon( 'whatsapp' ); // phpcs:ignore ?></a>
				<?php endif; ?>
				<a class="breo-hdr__icon" href="<?php echo esc_url( $acct_url ); ?>" aria-label="My account"><?php echo breo_bd_icon( 'user', 20 ); // phpcs:ignore ?></a>
				<a class="breo-hdr__icon breo-hdr__cart" href="<?php echo esc_url( $cart_url ); ?>" aria-label="Cart">
					<?php echo breo_bd_icon( 'cart', 21 ); // phpcs:ignore ?>
					<span class="breo-cart-count" data-count="<?php echo (int) $count; ?>"><?php echo (int) $count; ?></span>
				</a>
				<button type="button" class="breo-hdr__icon breo-hdr__burger" aria-expanded="false" aria-controls="breo-mnav" aria-label="Menu" data-breo-burger>
					<span class="breo-burger-open"><?php echo breo_bd_icon( 'menu', 22 ); // phpcs:ignore ?></span>
					<span class="breo-burger-close"><?php echo breo_bd_icon( 'close', 22 ); // phpcs:ignore ?></span>
				</button>
			</div>
		</div>

		<div class="breo-mnav" id="breo-mnav" hidden>
			<div class="breo-mnav__in">
				<p class="breo-mnav__label">Products</p>
				<div class="breo-mnav__products">
					<?php foreach ( $products as $p ) :
						$d = breo_bd_product_data( $p ); ?>
						<a href="<?php echo esc_url( $p->get_permalink() ); ?>">
							<span class="breo-mnav__img"><?php echo breo_bd_media( $p, 'tile', array( 'size' => 'thumbnail' ) ); // phpcs:ignore ?></span>
							<span><strong><?php echo esc_html( $d['short_name'] ); ?></strong><small><?php echo esc_html( $d['tagline'] ); ?></small></span>
						</a>
					<?php endforeach; ?>
				</div>
				<a class="breo-mnav__link" href="<?php echo esc_url( breo_bd_shop_url() ); ?>">All products</a>
				<a class="breo-mnav__link" href="<?php echo esc_url( breo_bd_page_url( 'about-breo' ) ); ?>">About Breo</a>
				<p class="breo-mnav__label">Support</p>
				<?php foreach ( breo_bd_nav_support() as $slug => $label ) : ?>
					<a class="breo-mnav__link" href="<?php echo esc_url( breo_bd_page_url( $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
				<a class="breo-mnav__link" href="<?php echo esc_url( $acct_url ); ?>">My account</a>
				<?php if ( $wa ) : ?>
					<a class="breo-btn breo-btn--light breo-mnav__wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo breo_bd_social_icon( 'whatsapp' ); // phpcs:ignore ?> Chat on WhatsApp</a>
				<?php endif; ?>
			</div>
		</div>
	</header>
	<?php
	return ob_get_clean();
}

/* ------------------------------------------------------------------------
 * Footer
 * --------------------------------------------------------------------- */

function breo_bd_footer_html() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done   = true;
	$social = array();
	foreach ( array( 'facebook', 'instagram', 'youtube', 'tiktok' ) as $s ) {
		if ( breo_bd_opt( $s ) ) {
			$social[ $s ] = breo_bd_opt( $s );
		}
	}
	$phone = breo_bd_opt( 'phone' );
	$email = breo_bd_opt( 'email' );
	$wa    = breo_bd_whatsapp_url( 'Hi Breo Bangladesh' );
	$acct  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();

	ob_start();
	?>
	<footer class="breo-ftr">
		<div class="breo-wrap breo-ftr__top">
			<div class="breo-ftr__brand">
				<?php echo breo_bd_logo(); // phpcs:ignore ?>
				<p>The authorized distributor of Breo portable wellness devices in Bangladesh: genuine products, a local warranty and real people to help.</p>
				<?php if ( $social ) : ?>
					<div class="breo-ftr__social">
						<?php foreach ( $social as $s => $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( ucfirst( $s ) ); ?>"><?php echo breo_bd_social_icon( $s ); // phpcs:ignore ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<nav class="breo-ftr__col" aria-label="Products">
				<p class="breo-ftr__h">Products</p>
				<?php foreach ( breo_bd_products() as $p ) :
					$d = breo_bd_product_data( $p ); ?>
					<a href="<?php echo esc_url( $p->get_permalink() ); ?>"><?php echo esc_html( $d['short_name'] . ' ' . $d['tagline'] ); ?></a>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( breo_bd_shop_url() ); ?>">All products</a>
			</nav>
			<nav class="breo-ftr__col" aria-label="Support">
				<p class="breo-ftr__h">Support</p>
				<?php foreach ( breo_bd_nav_support() as $slug => $label ) : ?>
					<a href="<?php echo esc_url( breo_bd_page_url( $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<nav class="breo-ftr__col" aria-label="Company">
				<p class="breo-ftr__h">Company</p>
				<a href="<?php echo esc_url( breo_bd_page_url( 'about-breo' ) ); ?>">About Breo</a>
				<a href="<?php echo esc_url( breo_bd_page_url( 'privacy-policy' ) ); ?>">Privacy Policy</a>
				<a href="<?php echo esc_url( breo_bd_page_url( 'terms-conditions' ) ); ?>">Terms &amp; Conditions</a>
				<a href="<?php echo esc_url( $acct ); ?>">My Account</a>
			</nav>
			<div class="breo-ftr__col breo-ftr__contact">
				<p class="breo-ftr__h">Get in touch</p>
				<?php if ( $wa ) : ?>
					<a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo breo_bd_social_icon( 'whatsapp' ); // phpcs:ignore ?> WhatsApp</a>
				<?php endif; ?>
				<?php if ( $phone ) : ?>
					<a href="<?php echo esc_url( breo_bd_tel( $phone ) ); ?>"><?php echo breo_bd_icon( 'phone', 18 ); // phpcs:ignore ?> <?php echo esc_html( $phone ); ?></a>
				<?php endif; ?>
				<?php if ( $email ) : ?>
					<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo breo_bd_icon( 'mail', 18 ); // phpcs:ignore ?> <?php echo esc_html( antispambot( $email ) ); ?></a>
				<?php endif; ?>
				<?php if ( breo_bd_opt( 'address' ) ) : ?>
					<span><?php echo breo_bd_icon( 'pin', 18 ); // phpcs:ignore ?> <?php echo esc_html( breo_bd_opt( 'address' ) ); ?></span>
				<?php endif; ?>
				<?php if ( breo_bd_opt( 'hours' ) ) : ?>
					<span><?php echo breo_bd_icon( 'clock', 18 ); // phpcs:ignore ?> <?php echo esc_html( breo_bd_opt( 'hours' ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<div class="breo-wrap breo-ftr__bottom">
			<p>© <?php echo esc_html( gmdate( 'Y' ) . ' ' . breo_bd_company() ); ?>. All rights reserved.</p>
			<p>Breo and the breo logo are trademarks of Shenzhen Breo Technology Co., Ltd., used by its authorized distributor in Bangladesh.</p>
		</div>
	</footer>
	<?php
	$wa_float = breo_bd_whatsapp_url( 'Hi Breo Bangladesh' . ( is_singular( 'product' ) ? ', I am interested in the ' . get_the_title() : '' ) );
	if ( $wa_float ) {
		echo '<a class="breo-wa" href="' . esc_url( $wa_float ) . '" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">' . breo_bd_social_icon( 'whatsapp' ) . '</a>'; // phpcs:ignore
	}
	return ob_get_clean();
}

/* ------------------------------------------------------------------------
 * Sharing tags (WhatsApp / Facebook previews) unless an SEO plugin does it.
 * --------------------------------------------------------------------- */

add_action( 'wp_head', function () {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
		return;
	}
	$title = wp_get_document_title();
	$desc  = get_bloginfo( 'description' );
	$img   = '';
	$type  = 'website';
	if ( 'product' === breo_bd_view() ) {
		$p = wc_get_product( get_queried_object_id() );
		if ( $p ) {
			$desc = wp_strip_all_tags( $p->get_short_description() );
			$img  = wp_get_attachment_image_url( $p->get_image_id(), 'large' );
			$type = 'product';
		}
	} elseif ( 'home' === breo_bd_view() ) {
		$desc = 'Authorized Breo distributor in Bangladesh. Portable neck, eye, back and muscle massagers with official warranty and delivery across Bangladesh.';
		$ps   = breo_bd_products();
		if ( $ps ) {
			$first = reset( $ps );
			$img   = wp_get_attachment_image_url( breo_bd_media_id( $first, 'desk' ), 'large' );
		}
	} elseif ( is_singular() ) {
		$ex = get_the_excerpt( get_queried_object_id() );
		if ( $ex ) {
			$desc = $ex;
		}
	}
	echo "\n" . '<meta name="description" content="' . esc_attr( wp_trim_words( $desc, 30, '…' ) ) . '">' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( wp_trim_words( $desc, 30, '…' ) ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( is_singular() ? get_permalink() : home_url( add_query_arg( array() ) ) ) . '">' . "\n";
	if ( $img ) {
		echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
	}
	echo '<meta name="theme-color" content="#1c1c1c">' . "\n";
}, 5 );

/* ------------------------------------------------------------------------
 * Cart: live header count + "Buy now" straight to checkout + no double
 * submits (redirect back to the product after Add to cart).
 * --------------------------------------------------------------------- */

add_filter( 'woocommerce_add_to_cart_fragments', function ( $f ) {
	$c = breo_bd_cart_count();
	$f['span.breo-cart-count'] = '<span class="breo-cart-count" data-count="' . $c . '">' . $c . '</span>';
	return $f;
} );

add_filter( 'woocommerce_add_to_cart_redirect', function ( $url, $product = null ) {
	if ( ! empty( $_REQUEST['breo_buy_now'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return wc_get_checkout_url();
	}
	if ( ! empty( $_REQUEST['breo_atc'] ) && $product instanceof WC_Product ) { // phpcs:ignore WordPress.Security.NonceVerification
		return $product->get_permalink() . '#buy';
	}
	return $url;
}, 20, 2 );
