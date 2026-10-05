<?php
/**
 * The designed homepage:
 *   [aunstore_home hero_ids="1,2,3" feat_ids="1,2,3,4,5" home_img="" office_img="" portable_img="" why_bg=""]
 *
 * Rendered by the plugin (not UX Builder) so the layout, motion and interactions are exactly as designed:
 * real-photo hero (Movies / Sport / Gaming) → feature marquee → interactive lineup explorer → smart-feature bento → videos (Store Details) → shop by space → customer reviews →
 * brand stats → warranty walkthrough → Smart Finder call-out → FAQ.
 *
 * The attributes are attachment ids for the page's own photography, so the page looks complete whatever
 * state the products are in. Product data (names, specs, prices, links) is read live from WooCommerce:
 *   · visitors see the lineup explorer once products are PUBLISHED;
 *   · while they are still drafts, shop managers see the explorer in preview mode (with a notice).
 * Styles: design/home-css.php · behaviour: design/home-js.php (both printed inline with WP Rocket guards).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/home-css.php';
require_once __DIR__ . '/home-js.php';
require_once __DIR__ . '/illustrations.php';

/** Facts for one product card: the `_aunstore_highlights` meta, or a best-effort parse of the short description. */
function aunstore_product_highlights( $product ) {
	$h = $product->get_meta( '_aunstore_highlights' );
	if ( is_array( $h ) && ! empty( $h['short'] ) ) return $h;

	$short = $product->get_short_description();
	$li    = array();
	if ( preg_match_all( '~<li>([^:<]+):\s*([^<]+)</li>~', $short, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $row ) $li[ trim( $row[1] ) ] = trim( $row[2] );
	}
	$res  = $li['Native Resolution'] ?? '';
	$tags = array();
	foreach ( array( 'home-theater-projector' => 'home', 'office-projector' => 'office', 'mini-projector' => 'portable' ) as $slug => $tag ) {
		if ( has_term( $slug, 'product_cat', $product->get_id() ) ) $tags[] = $tag;
	}
	return array(
		'short'      => preg_match( '/^AUN\s+(\S+(?:\s+Pro)?)/i', $product->get_name(), $n ) ? $n[1] : $product->get_name(),
		'best_for'   => preg_match( '~Best for:\s*([^<]+)</p>~', $short, $b ) ? trim( $b[1] ) : '',
		'resolution' => false !== stripos( $res, 'Full HD' ) ? 'Full HD 1080p' : ( $res ? 'HD 720p' : '' ),
		'res_note'   => false !== strpos( $res, '4K' ) ? '4K support' : '',
		'lumens'     => (int) preg_replace( '/\D+/', '', $li['Brightness'] ?? '' ),
		'os'         => str_replace( 'Genuine ', '', $li['Operating System'] ?? '' ),
		'max_screen' => (int) $product->get_meta( '_aun_max_screen_size' ),
		'tags'       => $tags,
	);
}

/**
 * The projectors, in shop order: everything in the "projectors" category plus everything the importer
 * created (so a product still shows if its category was changed).
 *
 * @param bool $with_drafts include draft products (for the hero facts and the manager preview)
 * @return array<int,array>
 */
function aunstore_lineup_models( $with_drafts = false ) {
	if ( ! function_exists( 'wc_get_product' ) ) return array();
	$base = array(
		'post_type'      => 'product',
		'post_status'    => $with_drafts ? array( 'publish', 'draft' ) : array( 'publish' ),
		'posts_per_page' => 12,
		'fields'         => 'ids',
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
	);
	$ids = array_unique( array_merge(
		get_posts( $base + array( 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'projectors' ) ) ) ),
		get_posts( $base + array( 'meta_key' => '_aunstore_package', 'meta_compare' => 'EXISTS' ) )
	) );

	$out = array();
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p || ! $p->get_image_id() ) continue;
		$draft = 'publish' !== $p->get_status();
		$out[] = array(
			'h'     => aunstore_product_highlights( $p ),
			'name'  => $p->get_name(),
			'url'   => $draft ? get_preview_post_link( $id ) : get_permalink( $id ),
			'img'   => wp_get_attachment_image_url( $p->get_image_id(), 'large' ),
			'thumb' => wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' ),
			'mid'   => wp_get_attachment_image_url( $p->get_image_id(), 'medium' ),
			'price' => $p->get_price_html(),
			'order' => (int) $p->get_menu_order(),
			'live'  => ! $draft,
			'key'   => (string) get_post_meta( $id, '_aunstore_package', true ),
		);
	}
	usort( $out, function ( $a, $b ) { return $a['order'] <=> $b['order']; } );
	return $out;
}

function aunstore_page_link( $slug, $fallback = '' ) {
	$p = get_page_by_path( $slug );
	return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : ( $fallback ?: home_url( '/' . $slug . '/' ) );
}

/**
 * The studio views of a projector for the explorer's turntable: front and back, from
 * assets/views/<package key>-<view>.webp (cut out and scaled alike by tools/cutout-views.py).
 *
 * @return array<string,string> label => URL, in turning order
 */
function aunstore_device_views( $key ) {
	$out = array();
	if ( '' === $key ) return $out;
	foreach ( array( 'front' => 'Front', 'back' => 'Back' ) as $view => $label ) {
		$file = 'assets/views/' . sanitize_file_name( $key . '-' . $view ) . '.webp';
		if ( is_readable( AUNSTORE_CORE_DIR . $file ) ) $out[ $label ] = plugins_url( $file, AUNSTORE_CORE_DIR . 'aunstore-core.php' );
	}
	return $out;
}

/** Which projector took a real projection photo ("U001 Pro"), by the photo's original (BD) id. '' if unknown. */
function aunstore_shot_model( $attachment_id ) {
	$src = (int) get_post_meta( $attachment_id, '_aunstore_src_id', true );
	$by  = array( 7956 => 'U001 Pro', 7957 => 'U001 Pro', 7958 => 'U001 Pro', 7946 => 'U002 Pro', 7947 => 'U002 Pro', 7948 => 'U002 Pro', 7949 => 'U002 Pro', 8247 => 'A45 Pro', 8248 => 'A45 Pro', 8249 => 'A45 Pro', 8250 => 'A45 Pro' );
	return $by[ $src ] ?? '';
}

/**
 * The feature bento: what a tile says about its picture, by the picture's original (BD) id. Each picture comes
 * from a product page, so the tile names that model and only claims what that model's spec table says.
 * Pictures without an entry here are skipped.
 */
function aunstore_feature_tile( $attachment_id ) {
	$src   = (int) get_post_meta( $attachment_id, '_aunstore_src_id', true );
	$tiles = array(
		8575 => array( 'U001 Pro', 'fa-tv', 'Genuine Android TV, apps included', 'Android TV 14 with Netflix, YouTube and Prime Video preloaded &mdash; no streaming stick, no extra box.', '50% 40%' ),
		8739 => array( 'U002 Pro', 'fa-crosshairs', 'Sharp and square in seconds', 'TOF laser auto focus and auto keystone: set it down at an angle and the picture straightens and sharpens itself.', '50% 30%' ),
		8971 => array( 'A45 Pro', 'fa-futbol', 'Match day at 150 inches', 'A Full HD picture up to 150&Prime; from 4.45 m away &mdash; room for the whole team.', '50% 55%' ),
		8573 => array( 'U001 Pro', 'fa-gamepad', 'Console-ready', 'HDMI input up to 4K with ARC and CEC &mdash; plug in a console, a streaming stick or a laptop.', '50% 45%' ),
		8737 => array( 'U002 Pro', 'fa-mobile-screen-button', 'Cast from your phone', 'AirPlay and Miracast over dual-band Wi-Fi &mdash; photos, videos and apps, straight to the big screen.', '50% 50%' ),
	);
	if ( ! isset( $tiles[ $src ] ) ) return null;
	return array_combine( array( 'model', 'icon', 'title', 'text', 'pos' ), $tiles[ $src ] );
}

/**
 * Customer reviews for the homepage. Only what customers really posted: approved WooCommerce reviews on
 * published products. Cards show 4–5 star reviews that have some text; the score and count use every rated review.
 *
 * @return array{items:array,avg:float,count:int}
 */
function aunstore_home_reviews( $limit = 6 ) {
	$none = array( 'items' => array(), 'avg' => 0.0, 'count' => 0 );
	if ( ! function_exists( 'wc_get_product' ) ) return $none;
	$comments = get_comments( array( 'post_type' => 'product', 'post_status' => 'publish', 'status' => 'approve', 'type' => 'review', 'number' => 300, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ) );
	$sum = 0;
	$n   = 0;
	$out = array();
	foreach ( $comments as $c ) {
		$r = (int) get_comment_meta( $c->comment_ID, 'rating', true );
		if ( $r < 1 || $r > 5 || 'publish' !== get_post_status( $c->comment_post_ID ) ) continue; // the query can be served from cache
		$sum += $r;
		$n++;
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $c->comment_content ) ) );
		if ( $r < 4 || strlen( $text ) < 30 || count( $out ) >= $limit ) continue;
		$words  = preg_split( '/\s+/', trim( $c->comment_author ) );
		$author = $words[0] . ( count( $words ) > 1 ? ' ' . mb_substr( end( $words ), 0, 1 ) . '.' : '' );
		$out[]  = array(
			'rating'   => $r,
			'text'     => $text,
			'author'   => $author ?: 'Customer',
			'date'     => mysql2date( 'M Y', $c->comment_date ),
			'product'  => (int) $c->comment_post_ID,
			'verified' => '1' === (string) get_comment_meta( $c->comment_ID, 'verified', true ),
		);
	}
	return array( 'items' => $out, 'avg' => $n ? round( $sum / $n, 1 ) : 0.0, 'count' => $n );
}

add_shortcode( 'aunstore_home', function ( $atts ) {
	$a = shortcode_atts( array( 'hero_ids' => '', 'feat_ids' => '', 'home_img' => '', 'office_img' => '', 'portable_img' => '', 'why_bg' => '' ), $atts, 'aunstore_home' );

	$pic = function ( $id, $size = 'large' ) {
		return $id ? (string) wp_get_attachment_image_url( (int) $id, $size ) : '';
	};

	$all       = aunstore_lineup_models( true ); // drafts too: the hero shows a model's facts even before it launches
	$published = array_values( array_filter( $all, function ( $m ) { return $m['live']; } ) );
	$preview   = ! $published && $all && current_user_can( 'edit_products' );
	$models    = $published ?: ( $preview ? $all : array() );

	// Hero: up to three real projection photos — Movies, Sport, Gaming, in that order — each paired with
	// the projector that took it (so the card can show that model's real specs and link to it).
	$by_short = array();
	foreach ( $all as $m ) $by_short[ $m['h']['short'] ] = $m;
	$scene_defs = array( array( 'Movies', 'fa-film', 'cinema' ), array( 'Sport', 'fa-futbol', 'sport' ), array( 'Gaming', 'fa-gamepad', 'gaming' ) );
	$slides     = array();
	foreach ( array_filter( array_map( 'absint', explode( ',', (string) $a['hero_ids'] ) ) ) as $id ) {
		if ( count( $slides ) >= count( $scene_defs ) || ! wp_attachment_is_image( $id ) ) continue;
		$def      = $scene_defs[ count( $slides ) ];
		$shot_by  = aunstore_shot_model( $id );
		$slides[] = array( 'id' => $id, 'label' => $def[0], 'icon' => $def[1], 'word' => $def[2], 'by' => $shot_by, 'model' => $by_short[ $shot_by ] ?? null );
	}
	$shop     = aunstore_projectors_url();
	$finder   = aunstore_page_link( 'projector-finder' );
	$cat      = function ( $slug ) use ( $shop ) {
		$l = taxonomy_exists( 'product_cat' ) ? get_term_link( $slug, 'product_cat' ) : null;
		return ( $l && ! is_wp_error( $l ) ) ? $l : $shop;
	};
	$e = 'esc_html';

	ob_start();
	echo '<style id="aunx-css" data-no-optimize="1" data-no-minify="1">' . aunstore_home_css() . '</style>';
	?>
<div class="aunx">

<section class="aunx-sec aunx-hero<?php echo $slides ? ' has-media' : ''; ?>" data-aunx-hero>
	<div class="aunx-wrap aunx-hero-in">
		<div class="aunx-hero-copy">
			<span class="aunx-pill"><i class="fa-solid fa-film" aria-hidden="true"></i> AUN &middot; All You Need</span>
			<h1>Get lost in the <em>world of <span class="aunx-word" data-aunx-word><?php echo $e( $slides ? $slides[0]['word'] : 'cinema' ); ?></span></em></h1>
			<p>Smart Full HD projectors with Android TV, auto focus and a 1-year warranty &mdash; shipped to your door, worldwide.</p>
			<div class="aunx-cta">
				<a class="aunx-btn aunx-btn-pri" href="<?php echo $models ? '#aunx-lineup' : esc_url( $shop ); ?>">Explore the lineup <i class="fa-solid fa-arrow-<?php echo $models ? 'down' : 'right'; ?>" aria-hidden="true"></i></a>
				<a class="aunx-btn aunx-btn-ghost" href="<?php echo esc_url( $finder ); ?>"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Find mine in 60 seconds</a>
			</div>
			<ul class="aunx-hero-trust">
				<li><i class="fa-solid fa-earth-americas" aria-hidden="true"></i> Worldwide shipping</li>
				<li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> 1-year warranty</li>
				<li><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Parts shipped to you</li>
			</ul>
		</div>
		<?php if ( $slides ) : ?>
		<div class="aunx-hero-visual">
			<div class="aunx-hero-media">
				<?php
				foreach ( $slides as $i => $sl ) {
					echo wp_get_attachment_image( $sl['id'], 'full', false, array(
						'class'         => 'aunx-hero-shot' . ( 0 === $i ? ' is-active skip-lazy' : '' ),
						'alt'           => 'Real projection photo' . ( $sl['by'] ? ', shot on the AUN ' . $sl['by'] : '' ),
						'loading'       => 0 === $i ? 'eager' : 'lazy',
						'fetchpriority' => 0 === $i ? 'high' : 'low',
						'decoding'      => 'async',
						'sizes'         => '(max-width: 849px) 100vw, 66vw',
						'data-word'     => $sl['word'],
					) );
				}
				?>
			</div>
			<div class="aunx-hcard">
				<?php if ( count( $slides ) > 1 ) : ?>
					<div class="aunx-hero-tabs" role="group" aria-label="Show a real projection photo" style="--n:<?php echo count( $slides ); ?>">
						<?php foreach ( $slides as $i => $sl ) : ?>
							<button type="button" class="aunx-hero-tab<?php echo 0 === $i ? ' is-active' : ''; ?>" aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>"><i class="fa-solid <?php echo esc_attr( $sl['icon'] ); ?>" aria-hidden="true"></i><?php echo $e( $sl['label'] ); ?></button>
						<?php endforeach; ?>
					</div>
					<span class="aunx-hero-prog" aria-hidden="true"><i></i></span>
				<?php endif; ?>
				<div class="aunx-hshots">
					<?php
					foreach ( $slides as $i => $sl ) :
						$m    = $sl['model'];
						$link = $m && ( $m['live'] || $preview ); // never send a visitor to a product that is still a draft
						$tag  = $link ? 'a' : 'div';
						?>
						<<?php echo $tag; ?> class="aunx-hshot<?php echo 0 === $i ? ' is-active' : ''; ?><?php echo $m ? '' : ' no-model'; ?>"<?php echo $link ? ' href="' . esc_url( $m['url'] ) . '"' : ''; ?>>
							<?php if ( $m ) : ?><img class="aunx-hshot-img" src="<?php echo esc_url( $m['mid'] ); ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
							<span class="aunx-hshot-b">
								<small><i class="fa-solid fa-camera" aria-hidden="true"></i> Real photo<?php echo $sl['by'] ? ' &middot; shot on' : ''; ?></small>
								<?php if ( $sl['by'] ) : ?><b><?php echo $e( $sl['by'] ); ?></b><?php endif; ?>
								<?php if ( $m && ( $m['h']['lumens'] || $m['h']['max_screen'] ) ) : ?>
									<span class="aunx-hshot-sp">
										<?php if ( $m['h']['lumens'] ) : ?><span><?php echo (int) $m['h']['lumens']; ?> ANSI lumens</span><?php endif; ?>
										<?php if ( $m['h']['max_screen'] ) : ?><span>Up to <?php echo (int) $m['h']['max_screen']; ?>&Prime;</span><?php endif; ?>
									</span>
								<?php endif; ?>
							</span>
							<?php if ( $link ) : ?><span class="aunx-hshot-go"><span class="screen-reader-text">View <?php echo $e( $m['h']['short'] ); ?></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span><?php endif; ?>
						</<?php echo $tag; ?>>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</div>
</section>

<section class="aunx-sec aunx-marq" aria-label="Highlights">
	<div class="aunx-marq-track">
		<?php
		$items = array(
			array( 'fa-earth-americas', 'Worldwide shipping' ), array( 'fa-shield-halved', '1-year warranty' ),
			array( 'fa-box', 'Replacement parts shipped to you' ), array( 'fa-circle-play', 'Video repair guides' ),
			array( 'fa-tv', 'Genuine Android TV' ), array( 'fa-film', 'Full HD 1080p' ),
			array( 'fa-crosshairs', 'TOF laser auto focus' ), array( 'fa-fingerprint', 'Verified genuine' ),
		);
		for ( $r = 0; $r < 2; $r++ ) {
			foreach ( $items as $it ) {
				echo '<span class="aunx-marq-item"' . ( $r ? ' aria-hidden="true"' : '' ) . '><i class="fa-solid ' . esc_attr( $it[0] ) . '"></i>' . $e( $it[1] ) . '</span>';
			}
		}
		?>
	</div>
</section>

<?php if ( $models ) : $total = count( $models ); ?>
<section class="aunx-sec aunx-lineup" id="aunx-lineup">
	<div class="aunx-wrap">
		<div class="aunx-head" data-rv>
			<span class="aunx-eyebrow"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> The lineup</span>
			<h2>Find the one made for your room</h2>
			<p>Tap a model to explore it &mdash; or filter by how you'll use it.</p>
		</div>
		<?php if ( $preview ) : ?>
			<p class="aunx-note"><i class="fa-solid fa-eye" aria-hidden="true"></i> <strong>Preview &mdash; only you can see this section.</strong> These products are still drafts. Publish them (with prices) and visitors will see it too.</p>
		<?php endif; ?>
		<div class="aunx-filters" role="group" aria-label="Filter projectors" data-rv>
			<?php
			$filters = array( 'all' => array( 'fa-border-all', 'All models' ), 'home' => array( 'fa-couch', 'Home cinema' ), 'office' => array( 'fa-briefcase', 'Office &amp; class' ), 'portable' => array( 'fa-suitcase-rolling', 'Portable' ), 'small' => array( 'fa-compress', 'Small rooms' ) );
			$used    = array( 'all' => true );
			foreach ( $models as $m ) foreach ( (array) $m['h']['tags'] as $t ) $used[ $t ] = true;
			foreach ( $filters as $key => $f ) {
				if ( empty( $used[ $key ] ) ) continue;
				echo '<button type="button" class="aunx-filter' . ( 'all' === $key ? ' is-active' : '' ) . '" data-filter="' . esc_attr( $key ) . '" aria-pressed="' . ( 'all' === $key ? 'true' : 'false' ) . '"><i class="fa-solid ' . esc_attr( $f[0] ) . '" aria-hidden="true"></i>' . $f[1] . '</button>';
			}
			?>
		</div>

		<div class="aunx-exp" data-aunx-exp data-rv>
			<div class="aunx-exp-stage">
				<span class="aunx-exp-spot"></span><span class="aunx-exp-ring"></span><span class="aunx-exp-floor"></span>
				<?php
				// Each model turns on a turntable: front, then round to the back (where the ports are), and back again.
				// Only the first model's pictures load with the page; the others load when they are picked.
				foreach ( $models as $i => $m ) :
					$views = aunstore_device_views( $m['key'] );
					if ( ! $views ) $views = array( 'Front' => $m['img'] );
					?>
					<div class="aunx-exp-img<?php echo 0 === $i ? ' is-active' : ''; ?>" data-i="<?php echo (int) $i; ?>" role="img" aria-label="<?php echo esc_attr( $m['name'] . ( count( $views ) > 1 ? ' — ' . strtolower( implode( ', ', array_keys( $views ) ) ) . ' views' : '' ) ); ?>">
						<?php
						$n = 0;
						foreach ( $views as $label => $url ) {
							$attr = 0 === $i ? 'src="' . esc_url( $url ) . '"' . ( $n ? ' loading="lazy"' : '' ) : 'data-src="' . esc_url( $url ) . '"';
							echo '<img class="aunx-view' . ( 0 === $n ? ' is-on' : '' ) . ( 0 === $i && 0 === $n ? ' skip-lazy' : '' ) . '" ' . $attr . ' alt="" decoding="async" width="1200" height="900">';
							$n++;
						}
						?>
					</div>
					<?php
				endforeach;
				?>
			</div>
			<div class="aunx-exp-info">
				<?php foreach ( $models as $i => $m ) : $h = $m['h']; ?>
					<article class="aunx-panel<?php echo 0 === $i ? ' is-active' : ''; ?>" data-i="<?php echo (int) $i; ?>">
						<span class="aunx-panel-k">Model <?php echo sprintf( '%02d', $i + 1 ); ?> <i>/ <?php echo sprintf( '%02d', $total ); ?></i></span>
						<h3><?php echo $e( $h['short'] ); ?></h3>
						<?php if ( $h['best_for'] ) : ?><p class="aunx-best"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Best for: <?php echo $e( $h['best_for'] ); ?></p><?php endif; ?>
						<ul class="aunx-specs">
							<?php if ( $h['lumens'] ) : ?><li><i class="fa-solid fa-sun" aria-hidden="true"></i><b data-count="<?php echo (int) $h['lumens']; ?>"><?php echo (int) $h['lumens']; ?></b><span>ANSI lumens</span></li><?php endif; ?>
							<?php if ( $h['max_screen'] ) : ?><li><i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i><b data-count="<?php echo (int) $h['max_screen']; ?>" data-suffix="&Prime;"><?php echo (int) $h['max_screen']; ?>&Prime;</b><span>Max screen</span></li><?php endif; ?>
							<?php if ( $h['resolution'] ) : ?><li><i class="fa-solid fa-film" aria-hidden="true"></i><b><?php echo $e( $h['resolution'] ); ?></b><span><?php echo $h['res_note'] ? $e( $h['res_note'] ) : 'Native resolution'; ?></span></li><?php endif; ?>
							<?php if ( $h['os'] ) : ?><li><i class="fa-brands fa-android" aria-hidden="true"></i><b><?php echo $e( $h['os'] ); ?></b><span>Smart apps built in</span></li><?php endif; ?>
						</ul>
						<div class="aunx-panel-foot">
							<?php if ( $m['price'] ) : ?><div class="aunx-price"><?php echo wp_kses_post( $m['price'] ); ?></div><?php endif; ?>
							<a class="aunx-btn aunx-btn-pri" href="<?php echo esc_url( $m['url'] ); ?>">View <?php echo $e( $h['short'] ); ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="aunx-rail" role="tablist" aria-label="Projector models" data-rv>
			<?php foreach ( $models as $i => $m ) : ?>
				<button type="button" class="aunx-tab<?php echo 0 === $i ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" data-i="<?php echo (int) $i; ?>" data-tags="<?php echo esc_attr( implode( ' ', (array) $m['h']['tags'] ) ); ?>">
					<img src="<?php echo esc_url( $m['thumb'] ); ?>" alt="" loading="lazy" decoding="async">
					<span><b><?php echo $e( $m['h']['short'] ); ?></b><small><?php echo $e( $m['h']['resolution'] ); ?></small></span>
					<i class="aunx-tab-bar" aria-hidden="true"></i>
				</button>
			<?php endforeach; ?>
		</div>
		<?php if ( ! $preview ) : ?>
			<p class="aunx-lineup-all" data-rv><a href="<?php echo esc_url( $shop ); ?>">See all projectors <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></p>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php
// Smart-feature bento: one big picture + four, each a real product-page image with the model it shows.
$feats = array();
foreach ( array_filter( array_map( 'absint', explode( ',', (string) $a['feat_ids'] ) ) ) as $id ) {
	if ( wp_attachment_is_image( $id ) && ( $t = aunstore_feature_tile( $id ) ) ) $feats[] = array( 'id' => $id ) + $t;
}
if ( count( $feats ) >= 3 ) :
	?>
<section class="aunx-sec aunx-feats">
	<div class="aunx-wrap">
		<div class="aunx-head aunx-head-dark" data-rv>
			<span class="aunx-eyebrow"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Smart inside</span>
			<h2>Everything the big screen needs</h2>
			<p>Every AUN projector streams Netflix, YouTube and Prime Video and mirrors your phone. Here is what else they can do &mdash; each shown on a model that has it.</p>
		</div>
		<div class="aunx-feats-grid">
			<?php
			foreach ( array_slice( $feats, 0, 5 ) as $n => $f ) :
				$m    = $by_short[ $f['model'] ] ?? null;
				$link = $m && ( $m['live'] || $preview );
				$tag  = $link ? 'a' : 'div';
				?>
				<<?php echo $tag; ?> class="aunx-feat<?php echo 0 === $n ? ' aunx-feat-wide' : ''; ?>"<?php echo $link ? ' href="' . esc_url( $m['url'] ) . '"' : ''; ?> data-rv style="--d:<?php echo esc_attr( $n * 0.07 ); ?>s">
					<?php echo wp_get_attachment_image( $f['id'], 'full', false, array( 'class' => 'aunx-feat-img', 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '', 'sizes' => 0 === $n ? '(max-width: 549px) 84vw, (max-width: 849px) 100vw, 800px' : '(max-width: 549px) 84vw, (max-width: 849px) 50vw, 400px', 'style' => 'object-position:' . $f['pos'] ) ); ?>
					<span class="aunx-feat-b">
						<span class="aunx-feat-tag"><i class="fa-solid <?php echo esc_attr( $f['icon'] ); ?>" aria-hidden="true"></i> <?php echo $e( $f['model'] ); ?></span>
						<h3><?php echo $f['title']; ?></h3>
						<span class="aunx-feat-more">
							<span class="aunx-feat-p"><?php echo $f['text']; ?></span>
							<?php if ( $link ) : ?><span class="aunx-feat-go">See the <?php echo $e( $f['model'] ); ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span><?php endif; ?>
						</span>
					</span>
				</<?php echo $tag; ?>>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php
// Videos: YouTube Shorts pasted into Settings → AUN Store Details → Homepage videos. Hidden while empty.
$vids = function_exists( 'aunstore_parse_videos' ) ? aunstore_parse_videos( aunstore_detail( 'home_videos' ) ) : array();
if ( $vids && shortcode_exists( 'aun_shorts' ) ) :
	?>
<section class="aunx-sec aunx-vids">
	<div class="aunx-wrap">
		<div class="aunx-head" data-rv>
			<span class="aunx-eyebrow"><i class="fa-brands fa-youtube" aria-hidden="true"></i> Watch it in action</span>
			<h2>Watch the projector in action</h2>
			<p>Short clips of AUN projectors at work. They play silently as you scroll &mdash; tap the speaker for sound.</p>
		</div>
		<div class="aunx-vids-rail" data-rv>
			<?php echo do_shortcode( '[aun_shorts ids="' . esc_attr( implode( ',', $vids ) ) . '" title="" icon="false" audio="true" feature_title="See it on a real wall" feature_desc="A short clip filmed with an AUN projector. Tap the speaker for sound." features=""]' ); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="aunx-sec aunx-rooms">
	<div class="aunx-wrap">
		<div class="aunx-head" data-rv>
			<span class="aunx-eyebrow"><i class="fa-solid fa-house" aria-hidden="true"></i> Shop by space</span>
			<h2>Where will the show happen?</h2>
		</div>
		<div class="aunx-rooms-grid">
			<?php
			// Each card shows a vector scene of its category (design/illustrations.php). A photo that really
			// shows that setting can replace it: pass its attachment id as home_img / office_img / portable_img.
			$rooms = array(
				array( 'home-theater-projector', 'Home theater', 'Movie nights, sports and gaming on a giant screen.', 'home', $a['home_img'] ),
				array( 'office-projector', 'Office &amp; classroom', 'Bright, reliable pictures for meetings and lessons.', 'office', $a['office_img'] ),
				array( 'mini-projector', 'Portable &amp; mini', 'Light enough for travel, bedrooms and the backyard.', 'portable', $a['portable_img'] ),
			);
			foreach ( $rooms as $n => $r ) {
				$art = $r[4]
					? wp_get_attachment_image( (int) $r[4], 'large', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '', 'class' => 'aunx-room-art' ) )
					: aunstore_room_svg( $r[3] );
				echo '<a class="aunx-room aunx-room-' . esc_attr( $r[3] ) . '" href="' . esc_url( $cat( $r[0] ) ) . '" data-rv style="--d:' . ( $n * 0.1 ) . 's">'
					. $art
					. '<div class="aunx-room-body"><h3>' . $r[1] . '</h3><p>' . $e( $r[2] ) . '</p>'
					. '<span class="aunx-room-go">Shop now <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span></div>'
					. '</a>';
			}
			?>
		</div>
	</div>
</section>

<?php
// Customer reviews: real WooCommerce reviews only (see aunstore_home_reviews()). Shown from 3 good reviews.
$rv      = aunstore_home_reviews( 6 );
$show_rv = count( $rv['items'] ) >= 3;
if ( $show_rv || current_user_can( 'edit_products' ) ) :
	?>
<section class="aunx-sec aunx-revs">
	<div class="aunx-wrap">
		<?php if ( ! $show_rv ) : ?>
			<div class="aunx-rev-note" role="note"><i class="fa-solid fa-eye" aria-hidden="true"></i> <span><b>Only you can see this.</b> Customer reviews appear here once at least 3 approved reviews with 4&ndash;5 stars and some text are on published products (now: <?php echo (int) count( $rv['items'] ); ?>). Nothing is ever written in for customers.</span></div>
		<?php else : ?>
			<div class="aunx-head" data-rv>
				<span class="aunx-eyebrow"><i class="fa-solid fa-star" aria-hidden="true"></i> Customer reviews</span>
				<h2>What owners say</h2>
				<p class="aunx-rev-score"><span class="aunx-stars" aria-hidden="true"><?php echo str_repeat( '<i class="fa-solid fa-star"></i>', 5 ); ?></span> <b><?php echo esc_html( number_format( $rv['avg'], 1 ) ); ?></b> out of 5 &middot; <?php echo (int) $rv['count']; ?> reviews</p>
			</div>
			<div class="aunx-revs-grid">
				<?php foreach ( $rv['items'] as $n => $r ) :
					$prod = wc_get_product( $r['product'] );
					$ph   = $prod ? aunstore_product_highlights( $prod ) : null;
					?>
					<figure class="aunx-rev" data-rv style="--d:<?php echo esc_attr( ( $n % 3 ) * 0.08 ); ?>s">
						<span class="aunx-stars" role="img" aria-label="<?php echo esc_attr( $r['rating'] . ' out of 5 stars' ); ?>"><?php echo str_repeat( '<i class="fa-solid fa-star"></i>', $r['rating'] ) . str_repeat( '<i class="fa-regular fa-star"></i>', 5 - $r['rating'] ); ?></span>
						<blockquote><p><?php echo $e( $r['text'] ); ?></p></blockquote>
						<figcaption>
							<span class="aunx-rev-av" aria-hidden="true"><?php echo $e( mb_substr( $r['author'], 0, 1 ) ); ?></span>
							<span class="aunx-rev-who">
								<b><?php echo $e( $r['author'] ); ?><?php if ( $r['verified'] ) : ?> <i class="fa-solid fa-circle-check" title="Verified buyer" aria-label="Verified buyer"></i><?php endif; ?></b>
								<small><?php echo $e( $r['verified'] ? 'Verified buyer · ' . $r['date'] : $r['date'] ); ?></small>
							</span>
							<?php if ( $prod ) : ?><a class="aunx-rev-prod" href="<?php echo esc_url( get_permalink( $prod->get_id() ) ); ?>"><?php echo $e( $ph['short'] ); ?></a><?php endif; ?>
						</figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<section class="aunx-sec aunx-why">
	<?php if ( $why = $pic( $a['why_bg'], 'large' ) ) : ?>
		<div class="aunx-why-bg" style="background-image:url('<?php echo esc_url( $why ); ?>')" aria-hidden="true"></div>
	<?php endif; ?>
	<div class="aunx-wrap">
		<div class="aunx-head aunx-head-dark" data-rv>
			<span class="aunx-eyebrow"><i class="fa-solid fa-star" aria-hidden="true"></i> Why AUN</span>
			<h2>All You Need &mdash; nothing you don't</h2>
			<p>A private cinema for sharing good times with friends and family, without the premium-brand price tag.</p>
		</div>
		<ul class="aunx-stats" data-rv>
			<li><b data-count="2014" data-plain="1">2014</b><span>Established</span></li>
			<li><b data-count="100" data-suffix="+">100+</b><span>Countries where AUN is sold</span></li>
			<li><b data-count="30000" data-suffix=" h">30,000 h</b><span>LED light source, every model</span></li>
			<li><b data-count="12" data-suffix=" mo">12 mo</b><span>Warranty, wherever you live</span></li>
		</ul>
		<div class="aunx-why-grid">
			<div class="aunx-glass" data-rv><span class="aunx-glass-ico"><i class="fa-solid fa-plug" aria-hidden="true"></i></span><h3>Works in your country</h3><p>Every model runs on 100&ndash;240 V mains power and has menus in 10+ languages, from English and Spanish to Japanese and Korean.</p></div>
			<div class="aunx-glass" data-rv style="--d:.1s"><span class="aunx-glass-ico"><i class="fa-solid fa-fingerprint" aria-hidden="true"></i></span><h3>Genuine &mdash; and you can prove it</h3><p>Every unit carries a 12-digit anti-counterfeit code. <a href="<?php echo esc_url( aunstore_page_link( 'verify-authenticity' ) ); ?>">Verify yours in seconds <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></p></div>
		</div>
	</div>
</section>

<section class="aunx-sec aunx-care">
	<div class="aunx-wrap">
		<div class="aunx-head" data-rv>
			<span class="aunx-eyebrow"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> 1-year warranty</span>
			<h2>If something goes wrong, we fix it &mdash; at your home</h2>
			<p>You never have to ship your projector back. Here is exactly how a claim works.</p>
		</div>
		<div class="aunx-care-in" data-aunx-steps>
			<div class="aunx-chat" data-rv aria-hidden="true">
				<div class="aunx-chat-top"><span class="aunx-chat-av"><i class="fa-solid fa-headset"></i></span><span><b>AUN Support</b><small><i></i> Online</small></span></div>
				<div class="aunx-chat-body">
					<div class="aunx-msg aunx-msg-me is-on" data-step="0"><span class="aunx-msg-vid"><i class="fa-solid fa-play"></i><em>0:18</em></span><span>There are lines on the picture. Order #1042.</span></div>
					<div class="aunx-msg" data-step="1"><span class="aunx-msg-row"><i class="fa-solid fa-stethoscope"></i><span><b>Diagnosed</b>We found the faulty part.</span></span></div>
					<div class="aunx-msg" data-step="2"><span class="aunx-msg-row"><i class="fa-solid fa-truck-fast"></i><span><b>Replacement part shipped</b>Tracking number sent to your email.</span></span></div>
					<div class="aunx-msg" data-step="3"><span class="aunx-msg-row"><i class="fa-solid fa-circle-play"></i><span><b>Your video guide</b>Step-by-step for your model.</span></span></div>
				</div>
			</div>
			<div class="aunx-steps" data-rv style="--d:.1s">
				<?php
				$steps = array(
					array( 'fa-video', 'Tell us what happened', 'Send your order number and a short phone video of the problem.' ),
					array( 'fa-stethoscope', 'We diagnose it', 'Our engineers identify the faulty part &mdash; simple issues are often solved on the spot.' ),
					array( 'fa-box', 'The part ships to you', 'Our factory sends the replacement part straight to your address.' ),
					array( 'fa-circle-play', 'Fit it with our video', 'A step-by-step tutorial for your model shows you how. Stuck? We are a message away.' ),
				);
				foreach ( $steps as $n => $s ) {
					echo '<button type="button" class="aunx-step' . ( 0 === $n ? ' is-active' : '' ) . '" data-i="' . (int) $n . '" aria-expanded="' . ( 0 === $n ? 'true' : 'false' ) . '">'
						. '<span class="aunx-step-n">' . ( $n + 1 ) . '</span>'
						. '<span class="aunx-step-b"><b>' . $e( $s[1] ) . '</b><span class="aunx-step-t">' . $s[2] . '</span></span>'
						. '<i class="fa-solid ' . esc_attr( $s[0] ) . ' aunx-step-ico" aria-hidden="true"></i></button>';
				}
				?>
				<a class="aunx-btn aunx-btn-line" href="<?php echo esc_url( aunstore_page_link( 'warranty' ) ); ?>">Warranty &amp; Support <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
			</div>
		</div>
	</div>
</section>

<section class="aunx-sec aunx-finder">
	<div class="aunx-wrap aunx-finder-in" data-rv>
		<span class="aunx-finder-ico"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span>
		<div>
			<h2>Not sure which one? Give us 60 seconds.</h2>
			<p>Answer 5 quick questions about your room, screen and budget &mdash; the Smart Finder picks your best match and tells you why.</p>
		</div>
		<a class="aunx-btn aunx-btn-white" href="<?php echo esc_url( $finder ); ?>">Start the Smart Finder <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
	</div>
</section>

<section class="aunx-sec aunx-faqs">
	<div class="aunx-wrap aunx-faqs-in">
		<div class="aunx-faqs-copy" data-rv>
			<span class="aunx-eyebrow"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Good to know</span>
			<h2>Questions, answered</h2>
			<p>Still wondering about something? A real person on our team will help.</p>
			<a class="aunx-btn aunx-btn-line" href="<?php echo esc_url( aunstore_page_link( 'contact-us' ) ); ?>"><i class="fa-solid fa-headset" aria-hidden="true"></i> Contact us</a>
		</div>
		<div class="aunx-faq" data-rv style="--d:.1s">
			<?php
			$ship = esc_url( aunstore_page_link( 'shipping-policy' ) );
			$war  = esc_url( aunstore_page_link( 'warranty' ) );
			$faqs = array(
				array( 'Do you ship to my country?', 'We ship worldwide. Delivery time and shipping cost for your address are shown at checkout, before you pay. See our <a href="' . $ship . '">Shipping Policy</a> for details, including customs duties.' ),
				array( 'How does the warranty work if I live abroad?', 'Every AUN projector has a 1-year warranty, and you never have to ship it back. Send our support team a short video of the problem; we diagnose it, our factory ships the replacement part to your address, and we share a step-by-step video so you can fit it yourself. Details on <a href="' . $war . '">Warranty &amp; Support</a>.' ),
				array( 'Will it work with the power supply in my country?', 'Yes. Every AUN projector accepts a universal mains voltage (100 &ndash; 240 V or wider), so it works anywhere in the world. If your wall socket is a different shape from the supplied plug, a simple plug adapter is all you need.' ),
				array( 'Which projector is right for my room?', 'Start with the <a href="' . esc_url( $finder ) . '">60-Second Smart Finder</a> &mdash; it weighs your room lighting, screen size, distance and budget. Every product page also has a Screen Size Calculator that shows the exact picture size at your wall distance.' ),
				array( 'Can I watch Netflix and YouTube?', 'Yes &mdash; every current AUN projector runs Android or Genuine Android TV with apps like YouTube, Netflix and Prime Video, plus wireless screen mirroring from your phone. App availability can vary by country.' ),
			);
			$ld = array();
			foreach ( $faqs as $n => $f ) {
				echo '<details' . ( 0 === $n ? ' open' : '' ) . '><summary><span>' . $e( $f[0] ) . '</span><i class="fa-solid fa-plus" aria-hidden="true"></i></summary><div class="aunx-faq-a">' . wp_kses_post( $f[1] ) . '</div></details>';
				$ld[] = array( '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => html_entity_decode( wp_strip_all_tags( $f[1] ), ENT_QUOTES, 'UTF-8' ) ) );
			}
			?>
		</div>
	</div>
</section>

</div>
	<?php
	echo '<script type="application/ld+json" data-no-optimize="1" data-no-minify="1">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ld ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
	echo '<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">' . aunstore_home_js() . '</script>';
	return ob_get_clean();
} );
