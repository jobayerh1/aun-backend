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
	const CACHE_KEY = 'aun_app_projector_catalogue';

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
		return (array) apply_filters(
			'aun_app_projector_categories',
			array( 'projector', 'projectors' )
		);
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

		// A mis-set category slug would otherwise return an empty planner with
		// no explanation, which reads as "the feature is broken". Falling back
		// to the unfiltered catalogue keeps it usable; retarget the category
		// with the aun_app_projector_categories filter if the list is too wide.
		if ( empty( $products ) && $cats ) {
			unset( $args['category'] );
			$products = wc_get_products( $args );
		}

		$out = array();
		foreach ( (array) $products as $product ) {
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
			'name'        => (string) $product->get_name(),
			'image'       => $image_url,
			'url'         => (string) $product->get_permalink(),
			'price'       => (string) wp_strip_all_tags( (string) $product->get_price_html() ),
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
	public static function match_model( $model_name ) {
		$needle = strtolower( trim( (string) $model_name ) );
		if ( '' === $needle ) {
			return 0;
		}

		$catalogue = self::catalogue();

		foreach ( $catalogue as $p ) {
			if ( strtolower( $p['name'] ) === $needle ) {
				return (int) $p['id'];
			}
		}

		// Longest match wins, so "A10" can't steal a device that is really an
		// "A10 Pro" just by being checked first.
		$best     = 0;
		$best_len = 0;
		foreach ( $catalogue as $p ) {
			$name = strtolower( $p['name'] );
			if ( false !== strpos( $name, $needle ) || false !== strpos( $needle, $name ) ) {
				$len = min( strlen( $name ), strlen( $needle ) );
				if ( $len > $best_len ) {
					$best     = (int) $p['id'];
					$best_len = $len;
				}
			}
		}
		return $best;
	}

	/** Drop the cached catalogue (product saved, or admin asked for a rebuild). */
	public static function flush() {
		delete_transient( self::CACHE_KEY );
	}
}
