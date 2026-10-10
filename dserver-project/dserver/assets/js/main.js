/* DServer theme — header behaviour (no dependencies). */
( function () {
	'use strict';
	const header = document.querySelector( '[data-ds-header]' );
	const burger = document.querySelector( '[data-ds-burger]' );
	const nav = document.getElementById( 'ds-nav' );

	const onScroll = () => header && header.classList.toggle( 'is-scrolled', window.scrollY > 10 );
	window.addEventListener( 'scroll', onScroll, { passive: true } );
	onScroll();

	if ( burger && nav ) {
		const setOpen = ( open ) => {
			nav.classList.toggle( 'is-open', open );
			burger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			document.documentElement.style.overflow = open ? 'hidden' : '';
		};
		burger.addEventListener( 'click', () => setOpen( ! nav.classList.contains( 'is-open' ) ) );
		nav.addEventListener( 'click', ( e ) => e.target.closest( 'a' ) && setOpen( false ) );
		document.addEventListener( 'keydown', ( e ) => e.key === 'Escape' && setOpen( false ) );
		window.matchMedia( '(min-width: 1101px)' ).addEventListener( 'change', ( m ) => m.matches && setOpen( false ) );
	}
}() );
