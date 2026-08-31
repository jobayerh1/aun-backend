/**
 * Kohthai Product Page — front-end behaviour
 * v1.0.0
 *
 * Two jobs:
 *   1. Keep a readable "Colour: Tea Green" line in sync above the swatch row.
 *   2. Reveal the sticky buy bar once the real Add to Cart scrolls out of view.
 *
 * Written to survive WP Rocket's "Delay JavaScript execution". Everything this
 * file adds is an ENHANCEMENT of markup that is already correct without it:
 * the per-swatch names are pure CSS, and the buy bar ships in the cached HTML
 * with [hidden] on it. If this script never runs, the page is still complete.
 *
 * No nonce is read or written here. The product page is cached, and a nonce
 * baked into cached HTML expires in 12-24h and starts failing for real
 * customers — that is the live bug in aun-alpha-otp-login, and it is not
 * getting repeated.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.ktPdp || {};

	/* =====================================================================
	 * 1. "Colour: <name>"
	 * ===================================================================== */

	function initColourLine() {
		var $swatches = $( '.ux-swatches' ).first();

		if ( ! $swatches.length ) {
			return;
		}

		// One line per attribute row, inserted above the swatches.
		var $line = $( '<p class="kt-colour-line"></p>' ).text( ( cfg.colourLabel || 'Colour' ) + ': ' );
		var $name = $( '<b></b>' ).text( cfg.notPicked || 'Choose below' );

		$line.append( $name );
		$swatches.before( $line );

		function sync() {
			var $picked = $swatches.find( '.ux-swatch[aria-checked="true"]' ).first();

			if ( $picked.length ) {
				// data-name is what Flatsome prints for humans; data-value is the slug.
				$name.text( $picked.attr( 'data-name' ) || $picked.attr( 'data-value' ) || '' );
			} else {
				$name.text( cfg.notPicked || 'Choose below' );
			}
		}

		// Flatsome flips aria-checked itself, so wait a tick before reading it.
		$swatches.on( 'click keyup', '.ux-swatch', function () {
			window.setTimeout( sync, 60 );
		} );

		// WooCommerce's own events cover programmatic changes and the Clear link.
		$( document.body ).on( 'found_variation reset_data woocommerce_variation_has_changed', function () {
			window.setTimeout( sync, 60 );
		} );

		sync();
	}

	/* =====================================================================
	 * 2. Sticky buy bar
	 * ===================================================================== */

	function initBuyBar() {
		var bar = document.querySelector( '[data-kt-buybar]' );

		if ( ! bar || ! cfg.sticky ) {
			return;
		}

		var realBtn = document.querySelector( '.single_add_to_cart_button' );

		if ( ! realBtn ) {
			return;
		}

		bar.hidden = false;

		var $variant = $( bar ).find( '[data-kt-variant]' );
		var $price   = $( bar ).find( '[data-kt-price]' );

		// --- reveal / hide -------------------------------------------------
		// IntersectionObserver rather than a scroll handler: no layout thrash,
		// and it keeps working when the summary column is sticky.
		if ( 'IntersectionObserver' in window ) {
			var observer = new IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( entry ) {
						// Show the bar only while the real button is off screen.
						bar.classList.toggle( 'is-visible', ! entry.isIntersecting );
					} );
				},
				{ rootMargin: '0px 0px -40px 0px' }
			);
			observer.observe( realBtn );
		} else {
			// Old Android browsers still get a working bar.
			bar.classList.add( 'is-visible' );
		}

		// --- the button ----------------------------------------------------
		// Scroll to the real control rather than submitting blind: on a variable
		// product a colour still has to be chosen, and silently failing an
		// add-to-cart is worse than showing the customer what is missing.
		$( bar ).on( 'click', '[data-kt-buy]', function () {
			var form      = document.querySelector( 'form.cart' );
			var isVariable = !! document.querySelector( 'form.variations_form' );
			var chosen     = true;

			if ( isVariable ) {
				$( '.variations select' ).each( function () {
					if ( ! this.value ) {
						chosen = false;
					}
				} );
			}

			if ( isVariable && ! chosen ) {
				scrollToSummary();
				return;
			}

			if ( form && typeof form.requestSubmit === 'function' ) {
				realBtn.click();
			} else if ( realBtn ) {
				realBtn.click();
			}
		} );

		function scrollToSummary() {
			var target = document.querySelector( '.variations' ) || realBtn;

			if ( ! target ) {
				return;
			}

			var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

			target.scrollIntoView( {
				behavior: reduce ? 'auto' : 'smooth',
				block: 'center'
			} );

			// A nudge so it is obvious WHY nothing was added to the cart.
			$( '.ux-swatches' ).first().css( 'outline', '2px solid #FFBC00' );
			window.setTimeout( function () {
				$( '.ux-swatches' ).first().css( 'outline', '' );
			}, 1400 );
		}

		// --- keep the bar honest about what is selected --------------------
		$( document.body ).on( 'found_variation', function ( event, variation ) {
			if ( variation && variation.price_html ) {
				$price.html( variation.price_html );
			}

			var picked = $( '.ux-swatch[aria-checked="true"]' )
				.map( function () {
					return $( this ).attr( 'data-name' );
				} )
				.get()
				.filter( Boolean )
				.join( ' · ' );

			$variant.text( picked );
		} );

		$( document.body ).on( 'reset_data', function () {
			$variant.text( '' );
		} );
	}

	/* =====================================================================
	 * Boot
	 * ===================================================================== */

	$( function () {
		try {
			initColourLine();
		} catch ( e ) {
			// A broken colour label must never take the buy bar down with it.
			window.console && window.console.warn( 'kt-pdp: colour line failed', e );
		}

		try {
			initBuyBar();
		} catch ( e ) {
			window.console && window.console.warn( 'kt-pdp: buy bar failed', e );
		}
	} );

}( window.jQuery ) );
