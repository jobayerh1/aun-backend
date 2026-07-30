<?php
/**
 * Bridge to the website's AUN Help Center plugin (aun-help-center.php): the
 * same bilingual, model-aware FAQ brain the website shows now powers the
 * app's Help tab — one knowledge base, maintained once.
 *
 * The help-center keys its models by WooCommerce product slug with a SHORT
 * label ("A005", "A005 Pro") derived from the product title. The app knows
 * the SLB model name, which may be that same short form OR a fuller product
 * title ("AUN A005 Pro Android Projector") — so both the raw name and its
 * short-label derivation (via the help-center's own aun_hc_short_label())
 * are tried, exact and case-insensitive. Nothing is hardcoded: add a new
 * projector in WooCommerce + SLB Products and it matches automatically.
 *
 * Certified Android TV models get the certified answer variants
 * (a_bn_cert/a_en_cert) — the same swap the website performs client-side.
 *
 * The app deliberately gets NO embedded videos or manuals here: it has
 * dedicated Videos and Manuals tabs already, so the help feed is text-only
 * (the website keeps embedding both — that behaviour is untouched).
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Help {

	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/** Bumped when the bundle shape changes so stale cached bundles die. */
	const CACHE_PREFIX = 'aun_app_help2_';

	/** Whether the help-center plugin is active on this site. */
	public static function available() {
		return function_exists( 'aun_hc_data' );
	}

	/**
	 * Short label for a product name, exactly the way the help-center derives
	 * it. Uses the help-center's own helper when present so the two can never
	 * drift; the fallback mirrors it for the test bench without the plugin.
	 *
	 * @param string $name Product/model name.
	 * @return string
	 */
	private static function short_label( $name ) {
		if ( function_exists( 'aun_hc_short_label' ) ) {
			return (string) aun_hc_short_label( $name );
		}
		$t = trim( (string) preg_replace( '/^\s*AUN\s+/i', '', (string) $name ) );
		if ( '' === $t ) {
			return (string) $name;
		}
		$parts = preg_split( '/\s+/', $t );
		$label = $parts[0];
		if ( isset( $parts[1] ) && preg_match( '/^(pro|plus|max|ultra|lite)$/i', $parts[1] ) ) {
			$label .= ' ' . ucfirst( strtolower( $parts[1] ) );
		}
		return $label;
	}

	/**
	 * Find the help-center model entry matching an SLB model name.
	 *
	 * The SLB name may be the short label itself ("A005 Pro") or a fuller
	 * product title ("AUN A005 Pro Android Projector") — both are tried,
	 * exact and case-insensitive. Never fuzzy.
	 *
	 * @param string $model_name e.g. "A005 Pro".
	 * @return array|null {label, certified, topics, manual}
	 */
	private static function match_model( $model_name ) {
		$name = trim( (string) $model_name );
		if ( '' === $name || ! function_exists( 'aun_hc_models' ) ) {
			return null;
		}

		$candidates = array_unique( array(
			strtolower( $name ),
			strtolower( trim( self::short_label( $name ) ) ),
		) );

		foreach ( (array) aun_hc_models() as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$label = strtolower( trim( (string) ( $entry['label'] ?? '' ) ) );
			if ( '' !== $label && in_array( $label, $candidates, true ) ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * The Help payload for one model (or the generic set when unmatched).
	 *
	 * @param int $model_id slb_products.id (0 = generic).
	 * @return array{available:bool,model:?array,faqs:array[]}
	 */
	public static function bundle( $model_id ) {
		if ( ! self::available() ) {
			return array(
				'available' => false,
				'model'     => null,
				'faqs'      => array(),
			);
		}

		$model_id  = (int) $model_id;
		$cache_key = self::CACHE_PREFIX . $model_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$model_name = '';
		if ( $model_id > 0 && AUN_App_Warranty::available() ) {
			global $wpdb;
			$t          = AUN_App_Warranty::t_prods();
			$model_name = (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $t WHERE id = %d", $model_id ) );
		}

		$hc_model  = self::match_model( $model_name );
		$certified = ! empty( $hc_model['certified'] );

		$faqs = array();
		foreach ( (array) aun_hc_data() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			// The manual entry (KB-020) embeds per-model download lists on the
			// website; the app has its own Manuals tab — skip it here.
			if ( ! empty( $row['manual'] ) ) {
				continue;
			}
			// 'uncertified' entries are hidden for certified Android TV models.
			$applies = (string) ( $row['applies'] ?? 'all' );
			if ( 'uncertified' === $applies && $certified ) {
				continue;
			}

			// Certified models get the certified answer variant when one exists
			// (mirror of the website's client-side swap).
			$a_en = (string) ( $row['a_en'] ?? '' );
			$a_bn = (string) ( $row['a_bn'] ?? '' );
			if ( $certified ) {
				if ( '' !== (string) ( $row['a_en_cert'] ?? '' ) ) {
					$a_en = (string) $row['a_en_cert'];
				}
				if ( '' !== (string) ( $row['a_bn_cert'] ?? '' ) ) {
					$a_bn = (string) $row['a_bn_cert'];
				}
			}

			$faqs[] = array(
				'id'     => (string) ( $row['id'] ?? '' ),
				'cat'    => (string) ( $row['cat'] ?? '' ),
				'q'      => (string) ( $row['q_en'] ?? '' ),
				'q_bn'   => (string) ( $row['q_bn'] ?? '' ),
				'a'      => $a_en,
				'a_bn'   => $a_bn,
				// Bangla + Banglish + English keywords the website search uses.
				'search' => (string) ( $row['search'] ?? '' ),
			);
		}

		$out = array(
			'available' => true,
			'model'     => $hc_model ? array(
				'label'     => (string) ( $hc_model['label'] ?? $model_name ),
				'certified' => $certified,
			) : null,
			'faqs'      => $faqs,
		);

		set_transient( $cache_key, $out, self::CACHE_TTL );
		return $out;
	}
}
