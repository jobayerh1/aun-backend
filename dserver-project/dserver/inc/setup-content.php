<?php
/**
 * One-click website setup (Appearance → DServer Setup, or `wp dserver setup`).
 *
 * Builds every page with Elementor's FREE widgets (section/column, heading, text editor,
 * button, icon box, icon list, image, image box, counter, testimonial, HTML, shortcode,
 * Google Maps), so everything stays editable in Elementor. Re-running only fills in what
 * is missing; pages you have edited are never overwritten.
 *
 * @package DServer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Setup runner.
 */
final class DServer_Setup {

	/** @var string[] */
	private array $log = array();

	/** @var array<string,int> */
	private array $media = array();

	/** @var array<string,int> */
	private array $pages = array();

	/**
	 * Run.
	 *
	 * @return string[]
	 */
	public function run(): array {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return array( '✖ Elementor is not active. Install and activate Elementor (free), then run the setup again.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 600 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}

		$this->basics();
		$this->media();
		$this->logo();
		$this->elementor_kit();
		$this->posts();
		$this->build_pages();
		$this->menus();
		update_option( 'dserver_setup_done', time() );
		delete_option( 'rewrite_rules' );
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		$this->log[] = '✔ Done — visit the site.';
		return $this->log;
	}

	/* ---------------------------------------------------------------- basics */

	private function basics(): void {
		update_option( 'blogname', 'DServer Technology' );
		update_option( 'blogdescription', 'Fast NVMe VPS, dedicated servers and cloud in Europe' );
		if ( ! get_option( 'permalink_structure' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
		}
		update_option( 'elementor_disable_color_schemes', 'yes' );
		update_option( 'elementor_disable_typography_schemes', 'yes' );
		update_option( 'elementor_cpt_support', array( 'page', 'post' ) );
		// Remove the WordPress sample content if untouched.
		$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
		if ( $hello && str_contains( $hello->post_content, 'Welcome to WordPress' ) ) {
			wp_delete_post( $hello->ID, true );
		}
		$sample = get_page_by_path( 'sample-page' );
		if ( $sample && str_contains( $sample->post_content, 'This is an example page' ) ) {
			wp_delete_post( $sample->ID, true );
		}
		$this->log[] = '✔ Site title, permalinks, Elementor defaults';
	}

	/* ---------------------------------------------------------------- media */

	private function media(): void {
		$credits = json_decode( (string) file_get_contents( DSERVER_DIR . 'assets/media/credits.json' ), true ) ?: array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		foreach ( glob( DSERVER_DIR . 'assets/media/*.{webp,mp4}', GLOB_BRACE ) ?: array() as $file ) {
			$name = pathinfo( $file, PATHINFO_FILENAME );
			$c    = $credits[ basename( $file ) ] ?? array();
			$id   = $this->sideload( $file, $name, (string) ( $c['alt'] ?? ucfirst( str_replace( '-', ' ', $name ) ) ), $c );
			if ( $id ) {
				$this->media[ $name ] = $id;
			}
		}
		$this->log[] = '✔ Media library: ' . count( $this->media ) . ' licensed photos and video (credits on the Media Credits page)';
	}

	private function sideload( string $file, string $key, string $alt, array $credit ): int {
		$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_ds_media', 'meta_value' => $key, 'fields' => 'ids', 'numberposts' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( $found ) {
			return (int) $found[0];
		}
		$tmp = wp_tempnam( basename( $file ) );
		copy( $file, $tmp );
		$id = media_handle_sideload( array( 'name' => basename( $file ), 'tmp_name' => $tmp ), 0, ucfirst( str_replace( '-', ' ', $key ) ) );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			$this->log[] = '✖ ' . basename( $file ) . ': ' . $id->get_error_message();
			return 0;
		}
		update_post_meta( $id, '_ds_media', $key );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		foreach ( array( 'source', 'author', 'author_url', 'original_url', 'license', 'license_url' ) as $k ) {
			if ( ! empty( $credit[ $k ] ) ) {
				update_post_meta( $id, '_ds_credit_' . $k, $credit[ $k ] );
			}
		}
		return (int) $id;
	}

	private function img( string $key ): array {
		$id = $this->media[ $key ] ?? 0;
		return $id ? array( 'id' => $id, 'url' => (string) wp_get_attachment_url( $id ) ) : array( 'id' => '', 'url' => '' );
	}

	private function logo(): void {
		$svg = static fn( string $text, string $sub ) => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 250 52" width="250" height="52"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6366F1"/><stop offset="1" stop-color="#06B6D4"/></linearGradient></defs><rect x="2" y="4" width="44" height="44" rx="11" fill="url(#g)"/><rect x="12" y="14" width="24" height="6" rx="2" fill="#fff"/><rect x="12" y="23" width="24" height="6" rx="2" fill="#fff" opacity=".8"/><rect x="12" y="32" width="24" height="6" rx="2" fill="#fff" opacity=".6"/><circle cx="32" cy="17" r="1.6" fill="#06B6D4"/><circle cx="32" cy="26" r="1.6" fill="#22C55E"/><circle cx="32" cy="35" r="1.6" fill="#6366F1"/><text x="56" y="31" font-family="Space Grotesk, Segoe UI, Arial, sans-serif" font-size="25" font-weight="700" fill="' . $text . '" letter-spacing="-0.5">DServer</text><text x="57" y="45" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="9.5" font-weight="700" fill="' . $sub . '" letter-spacing="2.6">TECHNOLOGY</text></svg>';
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'dserver/';
		wp_mkdir_p( $dir );
		$ids = array();
		foreach ( array( 'dserver-logo' => $svg( '#0B1020', '#4F46E5' ), 'dserver-logo-white' => $svg( '#FFFFFF', '#67E8F9' ) ) as $name => $markup ) {
			file_put_contents( $dir . $name . '.svg', $markup ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_ds_media', 'meta_value' => $name, 'fields' => 'ids', 'numberposts' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( $found ) {
				$ids[ $name ] = (int) $found[0];
				continue;
			}
			$id = wp_insert_attachment( array( 'post_mime_type' => 'image/svg+xml', 'post_title' => 'DServer logo', 'post_status' => 'inherit', 'guid' => trailingslashit( $up['baseurl'] ) . 'dserver/' . $name . '.svg' ), $dir . $name . '.svg' );
			wp_update_attachment_metadata( $id, array( 'width' => 250, 'height' => 52, 'file' => 'dserver/' . $name . '.svg', 'sizes' => array() ) );
			update_post_meta( $id, '_ds_media', $name );
			update_post_meta( $id, '_wp_attachment_image_alt', 'DServer Technology' );
			$ids[ $name ] = (int) $id;
		}
		if ( ! get_post( (int) get_theme_mod( 'custom_logo' ) ) ) {
			set_theme_mod( 'custom_logo', $ids['dserver-logo'] );
		}
		set_theme_mod( 'ds_logo_white', $ids['dserver-logo-white'] );
		$this->log[] = '✔ Logo';
	}

	private function elementor_kit(): void {
		$kit = (int) get_option( 'elementor_active_kit' );
		if ( ( ! $kit || ! get_post( $kit ) ) && class_exists( '\Elementor\Plugin' ) ) {
			$kit = (int) \Elementor\Plugin::$instance->kits_manager->create_default();
			update_option( 'elementor_active_kit', $kit );
		}
		if ( ! $kit ) {
			return;
		}
		$s = (array) get_post_meta( $kit, '_elementor_page_settings', true );
		$s['system_colors'] = array(
			array( '_id' => 'primary', 'title' => 'Primary', 'color' => '#4F46E5' ),
			array( '_id' => 'secondary', 'title' => 'Secondary', 'color' => '#0B1020' ),
			array( '_id' => 'text', 'title' => 'Text', 'color' => '#475569' ),
			array( '_id' => 'accent', 'title' => 'Accent', 'color' => '#06B6D4' ),
		);
		$s['custom_colors'] = array(
			array( '_id' => 'ds_light', 'title' => 'Light background', 'color' => '#F5F7FF' ),
			array( '_id' => 'ds_dark', 'title' => 'Dark background', 'color' => '#070B18' ),
			array( '_id' => 'ds_success', 'title' => 'Success', 'color' => '#22C55E' ),
		);
		$s['system_typography'] = array(
			array( '_id' => 'primary', 'title' => 'Primary', 'typography_typography' => 'custom', 'typography_font_family' => 'Space Grotesk', 'typography_font_weight' => '700' ),
			array( '_id' => 'secondary', 'title' => 'Secondary', 'typography_typography' => 'custom', 'typography_font_family' => 'Space Grotesk', 'typography_font_weight' => '600' ),
			array( '_id' => 'text', 'title' => 'Text', 'typography_typography' => 'custom', 'typography_font_family' => 'Inter', 'typography_font_weight' => '400' ),
			array( '_id' => 'accent', 'title' => 'Accent', 'typography_typography' => 'custom', 'typography_font_family' => 'Inter', 'typography_font_weight' => '600' ),
		);
		$s['container_width'] = array( 'unit' => 'px', 'size' => 1200 );
		update_post_meta( $kit, '_elementor_page_settings', $s );
		$this->log[] = '✔ Elementor global colours, fonts and content width';
	}

	/* ---------------------------------------------------------------- Elementor builders */

	private function id(): string {
		return substr( md5( wp_generate_uuid4() ), 0, 7 );
	}

	/** Widget. */
	private function w( string $type, array $settings, string $class = '' ): array {
		if ( $class ) {
			$settings['_css_classes'] = $class;
		}
		foreach ( array( 'link' ) as $k ) {
			if ( isset( $settings[ $k ] ) && is_string( $settings[ $k ] ) ) {
				$settings[ $k ] = array( 'url' => $settings[ $k ], 'is_external' => '', 'nofollow' => '' );
			}
		}
		return array( 'id' => $this->id(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}

	/** Column. */
	private function col( int $size, array $widgets, string $class = '' ): array {
		$settings = array( '_column_size' => $size, '_inline_size' => null );
		if ( $class ) {
			$settings['css_classes'] = $class;
		}
		return array( 'id' => $this->id(), 'elType' => 'column', 'settings' => $settings, 'elements' => $widgets );
	}

	/** Section. */
	private function sec( array $cols, string $class = 'ds-sec', array $extra = array() ): array {
		$settings = array_merge( array( 'layout' => 'boxed', 'content_width' => array( 'unit' => 'px', 'size' => 1200 ), 'gap' => 'extended', 'css_classes' => $class ), $extra );
		return array( 'id' => $this->id(), 'elType' => 'section', 'settings' => $settings, 'elements' => $cols );
	}

	private function eyebrow( string $t ): array {
		return $this->w( 'heading', array( 'title' => $t, 'header_size' => 'p' ), 'ds-eyebrow' );
	}

	private function h( string $t, string $tag = 'h2', string $class = 'ds-title' ): array {
		return $this->w( 'heading', array( 'title' => $t, 'header_size' => $tag ), $class );
	}

	private function text( string $html, string $class = 'ds-lead' ): array {
		return $this->w( 'text-editor', array( 'editor' => $html ), $class );
	}

	private function btn( string $t, string $url, string $class = 'ds-btn' ): array {
		return $this->w( 'button', array( 'text' => $t, 'link' => $url, 'size' => 'md' ), $class );
	}

	private function icon( string $fa ): array {
		return array( 'value' => 'fas fa-' . $fa, 'library' => 'fa-solid' );
	}

	private function iconbox( string $fa, string $title, string $text, string $url = '' ): array {
		$s = array( 'selected_icon' => $this->icon( $fa ), 'title_text' => $title, 'description_text' => $text, 'title_size' => 'h3' );
		if ( $url ) {
			$s['link'] = array( 'url' => $url, 'is_external' => '', 'nofollow' => '' );
		}
		return $this->w( 'icon-box', $s, 'ds-iconbox' );
	}

	private function checklist( array $items, string $class = 'ds-checklist' ): array {
		$list = array();
		foreach ( $items as $t ) {
			$list[] = array( '_id' => $this->id(), 'text' => $t, 'selected_icon' => $this->icon( 'check' ) );
		}
		return $this->w( 'icon-list', array( 'icon_list' => $list, 'view' => 'traditional' ), $class );
	}

	/** Section heading block (eyebrow + title + lead) in one full-width column. */
	private function heading_sec( string $eyebrow, string $title, string $lead, string $class = 'ds-sec ds-sec--head' ): array {
		$w = array( $this->eyebrow( $eyebrow ), $this->h( $title ) );
		if ( $lead ) {
			$w[] = $this->text( '<p>' . $lead . '</p>' );
		}
		return $this->sec( array( $this->col( 100, $w, 'ds-center' ) ), $class );
	}

	/** Page hero (inner pages). */
	private function page_hero( string $eyebrow, string $title, string $lead, string $image ): array {
		return $this->sec(
			array( $this->col( 100, array( $this->eyebrow( $eyebrow ), $this->h( $title, 'h1', 'ds-hero-title' ), $this->text( '<p>' . $lead . '</p>', 'ds-hero-lead' ) ) ) ),
			'ds-sec ds-pagehero ds-dark',
			array(
				'background_background'          => 'classic',
				'background_image'               => $this->img( $image ),
				'background_position'            => 'center center',
				'background_size'                => 'cover',
				'background_overlay_background'  => 'classic',
				'background_overlay_color'       => '#070B18',
				'background_overlay_opacity'     => array( 'unit' => 'px', 'size' => 0.82 ),
			)
		);
	}

	/** CTA band. */
	private function cta( string $title = 'Launch your server in 60 seconds', string $text = 'Pick a plan, choose a location and operating system, and you are online. Need something custom? Our engineers will size it with you.' ): array {
		return $this->sec(
			array(
				$this->col( 66, array( $this->h( $title, 'h2', 'ds-title ds-title--light' ), $this->text( '<p>' . $text . '</p>', 'ds-lead ds-lead--light' ) ) ),
				$this->col( 33, array( $this->btn( 'View VPS plans', home_url( '/vps-hosting/' ), 'ds-btn ds-btn--light' ), $this->btn( 'Talk to sales', home_url( '/contact/' ), 'ds-btn ds-btn--ghost-light' ) ), 'ds-cta-actions' ),
			),
			'ds-sec ds-cta ds-dark'
		);
	}

	/* ---------------------------------------------------------------- shared content */

	private function vps_plans(): array {
		return array(
			array( 'vps-s', 'VPS S', 'Personal projects, blogs and test environments.', '6.49', array( '2 vCPU (AMD EPYC)', '4 GB DDR5 RAM', '80 GB NVMe SSD', '20 TB traffic · 1 Gbit/s', '1 IPv4 + /64 IPv6', 'DDoS protection', 'Snapshots' ), '' ),
			array( 'vps-m', 'VPS M', 'Business websites, WooCommerce stores and small apps.', '11.99', array( '4 vCPU (AMD EPYC)', '8 GB DDR5 RAM', '160 GB NVMe SSD', '20 TB traffic · 1 Gbit/s', '1 IPv4 + /64 IPv6', 'DDoS protection', 'Snapshots + weekly backup' ), 'Most popular' ),
			array( 'vps-l', 'VPS L', 'Busy stores, SaaS applications and databases.', '22.99', array( '8 vCPU (AMD EPYC)', '16 GB DDR5 RAM', '320 GB NVMe SSD', '30 TB traffic · 2 Gbit/s', '1 IPv4 + /64 IPv6', 'DDoS protection', 'Daily off-site backup' ), '' ),
			array( 'vps-xl', 'VPS XL', 'High-traffic platforms and container clusters.', '44.99', array( '16 vCPU (AMD EPYC)', '32 GB DDR5 RAM', '640 GB NVMe SSD', '40 TB traffic · 2 Gbit/s', '2 IPv4 + /64 IPv6', 'Advanced DDoS protection', 'Daily off-site backup' ), 'Best value per core' ),
		);
	}

	private function price_cards( array $plans, string $order_label = 'Order' ): array {
		$size = 4 === count( $plans ) ? 25 : 33;
		$cols = array();
		foreach ( $plans as [ $slug, $name, $desc, $price, $features, $badge ] ) {
			$w = array();
			if ( $badge ) {
				$w[] = $this->w( 'heading', array( 'title' => $badge, 'header_size' => 'span' ), 'ds-badge' );
			}
			$w[] = $this->h( $name, 'h3', 'ds-plan' );
			$w[] = $this->text( '<p>' . $desc . '</p>', 'ds-plan-desc' );
			$w[] = $this->w( 'heading', array( 'title' => '€' . $price . '<small>/month</small>', 'header_size' => 'p' ), 'ds-price' );
			$w[] = $this->checklist( $features, 'ds-checklist ds-specs' );
			$w[] = $this->btn( $order_label . ' ' . $name, home_url( '/contact/?plan=' . $slug ), $badge === 'Most popular' ? 'ds-btn ds-btn--block' : 'ds-btn ds-btn--outline ds-btn--block' );
			$cols[] = $this->col( $size, $w, 'ds-card ds-price-card' . ( 'Most popular' === $badge ? ' is-featured' : '' ) );
		}
		return $cols;
	}

	private function faq( array $qa ): array {
		$html = '<div class="ds-faq">';
		foreach ( $qa as $i => [ $q, $a ] ) {
			$html .= '<details' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $q ) . '</summary><p>' . esc_html( $a ) . '</p></details>';
		}
		return $this->text( $html . '</div>', 'ds-faq-wrap' );
	}

	private function vps_faq(): array {
		return array(
			array( 'How fast is a VPS deployed?', 'Usually in about 60 seconds after payment. You receive the IP address and root password by email and in your customer panel.' ),
			array( 'Which operating systems can I install?', 'Ubuntu, Debian, AlmaLinux, Rocky Linux, Fedora and Windows Server (licence extra), or upload your own ISO.' ),
			array( 'Can I upgrade later?', 'Yes. Move to a bigger plan at any time with one click and a reboot — your data and IP address stay the same.' ),
			array( 'Is there a contract or setup fee?', 'No. VPS plans are billed monthly with no setup fee. Cancel at any time.' ),
			array( 'Where are your servers?', 'In Tier III data centers in Frankfurt (Germany), Amsterdam (Netherlands) and London (United Kingdom).' ),
			array( 'Do you offer managed servers?', 'Yes. Add Managed Server to any plan and our engineers handle updates, monitoring, security hardening and backups.' ),
		);
	}

	private function terminal(): array {
		$html = '<div class="ds-terminal" aria-hidden="true"><div class="ds-terminal__bar"><span></span><span></span><span></span><em>dserver-cli</em></div><pre>'
			. '<span class="c">$</span> dserver deploy --plan vps-m --region fra1 --os ubuntu-24.04' . "\n"
			. '<span class="m">→ Allocating 4 vCPU · 8 GB · 160 GB NVMe…</span>' . "\n"
			. '<span class="m">→ Installing Ubuntu 24.04 LTS…</span>' . "\n"
			. '<span class="m">→ Enabling DDoS protection & snapshots…</span>' . "\n"
			. '<span class="ok">✔ Server online in 58 s</span>' . "\n"
			. '  IPv4  <b>185.0.2.47</b>' . "\n"
			. '  SSH   ssh root@185.0.2.47<span class="cursor"></span></pre></div>';
		return $this->w( 'html', array( 'html' => $html ), 'ds-terminal-wrap' );
	}

	/* ---------------------------------------------------------------- pages */

	private function build_pages(): void {
		$c = home_url( '/contact/' );

		// Home.
		$services = array(
			array( 'server', 'VPS Hosting', 'NVMe virtual servers with dedicated resources, deployed in about 60 seconds.', home_url( '/vps-hosting/' ) ),
			array( 'hdd', 'Dedicated Servers', 'Single-tenant AMD Ryzen and EPYC servers with NVMe RAID and up to 10 Gbit/s.', home_url( '/dedicated-servers/' ) ),
			array( 'cloud', 'Cloud & Kubernetes', 'Private networks, load balancers and managed Kubernetes on our European cloud.', home_url( '/services/#cloud' ) ),
			array( 'tools', 'Managed Servers', 'Updates, monitoring, hardening and backups handled by our engineers 24/7.', home_url( '/services/#managed' ) ),
			array( 'building', 'Colocation', 'Host your own hardware in Tier III facilities with redundant power and cooling.', home_url( '/services/#colocation' ) ),
			array( 'shield-alt', 'Backup & DDoS Protection', 'Immutable off-site backups and always-on network filtering for every server.', home_url( '/services/#backup' ) ),
		);
		$svc_cols = array();
		foreach ( $services as [ $fa, $t, $d, $u ] ) {
			$svc_cols[] = $this->col( 33, array( $this->iconbox( $fa, $t, $d, $u ) ), 'ds-card ds-card--hover' );
		}
		$home = array(
			$this->sec(
				array(
					$this->col(
						60,
						array(
							$this->eyebrow( 'NVMe VPS · Dedicated servers · Cloud' ),
							$this->h( 'European servers built for <span class="ds-grad">speed and uptime</span>', 'h1', 'ds-hero-title' ),
							$this->text( '<p>Deploy NVMe VPS in about a minute, scale to dedicated hardware when you need it, and get help from engineers who answer 24/7. Data centers in Frankfurt, Amsterdam and London.</p>', 'ds-hero-lead' ),
							$this->btn( 'View VPS plans', home_url( '/vps-hosting/' ), 'ds-btn ds-btn--inline' ),
							$this->btn( 'Talk to sales', $c, 'ds-btn ds-btn--ghost-light ds-btn--inline' ),
							$this->checklist( array( 'From €6.49/month', 'No setup fee', 'Cancel any time' ), 'ds-checklist ds-checklist--inline' ),
						)
					),
					$this->col( 40, array( $this->terminal() ) ),
				),
				'ds-sec ds-hero ds-dark',
				array(
					'background_background'         => 'video',
					'background_video_link'         => isset( $this->media['network-cables-blue-loop'] ) ? (string) wp_get_attachment_url( $this->media['network-cables-blue-loop'] ) : '',
					'background_video_fallback'     => $this->img( 'network-cables-blue-poster' ),
					'background_play_on_mobile'     => '',
					'background_overlay_background' => 'classic',
					'background_overlay_color'      => '#070B18',
					'background_overlay_opacity'    => array( 'unit' => 'px', 'size' => 0.8 ),
					'height'                        => 'min-height',
					'custom_height'                 => array( 'unit' => 'vh', 'size' => 86 ),
					'content_position'              => 'middle',
				)
			),
			$this->sec(
				array(
					$this->col( 25, array( $this->w( 'counter', array( 'starting_number' => 0, 'ending_number' => 4000, 'suffix' => '+', 'thousand_separator' => 'yes', 'title' => 'Servers online' ) ) ), 'ds-stat' ),
					$this->col( 25, array( $this->w( 'counter', array( 'starting_number' => 0, 'ending_number' => 60, 'suffix' => ' s', 'title' => 'Average VPS deployment' ) ) ), 'ds-stat' ),
					$this->col( 25, array( $this->w( 'counter', array( 'starting_number' => 0, 'ending_number' => 3, 'title' => 'European data centers' ) ) ), 'ds-stat' ),
					$this->col( 25, array( $this->w( 'counter', array( 'starting_number' => 0, 'ending_number' => 24, 'suffix' => '/7', 'title' => 'Engineer support' ) ) ), 'ds-stat' ),
				),
				'ds-sec ds-stats'
			),
			$this->heading_sec( 'What we do', 'Infrastructure for every <span class="ds-grad">stage of growth</span>', 'From your first VPS to racks of dedicated hardware — one provider, one support team, one bill.' ),
			$this->sec( $svc_cols, 'ds-sec ds-sec--tight ds-grid' ),
			$this->heading_sec( 'VPS hosting', 'NVMe VPS from <span class="ds-grad">€6.49/month</span>', 'AMD EPYC processors, DDR5 memory and enterprise NVMe storage on every plan. Prices exclude VAT.', 'ds-sec ds-sec--head ds-sec--alt' ),
			$this->sec( $this->price_cards( $this->vps_plans() ), 'ds-sec ds-sec--tight ds-sec--alt ds-pricing' ),
			$this->sec(
				array(
					$this->col( 50, array( $this->w( 'image', array( 'image' => $this->img( 'server-rack-green-status-lights' ), 'image_size' => 'large' ), 'ds-photo' ) ) ),
					$this->col(
						50,
						array(
							$this->eyebrow( 'Why DServer' ),
							$this->h( 'Fast hardware, <span class="ds-grad">honest pricing</span>, real engineers' ),
							$this->text( '<p>We run our own hardware in carrier-neutral European data centers, so we can offer predictable performance at fair prices — without bandwidth surprises or long contracts.</p>' ),
							$this->checklist( array( 'Latest AMD EPYC CPUs and enterprise NVMe', 'DDoS protection included on every server', 'Tickets answered by system administrators, 24/7', 'GDPR-compliant: your data stays in the EU or UK', 'Upgrade in place — no migrations, no downtime surprises' ) ),
							$this->btn( 'About DServer', home_url( '/about/' ), 'ds-btn ds-btn--outline ds-btn--inline' ),
						),
						'ds-vcenter'
					),
				),
				'ds-sec ds-split'
			),
			$this->heading_sec( 'Locations', 'Close to your users <span class="ds-grad">across Europe</span>', 'Three Tier III facilities connected to the largest internet exchanges.', 'ds-sec ds-sec--head ds-sec--alt' ),
			$this->sec(
				array(
					$this->col( 33, array( $this->w( 'image-box', array( 'image' => $this->img( 'data-center-server-aisle' ), 'title_text' => 'Frankfurt, Germany', 'description_text' => 'DE-CIX connected · ideal for Central Europe and the DACH region.', 'title_size' => 'h3' ), 'ds-location' ) ), 'ds-card ds-card--flush' ),
					$this->col( 33, array( $this->w( 'image-box', array( 'image' => $this->img( 'dark-data-center-corridor' ), 'title_text' => 'Amsterdam, Netherlands', 'description_text' => 'AMS-IX connected · low latency to the Benelux and Nordics.', 'title_size' => 'h3' ), 'ds-location' ) ), 'ds-card ds-card--flush' ),
					$this->col( 33, array( $this->w( 'image-box', array( 'image' => $this->img( 'engineer-laptop-data-center-corridor' ), 'title_text' => 'London, United Kingdom', 'description_text' => 'LINX connected · UK data residency for regulated workloads.', 'title_size' => 'h3' ), 'ds-location' ) ), 'ds-card ds-card--flush' ),
				),
				'ds-sec ds-sec--tight ds-sec--alt ds-grid'
			),
			$this->heading_sec( 'Customers', 'What our customers <span class="ds-grad">say</span>', 'Sample reviews — replace them with real customer feedback before launch.' ),
			$this->sec(
				array(
					$this->col( 33, array( $this->w( 'testimonial', array( 'testimonial_content' => 'We moved our WooCommerce store to a VPS M and page loads dropped by half. Support answered our migration questions at 2 a.m.', 'testimonial_name' => 'Sample customer', 'testimonial_job' => 'E-commerce company', 'testimonial_alignment' => 'left' ) ) ), 'ds-card ds-quote' ),
					$this->col( 33, array( $this->w( 'testimonial', array( 'testimonial_content' => 'Predictable pricing, real engineers and snapshots before every deploy. Exactly what a small SaaS team needs.', 'testimonial_name' => 'Sample customer', 'testimonial_job' => 'SaaS start-up', 'testimonial_alignment' => 'left' ) ) ), 'ds-card ds-quote' ),
					$this->col( 33, array( $this->w( 'testimonial', array( 'testimonial_content' => 'Our dedicated EPYC servers in Frankfurt handle our analytics workloads with room to spare.', 'testimonial_name' => 'Sample customer', 'testimonial_job' => 'Data analytics firm', 'testimonial_alignment' => 'left' ) ) ), 'ds-card ds-quote' ),
				),
				'ds-sec ds-sec--tight ds-grid'
			),
			$this->heading_sec( 'FAQ', 'Questions, <span class="ds-grad">answered</span>', '', 'ds-sec ds-sec--head ds-sec--alt' ),
			$this->sec( array( $this->col( 100, array( $this->faq( array_slice( $this->vps_faq(), 0, 5 ) ) ) ) ), 'ds-sec ds-sec--tight ds-sec--alt ds-narrow' ),
			$this->cta(),
		);
		$home_id = $this->page( 'home', 'Home', $home );

		// VPS Hosting.
		$compare = '<div class="ds-table-wrap"><table class="ds-table"><thead><tr><th>Specification</th><th>VPS S</th><th>VPS M</th><th>VPS L</th><th>VPS XL</th></tr></thead><tbody>'
			. '<tr><td>vCPU (AMD EPYC)</td><td>2</td><td>4</td><td>8</td><td>16</td></tr>'
			. '<tr><td>RAM (DDR5)</td><td>4 GB</td><td>8 GB</td><td>16 GB</td><td>32 GB</td></tr>'
			. '<tr><td>NVMe storage</td><td>80 GB</td><td>160 GB</td><td>320 GB</td><td>640 GB</td></tr>'
			. '<tr><td>Traffic included</td><td>20 TB</td><td>20 TB</td><td>30 TB</td><td>40 TB</td></tr>'
			. '<tr><td>Port speed</td><td>1 Gbit/s</td><td>1 Gbit/s</td><td>2 Gbit/s</td><td>2 Gbit/s</td></tr>'
			. '<tr><td>IPv4 / IPv6</td><td>1 / /64</td><td>1 / /64</td><td>1 / /64</td><td>2 / /64</td></tr>'
			. '<tr><td>Backups</td><td>Snapshots</td><td>Weekly</td><td>Daily off-site</td><td>Daily off-site</td></tr>'
			. '<tr><td>DDoS protection</td><td>Standard</td><td>Standard</td><td>Standard</td><td>Advanced</td></tr>'
			. '<tr class="ds-table__price"><td>Price / month</td><td>€6.49</td><td>€11.99</td><td>€22.99</td><td>€44.99</td></tr>'
			. '</tbody></table></div><p class="ds-note">Prices in EUR per month, excluding VAT. Billed monthly, no setup fee, no minimum term.</p>';
		$this->page(
			'vps-hosting',
			'VPS Hosting',
			array(
				$this->page_hero( 'VPS hosting', 'NVMe VPS from <span class="ds-grad">€6.49/month</span>', 'Dedicated vCPU and RAM, enterprise NVMe storage and DDoS protection — online in about 60 seconds in Frankfurt, Amsterdam or London.', 'server-rack-network-cables-hosting' ),
				$this->sec( $this->price_cards( $this->vps_plans(), 'Deploy' ), 'ds-sec ds-pricing ds-pricing--overlap' ),
				$this->heading_sec( 'Compare', 'Compare <span class="ds-grad">VPS plans</span>', '', 'ds-sec ds-sec--head ds-sec--alt' ),
				$this->sec( array( $this->col( 100, array( $this->text( $compare, 'ds-compare' ) ) ) ), 'ds-sec ds-sec--tight ds-sec--alt' ),
				$this->heading_sec( 'Included', 'Everything you need, <span class="ds-grad">no surprises</span>', 'Every VPS comes with the essentials as standard.' ),
				$this->sec(
					array(
						$this->col( 33, array( $this->iconbox( 'microchip', 'AMD EPYC & DDR5', 'Current-generation server CPUs and memory on every plan.' ) ), 'ds-card' ),
						$this->col( 33, array( $this->iconbox( 'shield-alt', 'DDoS protection', 'Always-on network filtering, included at no extra cost.' ) ), 'ds-card' ),
						$this->col( 33, array( $this->iconbox( 'terminal', 'Full root access', 'Linux or Windows — install any stack, panel or container runtime.' ) ), 'ds-card' ),
						$this->col( 33, array( $this->iconbox( 'camera', 'Snapshots', 'Take a snapshot before every change and roll back in minutes.' ) ), 'ds-card' ),
						$this->col( 33, array( $this->iconbox( 'level-up-alt', 'Upgrade in place', 'Move to a bigger plan with one click; your IP stays the same.' ) ), 'ds-card' ),
						$this->col( 33, array( $this->iconbox( 'headset', '24/7 engineers', 'Real system administrators on tickets, chat and phone.' ) ), 'ds-card' ),
					),
					'ds-sec ds-sec--tight ds-grid'
				),
				$this->sec(
					array(
						$this->col(
							50,
							array(
								$this->eyebrow( 'Add-ons' ),
								$this->h( 'Extend your <span class="ds-grad">server</span>' ),
								$this->checklist( array( 'Extra IPv4 address — €1.50/month', 'Managed server (updates, monitoring, hardening) — €19/month', 'cPanel or Plesk licence — from €12/month', 'Additional 100 GB NVMe volume — €4.50/month', 'Daily off-site backup for VPS S — €1.90/month' ) ),
							),
							'ds-vcenter'
						),
						$this->col( 50, array( $this->terminal() ) ),
					),
					'ds-sec ds-split ds-sec--alt'
				),
				$this->heading_sec( 'FAQ', 'VPS <span class="ds-grad">questions</span>', '' ),
				$this->sec( array( $this->col( 100, array( $this->faq( $this->vps_faq() ) ) ) ), 'ds-sec ds-sec--tight ds-narrow' ),
				$this->cta(),
			)
		);

		// Dedicated servers.
		$dedicated = array(
			array( 'ds-ryzen7', 'DS Ryzen 7', 'Web clusters, game servers and CI runners.', '59', array( 'AMD Ryzen 7 · 8 cores / 16 threads', '64 GB DDR5 ECC', '2 × 1 TB NVMe (RAID 1)', '1 Gbit/s · unmetered', '/29 IPv4 + /64 IPv6', 'IPMI remote console', 'Setup within 24 h' ), '' ),
			array( 'ds-ryzen9', 'DS Ryzen 9', 'Databases, virtualisation and high-traffic stores.', '109', array( 'AMD Ryzen 9 · 16 cores / 32 threads', '128 GB DDR5 ECC', '2 × 2 TB NVMe (RAID 1)', '1 Gbit/s · unmetered', '/29 IPv4 + /64 IPv6', 'IPMI remote console', 'Setup within 24 h' ), 'Most popular' ),
			array( 'ds-epyc', 'DS EPYC', 'Enterprise workloads, Kubernetes and AI inference.', '239', array( 'AMD EPYC · 32 cores / 64 threads', '256 GB DDR5 ECC', '2 × 3.84 TB NVMe (RAID 1)', '10 Gbit/s port · 50 TB traffic', '/28 IPv4 + /64 IPv6', 'IPMI remote console', 'Setup within 48 h' ), '' ),
		);
		$this->page(
			'dedicated-servers',
			'Dedicated Servers',
			array(
				$this->page_hero( 'Dedicated servers', 'Bare-metal power, <span class="ds-grad">all yours</span>', 'Single-tenant AMD servers with NVMe RAID, ECC memory and remote console access. Monthly billing, no long contracts.', 'enterprise-server-drive-bays' ),
				$this->sec( $this->price_cards( $dedicated ), 'ds-sec ds-pricing ds-pricing--overlap' ),
				$this->sec( array( $this->col( 100, array( $this->text( '<p class="ds-note">Prices in EUR per month, excluding VAT. Custom configurations — more RAM, GPUs, 25 Gbit/s networking, hardware RAID — on request.</p>' ) ), 'ds-center' ) ), 'ds-sec ds-sec--flush' ),
				$this->heading_sec( 'Included', 'Built for <span class="ds-grad">serious workloads</span>', '' , 'ds-sec ds-sec--head ds-sec--alt' ),
				$this->sec(
					array(
						$this->col( 33, array( $this->iconbox( 'tachometer-alt', 'No noisy neighbours', 'Every core, every IOPS and every gigabit is yours alone.' ) ), 'ds-card' ),
						$this->col( 33, array( $this->iconbox( 'desktop', 'Remote console', 'IPMI / KVM access, rescue system and OS reinstall from your panel.' ) ), 'ds-card' ),
						$this->col( 33, array( $this->iconbox( 'tools', '4-hour hardware swap', 'Failed components are replaced by on-site engineers, 24/7.' ) ), 'ds-card' ),
					),
					'ds-sec ds-sec--tight ds-sec--alt ds-grid'
				),
				$this->cta( 'Need a custom server?', 'Tell us about your workload and our engineers will propose the right CPU, memory, storage and network — usually within one business day.' ),
			)
		);

		// Services.
		$svc_sections = array(
			array( 'cloud', 'Cloud & Kubernetes', 'cloud-computing-infrastructure-3d', 'Run scalable applications on our European cloud: private networks, floating IPs, load balancers, block storage and managed Kubernetes — billed by the hour, capped at a monthly price.', array( 'Managed Kubernetes control plane', 'Private networks and floating IPs', 'Load balancers with free TLS', 'Block storage volumes up to 10 TB', 'Terraform and API access' ) ),
			array( 'managed', 'Managed Servers', 'it-engineer-working-server-rack', 'Let our engineers run your servers. We handle operating system updates, monitoring, security hardening, backups and performance tuning, and react to alerts before your users notice.', array( '24/7 monitoring and alert response', 'OS and security updates', 'Firewall and SSH hardening', 'Backup verification and restore tests', 'Monthly health report' ) ),
			array( 'colocation', 'Colocation', 'data-center-server-aisle', 'Host your own hardware in our Tier III facilities with A+B redundant power, N+1 cooling and carrier-neutral connectivity. Remote hands are available around the clock.', array( '1U, quarter, half and full racks', 'A+B redundant power feeds', 'Carrier-neutral cross-connects', 'Remote hands 24/7', 'Biometric access and CCTV' ) ),
			array( 'backup', 'Backup & DDoS Protection', 'hard-disk-drive-backup-storage', 'Protect your data and your uptime. Immutable, encrypted off-site backups with one-click restore, plus always-on DDoS filtering for every IP address we route.', array( 'Daily encrypted off-site backups', 'Immutable retention (ransomware-safe)', 'One-click file and full-server restore', 'Always-on volumetric DDoS filtering', 'Advanced layer-7 protection on request' ) ),
			array( 'web', 'Web & WordPress Hosting', 'online-store-laptop-shopping', 'Fast, secure hosting for websites and WooCommerce stores on NVMe servers with LiteSpeed, free SSL, daily backups and staging — managed for you.', array( 'LiteSpeed web server and caching', 'Free SSL certificates', 'Staging sites and one-click restore', 'Malware scanning', 'Free website migration' ) ),
		);
		$blocks = array( $this->page_hero( 'Services', 'Everything you need to <span class="ds-grad">run online</span>', 'VPS, dedicated servers, cloud, colocation and managed services — from one European provider with one support team.', 'server-racks-colorful-cabling' ) );
		$blocks[] = $this->sec( $svc_cols, 'ds-sec ds-grid' );
		foreach ( $svc_sections as $i => [ $anchor, $title, $image, $text, $points ] ) {
			$img_col  = $this->col( 50, array( $this->w( 'image', array( 'image' => $this->img( $image ), 'image_size' => 'large' ), 'ds-photo' ) ) );
			$text_col = $this->col( 50, array( $this->eyebrow( 'Service' ), $this->h( $title ), $this->text( '<p>' . $text . '</p>' ), $this->checklist( $points ), $this->btn( 'Ask about ' . strtolower( $title ), $c, 'ds-btn ds-btn--outline ds-btn--inline' ) ), 'ds-vcenter' );
			$blocks[] = $this->sec( 1 === $i % 2 ? array( $text_col, $img_col ) : array( $img_col, $text_col ), 'ds-sec ds-split' . ( 0 === $i % 2 ? ' ds-sec--alt' : '' ), array( '_element_id' => $anchor ) );
		}
		$blocks[] = $this->cta();
		$this->page( 'services', 'Services', $blocks );

		// Solutions.
		$solutions = array(
			array( 'software-development-code-screen', 'SaaS & start-ups', 'Start on a €6.49 VPS, grow into Kubernetes and dedicated databases without changing provider.' ),
			array( 'online-store-laptop-shopping', 'E-commerce', 'Fast WooCommerce and Magento hosting with staging, backups and DDoS protection for peak sales.' ),
			array( 'technology-team-meeting-laptops', 'Agencies & developers', 'Reseller-friendly plans, API access and a single invoice for all your client servers.' ),
			array( 'bank-building-financial-district', 'Finance & fintech', 'UK and EU data residency, encrypted backups and private networking for regulated workloads.' ),
			array( 'healthcare-digital-imaging-tablet', 'Healthcare', 'GDPR-compliant infrastructure with access logging and isolated environments for patient data.' ),
			array( 'security-operations-center-screens', 'Gaming & streaming', 'High-clock Ryzen servers and low-latency European routes for game servers and media.' ),
		);
		$sol_cols = array();
		foreach ( $solutions as [ $img, $t, $d ] ) {
			$sol_cols[] = $this->col( 33, array( $this->w( 'image-box', array( 'image' => $this->img( $img ), 'title_text' => $t, 'description_text' => $d, 'title_size' => 'h3', 'link' => array( 'url' => $c, 'is_external' => '', 'nofollow' => '' ) ), 'ds-location' ) ), 'ds-card ds-card--flush ds-card--hover' );
		}
		$this->page(
			'solutions',
			'Solutions',
			array(
				$this->page_hero( 'Solutions', 'Infrastructure shaped around <span class="ds-grad">your industry</span>', 'Proven server and cloud designs for the businesses we host every day.', 'fiber-optic-strands-light' ),
				$this->sec( $sol_cols, 'ds-sec ds-grid' ),
				$this->cta( 'Not sure which setup you need?', 'Tell us what you run today and where you want to be — our engineers will design it with you, free of charge.' ),
			)
		);

		// About.
		$this->page(
			'about',
			'About',
			array(
				$this->page_hero( 'About us', 'Engineers who <span class="ds-grad">run servers</span>, not just sell them', 'DServer Technology was founded by system administrators who wanted hosting that is fast, fairly priced and supported by people who understand it.', 'bright-open-plan-office' ),
				$this->sec(
					array(
						$this->col( 50, array( $this->w( 'image', array( 'image' => $this->img( 'technology-team-meeting-laptops' ), 'image_size' => 'large' ), 'ds-photo' ) ) ),
						$this->col( 50, array( $this->eyebrow( 'Our story' ), $this->h( 'Built on <span class="ds-grad">our own hardware</span>' ), $this->text( '<p>We own and operate our servers in three European data centers. That lets us choose the hardware, tune the network and keep prices predictable — and it means the people answering your ticket can actually fix the problem.</p><p>Today DServer hosts websites, online stores, SaaS platforms and enterprise workloads for customers across Europe and beyond.</p>' ) ), 'ds-vcenter' ),
					),
					'ds-sec ds-split'
				),
				$this->heading_sec( 'Values', 'What we <span class="ds-grad">stand for</span>', '', 'ds-sec ds-sec--head ds-sec--alt' ),
				$this->sec(
					array(
						$this->col( 25, array( $this->iconbox( 'bolt', 'Performance', 'Current hardware, NVMe everywhere and well-peered networks.' ) ), 'ds-card' ),
						$this->col( 25, array( $this->iconbox( 'balance-scale', 'Fair pricing', 'Clear monthly prices, no hidden fees, no lock-in.' ) ), 'ds-card' ),
						$this->col( 25, array( $this->iconbox( 'user-shield', 'Privacy', 'European data centers and GDPR-compliant processing.' ) ), 'ds-card' ),
						$this->col( 25, array( $this->iconbox( 'hands-helping', 'Real support', 'Engineers, not scripts — 24 hours a day.' ) ), 'ds-card' ),
					),
					'ds-sec ds-sec--tight ds-sec--alt ds-grid'
				),
				$this->cta( 'Let’s build something fast', 'Questions about a project, a migration or a custom server? Our team is happy to help.' ),
			)
		);

		// Contact.
		$this->page(
			'contact',
			'Contact',
			array(
				$this->page_hero( 'Contact', 'Talk to a <span class="ds-grad">server engineer</span>', 'Sales questions, custom configurations or migrations — we usually reply within one business hour.', 'support-headset-white-background' ),
				$this->sec(
					array(
						$this->col( 33, array( $this->iconbox( 'phone', 'Call us', (string) dserver_opt( 'phone' ) . ' — sales Mon–Fri 9:00–18:00, support 24/7' ) ), 'ds-card' ),
						$this->col( 33, array( $this->iconbox( 'envelope', 'Email', (string) dserver_opt( 'email' ) . ' — replies within one business hour' ) ), 'ds-card' ),
						$this->col( 33, array( $this->iconbox( 'map-marker-alt', 'Office', str_replace( "\n", ', ', (string) dserver_opt( 'address' ) ) ) ), 'ds-card' ),
					),
					'ds-sec ds-grid'
				),
				$this->sec(
					array(
						$this->col( 50, array( $this->eyebrow( 'Send a message' ), $this->h( 'Request a <span class="ds-grad">quote</span>' ), $this->w( 'shortcode', array( 'shortcode' => '[dserver_contact]' ) ) ) ),
						$this->col( 50, array( $this->w( 'google_maps', array( 'address' => 'Shoreditch, London', 'zoom' => array( 'unit' => 'px', 'size' => 13 ), 'height' => array( 'unit' => 'px', 'size' => 520 ) ), 'ds-map' ) ) ),
					),
					'ds-sec ds-sec--alt ds-split'
				),
			)
		);

		// Blog page, legal pages, media credits (classic content — edit in the block editor or Elementor).
		$blog_id = $this->plain_page( 'blog', 'Blog', '' );
		$this->plain_page( 'privacy-policy', 'Privacy Policy', '<p><strong>Template — have this reviewed by your legal advisor.</strong></p><h2>Who we are</h2><p>DServer Technology provides hosting and server services. This policy explains how we process personal data.</p><h2>What we collect</h2><p>Account and billing details, contact form submissions, support tickets, and technical logs required to operate and secure our services.</p><h2>How we use it</h2><p>To provide and bill our services, answer enquiries, keep our network secure and meet legal obligations. We never sell personal data.</p><h2>Where it is stored</h2><p>In our data centers in the European Union and the United Kingdom.</p><h2>Your rights</h2><p>You may request access, correction, export or deletion of your data at any time by emailing us.</p>' );
		$this->plain_page( 'terms', 'Terms of Service', '<p><strong>Template — have this reviewed by your legal advisor.</strong></p><h2>Services</h2><p>Plans, prices and specifications are described on our website. Prices exclude VAT unless stated.</p><h2>Billing</h2><p>Services are billed monthly in advance and can be cancelled at the end of any billing period.</p><h2>Acceptable use</h2><p>Spam, malware, attacks on other networks and illegal content are not permitted and lead to suspension.</p><h2>Liability</h2><p>To the extent permitted by law, our liability is limited to the fees paid for the affected service in the previous month.</p>' );
		$this->plain_page( 'media-credits', 'Media Credits', $this->credits_html() );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
		update_option( 'page_for_posts', $blog_id );
		if ( ! empty( $this->pages['privacy-policy'] ) ) {
			update_option( 'wp_page_for_privacy_policy', $this->pages['privacy-policy'] );
		}
		$this->log[] = '✔ Pages: ' . count( $this->pages ) . ' (Home, VPS Hosting, Dedicated Servers, Services, Solutions, About, Contact, Blog, legal, Media Credits)';
	}

	/** Elementor page (never overwrites an existing page). */
	private function page( string $slug, string $title, array $elements ): int {
		$found = get_page_by_path( $slug );
		if ( $found ) {
			$this->pages[ $slug ] = $found->ID;
			return $found->ID;
		}
		$id = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => '' ) );
		update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
		update_post_meta( $id, '_ds_demo', 1 );
		$this->pages[ $slug ] = $id;
		return $id;
	}

	private function plain_page( string $slug, string $title, string $html ): int {
		$found = get_page_by_path( $slug );
		if ( $found ) {
			$this->pages[ $slug ] = $found->ID;
			return $found->ID;
		}
		$id = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $html ) );
		update_post_meta( $id, '_ds_demo', 1 );
		$this->pages[ $slug ] = $id;
		return $id;
	}

	private function credits_html(): string {
		$credits = json_decode( (string) file_get_contents( DSERVER_DIR . 'assets/media/credits.json' ), true ) ?: array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$rows    = '';
		foreach ( $credits as $file => $c ) {
			$rows .= '<tr><td>' . esc_html( $c['alt'] ?? $file ) . '</td><td><a href="' . esc_url( $c['original_url'] ?? '' ) . '">' . esc_html( $c['source'] ?? '' ) . '</a></td><td><a href="' . esc_url( $c['author_url'] ?? '' ) . '">' . esc_html( $c['author'] ?? '' ) . '</a></td><td><a href="' . esc_url( $c['license_url'] ?? '' ) . '">' . esc_html( $c['license'] ?? '' ) . '</a></td></tr>';
		}
		return '<p>Photos and video on this website are used under the Unsplash and Pexels licences, which allow free commercial use without attribution. We credit every creator anyway. The logo, icons and illustrations are original.</p><figure class="wp-block-table"><table><thead><tr><th>Media</th><th>Source</th><th>Creator</th><th>Licence</th></tr></thead><tbody>' . $rows . '</tbody></table></figure>';
	}

	/* ---------------------------------------------------------------- posts */

	private function posts(): void {
		$cat   = wp_create_category( 'Guides' );
		$posts = array(
			array( 'choosing-the-right-vps-size', 'How to choose the right VPS size', 'server-rack-network-cables-hosting', 'A practical guide to sizing CPU, RAM and storage for websites, stores and applications.', '<p>Picking a VPS is mostly about memory and storage speed. CPU matters less than people expect for typical websites.</p><h2>Websites and blogs</h2><p>A WordPress site with caching runs comfortably on <strong>2 vCPU and 4 GB RAM</strong> (our VPS S). Add more memory if you run several sites.</p><h2>Online stores</h2><p>WooCommerce benefits from more RAM for the database and object cache. Start with <strong>4 vCPU and 8 GB</strong> (VPS M) and watch memory usage during sales.</p><h2>Applications and databases</h2><p>For SaaS back-ends and databases, NVMe storage and memory dominate. <strong>8 vCPU and 16 GB</strong> (VPS L) is a solid starting point; move to dedicated servers when you need guaranteed IOPS.</p><h2>Upgrade, don’t over-buy</h2><p>Because DServer plans upgrade in place, start one size smaller and scale when monitoring tells you to.</p>' ),
			array( 'nvme-vs-ssd-hosting', 'NVMe vs SATA SSD: what it means for your website', 'enterprise-server-drive-bays', 'Why NVMe storage makes databases and dynamic sites feel faster — and when it matters most.', '<p>NVMe drives connect directly to the CPU over PCIe instead of the older SATA bus. The result is several times more throughput and far lower latency.</p><h2>Where you notice it</h2><ul><li>Database queries on busy stores</li><li>Search and filtering on large catalogues</li><li>Backups and restores</li><li>Container image pulls and builds</li></ul><h2>Where you don’t</h2><p>Fully cached static pages are served from memory, so storage speed matters less there. That is why every DServer plan combines NVMe with enough RAM to cache aggressively.</p>' ),
			array( 'protecting-servers-from-ddos', 'Protecting your server from DDoS attacks', 'security-operations-center-screens', 'What DDoS attacks are, how network filtering works and what you can do at the application level.', '<p>A distributed denial-of-service attack floods your server with traffic until legitimate visitors cannot reach it.</p><h2>Network-level protection</h2><p>Volumetric attacks are filtered before they reach your server. Every DServer IP address is protected by always-on filtering at no extra cost.</p><h2>Application-level protection</h2><ul><li>Use a web application firewall or rate limiting for login and search pages</li><li>Cache aggressively so each request costs less</li><li>Keep software updated to avoid amplification through vulnerabilities</li></ul><p>Need layer-7 protection for a high-risk site? <a href="/contact/">Talk to our team</a>.</p>' ),
		);
		$n = 0;
		foreach ( $posts as $i => [ $slug, $title, $img, $excerpt, $body ] ) {
			if ( get_page_by_path( $slug, OBJECT, 'post' ) ) {
				continue;
			}
			$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_excerpt' => $excerpt, 'post_content' => $body, 'post_category' => array( $cat ), 'post_date' => wp_date( 'Y-m-d H:i:s', strtotime( '-' . ( 3 + $i * 6 ) . ' days' ) ) ) );
			if ( ! empty( $this->media[ $img ] ) ) {
				set_post_thumbnail( $id, $this->media[ $img ] );
			}
			update_post_meta( $id, '_ds_demo', 1 );
			++$n;
		}
		$this->log[] = '✔ Blog articles: ' . $n . ' new';
	}

	/* ---------------------------------------------------------------- menus */

	private function menus(): void {
		$p    = fn( $s ) => $this->pages[ $s ] ?? 0;
		$make = function ( string $name, string $location, array $items ): void {
			if ( wp_get_nav_menu_object( $name ) ) {
				return;
			}
			$menu = wp_create_nav_menu( $name );
			$add  = function ( array $items, int $parent ) use ( &$add, $menu ) {
				foreach ( $items as $it ) {
					$args = array( 'menu-item-title' => $it[0], 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent );
					if ( is_int( $it[1] ) ) {
						$args += array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $it[1] );
					} else {
						$args += array( 'menu-item-type' => 'custom', 'menu-item-url' => $it[1] );
					}
					$id = wp_update_nav_menu_item( $menu, 0, $args );
					if ( ! empty( $it[2] ) ) {
						$add( $it[2], (int) $id );
					}
				}
			};
			$add( $items, 0 );
			$loc              = get_theme_mod( 'nav_menu_locations', array() );
			$loc[ $location ] = $menu;
			set_theme_mod( 'nav_menu_locations', $loc );
		};
		$svc = home_url( '/services/' );
		$make( 'Header menu', 'primary', array(
			array( 'VPS Hosting', $p( 'vps-hosting' ) ),
			array( 'Dedicated Servers', $p( 'dedicated-servers' ) ),
			array( 'Services', $p( 'services' ), array( array( 'Cloud & Kubernetes', $svc . '#cloud' ), array( 'Managed Servers', $svc . '#managed' ), array( 'Colocation', $svc . '#colocation' ), array( 'Backup & DDoS Protection', $svc . '#backup' ), array( 'Web & WordPress Hosting', $svc . '#web' ) ) ),
			array( 'Solutions', $p( 'solutions' ) ),
			array( 'About', $p( 'about' ) ),
			array( 'Blog', $p( 'blog' ) ),
			array( 'Contact', $p( 'contact' ) ),
		) );
		$make( 'Footer: Services', 'footer_services', array( array( 'VPS Hosting', $p( 'vps-hosting' ) ), array( 'Dedicated Servers', $p( 'dedicated-servers' ) ), array( 'Cloud & Kubernetes', $svc . '#cloud' ), array( 'Managed Servers', $svc . '#managed' ), array( 'Colocation', $svc . '#colocation' ) ) );
		$make( 'Footer: Company', 'footer_company', array( array( 'About', $p( 'about' ) ), array( 'Solutions', $p( 'solutions' ) ), array( 'Blog', $p( 'blog' ) ), array( 'Contact', $p( 'contact' ) ), array( 'Media Credits', $p( 'media-credits' ) ) ) );
		$make( 'Footer: Legal', 'footer_legal', array( array( 'Privacy Policy', $p( 'privacy-policy' ) ), array( 'Terms of Service', $p( 'terms' ) ) ) );
		$this->log[] = '✔ Menus: header, footer services, footer company, legal';
	}
}

/**
 * Admin page: Appearance → DServer Setup.
 */
function dserver_setup_menu(): void {
	add_theme_page( __( 'DServer Setup', 'dserver' ), __( 'DServer Setup', 'dserver' ), 'manage_options', 'dserver-setup', 'dserver_setup_page' );
}
add_action( 'admin_menu', 'dserver_setup_menu' );

/**
 * Render the admin page.
 */
function dserver_setup_page(): void {
	$log = array();
	if ( isset( $_POST['dserver_setup'] ) && check_admin_referer( 'dserver_setup' ) && current_user_can( 'manage_options' ) ) {
		$log = ( new DServer_Setup() )->run();
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'DServer Setup', 'dserver' ); ?></h1>
		<?php if ( $log ) : ?>
			<div class="notice notice-success"><p><?php echo wp_kses_post( implode( '<br>', array_map( 'esc_html', $log ) ) ); ?></p><p><a class="button button-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank"><?php esc_html_e( 'View site', 'dserver' ); ?></a></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Creates the DServer Technology website: Home, VPS Hosting, Dedicated Servers, Services, Solutions, About, Contact, Blog, legal pages, menus, logo and images. All pages are built with Elementor and can be edited with “Edit with Elementor”.', 'dserver' ); ?></p>
		<p><?php echo defined( 'ELEMENTOR_VERSION' ) ? esc_html__( 'Elementor is active.', 'dserver' ) : '<strong>' . esc_html__( 'Install and activate Elementor (free) first.', 'dserver' ) . '</strong>'; ?></p>
		<p><?php esc_html_e( 'Running it again only adds what is missing — pages you have edited are never overwritten.', 'dserver' ); ?></p>
		<form method="post"><?php wp_nonce_field( 'dserver_setup' ); ?><button class="button button-primary button-hero" name="dserver_setup" value="1"><?php echo get_option( 'dserver_setup_done' ) ? esc_html__( 'Run setup again', 'dserver' ) : esc_html__( 'Create the website', 'dserver' ); ?></button></form>
	</div>
	<?php
}

/**
 * Point new users to the setup page after activating the theme.
 */
function dserver_setup_notice(): void {
	if ( get_option( 'dserver_setup_done' ) || ! current_user_can( 'manage_options' ) || ( isset( $_GET['page'] ) && 'dserver-setup' === $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	printf( '<div class="notice notice-info"><p>%s <a class="button button-primary" href="%s">%s</a></p></div>', esc_html__( 'DServer theme is active. Create the website pages in one click:', 'dserver' ), esc_url( admin_url( 'themes.php?page=dserver-setup' ) ), esc_html__( 'Open DServer Setup', 'dserver' ) );
}
add_action( 'admin_notices', 'dserver_setup_notice' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'dserver setup',
		static function () {
			foreach ( ( new DServer_Setup() )->run() as $line ) {
				WP_CLI::log( $line );
			}
		}
	);
}
