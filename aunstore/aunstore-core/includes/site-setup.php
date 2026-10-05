<?php
/**
 * Tools → AUN Site Setup.
 *
 * One screen that builds the rest of the site from data/pages/:
 *   Pages   — creates/refreshes every page in pages.json (images copied from the BD site like products),
 *             sets the front page, the privacy page and the WooCommerce terms page.
 *   Menus   — "AUN Main Menu" (header + mobile) and "AUN Footer Links" (bottom bar).
 *   Footer  — the designed AUN footer (default), or four widgets in Flatsome's "Footer 2" area.
 *   Store   — currency USD.
 *
 * A page is only overwritten if it still holds exactly what setup wrote last time (tracked by a hash),
 * so edits made in WordPress are never lost; the screen shows those pages as "edited — skipped".
 * Pages marked "draft" in pages.json stay drafts until someone publishes them.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AUNStore_Site_Setup {

	const PAGE        = 'aunstore-site-setup';
	const HASH_META   = '_aunstore_page_hash';
	const MAIN_MENU   = 'AUN Main Menu';
	const FOOTER_MENU = 'AUN Footer Links';
	const WIDGETS_OPT = 'aunstore_footer_widget_ids';

	public static function init() {
		add_action( 'admin_menu', function () {
			add_management_page( 'AUN Site Setup', 'AUN Site Setup', 'manage_options', self::PAGE, array( __CLASS__, 'render_page' ) );
		} );
		add_action( 'admin_post_aunstore_site_setup', array( __CLASS__, 'handle' ) );
	}

	public static function manifest() {
		$m = json_decode( (string) @file_get_contents( AUNSTORE_CORE_DIR . 'data/pages/pages.json' ), true );
		return is_array( $m ) ? $m : array( 'pages' => array(), 'footer_widgets' => array(), 'copyright' => '' );
	}

	public static function find_page( $slug ) {
		$ids = get_posts( array(
			'post_type'      => 'page',
			'name'           => $slug,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		return $ids ? (int) $ids[0] : 0;
	}

	/* ── Pages ─────────────────────────────────────────────────────────── */

	/** @return string[] human-readable log lines */
	public static function setup_pages( $force = false ) {
		$log = array();
		foreach ( self::manifest()['pages'] as $p ) {
			$file = AUNSTORE_CORE_DIR . 'data/pages/' . $p['slug'] . '.txt';
			if ( ! is_file( $file ) ) {
				$log[] = "✗ {$p['title']}: content file missing";
				continue;
			}
			$errors  = array();
			$content = AUNStore_Importer::rewrite_ids( (string) file_get_contents( $file ), $errors );
			$hash    = md5( $content );
			$id      = self::find_page( $p['slug'] );

			if ( $id ) {
				$current = get_post( $id );
				$stored  = get_post_meta( $id, self::HASH_META, true );
				$edited  = $stored ? ( md5( $current->post_content ) !== $stored ) : ( '' !== trim( $current->post_content ) && 'privacy-policy' !== $p['slug'] );
				if ( $edited && ! $force ) {
					$log[] = "• {$p['title']}: edited in WordPress — left as it is";
					self::apply_role( $p, $id );
					continue;
				}
				$status = $current->post_status;
				// Publish pages meant to be live; never un-publish a page someone published.
				if ( 'publish' === $p['status'] ) $status = 'publish';
				wp_update_post( array( 'ID' => $id, 'post_title' => $p['title'], 'post_content' => $content, 'post_status' => $status ) );
				$verb = 'updated';
			} else {
				$id = wp_insert_post( array(
					'post_type'    => 'page',
					'post_title'   => $p['title'],
					'post_name'    => $p['slug'],
					'post_content' => $content,
					'post_status'  => $p['status'],
				), true );
				if ( is_wp_error( $id ) ) {
					$log[] = "✗ {$p['title']}: " . $id->get_error_message();
					continue;
				}
				$verb = 'created';
			}
			update_post_meta( $id, self::HASH_META, md5( get_post_field( 'post_content', $id ) ) );
			if ( ! empty( $p['template'] ) ) {
				update_post_meta( $id, '_wp_page_template', $p['template'] );
			}
			self::apply_role( $p, $id );
			$line = "✓ {$p['title']}: $verb (" . get_post_status( $id ) . ')';
			if ( $errors ) $line .= ' — ' . count( $errors ) . ' image problem(s): ' . implode( ' | ', array_unique( $errors ) );
			$log[] = $line;
		}

		foreach ( (array) ( self::manifest()['retired'] ?? array() ) as $slug ) {
			$id = self::find_page( $slug );
			if ( ! $id ) continue;
			$stored = get_post_meta( $id, self::HASH_META, true );
			if ( $stored && md5( get_post_field( 'post_content', $id ) ) === $stored ) {
				wp_trash_post( $id );
				$log[] = "✓ Removed the old /$slug/ page (moved to Trash)";
			} else {
				$log[] = "• /$slug/ is no longer used, but it was edited in WordPress — left as it is";
			}
		}
		return $log;
	}

	private static function apply_role( $p, $id ) {
		switch ( $p['role'] ?? '' ) {
			case 'front':
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $id );
				break;
			case 'privacy':
				update_option( 'wp_page_for_privacy_policy', $id );
				break;
			case 'terms':
				update_option( 'woocommerce_terms_page_id', $id );
				break;
		}
	}

	/* ── Menus ─────────────────────────────────────────────────────────── */

	private static function reset_menu( $name ) {
		$menu = wp_get_nav_menu_object( $name );
		$id   = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu( $name );
		foreach ( (array) wp_get_nav_menu_items( $id, array( 'post_status' => 'any' ) ) as $item ) {
			wp_delete_post( $item->ID, true );
		}
		return $id;
	}

	/** Add a page item if the page is published. @return int item id or 0 */
	private static function add_page_item( $menu, $slug, $title = '', $parent = 0 ) {
		$id = self::find_page( $slug );
		if ( ! $id || 'publish' !== get_post_status( $id ) ) return 0;
		return (int) wp_update_nav_menu_item( $menu, 0, array(
			'menu-item-title'     => $title ?: get_the_title( $id ),
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $id,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent,
		) );
	}

	private static function add_cat_item( $menu, $slug, $title, $parent = 0 ) {
		$term = taxonomy_exists( 'product_cat' ) ? get_term_by( 'slug', $slug, 'product_cat' ) : false;
		$args = $term
			? array( 'menu-item-object' => 'product_cat', 'menu-item-object-id' => $term->term_id, 'menu-item-type' => 'taxonomy' )
			: array( 'menu-item-type' => 'custom', 'menu-item-url' => home_url( '/product-category/' . $slug . '/' ) );
		return (int) wp_update_nav_menu_item( $menu, 0, $args + array(
			'menu-item-title'     => $title,
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent,
		) );
	}

	public static function setup_menus() {
		$log  = array();
		$main = self::reset_menu( self::MAIN_MENU );

		$proj = self::add_cat_item( $main, 'projectors', 'Projectors' );
		self::add_cat_item( $main, 'home-theater-projector', 'Home Theater', $proj );
		self::add_cat_item( $main, 'office-projector', 'Office & Classroom', $proj );
		self::add_cat_item( $main, 'mini-projector', 'Portable & Mini', $proj );
		self::add_cat_item( $main, 'projectors', 'All Projectors', $proj );
		self::add_page_item( $main, 'projector-finder', 'Smart Finder' );
		$support = self::add_page_item( $main, 'warranty', 'Support' );
		if ( $support ) {
			self::add_page_item( $main, 'warranty', 'Warranty & Support', $support );
			self::add_page_item( $main, 'verify-authenticity', '', $support );
			self::add_page_item( $main, 'shipping-policy', '', $support );
			self::add_page_item( $main, 'returns-and-refunds', '', $support );
			self::add_page_item( $main, 'contact-us', '', $support );
		}
		self::add_page_item( $main, 'about-us', 'About' );
		$log[] = '✓ ' . self::MAIN_MENU . ': ' . count( (array) wp_get_nav_menu_items( $main ) ) . ' items';

		$footer = self::reset_menu( self::FOOTER_MENU );
		self::add_page_item( $footer, 'privacy-policy' );
		self::add_page_item( $footer, 'terms-and-conditions' );
		self::add_page_item( $footer, 'shipping-policy' );
		$log[] = '✓ ' . self::FOOTER_MENU . ': ' . count( (array) wp_get_nav_menu_items( $footer ) ) . ' items';

		$registered = get_registered_nav_menus();
		$locations  = (array) get_theme_mod( 'nav_menu_locations', array() );
		$assigned   = array();
		foreach ( array( 'primary' => $main, 'primary_mobile' => $main, 'footer' => $footer ) as $loc => $menu ) {
			if ( isset( $registered[ $loc ] ) ) {
				$locations[ $loc ] = $menu;
				$assigned[]        = $loc;
			}
		}
		set_theme_mod( 'nav_menu_locations', $locations );
		$log[] = $assigned
			? '✓ Menu locations set: ' . implode( ', ', $assigned )
			: '• This theme has no primary/footer menu locations — assign the menus in Appearance → Menus';
		return $log;
	}

	/* ── Footer ────────────────────────────────────────────────────────── */

	public static function setup_footer() {
		global $wp_registered_sidebars;
		$log      = array();
		$manifest = self::manifest();
		$sidebar  = 'sidebar-footer-2';
		$designed = function_exists( 'aunstore_designed_footer_enabled' ) && aunstore_designed_footer_enabled();

		// Always clear the widgets an earlier run added (and only those).
		$instances = get_option( 'widget_custom_html', array() );
		if ( ! is_array( $instances ) ) $instances = array();
		$sidebars = wp_get_sidebars_widgets();
		foreach ( (array) get_option( self::WIDGETS_OPT, array() ) as $old ) {
			foreach ( $sidebars as $area => $ids ) {
				if ( is_array( $ids ) ) $sidebars[ $area ] = array_values( array_diff( $ids, array( $old ) ) );
			}
			unset( $instances[ (int) str_replace( 'custom_html-', '', $old ) ] );
		}
		$added = array();

		if ( $designed ) {
			// The logo shown in the designed footer (same image the product pages use).
			$logo = AUNStore_Importer::local_attachment( 1557 );
			if ( ! is_wp_error( $logo ) ) update_option( 'aunstore_logo_id', (int) $logo );
			$log[] = '✓ Designed footer is on (change it in Settings → AUN Store Details → Footer)';
		} elseif ( ! isset( $wp_registered_sidebars[ $sidebar ] ) ) {
			$log[] = '• Flatsome "Footer 2" widget area not found — activate Flatsome and run Footer again';
		} else {
			$next = max( array_merge( array( 1 ), array_filter( array_keys( $instances ), 'is_int' ) ) ) + 1;
			foreach ( $manifest['footer_widgets'] as $w ) {
				$instances[ $next ] = array( 'title' => $w['title'], 'content' => $w['content'] );
				$added[]            = 'custom_html-' . $next;
				$next++;
			}
			$sidebars[ $sidebar ] = array_merge( $added, isset( $sidebars[ $sidebar ] ) && is_array( $sidebars[ $sidebar ] ) ? $sidebars[ $sidebar ] : array() );
			$log[]                = '✓ Footer 2: ' . count( $added ) . ' widgets (About, Shop, Support, Get in Touch)';
		}

		$instances['_multiwidget'] = 1;
		update_option( 'widget_custom_html', $instances );
		wp_set_sidebars_widgets( $sidebars );
		update_option( self::WIDGETS_OPT, $added );

		if ( 'flatsome' === get_template() && ! empty( $manifest['copyright'] ) ) {
			set_theme_mod( 'footer_left_text', $manifest['copyright'] );
		}
		return $log;
	}

	/* ── Store ─────────────────────────────────────────────────────────── */

	public static function setup_store() {
		if ( ! class_exists( 'WooCommerce' ) ) return array( '• WooCommerce is not active — store settings skipped' );
		update_option( 'woocommerce_currency', 'USD' );
		return array( '✓ Store currency: USD' );
	}

	/* ── Admin screen ──────────────────────────────────────────────────── */

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
		check_admin_referer( 'aunstore_site_setup' );
		if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 300 );

		$steps = isset( $_POST['steps'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['steps'] ) ) : array();
		$force = ! empty( $_POST['force'] );
		$log   = array();
		if ( in_array( 'store', $steps, true ) )  $log = array_merge( $log, self::setup_store() );
		if ( in_array( 'pages', $steps, true ) )  $log = array_merge( $log, self::setup_pages( $force ) );
		if ( in_array( 'menus', $steps, true ) )  $log = array_merge( $log, self::setup_menus() );
		if ( in_array( 'footer', $steps, true ) ) $log = array_merge( $log, self::setup_footer() );
		if ( ! $log ) $log[] = 'Nothing selected.';

		set_transient( 'aunstore_setup_log_' . get_current_user_id(), $log, 300 );
		wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE ) );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$log = get_transient( 'aunstore_setup_log_' . get_current_user_id() );
		delete_transient( 'aunstore_setup_log_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1>AUN Site Setup</h1>
			<p>Builds the pages, menus and footer for aunstore.com. Safe to run again: pages you have edited in WordPress are left alone.</p>
			<p><strong>Tip:</strong> import the products first (Tools → AUN Product Import) so the menu can link the product categories.</p>
			<?php if ( $log ) : ?>
				<div class="notice notice-info"><p><?php echo implode( '<br>', array_map( 'esc_html', $log ) ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'aunstore_site_setup' ); ?>
				<input type="hidden" name="action" value="aunstore_site_setup">
				<fieldset style="margin:14px 0;line-height:2">
					<label><input type="checkbox" name="steps[]" value="store" checked> Store settings (currency USD)</label><br>
					<label><input type="checkbox" name="steps[]" value="pages" checked> Pages (<?php echo count( self::manifest()['pages'] ); ?>) + front page</label><br>
					<label><input type="checkbox" name="steps[]" value="menus" checked> Menus (header, mobile, footer links)</label><br>
					<label><input type="checkbox" name="steps[]" value="footer" checked> Footer</label><br>
					<label style="color:#b32d2e"><input type="checkbox" name="force" value="1"> Also overwrite pages I have edited (not recommended)</label>
				</fieldset>
				<?php submit_button( 'Run setup', 'primary', 'submit', false, array( 'onclick' => "this.value='Working…';" ) ); ?>
			</form>

			<h2 style="margin-top:30px">Pages</h2>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>Page</th><th>Address</th><th>Status</th></tr></thead>
				<tbody>
				<?php foreach ( self::manifest()['pages'] as $p ) :
					$id = self::find_page( $p['slug'] ); ?>
					<tr>
						<td><?php echo $id ? '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $p['title'] ) . '</a>' : esc_html( $p['title'] ); ?></td>
						<td><code>/<?php echo esc_html( $p['slug'] ); ?>/</code></td>
						<td><?php
							if ( ! $id ) {
								echo 'not created yet';
							} else {
								echo esc_html( get_post_status( $id ) );
								$stored = get_post_meta( $id, self::HASH_META, true );
								if ( $stored && md5( get_post_field( 'post_content', $id ) ) !== $stored ) echo ' · edited';
							}
						?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

AUNStore_Site_Setup::init();
