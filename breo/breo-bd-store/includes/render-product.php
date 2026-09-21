<?php
/**
 * Full-screen product page, after Breo's own detail pages: a sequence of
 * edge-to-edge sections (photo/video + our copy), a real buy panel, and a
 * sticky "name · price · Buy" bar.
 */
defined( 'ABSPATH' ) || exit;

function breo_bd_render_product() {
	$product = wc_get_product( get_queried_object_id() );
	$d       = breo_bd_product_data( $product );
	if ( ! $d ) {
		return '';
	}
	$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	if ( isset( WC()->structured_data ) ) {
		WC()->structured_data->generate_product_data( $product );
	}

	$first_h1 = true;
	$out      = '<div class="breo-pdp" style="--accent:' . esc_attr( $d['accent'] ) . '">';
	$out     .= breo_bd_sticky_bar( $product, $d );
	foreach ( $d['sections'] as $i => $s ) {
		$fn = 'breo_bd_sec_' . $s['type'];
		if ( function_exists( $fn ) ) {
			$out .= call_user_func( $fn, $product, $d, $s, $i );
		}
	}
	return $out . '</div>';
}

function breo_bd_sec_attrs( $s, $extra = '' ) {
	$id = ! empty( $s['id'] ) ? ' id="' . esc_attr( $s['id'] ) . '"' : '';
	return $id . $extra;
}

function breo_bd_sec_text( $s, $tag = 'h2' ) {
	$h = '';
	if ( ! empty( $s['eyebrow'] ) ) {
		$h .= '<p class="breo-eyebrow">' . esc_html( $s['eyebrow'] ) . '</p>';
	}
	if ( ! empty( $s['title'] ) ) {
		$h .= '<' . $tag . ' class="breo-title">' . esc_html( $s['title'] ) . '</' . $tag . '>';
	}
	if ( ! empty( $s['text'] ) ) {
		$h .= '<p class="breo-lead">' . esc_html( $s['text'] ) . '</p>';
	}
	if ( ! empty( $s['points'] ) ) {
		$h .= '<ul class="breo-points">';
		foreach ( $s['points'] as $pt ) {
			$h .= '<li>' . breo_bd_icon( 'check', 18 ) . esc_html( $pt ) . '</li>';
		}
		$h .= '</ul>';
	}
	if ( ! empty( $s['note'] ) ) {
		$h .= '<p class="breo-note">* ' . esc_html( $s['note'] ) . '</p>';
	}
	return $h;
}

function breo_bd_hero_cta( $product ) {
	$buy = breo_bd_buy_url( $product );
	$h   = '<div class="breo-hero-cta">';
	$h  .= $buy ? '<a class="breo-btn" href="' . esc_url( $buy ) . '" rel="nofollow">Buy now</a>' : '<a class="breo-btn" href="#buy">View details</a>';
	$h .= '<span class="breo-hero-cta__price">' . wp_kses_post( breo_bd_price_html( $product ) ) . '</span>';
	return $h . '</div>';
}

/* ---------- hero: edge-to-edge photo, copy over the empty side ---------- */
function breo_bd_sec_hero( $product, $d, $s ) {
	$h  = '<section class="breo-s breo-hero theme-' . esc_attr( $s['theme'] ) . ' align-' . esc_attr( $s['align'] ) . '"' . breo_bd_sec_attrs( $s ) . '>';
	$h .= '<div class="breo-hero__media">' . breo_bd_media( $product, $s['media'], array( 'size' => 'full', 'loading' => 'eager', 'sizes' => '100vw' ) ) . '</div>';
	$h .= '<div class="breo-wrap breo-hero__content" data-reveal>';
	$h .= '<h1 class="breo-eyebrow breo-h1">' . esc_html( $d['name'] ) . '</h1>';
	$h .= '<p class="breo-hero__model" aria-hidden="true"><span class="breo-hero__mark">' . breo_bd_logo( 'breo-logo--inline' ) . '<span>' . esc_html( $d['short_name'] ) . '</span></span></p>';
	$h .= '<p class="breo-hero__title">' . esc_html( $s['title'] ) . '</p>';
	$h .= '<p class="breo-hero__text">' . esc_html( $s['text'] ) . '</p>';
	$h .= breo_bd_hero_cta( $product );
	return $h . '</div></section>';
}

/* ---------- hero_split: colour panel + portrait photo ---------- */
function breo_bd_sec_hero_split( $product, $d, $s ) {
	$h  = '<section class="breo-s breo-hsplit theme-' . esc_attr( $s['theme'] ) . ( ! empty( $s['short'] ) ? ' is-short' : '' ) . '"' . breo_bd_sec_attrs( $s ) . '>';
	$h .= '<div class="breo-hsplit__text" data-reveal><div class="breo-hsplit__in">';
	$h .= '<h1 class="breo-eyebrow breo-h1">' . esc_html( $d['name'] ) . '</h1>';
	$h .= '<p class="breo-hero__model" aria-hidden="true"><span class="breo-hero__mark">' . breo_bd_logo( 'breo-logo--inline' ) . '<span>' . esc_html( $d['short_name'] ) . '</span></span></p>';
	$h .= '<p class="breo-hero__title">' . esc_html( $s['title'] ) . '</p>';
	$h .= '<p class="breo-hero__text">' . esc_html( $s['text'] ) . '</p>';
	$h .= breo_bd_hero_cta( $product );
	$h .= '</div></div>';
	$h .= '<div class="breo-hsplit__media">' . breo_bd_media( $product, $s['media'], array( 'size' => 'full', 'loading' => 'eager', 'sizes' => '(max-width: 900px) 100vw, 50vw' ) ) . '</div>';
	return $h . '</section>';
}

/* ---------- centred copy over a wide image ---------- */
function breo_bd_sec_center( $product, $d, $s ) {
	$full = ! empty( $s['full'] );
	$h    = '<section class="breo-s breo-center bg-' . esc_attr( isset( $s['bg'] ) ? $s['bg'] : 'white' ) . ( $full ? ' is-full' : '' ) . '"' . breo_bd_sec_attrs( $s ) . '>';
	$h   .= '<div class="breo-wrap breo-center__text" data-reveal>' . breo_bd_sec_text( $s ) . '</div>';
	$h   .= '<div class="breo-center__media' . ( $full ? '' : ' breo-wrap' ) . '" data-reveal>' . breo_bd_media( $product, $s['media'], array( 'size' => 'full', 'sizes' => $full ? '100vw' : '(max-width: 1240px) 100vw, 1240px' ) ) . '</div>';
	return $h . '</section>';
}

/* ---------- split: media half + copy half (images or looping video) ---------- */
function breo_bd_sec_split( $product, $d, $s ) {
	$side  = isset( $s['side'] ) ? $s['side'] : 'right';
	$media = breo_bd_media( $product, $s['media'], array( 'size' => 'full', 'sizes' => '(max-width: 900px) 100vw, 50vw' ) );
	$h     = '<section class="breo-s breo-split theme-' . esc_attr( $s['theme'] ) . ' media-' . esc_attr( $side ) . ( $media ? '' : ' no-media' ) . '"' . breo_bd_sec_attrs( $s ) . '>';
	$h    .= $media ? '<div class="breo-split__media">' . $media . '</div>' : '';
	$h   .= '<div class="breo-split__text" data-reveal><div class="breo-split__in">' . breo_bd_sec_text( $s ) . '</div></div>';
	return $h . '</section>';
}

/* ---------- banner: edge-to-edge photo with copy on one side ---------- */
function breo_bd_sec_banner( $product, $d, $s ) {
	$h  = '<section class="breo-s breo-banner theme-' . esc_attr( $s['theme'] ) . ' align-' . esc_attr( $s['align'] ) . '"' . breo_bd_sec_attrs( $s ) . '>';
	$h .= '<div class="breo-banner__media">' . breo_bd_media( $product, $s['media'], array( 'size' => 'full', 'sizes' => '100vw' ) ) . '</div>';
	$h .= '<div class="breo-wrap breo-banner__content" data-reveal><div class="breo-banner__in">' . breo_bd_sec_text( $s ) . '</div></div>';
	return $h . '</section>';
}

/*
 * ---------- film: edge-to-edge video in its own shape, copy over its empty side ----------
 * For wide clips composed with the product on one side (breo.com No.7). The frame keeps
 * the clip's aspect ratio ('ratio', e.g. '1920 / 850'), so nothing is cropped.
 */
function breo_bd_sec_film( $product, $d, $s ) {
	$align = isset( $s['align'] ) ? $s['align'] : 'right';
	$ratio = isset( $s['ratio'] ) && preg_match( '~^\d+(\.\d+)? / \d+(\.\d+)?$~', $s['ratio'] ) ? $s['ratio'] : '16 / 9';
	$h     = '<section class="breo-s breo-film theme-' . esc_attr( isset( $s['theme'] ) ? $s['theme'] : 'dark' ) . ' align-' . esc_attr( $align ) . '"' . breo_bd_sec_attrs( $s, ' style="--film-ratio:' . esc_attr( $ratio ) . '"' ) . '>';
	$h    .= '<div class="breo-film__media">' . breo_bd_media( $product, $s['media'], array( 'size' => 'full', 'sizes' => '100vw' ) ) . '</div>';
	$h    .= '<div class="breo-wrap breo-film__content" data-reveal><div class="breo-film__in">' . breo_bd_sec_text( $s ) . '</div></div>';
	return $h . '</section>';
}

/* ---------- stats: big numbers ---------- */
function breo_bd_sec_stats( $product, $d, $s ) {
	$h = '<section class="breo-s breo-stats theme-' . esc_attr( $s['theme'] ) . '"' . breo_bd_sec_attrs( $s ) . '><div class="breo-wrap breo-stats__grid">';
	foreach ( $s['items'] as $it ) {
		$h .= '<div class="breo-stat" data-reveal><p class="breo-stat__v">' . esc_html( $it[0] ) . '<span>' . esc_html( $it[1] ) . '</span></p><p class="breo-stat__l">' . esc_html( $it[2] ) . '</p></div>';
	}
	return $h . '</div></section>';
}

/* ---------- cards: numbered feature grid ---------- */
function breo_bd_sec_cards( $product, $d, $s ) {
	$h  = '<section class="breo-s breo-cards theme-' . esc_attr( $s['theme'] ) . '"' . breo_bd_sec_attrs( $s ) . '><div class="breo-wrap">';
	$h .= '<div class="breo-cards__head" data-reveal>' . breo_bd_sec_text( array( 'eyebrow' => isset( $s['eyebrow'] ) ? $s['eyebrow'] : '', 'title' => $s['title'] ) ) . '</div><div class="breo-cards__grid">';
	foreach ( $s['items'] as $i => $it ) {
		$h .= '<div class="breo-card2" data-reveal><span class="breo-card2__n">' . esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span><h3>' . esc_html( $it[0] ) . '</h3><p>' . esc_html( $it[1] ) . '</p></div>';
	}
	return $h . '</div></div></section>';
}

/* ---------- duo: two use cases ---------- */
function breo_bd_sec_duo( $product, $d, $s ) {
	$h  = '<section class="breo-s breo-duo theme-' . esc_attr( $s['theme'] ) . '"' . breo_bd_sec_attrs( $s ) . '><div class="breo-wrap">';
	$h .= '<h2 class="breo-title breo-duo__title" data-reveal>' . esc_html( $s['title'] ) . '</h2><div class="breo-duo__grid">';
	foreach ( $s['items'] as $i => $it ) {
		$h .= '<div class="breo-duo__item" data-reveal><span class="breo-duo__n">' . ( $i + 1 ) . '</span><h3>' . esc_html( $it[0] ) . '</h3><p>' . esc_html( $it[1] ) . '</p></div>';
	}
	return $h . '</div></div></section>';
}

/*
 * ---------- tiles: photo cards in a grid ----------
 * For feature photos too small to go full-screen (e.g. the P2's). Items:
 * array( media key, title, text [, 'wide'] ); a wide tile spans two columns.
 */
function breo_bd_sec_tiles( $product, $d, $s ) {
	$h  = '<section class="breo-s breo-tiles theme-' . esc_attr( isset( $s['theme'] ) ? $s['theme'] : 'white' ) . '"' . breo_bd_sec_attrs( $s ) . '><div class="breo-wrap">';
	$h .= '<div class="breo-tiles__head" data-reveal>' . breo_bd_sec_text( $s ) . '</div><div class="breo-tiles__grid">';
	foreach ( $s['items'] as $it ) {
		$wide = isset( $it[3] ) && 'wide' === $it[3];
		$img  = breo_bd_media( $product, $it[0], array( 'size' => 'large', 'sizes' => $wide ? '(max-width: 600px) 100vw, 820px' : '(max-width: 600px) 100vw, 420px' ) );
		$h   .= '<figure class="breo-tile' . ( $wide ? ' is-wide' : '' ) . '" data-reveal>';
		$h   .= $img ? '<div class="breo-tile__media">' . $img . '</div>' : '';
		$h   .= '<figcaption><h3>' . esc_html( $it[1] ) . '</h3><p>' . esc_html( $it[2] ) . '</p></figcaption></figure>';
	}
	return $h . '</div></div></section>';
}

/* ---------- buy panel ---------- */
function breo_bd_sec_buy( $product, $d ) {
	$main    = $product->get_image_id();
	$gallery = array_filter( array_merge( array( $main ), $product->get_gallery_image_ids() ) );
	$cat     = get_term_by( 'slug', $d['category'], 'product_cat' );
	$wa      = breo_bd_whatsapp_url( 'Hi Breo Bangladesh, I want to order the ' . $product->get_name() );
	$ms      = breo_bd_messenger_url( 'product-' . $product->get_id() );

	ob_start();
	?>
	<section class="breo-s breo-buy" id="buy">
		<div class="breo-wrap breo-buy__grid">
			<div class="breo-buy__gallery" data-breo-gallery>
				<div class="breo-buy__main" data-breo-zoom>
					<?php foreach ( $gallery as $n => $gid ) : ?>
						<figure class="breo-buy__slide<?php echo 0 === $n ? ' is-active' : ''; ?>" data-slide="<?php echo (int) $n; ?>" data-full="<?php echo esc_url( (string) wp_get_attachment_image_url( $gid, 'full' ) ); ?>">
							<?php echo wp_get_attachment_image( $gid, 'large', false, array( 'loading' => 0 === $n ? 'eager' : 'lazy', 'sizes' => '(max-width: 900px) 100vw, 640px' ) ); ?>
						</figure>
					<?php endforeach; ?>
					<button type="button" class="breo-buy__open" data-breo-open aria-label="Open image viewer"><?php echo breo_bd_icon( 'expand', 20 ); // phpcs:ignore ?></button>
				</div>
				<?php if ( count( $gallery ) > 1 ) : ?>
					<div class="breo-buy__thumbs" role="tablist" aria-label="Product images">
						<?php foreach ( $gallery as $n => $gid ) : ?>
							<button type="button" class="<?php echo 0 === $n ? 'is-active' : ''; ?>" data-thumb="<?php echo (int) $n; ?>" aria-label="<?php echo esc_attr( 'Image ' . ( $n + 1 ) ); ?>">
								<?php echo wp_get_attachment_image( $gid, 'thumbnail', false, array( 'loading' => 'lazy' ) ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="breo-buy__summary">
				<?php
				if ( function_exists( 'wc_print_notices' ) && WC()->session ) {
					wc_print_notices();
				}
				?>
				<p class="breo-eyebrow"><?php echo esc_html( 'Breo · ' . ( $cat ? $cat->name : $d['tagline'] ) ); ?></p>
				<h2 class="breo-buy__name"><?php echo esc_html( $product->get_name() ); ?></h2>
				<div class="breo-buy__price"><?php echo wp_kses_post( breo_bd_price_html( $product ) ); ?></div>
				<?php do_action( 'breo_bd_buy_after_price', $product ); // e.g. Breo EMI Plans ?>
				<p class="breo-buy__short"><?php echo esc_html( wp_strip_all_tags( $product->get_short_description() ) ); ?></p>
				<ul class="breo-badges">
					<?php foreach ( $d['badges'] as $b ) : ?>
						<li><?php echo breo_bd_icon( 'spark', 15 ) . esc_html( $b ); // phpcs:ignore ?></li>
					<?php endforeach; ?>
				</ul>
				<dl class="breo-buy__meta">
					<div><dt>Model</dt><dd><?php echo esc_html( $d['model'] ); ?></dd></div>
					<div><dt>Colour</dt><dd><?php echo esc_html( $d['color'] ); ?></dd></div>
					<div><dt>SKU</dt><dd><?php echo esc_html( $product->get_sku() ); ?></dd></div>
				</dl>

				<?php if ( $product->is_purchasable() && $product->is_in_stock() ) : ?>
					<form class="breo-buy__form" method="post" action="<?php echo esc_url( $product->get_permalink() ); ?>">
						<div class="breo-qty" data-breo-qty>
							<button type="button" data-step="-1" aria-label="Decrease quantity">−</button>
							<input type="number" name="quantity" value="1" min="1" max="<?php echo esc_attr( $product->get_max_purchase_quantity() > 0 ? $product->get_max_purchase_quantity() : 99 ); ?>" inputmode="numeric" aria-label="Quantity">
							<button type="button" data-step="1" aria-label="Increase quantity">+</button>
						</div>
						<input type="hidden" name="add-to-cart" value="<?php echo (int) $product->get_id(); ?>">
						<button type="submit" name="breo_atc" value="1" class="breo-btn breo-btn--ghost">Add to cart</button>
						<button type="submit" name="breo_buy_now" value="1" class="breo-btn">Buy now</button>
					</form>
				<?php elseif ( ! $product->is_in_stock() ) : ?>
					<p class="breo-buy__na">Sold out for now. Message us and we'll tell you when the next shipment lands.</p>
				<?php else : ?>
					<p class="breo-buy__na">Launching soon. Message us to reserve yours.</p>
				<?php endif; ?>

				<?php do_action( 'breo_bd_buy_after_form', $product ); // e.g. Breo Smart Delivery ?>

				<?php if ( $wa || $ms ) : ?>
					<div class="breo-buy__chat">
						<span class="breo-buy__chat-h">Order or ask on</span>
						<?php if ( $wa ) : ?>
							<a class="breo-chat breo-chat--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo breo_bd_social_icon( 'whatsapp' ); // phpcs:ignore ?> WhatsApp</a>
						<?php endif; ?>
						<?php if ( $ms ) : ?>
							<a class="breo-chat breo-chat--ms" href="<?php echo esc_url( $ms ); ?>" target="_blank" rel="noopener"><?php echo breo_bd_social_icon( 'messenger' ); // phpcs:ignore ?> Messenger</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<ul class="breo-buy__trust">
					<?php foreach ( breo_bd_trust_items() as $t ) : ?>
						<li><?php echo breo_bd_icon( $t[0], 20 ); // phpcs:ignore ?><span><?php echo esc_html( $t[1] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* ---------- what's in the box ---------- */
function breo_bd_sec_box( $product, $d, $s ) {
	$photo = ! empty( $s['media'] ) ? breo_bd_media( $product, $s['media'], array( 'size' => 'large', 'sizes' => '(max-width: 900px) 100vw, 560px' ) ) : '';
	$h     = '<section class="breo-s breo-box" id="box"><div class="breo-wrap">';
	$h    .= '<h2 class="breo-title breo-box__title" data-reveal>What\'s in the box</h2>';
	$h    .= '<div class="breo-box__grid' . ( $photo ? ' has-photo' : '' ) . '">';
	if ( $photo ) {
		$h .= '<div class="breo-box__photo" data-reveal>' . $photo . '</div>';
	}
	if ( $photo ) {
		// Next to a packaging photo, a clean checklist reads better than icon tiles.
		$h .= '<ul class="breo-box__list" data-reveal>';
		foreach ( $d['box'] as $it ) {
			$h .= '<li>' . breo_bd_icon( 'check', 20 ) . '<span>' . esc_html( $it[0] ) . '</span></li>';
		}
		return $h . '</ul></div></div></section>';
	}
	$h .= '<ul class="breo-box__items">';
	foreach ( $d['box'] as $it ) {
		$img = ! empty( $it[1] ) ? breo_bd_media( $product, $it[1], array( 'size' => 'medium' ) ) : '';
		$h  .= '<li data-reveal><span class="breo-box__img">' . ( $img ? $img : breo_bd_icon( 'box', 30 ) ) . '</span><span>' . esc_html( $it[0] ) . '</span></li>';
	}
	return $h . '</ul></div></div></section>';
}

/* ---------- specifications (+ dimension drawing) ---------- */
function breo_bd_sec_specs( $product, $d, $s ) {
	$draw = ! empty( $s['media'] ) ? breo_bd_media( $product, $s['media'], array( 'size' => 'large', 'sizes' => '480px' ) ) : '';
	$h    = '<section class="breo-s breo-specs" id="specs"><div class="breo-wrap">';
	$h   .= '<h2 class="breo-title" data-reveal>Specifications</h2>';
	$h   .= '<div class="breo-specs__grid' . ( $draw ? ' has-draw' : '' ) . '">';
	if ( $draw ) {
		$h .= '<div class="breo-specs__draw" data-reveal>' . $draw . '</div>';
	}
	$h .= '<table class="breo-specs__table" data-reveal><tbody>';
	foreach ( $d['specs'] as $k => $v ) {
		$h .= '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
	}
	$h  .= '</tbody></table>';
	$h  .= function_exists( 'breo_bd_support_cards_html' ) ? breo_bd_support_cards_html( $product ) : '';
	return $h . '</div></div></section>';
}

/* ---------- how to use + FAQ ---------- */
function breo_bd_product_faq( $d, $product = null ) {
	$faq = $d['faq'];
	if ( $product && '' !== $product->get_price() ) {
		$w = breo_bd_warranty_period();
		array_unshift( $faq, array(
			'What is the price of the Breo ' . $d['short_name'] . ' in Bangladesh?',
			breo_bd_plain_price( $product ) . ' at Breo Bangladesh, the authorized Breo distributor. It includes ' . ( $w ? 'a ' . $w : 'the' ) . ' official warranty, and you can pay cash on delivery anywhere in Bangladesh.',
		) );
	}
	$faq[] = array( 'Is this a genuine Breo product?', 'Yes. Breo Bangladesh is an authorized Breo distributor, and every unit is officially imported.' );
	if ( breo_bd_warranty_period() ) {
		$faq[] = array( 'What warranty do I get?', 'A ' . breo_bd_warranty_period() . ' official warranty against manufacturing defects, handled by us in Bangladesh. See our Warranty Policy for details.' );
	}
	$faq[] = array( 'Is it a medical device?', 'No. Breo massagers are for relaxation and everyday wellness. They don\'t diagnose or treat any disease. If you have a medical condition, ask your doctor first.' );
	return $faq;
}

function breo_bd_sec_usage_faq( $product, $d ) {
	ob_start();
	?>
	<section class="breo-s breo-uf" id="faq">
		<div class="breo-wrap breo-uf__grid">
			<div class="breo-uf__usage" data-reveal>
				<h2 class="breo-title">How to use</h2>
				<ol class="breo-steps">
					<?php foreach ( $d['usage'] as $u ) : ?>
						<li><?php echo esc_html( $u ); ?></li>
					<?php endforeach; ?>
				</ol>
			</div>
			<div class="breo-uf__faq" data-reveal>
				<h2 class="breo-title">Questions, answered</h2>
				<div class="breo-faq">
					<?php foreach ( breo_bd_product_faq( $d, $product ) as $q ) : ?>
						<details><summary><?php echo esc_html( $q[0] ); ?></summary><p><?php echo esc_html( $q[1] ); ?></p></details>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* ---------- other products ---------- */
function breo_bd_sec_related( $product ) {
	$others = array_filter( breo_bd_products(), function ( $p ) use ( $product ) {
		return $p->get_id() !== $product->get_id();
	} );
	if ( ! $others ) {
		return '';
	}
	$h = '<section class="breo-s breo-related"><div class="breo-wrap"><h2 class="breo-title" data-reveal>You may also like</h2><div class="breo-grid breo-grid--3">';
	foreach ( $others as $p ) {
		$h .= breo_bd_product_card( $p );
	}
	$h .= '</div>' . breo_bd_help_band( 'Hi Breo Bangladesh, I have a question about the ' . $product->get_name() ) . '</div></section>';
	return $h;
}

/* ---------- sticky "name · price · buy" bar ---------- */
function breo_bd_sticky_bar( $product, $d ) {
	$h  = '<div class="breo-sticky" data-breo-sticky aria-hidden="true"><div class="breo-wrap breo-sticky__in">';
	$h .= '<p class="breo-sticky__name"><strong>' . esc_html( $d['short_name'] ) . '</strong><span>' . esc_html( $d['tagline'] ) . '</span></p>';
	$h .= '<nav class="breo-sticky__nav" aria-label="On this page"><a href="#overview" tabindex="-1">Overview</a><a href="#specs" tabindex="-1">Specs</a><a href="#faq" tabindex="-1">FAQ</a></nav>';
	$buy = breo_bd_buy_url( $product );
	$h  .= '<div class="breo-sticky__act"><span class="breo-sticky__price">' . wp_kses_post( breo_bd_price_html( $product ) ) . '</span>'
		. ( $buy ? '<a class="breo-btn breo-btn--sm" href="' . esc_url( $buy ) . '" rel="nofollow" tabindex="-1">Buy now</a>' : '<a class="breo-btn breo-btn--sm" href="#buy" tabindex="-1">Details</a>' )
		. '</div>';
	return $h . '</div></div>';
}

/* ---------- shared: product card + help band ---------- */
function breo_bd_product_card( $p ) {
	$d   = breo_bd_product_data( $p );
	$img = breo_bd_media( $p, 'tile', array( 'size' => 'woocommerce_single', 'sizes' => '(max-width: 700px) 50vw, 400px' ) );
	if ( ! $img ) {
		$img = $p->get_image_id() ? wp_get_attachment_image( $p->get_image_id(), 'woocommerce_single' ) : wc_placeholder_img();
	}
	$name = $d ? $d['short_name'] : $p->get_name();
	$sub  = $d ? $d['tagline'] : '';
	$h    = '<article class="breo-pcard" data-reveal>';
	$h   .= '<a class="breo-pcard__img" href="' . esc_url( $p->get_permalink() ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
	$h   .= '<div class="breo-pcard__body">';
	$h   .= '<h3 class="breo-pcard__name"><a href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( $name ) . '</a></h3>';
	if ( $sub ) {
		$h .= '<p class="breo-pcard__sub">' . esc_html( $sub ) . '</p>';
	}
	$h .= '<p class="breo-pcard__price">' . wp_kses_post( breo_bd_price_html( $p ) ) . '</p>';
	$h .= '<a class="breo-btn breo-btn--sm breo-btn--ghost" href="' . esc_url( $p->get_permalink() ) . '">Learn more</a>';
	return $h . '</div></article>';
}

function breo_bd_help_band( $wa_text = '' ) {
	$wa    = breo_bd_whatsapp_url( $wa_text );
	$phone = breo_bd_opt( 'phone' );
	if ( ! $wa && ! $phone ) {
		return '';
	}
	$h = '<div class="breo-help" data-reveal><div><h2>Not sure which one is right for you?</h2><p>Tell us where it hurts and we\'ll recommend the right Breo device.</p></div><div class="breo-help__btns">';
	if ( $wa ) {
		$h .= '<a class="breo-btn" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . breo_bd_social_icon( 'whatsapp' ) . ' Chat on WhatsApp</a>';
	}
	if ( $phone ) {
		$h .= '<a class="breo-btn breo-btn--ghost" href="' . esc_url( breo_bd_tel( $phone ) ) . '">Call ' . esc_html( $phone ) . '</a>';
	}
	return $h . '</div></div>';
}
