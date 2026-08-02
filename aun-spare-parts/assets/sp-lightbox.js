( function () {
	'use strict';

	/**
	 * Shared image lightbox for the reference / "see example" photos on both the
	 * request form and the tracking page.
	 *
	 * Deliberately dependency-free (no lightbox library) so there is nothing extra
	 * for WP Rocket to optimise and nothing to keep updated. Behaviour:
	 *   - fades the backdrop in and scales the image up instead of appearing instantly
	 *   - real close button, Escape, and backdrop click all dismiss it
	 *   - spinner until the full-size image has actually decoded
	 *   - locks page scroll while open and restores focus to the thumbnail after
	 *   - honours prefers-reduced-motion (handled in CSS)
	 */

	var DEFAULTS = {
		en: { lb_close: 'Close' },
		bn: { lb_close: 'বন্ধ করুন' }
	};

	/** Resolve the close label from the widget's own translation payload. */
	function closeLabel( root ) {
		var lang = ( root && root.getAttribute( 'data-lang' ) ) === 'en' ? 'en' : 'bn';
		var txt  = DEFAULTS[ lang ].lb_close;
		try {
			var o = JSON.parse( ( root && root.getAttribute( 'data-i18n' ) ) || 'null' );
			if ( o && o[ lang ] && o[ lang ].lb_close ) { txt = o[ lang ].lb_close; }
		} catch ( e ) {}
		return txt;
	}

	var open = null; // only ever one lightbox at a time

	/**
	 * @param {boolean} immediate Skip the fade-out and tear down now. Used when a
	 *        second image is opened while this one is still closing — otherwise both
	 *        overlays sit in the DOM together and the stale one covers the new image.
	 */
	function close( immediate ) {
		if ( ! open ) { return; }
		var o = open;
		open = null;

		document.removeEventListener( 'keydown', o.onKey );
		document.documentElement.style.overflow = o.prevOverflow;
		o.box.classList.remove( 'is-open' );

		var done = false;
		var drop = function ( restoreFocus ) {
			if ( done ) { return; }
			done = true;
			if ( o.box.parentNode ) { o.box.parentNode.removeChild( o.box ); }
			if ( restoreFocus && o.opener && document.contains( o.opener ) ) { o.opener.focus(); }
		};

		if ( immediate ) {
			drop( false ); // the caller is about to take focus itself
			return;
		}

		// Let the fade-out finish before removing — with a timeout fallback for when
		// no transition runs at all (reduced motion, background tab).
		o.box.addEventListener( 'transitionend', function () { drop( true ); } );
		setTimeout( function () { drop( true ); }, 320 );
	}

	function openLightbox( src, caption, root, opener ) {
		if ( ! src ) { return; }
		close( true ); // tear down any previous one instantly, never stack overlays

		var box = document.createElement( 'div' );
		box.className = 'aun-sp-lb is-loading';
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-modal', 'true' );
		if ( caption ) { box.setAttribute( 'aria-label', caption ); }

		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'aun-sp-lb-close';
		btn.setAttribute( 'aria-label', closeLabel( root ) );
		btn.innerHTML = '&times;';

		var spin = document.createElement( 'div' );
		spin.className = 'aun-sp-lb-spin';
		spin.setAttribute( 'aria-hidden', 'true' );

		var fig = document.createElement( 'figure' );
		fig.className = 'aun-sp-lb-fig';

		var img = document.createElement( 'img' );
		img.className = 'aun-sp-lb-img';
		img.alt = caption || '';
		img.addEventListener( 'load', function () { box.classList.remove( 'is-loading' ); } );
		img.addEventListener( 'error', function () { box.classList.remove( 'is-loading' ); box.classList.add( 'is-error' ); } );
		img.src = src;

		fig.appendChild( img );
		if ( caption ) {
			var cap = document.createElement( 'figcaption' );
			cap.className = 'aun-sp-lb-cap';
			cap.textContent = caption;
			fig.appendChild( cap );
		}

		box.appendChild( spin );
		box.appendChild( fig );
		box.appendChild( btn );

		// Backdrop click closes; clicks on the image itself must not.
		box.addEventListener( 'click', function ( e ) { if ( e.target === box || e.target === fig ) { close(); } } );
		btn.addEventListener( 'click', close );

		var onKey = function ( e ) {
			if ( e.key === 'Escape' ) { close(); }
			// Focus is parked on the close button — keep Tab inside the dialog.
			if ( e.key === 'Tab' ) { e.preventDefault(); btn.focus(); }
		};
		document.addEventListener( 'keydown', onKey );

		var prevOverflow = document.documentElement.style.overflow;
		document.documentElement.style.overflow = 'hidden';

		open = { box: box, onKey: onKey, prevOverflow: prevOverflow, opener: opener || null };
		document.body.appendChild( box );

		// Force a reflow so the browser commits the closed styles, then flip the class
		// in the SAME tick — the transition then runs from those styles. (Deliberately
		// not requestAnimationFrame: rAF is paused in hidden/background tabs, which would
		// leave the lightbox stuck at opacity 0 instead of just skipping the animation.)
		void box.offsetWidth;
		box.classList.add( 'is-open' );
		btn.focus();
	}

	// Delegated: works for thumbnails rendered later by JS (tracking re-upload box).
	document.addEventListener( 'click', function ( e ) {
		var a = e.target && e.target.closest ? e.target.closest( '.aun-sp-refimg' ) : null;
		if ( ! a ) { return; }
		e.preventDefault();
		openLightbox(
			a.getAttribute( 'data-full' ) || a.getAttribute( 'href' ),
			a.getAttribute( 'data-caption' ) || '',
			a.closest( '.aun-sp' ),
			a
		);
	} );

	// Exposed so the form/tracking scripts can reuse it if they ever need to.
	window.aunSpLightbox = openLightbox;
} )();
