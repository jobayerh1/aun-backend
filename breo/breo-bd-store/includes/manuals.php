<?php
/**
 * Factory PDF manuals.
 *
 * Breo names every manual with the model code (e.g. "N6 mini_Manual_EN_TH_
 * N990000631_20250820.pdf"), so a manual uploaded to the Media Library is
 * matched to its product by the SKU in the filename. No extra step for the
 * shop owner, and a replacement upload is picked up on its own.
 *
 * A product can override the match with the _breo_manual_id meta (attachment
 * ID), or 0 to hide the download.
 */
defined( 'ABSPATH' ) || exit;

/**
 * The manual for one product.
 *
 * @return array|null id, url, name, size (human), bytes
 */
function breo_bd_manual( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return null;
	}
	$set = $product->get_meta( '_breo_manual_id' );
	if ( '' !== $set ) {
		return (int) $set > 0 ? breo_bd_manual_info( (int) $set ) : null;
	}
	$sku = $product->get_sku();
	if ( ! $sku ) {
		return null;
	}
	$found = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => 'application/pdf',
		'posts_per_page' => 1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => '_wp_attached_file', 'value' => $sku, 'compare' => 'LIKE' ),
		),
	) );
	$id = $found ? (int) $found[0] : 0;
	$product->update_meta_data( '_breo_manual_id', $id ); // remember, including "none found"
	$product->save_meta_data();
	return $id ? breo_bd_manual_info( $id ) : null;
}

function breo_bd_manual_info( $id ) {
	$url = wp_get_attachment_url( $id );
	if ( ! $url ) {
		return null;
	}
	$path  = get_attached_file( $id );
	$bytes = $path && file_exists( $path ) ? (int) filesize( $path ) : 0;
	return array(
		'id'    => (int) $id,
		'url'   => $url,
		'name'  => get_the_title( $id ),
		'bytes' => $bytes,
		'size'  => $bytes ? size_format( $bytes, 1 ) : '',
	);
}

/** [breo_manuals] — every product's manual, for the /manuals/ page. */
add_shortcode( 'breo_manuals', function () {
	$out = '';
	foreach ( breo_bd_products() as $p ) {
		$m = breo_bd_manual( $p );
		if ( ! $m ) {
			continue;
		}
		$d    = breo_bd_product_data( $p );
		$name = $d ? $d['name'] : $p->get_name();
		$out .= '<a class="breo-dl" href="' . esc_url( $m['url'] ) . '" target="_blank" rel="noopener">'
			. breo_bd_icon( 'doc', 22 )
			. '<span class="breo-dl__t">' . esc_html( $name ) . ' user manual'
			. '<span class="breo-dl__m">PDF' . ( $m['size'] ? ', ' . esc_html( $m['size'] ) : '' ) . ' · SKU ' . esc_html( $p->get_sku() ) . '</span></span>'
			. '<span class="breo-dl__a">Download</span></a>';
	}
	return $out ? '<div class="breo-dls">' . $out . '</div>' : '<p>The manuals are on their way. Message us on WhatsApp and we will send you one.</p>';
} );

/**
 * Downloads & support cards for the product page: the manual, the warranty and
 * a way to reach a human. Shown as a card row, not a lone link.
 */
function breo_bd_support_cards_html( $product ) {
	$cards = array();
	$m     = breo_bd_manual( $product );
	if ( $m ) {
		$cards[] = array(
			'icon' => 'doc',
			'title' => 'User manual',
			'meta'  => 'PDF' . ( $m['size'] ? ' · ' . $m['size'] : '' ) . ' · English',
			'cta'   => 'Download',
			'url'   => $m['url'],
			'blank' => true,
		);
	}
	$w = breo_bd_warranty_period();
	$cards[] = array(
		'icon'  => 'shield',
		'title' => 'Warranty',
		'meta'  => ( $w ? ucfirst( $w ) . ' official warranty,' : 'Official warranty,' ) . ' handled in Bangladesh',
		'cta'   => 'Read the policy',
		'url'   => breo_bd_page_url( 'warranty-policy' ),
		'blank' => false,
	);
	$wa = breo_bd_whatsapp_url( 'Hi Breo Bangladesh, I have a question about the ' . $product->get_name() );
	if ( $wa ) {
		$cards[] = array(
			'icon'  => 'chat',
			'title' => 'Need help?',
			'meta'  => 'Talk to us on WhatsApp, in Bangla or English',
			'cta'   => 'Message us',
			'url'   => $wa,
			'blank' => true,
		);
	}
	$h = '<div class="breo-supp" data-reveal><h3 class="breo-supp__h">Downloads &amp; support</h3><div class="breo-supp__grid">';
	foreach ( $cards as $c ) {
		$h .= '<a class="breo-supp__card" href="' . esc_url( $c['url'] ) . '"' . ( $c['blank'] ? ' target="_blank" rel="noopener"' : '' ) . '>'
			. '<span class="breo-supp__ico">' . breo_bd_icon( $c['icon'], 22 ) . '</span>'
			. '<span class="breo-supp__t">' . esc_html( $c['title'] ) . '</span>'
			. '<span class="breo-supp__m">' . esc_html( $c['meta'] ) . '</span>'
			. '<span class="breo-supp__cta">' . esc_html( $c['cta'] ) . breo_bd_icon( 'chevr', 15 ) . '</span></a>';
	}
	return $h . '</div></div>';
}

/* ---------- admin: pick or clear the manual on the product edit screen ---------- */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'breo-manual', 'Breo: user manual (PDF)', 'breo_bd_manual_box', 'product', 'side' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $screen && 'product' === $screen->post_type ) {
		wp_enqueue_media(); // wp.media must be queued before the metabox renders
	}
} );

function breo_bd_manual_box( $post ) {
	$product = wc_get_product( $post->ID );
	if ( ! $product ) {
		return;
	}
	$set = $product->get_meta( '_breo_manual_id' );
	$m   = breo_bd_manual( $product );
	wp_nonce_field( 'breo_manual', 'breo_manual_nonce' );
	?>
	<div id="breo-manual-box" data-sku="<?php echo esc_attr( $product->get_sku() ); ?>">
		<p class="breo-manual-now">
			<?php if ( $m ) : ?>
				<strong><?php echo esc_html( $m['name'] ); ?></strong><br>
				<span class="description">PDF<?php echo $m['size'] ? ' · ' . esc_html( $m['size'] ) : ''; ?>
					<?php echo '' === $set ? '· matched by SKU' : '· chosen by hand'; ?></span><br>
				<a href="<?php echo esc_url( $m['url'] ); ?>" target="_blank" rel="noopener">Open PDF</a>
			<?php else : ?>
				<span class="description">No manual yet. Choose a PDF, or upload one named with the SKU
					(<?php echo esc_html( $product->get_sku() ); ?>) and it is matched automatically.</span>
			<?php endif; ?>
		</p>
		<input type="hidden" name="breo_manual_id" id="breo-manual-id" value="<?php echo esc_attr( '' === $set ? '' : $set ); ?>">
		<p>
			<button type="button" class="button" id="breo-manual-pick">Choose PDF…</button>
			<button type="button" class="button-link" id="breo-manual-auto">Match by SKU</button>
			<button type="button" class="button-link delete" id="breo-manual-none">Hide on this product</button>
		</p>
		<p class="breo-manual-msg description" aria-live="polite"></p>
	</div>
	<script data-no-optimize="1" data-no-minify="1" data-cfasync="false" data-no-defer="1">
	(function () {
		var box = document.getElementById('breo-manual-box'), input = document.getElementById('breo-manual-id');
		var msg = box.querySelector('.breo-manual-msg'), frame;
		function say(t) { msg.textContent = t + ' Save the product to apply.'; }
		document.getElementById('breo-manual-pick').addEventListener('click', function (e) {
			e.preventDefault();
			if (!window.wp || !wp.media) { say('The media library did not load. Reload this page and try again.'); return; }
			frame = frame || wp.media({ title: 'Choose the user manual', library: { type: 'application/pdf' }, button: { text: 'Use this PDF' }, multiple: false });
			frame.off('select').on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				input.value = a.id;
				say('Selected: ' + (a.filename || a.title) + '.');
			});
			frame.open();
		});
		document.getElementById('breo-manual-auto').addEventListener('click', function (e) {
			e.preventDefault(); input.value = ''; say('Back to matching by SKU.');
		});
		document.getElementById('breo-manual-none').addEventListener('click', function (e) {
			e.preventDefault(); input.value = '0'; say('The download will be hidden.');
		});
	})();
	</script>
	<?php
}

add_action( 'save_post_product', function ( $post_id ) {
	if ( ! isset( $_POST['breo_manual_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['breo_manual_nonce'] ), 'breo_manual' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$product = wc_get_product( $post_id );
	if ( ! $product ) {
		return;
	}
	$raw = isset( $_POST['breo_manual_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['breo_manual_id'] ) ) ) : '';
	if ( '' === $raw ) {
		$product->delete_meta_data( '_breo_manual_id' ); // back to matching by SKU
	} else {
		$product->update_meta_data( '_breo_manual_id', (int) $raw );
	}
	$product->save_meta_data();
} );

/** A new PDF upload clears the remembered matches, so it is picked up at once. */
add_action( 'add_attachment', function ( $id ) {
	if ( 'application/pdf' !== get_post_mime_type( $id ) ) {
		return;
	}
	foreach ( breo_bd_products() as $p ) {
		if ( '' === $p->get_meta( '_breo_manual_id' ) || 0 === (int) $p->get_meta( '_breo_manual_id' ) ) {
			$p->delete_meta_data( '_breo_manual_id' );
			$p->save_meta_data();
		}
	}
} );
