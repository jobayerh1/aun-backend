<?php
/**
 * Product page tabs: Specifications, Screen Size Calculator and Warranty.
 *
 * Specifications = HTML in the `_aunstore_specs` product meta (edited in a box on the product screen).
 * Screen Size Calculator = [aun_throw_calculator] for projectors that have a spec sheet.
 * Warranty = the global parts-shipped warranty summary, the same for every projector.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const AUNSTORE_SPECS_META = '_aunstore_specs';

function aunstore_product_has_specs( $product_id ) {
	return '' !== trim( (string) get_post_meta( $product_id, AUNSTORE_SPECS_META, true ) );
}

add_filter( 'woocommerce_product_tabs', function ( $tabs ) {
	global $product;
	if ( ! $product instanceof WC_Product || ! aunstore_product_has_specs( $product->get_id() ) ) return $tabs;

	$tabs['aunstore_specs'] = array(
		'title'    => __( 'Specifications', 'aunstore-core' ),
		'priority' => 15, // right after Description (10)
		'callback' => function () {
			global $product;
			echo '<div class="aunstore-specs">' . get_post_meta( $product->get_id(), AUNSTORE_SPECS_META, true ) . '</div>';
		},
	);
	if ( shortcode_exists( 'aun_throw_calculator' ) ) {
		$tabs['screen_size_calculator'] = array(
			'title'    => __( 'Screen Size Calculator', 'aunstore-core' ),
			'priority' => 20,
			'callback' => function () {
				echo do_shortcode( '[aun_throw_calculator]' );
			},
		);
	}
	$tabs['aunstore_warranty'] = array(
		'title'    => __( 'Warranty', 'aunstore-core' ),
		'priority' => 25,
		'callback' => 'aunstore_render_warranty_tab',
	);
	return $tabs;
} );

function aunstore_warranty_page_url() {
	$page = get_page_by_path( 'warranty' );
	return ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : '';
}

function aunstore_render_warranty_tab() {
	$check = '<span style="width:20px;height:20px;min-width:20px;border-radius:50%;background:#0188fe;display:inline-flex;align-items:center;justify-content:center"><i class="fa-solid fa-check" style="color:#fff;font-size:9px;line-height:1"></i></span>';
	$li    = function ( $text, $last = false ) use ( $check ) {
		return '<li style="display:flex;align-items:center;gap:10px;padding:8px 0;' . ( $last ? '' : 'border-bottom:1px solid rgba(1,136,254,.1)' ) . '">' . $check
			. '<span style="font-size:14px;color:#1e3a52;line-height:1.4">' . $text . '</span></li>';
	};
	$step = function ( $n, $text ) {
		return '<div style="flex:1 1 160px;display:flex;align-items:flex-start;gap:9px"><span style="width:22px;height:22px;min-width:22px;border-radius:50%;background:#eaf4ff;border:1px solid #bcd9f8;color:#0188fe;font-size:12px;font-weight:800;display:inline-flex;align-items:center;justify-content:center">' . $n . '</span>'
			. '<span style="font-size:13px;color:#1e3a52;line-height:1.45">' . $text . '</span></div>';
	};
	$more = aunstore_warranty_page_url();
	?>
	<div style="background:#f0f7ff;padding:20px 22px;border-radius:12px;border:1px solid #bcd9f8;position:relative;overflow:hidden;box-sizing:border-box">
		<div style="position:absolute;top:0;left:0;width:4px;height:100%;background:#0188fe"></div>
		<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap">
			<span style="background:#0188fe;color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;letter-spacing:.5px;line-height:1.6;white-space:nowrap">1 YEAR WARRANTY</span>
			<strong style="font-size:15px;color:#0c2a4a;line-height:1.3">Official manufacturer coverage, wherever you live</strong>
		</div>
		<ul style="list-style:none;padding:0;margin:0 0 16px">
			<?php
			echo $li( 'Hardware defects covered for 12 months from the date of purchase' );
			echo $li( 'Replacement parts shipped from our factory straight to your address' );
			echo $li( 'Step-by-step video tutorials show you how to fit the part', true );
			?>
		</ul>
		<div style="background:#fff;border:1px solid #dcebfb;border-radius:10px;padding:14px 16px;margin-bottom:16px">
			<div style="font-size:11px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#0188fe;margin-bottom:10px">How a claim works</div>
			<div style="display:flex;flex-wrap:wrap;gap:12px">
				<?php
				echo $step( 1, 'Contact support with your order number and a short video of the problem' );
				echo $step( 2, 'We diagnose it and ship the replacement part from our factory' );
				echo $step( 3, 'Fit it with our video guide &mdash; no need to send the projector back' );
				?>
			</div>
		</div>
		<div style="display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid #bcd9f8;flex-wrap:wrap;gap:10px">
			<span style="display:inline-flex;align-items:center;gap:7px;font-size:12px;color:#4a6a8a;line-height:1.4"><i class="fa-regular fa-file-lines" style="font-size:13px"></i><span>Keep your order confirmation &mdash; it is your proof of purchase</span></span>
			<?php if ( $more ) : ?>
				<a href="<?php echo esc_url( $more ); ?>" style="display:inline-flex;align-items:center;gap:7px;background:#0188fe;color:#fff;font-size:13px;font-weight:600;padding:9px 16px;border-radius:7px;text-decoration:none;line-height:1;white-space:nowrap"><span style="color:#fff">Warranty &amp; Support</span><i class="fa-solid fa-arrow-right" style="font-size:10px;color:#fff"></i></a>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

// "Need help deciding? Use our 60-Second Smart Finder" under the product summary (projectors only —
// the finder's own shortcode checks the category), after the Add to Compare link.
add_action( 'woocommerce_product_meta_end', function () {
	if ( shortcode_exists( 'aun_finder_notice' ) ) echo do_shortcode( '[aun_finder_notice]' );
}, 20 );

/* ── Admin: the Specifications box ─────────────────────────────────────── */

add_action( 'add_meta_boxes_product', function () {
	add_meta_box( 'aunstore-specs', __( 'Specifications tab (HTML)', 'aunstore-core' ), function ( $post ) {
		wp_nonce_field( 'aunstore_specs_save', 'aunstore_specs_nonce' );
		echo '<p class="description">Shown as the <strong>Specifications</strong> tab on the product page and used by Compare. Leave empty to hide the tab.</p>';
		echo '<textarea name="aunstore_specs" rows="14" style="width:100%;font-family:monospace;font-size:12px">'
			. esc_textarea( get_post_meta( $post->ID, AUNSTORE_SPECS_META, true ) ) . '</textarea>';
	}, 'product', 'normal', 'default' );
} );

add_action( 'save_post_product', function ( $post_id ) {
	if ( ! isset( $_POST['aunstore_specs_nonce'] ) ) return;
	if ( ! wp_verify_nonce( sanitize_key( $_POST['aunstore_specs_nonce'] ), 'aunstore_specs_save' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$html = isset( $_POST['aunstore_specs'] ) ? wp_unslash( $_POST['aunstore_specs'] ) : '';
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		$html = wp_kses_post( $html );
	}
	update_post_meta( $post_id, AUNSTORE_SPECS_META, $html );
} );
