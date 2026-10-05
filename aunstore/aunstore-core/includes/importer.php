<?php
/**
 * Tools → AUN Product Import.
 *
 * Builds each global product from the bundled content in data/products/<slug>/:
 *   product.json     name, SKU, slug, categories, short description, gallery (BD attachment ids)
 *   description.txt  the Flatsome/UX Builder page, still using the BD site's attachment ids
 *   specs.html       the Specifications tab
 *
 * Images are downloaded once from aun-projector.com.bd (URLs in data/media-map.json), remembered
 * by their BD id (`_aunstore_src_id`), and every BD id in the content is rewritten to the new local id.
 *
 * Re-running a product refreshes its content, images and categories. Price, stock, status and
 * anything else edited in WooCommerce are never touched. New products are created as DRAFTS.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AUNStore_Importer {

	const PAGE = 'aunstore-import';

	public static function init() {
		add_action( 'admin_menu', function () {
			add_management_page( 'AUN Product Import', 'AUN Product Import', 'manage_options', self::PAGE, array( __CLASS__, 'render_page' ) );
		} );
		add_action( 'admin_post_aunstore_import', array( __CLASS__, 'handle_import' ) );
	}

	/** @return array<string,string> product key => folder path */
	public static function packages() {
		$out = array();
		foreach ( glob( AUNSTORE_CORE_DIR . 'data/products/*', GLOB_ONLYDIR ) ?: array() as $dir ) {
			if ( is_file( $dir . '/product.json' ) ) {
				$out[ basename( $dir ) ] = $dir;
			}
		}
		return $out;
	}

	public static function load_package( $key ) {
		$packages = self::packages();
		if ( ! isset( $packages[ $key ] ) ) {
			return new WP_Error( 'aunstore_missing', "Unknown product package: $key" );
		}
		$dir  = $packages[ $key ];
		$data = json_decode( (string) file_get_contents( $dir . '/product.json' ), true );
		if ( ! is_array( $data ) || empty( $data['sku'] ) || empty( $data['name'] ) ) {
			return new WP_Error( 'aunstore_bad_json', "product.json is missing or invalid for $key" );
		}
		$data['description'] = is_file( $dir . '/description.txt' ) ? (string) file_get_contents( $dir . '/description.txt' ) : '';
		$data['specs']       = is_file( $dir . '/specs.html' ) ? (string) file_get_contents( $dir . '/specs.html' ) : '';
		return $data;
	}

	public static function media_map() {
		static $map = null;
		if ( null === $map ) {
			$map = json_decode( (string) @file_get_contents( AUNSTORE_CORE_DIR . 'data/media-map.json' ), true );
			if ( ! is_array( $map ) ) $map = array();
		}
		return $map;
	}

	/* ── Images ─────────────────────────────────────────────────────────── */

	/** Local attachment id already imported for a BD attachment id, or 0. */
	public static function find_local_attachment( $bd_id ) {
		$ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_aunstore_src_id',
			'meta_value'     => (string) (int) $bd_id,
		) );
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Local attachment id for a BD attachment id, downloading it on first use.
	 * @return int|WP_Error
	 */
	public static function local_attachment( $bd_id ) {
		$bd_id    = (int) $bd_id;
		$override = self::override_file( $bd_id );
		$found    = self::find_local_attachment( $bd_id );
		if ( $found ) {
			if ( $override ) self::apply_override( $found, $override );
			return $found;
		}

		$map = self::media_map();
		if ( empty( $map[ (string) $bd_id ] ) ) {
			return new WP_Error( 'aunstore_unmapped', "Image #$bd_id is not in media-map.json" );
		}
		$id = self::sideload( $map[ (string) $bd_id ], '_aunstore_src_id', (string) $bd_id, "Image #$bd_id" );
		if ( $override && ! is_wp_error( $id ) ) self::apply_override( $id, $override );
		return $id;
	}

	/**
	 * A corrected copy of a BD image bundled in data/media-overrides/<bd id>.<ext>, or ''.
	 * Used where the original file has a flaw (e.g. 8572: a stray selection frame that shows on dark backgrounds).
	 */
	private static function override_file( $bd_id ) {
		$files = glob( AUNSTORE_CORE_DIR . 'data/media-overrides/' . (int) $bd_id . '.*' );
		return $files ? $files[0] : '';
	}

	/** Replace an imported attachment's file with the bundled corrected copy (same attachment id, same URL). */
	private static function apply_override( $att_id, $override ) {
		$hash = md5_file( $override );
		if ( get_post_meta( $att_id, '_aunstore_override', true ) === $hash ) return;
		$file = get_attached_file( $att_id );
		if ( ! $file || ! @copy( $override, $file ) ) return;

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $file ) );
		update_post_meta( $att_id, '_aunstore_override', $hash );
	}

	/**
	 * Local URL for a file referenced by URL in the BD content (e.g. video_mp4="/wp-content/uploads/…").
	 * @return string|WP_Error
	 */
	public static function local_file_url( $bd_url ) {
		if ( 0 === strpos( $bd_url, '/' ) ) {
			$bd_url = 'https://aun-projector.com.bd' . $bd_url;
		}
		$ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_aunstore_src_url',
			'meta_value'     => $bd_url,
		) );
		$att_id = $ids ? (int) $ids[0] : self::sideload( $bd_url, '_aunstore_src_url', $bd_url, 'File ' . wp_basename( $bd_url ) );
		if ( is_wp_error( $att_id ) ) return $att_id;
		return wp_make_link_relative( wp_get_attachment_url( $att_id ) );
	}

	/** Download $url into the Media Library and tag it with $meta_key = $meta_value. @return int|WP_Error */
	private static function sideload( $url, $meta_key, $meta_value, $label ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error( 'aunstore_download', "$label could not be downloaded ($url): " . $tmp->get_error_message() );
		}
		$name = sanitize_file_name( rawurldecode( wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ) ) );
		if ( '' === pathinfo( $name, PATHINFO_FILENAME ) ) {
			$name = 'aun-media-' . md5( $url ) . '.' . ( pathinfo( $name, PATHINFO_EXTENSION ) ?: 'jpg' );
		}
		$att_id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), 0 );
		if ( is_wp_error( $att_id ) ) {
			@unlink( $tmp );
			return new WP_Error( 'aunstore_sideload', "$label could not be saved: " . $att_id->get_error_message() );
		}
		update_post_meta( $att_id, $meta_key, $meta_value );
		return (int) $att_id;
	}

	/**
	 * Swap BD attachment ids in Flatsome shortcodes for local ids.
	 * Handles img="", bg="", id="" and prefixed forms such as home_img="" / why_bg="" (single ids),
	 * and numeric ids="1,2,3" / hero_ids="1,2,3" lists (YouTube ids in [aun_shorts ids=""] are not numeric, so they are left alone).
	 *
	 * @param array $errors Collects messages for ids that could not be resolved (left unchanged).
	 */
	public static function rewrite_ids( $content, array &$errors ) {
		$resolve = function ( $bd_id ) use ( &$errors ) {
			$local = self::local_attachment( $bd_id );
			if ( is_wp_error( $local ) ) {
				$errors[] = $local->get_error_message();
				return (int) $bd_id;
			}
			return $local;
		};

		$content = preg_replace_callback( '/\b((?:[a-z]+_)?(?:img|bg)|id)="(\d+)"/', function ( $m ) use ( $resolve ) {
			return $m[1] . '="' . $resolve( $m[2] ) . '"';
		}, $content );

		$content = preg_replace_callback( '/\b((?:[a-z]+_)?ids)="(\d+(?:\s*,\s*\d+)*)"/', function ( $m ) use ( $resolve ) {
			$ids = array_map( 'trim', explode( ',', $m[2] ) );
			return $m[1] . '="' . implode( ',', array_map( $resolve, $ids ) ) . '"';
		}, $content );

		// Self-hosted banner videos, e.g. [ux_banner video_mp4="/wp-content/uploads/…mp4"].
		return preg_replace_callback( '#\b(video_mp4|video_webm|video_ogg)="((?:https://aun-projector\.com\.bd)?/wp-content/uploads/[^"]+)"#', function ( $m ) use ( &$errors ) {
			$local = self::local_file_url( $m[2] );
			if ( is_wp_error( $local ) ) {
				$errors[] = $local->get_error_message();
				return $m[0];
			}
			return $m[1] . '="' . $local . '"';
		}, $content );
	}

	/* ── Products ───────────────────────────────────────────────────────── */

	/** Parent "Projectors" category (slug `projectors`) — the Smart Finder and menus use it. */
	public static function parent_category_id() {
		$term = get_term_by( 'slug', 'projectors', 'product_cat' );
		if ( $term ) return (int) $term->term_id;
		$made = wp_insert_term( 'Projectors', 'product_cat', array( 'slug' => 'projectors' ) );
		return is_wp_error( $made ) ? 0 : (int) $made['term_id'];
	}

	/** @return int[] term ids: the "Projectors" parent + each named sub-category (created/re-parented as needed) */
	public static function category_ids( array $names ) {
		$parent = self::parent_category_id();
		$ids    = $parent ? array( $parent ) : array();
		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, 'product_cat' );
			if ( ! $term ) {
				$made = wp_insert_term( $name, 'product_cat', array( 'parent' => $parent ) );
				if ( is_wp_error( $made ) ) continue;
				$ids[] = (int) $made['term_id'];
			} else {
				if ( $parent && (int) $term->term_id !== $parent && (int) $term->parent !== $parent ) {
					wp_update_term( $term->term_id, 'product_cat', array( 'parent' => $parent ) );
				}
				$ids[] = (int) $term->term_id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Create or refresh one product.
	 * @return array{product_id:int,created:bool,errors:string[]}|WP_Error
	 */
	public static function import( $key ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'aunstore_no_wc', 'WooCommerce must be active.' );
		}
		$data = self::load_package( $key );
		if ( is_wp_error( $data ) ) return $data;

		if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 300 );

		$errors = array();

		$images = array();
		foreach ( (array) ( $data['images'] ?? array() ) as $bd_id ) {
			$local = self::local_attachment( $bd_id );
			if ( is_wp_error( $local ) ) {
				$errors[] = $local->get_error_message();
			} else {
				$images[] = $local;
			}
		}
		$description = self::rewrite_ids( $data['description'], $errors );
		$specs       = self::rewrite_ids( $data['specs'], $errors );

		$existing = wc_get_product_id_by_sku( $data['sku'] );
		$created  = ! $existing;
		$product  = $existing ? wc_get_product( $existing ) : new WC_Product_Simple();
		if ( ! $product ) {
			return new WP_Error( 'aunstore_product', 'Product with SKU ' . $data['sku'] . ' could not be loaded.' );
		}

		if ( $created ) {
			$product->set_sku( $data['sku'] );
			$product->set_status( 'draft' );
			if ( ! empty( $data['slug'] ) ) $product->set_slug( $data['slug'] );
		}
		$product->set_name( $data['name'] );
		$product->set_short_description( (string) ( $data['short_description'] ?? '' ) );
		$product->set_description( $description );
		$product->set_category_ids( self::category_ids( (array) ( $data['categories'] ?? array() ) ) );
		if ( $images ) {
			$product->set_image_id( array_shift( $images ) );
			$product->set_gallery_image_ids( $images );
		}
		$product->update_meta_data( AUNSTORE_SPECS_META, $specs );
		$product->update_meta_data( '_aunstore_package', $key );
		// Tool data (Screen Size Calculator optics, Smart Finder internal brightness).
		foreach ( (array) ( $data['meta'] ?? array() ) as $meta_key => $meta_value ) {
			if ( 0 === strpos( (string) $meta_key, '_aun_' ) || 0 === strpos( (string) $meta_key, '_aunstore_' ) ) {
				$product->update_meta_data( $meta_key, $meta_value );
			}
		}
		if ( isset( $data['menu_order'] ) ) {
			$product->set_menu_order( (int) $data['menu_order'] );
		}
		$product_id = $product->save();

		// Attach downloaded images to the product so they group under it in the Media Library.
		foreach ( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) as $att ) {
			if ( $att && ! wp_get_post_parent_id( $att ) ) {
				wp_update_post( array( 'ID' => $att, 'post_parent' => $product_id ) );
			}
		}

		return array( 'product_id' => (int) $product_id, 'created' => $created, 'errors' => array_values( array_unique( $errors ) ) );
	}

	/* ── Admin screen ───────────────────────────────────────────────────── */

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
		check_admin_referer( 'aunstore_import' );

		$key    = isset( $_POST['package'] ) ? sanitize_key( wp_unslash( $_POST['package'] ) ) : '';
		$result = self::import( $key );

		if ( is_wp_error( $result ) ) {
			$notice = array( 'type' => 'error', 'msg' => $result->get_error_message() );
		} else {
			$msg = ( $result['created'] ? 'Created' : 'Updated' ) . ' product #' . $result['product_id'] . ' (' . $key . ').';
			if ( $result['errors'] ) {
				$msg .= ' ' . count( $result['errors'] ) . ' image problem(s): ' . implode( ' | ', $result['errors'] ) . ' — press Import again to retry.';
			}
			$notice = array( 'type' => $result['errors'] ? 'warning' : 'success', 'msg' => $msg );
		}
		set_transient( 'aunstore_import_notice_' . get_current_user_id(), $notice, 300 );
		wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE ) );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<div class="wrap"><h1>AUN Product Import</h1><div class="notice notice-error"><p>WooCommerce must be active.</p></div></div>';
			return;
		}

		$notice = get_transient( 'aunstore_import_notice_' . get_current_user_id() );
		delete_transient( 'aunstore_import_notice_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1>AUN Product Import</h1>
			<p>Builds each product page from the content bundled in this plugin. Images are copied from aun-projector.com.bd the first time (this can take a minute per product).</p>
			<p><strong>New products are created as drafts with no price.</strong> Add the USD price, then publish. Importing again refreshes the description, specifications, short description, images and categories. It never changes price, stock or status.</p>
			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>"><p><?php echo esc_html( $notice['msg'] ); ?></p></div>
			<?php endif; ?>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>Product</th><th>SKU</th><th>On this site</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( array_keys( self::packages() ) as $key ) :
					$data = self::load_package( $key );
					if ( is_wp_error( $data ) ) {
						echo '<tr><td colspan="4">' . esc_html( $data->get_error_message() ) . '</td></tr>';
						continue;
					}
					$pid = wc_get_product_id_by_sku( $data['sku'] );
					?>
					<tr>
						<td><strong><?php echo esc_html( $data['name'] ); ?></strong></td>
						<td><code><?php echo esc_html( $data['sku'] ); ?></code></td>
						<td>
							<?php if ( $pid ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">#<?php echo (int) $pid; ?></a>
								(<?php echo esc_html( get_post_status( $pid ) ); ?>)
							<?php else : ?>
								Not imported yet
							<?php endif; ?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'aunstore_import' ); ?>
								<input type="hidden" name="action" value="aunstore_import">
								<input type="hidden" name="package" value="<?php echo esc_attr( $key ); ?>">
								<button class="button <?php echo $pid ? '' : 'button-primary'; ?>" onclick="this.disabled=true;this.textContent='Importing…';this.form.submit();">
									<?php echo $pid ? 'Re-import content' : 'Import'; ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

AUNStore_Importer::init();
