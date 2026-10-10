/*!
 * Palltheme — main script (vanilla JS, no dependencies)
 * Copyright (c) 2026 Engr. Nazim U Ahmed. All rights reserved.
 */
( function () {
	'use strict';

	const data = window.pallthemeData || { i18n: {} };
	const t = ( key, fallback ) => ( data.i18n && data.i18n[ key ] ) || fallback;
	const $ = ( sel, ctx = document ) => ctx.querySelector( sel );
	const $$ = ( sel, ctx = document ) => Array.from( ctx.querySelectorAll( sel ) );
	const reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const animationsOn = data.animations !== false && ! reduceMotion && ! document.body.classList.contains( 'pt-no-anim' );

	/* ------------------------------------------------------------------
	 * Dialog helper (off-canvas, search, mini cart, quick view)
	 * ------------------------------------------------------------------ */
	const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])';
	let openDialog = null;

	function showDialog( el, opener ) {
		if ( ! el ) {
			return;
		}
		if ( openDialog ) {
			hideDialog( openDialog );
		}
		el.hidden = false;
		el._opener = opener || document.activeElement;
		if ( opener ) {
			opener.setAttribute( 'aria-expanded', 'true' );
		}
		document.documentElement.style.overflow = 'hidden';
		openDialog = el;
		const target = $( '[data-autofocus], input[type="search"]', el ) || $( FOCUSABLE, el );
		window.requestAnimationFrame( () => target && target.focus() );
	}

	function hideDialog( el ) {
		if ( ! el || el.hidden ) {
			return;
		}
		el.hidden = true;
		document.documentElement.style.overflow = '';
		if ( el._opener ) {
			el._opener.setAttribute && el._opener.setAttribute( 'aria-expanded', 'false' );
			el._opener.focus && el._opener.focus();
		}
		openDialog = null;
	}
	window.pallthemeDialog = { show: showDialog, hide: hideDialog };

	document.addEventListener( 'keydown', ( e ) => {
		if ( ! openDialog ) {
			return;
		}
		if ( e.key === 'Escape' ) {
			hideDialog( openDialog );
			return;
		}
		if ( e.key === 'Tab' ) {
			const items = $$( FOCUSABLE, openDialog ).filter( ( n ) => n.offsetParent !== null );
			if ( ! items.length ) {
				return;
			}
			const first = items[ 0 ];
			const last = items[ items.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	} );

	/* ------------------------------------------------------------------
	 * Color scheme: light → dark → system
	 * ------------------------------------------------------------------ */
	const root = document.documentElement;
	const mql = window.matchMedia( '(prefers-color-scheme: dark)' );

	function applyScheme( pref ) {
		const resolved = pref === 'system' ? ( mql.matches ? 'dark' : 'light' ) : pref;
		root.setAttribute( 'data-scheme', resolved );
		root.setAttribute( 'data-scheme-pref', pref );
		$$( '[data-pt-scheme-label]' ).forEach( ( n ) => {
			n.textContent = t( pref, pref ) + ' — ' + t( 'switch', 'click to change' );
		} );
	}

	$$( '[data-pt-scheme-toggle]' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			const order = [ 'light', 'dark', 'system' ];
			const current = root.getAttribute( 'data-scheme-pref' ) || 'system';
			const next = order[ ( order.indexOf( current ) + 1 ) % order.length ];
			try {
				localStorage.setItem( 'pt-scheme', next );
			} catch ( err ) {}
			applyScheme( next );
		} );
	} );
	mql.addEventListener( 'change', () => {
		if ( root.getAttribute( 'data-scheme-pref' ) === 'system' ) {
			applyScheme( 'system' );
		}
	} );
	applyScheme( root.getAttribute( 'data-scheme-pref' ) || 'system' );

	/* ------------------------------------------------------------------
	 * Transparent header safeguard: only stay transparent when the page
	 * actually starts with a dark section (hero, dark band). Otherwise a
	 * light first section would make white menu text unreadable.
	 * ------------------------------------------------------------------ */
	function checkTransparentHeader() {
		if ( ! document.body.classList.contains( 'pt-header-transparent' ) && ! document.body.dataset.ptWasTransparent ) {
			return;
		}
		document.body.dataset.ptWasTransparent = '1';
		const main = $( '#primary' );
		if ( ! main ) {
			return;
		}
		const top = main.getBoundingClientRect().top;
		const darkTop = $$( '.pt-hero, [data-scheme-lock="dark"]', main ).some(
			( el ) => el.offsetHeight > 40 && Math.abs( el.getBoundingClientRect().top - top ) < 24
		);
		document.body.classList.toggle( 'pt-header-transparent', darkTop );
	}
	checkTransparentHeader();
	if ( document.body.classList.contains( 'elementor-editor-active' ) && 'MutationObserver' in window ) {
		let pending = null;
		new MutationObserver( () => {
			window.clearTimeout( pending );
			pending = window.setTimeout( checkTransparentHeader, 300 );
		} ).observe( $( '#primary' ) || document.body, { childList: true, subtree: true } );
	}

	/* ------------------------------------------------------------------
	 * Header: scrolled state
	 * ------------------------------------------------------------------ */
	const header = $( '[data-pt-header]' );
	const toTop = $( '[data-pt-to-top]' );
	const topbar = $( '.pt-topbar' );
	if ( topbar && 'ResizeObserver' in window ) {
		// A transparent header sits just below the top bar, whose height changes when it wraps.
		new window.ResizeObserver( () => document.documentElement.style.setProperty( '--pt-topbar-h', topbar.offsetHeight + 'px' ) ).observe( topbar );
	}
	let ticking = false;
	function onScroll() {
		const y = window.scrollY;
		if ( header ) {
			header.classList.toggle( 'is-scrolled', y > 24 );
		}
		if ( toTop ) {
			toTop.hidden = y < 700;
		}
		ticking = false;
	}
	window.addEventListener( 'scroll', () => {
		if ( ! ticking ) {
			window.requestAnimationFrame( onScroll );
			ticking = true;
		}
	}, { passive: true } );
	onScroll();
	if ( toTop ) {
		toTop.addEventListener( 'click', () => window.scrollTo( { top: 0, behavior: reduceMotion ? 'auto' : 'smooth' } ) );
	}

	/* ------------------------------------------------------------------
	 * Menus: submenu toggles (touch + keyboard)
	 * ------------------------------------------------------------------ */
	document.addEventListener( 'click', ( e ) => {
		const toggle = e.target.closest( '.pt-submenu-toggle' );
		if ( toggle ) {
			e.preventDefault();
			const li = toggle.closest( 'li' );
			const open = ! li.classList.contains( 'is-open' );
			$$( ':scope > li.is-open', li.parentElement ).forEach( ( sib ) => {
				if ( sib !== li ) {
					sib.classList.remove( 'is-open' );
					const b = $( ':scope > .pt-submenu-toggle', sib );
					b && b.setAttribute( 'aria-expanded', 'false' );
				}
			} );
			li.classList.toggle( 'is-open', open );
			toggle.setAttribute( 'aria-expanded', String( open ) );
			return;
		}
		// Close open desktop submenus when clicking elsewhere.
		if ( ! e.target.closest( '.pt-menu' ) ) {
			$$( '.pt-menu li.is-open' ).forEach( ( li ) => li.classList.remove( 'is-open' ) );
		}
	} );

	/* ------------------------------------------------------------------
	 * Off-canvas menu
	 * ------------------------------------------------------------------ */
	const offcanvas = $( '#pt-offcanvas' );
	$$( '[data-pt-offcanvas-open]' ).forEach( ( b ) => b.addEventListener( 'click', () => showDialog( offcanvas, b ) ) );
	$$( '[data-pt-offcanvas-close]' ).forEach( ( b ) => b.addEventListener( 'click', () => hideDialog( offcanvas ) ) );
	window.matchMedia( '(min-width: 1100px)' ).addEventListener( 'change', ( m ) => m.matches && hideDialog( offcanvas ) );

	/* ------------------------------------------------------------------
	 * Mini cart drawer
	 * ------------------------------------------------------------------ */
	const minicart = $( '#pt-minicart' );
	if ( minicart ) {
		$$( '[data-pt-minicart-open]' ).forEach( ( a ) => a.addEventListener( 'click', ( e ) => {
			if ( e.metaKey || e.ctrlKey || e.shiftKey ) {
				return;
			}
			e.preventDefault();
			showDialog( minicart, a );
		} ) );
		$$( '[data-pt-minicart-close]' ).forEach( ( b ) => b.addEventListener( 'click', () => hideDialog( minicart ) ) );
		// WooCommerce fires this jQuery event after AJAX add-to-cart.
		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'added_to_cart', () => {
				showDialog( minicart, $( '[data-pt-minicart-open]' ) );
			} );
		}
	}

	/* ------------------------------------------------------------------
	 * Wishlist badge (YITH or any plugin using palltheme_wishlist_data)
	 * ------------------------------------------------------------------ */
	const wishBadge = $( '[data-pt-wishlist-count]' );
	if ( wishBadge && data.ajaxUrl ) {
		let wishTimer = null;
		const refreshWishlist = () => {
			window.clearTimeout( wishTimer );
			wishTimer = window.setTimeout( () => {
				fetch( data.ajaxUrl + '?action=palltheme_wishlist_count', { credentials: 'same-origin' } )
					.then( ( r ) => r.json() )
					.then( ( json ) => {
						const count = json && json.success ? Number( json.data.count ) : 0;
						wishBadge.textContent = String( count );
						wishBadge.hidden = ! count;
					} )
					.catch( () => {} );
			}, 1200 );
		};
		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'added_to_wishlist removed_from_wishlist yith_wcwl_fragments_replaced', refreshWishlist );
		}
		// YITH 4 renders React buttons that fire no jQuery events: watch their
		// "added"/"removed" state instead (only class changes on those buttons).
		if ( 'MutationObserver' in window ) {
			new MutationObserver( ( records ) => {
				if ( records.some( ( r ) => r.target.matches && r.target.matches( '[class*="yith-wcwl-add-to-wishlist-button"]' ) && ! /loading/.test( r.target.className ) ) ) {
					refreshWishlist();
				}
			} ).observe( document.body, { subtree: true, attributes: true, attributeFilter: [ 'class' ] } );
		}
	}

	/* ------------------------------------------------------------------
	 * Search overlay + live results
	 * ------------------------------------------------------------------ */
	const search = $( '#pt-search' );
	if ( search ) {
		const input = $( '[data-pt-search-input]', search );
		const results = $( '[data-pt-search-results]', search );
		const status = $( '#pt-search-status', search );
		let timer = null;
		let controller = null;
		let activeIndex = -1;

		$$( '[data-pt-search-open]' ).forEach( ( b ) => b.addEventListener( 'click', () => showDialog( search, b ) ) );
		$$( '[data-pt-search-close]' ).forEach( ( b ) => b.addEventListener( 'click', () => hideDialog( search ) ) );
		document.addEventListener( 'keydown', ( e ) => {
			const tag = ( e.target.tagName || '' ).toLowerCase();
			if ( e.key === '/' && ! openDialog && tag !== 'input' && tag !== 'textarea' && ! e.target.isContentEditable ) {
				e.preventDefault();
				showDialog( search, $( '[data-pt-search-open]' ) );
			}
		} );

		const esc = ( s ) => String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ] );

		function render( payload, q ) {
			activeIndex = -1;
			if ( ! payload || ! payload.groups || ! payload.groups.length ) {
				results.innerHTML = '';
				status.textContent = t( 'noResults', 'No results found' );
				return;
			}
			let html = '';
			payload.groups.forEach( ( group ) => {
				html += '<div class="pt-search__group">' + esc( group.label ) + '</div>';
				group.items.forEach( ( item ) => {
					html += '<a class="pt-search__item" href="' + esc( item.url ) + '">' +
						'<span class="pt-search__thumb">' + ( item.thumb ? '<img src="' + esc( item.thumb ) + '" alt="" loading="lazy" width="48" height="48">' : '<b aria-hidden="true">' + esc( String( item.title ).charAt( 0 ) ) + '</b>' ) + '</span>' +
						'<span><span class="pt-search__title">' + esc( item.title ) + '</span>' +
						( item.sub ? '<span class="pt-search__sub">' + esc( item.sub ) + '</span>' : '' ) + '</span>' +
						( item.price ? '<span class="pt-search__price">' + esc( item.price ) + '</span>' : '' ) +
						'</a>';
				} );
			} );
			const viewAll = t( 'viewAll', 'View all %d results' ).replace( '%d', payload.total );
			html += '<a class="pt-search__all" href="' + esc( data.searchUrl + '?s=' + encodeURIComponent( q ) ) + '">' + esc( viewAll ) + '</a>';
			results.innerHTML = html;
			status.textContent = viewAll;
		}

		function query( q ) {
			if ( controller ) {
				controller.abort();
			}
			if ( q.length < 2 || ! data.searchEndpoint ) {
				results.innerHTML = '';
				status.textContent = '';
				return;
			}
			controller = new AbortController();
			status.textContent = t( 'searching', 'Searching…' );
			const url = data.searchEndpoint + ( data.searchEndpoint.includes( '?' ) ? '&' : '?' ) + 'q=' + encodeURIComponent( q );
			fetch( url, { signal: controller.signal, headers: { Accept: 'application/json' } } )
				.then( ( r ) => ( r.ok ? r.json() : null ) )
				.then( ( json ) => render( json, q ) )
				.catch( ( err ) => {
					if ( err.name !== 'AbortError' ) {
						status.textContent = '';
					}
				} );
		}

		input.addEventListener( 'input', () => {
			window.clearTimeout( timer );
			timer = window.setTimeout( () => query( input.value.trim() ), 220 );
		} );
		input.addEventListener( 'keydown', ( e ) => {
			const items = $$( '.pt-search__item, .pt-search__all', results );
			if ( ! items.length || ( e.key !== 'ArrowDown' && e.key !== 'ArrowUp' ) ) {
				return;
			}
			e.preventDefault();
			activeIndex = e.key === 'ArrowDown' ? Math.min( items.length - 1, activeIndex + 1 ) : Math.max( 0, activeIndex - 1 );
			items[ activeIndex ].focus();
		} );
		results.addEventListener( 'keydown', ( e ) => {
			if ( e.key !== 'ArrowDown' && e.key !== 'ArrowUp' ) {
				return;
			}
			const items = $$( '.pt-search__item, .pt-search__all', results );
			e.preventDefault();
			activeIndex = items.indexOf( document.activeElement );
			activeIndex += e.key === 'ArrowDown' ? 1 : -1;
			if ( activeIndex < 0 ) {
				input.focus();
				return;
			}
			items[ Math.min( items.length - 1, activeIndex ) ].focus();
		} );
	}

	/* ------------------------------------------------------------------
	 * Scroll reveal
	 * ------------------------------------------------------------------ */
	const revealEls = $$( '.pt-reveal' );
	if ( animationsOn && 'IntersectionObserver' in window ) {
		const io = new IntersectionObserver( ( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-visible' );
					io.unobserve( entry.target );
				}
			} );
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 } );
		revealEls.forEach( ( el, i ) => {
			el.style.transitionDelay = ( ( i % 4 ) * 70 ) + 'ms';
			io.observe( el );
		} );
	} else {
		revealEls.forEach( ( el ) => el.classList.add( 'is-visible' ) );
	}

	/* ------------------------------------------------------------------
	 * Animated counters: "500+", "99.99%", "$2.4M", "24/7" (non-numeric kept)
	 * ------------------------------------------------------------------ */
	function animateCount( el ) {
		const raw = el.getAttribute( 'data-pt-count' ) || el.textContent;
		const match = raw.match( /^([^0-9]*)([0-9][0-9,]*\.?[0-9]*)(.*)$/ );
		if ( ! match || /\d\s*\/\s*\d/.test( raw ) ) {
			return;
		}
		const prefix = match[ 1 ];
		const numStr = match[ 2 ].replace( /,/g, '' );
		const suffix = match[ 3 ];
		const target = parseFloat( numStr );
		const decimals = ( numStr.split( '.' )[ 1 ] || '' ).length;
		const useComma = match[ 2 ].includes( ',' );
		const duration = 1600;
		const start = performance.now();
		const fmt = ( n ) => {
			let s = n.toFixed( decimals );
			if ( useComma ) {
				s = Number( s ).toLocaleString( undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals } );
			}
			return prefix + s + suffix;
		};
		function frame( now ) {
			const p = Math.min( 1, ( now - start ) / duration );
			const eased = 1 - Math.pow( 1 - p, 3 );
			el.textContent = fmt( target * eased );
			if ( p < 1 ) {
				window.requestAnimationFrame( frame );
			} else {
				el.textContent = raw;
			}
		}
		window.requestAnimationFrame( frame );
	}
	const counters = $$( '[data-pt-count]' );
	if ( animationsOn && counters.length && 'IntersectionObserver' in window ) {
		const cio = new IntersectionObserver( ( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					animateCount( entry.target );
					cio.unobserve( entry.target );
				}
			} );
		}, { threshold: 0.5 } );
		counters.forEach( ( c ) => cio.observe( c ) );
	}

	/* ------------------------------------------------------------------
	 * Sliders (CSS scroll-snap + buttons)
	 * ------------------------------------------------------------------ */
	$$( '[data-pt-slider]' ).forEach( ( slider ) => {
		const track = $( '.pt-slider__track', slider );
		if ( ! track ) {
			return;
		}
		$$( '[data-dir]', slider ).forEach( ( btn ) => btn.addEventListener( 'click', () => {
			const dir = Number( btn.getAttribute( 'data-dir' ) ) * ( document.dir === 'rtl' ? -1 : 1 );
			const card = track.firstElementChild;
			const step = card ? card.getBoundingClientRect().width + 20 : track.clientWidth;
			track.scrollBy( { left: dir * step, behavior: reduceMotion ? 'auto' : 'smooth' } );
		} ) );
		const autoplay = Number( slider.getAttribute( 'data-autoplay' ) || 0 );
		if ( autoplay && animationsOn ) {
			let paused = false;
			slider.addEventListener( 'mouseenter', () => ( paused = true ) );
			slider.addEventListener( 'mouseleave', () => ( paused = false ) );
			slider.addEventListener( 'focusin', () => ( paused = true ) );
			window.setInterval( () => {
				if ( paused || document.hidden ) {
					return;
				}
				const atEnd = Math.abs( track.scrollLeft ) + track.clientWidth >= track.scrollWidth - 4;
				const card = track.firstElementChild;
				const step = card ? card.getBoundingClientRect().width + 20 : track.clientWidth;
				track.scrollTo( { left: atEnd ? 0 : track.scrollLeft + ( document.dir === 'rtl' ? -step : step ), behavior: 'smooth' } );
			}, autoplay );
		}
	} );

	/* ------------------------------------------------------------------
	 * Technology filter tabs
	 * ------------------------------------------------------------------ */
	$$( '[data-pt-tech-filter]' ).forEach( ( bar ) => {
		const grid = bar.nextElementSibling;
		bar.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( 'button[data-cat]' );
			if ( ! btn || ! grid ) {
				return;
			}
			$$( 'button', bar ).forEach( ( b ) => {
				b.classList.toggle( 'is-active', b === btn );
				b.setAttribute( 'aria-pressed', String( b === btn ) );
			} );
			const cat = btn.getAttribute( 'data-cat' );
			$$( '[data-cats]', grid ).forEach( ( item ) => {
				item.hidden = cat !== '*' && ! item.getAttribute( 'data-cats' ).split( ' ' ).includes( cat );
			} );
		} );
	} );

	/* ------------------------------------------------------------------
	 * Background videos: desktop only, never with reduced motion / Save-Data
	 * ------------------------------------------------------------------ */
	const saveData = navigator.connection && navigator.connection.saveData;
	if ( data.videos !== false && ! reduceMotion && ! saveData && window.matchMedia( '(min-width: 768px)' ).matches ) {
		$$( 'video[data-pt-video]' ).forEach( ( video ) => {
			$$( 'source[data-src]', video ).forEach( ( s ) => {
				s.src = s.getAttribute( 'data-src' );
			} );
			video.load();
			video.addEventListener( 'canplay', () => {
				video.classList.add( 'is-ready' );
				video.play().catch( () => {} );
			}, { once: true } );
		} );
	}

	/* ------------------------------------------------------------------
	 * Lottie (only loads the player when a Lottie element exists)
	 * ------------------------------------------------------------------ */
	const lotties = $$( '[data-pt-lottie]' );
	if ( lotties.length && animationsOn ) {
		const s = document.createElement( 'script' );
		s.src = 'https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie_light.min.js';
		s.async = true;
		s.onload = () => lotties.forEach( ( el ) => window.lottie && window.lottie.loadAnimation( {
			container: el,
			renderer: 'svg',
			loop: true,
			autoplay: true,
			path: el.getAttribute( 'data-pt-lottie' ),
		} ) );
		document.head.appendChild( s );
	}

	/* ------------------------------------------------------------------
	 * Copy link
	 * ------------------------------------------------------------------ */
	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-pt-copy]' );
		if ( ! btn || ! navigator.clipboard ) {
			return;
		}
		navigator.clipboard.writeText( btn.getAttribute( 'data-pt-copy' ) ).then( () => {
			btn.classList.add( 'is-copied' );
			window.setTimeout( () => btn.classList.remove( 'is-copied' ), 1600 );
		} );
	} );
}() );
