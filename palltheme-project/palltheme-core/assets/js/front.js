/* Palltheme Core — tiny front-end helpers. */
( function () {
	'use strict';
	// Click-to-load Google Maps (no third-party request until the visitor asks).
	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-pt-map-load]' );
		if ( ! btn ) {
			return;
		}
		const box = btn.closest( '[data-pt-map]' );
		const iframe = document.createElement( 'iframe' );
		iframe.src = box.getAttribute( 'data-pt-map' );
		iframe.loading = 'lazy';
		iframe.title = btn.textContent.trim();
		iframe.referrerPolicy = 'no-referrer-when-downgrade';
		box.innerHTML = '';
		box.appendChild( iframe );
	} );

	// Recently viewed products (stored only in this browser).
	const recent = document.querySelector( '[data-pall-recent]' );
	if ( recent ) {
		const current = Number( recent.getAttribute( 'data-pall-recent' ) );
		let ids = [];
		try {
			ids = JSON.parse( localStorage.getItem( 'pall-recent' ) || '[]' ).filter( Number.isInteger );
		} catch ( err ) {}
		const others = ids.filter( ( id ) => id !== current ).slice( 0, 4 );
		try {
			localStorage.setItem( 'pall-recent', JSON.stringify( [ current ].concat( ids.filter( ( id ) => id !== current ) ).slice( 0, 12 ) ) );
		} catch ( err ) {}
		if ( others.length ) {
			const url = recent.getAttribute( 'data-endpoint' ) + ( recent.getAttribute( 'data-endpoint' ).includes( '?' ) ? '&' : '?' ) + 'ids=' + others.join( ',' );
			fetch( url, { headers: { Accept: 'application/json' } } )
				.then( ( r ) => r.json() )
				.then( ( json ) => {
					if ( json && json.html ) {
						recent.querySelector( '[data-pall-recent-list]' ).innerHTML = json.html;
						recent.hidden = false;
					}
				} )
				.catch( () => {} );
		}
	}
}() );
