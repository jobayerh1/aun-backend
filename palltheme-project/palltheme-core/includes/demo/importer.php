<?php
/**
 * One-click demo importer (Theme Settings → Import Demo, or `wp palltheme demo import`).
 *
 * Creates media, taxonomies, all content types, WooCommerce categories,
 * attributes and products (simple, variable, virtual), pages with
 * Elementor layouts, menus, widgets, Business Settings and theme options.
 * Every created object is tagged `_pall_demo` so "Remove demo content"
 * deletes exactly what was imported. Re-runs only add what is missing.
 *
 * Media:
 *  - Bundled original artwork (assets/demo): SVG covers, logos, avatars and
 *    WebP product renders — owned by the theme author, safe to redistribute.
 *  - Optional "photo pack" (wp-content/palltheme-photo-pack/manifest.json):
 *    third-party photos/videos used on a specific website. They are NOT
 *    bundled with the plugin because their licences do not allow
 *    redistribution; credits are stored on each attachment.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/data.php';

/**
 * Importer.
 */
final class Pallcore_Demo_Importer {

	/** @var string[] */
	private array $log = array();

	/** @var array<string,int> Media key => attachment ID. */
	private array $media = array();

	/** @var array<string,int> Page slug => ID. */
	private array $pages = array();

	/** @var array<string,int> Service slug => ID. */
	private array $services = array();

	/** @var array<string,int> Solution slug => ID. */
	private array $solutions = array();

	/** @var array<string,int> Case study slug => ID. */
	private array $cases = array();

	/** @var array<string,int> Post slug => ID. */
	private array $posts = array();

	/** @var array<string,int> SKU => product ID. */
	private array $products = array();

	/** @var array<string,mixed> */
	private array $data;

	/** @var bool */
	private bool $first_run;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->data      = pallcore_demo_data();
		$this->first_run = ! get_option( 'pallcore_demo_imported' );
	}

	/**
	 * Run everything.
	 *
	 * @return string[] Log lines.
	 */
	public function run(): array {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 900 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_defer_term_counting( true );

		$this->settings();
		$this->import_media();
		$this->import_photo_pack();
		$this->taxonomies();
		$this->blog();
		if ( class_exists( 'WooCommerce' ) ) {
			$this->woocommerce();
		} else {
			$this->log[] = '⚠ WooCommerce is not active — products skipped. Activate WooCommerce and run the import again to add the store.';
		}
		$this->services();
		$this->solutions();
		$this->case_studies();
		$this->team();
		$this->testimonials();
		$this->clients();
		$this->technologies();
		$this->pricing();
		$this->faqs();
		$this->jobs();
		$this->link_related();
		$this->pages();
		$this->resolve_links();
		$this->menus();
		$this->widgets();
		$this->theme_options();

		wp_defer_term_counting( false );
		update_option( 'pallcore_demo_imported', time() );
		// Clear (not rebuild) rewrite rules: post types registered earlier in this request may predate the
		// permalink change. WordPress rebuilds the rules on the next page load with everything registered.
		delete_option( 'rewrite_rules' );
		$this->log[] = '✔ Done. Visit the site to see the demo.';
		return $this->log;
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	/**
	 * Insert a post once (matched by type + slug), tag it as demo.
	 *
	 * @param array $args wp_insert_post args.
	 * @param array $meta `_pall_` meta (without prefix).
	 */
	private function post( array $args, array $meta = array() ): int {
		$args = wp_parse_args(
			$args,
			array(
				'post_status' => 'publish',
				'post_author' => get_current_user_id() ?: 1,
			)
		);
		$slug     = $args['post_name'] ?? sanitize_title( $args['post_title'] );
		$existing = get_page_by_path( $slug, OBJECT, $args['post_type'] );
		if ( $existing ) {
			return (int) $existing->ID;
		}
		$args['post_name'] = $slug;
		$id                = wp_insert_post( wp_slash( $args ), true );
		if ( is_wp_error( $id ) ) {
			$this->log[] = '✖ ' . $args['post_title'] . ': ' . $id->get_error_message();
			return 0;
		}
		update_post_meta( $id, '_pall_demo', 1 );
		foreach ( $meta as $key => $value ) {
			if ( '' !== $value && array() !== $value && null !== $value ) {
				update_post_meta( $id, '_pall_' . $key, $value );
			}
		}
		return (int) $id;
	}

	/**
	 * Get or create a term.
	 */
	private function term( string $name, string $taxonomy, int $parent = 0 ): int {
		$found = term_exists( $name, $taxonomy, $parent ?: null );
		if ( $found ) {
			return (int) ( is_array( $found ) ? $found['term_id'] : $found );
		}
		$res = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );
		if ( is_wp_error( $res ) ) {
			return 0;
		}
		update_term_meta( (int) $res['term_id'], '_pall_demo', 1 );
		return (int) $res['term_id'];
	}

	/**
	 * Best available image for a slot: photo-pack photo first, bundled art second.
	 */
	private function image( string $photo, string $fallback = '' ): int {
		if ( $photo && isset( $this->media[ 'photo:' . $photo ] ) ) {
			return $this->media[ 'photo:' . $photo ];
		}
		return $fallback && isset( $this->media[ $fallback ] ) ? $this->media[ $fallback ] : 0;
	}

	/**
	 * Set a featured image (only when none is set).
	 */
	private function thumb( int $post_id, int $attachment_id ): void {
		if ( $post_id && $attachment_id && ! has_post_thumbnail( $post_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * Sideload a local file into the Media Library (generates all image sizes).
	 *
	 * @param string $file   Absolute path.
	 * @param string $key    Unique demo media key (used to skip on re-run).
	 * @param string $title  Title.
	 * @param string $alt    Alt text.
	 * @param array  $credit Credit meta (source, url, author, author_url, license, license_url, usage).
	 */
	private function sideload( string $file, string $key, string $title, string $alt = '', array $credit = array() ): int {
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => '_pall_demo_media', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( $existing ) {
			return (int) $existing[0];
		}
		if ( ! is_readable( $file ) ) {
			return 0;
		}
		$tmp = wp_tempnam( basename( $file ) );
		copy( $file, $tmp );
		$id = media_handle_sideload(
			array(
				'name'     => basename( $file ),
				'tmp_name' => $tmp,
			),
			0,
			$title
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			$this->log[] = '✖ Media ' . basename( $file ) . ': ' . $id->get_error_message();
			return 0;
		}
		update_post_meta( $id, '_pall_demo', 1 );
		update_post_meta( $id, '_pall_demo_media', $key );
		if ( $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}
		foreach ( $credit as $k => $v ) {
			if ( '' !== (string) $v ) {
				update_post_meta( $id, '_pall_credit_' . $k, $v );
			}
		}
		return (int) $id;
	}

	/* ------------------------------------------------------------------
	 * Steps
	 * ---------------------------------------------------------------- */

	/**
	 * Business settings — only fills empty values.
	 */
	private function settings(): void {
		$current = (array) get_option( 'pallcore_settings', array() );
		foreach ( $this->data['settings'] as $key => $value ) {
			if ( '' === (string) ( $current[ $key ] ?? '' ) ) {
				$current[ $key ] = $value;
			}
		}
		update_option( 'pallcore_settings', $current );
		if ( $this->first_run ) {
			update_option( 'blogname', 'TechNova Systems' );
			update_option( 'blogdescription', 'Powering the Future with Intelligent Technology' );
		}
		if ( ! get_option( 'permalink_structure' ) ) {
			global $wp_rewrite;
			$wp_rewrite->set_permalink_structure( '/%postname%/' );
			$this->log[] = '• Permalinks set to "Post name" (Settings → Permalinks).';
		}
		$this->log[] = '✔ Business settings (contact details, hero, statistics, trust items, chat buttons, newsletter)';
	}

	/**
	 * Bundled original artwork: SVG covers/logos/avatars + WebP product renders.
	 */
	private function import_media(): void {
		$dir  = PALLCORE_DIR . 'assets/demo/';
		$up   = wp_upload_dir();
		$dest = trailingslashit( $up['basedir'] ) . 'palltheme-demo/';
		wp_mkdir_p( $dest );

		// SVG art (covers, logos, avatars) — inserted directly; SVGs need no sub-sizes.
		foreach ( glob( $dir . '*.{svg,pdf}', GLOB_BRACE ) ?: array() as $file ) {
			$name = pathinfo( $file, PATHINFO_FILENAME );
			if ( str_starts_with( $name, 'product-' ) ) {
				continue; // Products use the WebP renders below.
			}
			$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_pall_demo_media', 'meta_value' => $name, 'fields' => 'ids', 'posts_per_page' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( $existing ) {
				$this->media[ $name ] = (int) $existing[0];
				if ( ! get_post_meta( $existing[0], '_wp_attachment_image_alt', true ) && $this->art_alt( $name ) ) {
					update_post_meta( $existing[0], '_wp_attachment_image_alt', $this->art_alt( $name ) );
				}
				continue;
			}
			$target = $dest . basename( $file );
			copy( $file, $target );
			$is_svg = str_ends_with( $file, '.svg' );
			$id     = wp_insert_attachment(
				array(
					'post_mime_type' => $is_svg ? 'image/svg+xml' : 'application/pdf',
					'post_title'     => ucwords( str_replace( '-', ' ', $name ) ),
					'post_status'    => 'inherit',
					'guid'           => trailingslashit( $up['baseurl'] ) . 'palltheme-demo/' . basename( $file ),
				),
				$target
			);
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			if ( $is_svg ) {
				$svg = (string) file_get_contents( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				preg_match( '/viewBox="0 0 ([\d.]+) ([\d.]+)"/', $svg, $m );
				wp_update_attachment_metadata( $id, array( 'width' => (int) ( $m[1] ?? 800 ), 'height' => (int) ( $m[2] ?? 800 ), 'file' => _wp_relative_upload_path( $target ), 'sizes' => array() ) );
			}
			update_post_meta( $id, '_pall_demo', 1 );
			update_post_meta( $id, '_pall_demo_media', $name );
			if ( $this->art_alt( $name ) ) {
				update_post_meta( $id, '_wp_attachment_image_alt', $this->art_alt( $name ) );
			}
			$this->media[ $name ] = (int) $id;
		}

		// Product renders (WebP → real thumbnails).
		foreach ( glob( $dir . 'products/*.webp' ) ?: array() as $file ) {
			$name = pathinfo( $file, PATHINFO_FILENAME );
			$alt  = ucwords( str_replace( array( 'product-', '-detail', '-' ), array( '', ' detail', ' ' ), $name ) ) . ' — illustration';
			$id   = $this->sideload( $file, $name, ucwords( str_replace( '-', ' ', $name ) ), $alt );
			if ( $id ) {
				$this->media[ $name ] = $id;
			}
		}
		$this->log[] = '✔ Media: ' . count( $this->media ) . ' original illustrations and product images (owned by the theme author)';
	}

	/**
	 * Alt text for a bundled artwork file, from its name.
	 *
	 * @param string $name File name without extension.
	 */
	private function art_alt( string $name ): string {
		$words = static fn( string $s ) => ucfirst( str_replace( '-', ' ', $s ) );
		if ( str_starts_with( $name, 'logo-technova' ) ) {
			return 'TechNova Systems';
		}
		if ( str_starts_with( $name, 'client-' ) ) {
			$client = array_filter( $this->data['clients'], static fn( $c ) => sanitize_title( $c[0] ) === substr( $name, 7 ) );
			return ( $client ? reset( $client )[0] : $words( substr( $name, 7 ) ) ) . ' logo (fictional demo brand)';
		}
		if ( str_starts_with( $name, 'team-' ) ) {
			return 'Illustrated avatar';
		}
		if ( str_starts_with( $name, 'cover-' ) ) {
			return $words( str_replace( 'blog-', '', substr( $name, 6 ) ) ) . ' — illustration';
		}
		return '';
	}

	/**
	 * Optional third-party photo pack (not bundled — see class docblock).
	 */
	private function import_photo_pack(): void {
		$dir      = trailingslashit( (string) apply_filters( 'pallcore_demo_photo_pack_dir', WP_CONTENT_DIR . '/palltheme-photo-pack' ) );
		$manifest = $dir . 'manifest.json';
		if ( ! is_readable( $manifest ) ) {
			$this->log[] = '• No photo pack found — original illustrations are used for all images.';
			return;
		}
		$items = json_decode( (string) file_get_contents( $manifest ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$count = 0;
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			$file = $dir . ( $item['file'] ?? '' );
			$key  = 'photo:' . ( $item['key'] ?? '' );
			$id   = $this->sideload(
				$file,
				$key,
				ucfirst( str_replace( '-', ' ', pathinfo( (string) $item['file'], PATHINFO_FILENAME ) ) ),
				(string) ( $item['alt'] ?? '' ),
				array(
					'source'      => $item['source'] ?? '',
					'url'         => $item['original_url'] ?? '',
					'author'      => $item['author'] ?? '',
					'author_url'  => $item['author_url'] ?? '',
					'license'     => $item['license'] ?? '',
					'license_url' => $item['license_url'] ?? '',
					'usage'       => $item['usage'] ?? '',
				)
			);
			if ( $id ) {
				$this->media[ $key ] = $id;
				++$count;
			}
		}
		$this->log[] = '✔ Photo pack: ' . $count . ' licensed photos/videos imported with credits (see the Media Credits page)';
	}

	/**
	 * Taxonomy terms.
	 */
	private function taxonomies(): void {
		foreach ( $this->data['service_cats'] as $name ) {
			$this->term( $name, 'pall_service_cat' );
		}
		foreach ( $this->data['industries'] as $name ) {
			$this->term( $name, 'pall_industry' );
		}
		foreach ( array( 'General', 'Services', 'Hosting', 'Security', 'Store', 'Support' ) as $name ) {
			$this->term( $name, 'pall_faq_cat' );
		}
		foreach ( array( 'Leadership', 'Engineering', 'Operations', 'Security' ) as $name ) {
			$this->term( $name, 'pall_department' );
		}
		$this->log[] = '✔ Categories: service categories, industries, FAQ groups, departments';
	}

	/**
	 * Blog categories + posts (+ unpublish the untouched sample post).
	 */
	private function blog(): void {
		$cats = array();
		foreach ( $this->data['blog_cats'] as $name ) {
			$cats[ $name ] = $this->term( $name, 'category' );
		}
		foreach ( pallcore_demo_posts() as $i => $p ) {
			$id = $this->post(
				array(
					'post_type'     => 'post',
					'post_title'    => $p['title'],
					'post_name'     => $p['slug'],
					'post_excerpt'  => $p['excerpt'],
					'post_content'  => $p['body'],
					'post_category' => array_values( array_filter( array_map( static fn( $c ) => $cats[ $c ] ?? 0, $p['cats'] ) ) ),
					'post_date'     => wp_date( 'Y-m-d H:i:s', strtotime( '-' . ( $i * 4 + 1 ) . ' days' ) ),
					'tags_input'    => $p['tags'],
				)
			);
			$cover = isset( $this->media[ 'cover-' . $p['cover'] ] ) ? 'cover-' . $p['cover'] : (string) $p['cover'];
			$this->thumb( $id, $this->image( (string) $p['photo'], $cover ) ?: ( $this->media[ 'product-' . str_replace( 'product-', '', $cover ) ] ?? 0 ) );
			$this->posts[ $p['slug'] ] = $id;
		}
		$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
		if ( $hello && 'publish' === $hello->post_status && str_contains( $hello->post_content, 'Welcome to WordPress' ) ) {
			wp_update_post( array( 'ID' => $hello->ID, 'post_status' => 'draft' ) );
			$this->log[] = '• The WordPress sample post "Hello world!" was moved to Drafts.';
		}
		$this->log[] = '✔ Blog: ' . count( $cats ) . ' categories, ' . count( $this->posts ) . ' articles';
	}

	/**
	 * Services.
	 */
	private function services(): void {
		foreach ( pallcore_demo_services() as $i => $s ) {
			$id = $this->post(
				array(
					'post_type'    => 'pall_service',
					'post_title'   => $s['title'],
					'post_name'    => $s['slug'],
					'post_excerpt' => $s['excerpt'],
					'post_content' => $s['body'],
					'menu_order'   => $i,
				),
				array(
					'icon'         => $s['icon'],
					'features'     => $s['features'],
					'benefits'     => $s['benefits'],
					'technologies' => $s['tech'],
					'faq'          => $s['faq'],
					'gallery'      => array_values( array_filter( array( $this->image( $s['photo'] ), $this->media[ $s['cover'] ] ?? 0 ) ) ),
				)
			);
			$this->thumb( $id, $this->image( $s['photo'], $s['cover'] ) );
			wp_set_object_terms( $id, $s['cat'], 'pall_service_cat' );
			$this->services[ $s['slug'] ] = $id;
		}
		$this->log[] = '✔ Services: ' . count( $this->services );
	}

	/**
	 * Solutions.
	 */
	private function solutions(): void {
		foreach ( pallcore_demo_solutions() as $i => $s ) {
			$id = $this->post(
				array(
					'post_type'    => 'pall_solution',
					'post_title'   => $s['title'],
					'post_name'    => $s['slug'],
					'post_excerpt' => $s['excerpt'],
					'post_content' => '',
					'menu_order'   => $i,
				),
				array(
					'icon'           => $s['icon'],
					'problem'        => $s['problem'],
					'approach'       => $s['approach'],
					'architecture'   => $s['architecture'],
					'implementation' => $s['implementation'],
					'features'       => $s['features'],
					'technologies'   => $s['tech'],
					'benefits'       => array(
						array( 'Reliability', 'Redundant, monitored designs keep critical services available.' ),
						array( 'Security', 'Controls built in from the start, not added later.' ),
						array( 'Clarity', 'Documentation and runbooks your team can use.' ),
					),
				)
			);
			$this->thumb( $id, $this->image( $s['photo'], $s['cover'] ) );
			wp_set_object_terms( $id, $s['industry'], 'pall_industry' );
			$this->solutions[ $s['slug'] ] = $id;
		}
		$this->log[] = '✔ Solutions: ' . count( $this->solutions );
	}

	/**
	 * Case studies.
	 */
	private function case_studies(): void {
		foreach ( pallcore_demo_cases() as $i => $c ) {
			$photo = $this->image( $c['photo'] );
			$cover = $this->media[ 'cover-' . $c['cover'] ] ?? 0;
			$id    = $this->post(
				array(
					'post_type'    => 'pall_case_study',
					'post_title'   => $c['title'],
					'post_name'    => $c['slug'],
					'post_excerpt' => $c['excerpt'],
					'post_content' => '',
					'menu_order'   => $i,
				),
				array_merge( $c['meta'], array( 'gallery' => array_values( array_filter( array( $photo, $cover ) ) ) ) )
			);
			$this->thumb( $id, $photo ?: $cover );
			wp_set_object_terms( $id, $c['industry'], 'pall_industry' );
			$this->cases[ $c['slug'] ] = $id;
		}
		$this->log[] = '✔ Case studies: ' . count( $this->cases ) . ' (fictional demo clients)';
	}

	/**
	 * Team.
	 */
	private function team(): void {
		foreach ( $this->data['team'] as $i => [ $name, $role, $img, $dept, $bio, $skills ] ) {
			$id = $this->post(
				array(
					'post_type'    => 'pall_team',
					'post_title'   => $name,
					'post_content' => '<p>' . esc_html( $bio ) . '</p><p><em>Demo team member — replace with your own team.</em></p>',
					'menu_order'   => $i,
				),
				array(
					'position' => $role,
					'skills'   => $skills,
					'linkedin' => 'https://linkedin.com/',
				)
			);
			$this->thumb( $id, $this->media[ 'team-' . $img ] ?? 0 );
			wp_set_object_terms( $id, $dept, 'pall_department' );
		}
		$this->log[] = '✔ Team: ' . count( $this->data['team'] ) . ' demo members (illustrated avatars)';
	}

	/**
	 * Testimonials.
	 */
	private function testimonials(): void {
		foreach ( $this->data['testimonials'] as $i => [ $name, $role, $company, $quote ] ) {
			$brand = trim( (string) preg_replace( '/^Demo Company\s*—\s*/u', '', $company ) );
			$id    = $this->post(
				array(
					'post_type'    => 'pall_testimonial',
					'post_title'   => $name,
					'post_content' => $quote,
					'menu_order'   => $i,
				),
				array(
					'position'     => $role,
					'company'      => $company,
					'rating'       => '5',
					'company_logo' => $this->media[ 'client-' . sanitize_title( $brand ) ] ?? '',
				)
			);
			$this->thumb( $id, $this->media[ 'team-client-' . ( $i + 1 ) ] ?? 0 );
		}
		$this->log[] = '✔ Testimonials: ' . count( $this->data['testimonials'] ) . ' (fictional people, labelled "Demo Company")';
	}

	/**
	 * Clients.
	 */
	private function clients(): void {
		foreach ( $this->data['clients'] as $i => [ $name, $industry ] ) {
			$id = $this->post(
				array(
					'post_type'  => 'pall_client',
					'post_title' => $name,
					'menu_order' => $i,
				),
				array( 'industry' => $industry )
			);
			$this->thumb( $id, $this->media[ 'client-' . sanitize_title( $name ) ] ?? 0 );
			wp_set_object_terms( $id, $industry, 'pall_industry' );
		}
		$this->log[] = '✔ Client logos: ' . count( $this->data['clients'] ) . ' fictional demo brands';
	}

	/**
	 * Technologies (text marks — no third-party logos).
	 */
	private function technologies(): void {
		foreach ( $this->data['technologies'] as $i => [ $name, $short, $group ] ) {
			$id = $this->post( array( 'post_type' => 'pall_technology', 'post_title' => $name, 'menu_order' => $i ), array( 'short' => $short ) );
			wp_set_object_terms( $id, $group, 'pall_tech_cat' );
		}
		$this->log[] = '✔ Technology stack: ' . count( $this->data['technologies'] ) . ' (text marks; trademarks belong to their owners)';
	}

	/**
	 * Pricing plans.
	 */
	private function pricing(): void {
		foreach ( $this->data['pricing'] as $i => [ $name, $desc, $price, $period, $features, $featured, $badge ] ) {
			$this->post(
				array( 'post_type' => 'pall_pricing', 'post_title' => $name, 'post_excerpt' => $desc, 'menu_order' => $i ),
				array(
					'price'       => $price,
					'period'      => $period,
					'features'    => $features,
					'featured'    => $featured ? '1' : '',
					'badge'       => $badge,
					'button_text' => 'Custom' === $price ? 'Talk to an expert' : 'Get a quote',
					'button_url'  => home_url( '/contact/' ),
				)
			);
		}
		$this->log[] = '✔ Pricing plans: ' . count( $this->data['pricing'] );
	}

	/**
	 * FAQs.
	 */
	private function faqs(): void {
		foreach ( $this->data['faqs'] as $i => [ $group, $q, $a ] ) {
			$id = $this->post( array( 'post_type' => 'pall_faq', 'post_title' => $q, 'post_content' => $a, 'menu_order' => $i ) );
			wp_set_object_terms( $id, $group, 'pall_faq_cat' );
		}
		$this->log[] = '✔ FAQs: ' . count( $this->data['faqs'] ) . ' in 6 groups';
	}

	/**
	 * Jobs.
	 */
	private function jobs(): void {
		foreach ( $this->data['jobs'] as $i => [ $title, $dept, $location, $type, $summary ] ) {
			$content = '<p>' . esc_html( $summary ) . '</p><h2>What you will do</h2><ul><li>Own work from design to handover</li><li>Work directly with customers and vendors</li><li>Document, automate and continuously improve</li></ul><h2>What we offer</h2><ul><li>Training and certification budget</li><li>Flexible hybrid working</li><li>Health cover and annual bonus</li></ul><p><em>Demo job listing.</em></p>';
			$id      = $this->post(
				array( 'post_type' => 'pall_job', 'post_title' => $title, 'post_excerpt' => $summary, 'post_content' => $content, 'menu_order' => $i ),
				array( 'department' => $dept, 'location' => $location, 'job_type' => $type, 'deadline' => wp_date( 'F j, Y', strtotime( '+45 days' ) ) )
			);
			wp_set_object_terms( $id, $dept, 'pall_department' );
		}
		$this->log[] = '✔ Job openings: ' . count( $this->data['jobs'] );
	}

	/**
	 * WooCommerce: settings, categories, attributes, products, reviews.
	 */
	private function woocommerce(): void {
		if ( class_exists( 'WC_Install' ) ) {
			WC_Install::create_pages();
		}
		if ( 'yes' === get_option( 'woocommerce_coming_soon' ) ) {
			update_option( 'woocommerce_coming_soon', 'no' );
			$this->log[] = '• WooCommerce "Coming soon" mode was turned off so the demo store is visible (WooCommerce → Settings → Site visibility).';
		}
		if ( $this->first_run ) {
			update_option( 'woocommerce_demo_store', 'yes' );
			update_option( 'woocommerce_demo_store_notice', 'Demo store — products, prices and specifications are illustrative. Brand names belong to their owners; no affiliation is implied. Orders will not be fulfilled.' );
			$this->log[] = '• Demo store notice enabled (Appearance → Customize → WooCommerce → Store notice). Turn it off when you go live.';
		}
		// A checkout needs at least one payment and one shipping method. Only filled in when the store has none.
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		if ( isset( $gateways['cod'] ) && ! array_filter( $gateways, static fn( $g ) => 'yes' === $g->enabled ) ) {
			$cod            = (array) get_option( 'woocommerce_cod_settings', array() );
			$cod['enabled'] = 'yes';
			update_option( 'woocommerce_cod_settings', $cod );
			$this->log[] = '• No payment method was enabled, so Cash on delivery was switched on for the demo checkout. Set up your real payment methods in WooCommerce → Settings → Payments.';
		}
		if ( class_exists( 'WC_Shipping_Zones' ) && ! WC_Shipping_Zones::get_zones() ) {
			$rest = new WC_Shipping_Zone( 0 );
			if ( ! $rest->get_shipping_methods() ) {
				$rest->add_shipping_method( 'free_shipping' );
				$this->log[] = '• No shipping zones existed, so "Free shipping" was added for all locations. Set up your real rates in WooCommerce → Settings → Shipping.';
			}
		}
		if ( defined( 'YITH_WCWL' ) && ! get_post_status( (int) get_option( 'yith_wcwl_wishlist_page_id' ) ) ) {
			$wish = $this->post( array( 'post_type' => 'page', 'post_title' => 'Wishlist', 'post_name' => 'wishlist', 'post_content' => '<!-- wp:shortcode -->[yith_wcwl_wishlist]<!-- /wp:shortcode -->' ) );
			update_option( 'yith_wcwl_wishlist_page_id', $wish );
			$this->log[] = '• Wishlist page created for YITH WooCommerce Wishlist.';
		}
		if ( defined( 'YITH_WCWL' ) && $this->first_run && 'php-templates' !== get_option( 'yith_wcwl_rendering_method' ) ) {
			// YITH's React buttons add about 1 MB of JavaScript (React, lodash, moment) to every store page;
			// its classic PHP-template buttons do the same job with one small jQuery file.
			update_option( 'yith_wcwl_rendering_method', 'php-templates' );
			$this->log[] = '• YITH Wishlist switched to its lightweight classic buttons (YITH → Wishlist → rendering method) for faster store pages.';
		}
		if ( defined( 'YITH_WOOCOMPARE' ) && 'shop' === get_option( 'yith_woocompare_show_compare_button_in', 'shop' ) && $this->first_run ) {
			update_option( 'yith_woocompare_show_compare_button_in', 'both' );
			$this->log[] = '• YITH Compare button enabled on product pages as well as the shop.';
		}

		// Categories.
		$cat_ids = array();
		foreach ( $this->data['product_cats'] as $parent => $children ) {
			$pid                = $this->term( $parent, 'product_cat' );
			$cat_ids[ $parent ] = $pid;
			foreach ( $children as $child ) {
				$cat_ids[ $child ] = $this->term( $child, 'product_cat', $pid );
			}
		}

		// Global attributes.
		$attr_tax = array();
		foreach ( $this->data['attributes'] as $label ) {
			$slug = wc_sanitize_taxonomy_name( $label );
			if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
				wc_create_attribute( array( 'name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
			}
			$taxonomy = wc_attribute_taxonomy_name( $slug );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ) );
			}
			$attr_tax[ $label ] = $taxonomy;
		}

		$datasheet = isset( $this->media['datasheet-sample'] ) ? wp_get_attachment_url( $this->media['datasheet-sample'] ) : '';
		foreach ( pallcore_demo_products() as $i => $p ) {
			$existing = wc_get_product_id_by_sku( $p['sku'] );
			if ( $existing ) {
				$this->products[ $p['sku'] ] = $existing;
				continue;
			}
			$variable = ! empty( $p['variable'] );
			$product  = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
			$product->set_name( $p['name'] );
			$product->set_sku( $p['sku'] );
			$product->set_status( 'publish' );
			$product->set_short_description( $p['short'] );
			$product->set_description( $this->product_description( $p ) );
			$product->set_category_ids( array_filter( array( $cat_ids[ $p['cat'] ] ?? 0 ) ) );
			$product->set_featured( ! empty( $p['featured'] ) );
			$product->set_menu_order( $i );
			$main   = $this->media[ 'product-' . $p['img'] ] ?? 0;
			$detail = $this->media[ 'product-' . $p['img'] . '-detail' ] ?? 0;
			if ( $main ) {
				$product->set_image_id( $main );
			}
			if ( $detail ) {
				$product->set_gallery_image_ids( array( $detail ) );
			}
			if ( ! empty( $p['virtual'] ) ) {
				$product->set_virtual( true );
			}
			if ( ! $variable ) {
				$product->set_regular_price( $p['price'] );
				if ( $p['sale'] ) {
					$product->set_sale_price( $p['sale'] );
				}
				if ( $p['stock'] >= 0 ) {
					$product->set_manage_stock( true );
					$product->set_stock_quantity( $p['stock'] );
				}
			}

			$attributes = array();
			$pos        = 0;
			foreach ( $p['attrs'] as $label => $value ) {
				$attr = new WC_Product_Attribute();
				$attr->set_id( wc_attribute_taxonomy_id_by_name( wc_sanitize_taxonomy_name( $label ) ) );
				$attr->set_name( $attr_tax[ $label ] );
				$attr->set_options( array( $this->term( $value, $attr_tax[ $label ] ) ) );
				$attr->set_position( $pos++ );
				$attr->set_visible( true );
				$attr->set_variation( false );
				$attributes[] = $attr;
			}
			$var_label = $variable ? (string) array_key_first( $p['variable'] ) : '';
			if ( $variable ) {
				$terms = array();
				foreach ( array_keys( $p['variable'][ $var_label ] ) as $order => $value ) {
					$term_id = $this->term( $value, $attr_tax[ $var_label ] );
					update_term_meta( $term_id, 'order', $order ); // Show 1m, 3m, 10m — not alphabetical 10m, 1m, 3m.
					$terms[] = $term_id;
				}
				$attr = new WC_Product_Attribute();
				$attr->set_id( wc_attribute_taxonomy_id_by_name( wc_sanitize_taxonomy_name( $var_label ) ) );
				$attr->set_name( $attr_tax[ $var_label ] );
				$attr->set_options( $terms );
				$attr->set_position( $pos );
				$attr->set_visible( true );
				$attr->set_variation( true );
				$attributes[] = $attr;
			}
			$product->set_attributes( $attributes );
			$id = $product->save();

			if ( $variable ) {
				foreach ( $p['variable'][ $var_label ] as $value => [ $price, $stock ] ) {
					$term      = get_term_by( 'name', $value, $attr_tax[ $var_label ] );
					$variation = new WC_Product_Variation();
					$variation->set_parent_id( $id );
					$variation->set_attributes( array( $attr_tax[ $var_label ] => $term ? $term->slug : sanitize_title( $value ) ) );
					$variation->set_regular_price( $price );
					$variation->set_sku( $p['sku'] . '-' . strtoupper( sanitize_title( $value ) ) );
					$variation->set_manage_stock( true );
					$variation->set_stock_quantity( $stock );
					$variation->set_status( 'publish' );
					$variation->save();
				}
				WC_Product_Variable::sync( $id );
			}
			if ( taxonomy_exists( 'product_brand' ) && ! empty( $p['brand'] ) ) {
				wp_set_object_terms( $id, $this->term( $p['brand'], 'product_brand' ), 'product_brand' );
			}
			update_post_meta( $id, '_pall_demo', 1 );
			update_post_meta( $id, '_pall_specs', $p['specs'] );
			update_post_meta( $id, '_pall_warranty', $p['warranty'] );
			if ( ! empty( $p['downloads'] ) && $datasheet ) {
				update_post_meta( $id, '_pall_downloads', array( array( 'Product datasheet (sample PDF)', $datasheet ), array( 'Quick start guide (sample PDF)', $datasheet ) ) );
			}
			$this->products[ $p['sku'] ] = $id;
		}

		// Cross-sells ("frequently bought together") and upsells.
		foreach ( pallcore_demo_products() as $p ) {
			$product = wc_get_product( $this->products[ $p['sku'] ] ?? 0 );
			if ( ! $product ) {
				continue;
			}
			$changed = false;
			if ( ! empty( $p['cross'] ) && ! $product->get_cross_sell_ids() ) {
				$product->set_cross_sell_ids( array_values( array_filter( array_map( fn( $s ) => $this->products[ $s ] ?? 0, $p['cross'] ) ) ) );
				$changed = true;
			}
			if ( ! empty( $p['up'] ) && ! $product->get_upsell_ids() ) {
				$product->set_upsell_ids( array_values( array_filter( array_map( fn( $s ) => $this->products[ $s ] ?? 0, $p['up'] ) ) ) );
				$changed = true;
			}
			if ( $changed ) {
				$product->save();
			}
		}

		// Reviews — clearly marked as demo reviewers.
		$reviews = array(
			array( 'TN-SRV-R760', 5, 'Arrived configured exactly as requested, with a burn-in report.' ), array( 'TN-SRV-R760', 4, 'Quiet and fast. Delivery took one extra day.' ),
			array( 'TN-SW-C9300-48', 5, 'Latest firmware out of the box and helpful stacking advice.' ), array( 'TN-SFP-10G-SR', 5, 'Worked first time in our switches. Good value for volume orders.' ),
			array( 'TN-FW-100F', 5, 'Smooth migration from our old firewall with the team’s help.' ), array( 'TN-SSD-NVME-U2', 5, 'Consistent latency under load. Exactly what we needed.' ),
			array( 'TN-UPS-3K', 4, 'Solid unit; the network card made monitoring easy.' ), array( 'TN-RT-CCR2116', 5, 'Handles our BGP sessions comfortably.' ),
			array( 'TN-NAS-8BAY', 5, 'Snapshots and replication were simple to set up.' ), array( 'TN-SW-25G-48', 5, 'Great fabric switch for our new cluster.' ),
			array( 'TN-RAM-DDR5', 5, 'Compatible with our servers as promised.' ), array( 'TN-SVC-MANAGED-NET', 5, 'Proactive alerts caught an issue before users did.' ),
		);
		$n = 0;
		foreach ( $reviews as [ $sku, $rating, $text ] ) {
			$pid = $this->products[ $sku ] ?? 0;
			++$n;
			if ( ! $pid || get_comments( array( 'post_id' => $pid, 'author_email' => 'demo' . $n . '@example.com', 'count' => true ) ) ) {
				continue;
			}
			$cid = wp_insert_comment( array( 'comment_post_ID' => $pid, 'comment_author' => 'Demo Customer ' . chr( 64 + $n ), 'comment_author_email' => 'demo' . $n . '@example.com', 'comment_content' => $text, 'comment_type' => 'review', 'comment_approved' => 1 ) );
			if ( $cid ) {
				update_comment_meta( $cid, 'rating', $rating );
			}
			if ( class_exists( 'WC_Comments' ) ) {
				WC_Comments::clear_transients( $pid );
			}
		}

		$this->log[] = '✔ Store: ' . count( $cat_ids ) . ' categories, ' . count( $this->data['attributes'] ) . ' filterable attributes, ' . count( $this->products ) . ' products (simple, variable, virtual), demo reviews, cross-sells and upsells';
	}

	/**
	 * Product description built from the product data.
	 *
	 * @param array $p Product data.
	 */
	private function product_description( array $p ): string {
		$service = ! empty( $p['virtual'] ) && 'Support' === $p['cat'];
		$html    = '<p>' . esc_html( $p['short'] ) . '</p>';
		if ( $service ) {
			$html .= '<h2>How it works</h2><ul><li>Place your order online or request a quote</li><li>An engineer contacts you within one business day to confirm scope</li><li>Work is scheduled at a time that suits you</li></ul>';
		} else {
			$html .= '<h2>Why buy from us</h2><ul><li>Every unit is inspected and firmware-updated before dispatch</li><li>Optional pre-configuration and burn-in testing</li><li>Pre-sales advice from engineers, not just sales staff</li></ul><p>Need a full solution? Explore our <a href="{svc:server-solutions}">server</a> and <a href="{svc:network-infrastructure}">network</a> services.</p>';
		}
		return $html . '<p><em>Demo listing: specifications, prices and stock are illustrative.</em></p>';
	}

	/**
	 * Related content links (after everything exists).
	 */
	private function link_related(): void {
		$ids = static fn( array $map, array $keys ) => array_values( array_filter( array_map( static fn( $k ) => $map[ $k ] ?? 0, $keys ) ) );

		foreach ( pallcore_demo_services() as $s ) {
			$id = $this->services[ $s['slug'] ] ?? 0;
			if ( ! $id ) {
				continue;
			}
			$same = array();
			foreach ( pallcore_demo_services() as $o ) {
				if ( $o['slug'] !== $s['slug'] && $o['cat'] === $s['cat'] ) {
					$same[] = $o['slug'];
				}
			}
			update_post_meta( $id, '_pall_related_services', $ids( $this->services, array_slice( $same, 0, 3 ) ) );
			update_post_meta( $id, '_pall_related_products', $ids( $this->products, $s['products'] ) );
		}
		foreach ( pallcore_demo_solutions() as $s ) {
			$id = $this->solutions[ $s['slug'] ] ?? 0;
			update_post_meta( $id, '_pall_related_services', $ids( $this->services, $s['services'] ) );
			update_post_meta( $id, '_pall_related_products', $ids( $this->products, $s['products'] ) );
			update_post_meta( $id, '_pall_related_cases', $ids( $this->cases, $s['cases'] ) );
		}
		foreach ( pallcore_demo_cases() as $c ) {
			$id = $this->cases[ $c['slug'] ] ?? 0;
			update_post_meta( $id, '_pall_related_services', $ids( $this->services, $c['services'] ) );
			update_post_meta( $id, '_pall_related_solutions', $ids( $this->solutions, $c['solutions'] ) );
		}
		foreach ( pallcore_demo_posts() as $p ) {
			$id = $this->posts[ $p['slug'] ] ?? 0;
			update_post_meta( $id, '_pall_related_services', $ids( $this->services, $p['services'] ) );
			update_post_meta( $id, '_pall_related_products', $ids( $this->products, $p['products'] ) );
		}
		$this->log[] = '✔ Internal links: services ↔ products, solutions ↔ case studies, blog → services/products';
	}

	/**
	 * Replace {type:key} placeholders in demo content with real URLs.
	 */
	private function resolve_links(): void {
		$resolve = function ( array $m ): string {
			[ , $type, $key ] = $m;
			$url = '';
			switch ( $type ) {
				case 'svc':
					$url = isset( $this->services[ $key ] ) ? get_permalink( $this->services[ $key ] ) : '';
					break;
				case 'sol':
					$url = isset( $this->solutions[ $key ] ) ? get_permalink( $this->solutions[ $key ] ) : '';
					break;
				case 'case':
					$url = isset( $this->cases[ $key ] ) ? get_permalink( $this->cases[ $key ] ) : '';
					break;
				case 'post':
					$url = isset( $this->posts[ $key ] ) ? get_permalink( $this->posts[ $key ] ) : '';
					break;
				case 'page':
					$url = isset( $this->pages[ $key ] ) ? get_permalink( $this->pages[ $key ] ) : '';
					break;
				case 'pcat':
					$term = get_term_by( 'slug', $key, 'product_cat' );
					$url  = $term ? get_term_link( $term ) : '';
					break;
				case 'product':
					$url = isset( $this->products[ $key ] ) ? get_permalink( $this->products[ $key ] ) : '';
					break;
			}
			if ( ! $url || is_wp_error( $url ) ) {
				$this->log[] = '⚠ Unresolved link {' . $type . ':' . $key . '} pointed to the homepage';
				$url         = home_url( '/' );
			}
			return esc_url( $url );
		};
		$ids = array_merge( array_values( $this->services ), array_values( $this->posts ), array_values( $this->products ) );
		$n   = 0;
		foreach ( $ids as $id ) {
			$content = (string) get_post_field( 'post_content', $id );
			if ( ! str_contains( $content, '{' ) ) {
				continue;
			}
			$new = (string) preg_replace_callback( '/\{(svc|sol|case|post|page|pcat|product):([A-Za-z0-9_-]+)\}/', $resolve, $content );
			if ( $new !== $content ) {
				wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $new ) ) );
				++$n;
			}
		}
		$this->log[] = '✔ Internal links resolved in ' . $n . ' items';
	}

	/* ------------------------------------------------------------------
	 * Pages (+ Elementor layouts)
	 * ---------------------------------------------------------------- */

	/**
	 * Elementor element ID.
	 */
	private function el_id(): string {
		return substr( md5( wp_generate_uuid4() ), 0, 7 );
	}

	/**
	 * Widget element.
	 *
	 * @param string $type     Widget type.
	 * @param array  $settings Settings.
	 */
	private function el_widget( string $type, array $settings = array() ): array {
		return array( 'id' => $this->el_id(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}

	/**
	 * Full-width section holding widgets.
	 *
	 * @param array $widgets Widgets.
	 */
	private function el_section( array $widgets ): array {
		return array(
			'id'       => $this->el_id(),
			'elType'   => 'section',
			'settings' => array( 'layout' => 'full_width', 'gap' => 'no' ),
			'elements' => array( array( 'id' => $this->el_id(), 'elType' => 'column', 'settings' => array( '_column_size' => 100 ), 'elements' => $widgets ) ),
		);
	}

	/**
	 * Boxed text section (prose).
	 *
	 * @param string $html HTML.
	 */
	private function el_text( string $html ): array {
		$section                              = $this->el_section( array( $this->el_widget( 'text-editor', array( 'editor' => $html ) ) ) );
		$section['settings']['layout']        = 'boxed';
		$section['settings']['content_width'] = array( 'unit' => 'px', 'size' => 860 );
		$section['settings']['padding']       = array( 'unit' => 'px', 'top' => '56', 'right' => '16', 'bottom' => '56', 'left' => '16', 'isLinked' => false );
		return $section;
	}

	/**
	 * Media control value for Elementor (the shortcode fallback uses the id).
	 */
	private function media_setting( int $id ): array {
		return $id ? array( 'id' => $id, 'url' => (string) wp_get_attachment_url( $id ) ) : array( 'id' => '', 'url' => '' );
	}

	/**
	 * Create a page with shortcode content and a matching Elementor layout.
	 *
	 * Blocks: ['html', $html] or [tag, settings] where tag is both the
	 * shortcode and the Elementor widget name (pall_*).
	 *
	 * @param string $slug     Slug.
	 * @param string $title    Title.
	 * @param string $excerpt  Excerpt (shown under the title).
	 * @param array  $blocks   Blocks.
	 * @param string $template Page template.
	 */
	private function page( string $slug, string $title, string $excerpt, array $blocks, string $template = '' ): int {
		$content  = '';
		$elements = array();
		$mixed    = count( array_filter( $blocks, static fn( $b ) => 'html' !== $b[0] ) ) > 0;
		foreach ( $blocks as $block ) {
			if ( 'html' === $block[0] ) {
				$content   .= $mixed ? '<!-- wp:html --><div class="pt-section pt-section--tight"><div class="pt-container pt-container--narrow pt-prose">' . $block[1] . '</div></div><!-- /wp:html -->' : $block[1];
				$elements[] = $this->el_text( $block[1] );
				continue;
			}
			[ $tag, $settings ] = $block;
			$atts = '';
			foreach ( $settings as $k => $v ) {
				if ( is_array( $v ) ) {
					$v = ! empty( $v['id'] ) ? $v['id'] : ( $v['url'] ?? '' );
				}
				if ( is_scalar( $v ) && '' !== (string) $v ) {
					$atts .= ' ' . $k . '="' . esc_attr( str_replace( array( "\r\n", "\n", '"' ), array( '\n', '\n', '&quot;' ), (string) $v ) ) . '"';
				}
			}
			$content .= '<!-- wp:shortcode -->[' . $tag . $atts . ']<!-- /wp:shortcode -->' . "\n";
			$widgets  = function_exists( 'pallcore_elementor_widget_configs' ) ? array_map( static fn( $c ) => 'pall_' . $c['name'], pallcore_elementor_widget_configs() ) : array();
			if ( ! in_array( $tag, $widgets, true ) ) {
				// Shortcode-only blocks (no dedicated widget) go into Elementor's Shortcode widget.
				$elements[] = $this->el_section( array( $this->el_widget( 'shortcode', array( 'shortcode' => '[' . $tag . $atts . ']' ) ) ) );
				continue;
			}
			// Elementor URL controls expect arrays; the section ID control is called "anchor".
			foreach ( array( 'link_url', 'btn1_url', 'btn2_url', 'button_url', 'button2_url' ) as $url_key ) {
				if ( isset( $settings[ $url_key ] ) && is_string( $settings[ $url_key ] ) ) {
					$settings[ $url_key ] = array( 'url' => $settings[ $url_key ], 'is_external' => '', 'nofollow' => '' );
				}
			}
			if ( isset( $settings['id'] ) ) {
				$settings['anchor'] = $settings['id'];
				unset( $settings['id'] );
			}
			$elements[] = $this->el_section( array( $this->el_widget( $tag, $settings ) ) );
		}

		$id = $this->post( array( 'post_type' => 'page', 'post_title' => $title, 'post_name' => $slug, 'post_excerpt' => $excerpt, 'post_content' => $content ) );
		if ( ! $id ) {
			return 0;
		}
		if ( $template ) {
			update_post_meta( $id, '_wp_page_template', $template );
		}
		if ( defined( 'ELEMENTOR_VERSION' ) && $elements && ! get_post_meta( $id, '_elementor_data', true ) && get_post_meta( $id, '_pall_demo', true ) ) {
			update_post_meta( $id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $id, '_elementor_template_type', 'wp-page' );
			update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
			update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
		}
		$this->pages[ $slug ] = $id;
		return $id;
	}

	/**
	 * All pages.
	 */
	private function pages(): void {
		$pg      = $this->data['pages'];
		$contact = home_url( '/contact/' );
		$lottie  = PALLCORE_URL . 'assets/lottie/network-pulse.json';

		// Hero media from the photo pack (if present) into Business Settings — empty values only.
		$settings = (array) get_option( 'pallcore_settings', array() );
		$fill     = array(
			'hero_image'      => $this->image( 'hero' ),
			'hero_poster'     => $this->image( 'hero-poster' ),
			'hero_video_webm' => isset( $this->media['photo:hero-video-webm'] ) ? wp_get_attachment_url( $this->media['photo:hero-video-webm'] ) : '',
			'hero_video_mp4'  => isset( $this->media['photo:hero-video-mp4'] ) ? wp_get_attachment_url( $this->media['photo:hero-video-mp4'] ) : '',
			'hero_btn1_url'   => home_url( '/solutions/' ),
			'hero_btn2_url'   => $contact,
			'quote_url'       => $contact,
		);
		foreach ( $fill as $k => $v ) {
			if ( $v && '' === (string) ( $settings[ $k ] ?? '' ) ) {
				$settings[ $k ] = $v;
			}
		}
		update_option( 'pallcore_settings', $settings );

		// Simple pages first so later layouts can link to them.
		$this->page( 'faq', $pg['faq'][0], $pg['faq'][1], array(
			array( 'pall_faq', array( 'eyebrow' => 'General', 'title' => 'About TechNova', 'category' => 'general', 'spacing' => 'tight' ) ),
			array( 'pall_faq', array( 'eyebrow' => 'Services', 'title' => 'Services & deployment', 'category' => 'services', 'background' => 'alt' ) ),
			array( 'pall_faq', array( 'eyebrow' => 'Hosting', 'title' => 'Hosting, VPS & cloud', 'category' => 'hosting' ) ),
			array( 'pall_faq', array( 'eyebrow' => 'Security', 'title' => 'Security & backup', 'category' => 'security', 'background' => 'alt' ) ),
			array( 'pall_faq', array( 'eyebrow' => 'Store', 'title' => 'Orders, shipping, warranty & returns', 'category' => 'store' ) ),
			array( 'pall_faq', array( 'eyebrow' => 'Support', 'title' => 'Support & maintenance', 'category' => 'support', 'background' => 'alt' ) ),
			array( 'pall_cta', array() ),
		) );
		$this->page( 'contact', $pg['contact'][0], $pg['contact'][1], array(
			array( 'pall_contact', array() ),
			array( 'pall_cta', array( 'heading' => 'Already a customer? *Get support*', 'text' => 'Open a ticket, request remote help or reach the emergency line from our support center.', 'button' => 'Contact Support', 'button_url' => home_url( '/support/' ) ) ),
		) );

		$this->page( 'about', $pg['about'][0], $pg['about'][1], array(
			array( 'pall_split', array( 'priority' => 'yes', 'eyebrow' => 'Company overview', 'title' => 'Engineers *first*, since 2010', 'image' => $this->media_setting( $this->image( 'about-team', 'cover-page' ) ), 'text' => "TechNova Systems is a modern technology solutions provider delivering enterprise infrastructure, networking, cloud, cybersecurity, server, software and managed IT solutions for businesses of all sizes.\n\nWe design, build and run the technology our customers depend on — and we document it so their teams stay in control. (TechNova is a fictional demo company.)", 'bullets' => "Vendor-neutral recommendations\nEngineers on every project\nDocumentation as standard\nLong-term support", 'badge_value' => '500+', 'badge_label' => 'Projects delivered' ) ),
			array( 'pall_features', array( 'eyebrow' => 'Purpose', 'title' => 'Mission, vision & *values*', 'align' => 'center', 'background' => 'alt', 'columns' => 3, 'items' => "rocket | Our mission | Help organisations run secure, reliable technology so they can focus on their customers.\nglobe | Our vision | A world where every business has enterprise-grade infrastructure, regardless of size.\ncheck | Our values | Clarity, craftsmanship, ownership and security in everything we deliver." ) ),
			array( 'pall_split', array( 'eyebrow' => 'Why choose us', 'title' => 'A partner that *stays* after go-live', 'image' => $this->media_setting( $this->image( 'about-office', 'cover-enterprise' ) ), 'reverse' => 'yes', 'text' => 'Projects end; infrastructure does not. We plan for the years after deployment — monitoring, patching, capacity and refresh — so your systems keep improving instead of slowly ageing.', 'bullets' => "Clear proposals with fixed scopes\nStaged, change-controlled delivery\nResponsive support\nHonest advice, including when to wait", 'button_text' => 'Talk to an Expert', 'button_url' => $contact ) ),
			array( 'pall_features', array( 'eyebrow' => 'Infrastructure & capabilities', 'title' => 'What we bring to every *engagement*', 'columns' => 3, 'items' => "headset | 24/7 monitoring | An operations team watches managed customer systems around the clock.\nlayers | Staging lab | Equipment is configured and tested in our lab before it reaches your site.\nusers | Specialist engineers | Networking, cloud, security and systems specialists on one team.\nnetwork | Multi-vendor expertise | Experience across the leading switching, routing, server and cloud platforms.\nchart | Measurable outcomes | Agreed metrics for performance, availability and response times.\nlicense | Documentation | As-built diagrams, runbooks and handover packs for every project." ) ),
			array( 'pall_technologies', array( 'eyebrow' => 'Technology ecosystem', 'title' => 'Expertise across *leading platforms*', 'align' => 'center', 'background' => 'alt', 'lead' => 'Technologies we work with. Names are trademarks of their owners; no partnership is implied.' ) ),
			array( 'pall_stats', array( 'style' => 'cards' ) ),
			array( 'pall_team', array( 'eyebrow' => 'Our team', 'title' => 'The people behind the *work*', 'lead' => 'Demo team members.', 'count' => 8, 'columns' => 4, 'background' => 'alt' ) ),
			array( 'pall_cta', array() ),
		) );

		$this->page( 'pricing', $pg['pricing'][0], $pg['pricing'][1], array(
			array( 'pall_pricing', array() ),
			array( 'pall_features', array( 'eyebrow' => 'Included in every plan', 'title' => 'The *essentials*, always', 'align' => 'center', 'background' => 'alt', 'style' => 'minimal', 'columns' => 3, 'items' => "shield | Security baseline | Patching, endpoint protection and MFA guidance.\nbackup | Backup checks | Monitored backups with regular restore tests.\nchart | Monthly reporting | Clear reports on health, tickets and recommendations." ) ),
			array( 'pall_faq', array( 'eyebrow' => 'FAQ', 'title' => 'Plans & billing questions', 'align' => 'center', 'category' => 'services' ) ),
			array( 'pall_cta', array() ),
		) );

		$tutorials = get_term_by( 'slug', 'tutorials', 'category' );
		$tut_url   = $tutorials ? (string) get_category_link( $tutorials ) : home_url( '/blog/' );
		$phone     = (string) pallcore_setting( 'phone' );
		$this->page( 'support', $pg['support'][0], $pg['support'][1], array(
			array( 'pall_features', array( 'eyebrow' => 'Support center', 'title' => 'How can we *help*?', 'columns' => 3, 'spacing' => 'tight', 'items' => "headset | Technical support | Business-hours help for configuration, troubleshooting and questions. | {$contact}\nlicense | Open a ticket | Describe the issue and an engineer will respond within your plan’s target. | {$contact}#pall-name\ncode | Documentation | Guides and tutorials written by our engineers. | {$tut_url}\nlayers | Knowledge base | In-depth articles on networking, cloud, security and infrastructure. | " . home_url( '/blog/' ) . "\nconsult | FAQ | Quick answers about services, hosting, orders and warranty. | " . home_url( '/faq/' ) . "\nterminal | Remote support | Secure, permission-based remote sessions to fix issues faster. | #remote\nbolt | Emergency support | 24/7 line for managed customers with critical incidents. | tel:" . pallcore_phone_digits( $phone ) . "\nspeed | Service status | Current status of customer-facing services. | #status\nmail | Contact support | Prefer email or chat? All channels in one place. | {$contact}" ) ),
			array( 'pall_split', array( 'id' => 'remote', 'eyebrow' => 'Remote support', 'title' => 'Fixed *remotely*, often within minutes', 'lottie' => $lottie, 'background' => 'alt', 'text' => 'Once you open a ticket, an engineer can start a secure remote session with your permission. You see everything we do, and the session ends when the issue is resolved.', 'bullets' => "Permission requested every time\nEncrypted connection\nSession notes added to your ticket\nNo software left running afterwards", 'button_text' => 'Request a remote session', 'button_url' => $contact ) ),
			array( 'pall_features', array( 'id' => 'status', 'eyebrow' => 'Service status', 'title' => 'All systems *operational*', 'lead' => 'Demo status board — connect your real status page or monitoring tool here.', 'style' => 'trust', 'columns' => 3, 'items' => "check | Customer portal | Operational\ncheck | Managed monitoring | Operational\ncheck | Hosting platform | Operational\ncheck | Backup service | Operational\ncheck | Email & ticketing | Operational\ncheck | Emergency line | Operational" ) ),
			array( 'pall_faq', array( 'eyebrow' => 'Self-service', 'title' => 'Support FAQ', 'category' => 'support', 'align' => 'center', 'background' => 'alt' ) ),
			array( 'pall_contact', array( 'map' => 'no' ) ),
		) );

		$this->page( 'careers', $pg['careers'][0], $pg['careers'][1], array(
			array( 'pall_split', array( 'priority' => 'yes', 'eyebrow' => 'Why work with us', 'title' => 'Real infrastructure. *Real impact.*', 'image' => $this->media_setting( $this->image( 'careers-team', 'cover-page' ) ), 'text' => 'Work on live networks, data centers and cloud platforms for organisations that depend on them. We hire for curiosity and ownership, and we invest in your growth.', 'bullets' => "Certification and training budget\nLab equipment to experiment with\nMentoring from senior engineers\nClear career paths" ) ),
			array( 'pall_features', array( 'eyebrow' => 'Benefits', 'title' => 'What we *offer*', 'columns' => 3, 'background' => 'alt', 'items' => "education | Learning budget | Annual budget for certifications, courses and conferences.\nbuilding | Flexible working | Hybrid working with core collaboration hours.\nhealth | Health cover | Health insurance for you and your family.\nchart | Annual bonus | Shared success through a transparent bonus scheme.\nlayers | Modern tools | Good laptops, lab hardware and the software you need.\nusers | Supportive team | Peer reviews, pairing and a no-blame culture." ) ),
			array( 'pall_split', array( 'eyebrow' => 'Culture', 'title' => 'Craft, clarity and *calm*', 'image' => $this->media_setting( $this->image( 'careers-culture', 'cover-page' ) ), 'reverse' => 'yes', 'text' => 'We value well-documented work, honest communication and steady improvement. Incidents are learning opportunities, not blame sessions.' ) ),
			array( 'pall_jobs', array( 'eyebrow' => 'Open positions', 'title' => 'Current *openings*' ) ),
			array( 'pall_cta', array( 'heading' => 'Don’t see your role? *Get in touch*', 'text' => 'We are always happy to hear from talented engineers. Send your CV and tell us what you would like to work on.', 'button' => 'Send your CV', 'button_url' => 'mailto:' . (string) pallcore_setting( 'careers_email' ), 'show_phone' => 'no' ) ),
		) );

		$sample = get_page_by_path( 'sample-page' );
		if ( $sample && 'publish' === $sample->post_status && str_contains( $sample->post_content, 'This is an example page' ) ) {
			wp_update_post( array( 'ID' => $sample->ID, 'post_status' => 'draft' ) );
			$this->log[] = '• The WordPress "Sample Page" was moved to Drafts.';
		}
		$blog_id = $this->post( array( 'post_type' => 'page', 'post_title' => $pg['blog'][0], 'post_name' => 'blog', 'post_excerpt' => $pg['blog'][1] ) );
		$this->pages['blog'] = $blog_id;

		$company = 'TechNova Systems';
		$legal   = array(
			'privacy-policy' => '<p><strong>Template — review with your legal advisor before publishing.</strong></p><h2>What we collect</h2><p>' . $company . ' collects the information you provide when you contact us, subscribe, create an account or place an order (name, email, phone, company, billing and shipping details), and technical data such as IP address and browser type.</p><h2>How we use it</h2><p>To answer enquiries, deliver products and services, process payments, provide support, improve our website and — only with your consent — send newsletters.</p><h2>Sharing</h2><p>We share data only with service providers who help us operate (payment processors, carriers, hosting) and when required by law. We never sell personal data.</p><h2>Your rights</h2><p>You can request access, correction, export or deletion of your personal data at any time by contacting us.</p><h2>Retention & security</h2><p>We keep data only as long as necessary and protect it with appropriate security controls.</p>',
			'terms'          => '<p><strong>Template — review with your legal advisor before publishing.</strong></p><h2>Use of this website</h2><p>By using this website you agree to these terms. Content is provided for general information and may change without notice.</p><h2>Orders & pricing</h2><p>Orders are subject to acceptance and availability. Prices and specifications are provided in good faith; obvious errors may be corrected.</p><h2>Warranty</h2><p>Hardware is covered by the warranty stated on each product page unless an extended warranty is purchased.</p><h2>Liability</h2><p>To the extent permitted by law, ' . $company . ' is not liable for indirect or consequential loss.</p><h2>Governing law</h2><p>These terms are governed by the laws of the jurisdiction in which ' . $company . ' is registered.</p>',
			'cookie-policy'  => '<p><strong>Template — review with your legal advisor before publishing.</strong></p><h2>What are cookies?</h2><p>Small text files stored on your device that help websites work and remember preferences.</p><h2>Cookies we use</h2><ul><li><strong>Essential:</strong> cart, checkout, login and security.</li><li><strong>Preferences:</strong> your light/dark appearance choice and recently viewed products (stored in your browser).</li><li><strong>Analytics (optional):</strong> only with your consent.</li></ul><h2>Managing cookies</h2><p>You can block or delete cookies in your browser settings. Blocking essential cookies may break the cart and checkout.</p>',
			'refund-policy'  => '<p><strong>Template — review with your legal advisor before publishing.</strong></p><h2>Returns</h2><p>Unopened hardware can be returned within 14 days of delivery. Contact support for a return authorisation before shipping.</p><h2>Faulty items</h2><p>Items that arrive faulty are repaired, replaced or refunded after inspection. Later warranty claims follow the warranty on the product page.</p><h2>Software, licences & services</h2><p>Electronically delivered licences are non-refundable once issued. Professional services can be cancelled free of charge before work is scheduled.</p><h2>Refunds</h2><p>Approved refunds are issued to the original payment method within 10 business days.</p>',
		);
		foreach ( $legal as $slug => $html ) {
			$existing_privacy = (int) get_option( 'wp_page_for_privacy_policy' );
			if ( 'privacy-policy' === $slug && $existing_privacy && get_post_status( $existing_privacy ) ) {
				if ( 'draft' === get_post_status( $existing_privacy ) && str_contains( (string) get_post_field( 'post_content', $existing_privacy ), 'Suggested text:' ) ) {
					wp_update_post( array( 'ID' => $existing_privacy, 'post_content' => $html, 'post_status' => 'publish' ) );
					update_post_meta( $existing_privacy, '_pall_demo', 1 );
					$this->log[] = '• The draft Privacy Policy page WordPress created was filled with the template and published.';
				}
				$this->pages[ $slug ] = $existing_privacy;
				continue;
			}
			$this->page( $slug, $pg[ $slug ][0], '', array( array( 'html', $html ) ) );
		}
		$this->page( 'media-credits', $pg['media-credits'][0], $pg['media-credits'][1], array(
			array( 'html', '<p>Most artwork on this website (illustrations, product images, logos and icons) is original. Third-party photos and videos are listed below with their source and licence. These licences do not require attribution, but we credit every creator.</p>' ),
			array( 'pall_media_credits', array( 'spacing' => 'tight' ) ),
		) );

		// Homepage last: it shows content from everything above.
		$home = array(
			array( 'pall_hero', array( 'stats' => 'yes' ) ),
			array( 'pall_features', array( 'style' => 'trust', 'columns' => 3 ) ),
			array( 'pall_clients', array( 'eyebrow' => 'Trusted by teams across industries', 'align' => 'center', 'spacing' => 'tight', 'lead' => 'Logos shown are fictional demo brands.' ) ),
			array( 'pall_services', array( 'eyebrow' => 'What we do', 'title' => 'End-to-end *IT services* for modern businesses', 'lead' => 'From the network edge to the cloud, our engineers design, deploy and run the technology your business depends on.', 'count' => 6, 'link_text' => 'All 12 services', 'link_url' => home_url( '/services/' ) ) ),
			array( 'pall_split', array( 'eyebrow' => 'Why TechNova', 'title' => 'Infrastructure that is *engineered*, not improvised', 'image' => $this->media_setting( $this->image( 'home-why', 'cover-data-center' ) ), 'background' => 'alt', 'text' => 'We combine vendor-neutral design with disciplined delivery: staged changes, tested rollbacks and documentation your team can use. Then we stay — monitoring, patching and planning what comes next.', 'bullets' => "Vendor-neutral architecture\nChange-controlled delivery\n24/7 monitoring for managed customers\nClear, honest reporting", 'button_text' => 'Talk to an Expert', 'button_url' => $contact, 'button2_text' => 'View Projects', 'button2_url' => home_url( '/case-studies/' ), 'badge_value' => '15+', 'badge_label' => 'Years of experience' ) ),
			array( 'pall_solutions', array( 'eyebrow' => 'Industry solutions', 'title' => 'Built for the way *your industry* works', 'count' => 6, 'link_text' => 'All solutions', 'link_url' => home_url( '/solutions/' ) ) ),
			array( 'pall_products', array( 'eyebrow' => 'Technology store', 'title' => 'Enterprise hardware, *ready to ship*', 'show' => 'featured', 'count' => 8, 'background' => 'alt', 'link_text' => 'View Products', 'link_url' => home_url( '/shop/' ) ) ),
			array( 'pall_process', array( 'eyebrow' => 'How we work', 'title' => 'A proven delivery *process*' ) ),
			array( 'pall_case_studies', array( 'eyebrow' => 'Projects', 'title' => 'Results that *speak for themselves*', 'lead' => 'Demo case studies with fictional clients.', 'background' => 'alt', 'link_text' => 'All projects', 'link_url' => home_url( '/case-studies/' ) ) ),
			array( 'pall_technologies', array( 'eyebrow' => 'Technology stack', 'title' => 'Vendor-neutral expertise across *leading platforms*', 'align' => 'center' ) ),
			array( 'pall_testimonials', array( 'eyebrow' => 'Testimonials', 'title' => 'What our *clients* say', 'lead' => 'Demo testimonials from fictional customers.', 'background' => 'alt' ) ),
			array( 'pall_posts', array( 'eyebrow' => 'Insights', 'title' => 'Latest from our *engineers*', 'link_text' => 'Read the blog', 'link_url' => home_url( '/blog/' ) ) ),
			array( 'pall_faq', array( 'eyebrow' => 'FAQ', 'title' => 'Questions, *answered*', 'category' => 'general', 'align' => 'center', 'background' => 'alt' ) ),
			array( 'pall_cta', array() ),
		);
		$home_id = $this->page( 'home', 'Home', '', $home, 'templates/template-full-width.php' );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
		update_option( 'page_for_posts', $blog_id );
		if ( ! empty( $this->pages['privacy-policy'] ) ) {
			update_option( 'wp_page_for_privacy_policy', $this->pages['privacy-policy'] );
		}
		if ( function_exists( 'wc_get_page_id' ) && ! empty( $this->pages['terms'] ) ) {
			update_option( 'woocommerce_terms_page_id', $this->pages['terms'] );
		}
		$this->log[] = '✔ Pages: ' . count( $this->pages ) . ' (Home, About, Pricing, FAQ, Contact, Support, Careers, Blog, legal pages, Media Credits)' . ( defined( 'ELEMENTOR_VERSION' ) ? ' with Elementor layouts' : ' — activate Elementor and re-import to add Elementor layouts' );
	}

	/* ------------------------------------------------------------------
	 * Menus, widgets, options
	 * ---------------------------------------------------------------- */

	/**
	 * Create a demo menu unless one with that name exists.
	 *
	 * @param string $name     Menu name.
	 * @param string $location Theme location.
	 * @param array  $items    Items: [title, target, children[], classes, description].
	 */
	private function menu( string $name, string $location, array $items ): void {
		$existing = wp_get_nav_menu_object( $name );
		if ( $existing ) {
			$locations = get_theme_mod( 'nav_menu_locations', array() );
			if ( empty( $locations[ $location ] ) ) {
				$locations[ $location ] = $existing->term_id;
				set_theme_mod( 'nav_menu_locations', $locations );
			}
			return;
		}
		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			return;
		}
		update_term_meta( $menu_id, '_pall_demo', 1 );
		$this->add_items( $menu_id, $items, 0 );
		$locations              = get_theme_mod( 'nav_menu_locations', array() );
		$locations[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Recursively add menu items.
	 *
	 * @param int   $menu_id Menu.
	 * @param array $items   Items.
	 * @param int   $parent  Parent item.
	 */
	private function add_items( int $menu_id, array $items, int $parent ): void {
		foreach ( $items as $item ) {
			$target = $item[1];
			$args   = array(
				'menu-item-title'       => $item[0],
				'menu-item-status'      => 'publish',
				'menu-item-parent-id'   => $parent,
				'menu-item-classes'     => $item[3] ?? '',
				'menu-item-description' => $item[4] ?? '',
			);
			if ( is_array( $target ) && in_array( $target[0], array( 'page', 'post' ), true ) ) {
				if ( empty( $target[1] ) ) {
					continue;
				}
				$args += array( 'menu-item-type' => 'post_type', 'menu-item-object' => get_post_type( $target[1] ), 'menu-item-object-id' => $target[1] );
			} elseif ( is_array( $target ) && 'term' === $target[0] ) {
				if ( empty( $target[1] ) ) {
					continue;
				}
				$args += array( 'menu-item-type' => 'taxonomy', 'menu-item-object' => $target[2], 'menu-item-object-id' => $target[1] );
			} elseif ( is_array( $target ) && 'archive' === $target[0] ) {
				$args += array( 'menu-item-type' => 'post_type_archive', 'menu-item-object' => $target[1] );
			} else {
				$args += array( 'menu-item-type' => 'custom', 'menu-item-url' => (string) $target );
			}
			$item_id = wp_update_nav_menu_item( $menu_id, 0, $args );
			if ( ! is_wp_error( $item_id ) && ! empty( $item[2] ) ) {
				$this->add_items( $menu_id, $item[2], (int) $item_id );
			}
		}
	}

	/**
	 * Build all menus.
	 */
	private function menus(): void {
		$svc  = fn( $slug, $desc = '' ) => array( get_the_title( $this->services[ $slug ] ?? 0 ), array( 'post', $this->services[ $slug ] ?? 0 ), array(), '', $desc );
		$sol  = fn( $slug, $label ) => array( $label, array( 'post', $this->solutions[ $slug ] ?? 0 ) );
		$cat  = static function ( $name, $label = '' ) {
			$t = get_term_by( 'name', $name, 'product_cat' );
			return array( $label ?: $name, array( 'term', $t ? $t->term_id : 0, 'product_cat' ) );
		};
		$page = fn( $k ) => array( 'page', $this->pages[ $k ] ?? 0 );
		$shop = function_exists( 'wc_get_page_id' ) ? array( 'page', wc_get_page_id( 'shop' ) ) : home_url( '/' );
		$tut  = get_term_by( 'slug', 'tutorials', 'category' );
		$news = get_term_by( 'slug', 'technology-news', 'category' );

		$services_mega = array(
			array( 'Infrastructure', '#', array( $svc( 'network-infrastructure', 'Campus, data center & WAN' ), $svc( 'server-solutions', 'Rack, tower & HCI' ), $svc( 'cloud-computing', 'Public, private & hybrid' ), $svc( 'data-center-solutions', 'Power, cooling & cabling' ) ) ),
			array( 'Security & Operations', '#', array( $svc( 'cybersecurity', 'Assessments, firewalls & monitoring' ), $svc( 'managed-it-services', 'Monitoring & helpdesk' ), $svc( 'backup-disaster-recovery', 'Immutable backups & DR' ), $svc( 'vps-hosting', 'NVMe VPS & managed hosting' ) ) ),
			array( 'Software & AI', '#', array( $svc( 'software-development', 'Portals, APIs & integrations' ), $svc( 'ai-solutions', 'Private AI & GPU infrastructure' ), $svc( 'iot-solutions', 'Sensors, edge & dashboards' ), $svc( 'it-consultancy', 'Strategy & architecture' ) ) ),
		);
		$solutions_mega = array(
			array( 'Connectivity', '#', array( $sol( 'isp-network-solutions', 'ISP' ), $sol( 'telecom-infrastructure', 'Telecom' ), $sol( 'data-centers-colocation', 'Data Centers' ) ) ),
			array( 'Organisations', '#', array( $sol( 'enterprise-it', 'Enterprise' ), $sol( 'education-campus', 'Education' ), $sol( 'healthcare-it', 'Healthcare' ) ) ),
			array( 'Regulated & industry', '#', array( $sol( 'banking-financial-services', 'Banking' ), $sol( 'government-public-sector', 'Government' ), $sol( 'retail-ecommerce', 'Retail' ), $sol( 'manufacturing-industry-4-0', 'Manufacturing' ) ) ),
		);
		$products_mega = array(
			array( 'Servers', $cat( 'Servers' )[1], array( $cat( 'Servers', 'Rack & tower servers' ), $cat( 'CPUs' ), $cat( 'GPUs' ), $cat( 'RAM', 'Server memory' ) ) ),
			array( 'Networking', $cat( 'Networking' )[1], array( $cat( 'Switches' ), $cat( 'Routers' ), $cat( 'Network Cards' ), $cat( 'Transceivers' ), $cat( 'Fiber Optics' ) ) ),
			array( 'Storage', $cat( 'Storage' )[1], array( $cat( 'SSD', 'SSD & NVMe' ), $cat( 'HDD', 'Hard drives' ), $cat( 'Storage', 'Backup appliances' ) ) ),
			array( 'Security', $cat( 'Firewalls' )[1], array( $cat( 'Firewalls' ), $cat( 'Licenses', 'Security & software licences' ) ) ),
			array( 'Accessories', $cat( 'Accessories' )[1], array( $cat( 'Racks' ), $cat( 'UPS', 'UPS & power' ), $cat( 'Accessories' ), $cat( 'Support', 'Support & services' ) ) ),
		);

		$this->menu( 'Primary Menu', 'primary', array(
			array( 'Home', home_url( '/' ) ),
			array( 'About', $page( 'about' ), array( array( 'About Us', $page( 'about' ) ), array( 'Our Team', array( 'archive', 'pall_team' ) ), array( 'Pricing', $page( 'pricing' ) ), array( 'Careers', $page( 'careers' ) ), array( 'FAQ', $page( 'faq' ) ) ) ),
			array( 'Services', array( 'archive', 'pall_service' ), $services_mega, 'mega' ),
			array( 'Solutions', array( 'archive', 'pall_solution' ), $solutions_mega, 'mega' ),
			array( 'Products', $shop, class_exists( 'WooCommerce' ) ? $products_mega : array(), 'mega' ),
			array( 'Projects', array( 'archive', 'pall_case_study' ) ),
			array( 'Blog', $page( 'blog' ) ),
			array( 'Contact', $page( 'contact' ) ),
		) );
		$this->menu( 'Top Bar', 'topbar', array( array( 'Support', $page( 'support' ) ), array( 'Careers', $page( 'careers' ) ) ) );
		$this->menu( 'Footer: Company', 'footer_company', array( array( 'About Us', $page( 'about' ) ), array( 'Projects', array( 'archive', 'pall_case_study' ) ), array( 'Our Team', array( 'archive', 'pall_team' ) ), array( 'Careers', $page( 'careers' ) ), array( 'Pricing', $page( 'pricing' ) ) ) );
		$this->menu( 'Footer: Services', 'footer_services', array( $svc( 'network-infrastructure' ), $svc( 'cloud-computing' ), $svc( 'cybersecurity' ), $svc( 'managed-it-services' ), $svc( 'data-center-solutions' ), array( 'All services', array( 'archive', 'pall_service' ) ) ) );
		$this->menu( 'Footer: Solutions', 'footer_solutions', array( $sol( 'isp-network-solutions', 'ISP' ), $sol( 'enterprise-it', 'Enterprise' ), $sol( 'healthcare-it', 'Healthcare' ), $sol( 'banking-financial-services', 'Banking' ), $sol( 'government-public-sector', 'Government' ), array( 'All solutions', array( 'archive', 'pall_solution' ) ) ) );
		if ( class_exists( 'WooCommerce' ) ) {
			$this->menu( 'Footer: Products', 'footer_products', array( $cat( 'Servers' ), $cat( 'Networking' ), $cat( 'Storage' ), $cat( 'Firewalls', 'Security' ), $cat( 'Accessories' ), array( 'All products', $shop ) ) );
		}
		$this->menu( 'Footer: Support', 'footer_support', array( array( 'Support Center', $page( 'support' ) ), array( 'FAQ', $page( 'faq' ) ), array( 'Contact', $page( 'contact' ) ), array( 'My Account', function_exists( 'wc_get_page_id' ) ? array( 'page', wc_get_page_id( 'myaccount' ) ) : home_url( '/' ) ), array( 'Service Status', home_url( '/support/#status' ) ) ) );
		$this->menu( 'Footer: Resources', 'footer_resources', array_values( array_filter( array( array( 'Blog', $page( 'blog' ) ), $tut ? array( 'Tutorials', array( 'term', $tut->term_id, 'category' ) ) : null, $news ? array( 'Technology News', array( 'term', $news->term_id, 'category' ) ) : null, array( 'Case Studies', array( 'archive', 'pall_case_study' ) ), array( 'Documentation', $page( 'faq' ) ) ) ) ) );
		$this->menu( 'Footer: Legal', 'footer_legal', array( array( 'Privacy Policy', $page( 'privacy-policy' ) ), array( 'Terms & Conditions', $page( 'terms' ) ), array( 'Refund Policy', $page( 'refund-policy' ) ), array( 'Cookie Policy', $page( 'cookie-policy' ) ), array( 'Media Credits', $page( 'media-credits' ) ) ) );
		$this->log[] = '✔ Menus: primary (Home, About, Services, Solutions, Products, Projects, Blog, Contact — 3 mega menus), top bar, 7 footer menus';
	}

	/**
	 * Sidebar widgets.
	 */
	private function widgets(): void {
		$sidebars = (array) get_option( 'sidebars_widgets', array() );
		$shop     = (array) ( $sidebars['sidebar-shop'] ?? array() );
		if ( ! preg_grep( '/^pallcore_filters-/', $shop ) ) {
			$instances                 = (array) get_option( 'widget_pallcore_filters', array() );
			$next                      = max( array_merge( array( 1 ), array_filter( array_keys( $instances ), 'is_int' ) ) ) + 1;
			$instances[ $next ]        = array( 'categories' => 1, 'brands' => 1, 'price' => 1, 'availability' => 1, 'rating' => 1, 'attributes' => 1, 'exclude' => '' );
			$instances['_multiwidget'] = 1;
			update_option( 'widget_pallcore_filters', $instances );
			array_unshift( $shop, 'pallcore_filters-' . $next );
			$sidebars['sidebar-shop'] = $shop;
			$this->log[] = '✔ Widgets: product filters added to the shop sidebar';
		}
		if ( empty( $sidebars['sidebar-blog'] ) ) {
			update_option( 'widget_search', array( 2 => array( 'title' => '' ), '_multiwidget' => 1 ) );
			update_option( 'widget_recent-posts', array( 2 => array( 'title' => 'Recent articles', 'number' => 5 ), '_multiwidget' => 1 ) );
			update_option( 'widget_categories', array( 2 => array( 'title' => 'Categories', 'count' => 1 ), '_multiwidget' => 1 ) );
			$sidebars['sidebar-blog'] = array( 'search-2', 'recent-posts-2', 'categories-2' );
			$this->log[] = '✔ Widgets: blog sidebar (search, recent articles, categories)';
		}
		update_option( 'sidebars_widgets', $sidebars );
	}

	/**
	 * Theme options (Customizer) + Elementor kit colors.
	 */
	private function theme_options(): void {
		$mods = array(
			'header_style'       => 'transparent',
			'header_cta_text'    => 'Get a Quote',
			'header_cta_url'     => get_permalink( $this->pages['contact'] ?? 0 ) ?: '',
			'footer_description' => 'TechNova Systems delivers enterprise infrastructure, networking, cloud, cybersecurity and managed IT for businesses of all sizes. (Fictional demo company.)',
			'footer_layout'      => 'mega',
			'footer_copyright'   => '© {year} {site}. All Rights Reserved.',
		);
		if ( isset( $this->media['logo-technova'] ) ) {
			$mods['custom_logo'] = $this->media['logo-technova'];
		}
		if ( isset( $this->media['logo-technova-dark'] ) ) {
			$mods['logo_light'] = $this->media['logo-technova-dark'];
			$mods['logo_dark']  = $this->media['logo-technova-dark'];
		}
		foreach ( $mods as $key => $value ) {
			if ( $this->first_run || ! get_theme_mod( $key ) ) {
				set_theme_mod( $key, $value );
			}
		}
		if ( function_exists( 'palltheme_sync_elementor_kit' ) ) {
			palltheme_sync_elementor_kit();
		}
		$this->log[] = '✔ Theme options: logo, transparent header, Get a Quote button, enterprise footer, Elementor global colors';
	}

	/* ------------------------------------------------------------------
	 * Removal
	 * ---------------------------------------------------------------- */

	/**
	 * Delete everything tagged as demo.
	 *
	 * @return string[] Log.
	 */
	public static function remove(): array {
		$count = 0;
		$types = array_merge( array( 'post', 'page', 'attachment', 'product', 'product_variation' ), pallcore_post_types() );
		$ids   = get_posts( array( 'post_type' => $types, 'post_status' => 'any', 'meta_key' => '_pall_demo', 'posts_per_page' => -1, 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		foreach ( $ids as $id ) {
			if ( 'product' === get_post_type( $id ) && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $id );
				if ( $product && $product->is_type( 'variable' ) ) {
					foreach ( $product->get_children() as $child ) {
						wp_delete_post( $child, true );
					}
				}
			}
			if ( 'attachment' === get_post_type( $id ) ) {
				wp_delete_attachment( $id, true );
			} else {
				wp_delete_post( $id, true );
			}
			++$count;
		}
		$terms = get_terms( array( 'taxonomy' => get_taxonomies(), 'hide_empty' => false, 'meta_key' => '_pall_demo' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( 'nav_menu' === $term->taxonomy ) {
				wp_delete_nav_menu( $term->term_id );
			} else {
				wp_delete_term( $term->term_id, $term->taxonomy );
			}
		}
		delete_option( 'pallcore_demo_imported' );
		// Logo/hero settings that pointed at deleted demo images would render as broken images.
		foreach ( array( 'custom_logo', 'logo_light', 'logo_dark' ) as $mod ) {
			$value = (int) get_theme_mod( $mod );
			if ( $value && ! get_post( $value ) ) {
				remove_theme_mod( $mod );
			}
		}
		$settings = (array) get_option( 'pallcore_settings', array() );
		foreach ( array( 'hero_image', 'hero_poster' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) && is_numeric( $settings[ $key ] ) && ! get_post( (int) $settings[ $key ] ) ) {
				$settings[ $key ] = '';
			}
		}
		foreach ( array( 'hero_video_webm', 'hero_video_mp4' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) && str_contains( (string) $settings[ $key ], '/uploads/' ) && ! attachment_url_to_postid( (string) $settings[ $key ] ) ) {
				$settings[ $key ] = '';
			}
		}
		update_option( 'pallcore_settings', $settings );
		if ( 'yes' === get_option( 'woocommerce_demo_store' ) ) {
			update_option( 'woocommerce_demo_store', 'no' );
		}
		return array( sprintf( '✔ Removed %d demo items and %d demo terms/menus. Your settings were kept; the demo store notice was turned off.', $count, is_array( $terms ) ? count( $terms ) : 0 ) );
	}
}

/**
 * Admin page.
 */
function pallcore_render_demo_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$log = array();
	if ( isset( $_POST['pallcore_demo_action'] ) && check_admin_referer( 'pallcore_demo', 'pallcore_demo_nonce' ) ) {
		$action = sanitize_key( wp_unslash( $_POST['pallcore_demo_action'] ) );
		if ( 'import' === $action ) {
			$log = ( new Pallcore_Demo_Importer() )->run();
		} elseif ( 'remove' === $action ) {
			$log = Pallcore_Demo_Importer::remove();
		}
	}
	$imported  = get_option( 'pallcore_demo_imported' );
	$photo_dir = (string) apply_filters( 'pallcore_demo_photo_pack_dir', WP_CONTENT_DIR . '/palltheme-photo-pack' );
	$has_pack  = is_readable( trailingslashit( $photo_dir ) . 'manifest.json' );
	?>
	<div class="wrap pallcore-settings">
		<h1><?php esc_html_e( 'Import Demo — TechNova Systems', 'palltheme-core' ); ?></h1>
		<?php if ( $log ) : ?>
			<div class="pallcore-card"><h2><?php esc_html_e( 'Result', 'palltheme-core' ); ?></h2><div class="pallcore-log"><?php echo wp_kses_post( implode( '<br>', array_map( 'esc_html', $log ) ) ); ?></div><p><a class="button button-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank"><?php esc_html_e( 'View site', 'palltheme-core' ); ?></a></p></div>
		<?php endif; ?>
		<div class="pallcore-card">
			<h2><?php esc_html_e( 'What will be imported', 'palltheme-core' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Home, About, Pricing, FAQ, Contact, Support, Careers, Blog, legal pages and Media Credits — editable with Elementor', 'palltheme-core' ); ?></li>
				<li><?php esc_html_e( '12 services, 10 solutions, 6 case studies, 8 team members, 8 testimonials, 10 client logos, 16 technologies, 3 pricing plans, 22 FAQs, 5 jobs', 'palltheme-core' ); ?></li>
				<li><?php esc_html_e( '18 blog articles in 12 categories', 'palltheme-core' ); ?></li>
				<li><?php esc_html_e( 'WooCommerce: 21 categories, 9 filterable attributes, 42 products (simple, variable and virtual) with specifications, galleries, reviews, cross-sells and upsells', 'palltheme-core' ); ?></li>
				<li><?php esc_html_e( 'Mega menus, 7 footer menus, shop filters, logo, contact details and theme settings', 'palltheme-core' ); ?></li>
			</ul>
			<p><?php echo $has_pack ? esc_html__( 'Photo pack found: licensed photos will be imported with credits.', 'palltheme-core' ) : esc_html__( 'No photo pack found: original illustrations will be used for all images (you can add your own photos at any time).', 'palltheme-core' ); ?></p>
			<p><?php esc_html_e( 'Existing content is never deleted and re-running only adds what is missing. People, clients, testimonials and figures in the demo are fictional.', 'palltheme-core' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'pallcore_demo', 'pallcore_demo_nonce' ); ?>
				<button class="button button-primary button-hero" name="pallcore_demo_action" value="import"><?php echo $imported ? esc_html__( 'Re-run import (adds missing items)', 'palltheme-core' ) : esc_html__( 'Import demo website', 'palltheme-core' ); ?></button>
			</form>
		</div>
		<?php if ( $imported ) : ?>
			<div class="pallcore-card">
				<h2><?php esc_html_e( 'Remove demo content', 'palltheme-core' ); ?></h2>
				<p><?php esc_html_e( 'Deletes only items created by the importer (posts, pages, products, images, demo terms and menus). Content you created yourself is not touched.', 'palltheme-core' ); ?></p>
				<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete all demo content?', 'palltheme-core' ) ); ?>');">
					<?php wp_nonce_field( 'pallcore_demo', 'pallcore_demo_nonce' ); ?>
					<button class="button button-link-delete" name="pallcore_demo_action" value="remove"><?php esc_html_e( 'Remove demo content', 'palltheme-core' ); ?></button>
				</form>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * WP-CLI: `wp palltheme demo import|remove`.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'palltheme demo',
		static function ( $args ) {
			$action = $args[0] ?? 'import';
			$log    = 'remove' === $action ? Pallcore_Demo_Importer::remove() : ( new Pallcore_Demo_Importer() )->run();
			foreach ( $log as $line ) {
				WP_CLI::log( $line );
			}
			WP_CLI::success( 'Finished.' );
		}
	);
}
