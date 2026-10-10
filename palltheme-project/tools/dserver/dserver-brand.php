<?php
/**
 * DServer Technology: brand the palltheme demo site (run with `wp eval-file`).
 * Re-runnable: everything is matched by slug/meta before being created.
 */

$company = 'DServer Technology';
$base    = home_url( '/' );
$log     = static function ( string $m ) { WP_CLI::log( $m ); };

/* ---------- Business settings (fictional contact details — replace before launch) ---------- */
$s = (array) get_option( 'pallcore_settings', array() );
$s = array_merge(
	$s,
	array(
		'company_name'    => $company,
		'phone'           => '+44 20 7946 0958',
		'email'           => 'hello@dserver.example',
		'careers_email'   => 'careers@dserver.example',
		'address'         => "Unit 4, 120 Server Lane\nLondon EC2A 0DS\nUnited Kingdom",
		'hours'           => "Sales | Mon – Fri 9:00 – 18:00 (UK)\nSupport | 24/7 for all VPS & server customers\nNetwork Operations | 24/7/365",
		'hours_short'     => 'Sales Mon – Fri 9:00 – 18:00 · 24/7 server support',
		'map_query'       => 'Shoreditch, London',
		'whatsapp'        => '+442079460958',
		'telegram'        => 'dserver_support',
		'messenger'       => 'dserver.tech',
		'hero_eyebrow'    => 'NVMe VPS · Dedicated Servers · Cloud',
		'hero_title'      => 'European servers built for *speed and uptime*',
		'hero_text'       => 'Deploy NVMe VPS in under a minute, scale to dedicated hardware when you need it, and get help from engineers who answer 24/7. Data centers in Frankfurt, Amsterdam and London.',
		'hero_btn1_text'  => 'View VPS plans',
		'hero_btn1_url'   => $base . 'vps-hosting/',
		'hero_btn2_text'  => 'Talk to sales',
		'hero_cards'      => "60 s | VPS deployment | speed\n24/7 | Engineer support | headset\n3 | EU & UK locations | globe",
		'stats'           => "4,000+ | Servers deployed\n99.95% | Network uptime target\n3 | Data center locations\n24/7 | Engineer support",
		'trust_items'     => "speed | NVMe on every plan | Enterprise NVMe storage and modern AMD EPYC CPUs as standard.\nshield | DDoS protection included | Always-on network filtering at no extra cost.\nheadset | Real engineers, 24/7 | Tickets, chat and phone answered by system administrators.\nglobe | European data centers | Frankfurt, Amsterdam and London, GDPR-compliant.\nbackup | Snapshots & backups | One-click snapshots plus optional daily off-site backups.\ncheck | No lock-in | Monthly billing, cancel any time, upgrade in place.",
		'cta_title'       => 'Launch your server in 60 seconds',
		'cta_text'        => 'Pick a plan, choose your location and operating system, and you are online. Need something custom? Our engineers will size it with you.',
		'cta_button'      => 'Get started',
		'newsletter_text' => 'Product news, maintenance notices and server tips. No spam.',
		'chat_message'    => 'Hello DServer! I have a question about your VPS plans.',
		'search_suggestions' => 'VPS, Dedicated server, NVMe, Backup, Colocation, DDoS',
		'intro_pall_service'  => 'Hosting, servers, cloud and managed IT — everything you need to run your applications on fast European infrastructure.',
		'intro_pall_solution' => 'Proven infrastructure designs for the industries we host, from SaaS start-ups to banks and public sector.',
		'intro_pall_team'     => 'The engineers and specialists behind DServer Technology (demo profiles).',
	)
);
update_option( 'pallcore_settings', $s );
update_option( 'blogname', $company );
update_option( 'blogdescription', 'Fast NVMe VPS, dedicated servers and cloud in Europe' );
$log( '✔ Business settings and hero' );

/* ---------- Brand colours (indigo + cyan) ---------- */
foreach ( array(
	'color_primary'   => '#4F46E5',
	'color_secondary' => '#0B1020',
	'color_accent'    => '#06B6D4',
	'color_dark'      => '#070B18',
	'color_light'     => '#F5F7FF',
) as $k => $v ) {
	set_theme_mod( $k, $v );
}
set_theme_mod( 'footer_description', $company . ' runs fast NVMe VPS, dedicated servers and managed cloud from European data centers, backed by 24/7 engineer support.' );
set_theme_mod( 'header_cta_text', 'Get a VPS' );
set_theme_mod( 'header_cta_url', $base . 'vps-hosting/' );

/* ---------- Logo (original SVG) ---------- */
$up  = wp_upload_dir();
$dir = trailingslashit( $up['basedir'] ) . 'dserver/';
wp_mkdir_p( $dir );
$logo = static function ( string $text_col, string $sub_col ): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 250 52" width="250" height="52"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6366F1"/><stop offset="1" stop-color="#06B6D4"/></linearGradient></defs>'
		. '<rect x="2" y="4" width="44" height="44" rx="11" fill="url(#g)"/>'
		. '<rect x="12" y="14" width="24" height="6" rx="2" fill="#fff" opacity=".95"/><rect x="12" y="23" width="24" height="6" rx="2" fill="#fff" opacity=".8"/><rect x="12" y="32" width="24" height="6" rx="2" fill="#fff" opacity=".65"/>'
		. '<circle cx="32" cy="17" r="1.6" fill="#06B6D4"/><circle cx="32" cy="26" r="1.6" fill="#22C55E"/><circle cx="32" cy="35" r="1.6" fill="#6366F1"/>'
		. '<text x="56" y="31" font-family="Segoe UI, Inter, Arial, sans-serif" font-size="25" font-weight="800" fill="' . $text_col . '" letter-spacing="-0.5">DServer</text>'
		. '<text x="57" y="45" font-family="Segoe UI, Inter, Arial, sans-serif" font-size="9.5" font-weight="700" fill="' . $sub_col . '" letter-spacing="2.6">TECHNOLOGY</text></svg>';
};
$attach = static function ( string $file, string $svg, string $title ) use ( $dir, $up ): int {
	$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_dserver_logo', 'meta_value' => $file, 'fields' => 'ids', 'numberposts' => 1 ) );
	file_put_contents( $dir . $file, $svg );
	if ( $found ) {
		return (int) $found[0];
	}
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/svg+xml', 'post_title' => $title, 'post_status' => 'inherit', 'guid' => trailingslashit( $up['baseurl'] ) . 'dserver/' . $file ), $dir . $file );
	wp_update_attachment_metadata( $id, array( 'width' => 250, 'height' => 52, 'file' => 'dserver/' . $file, 'sizes' => array() ) );
	update_post_meta( $id, '_wp_attachment_image_alt', 'DServer Technology' );
	update_post_meta( $id, '_dserver_logo', $file );
	return (int) $id;
};
$logo_dark_bg = $attach( 'dserver-logo-light.svg', $logo( '#FFFFFF', '#67E8F9' ), 'DServer logo (light, for dark backgrounds)' );
$logo_main    = $attach( 'dserver-logo.svg', $logo( '#0B1020', '#4F46E5' ), 'DServer logo' );
set_theme_mod( 'custom_logo', $logo_main );
set_theme_mod( 'logo_light', $logo_dark_bg );
set_theme_mod( 'logo_dark', $logo_dark_bg );
$log( '✔ Logo, colours, header button' );

/* ---------- Helpers: posts + Elementor sections ---------- */
$el_id   = static fn() => substr( md5( wp_generate_uuid4() ), 0, 7 );
$section = static function ( string $type, array $settings ) use ( $el_id ): array {
	foreach ( array( 'button_url', 'link_url', 'button2_url' ) as $k ) {
		if ( isset( $settings[ $k ] ) && is_string( $settings[ $k ] ) ) {
			$settings[ $k ] = array( 'url' => $settings[ $k ], 'is_external' => '', 'nofollow' => '' );
		}
	}
	return array(
		'id'       => $el_id(),
		'elType'   => 'section',
		'settings' => array( 'layout' => 'full_width', 'gap' => 'no' ),
		'elements' => array( array( 'id' => $el_id(), 'elType' => 'column', 'settings' => array( '_column_size' => 100 ), 'elements' => array( array( 'id' => $el_id(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() ) ) ) ),
	);
};
$shortcode = static function ( string $tag, array $atts ): string {
	$out = '';
	foreach ( $atts as $k => $v ) {
		$out .= ' ' . $k . '="' . esc_attr( str_replace( "\n", '\n', (string) $v ) ) . '"';
	}
	return '<!-- wp:shortcode -->[' . $tag . $out . ']<!-- /wp:shortcode -->' . "\n";
};
$upsert = static function ( array $args, array $meta = array() ): int {
	$found = get_page_by_path( $args['post_name'], OBJECT, $args['post_type'] );
	$args  = wp_parse_args( $args, array( 'post_status' => 'publish' ) );
	if ( $found ) {
		$args['ID'] = $found->ID;
	}
	$id = (int) wp_insert_post( wp_slash( $args ) );
	update_post_meta( $id, '_pall_demo', 1 );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, '_pall_' . $k, $v );
	}
	return $id;
};
$photo = static function ( string $key ): int {
	$f = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_pall_demo_media', 'meta_value' => 'photo:' . $key, 'fields' => 'ids', 'numberposts' => 1 ) );
	return $f ? (int) $f[0] : 0;
};

/* ---------- VPS plans (EUR, monthly, excl. VAT) ----------
 * Positioned in the European mid-market (Oct 2026 references: OVHcloud VPS-1 €6.49 / VPS-2 €9.99 /
 * VPS-3 €19.99; Hetzner CPX32 €35.49 after the June 2026 increase; Contabo entry ~€4.40–5.50).
 */
$plans = array(
	array( 'vps-s', 'VPS S', 'For personal projects, blogs and test environments.', '€6.49', array( '2 vCPU (AMD EPYC)', '4 GB DDR5 RAM', '80 GB NVMe SSD', '20 TB traffic · 1 Gbit/s', '1 IPv4 + /64 IPv6', 'DDoS protection', 'Snapshots' ), false, '' ),
	array( 'vps-m', 'VPS M', 'For business websites, WooCommerce stores and small apps.', '€11.99', array( '4 vCPU (AMD EPYC)', '8 GB DDR5 RAM', '160 GB NVMe SSD', '20 TB traffic · 1 Gbit/s', '1 IPv4 + /64 IPv6', 'DDoS protection', 'Snapshots + weekly backup' ), true, 'Most popular' ),
	array( 'vps-l', 'VPS L', 'For busy stores, SaaS apps and databases.', '€22.99', array( '8 vCPU (AMD EPYC)', '16 GB DDR5 RAM', '320 GB NVMe SSD', '30 TB traffic · 2 Gbit/s', '1 IPv4 + /64 IPv6', 'DDoS protection', 'Daily off-site backup' ), false, '' ),
	array( 'vps-xl', 'VPS XL', 'For high-traffic platforms and container clusters.', '€44.99', array( '16 vCPU (AMD EPYC)', '32 GB DDR5 RAM', '640 GB NVMe SSD', '40 TB traffic · 2 Gbit/s', '2 IPv4 + /64 IPv6', 'Advanced DDoS protection', 'Daily off-site backup' ), false, 'Best value / core' ),
);
$plan_ids = array();
foreach ( $plans as $i => [ $slug, $name, $desc, $price, $features, $featured, $badge ] ) {
	$plan_ids[] = $upsert(
		array( 'post_type' => 'pall_pricing', 'post_name' => $slug, 'post_title' => $name, 'post_excerpt' => $desc, 'menu_order' => 20 + $i ),
		array( 'price' => $price, 'period' => '/month', 'features' => $features, 'featured' => $featured ? '1' : '', 'badge' => $badge, 'button_text' => 'Deploy ' . $name, 'button_url' => $base . 'contact/?plan=' . $slug )
	);
}
$ids_csv = implode( ',', $plan_ids );
$log( '✔ VPS plans: ' . count( $plan_ids ) );

/* ---------- Extra services ---------- */
$svc_cat = term_exists( 'Infrastructure', 'pall_service_cat' );
$extra   = array(
	array( 'dedicated-servers', 'Dedicated Servers', 'server', 'Single-tenant bare-metal servers with AMD EPYC and Intel Xeon CPUs, NVMe RAID and 10 Gbit/s uplinks — delivered in hours, not weeks.', 'svc-server', array( 'AMD EPYC & Intel Xeon configurations', 'NVMe RAID 1/10 storage', '1–10 Gbit/s network ports', 'Out-of-band IPMI/KVM access', 'Hardware replacement within 4 hours', 'Optional fully managed service' ) ),
	array( 'colocation', 'Colocation', 'rack', 'Host your own hardware in our Tier III European facilities with redundant power, cooling and carrier-neutral connectivity.', 'svc-datacenter', array( '1U to full-rack options', 'A+B redundant power feeds', 'Carrier-neutral connectivity', 'Remote hands 24/7', 'Biometric access control', 'Cross-connects and private cages' ) ),
);
foreach ( $extra as $i => [ $slug, $title, $icon, $excerpt, $ph, $features ] ) {
	$body = '<p>' . esc_html( $excerpt ) . '</p><h2>What is included</h2><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $features ) ) . '</li></ul><p>Need help choosing? <a href="' . esc_url( $base . 'contact/' ) . '">Talk to our engineers</a> or compare our <a href="' . esc_url( $base . 'vps-hosting/' ) . '">VPS plans</a>.</p>';
	$id   = $upsert( array( 'post_type' => 'pall_service', 'post_name' => $slug, 'post_title' => $title, 'post_excerpt' => $excerpt, 'post_content' => $body, 'menu_order' => -2 + $i ), array( 'icon' => $icon, 'features' => $features ) );
	$img  = $photo( $ph ) ?: $photo( 'svc-hosting' );
	if ( $img && ! has_post_thumbnail( $id ) ) {
		set_post_thumbnail( $id, $img );
	}
	if ( $svc_cat ) {
		wp_set_object_terms( $id, (int) ( is_array( $svc_cat ) ? $svc_cat['term_id'] : $svc_cat ), 'pall_service_cat' );
	}
}
$log( '✔ Extra services: Dedicated Servers, Colocation' );

/* ---------- VPS Hosting page ---------- */
$compare = '<div class="pt-prose"><h2>Compare VPS plans</h2><p>All prices are per month, excluding VAT. Billed monthly — no setup fee, no lock-in.</p>'
	. '<div class="pt-table-wrap"><table class="pt-compare"><thead><tr><th>Specification</th><th>VPS S</th><th>VPS M</th><th>VPS L</th><th>VPS XL</th></tr></thead><tbody>'
	. '<tr><td>vCPU (AMD EPYC)</td><td>2</td><td>4</td><td>8</td><td>16</td></tr>'
	. '<tr><td>RAM (DDR5)</td><td>4 GB</td><td>8 GB</td><td>16 GB</td><td>32 GB</td></tr>'
	. '<tr><td>NVMe storage</td><td>80 GB</td><td>160 GB</td><td>320 GB</td><td>640 GB</td></tr>'
	. '<tr><td>Traffic included</td><td>20 TB</td><td>20 TB</td><td>30 TB</td><td>40 TB</td></tr>'
	. '<tr><td>Port speed</td><td>1 Gbit/s</td><td>1 Gbit/s</td><td>2 Gbit/s</td><td>2 Gbit/s</td></tr>'
	. '<tr><td>IPv4 / IPv6</td><td>1 / /64</td><td>1 / /64</td><td>1 / /64</td><td>2 / /64</td></tr>'
	. '<tr><td>Backups</td><td>Snapshots</td><td>Weekly</td><td>Daily off-site</td><td>Daily off-site</td></tr>'
	. '<tr><td>Price / month</td><td><strong>€6.49</strong></td><td><strong>€11.99</strong></td><td><strong>€22.99</strong></td><td><strong>€44.99</strong></td></tr>'
	. '</tbody></table></div>'
	. '<h3>Add-ons</h3><ul><li>Extra IPv4 address — €1.50/month</li><li>Managed server (updates, monitoring, hardening) — €19/month</li><li>Control panel licence (cPanel/Plesk) — from €12/month</li><li>Additional 100 GB NVMe volume — €4.50/month</li></ul>'
	. '<p><small>Prices are DServer Technology demo prices, set against European market rates in October 2026. Locations: Frankfurt (DE), Amsterdam (NL), London (UK).</small></p></div>';
$lottie = PALLCORE_URL . 'assets/lottie/network-pulse.json';
$vps_blocks = array(
	array( 'pall_pricing', array( 'eyebrow' => 'VPS hosting', 'title' => 'NVMe VPS *plans*', 'lead' => 'Choose a plan, a location and an operating system — your server is online in about 60 seconds.', 'align' => 'center', 'ids' => $ids_csv, 'count' => 4, 'columns' => 4 ) ),
	array( 'html', $compare ),
	array( 'pall_features', array( 'eyebrow' => 'Included with every VPS', 'title' => 'Everything you need, *no surprises*', 'columns' => 3, 'background' => 'alt', 'items' => "speed | NVMe & AMD EPYC | Modern CPUs and enterprise NVMe on every plan.\nshield | DDoS protection | Always-on network filtering included.\nlayers | Upgrade in place | Move to a bigger plan with one click and a reboot.\nterminal | Full root access | Linux or Windows, your choice of OS and stack.\nbackup | Snapshots | Take a snapshot before every change; restore in minutes.\nheadset | 24/7 engineers | Real system administrators, day and night." ) ),
	array( 'pall_split', array( 'eyebrow' => 'Locations', 'title' => 'Low latency *across Europe*', 'lottie' => $lottie, 'text' => 'Deploy in Frankfurt, Amsterdam or London. All three locations connect to major internet exchanges for fast routes to European and global users, and your data stays in the EU or UK.', 'bullets' => "Frankfurt — DE-CIX connected\nAmsterdam — AMS-IX connected\nLondon — LINX connected\nGDPR-compliant data processing", 'button_text' => 'Ask about custom servers', 'button_url' => $base . 'contact/' ) ),
	array( 'pall_faq', array( 'eyebrow' => 'FAQ', 'title' => 'VPS questions', 'category' => 'hosting', 'align' => 'center', 'background' => 'alt' ) ),
	array( 'pall_cta', array() ),
);
$content  = '';
$elements = array();
foreach ( $vps_blocks as [ $tag, $atts ] ) {
	if ( 'html' === $tag ) {
		$content   .= '<!-- wp:html --><div class="pt-section pt-section--tight"><div class="pt-container pt-container--narrow pt-prose">' . $atts . '</div></div><!-- /wp:html -->';
		$sec        = $section( 'text-editor', array( 'editor' => $atts ) );
		$sec['settings'] += array( 'layout' => 'boxed', 'content_width' => array( 'unit' => 'px', 'size' => 1000 ), 'padding' => array( 'unit' => 'px', 'top' => '40', 'right' => '16', 'bottom' => '56', 'left' => '16', 'isLinked' => false ) );
		$sec['settings']['layout'] = 'boxed';
		$elements[] = $sec;
		continue;
	}
	$content .= $shortcode( $tag, $atts );
	$elements[] = $section( $tag, $atts );
}
$vps_id = $upsert( array( 'post_type' => 'page', 'post_name' => 'vps-hosting', 'post_title' => 'VPS Hosting', 'post_excerpt' => 'Fast NVMe virtual servers in Frankfurt, Amsterdam and London — from €6.49/month.', 'post_content' => $content ) );
update_post_meta( $vps_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $vps_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $vps_id, '_elementor_version', ELEMENTOR_VERSION );
update_post_meta( $vps_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
delete_post_meta( $vps_id, '_elementor_element_cache' );
$log( '✔ VPS Hosting page (Elementor) #' . $vps_id );

/* ---------- Homepage: add the VPS pricing section after the services grid ---------- */
$home = (int) get_option( 'page_on_front' );
$data = json_decode( (string) get_post_meta( $home, '_elementor_data', true ), true );
if ( is_array( $data ) && ! str_contains( wp_json_encode( $data ), '"ids":"' . $ids_csv . '"' ) ) {
	$pos = 0;
	foreach ( $data as $i => $sec ) {
		if ( 'pall_services' === ( $sec['elements'][0]['elements'][0]['widgetType'] ?? '' ) ) {
			$pos = $i + 1;
		}
	}
	array_splice( $data, $pos, 0, array( $section( 'pall_pricing', array( 'eyebrow' => 'VPS hosting', 'title' => 'NVMe VPS from *€6.49/month*', 'lead' => 'European data centers, AMD EPYC CPUs and 24/7 engineer support on every plan.', 'align' => 'center', 'ids' => $ids_csv, 'count' => 4, 'columns' => 4, 'background' => 'alt', 'link_text' => 'Compare all plans', 'link_url' => $base . 'vps-hosting/' ) ) ) );
	update_post_meta( $home, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	delete_post_meta( $home, '_elementor_element_cache' );
	$log( '✔ Homepage: VPS pricing section added' );
}
// The pricing page keeps the managed IT plans only.
$pricing = get_page_by_path( 'pricing' );
if ( $pricing ) {
	$pd = json_decode( (string) get_post_meta( $pricing->ID, '_elementor_data', true ), true );
	foreach ( (array) $pd as &$sec ) {
		$w = &$sec['elements'][0]['elements'][0];
		if ( 'pall_pricing' === ( $w['widgetType'] ?? '' ) ) {
			$managed = get_posts( array( 'post_type' => 'pall_pricing', 'post_name__in' => array( 'essential', 'business', 'enterprise' ), 'fields' => 'ids', 'orderby' => 'menu_order', 'order' => 'ASC' ) );
			$w['settings']['ids']   = implode( ',', $managed );
			$w['settings']['title'] = 'Managed IT *plans*';
		}
		unset( $w );
	}
	unset( $sec );
	update_post_meta( $pricing->ID, '_elementor_data', wp_slash( wp_json_encode( $pd ) ) );
	delete_post_meta( $pricing->ID, '_elementor_element_cache' );
}

/* ---------- Menu: "VPS" after Services ---------- */
$menu  = wp_get_nav_menu_object( 'Primary Menu' );
$items = wp_get_nav_menu_items( $menu->term_id );
$has   = array_filter( $items, static fn( $it ) => (int) $it->object_id === $vps_id );
if ( ! $has ) {
	$after = 0;
	foreach ( $items as $it ) {
		if ( ! $it->menu_item_parent && 'Solutions' === $it->title ) {
			$after = (int) $it->menu_order;
		}
	}
	foreach ( $items as $it ) {
		if ( (int) $it->menu_order >= $after ) {
			wp_update_post( array( 'ID' => $it->ID, 'menu_order' => $it->menu_order + 1 ) );
		}
	}
	wp_update_nav_menu_item( $menu->term_id, 0, array( 'menu-item-title' => 'VPS', 'menu-item-object' => 'page', 'menu-item-object-id' => $vps_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => $after ) );
	$log( '✔ Menu: VPS added' );
}
$footer = wp_get_nav_menu_object( 'Footer: Services' );
if ( $footer && ! array_filter( wp_get_nav_menu_items( $footer->term_id ), static fn( $it ) => (int) $it->object_id === $vps_id ) ) {
	wp_update_nav_menu_item( $footer->term_id, 0, array( 'menu-item-title' => 'VPS Hosting', 'menu-item-object' => 'page', 'menu-item-object-id' => $vps_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => 1 ) );
}

if ( function_exists( 'palltheme_sync_elementor_kit' ) ) {
	palltheme_sync_elementor_kit();
}
delete_option( 'rewrite_rules' );
$log( '✔ Done' );
