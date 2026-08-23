<?php
/**
 * Projector catalogue + optics, for the in-app Projector Planner.
 *
 * The planner answers "what screen size will I get in MY room", which is the
 * one question a customer asks BEFORE buying as well as after. So this is the
 * app's only public, no-login data source, and its model list is the shop
 * catalogue (WooCommerce) rather than wp_slb_products — the latter only holds
 * models somebody already registered a warranty for, which is exactly the
 * wrong list to show a prospective buyer.
 *
 * Nothing about a projector is hardcoded here. The optics come from the same
 * product meta the website calculator uses (`_aun_throw_ratio`,
 * `_aun_min_screen_size`, `_aun_max_screen_size`), and when the AUN Throw
 * Distance Calculator plugin is present we call ITS resolver so the two
 * surfaces can never disagree. Each figure is returned with the source that
 * produced it, so the app can label an estimate as an estimate instead of
 * presenting a guess as a measurement.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Projectors {

	/** Cache key for the resolved catalogue. */
	/** ⚠️ Suffix bumped when the catalogue's CONTENTS change meaning, so a
	 * stale transient cannot keep serving the old list after an upgrade.
	 * _v2: discontinued filtering no longer depends on a loaded plugin. */
	const CACHE_KEY = 'aun_app_projector_catalogue_v2';

	/** How long the catalogue stays cached (products change rarely). */
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/** Fallback throw ratio, matching the website calculator's own default. */
	const DEFAULT_THROW_RATIO = 1.35;

	/** Whether WooCommerce is available to read a catalogue from. */
	public static function available() {
		return function_exists( 'wc_get_products' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Product category slugs treated as projectors.
	 *
	 * Filterable so the catalogue can be retargeted without touching code —
	 * `add_filter( 'aun_app_projector_categories', fn() => array( 'projectors' ) )`.
	 *
	 * @return string[] Empty array = don't filter by category at all.
	 */
	public static function categories() {
		// 'projector-price' is the slug of "Projector Price in Bangladesh"
		// (/projector-price/) — the real projector catalogue. Without it the
		// planner listed accessories, screens and mounts, none of which have a
		// throw ratio.
		return (array) apply_filters(
			'aun_app_projector_categories',
			array( 'projector-price' )
		);
	}

	/**
	 * Is this product discontinued?
	 *
	 * Uses the same test as the rest of the site (see aun-campaign-bar.php):
	 * the WooCommerce Discontinued Products plugin marks a product with the
	 * `dp-discontinued` term in the `product_discontinued` taxonomy. Planning a
	 * room around a projector nobody can buy wastes the customer's time, so
	 * these are dropped from the catalogue entirely.
	 *
	 * @param int $product_id Product id.
	 * @return bool
	 */
	public static function is_discontinued( $product_id ) {
		return in_array( (int) $product_id, self::discontinued_ids(), true );
	}

	/** Cached for the request — the catalogue asks once per product. */
	private static $discontinued_ids = null;

	/**
	 * Every product tagged discontinued, read STRAIGHT FROM THE DATABASE.
	 *
	 * ⚠️ **Deliberately not `taxonomy_exists()` + `has_term()`, and this is a
	 * live bug fix.** Both of those need the Discontinued Products plugin to be
	 * LOADED so the taxonomy is registered — and `mu-aun-api-lean.php` drops
	 * that plugin on every `/wp-json/aun-app/` request, filed under "WooCommerce
	 * add-ons that only shape the shop pages". It does not only shape shop
	 * pages: the planner catalogue depends on it.
	 *
	 * So on the app's own endpoint `taxonomy_exists()` returned false,
	 * `is_discontinued()` answered false for everything, and the planner offered
	 * the customer models we no longer sell.
	 *
	 * ⚠️ It was intermittent, which is why it hid for so long: `catalogue()` is
	 * cached for 6 hours, so whichever request happened to rebuild it decided
	 * what everyone saw. Rebuilt from wp-admin → correct list. Rebuilt from an
	 * app request → discontinued models, cached for the next six hours, and the
	 * admin diagnostic panel would show the CORRECT list all the while, because
	 * that page loads the plugin.
	 *
	 * The term rows exist in the database whether or not the plugin is loaded,
	 * so reading them directly makes the planner independent of it. One query
	 * per request, replacing one `has_term()` per product.
	 *
	 * @return int[] Product IDs.
	 */
	public static function discontinued_ids() {
		if ( is_array( self::$discontinued_ids ) ) {
			return self::$discontinued_ids;
		}
		global $wpdb;
		$ids = $wpdb->get_col(
			"SELECT tr.object_id
			   FROM {$wpdb->term_relationships} tr
			   JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			   JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			  WHERE tt.taxonomy = 'product_discontinued'
			    AND t.slug = 'dp-discontinued'"
		);
		self::$discontinued_ids = array_map( 'intval', (array) $ids );
		return self::$discontinued_ids;
	}

	/**
	 * The full planner catalogue: every projector we sell, with its optics.
	 *
	 * @param bool $force Skip the cache.
	 * @return array<int,array>
	 */
	public static function catalogue( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		if ( ! self::available() ) {
			return array();
		}

		$args = array(
			'status' => 'publish',
			'limit'  => 200,
			'orderby' => 'title',
			'order'   => 'ASC',
		);
		$cats = array_filter( array_map( 'sanitize_title', self::categories() ) );
		if ( $cats ) {
			$args['category'] = $cats;
		}

		$products = wc_get_products( $args );

		// NOTE: deliberately NO fallback to the unfiltered catalogue here.
		// An earlier version did that so a mis-set slug couldn't empty the
		// planner, but the failure it caused was worse than the one it
		// prevented: the planner silently filled with screens, mounts and
		// cables, none of which have a throw ratio. An empty list is an
		// obvious, fixable problem; a list of the wrong products is not.

		$out = array();
		foreach ( (array) $products as $product ) {
			// Don't plan a room around something nobody can buy.
			if ( self::is_discontinued( (int) $product->get_id() ) ) {
				continue;
			}
			$entry = self::entry( $product );
			if ( null !== $entry ) {
				$out[] = $entry;
			}
		}

		set_transient( self::CACHE_KEY, $out, self::CACHE_TTL );
		return $out;
	}

	/**
	 * Normalise one WooCommerce product into a planner entry.
	 *
	 * @param WC_Product $product Product.
	 * @return array|null Null when the product can't be planned with.
	 */
	private static function entry( $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return null;
		}
		$id    = (int) $product->get_id();
		$specs = self::specs( $id );

		// Throw ratio is the ONE figure the planner cannot work without. A
		// product whose ratio is a pure guess is still usable — it is simply
		// flagged as an estimate rather than silently presented as fact.
		$image_id  = (int) $product->get_image_id();
		$image_url = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : '';

		return array(
			'id'          => $id,
			// The SKU is how a registered device is matched to this product —
			// see product_for_device(). Both sides already carry it.
			//
			// ⚠️ `sku` is the PARENT's own, which on a variable product is
			// legitimately empty — WooCommerce keeps SKUs on the variations.
			// Match against `skus`, never this one, or every colour-variant
			// projector silently fails to link (see self::skus_of()).
			'sku'         => (string) $product->get_sku(),
			'skus'        => self::skus_of( $product ),
			'erp_id'      => self::erp_id_of( $id ),
			'name'        => (string) $product->get_name(),
			'image'       => $image_url,
			'url'         => (string) $product->get_permalink(),
			// ⚠️ NOT wp_strip_all_tags( get_price_html() ). That is a filtered
			// free-for-all — it arrived as "&#2547;&nbsp;14,500.00 0% EMIs from
			// ৳2,417/month": entity codes stripping does not decode, plus the EMI
			// plugin's paragraph in a field the app renders as one price.
			// AUN_App_REST::price_text() is the one place that gets this right.
			'price'       => class_exists( 'AUN_App_REST' ) && method_exists( 'AUN_App_REST', 'price_text' )
				? (string) AUN_App_REST::price_text( $product )
				: (string) wp_strip_all_tags( (string) $product->get_price_html() ),
			'in_stock'    => (bool) $product->is_in_stock(),
			'throw_ratio' => round( (float) $specs['throw_ratio'], 3 ),
			'min_screen'  => (int) $specs['min_screen'],
			'max_screen'  => (int) $specs['max_screen'],
			'min_distance_m' => round( (float) $specs['min_distance_m'], 3 ),
			// 16:9 unless the product says otherwise — see aspect_ratio().
			'aspect'      => self::aspect_ratio( $id ),
			// 'meta' = an admin typed it, 'extracted' = read from the product's
			// own spec text, 'default' = we guessed. The app shows an "estimated"
			// note for anything that isn't 'meta' or 'extracted'.
			'sources'     => (array) $specs['sources'],
			'exact'       => in_array( ( $specs['sources']['throw_ratio'] ?? '' ), array( 'meta', 'extracted' ), true ),
		);
	}

	/**
	 * Every SKU that identifies this product — the parent's own plus each
	 * variation's.
	 *
	 * ⚠️ **A variable product has no SKU of its own, and that is correct
	 * WooCommerce behaviour, not missing data.** The AUN A005 is one product
	 * with a Grey and a White variation carrying APB-A005-GRY and
	 * APB-A005-WHT; `$product->get_sku()` on the parent returns ''. Because
	 * matching only ever looked at that parent SKU, a registered A005 could
	 * never be linked by SKU and fell through to name matching — which cannot
	 * tell "A005" from "A005 Pro". The admin planner panel reported it as
	 * "no SKU", which read like a data-entry problem and was really this.
	 *
	 * The colours are optically identical, so every variation SKU maps to the
	 * same parent product and the same throw ratio. That is exactly what the
	 * planner needs.
	 *
	 * @param WC_Product $product Parent product.
	 * @return string[] Unique, non-empty SKUs.
	 */
	public static function skus_of( $product ) {
		$out = array();
		$own = trim( (string) $product->get_sku() );
		if ( '' !== $own ) {
			$out[] = $own;
		}
		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) ) {
			foreach ( (array) $product->get_children() as $child_id ) {
				$child = wc_get_product( (int) $child_id );
				if ( ! $child ) {
					continue;
				}
				$sku = trim( (string) $child->get_sku() );
				if ( '' !== $sku ) {
					$out[] = $sku;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Optics for one product.
	 *
	 * Prefers the website calculator's own resolver so there is exactly one
	 * implementation of "how do we work out a throw ratio". When that plugin
	 * isn't active we still read the same meta keys directly — the contract
	 * between the two is the meta, not the PHP class — but the regex
	 * extraction from description text is only available with the plugin.
	 *
	 * @param int $product_id Product id.
	 * @return array{throw_ratio:float,min_screen:int,max_screen:int,min_distance_m:float,sources:array}
	 */
	public static function specs( $product_id ) {
		$product_id = (int) $product_id;

		if ( class_exists( 'AUN_Throw_Calculator' ) && method_exists( 'AUN_Throw_Calculator', 'specs' ) ) {
			$specs = AUN_Throw_Calculator::specs( $product_id );
			if ( is_array( $specs ) && isset( $specs['throw_ratio'] ) ) {
				return $specs;
			}
		}

		$ratio   = (float) get_post_meta( $product_id, '_aun_throw_ratio', true );
		$sources = array();
		if ( $ratio >= 0.1 ) {
			$sources['throw_ratio'] = 'meta';
		} else {
			$ratio                  = self::DEFAULT_THROW_RATIO;
			$sources['throw_ratio'] = 'default';
		}

		$min = (int) get_post_meta( $product_id, '_aun_min_screen_size', true );
		$max = (int) get_post_meta( $product_id, '_aun_max_screen_size', true );
		$sources['min_screen'] = $min > 0 ? 'meta' : 'none';
		$sources['max_screen'] = $max > 0 ? 'meta' : 'none';

		return array(
			'throw_ratio'    => $ratio,
			'min_screen'     => $min,
			'max_screen'     => $max,
			'min_distance_m' => 0.0,
			'sources'        => $sources,
		);
	}

	/**
	 * Native aspect ratio as "W:H".
	 *
	 * The website calculator assumes 16:9 everywhere, which is right for almost
	 * every projector we sell but silently wrong for a 4:3 or 16:10 panel. The
	 * app asks for it explicitly so the geometry can follow the product rather
	 * than an assumption baked into the maths.
	 *
	 * @param int $product_id Product id.
	 * @return string e.g. "16:9".
	 */
	public static function aspect_ratio( $product_id ) {
		$raw = trim( (string) get_post_meta( (int) $product_id, '_aun_aspect_ratio', true ) );
		if ( preg_match( '/^(\d{1,2})\s*:\s*(\d{1,2})$/', $raw, $m ) ) {
			$w = (int) $m[1];
			$h = (int) $m[2];
			if ( $w > 0 && $h > 0 ) {
				return $w . ':' . $h;
			}
		}
		return '16:9';
	}

	/**
	 * Best-effort match from a registered device's model name to a catalogue
	 * entry, so an owner opening the planner starts on THEIR projector instead
	 * of picking from a list of things they already didn't buy.
	 *
	 * Exact (case-insensitive) match first, then a containment match in either
	 * direction — "A10" should find "AUN A10 Full HD Projector", and a device
	 * recorded as "AUN A10 Projector" should find a product named "AUN A10".
	 *
	 * @param string $model_name Device model as registered.
	 * @return int Catalogue product id, or 0 when nothing matches confidently.
	 */
	/**
	 * The ERP (UltimatePOS) product id this WooCommerce product represents.
	 *
	 * Set on the product page by the AUN Throw Distance Calculator plugin. When
	 * absent we accept a purely numeric SKU, since some catalogues already put
	 * the ERP id there.
	 *
	 * @param int $product_id WooCommerce product id.
	 * @return int ERP id, or 0 when unmapped.
	 */
	public static function erp_id_of( $product_id ) {
		$erp = (int) get_post_meta( (int) $product_id, '_aun_erp_product_id', true );
		if ( $erp > 0 ) {
			return $erp;
		}
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( (int) $product_id );
			if ( $product ) {
				$sku = trim( (string) $product->get_sku() );
				if ( '' !== $sku && ctype_digit( $sku ) ) {
					return (int) $sku;
				}
			}
		}
		return 0;
	}

	/**
	 * The catalogue entry for a projector the customer actually owns.
	 *
	 * Resolved by ID, never by name. A registered device carries the ERP
	 * product id it was sold as, and slb_products holds that same id — the link
	 * the warranty plugin and the hourly stock sync already run on, whose own
	 * docblock says it never guesses by name.
	 *
	 * Name matching used to be the ONLY method here and it was wrong on real
	 * data: a device recorded as "A005" matched "A005 Pro" exactly as well as
	 * "A005", and every new model sharing a prefix would break it again. It
	 * survives purely as a last resort for products nobody has mapped yet, and
	 * the admin screen lists those so they can be fixed.
	 *
	 * @param array $device One entry from AUN_App_Warranty::get_devices().
	 * @return int WooCommerce product id, or 0.
	 */
	public static function product_for_device( $device ) {
		$catalogue = self::catalogue();

		// 1. THE SKU. This is the link the rest of the system already runs on
		//    and it needs no new data entry at all: the dealer-stock sync
		//    records each serial's product_sku in slb_serials, and every
		//    WooCommerce product carries that same SKU. Serial → SKU → product,
		//    all exact.
		$sku = self::sku_for_serial( (string) ( $device['serial'] ?? '' ) );
		if ( '' !== $sku ) {
			foreach ( $catalogue as $p ) {
				// ⚠️ Against every SKU the product answers to, not just the
				// parent's. A variable product keeps its SKUs on the variations,
				// so comparing the parent alone never matched a colour variant.
				foreach ( (array) ( $p['skus'] ?? array() ) as $candidate ) {
					if ( 0 === strcasecmp( (string) $candidate, $sku ) ) {
						return (int) $p['id'];
					}
				}
			}
		}

		// 2. An explicit ERP product id on the product, for anything the SKU
		//    route cannot reach (a serial that predates the sync, say).
		$erp = (int) ( $device['erp_product_id'] ?? 0 );
		if ( $erp < 1 ) {
			$erp = self::erp_id_for_model( (int) ( $device['model_id'] ?? 0 ) );
		}
		if ( $erp > 0 ) {
			foreach ( $catalogue as $p ) {
				if ( (int) ( $p['erp_id'] ?? 0 ) === $erp ) {
					return (int) $p['id'];
				}
			}
		}

		// 3. Last resort only, and never reliable — see this method's note.
		return self::match_model( (string) ( $device['model'] ?? '' ) );
	}

	/**
	 * The product SKU recorded against a serial by the dealer-stock sync.
	 *
	 * `slb_serials` is written by the AUN Warranty & Registration plugin from
	 * the ERP, and holds product_sku next to the serial — so a registered
	 * device can be traced to an exact product without anybody typing anything.
	 *
	 * @param string $serial Device serial.
	 * @return string SKU, or ''.
	 */
	public static function sku_for_serial( $serial ) {
		if ( ! class_exists( 'AUN_App_Warranty' ) ) {
			return '';
		}
		$serial = trim( (string) $serial );
		if ( '' === $serial ) {
			return '';
		}

		global $wpdb;
		$t = AUN_App_Warranty::t_serials();
		if ( ! in_array( 'product_sku', (array) $wpdb->get_col( "SHOW COLUMNS FROM $t" ), true ) ) {
			return '';
		}
		return (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT product_sku FROM $t WHERE serial = %s AND product_sku IS NOT NULL AND product_sku != '' LIMIT 1",
			$serial
		) );
	}

	/**
	 * ERP product id behind a warranty model id (slb_products.id).
	 *
	 * @param int $model_id slb_products.id.
	 * @return int
	 */
	public static function erp_id_for_model( $model_id ) {
		global $wpdb;
		$model_id = (int) $model_id;
		if ( $model_id < 1 || ! class_exists( 'AUN_App_Warranty' ) ) {
			return 0;
		}
		$t = AUN_App_Warranty::t_prods();
		if ( ! in_array( 'erp_product_id', (array) $wpdb->get_col( "SHOW COLUMNS FROM $t" ), true ) ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT erp_product_id FROM $t WHERE id = %d LIMIT 1",
			$model_id
		) );
	}

	/**
	 * LAST-RESORT name match, for products with no ERP id mapped yet.
	 *
	 * Prefer product_for_device(). This exists so an unmapped catalogue still
	 * does something sensible, not as the primary mechanism — see that method
	 * for why matching on names is unsafe.
	 *
	 * @param string $model_name Device model as registered.
	 * @return int Catalogue product id, or 0.
	 */
	public static function match_model( $model_name ) {
		$needle = self::normalise_model( $model_name );
		if ( '' === $needle ) {
			return 0;
		}

		$catalogue = self::catalogue();

		// 1. Exact match on the normalised name always wins.
		foreach ( $catalogue as $p ) {
			if ( self::normalise_model( $p['name'] ) === $needle ) {
				return (int) $p['id'];
			}
		}

		// 2. Otherwise the CLOSEST containing name wins — the one with the
		//    least extra text.
		//
		//    Picking the longest match was wrong: for a device recorded as
		//    "A005", both "AUN A005" and "AUN A005 Pro" contain it and scored
		//    identically, so the tie broke on catalogue order and quietly
		//    selected the Pro. Extra words almost always mean a DIFFERENT
		//    model, so the fewer of them the better.
		$best  = 0;
		$extra = PHP_INT_MAX;
		foreach ( $catalogue as $p ) {
			$name = self::normalise_model( $p['name'] );
			if ( '' === $name ) {
				continue;
			}
			if ( false === strpos( $name, $needle ) && false === strpos( $needle, $name ) ) {
				continue;
			}
			$diff = abs( strlen( $name ) - strlen( $needle ) );
			if ( $diff < $extra ) {
				$best  = (int) $p['id'];
				$extra = $diff;
			}
		}
		return $best;
	}

	/**
	 * Reduce a product or device name to just the part that identifies a model.
	 *
	 * Catalogue titles carry marketing ("AUN A005 Full HD Projector") while a
	 * registered device may hold only "A005", so the two never match literally.
	 * Stripping the brand, the word "projector" and punctuation leaves the
	 * distinguishing part — and keeps a real difference like "pro" intact,
	 * because that IS a different model.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public static function normalise_model( $name ) {
		$n = strtolower( trim( (string) $name ) );
		$n = str_replace( array( '-', '_', '/', '+' ), ' ', $n );
		// Brand and category noise, never a model distinction.
		$n = preg_replace( '/(aun|projector|projectors|full\s*hd|fhd|native|smart|android|wifi|wi\s*fi)/', ' ', $n );
		$n = preg_replace( '/[^a-z0-9 ]/', '', $n );
		return trim( preg_replace( '/\s+/', ' ', $n ) );
	}

	/** Drop the cached catalogue (product saved, or admin asked for a rebuild). */
	public static function flush() {
		delete_transient( self::CACHE_KEY );
	}
}
