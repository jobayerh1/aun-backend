/* Palltheme Core — admin field UI (media pickers, repeaters, filters, icon preview). */
( function () {
	'use strict';
	const i18n = window.pallcoreAdmin || {};

	document.addEventListener( 'click', ( e ) => {
		// Media picker.
		const pick = e.target.closest( '[data-pallcore-media-pick]' );
		if ( pick && window.wp && wp.media ) {
			e.preventDefault();
			const box = pick.closest( '[data-pallcore-media]' );
			const multiple = box.getAttribute( 'data-pallcore-media' ) === 'multiple';
			const input = box.querySelector( 'input[type=hidden]' );
			const preview = box.querySelector( '.pallcore-media__preview' );
			const frame = wp.media( {
				title: multiple ? i18n.chooseImages : i18n.chooseImage,
				button: { text: i18n.use || 'Use' },
				multiple: multiple ? 'add' : false,
				library: { type: 'image' },
			} );
			frame.on( 'open', () => {
				const sel = frame.state().get( 'selection' );
				input.value.split( ',' ).filter( Boolean ).forEach( ( id ) => sel.add( wp.media.attachment( id ) ) );
			} );
			frame.on( 'select', () => {
				const items = frame.state().get( 'selection' ).toJSON();
				input.value = items.map( ( a ) => a.id ).join( ',' );
				preview.innerHTML = items.map( ( a ) => {
					const url = ( a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url );
					return '<img src="' + url + '" alt="" width="80" height="80">';
				} ).join( '' );
			} );
			frame.open();
			return;
		}

		const clear = e.target.closest( '[data-pallcore-media-clear]' );
		if ( clear ) {
			e.preventDefault();
			const box = clear.closest( '[data-pallcore-media]' );
			box.querySelector( 'input[type=hidden]' ).value = '';
			box.querySelector( '.pallcore-media__preview' ).innerHTML = '';
			return;
		}

		// Repeater rows.
		const add = e.target.closest( '[data-pallcore-pairs-add]' );
		if ( add ) {
			e.preventDefault();
			const wrap = add.closest( '[data-pallcore-pairs]' );
			const rows = wrap.querySelector( '.pallcore-pairs__rows' );
			const tpl = wrap.querySelector( 'template' ).innerHTML.replace( /__i__/g, String( Date.now() ) );
			rows.insertAdjacentHTML( 'beforeend', tpl );
			rows.lastElementChild.querySelector( 'input' ).focus();
			return;
		}
		const remove = e.target.closest( '[data-pallcore-pairs-remove]' );
		if ( remove ) {
			e.preventDefault();
			const rows = remove.closest( '.pallcore-pairs__rows' );
			if ( rows.children.length > 1 ) {
				remove.closest( '.pallcore-pairs__row' ).remove();
			} else {
				remove.closest( '.pallcore-pairs__row' ).querySelectorAll( 'input,textarea' ).forEach( ( f ) => ( f.value = '' ) );
			}
		}
	} );

	// Filter long select lists.
	document.addEventListener( 'input', ( e ) => {
		const f = e.target.closest( '[data-pallcore-filter]' );
		if ( ! f ) {
			return;
		}
		const select = document.getElementById( f.getAttribute( 'data-pallcore-filter' ) );
		const q = f.value.toLowerCase();
		Array.from( select.options ).forEach( ( o ) => {
			o.hidden = q && ! o.selected && ! o.text.toLowerCase().includes( q );
		} );
	} );

	// Icon preview.
	document.addEventListener( 'change', ( e ) => {
		const sel = e.target.closest( '[data-pallcore-icon]' );
		if ( ! sel ) {
			return;
		}
		const preview = sel.parentElement.querySelector( '[data-pallcore-icon-preview]' );
		const tpl = document.getElementById( 'pallcore-icon-templates' );
		const icon = tpl && tpl.content.querySelector( '[data-icon="' + sel.value + '"]' );
		preview.innerHTML = icon ? icon.innerHTML : '';
	} );
}() );
