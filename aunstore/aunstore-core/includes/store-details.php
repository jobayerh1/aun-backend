<?php
/**
 * Store details (Settings → AUN Store Details) and the shortcodes that print them.
 *
 * Contact details live in ONE place. Pages, the footer and product tabs read them through
 * shortcodes, so changing an email or WhatsApp number never means editing page content.
 * Anything left empty is simply not shown.
 *
 *   [aunstore_info key="email|phone|whatsapp|address|hours|company"]   plain value (email/phone linked)
 *   [aunstore_contact_cards]                                            contact cards for every filled channel
 *   [aunstore_social]                                                   social icons for every filled profile
 *   [aunstore_link page="slug" text="Label"]                            link, only if that page is published
 *   [aunstore_year]                                                     current year
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const AUNSTORE_DETAILS_OPTION = 'aunstore_details';

function aunstore_detail_fields() {
	return array(
		'company'   => array( 'Company / brand name', 'AUN', 'Shown in the footer copyright and policy pages.' ),
		'email'     => array( 'Support email', '', 'e.g. support@aunstore.com' ),
		'whatsapp'  => array( 'WhatsApp number', '', 'International format, digits only, e.g. 8613800000000' ),
		'phone'     => array( 'Phone number', '', 'As it should be displayed, e.g. +86 138 0000 0000' ),
		'address'   => array( 'Company address', '', 'Optional. Leave empty to hide.' ),
		'hours'     => array( 'Support hours', 'Monday – Friday, 9:00 – 18:00 (Beijing time)', 'Shown on Contact, Warranty and in the footer.' ),
		'facebook'  => array( 'Facebook URL', '', '' ),
		'youtube'   => array( 'YouTube URL', '', 'Also where repair tutorials are published.' ),
		'instagram' => array( 'Instagram URL', '', '' ),
		'tiktok'    => array( 'TikTok URL', '', '' ),
		'home_videos' => array( 'Homepage videos', '', 'YouTube Shorts links (or video ids), one per line. They play in "Watch it in action" on the homepage; the section stays hidden while this is empty.' ),
		'footer_style' => array( 'Footer', 'designed', 'Designed = the AUN footer with icons. Flatsome widgets = the footer built in Appearance → Widgets.' ),
	);
}

function aunstore_detail( $key ) {
	$saved  = get_option( AUNSTORE_DETAILS_OPTION, array() );
	$fields = aunstore_detail_fields();
	if ( is_array( $saved ) && isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] ) ) {
		return trim( (string) $saved[ $key ] );
	}
	return isset( $fields[ $key ] ) ? $fields[ $key ][1] : '';
}

/**
 * Pasted video links → what [aun_shorts] takes: YouTube ids (from shorts/, youtu.be/, watch?v=, embed/ links
 * or bare 11-character ids), media-library ids, or direct .mp4/.webm links. Anything else is ignored.
 */
function aunstore_parse_videos( $text ) {
	$ids = array();
	foreach ( preg_split( '/[\r\n,]+/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) continue;
		if ( preg_match( '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:shorts/|embed/|live/|v/|watch\?(?:[^#]*&)?v=))([A-Za-z0-9_-]{11})~', $line, $m ) ) {
			$ids[] = $m[1];
		} elseif ( preg_match( '/^\d{1,9}$/', $line ) ) {
			$ids[] = $line;
		} elseif ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $line ) ) {
			$ids[] = $line;
		} elseif ( preg_match( '~^https?://\S+\.(?:mp4|webm|mov)(?:\?\S*)?$~i', $line ) ) {
			$ids[] = esc_url_raw( $line );
		}
	}
	return array_values( array_unique( $ids ) );
}

/** URL of the "Projectors" product category, falling back to the shop page. */
function aunstore_projectors_url() {
	if ( taxonomy_exists( 'product_cat' ) ) {
		$link = get_term_link( 'projectors', 'product_cat' );
		if ( ! is_wp_error( $link ) ) return $link;
	}
	if ( function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ) {
		return get_permalink( wc_get_page_id( 'shop' ) );
	}
	return home_url( '/shop/' );
}

/* ── Settings screen ─────────────────────────────────────────────────── */

add_action( 'admin_menu', function () {
	add_options_page( 'AUN Store Details', 'AUN Store Details', 'manage_options', 'aunstore-details', 'aunstore_details_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'aunstore_details_group', AUNSTORE_DETAILS_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => function ( $in ) {
			$out = array();
			foreach ( aunstore_detail_fields() as $key => $f ) {
				$v = isset( $in[ $key ] ) ? trim( wp_unslash( (string) $in[ $key ] ) ) : '';
				if ( in_array( $key, array( 'facebook', 'youtube', 'instagram', 'tiktok' ), true ) ) {
					$v = esc_url_raw( $v );
				} elseif ( 'email' === $key ) {
					$v = sanitize_email( $v );
				} elseif ( 'whatsapp' === $key ) {
					$v = preg_replace( '/\D+/', '', $v );
				} elseif ( 'footer_style' === $key ) {
					$v = 'flatsome' === $v ? 'flatsome' : 'designed';
				} elseif ( 'address' === $key || 'home_videos' === $key ) {
					$v = sanitize_textarea_field( $v );
				} else {
					$v = sanitize_text_field( $v );
				}
				$out[ $key ] = $v;
			}
			return $out;
		},
	) );
} );

function aunstore_details_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$saved = get_option( AUNSTORE_DETAILS_OPTION, array() );
	?>
	<div class="wrap">
		<h1>AUN Store Details</h1>
		<p>Used by the Contact, Warranty &amp; Support and policy pages, the footer and the product Warranty tab. Empty fields are hidden on the site.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'aunstore_details_group' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( aunstore_detail_fields() as $key => $f ) :
					$val = isset( $saved[ $key ] ) ? $saved[ $key ] : $f[1]; ?>
					<tr>
						<th scope="row"><label for="aunstore-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f[0] ); ?></label></th>
						<td>
							<?php if ( 'footer_style' === $key ) : ?>
								<select id="aunstore-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( AUNSTORE_DETAILS_OPTION . "[$key]" ); ?>">
									<option value="designed" <?php selected( $val, 'designed' ); ?>>Designed (AUN)</option>
									<option value="flatsome" <?php selected( $val, 'flatsome' ); ?>>Flatsome widgets</option>
								</select>
							<?php elseif ( 'address' === $key || 'home_videos' === $key ) : ?>
								<textarea id="aunstore-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( AUNSTORE_DETAILS_OPTION . "[$key]" ); ?>" rows="<?php echo 'home_videos' === $key ? 6 : 3; ?>" class="large-text"<?php echo 'home_videos' === $key ? ' placeholder="https://youtube.com/shorts/…"' : ''; ?>><?php echo esc_textarea( $val ); ?></textarea>
								<?php if ( 'home_videos' === $key && '' !== trim( (string) $val ) ) : $n = count( aunstore_parse_videos( $val ) ); ?>
									<p class="description" style="color:<?php echo $n ? '#1a7f37' : '#b32d2e'; ?>"><?php echo $n ? esc_html( sprintf( _n( '%d video recognised.', '%d videos recognised.', $n ), $n ) ) : 'No video recognised — paste YouTube links such as https://youtube.com/shorts/abc123DEF45'; ?></p>
								<?php endif; ?>
							<?php else : ?>
								<input id="aunstore-<?php echo esc_attr( $key ); ?>" type="text" class="regular-text" name="<?php echo esc_attr( AUNSTORE_DETAILS_OPTION . "[$key]" ); ?>" value="<?php echo esc_attr( $val ); ?>">
							<?php endif; ?>
							<?php if ( $f[2] ) : ?><p class="description"><?php echo esc_html( $f[2] ); ?></p><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ── Shortcodes ──────────────────────────────────────────────────────── */

add_shortcode( 'aunstore_info', function ( $atts ) {
	$a   = shortcode_atts( array( 'key' => '', 'link' => '1' ), $atts, 'aunstore_info' );
	$val = aunstore_detail( sanitize_key( $a['key'] ) );
	if ( '' === $val ) return '';
	$link = '1' === (string) $a['link'];
	switch ( $a['key'] ) {
		case 'email':
			return $link ? '<a href="mailto:' . esc_attr( $val ) . '">' . esc_html( $val ) . '</a>' : esc_html( $val );
		case 'phone':
			return $link ? '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $val ) ) . '">' . esc_html( $val ) . '</a>' : esc_html( $val );
		case 'whatsapp':
			return $link ? '<a href="https://wa.me/' . esc_attr( $val ) . '" target="_blank" rel="noopener">+' . esc_html( $val ) . '</a>' : '+' . esc_html( $val );
		case 'address':
			return nl2br( esc_html( $val ) );
		default:
			return esc_html( $val );
	}
} );

add_shortcode( 'aunstore_year', function () {
	return esc_html( wp_date( 'Y' ) );
} );

add_shortcode( 'aunstore_link', function ( $atts ) {
	$a    = shortcode_atts( array( 'page' => '', 'text' => '', 'wrap' => '' ), $atts, 'aunstore_link' );
	$page = get_page_by_path( sanitize_title( $a['page'] ) );
	if ( ! $page || 'publish' !== $page->post_status ) return '';
	$html = '<a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( '' !== $a['text'] ? $a['text'] : get_the_title( $page ) ) . '</a>';
	return 'li' === $a['wrap'] ? '<li>' . $html . '</li>' : $html;
} );

add_shortcode( 'aunstore_contact_cards', function () {
	$cards = array();
	if ( $v = aunstore_detail( 'whatsapp' ) ) {
		$cards[] = array( 'fa-brands fa-whatsapp', 'WhatsApp', '+' . $v, 'https://wa.me/' . $v, '#16a34a' );
	}
	if ( $v = aunstore_detail( 'email' ) ) {
		$cards[] = array( 'fa-solid fa-envelope', 'Email', $v, 'mailto:' . $v, '#0188fe' );
	}
	if ( $v = aunstore_detail( 'phone' ) ) {
		$cards[] = array( 'fa-solid fa-phone', 'Phone', $v, 'tel:' . preg_replace( '/[^\d+]/', '', $v ), '#0188fe' );
	}
	if ( $v = aunstore_detail( 'hours' ) ) {
		$cards[] = array( 'fa-solid fa-clock', 'Support hours', $v, '', '#b45309' );
	}
	if ( ! $cards ) return '';

	$out = '<div class="aunstore-contact-cards">';
	foreach ( $cards as $c ) {
		$inner = '<span class="aunstore-cc-icon" style="color:' . esc_attr( $c[4] ) . '"><i class="' . esc_attr( $c[0] ) . '" aria-hidden="true"></i></span>'
			. '<span class="aunstore-cc-label">' . esc_html( $c[1] ) . '</span>'
			. '<span class="aunstore-cc-value">' . esc_html( $c[2] ) . '</span>';
		$out .= $c[3]
			? '<a class="aunstore-cc" href="' . esc_url( $c[3] ) . '"' . ( 0 === strpos( $c[3], 'https://' ) ? ' target="_blank" rel="noopener"' : '' ) . '>' . $inner . '</a>'
			: '<div class="aunstore-cc">' . $inner . '</div>';
	}
	return $out . '</div>';
} );

/**
 * [aunstore_help_panel] — the two-panel "Talk to a Human" / "Warranty & Support" block that closes
 * every AUN info and policy page (same markup as the BD site), filled from Store Details.
 */
add_shortcode( 'aunstore_help_panel', function () {
	$row = function ( $url, $icon, $icon_color, $label, $text_color, $new_tab = false, $cls = '' ) {
		return '<a class="aunstore-hp-link' . ( $cls ? ' ' . $cls : '' ) . '" href="' . esc_url( $url ) . '"' . ( $new_tab ? ' target="_blank" rel="noopener"' : '' )
			. ' style="color:' . esc_attr( $text_color ) . ';"><i class="' . esc_attr( $icon ) . '" style="font-size:19px;color:' . esc_attr( $icon_color ) . ';width:22px;text-align:center;"></i> ' . esc_html( $label ) . '</a>';
	};
	$page = function ( $slug ) {
		$p = get_page_by_path( $slug );
		return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : '';
	};

	$left = '';
	if ( $v = aunstore_detail( 'whatsapp' ) ) $left .= $row( 'https://wa.me/' . $v, 'fa-brands fa-whatsapp', '#25D366', 'WhatsApp Chat', '#166534', true, 'is-wa' );
	if ( $v = aunstore_detail( 'email' ) )    $left .= $row( 'mailto:' . $v, 'fa-solid fa-envelope', '#0188fe', $v, '#0f172a' );
	if ( $v = aunstore_detail( 'phone' ) )    $left .= $row( 'tel:' . preg_replace( '/[^\d+]/', '', $v ), 'fa-solid fa-phone', '#475569', $v, '#334155' );
	if ( '' === $left && ( $u = $page( 'contact-us' ) ) ) $left .= $row( $u, 'fa-solid fa-headset', '#0188fe', 'Contact Us', '#0f172a' );
	$hours = aunstore_detail( 'hours' );

	$right = '';
	foreach ( array(
		array( 'warranty', 'fa-solid fa-screwdriver-wrench', 'Warranty & Support' ),
		array( 'verify-authenticity', 'fa-solid fa-fingerprint', 'Verify Authenticity' ),
		array( 'shipping-policy', 'fa-solid fa-truck-fast', 'Shipping Policy' ),
		array( 'returns-and-refunds', 'fa-solid fa-arrow-right-arrow-left', 'Returns & Refunds' ),
	) as $l ) {
		if ( $u = $page( $l[0] ) ) $right .= $row( $u, $l[1], '#0188fe', $l[2], '#0f172a' );
	}

	return '<div class="aunstore-hp">'
		. '<div class="aunstore-hp-side aunstore-hp-left"><h3>Talk to a Human</h3><p>Real people, real answers &mdash; before or after you buy.</p>' . $left
		. ( $hours ? '<p class="aunstore-hp-hours"><i class="fa-solid fa-clock"></i> ' . esc_html( $hours ) . '</p>' : '' ) . '</div>'
		. '<div class="aunstore-hp-side"><h3>Warranty &amp; Support</h3><p>Warranty claims, authenticity checks and policies.</p>' . $right . '</div>'
		. '</div>';
} );

/** Compact icon list for dark areas such as the footer. */
add_shortcode( 'aunstore_contact_list', function () {
	$rows = array();
	if ( $v = aunstore_detail( 'email' ) )    $rows[] = array( 'fa-solid fa-envelope', '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>' );
	if ( $v = aunstore_detail( 'whatsapp' ) ) $rows[] = array( 'fa-brands fa-whatsapp', '<a href="https://wa.me/' . esc_attr( $v ) . '" target="_blank" rel="noopener">+' . esc_html( $v ) . '</a>' );
	if ( $v = aunstore_detail( 'phone' ) )    $rows[] = array( 'fa-solid fa-phone', '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $v ) ) . '">' . esc_html( $v ) . '</a>' );
	if ( $v = aunstore_detail( 'hours' ) )    $rows[] = array( 'fa-solid fa-clock', esc_html( $v ) );
	if ( $v = aunstore_detail( 'address' ) )  $rows[] = array( 'fa-solid fa-location-dot', nl2br( esc_html( $v ) ) );
	if ( ! $rows ) return '';
	$out = '<ul class="aunstore-contact-list">';
	foreach ( $rows as $r ) {
		$out .= '<li><i class="' . esc_attr( $r[0] ) . '" aria-hidden="true"></i><span>' . $r[1] . '</span></li>';
	}
	return $out . '</ul>';
} );

add_shortcode( 'aunstore_social', function () {
	$icons = array( 'facebook' => 'fa-facebook-f', 'youtube' => 'fa-youtube', 'instagram' => 'fa-instagram', 'tiktok' => 'fa-tiktok' );
	$out   = '';
	foreach ( $icons as $key => $icon ) {
		if ( $url = aunstore_detail( $key ) ) {
			$out .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( ucfirst( $key ) ) . '"><i class="fa-brands ' . esc_attr( $icon ) . '" aria-hidden="true"></i></a>';
		}
	}
	return $out ? '<div class="aunstore-social">' . $out . '</div>' : '';
} );

// The footer widgets (Custom HTML) use the shortcodes above; make sure they run there.
add_filter( 'widget_custom_html_content', function ( $content ) {
	return false !== strpos( $content, '[aunstore_' ) ? do_shortcode( $content ) : $content;
}, 12 );

/* ── Shared front-end styles (info-page FAQ, contact cards, footer) ──── */

// Printed as a guarded <style> tag (not wp_add_inline_style) so WP Rocket's minify / Remove Unused CSS leave it alone.
add_action( 'wp_head', function () {
	echo '<style id="aunstore-core-css" data-no-optimize="1" data-no-minify="1">' . aunstore_core_css() . "</style>
";
}, 20 );

function aunstore_core_css() {
	return <<<'CSS'
.aun-faq{max-width:820px;margin:0 auto}
.aun-faq details{background:#fff;border:1px solid #e2e8f0;border-left:3px solid transparent;border-radius:10px;margin-bottom:12px;box-shadow:0 2px 5px rgba(0,0,0,.04);overflow:hidden;transition:border-color .2s ease,box-shadow .2s ease}
.aun-faq details[open]{border-left-color:#ffbc00;box-shadow:0 6px 18px rgba(1,136,254,.08)}
.aun-faq summary{list-style:none;cursor:pointer;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;gap:14px;font-size:16px;font-weight:700;color:#1e293b;outline:none}
.aun-faq summary::-webkit-details-marker{display:none}
.aun-faq summary:hover{background:#f8fafc}
.aun-faq summary .aun-chev{color:#0188fe;font-size:13px;transition:transform .25s ease;flex-shrink:0}
.aun-faq details[open] summary .aun-chev{transform:rotate(180deg)}
.aun-faq .aun-faq-a{padding:2px 22px 20px;color:#555;line-height:1.65;font-size:14.5px}
.aun-faq .aun-faq-a a{color:#0188fe}
.aunstore-contact-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;max-width:900px;margin:0 auto}
.aunstore-cc{display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:22px 16px;box-shadow:0 2px 6px rgba(15,23,42,.04);text-decoration:none!important;color:#0f172a;transition:box-shadow .2s ease,transform .2s ease}
a.aunstore-cc:hover{box-shadow:0 8px 22px rgba(1,136,254,.12);transform:translateY(-2px)}
.aunstore-cc-icon{font-size:24px}
.aunstore-cc-label{font-size:12px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#64748b}
.aunstore-cc-value{font-size:15px;font-weight:700;color:#0f172a;word-break:break-word}
.aunstore-social{display:flex;gap:10px;flex-wrap:wrap}
.aunstore-social a{width:38px;height:38px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.08);color:#fff!important;font-size:16px;transition:background .2s ease}
.aunstore-social a:hover{background:#0188fe}
.aunstore-policy{max-width:860px;margin:0 auto;color:#334155;line-height:1.75;font-size:15.5px}
.aunstore-policy h2{font-size:22px;color:#0f172a;margin:34px 0 10px}
.aunstore-policy h3{font-size:17px;color:#0f172a;margin:22px 0 8px}
.aunstore-policy ul{margin:0 0 16px 20px}
.aunstore-policy a{color:#0188fe}
.aunstore-fw ul{list-style:none;margin:0;padding:0}
.aunstore-fw li{margin:0 0 9px;padding:0;border:0}
.aunstore-fw a{opacity:.85}
.aunstore-fw a:hover{opacity:1;color:#4da8ff}
.aunstore-fw p{font-size:14px;line-height:1.7;margin:0 0 12px;opacity:.85}
.aunstore-fw .aunstore-fbrand{font-size:18px;font-weight:800;margin:0 0 6px;opacity:1}
.aunstore-contact-list{list-style:none;margin:0;padding:0}
.aunstore-contact-list li{display:flex;gap:10px;align-items:flex-start;margin:0 0 10px;font-size:14px;line-height:1.5}
.aunstore-contact-list i{width:16px;margin-top:3px;color:#4da8ff;flex-shrink:0;text-align:center}
.aunstore-hp{background:#fff;border-radius:12px;overflow:hidden;display:flex;flex-wrap:wrap;text-align:left}
.aunstore-hp-side{flex:1;min-width:280px;padding:35px;background:#fff}
.aunstore-hp-left{background:#f8fafc;border-right:1px solid #e2e8f0}
.aunstore-hp h3{margin:0 0 8px;font-size:20px;font-weight:800;color:#0f172a}
.aunstore-hp-side>p{color:#64748b;font-size:14px;margin:0 0 25px}
.aunstore-hp-link{display:flex!important;align-items:center;gap:12px;background:#fff;padding:14px 18px;border-radius:8px;border:1px solid #e2e8f0;text-decoration:none!important;font-weight:600;margin-bottom:12px;transition:all .2s}
.aunstore-hp-link:hover{background:#eff6ff;border-color:#bfdbfe}
.aunstore-hp-link.is-wa:hover{background:#dcfce7;border-color:#86efac}
.aunstore-hp .aunstore-hp-hours{font-size:13px;color:#64748b;margin:14px 0 0}
CSS;
}
