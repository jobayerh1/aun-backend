/*!
 * Palltheme — store interactions (vanilla JS)
 * AJAX filtering/sorting/pagination is progressive enhancement: every link
 * and form still works as a normal page load without JavaScript.
 * Copyright (c) 2026 Engr. Nazim U Ahmed. All rights reserved.
 */
( function () {
	'use strict';

	const cfg = window.pallthemeWoo || { i18n: {} };
	const $ = ( sel, ctx = document ) => ctx.querySelector( sel );
	const $$ = ( sel, ctx = document ) => Array.from( ctx.querySelectorAll( sel ) );
	const results = $( '[data-pt-shop-results]' );
	const filters = $( '#pt-shop-filters' );

	/* ---------------- Mobile filter panel ---------------- */
	function setFilters( open ) {
		if ( ! filters ) {
			return;
		}
		filters.classList.toggle( 'is-open', open );
		document.body.classList.toggle( 'pt-filters-open', open );
		$$( '[data-pt-filters-open]' ).forEach( ( b ) => b.setAttribute( 'aria-expanded', String( open ) ) );
		if ( open ) {
			const first = $( 'a, button, input, select', filters );
			first && first.focus();
		}
	}
	document.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( '[data-pt-filters-open]' ) ) {
			setFilters( true );
		} else if ( e.target.closest( '[data-pt-filters-close]' ) ) {
			setFilters( false );
		} else if ( document.body.classList.contains( 'pt-filters-open' ) && ! e.target.closest( '#pt-shop-filters' ) ) {
			setFilters( false );
		}
	} );
	document.addEventListener( 'keydown', ( e ) => e.key === 'Escape' && setFilters( false ) );

	/* ---------------- Grid / list view ---------------- */
	function setView( view ) {
		if ( ! results ) {
			return;
		}
		results.classList.toggle( 'pt-view-list', view === 'list' );
		$$( '[data-pt-view]' ).forEach( ( b ) => {
			const on = b.getAttribute( 'data-pt-view' ) === view;
			b.classList.toggle( 'is-active', on );
			b.setAttribute( 'aria-pressed', String( on ) );
		} );
	}
	document.addEventListener( 'click', ( e ) => {
		const b = e.target.closest( '[data-pt-view]' );
		if ( ! b ) {
			return;
		}
		const view = b.getAttribute( 'data-pt-view' );
		try {
			localStorage.setItem( 'pt-shop-view', view );
		} catch ( err ) {}
		setView( view );
	} );
	try {
		setView( localStorage.getItem( 'pt-shop-view' ) || 'grid' );
	} catch ( err ) {}

	/* ---------------- AJAX shop navigation ---------------- */
	let controller = null;

	function announce( msg ) {
		let live = $( '#pt-shop-live' );
		if ( ! live ) {
			live = document.createElement( 'p' );
			live.id = 'pt-shop-live';
			live.className = 'screen-reader-text';
			live.setAttribute( 'role', 'status' );
			document.body.appendChild( live );
		}
		live.textContent = msg;
	}

	function load( url, push = true ) {
		if ( ! results ) {
			window.location.href = url;
			return;
		}
		if ( controller ) {
			controller.abort();
		}
		controller = new AbortController();
		results.classList.add( 'is-loading' );
		results.setAttribute( 'aria-busy', 'true' );

		fetch( url, { signal: controller.signal, credentials: 'same-origin' } )
			.then( ( r ) => {
				if ( ! r.ok ) {
					throw new Error( r.status );
				}
				return r.text();
			} )
			.then( ( html ) => {
				const doc = new DOMParser().parseFromString( html, 'text/html' );
				const fresh = doc.querySelector( '[data-pt-shop-results]' );
				if ( ! fresh ) {
					window.location.href = url;
					return;
				}
				results.innerHTML = fresh.innerHTML;
				const freshFilters = doc.querySelector( '#pt-shop-filters' );
				if ( filters && freshFilters ) {
					// Keep open/closed state of filter groups.
					const openIds = $$( 'details[open]', filters ).map( ( d ) => d.id );
					filters.innerHTML = freshFilters.innerHTML;
					$$( 'details', filters ).forEach( ( d ) => {
						if ( d.id && openIds.includes( d.id ) ) {
							d.open = true;
						}
					} );
				}
				const title = doc.querySelector( 'title' );
				if ( title ) {
					document.title = title.textContent;
				}
				if ( push ) {
					window.history.pushState( { ptShop: true }, '', url );
				}
				try {
					setView( localStorage.getItem( 'pt-shop-view' ) || 'grid' );
				} catch ( err ) {}
				announce( cfg.i18n.results || 'Products updated' );
				const top = results.getBoundingClientRect().top + window.scrollY - 120;
				if ( window.scrollY > top ) {
					window.scrollTo( { top, behavior: 'smooth' } );
				}
				document.dispatchEvent( new CustomEvent( 'palltheme:shop-updated' ) );
			} )
			.catch( ( err ) => {
				if ( err.name !== 'AbortError' ) {
					window.location.href = url;
				}
			} )
			.finally( () => {
				results.classList.remove( 'is-loading' );
				results.removeAttribute( 'aria-busy' );
			} );
	}

	if ( results ) {
		document.addEventListener( 'click', ( e ) => {
			const a = e.target.closest( '[data-pt-filter-link], .pt-filter a, .woocommerce-pagination a' );
			if ( ! a || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank' ) {
				return;
			}
			if ( a.origin !== window.location.origin ) {
				return;
			}
			e.preventDefault();
			load( a.href );
		} );

		// Sorting dropdown + price form (any GET form inside results/filters).
		document.addEventListener( 'change', ( e ) => {
			const select = e.target.closest( '.woocommerce-ordering select' );
			if ( ! select ) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			const form = select.form;
			const url = new URL( form.action || window.location.href, window.location.href );
			new FormData( form ).forEach( ( v, k ) => url.searchParams.set( k, v ) );
			url.searchParams.delete( 'paged' );
			url.pathname = url.pathname.replace( /\/page\/\d+\/?/, '/' );
			load( url.toString() );
		}, true );

		document.addEventListener( 'submit', ( e ) => {
			const form = e.target.closest( '.pt-filter form, .woocommerce-ordering' );
			if ( ! form ) {
				return;
			}
			e.preventDefault();
			const url = new URL( form.action || window.location.href, window.location.href );
			new FormData( form ).forEach( ( v, k ) => {
				if ( v === '' ) {
					url.searchParams.delete( k );
				} else {
					url.searchParams.set( k, v );
				}
			} );
			url.pathname = url.pathname.replace( /\/page\/\d+\/?/, '/' );
			load( url.toString() );
		} );

		window.addEventListener( 'popstate', () => load( window.location.href, false ) );
	}

	/* ---------------- Load more ---------------- */
	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-pt-loadmore]' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		const list = $( 'ul.products', results || document );
		btn.textContent = cfg.i18n.loading || 'Loading…';
		btn.setAttribute( 'aria-busy', 'true' );
		fetch( btn.href, { credentials: 'same-origin' } )
			.then( ( r ) => r.text() )
			.then( ( html ) => {
				const doc = new DOMParser().parseFromString( html, 'text/html' );
				const items = $$( 'ul.products > li.product', doc );
				items.forEach( ( li ) => list.appendChild( document.importNode( li, true ) ) );
				const next = doc.querySelector( '[data-pt-loadmore]' );
				if ( next ) {
					btn.href = next.href;
					btn.textContent = cfg.i18n.loadMore || 'Load more products';
					btn.removeAttribute( 'aria-busy' );
				} else {
					btn.parentElement.innerHTML = '<p class="pt-muted">' + ( cfg.i18n.noMore || 'All products loaded' ) + '</p>';
				}
				const count = doc.querySelector( '.woocommerce-result-count' );
				const current = $( '.woocommerce-result-count' );
				if ( count && current ) {
					current.textContent = count.textContent;
				}
				announce( items.length + ' ' + ( cfg.i18n.results || 'products added' ) );
			} )
			.catch( () => {
				window.location.href = btn.href;
			} );
	} );

	/* ---------------- Quick view ---------------- */
	let qv = null;
	function quickview( url, opener ) {
		if ( ! qv ) {
			qv = document.createElement( 'div' );
			qv.className = 'pt-quickview';
			qv.hidden = true;
			qv.setAttribute( 'role', 'dialog' );
			qv.setAttribute( 'aria-modal', 'true' );
			qv.innerHTML = '<div class="pt-quickview__backdrop" data-pt-qv-close></div><div class="pt-quickview__panel"><button type="button" class="pt-icon-btn pt-quickview__close" data-pt-qv-close aria-label="Close"><svg class="pt-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button><div class="pt-quickview__content" aria-live="polite"></div></div>';
			document.body.appendChild( qv );
			qv.addEventListener( 'click', ( e ) => {
				if ( e.target.closest( '[data-pt-qv-close]' ) ) {
					window.pallthemeDialog.hide( qv );
				}
			} );
		}
		const content = $( '.pt-quickview__content', qv );
		content.innerHTML = '<p style="padding:48px;text-align:center">' + ( cfg.i18n.loading || 'Loading…' ) + '</p>';
		window.pallthemeDialog.show( qv, opener );
		fetch( url, { credentials: 'same-origin', headers: { Accept: 'application/json' } } )
			.then( ( r ) => r.json() )
			.then( ( json ) => {
				content.innerHTML = json && json.html ? json.html : '';
				const form = $( '.variations_form', content );
				if ( form && window.jQuery && window.jQuery.fn.wc_variation_form ) {
					window.jQuery( form ).wc_variation_form();
				}
				const heading = $( 'h2', content );
				if ( heading ) {
					heading.id = 'pt-qv-title';
					qv.setAttribute( 'aria-labelledby', 'pt-qv-title' );
				}
			} )
			.catch( () => {
				content.innerHTML = '<p style="padding:48px;text-align:center">' + ( cfg.i18n.error || 'Error' ) + '</p>';
			} );
	}
	document.addEventListener( 'click', ( e ) => {
		const b = e.target.closest( '[data-pt-quickview]' );
		if ( ! b ) {
			return;
		}
		e.preventDefault();
		quickview( b.getAttribute( 'data-pt-quickview' ), b );
	} );

	/* ---------------- Product gallery: no layout shift while FlexSlider starts ---------------- */
	// For one frame during set-up the slider is several times taller than the finished
	// gallery, which pushes the summary down and back on phones (a layout shift).
	// Hold the gallery at its current height until WooCommerce has faded it in.
	if ( window.jQuery ) {
		window.jQuery( document ).on( 'wc-product-gallery-before-init', '.woocommerce-product-gallery', function () {
			const gallery = this;
			if ( ! gallery.offsetHeight ) {
				return;
			}
			gallery.style.height = gallery.offsetHeight + 'px';
			gallery.style.overflow = 'hidden';
			let released = false;
			const release = () => {
				if ( released ) {
					return;
				}
				released = true;
				gallery.style.height = '';
				gallery.style.overflow = '';
			};
			gallery.addEventListener( 'transitionend', ( e ) => e.propertyName === 'opacity' && release() );
			window.setTimeout( release, 2500 );
		} );
	}

	/* ---------------- AJAX add to cart on single simple products ---------------- */
	document.addEventListener( 'submit', ( e ) => {
		const form = e.target.closest( 'form.cart' );
		if ( ! form || form.classList.contains( 'variations_form' ) || form.classList.contains( 'grouped_form' ) || ! window.jQuery || typeof window.wc_add_to_cart_params === 'undefined' ) {
			return;
		}
		const submitter = e.submitter;
		if ( submitter && submitter.name === 'pt_buy_now' ) {
			return; // Buy Now uses a normal POST + redirect to checkout.
		}
		const button = $( '[name="add-to-cart"]', form );
		if ( ! button || form.closest( '.product-type-external' ) ) {
			return;
		}
		e.preventDefault();
		const body = new FormData( form );
		body.set( 'product_id', button.value );
		body.delete( 'add-to-cart' );
		button.classList.add( 'loading' );
		button.disabled = true;
		const url = window.wc_add_to_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'add_to_cart' );
		fetch( url, { method: 'POST', body, credentials: 'same-origin' } )
			.then( ( r ) => r.json() )
			.then( ( res ) => {
				if ( ! res || res.error ) {
					form.submit();
					return;
				}
				// Replace fragments ourselves (cart-fragments.js is not always enqueued).
				Object.keys( res.fragments || {} ).forEach( ( sel ) => {
					$$( sel ).forEach( ( el ) => {
						el.outerHTML = res.fragments[ sel ];
					} );
				} );
				window.jQuery( document.body ).trigger( 'added_to_cart', [ res.fragments, res.cart_hash, window.jQuery( button ) ] );
			} )
			.catch( () => form.submit() )
			.finally( () => {
				button.classList.remove( 'loading' );
				button.disabled = false;
			} );
	} );
}() );
