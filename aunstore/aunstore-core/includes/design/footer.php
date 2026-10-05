<?php
/**
 * The designed site footer.
 *
 * Replaces Flatsome's widget footer + copyright bar with one designed block: trust strip, two call-to-action
 * cards, brand + icon link columns, contact details (from Settings → AUN Store Details) and a bottom bar.
 * Links to pages that are not published are left out automatically.
 *
 * Switch back to Flatsome's own footer any time: Settings → AUN Store Details → Footer → "Flatsome widgets".
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function aunstore_designed_footer_enabled() {
	return (bool) apply_filters( 'aunstore_designed_footer', 'flatsome' !== aunstore_detail( 'footer_style' ) );
}

function aunstore_footer_css() {
	return <<<'CSS'
.aunf{--bl:#0188fe;--cy:#00c6ff;--sky:#4da8ff;--line:rgba(255,255,255,.09);--mut:#8ea3c0;position:relative;background:radial-gradient(900px 420px at 12% 0,rgba(1,136,254,.16),transparent 60%),radial-gradient(700px 380px at 95% 100%,rgba(0,198,255,.1),transparent 60%),linear-gradient(180deg,#070c18,#050913);color:#c3d1e4;font-size:15px;line-height:1.6;text-align:left;-webkit-font-smoothing:antialiased}
.aunf *,.aunf *::before,.aunf *::after{box-sizing:border-box}
.aunf::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,transparent,var(--bl) 25%,var(--cy) 75%,transparent)}
.aunf-wrap{max-width:1200px;margin:0 auto;padding:0 24px}
.aunf ul{list-style:none;margin:0;padding:0}
.aunf li{margin:0;padding:0;border:0}
.aunf p{margin:0}
.aunf a{text-decoration:none;color:inherit}
.aunf h4{margin:0 0 18px;width:auto;font-size:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#fff}

.aunf-perks{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr));border-bottom:1px solid var(--line)}
.aunf-perks li{display:flex;align-items:center;gap:14px;padding:30px 20px 30px 0}
.aunf-ico{width:46px;height:46px;border-radius:14px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:18px;color:#cfe9ff;background:linear-gradient(135deg,rgba(1,136,254,.38),rgba(0,198,255,.16));border:1px solid rgba(255,255,255,.1)}
.aunf-perks b{display:block;color:#fff;font-size:15px;font-weight:700;line-height:1.3}
.aunf-perks span span{display:block;font-size:13px;color:var(--mut);line-height:1.4}

.aunf-cta{display:grid;grid-template-columns:1fr 1fr;gap:18px;padding:34px 0}
.aunf-card{display:flex;align-items:center;gap:16px;padding:20px 22px;border-radius:20px;background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.025));border:1px solid var(--line);transition:transform .25s ease,border-color .25s ease,background .25s ease}
.aunf-card:hover{transform:translateY(-3px);border-color:rgba(77,168,255,.5);background:linear-gradient(180deg,rgba(1,136,254,.18),rgba(255,255,255,.03))}
.aunf-card b{display:block;color:#fff;font-size:17px;font-weight:800;letter-spacing:-.01em;line-height:1.3}
.aunf-card>span:nth-child(2){flex:1;min-width:0}
.aunf-card span span{display:block;font-size:14px;color:var(--mut)}
.aunf-card>i{width:38px;height:38px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.08);color:#fff;font-size:13px;transition:background .25s ease,transform .25s ease}
.aunf-card:hover>i{background:var(--bl);transform:translateX(3px)}

.aunf-grid{display:grid;grid-template-columns:1.5fr 1fr 1fr 1.25fr;gap:40px;padding:22px 0 46px}
.aunf-logo{display:inline-flex;align-items:center;gap:12px;margin-bottom:16px}
.aunf-logo img{height:54px;width:auto;display:block}
.aunf-word{font-size:30px;font-weight:900;letter-spacing:.02em;color:#fff;line-height:1}
.aunf-word small{display:block;font-size:11px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:var(--sky);margin-top:5px}
.aunf-brand p{color:var(--mut);font-size:14.5px;max-width:320px}
.aunf-social{display:flex;gap:10px;margin-top:20px}
.aunf-social a{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.07);border:1px solid var(--line);color:#fff;font-size:16px;transition:background .2s ease,transform .2s ease,border-color .2s ease}
.aunf-social a:hover{background:var(--bl);border-color:var(--bl);transform:translateY(-2px)}
.aunf-links li+li{margin-top:11px}
.aunf-links a{display:inline-flex;align-items:center;gap:10px;color:#c3d1e4;font-size:14.5px;transition:color .2s ease,transform .2s ease}
.aunf-links a i{width:16px;text-align:center;font-size:13px;color:#5f7897;transition:color .2s ease}
.aunf-links a:hover{color:#fff;transform:translateX(3px)}
.aunf-links a:hover i{color:var(--sky)}
.aunf-contact li{display:flex;gap:12px;align-items:flex-start;font-size:14.5px}
.aunf-contact li+li{margin-top:14px}
.aunf-contact i{width:34px;height:34px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.06);border:1px solid var(--line);color:var(--sky);font-size:14px}
.aunf-contact small{display:block;font-size:11.5px;letter-spacing:.08em;text-transform:uppercase;color:#6f86a4;line-height:1.3}
.aunf-contact a,.aunf-contact span span{color:#e6eef9;font-weight:600;word-break:break-word}
.aunf-contact a:hover{color:var(--sky)}

.aunf-bottom{display:flex;align-items:center;justify-content:space-between;gap:16px 24px;flex-wrap:wrap;padding:20px 0 26px;border-top:1px solid var(--line);font-size:13.5px;color:#7f93ae}
.aunf-bottom strong{color:#c3d1e4}
.aunf-legal{display:flex!important;flex-wrap:wrap;gap:8px 22px}
.aunf-legal a:hover{color:#fff}
.aunf-secure{display:inline-flex;align-items:center;gap:8px}
.aunf-secure i{color:#34d399}

@media (max-width:1024px){
	.aunf-grid{grid-template-columns:1fr 1fr;gap:34px 30px}
	.aunf-perks{grid-template-columns:1fr 1fr}
	.aunf-perks li{padding:20px 12px 20px 0}
	.aunf-perks li:nth-child(n+3){padding-top:0}
}
@media (max-width:640px){
	.aunf-wrap{padding:0 18px}
	.aunf-cta{grid-template-columns:1fr;padding:26px 0}
	.aunf-grid{grid-template-columns:1fr 1fr;gap:30px 20px}
	.aunf-brand,.aunf-col-contact{grid-column:1/-1}
	.aunf-perks b{font-size:14px}
	.aunf-ico{width:40px;height:40px;font-size:16px}
	.aunf-bottom{justify-content:center;text-align:center}
	.aunf-legal{justify-content:center}
}
CSS;
}

/** The footer markup (no wrapper <footer> tag — Flatsome already provides one). */
function aunstore_footer_html() {
	$page = function ( $slug ) {
		$p = get_page_by_path( $slug );
		return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : '';
	};
	$cat = function ( $slug ) {
		$l = taxonomy_exists( 'product_cat' ) ? get_term_link( $slug, 'product_cat' ) : null;
		return ( $l && ! is_wp_error( $l ) ) ? $l : '';
	};
	$links = function ( array $rows ) {
		$out = '';
		foreach ( $rows as $r ) {
			if ( empty( $r[0] ) ) continue;
			$out .= '<li><a href="' . esc_url( $r[0] ) . '"><i class="fa-solid ' . esc_attr( $r[1] ) . '" aria-hidden="true"></i>' . esc_html( $r[2] ) . '</a></li>';
		}
		return $out;
	};
	$company = aunstore_detail( 'company' );

	$perks = array(
		array( 'fa-earth-americas', 'Worldwide shipping', 'Tracked delivery to your door' ),
		array( 'fa-shield-halved', '1-year warranty', 'Wherever you live' ),
		array( 'fa-box', 'Parts shipped to you', 'No sending your projector back' ),
		array( 'fa-fingerprint', 'Verified genuine', '12-digit code on every unit' ),
	);

	$o = '<div class="aunf"><div class="aunf-wrap">';

	$o .= '<ul class="aunf-perks">';
	foreach ( $perks as $p ) {
		$o .= '<li><span class="aunf-ico"><i class="fa-solid ' . esc_attr( $p[0] ) . '" aria-hidden="true"></i></span><span><b>' . esc_html( $p[1] ) . '</b><span>' . esc_html( $p[2] ) . '</span></span></li>';
	}
	$o .= '</ul>';

	$cards = '';
	if ( $u = $page( 'verify-authenticity' ) ) {
		$cards .= '<a class="aunf-card" href="' . esc_url( $u ) . '"><span class="aunf-ico"><i class="fa-solid fa-fingerprint" aria-hidden="true"></i></span><span><b>Is your AUN genuine?</b><span>Check the 12-digit code in seconds</span></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>';
	}
	if ( $u = $page( 'projector-finder' ) ) {
		$cards .= '<a class="aunf-card" href="' . esc_url( $u ) . '"><span class="aunf-ico"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span><span><b>Not sure which model?</b><span>Find your match in 60 seconds</span></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>';
	}
	if ( $cards ) $o .= '<div class="aunf-cta">' . $cards . '</div>';

	$o .= '<div class="aunf-grid">';

	// Brand.
	$logo_id = (int) get_option( 'aunstore_logo_id' );
	$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	$o .= '<div class="aunf-brand"><a class="aunf-logo" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( $company ) . ' home">'
		. ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $company ) . '" loading="lazy">' : '' )
		. '<span class="aunf-word">' . esc_html( $company ) . '<small>All You Need</small></span></a>'
		. '<p>Smart projectors for home cinema, office and travel. Sold in more than 100 countries since 2014.</p>';
	$social = '';
	foreach ( array( 'facebook' => 'fa-facebook-f', 'youtube' => 'fa-youtube', 'instagram' => 'fa-instagram', 'tiktok' => 'fa-tiktok' ) as $key => $icon ) {
		if ( $url = aunstore_detail( $key ) ) {
			$social .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( ucfirst( $key ) ) . '"><i class="fa-brands ' . esc_attr( $icon ) . '" aria-hidden="true"></i></a>';
		}
	}
	if ( $social ) $o .= '<div class="aunf-social">' . $social . '</div>';
	$o .= '</div>';

	// Shop.
	$o .= '<nav class="aunf-col" aria-label="Shop"><h4>Shop</h4><ul class="aunf-links">' . $links( array(
		array( aunstore_projectors_url(), 'fa-border-all', 'All Projectors' ),
		array( $cat( 'home-theater-projector' ), 'fa-couch', 'Home Theater' ),
		array( $cat( 'office-projector' ), 'fa-briefcase', 'Office & Classroom' ),
		array( $cat( 'mini-projector' ), 'fa-suitcase-rolling', 'Portable & Mini' ),
		array( $page( 'projector-finder' ), 'fa-wand-magic-sparkles', '60-Second Smart Finder' ),
	) ) . '</ul></nav>';

	// Support.
	$o .= '<nav class="aunf-col" aria-label="Support"><h4>Support</h4><ul class="aunf-links">' . $links( array(
		array( $page( 'warranty' ), 'fa-screwdriver-wrench', 'Warranty & Support' ),
		array( $page( 'verify-authenticity' ), 'fa-fingerprint', 'Verify Authenticity' ),
		array( $page( 'shipping-policy' ), 'fa-truck-fast', 'Shipping Policy' ),
		array( $page( 'returns-and-refunds' ), 'fa-arrow-right-arrow-left', 'Returns & Refunds' ),
		array( $page( 'my-account' ), 'fa-user', 'My Account & Orders' ),
		array( $page( 'about-us' ), 'fa-circle-info', 'About AUN' ),
	) ) . '</ul></nav>';

	// Contact.
	$rows = '';
	if ( $v = aunstore_detail( 'email' ) )    $rows .= '<li><i class="fa-solid fa-envelope" aria-hidden="true"></i><span><small>Email</small><a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a></span></li>';
	if ( $v = aunstore_detail( 'whatsapp' ) ) $rows .= '<li><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span><small>WhatsApp</small><a href="https://wa.me/' . esc_attr( $v ) . '" target="_blank" rel="noopener">+' . esc_html( $v ) . '</a></span></li>';
	if ( $v = aunstore_detail( 'phone' ) )    $rows .= '<li><i class="fa-solid fa-phone" aria-hidden="true"></i><span><small>Phone</small><a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $v ) ) . '">' . esc_html( $v ) . '</a></span></li>';
	if ( $v = aunstore_detail( 'hours' ) )    $rows .= '<li><i class="fa-solid fa-clock" aria-hidden="true"></i><span><small>Support hours</small><span>' . esc_html( $v ) . '</span></span></li>';
	if ( $u = $page( 'contact-us' ) )         $rows .= '<li><i class="fa-solid fa-headset" aria-hidden="true"></i><span><small>Need help?</small><a href="' . esc_url( $u ) . '">Contact our team</a></span></li>';
	$o .= '<div class="aunf-col aunf-col-contact"><h4>Get in touch</h4><ul class="aunf-contact">' . $rows . '</ul></div>';

	$o .= '</div>'; // grid

	$legal = '';
	foreach ( array( 'privacy-policy' => 'Privacy Policy', 'terms-and-conditions' => 'Terms & Conditions', 'shipping-policy' => 'Shipping Policy' ) as $slug => $label ) {
		if ( $u = $page( $slug ) ) $legal .= '<li><a href="' . esc_url( $u ) . '">' . esc_html( $label ) . '</a></li>';
	}
	$o .= '<div class="aunf-bottom"><span>&copy; ' . esc_html( wp_date( 'Y' ) ) . ' <strong>' . esc_html( $company ) . '</strong>. All rights reserved.</span>'
		. ( $legal ? '<ul class="aunf-legal">' . $legal . '</ul>' : '' )
		. '<span class="aunf-secure"><i class="fa-solid fa-lock" aria-hidden="true"></i> Secure checkout</span></div>';

	return $o . '</div></div>';
}

function aunstore_footer_output() {
	static $done = false;
	if ( $done || is_admin() || ! aunstore_designed_footer_enabled() ) return;
	$done = true;
	// Hide the theme's own widget footer + copyright bar: this block replaces both.
	echo '<style id="aunf-css" data-no-optimize="1" data-no-minify="1">#footer .footer-widgets,#footer .absolute-footer{display:none!important}' . aunstore_footer_css() . '</style>';
	echo aunstore_footer_html();
}

// Flatsome prints its footer on `flatsome_footer`; ours goes first. If that hook is missing in some
// Flatsome version, fall back to wp_footer so the footer still appears at the bottom of the page.
add_action( 'flatsome_footer', 'aunstore_footer_output', 5 );
add_action( 'wp_footer', function () {
	if ( 'flatsome' === get_template() ) aunstore_footer_output();
}, 1 );
