( function () {
	'use strict';

	var I18N = {
		en: {
			searching: 'Searching…', submitting: 'Submitting…',
			enter_number: 'Please enter your number.',
			choose_part: 'Please choose at least one part.',
			need_photo: 'Please add the required photo for: ',
			too_large: 'That photo is over 15 MB — please choose a smaller one for: ',
			net_err: 'Network error — please try again.',
			not_found: "We couldn't find that purchase.",
			verified: 'verified', on_record: 'on record',
			w_in: 'Yes — in warranty', w_out: 'No — out of warranty', w_unknown: 'Purchase date unknown',
			open_one: 'You already have a request in progress:',
			open_many: 'You already have {n} requests in progress:',
			open_track: 'Track it',
			dup_h: 'You have already requested this part',
			dup_body: 'We are still working on your earlier request. Sending it again will not make it faster — it only creates a second request we have to cancel.',
			dup_track: 'Track my existing request',
			dup_anyway: 'This is a different problem — submit anyway',
			dup_cancel: 'Cancel',
			chosen_photo: 'Your photo',
			too_large_1: 'That photo is over 15 MB — please choose a smaller one.'
		},
		bn: {
			searching: 'খোঁজা হচ্ছে…', submitting: 'জমা হচ্ছে…',
			enter_number: 'আপনার নম্বর লিখুন।',
			choose_part: 'অন্তত একটি পার্টস নির্বাচন করুন।',
			need_photo: 'এই অংশের জন্য ছবি দিন: ',
			too_large: 'ছবিটি ১৫ MB-এর বেশি — অনুগ্রহ করে ছোট ছবি দিন: ',
			net_err: 'নেটওয়ার্ক সমস্যা — আবার চেষ্টা করুন।',
			not_found: 'আপনার ক্রয় খুঁজে পাওয়া যায়নি।',
			verified: 'যাচাইকৃত', on_record: 'রেকর্ডে আছে',
			w_in: 'হ্যাঁ — ওয়ারেন্টিতে আছে', w_out: 'না — ওয়ারেন্টির বাইরে', w_unknown: 'ক্রয়ের তারিখ অজানা',
			open_one: 'আপনার একটি অনুরোধ ইতিমধ্যেই চলমান আছে:',
			open_many: 'আপনার ইতিমধ্যেই {n} টি অনুরোধ চলমান আছে:',
			open_track: 'ট্র্যাক করুন',
			dup_h: 'আপনি এই পার্টসটি ইতিমধ্যেই চেয়েছেন',
			dup_body: 'আপনার আগের অনুরোধটি নিয়ে আমরা এখনো কাজ করছি। আবার পাঠালে দ্রুত হবে না — বরং একটি দ্বিতীয় অনুরোধ তৈরি হবে যা আমাদের বাতিল করতে হবে।',
			dup_track: 'আমার চলমান অনুরোধ ট্র্যাক করুন',
			dup_anyway: 'এটি ভিন্ন সমস্যা — তবুও জমা দিন',
			dup_cancel: 'বাতিল',
			chosen_photo: 'আপনার ছবি',
			too_large_1: 'ছবিটি ১৫ MB-এর বেশি — অনুগ্রহ করে ছোট ছবি দিন।'
		}
	};

	// Same ceiling the server enforces (see ajax_submit).
	var MAX_BYTES = 15 * 1024 * 1024;

	function init( root ) {
		var input     = root.querySelector( '.aun-sp-q' );
		var findBtn   = root.querySelector( '.aun-sp-find-btn' );
		var foundForm = root.querySelector( '.aun-sp-found' );
		// Bail on non-form roots (e.g. the tracking widget also carries .aun-sp).
		if ( ! findBtn || ! foundForm ) { return; }

		var ajax      = root.getAttribute( 'data-ajax' );
		var nonce     = root.getAttribute( 'data-nonce' );

		// Merge admin-edited translations (Spare Parts → Translations) over the defaults.
		try {
			var _o = JSON.parse( root.getAttribute( 'data-i18n' ) || 'null' );
			if ( _o ) {
				[ 'en', 'bn' ].forEach( function ( L ) {
					if ( _o[ L ] ) { for ( var k in _o[ L ] ) { I18N[ L ][ k ] = _o[ L ][ k ]; } }
				} );
			}
		} catch ( e ) {}

		var tabs      = root.querySelectorAll( '.aun-sp-tab' );
		var findMsg   = root.querySelector( '.aun-sp-find-msg' );
		var chooser   = root.querySelector( '.aun-sp-chooser' );
		var deviceSel = root.querySelector( '.aun-sp-device' );
		var submitBtn = root.querySelector( '.aun-sp-submit-btn' );
		var submitMsg = root.querySelector( '.aun-sp-submit-msg' );
		var doneBox   = root.querySelector( '.aun-sp-done' );
		var changeBtn = root.querySelector( '.aun-sp-change' );
		var cancelBtn = root.querySelector( '.aun-sp-cancel' );
		var editBox   = root.querySelector( '.aun-sp-edit' );
		var onfileBox = root.querySelector( '.aun-sp-onfile' );
		var changedFl = root.querySelector( '[name="contact_changed"]' );
		var forkBox   = root.querySelector( '.aun-sp-fork' );
		var partsPanel = root.querySelector( '.aun-sp-parts-panel' );
		var choosePartsBtn = root.querySelector( '.aun-sp-choose-parts' );
		var ongoingBox = root.querySelector( '.aun-sp-ongoing' );

		var searchBy   = 'mobile';
		var foundQuery = '';
		var matches    = [];
		var selected   = 0;
		var placeholders = { mobile: '01XXXXXXXXX', order: 'e.g. SO-000123', serial: 'serial number' };

		// Language comes from the server (TranslatePress / WP locale) — there is no
		// in-plugin toggle any more, so the widget always matches the page it sits on.
		var lang = root.getAttribute( 'data-lang' ) === 'en' ? 'en' : 'bn';
		function t( k ) { return ( I18N[ lang ] && I18N[ lang ][ k ] ) || I18N.en[ k ] || k; }
		function warrantyLabel( w ) {
			if ( ! w || ! w.known ) { return t( 'w_unknown' ); }
			return w.in_warranty ? t( 'w_in' ) : t( 'w_out' );
		}

		function openEdit() { if ( editBox ) { editBox.hidden = false; } if ( onfileBox ) { onfileBox.hidden = true; } if ( changedFl ) { changedFl.value = '1'; } }
		function closeEdit() {
			if ( editBox ) {
				editBox.hidden = true;
				// Wipe anything typed — otherwise "use my details on file" still submits
				// the abandoned phone/address and the server ships to the wrong place.
				editBox.querySelectorAll( 'input, textarea' ).forEach( function ( f ) { f.value = ''; } );
			}
			if ( onfileBox ) { onfileBox.hidden = false; }
			if ( changedFl ) { changedFl.value = '0'; }
		}
		if ( changeBtn ) { changeBtn.addEventListener( 'click', openEdit ); }
		if ( cancelBtn ) { cancelBtn.addEventListener( 'click', closeEdit ); }

		// Fork: after a match the customer first chooses "spare part" or "send projector".
		// "Send projector" is a plain link; "spare part" reveals the parts/delivery panel.
		function resetFork() { if ( forkBox ) { forkBox.hidden = false; } if ( partsPanel ) { partsPanel.hidden = true; } }
		if ( choosePartsBtn ) {
			choosePartsBtn.addEventListener( 'click', function () {
				if ( forkBox ) { forkBox.hidden = true; }
				if ( partsPanel ) { partsPanel.hidden = false; partsPanel.scrollIntoView( { behavior: 'smooth', block: 'nearest' } ); }
			} );
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				tabs.forEach( function ( o ) { o.classList.remove( 'is-active' ); } );
				tab.classList.add( 'is-active' );
				searchBy = tab.getAttribute( 'data-sb' );
				input.placeholder = placeholders[ searchBy ] || '';
				input.focus();
			} );
		} );

		function msg( el, text, kind ) { el.textContent = text || ''; el.classList.remove( 'is-error', 'is-ok' ); if ( kind ) { el.classList.add( kind ); } }

		// POST helper with one automatic retry on a stale nonce: the page (and its
		// embedded nonce) can be served from a long-lived cache, so on bad_nonce we
		// fetch a fresh nonce from admin-ajax and re-send the same request once.
		function post( action, fd ) {
			fd.append( 'action', action );
			fd.append( '_nonce', nonce );
			// admin-ajax runs outside TranslatePress's URL context, so tell the server
			// which language this page was rendered in (it replies in that language).
			fd.append( 'lang', lang );
			return fetch( ajax, { method: 'POST', credentials: 'same-origin', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && ! res.success && res.data && res.data.code === 'bad_nonce' ) {
						var nf = new FormData();
						nf.append( 'action', 'aun_sp_nonce' );
						return fetch( ajax, { method: 'POST', credentials: 'same-origin', body: nf } )
							.then( function ( r ) { return r.json(); } )
							.then( function ( nr ) {
								if ( ! nr || ! nr.success || ! nr.data || ! nr.data.nonce ) { return res; }
								nonce = nr.data.nonce;
								fd.set( '_nonce', nonce );
								return fetch( ajax, { method: 'POST', credentials: 'same-origin', body: fd } ).then( function ( r ) { return r.json(); } );
							} );
					}
					return res;
				} );
		}

		// ── "You already have a request in progress" ────────────────────────────────
		// Shown as soon as we recognise the customer, i.e. BEFORE they fill anything
		// in. Most duplicate submissions are people who forgot they already asked.
		function renderOngoing( list ) {
			if ( ! ongoingBox ) { return; }
			ongoingBox.textContent = '';
			if ( ! list || ! list.length ) { ongoingBox.hidden = true; return; }

			var head = document.createElement( 'div' );
			head.className = 'aun-sp-ongoing-h';
			head.textContent = list.length === 1 ? t( 'open_one' ) : t( 'open_many' ).replace( '{n}', list.length );
			ongoingBox.appendChild( head );

			list.forEach( function ( o ) {
				var row = document.createElement( 'div' );
				row.className = 'aun-sp-ongoing-row';

				var left = document.createElement( 'div' );
				var ref  = document.createElement( 'strong' );
				ref.textContent = o.ref;
				left.appendChild( ref );
				var meta = document.createElement( 'div' );
				meta.className = 'aun-sp-ongoing-meta';
				meta.textContent = ( o.parts || '' ) + ( o.created ? ' · ' + o.created : '' );
				left.appendChild( meta );
				row.appendChild( left );

				var right = document.createElement( 'div' );
				right.className = 'aun-sp-ongoing-right';
				var chip = document.createElement( 'span' );
				chip.className = 'aun-sp-warranty mid';
				chip.textContent = o.status;
				right.appendChild( chip );
				if ( o.url ) {
					var a = document.createElement( 'a' );
					a.className = 'aun-sp-ongoing-link';
					a.href = o.url;
					a.textContent = t( 'open_track' ) + ' →';
					right.appendChild( a );
				}
				row.appendChild( right );
				ongoingBox.appendChild( row );
			} );
			ongoingBox.hidden = false;
		}

		// ── Duplicate confirmation ──────────────────────────────────────────────────
		// The server refuses a submission that overlaps an open request unless the
		// customer explicitly confirms. This is that confirmation: a modal naming the
		// existing request, with "track it" as the easy path and an explicit opt-in to
		// continue. Resubmits the SAME FormData plus confirm_duplicate=1.
		function showDuplicateDialog( dupes, resubmit ) {
			var ov = document.createElement( 'div' );
			ov.className = 'aun-sp-modal';

			var box = document.createElement( 'div' );
			box.className = 'aun-sp-modal-box';
			box.setAttribute( 'role', 'dialog' );
			box.setAttribute( 'aria-modal', 'true' );

			var h = document.createElement( 'div' );
			h.className = 'aun-sp-modal-h';
			h.textContent = t( 'dup_h' );
			box.appendChild( h );

			var p = document.createElement( 'p' );
			p.className = 'aun-sp-modal-p';
			p.textContent = t( 'dup_body' );
			box.appendChild( p );

			( dupes || [] ).forEach( function ( d ) {
				var row = document.createElement( 'div' );
				row.className = 'aun-sp-modal-row';
				var s = document.createElement( 'strong' );
				s.textContent = d.ref;
				row.appendChild( s );
				var m = document.createElement( 'div' );
				m.className = 'aun-sp-ongoing-meta';
				m.textContent = ( d.parts || '' ) + ( d.created ? ' · ' + d.created : '' );
				row.appendChild( m );
				var chip = document.createElement( 'span' );
				chip.className = 'aun-sp-warranty mid';
				chip.textContent = d.status;
				row.appendChild( chip );
				box.appendChild( row );
			} );

			var acts = document.createElement( 'div' );
			acts.className = 'aun-sp-modal-actions';

			var trackUrl = ( dupes && dupes[0] && dupes[0].url ) || root.getAttribute( 'data-track-url' ) || '';
			if ( trackUrl ) {
				var go = document.createElement( 'a' );
				go.className = 'aun-sp-btn';
				go.href = trackUrl;
				go.textContent = t( 'dup_track' );
				acts.appendChild( go );
			}

			var anyway = document.createElement( 'button' );
			anyway.type = 'button';
			anyway.className = 'aun-sp-btn aun-sp-btn-ghost';
			anyway.textContent = t( 'dup_anyway' );
			anyway.addEventListener( 'click', function () { close(); resubmit(); } );
			acts.appendChild( anyway );

			var cancel = document.createElement( 'button' );
			cancel.type = 'button';
			cancel.className = 'aun-sp-modal-cancel';
			cancel.textContent = t( 'dup_cancel' );
			cancel.addEventListener( 'click', function () { close(); } );
			acts.appendChild( cancel );

			box.appendChild( acts );
			ov.appendChild( box );

			function close() {
				if ( ov.parentNode ) { ov.parentNode.removeChild( ov ); }
				document.removeEventListener( 'keydown', onKey );
			}
			function onKey( e ) { if ( e.key === 'Escape' ) { close(); } }
			ov.addEventListener( 'click', function ( e ) { if ( e.target === ov ) { close(); } } );
			document.addEventListener( 'keydown', onKey );

			document.body.appendChild( ov );
			anyway.focus();
		}

		function rebuildDropdown() {
			deviceSel.innerHTML = '';
			matches.forEach( function ( m, i ) {
				var opt = document.createElement( 'option' );
				opt.value = String( i );
				opt.textContent = ( m.model || 'Purchase' ) + ' — ' + ( m.purchase_date || '' ) + ' (' + warrantyLabel( m.warranty ) + ')';
				deviceSel.appendChild( opt );
			} );
		}

		function doFind() {
			var q = input.value.trim();
			if ( ! q ) { msg( findMsg, t( 'enter_number' ), 'is-error' ); return; }
			findBtn.disabled = true;
			msg( findMsg, t( 'searching' ) );
			// Clear any previously found purchase, so a new search that finds nothing (or
			// errors) never leaves a stale result showing below.
			foundForm.hidden = true;
			if ( chooser ) { chooser.hidden = true; }
			if ( ongoingBox ) { ongoingBox.hidden = true; ongoingBox.textContent = ''; }
			matches = [];
			var fd = new FormData();
			fd.append( 'search_by', searchBy );
			fd.append( 'query', q );
			post( 'aun_sp_find', fd ).then( function ( res ) {
				findBtn.disabled = false;
				if ( ! res || ! res.success ) { msg( findMsg, ( res && res.data && res.data.message ) || t( 'not_found' ), 'is-error' ); return; }
				matches    = res.data.matches || [];
				foundQuery = q;
				if ( ! matches.length ) { msg( findMsg, t( 'not_found' ), 'is-error' ); return; }
				msg( findMsg, '' );
				if ( matches.length > 1 ) { rebuildDropdown(); chooser.hidden = false; } else { chooser.hidden = true; }
				selectMatch( 0, true );
				renderOngoing( res.data.ongoing );
				resetFork();
				foundForm.hidden = false;
				foundForm.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			} ).catch( function () { findBtn.disabled = false; msg( findMsg, t( 'net_err' ), 'is-error' ); } );
		}

		findBtn.addEventListener( 'click', doFind );
		input.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); doFind(); } } );
		if ( deviceSel ) { deviceSel.addEventListener( 'change', function () { selectMatch( parseInt( deviceSel.value, 10 ) || 0, false ); } ); }

		function setField( name, value ) { var el = foundForm.querySelector( '[data-f="' + name + '"]' ); if ( el ) { el.textContent = value || '—'; } }

		function selectMatch( idx, prefill ) {
			selected = idx;
			var m = matches[ idx ];
			if ( ! m ) { return; }
			setField( 'model', m.model );
			setField( 'purchase_date', m.purchase_date );
			setField( 'masked_phone', m.masked_phone );
			setField( 'masked_address', m.masked_address );

			var w   = m.warranty || {};
			var wEl = foundForm.querySelector( '.aun-sp-warranty' );
			if ( wEl ) {
				wEl.textContent = warrantyLabel( w );
				wEl.classList.remove( 'in', 'out', 'unknown' );
				wEl.classList.add( ! w.known ? 'unknown' : ( w.in_warranty ? 'in' : 'out' ) );
			}
			var src = foundForm.querySelector( '.aun-sp-source' );
			if ( src ) { src.textContent = m.source === 'erp' ? t( 'verified' ) : t( 'on_record' ); }

			if ( prefill && ! m.has_address ) { openEdit(); }
		}

		// Ticking a part reveals its quantity picker and (if any) its photo box.
		// The picker is a plain <select> so there is nothing to validate client-side —
		// and it renders as a native wheel on phones, which beats tiny +/− buttons.
		foundForm.querySelectorAll( '.aun-sp-part input[type=checkbox]' ).forEach( function ( cb ) {
			cb.addEventListener( 'change', function () {
				var up = foundForm.querySelector( '.aun-sp-upload[data-part="' + cb.value + '"]' );
				if ( up ) { up.hidden = ! cb.checked; }
				var qt = foundForm.querySelector( '.aun-sp-qty[data-part="' + cb.value + '"]' );
				if ( qt ) {
					qt.hidden = ! cb.checked;
					// Reset to 1 when unticked so a hidden 3 can't ride along later.
					if ( ! cb.checked ) {
						var qi = qt.querySelector( '.aun-sp-qty-in' );
						if ( qi ) { qi.value = '1'; }
					}
				}
			} );
		} );

		// A quantity above 1 is unusual, so make it visibly deliberate once chosen.
		foundForm.querySelectorAll( '.aun-sp-qty-in' ).forEach( function ( sel ) {
			sel.addEventListener( 'change', function () {
				sel.closest( '.aun-sp-qty' ).classList.toggle( 'is-multi', parseInt( sel.value, 10 ) > 1 );
			} );
		} );

		// Show the photo the customer just picked, as the same thumbnail as the
		// "see example" photo — tap it to see it large in the same lightbox. This lets
		// them check the serial is actually readable BEFORE sending, and an oversize
		// file is refused right here instead of after a long upload on mobile data.
		foundForm.querySelectorAll( '.aun-sp-upload input[type=file]' ).forEach( function ( fi ) {
			var up   = fi.closest( '.aun-sp-upload' );
			var part = up ? up.getAttribute( 'data-part' ) : '';
			var cb   = part ? foundForm.querySelector( '.aun-sp-part input[type=checkbox][value="' + part + '"]' ) : null;
			var nm   = cb && cb.parentNode.querySelector( 'span' );
			var pname = nm ? nm.textContent.trim() : '';

			var box = document.createElement( 'div' );
			box.className = 'aun-sp-upload-preview';
			box.hidden = true;
			fi.parentNode.insertBefore( box, fi.nextSibling );

			var blob = '';
			fi.addEventListener( 'change', function () {
				if ( blob ) { URL.revokeObjectURL( blob ); blob = ''; }
				box.textContent = '';
				box.hidden = true;
				var f = fi.files && fi.files[0];
				if ( ! f ) { return; }
				if ( f.size > MAX_BYTES ) {
					fi.value = '';
					var w = document.createElement( 'span' );
					w.className = 'aun-sp-upload-warn';
					w.textContent = t( 'too_large_1' );
					box.appendChild( w );
					box.hidden = false;
					return;
				}
				if ( ! window.URL || ! URL.createObjectURL ) { return; }
				blob = URL.createObjectURL( f );
				var cap = pname ? t( 'chosen_photo' ) + ' · ' + pname : t( 'chosen_photo' );
				var a = document.createElement( 'a' );
				a.className = 'aun-sp-refimg';
				a.href = blob;
				a.setAttribute( 'data-full', blob );
				a.setAttribute( 'data-caption', cap );
				a.title = cap;
				var img = document.createElement( 'img' );
				img.src = blob;
				img.alt = cap;
				a.appendChild( img );
				var lb = document.createElement( 'span' );
				lb.textContent = t( 'chosen_photo' );
				a.appendChild( lb );
				box.appendChild( a );
				box.hidden = false;
			} );
		} );

		foundForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var checked = foundForm.querySelectorAll( '.aun-sp-part input:checked' );
			if ( ! checked.length ) { msg( submitMsg, t( 'choose_part' ), 'is-error' ); return; }

			var missing = null, tooLarge = null;
			checked.forEach( function ( cb ) {
				var up = foundForm.querySelector( '.aun-sp-upload[data-part="' + cb.value + '"]' );
				if ( ! up ) { return; }
				var file   = up.querySelector( 'input[type=file]' );
				var nameEl = cb.parentNode.querySelector( 'span' );
				var pname  = nameEl ? nameEl.textContent.trim() : cb.value;
				if ( up.getAttribute( 'data-required' ) === '1' && ( ! file || ! file.files.length ) ) {
					missing = pname;
				}
				if ( file && file.files.length && file.files[0].size > 15 * 1024 * 1024 ) {
					tooLarge = pname;
				}
			} );
			if ( missing ) { msg( submitMsg, t( 'need_photo' ).replace( /\s*$/, '' ) + ' ' + missing, 'is-error' ); return; }
			if ( tooLarge ) { msg( submitMsg, t( 'too_large' ).replace( /\s*$/, '' ) + ' ' + tooLarge, 'is-error' ); return; }

			send( false );

			// confirmed=true replays the exact same submission with the duplicate
			// override, after the customer has said yes in the dialog.
			function send( confirmed ) {
				var m = matches[ selected ] || {};
				submitBtn.disabled = true;
				msg( submitMsg, t( 'submitting' ) );
				var fd = new FormData( foundForm );
				fd.append( 'search_by', searchBy );
				fd.append( 'query', foundQuery );
				fd.append( 'selected_source', m.source || '' );
				fd.append( 'selected_order', m.order_number || '' );
				if ( confirmed ) { fd.append( 'confirm_duplicate', '1' ); }

				post( 'aun_sp_submit', fd ).then( function ( res ) {
					submitBtn.disabled = false;
					if ( ! res || ! res.success ) {
						// The server refused because this overlaps an open request — ask.
						if ( res && res.data && res.data.code === 'duplicate' ) {
							msg( submitMsg, '' );
							showDuplicateDialog( res.data.duplicates, function () { send( true ); } );
							return;
						}
						msg( submitMsg, ( res && res.data && res.data.message ) || t( 'net_err' ), 'is-error' );
						return;
					}
					root.querySelectorAll( '.aun-sp-ref' ).forEach( function ( el ) { el.textContent = res.data.ref; } );
					// Deep-link the "Track this request" button straight to this reference.
					var tl = root.querySelector( '.aun-sp-track-link' );
					if ( tl ) {
						var base = tl.getAttribute( 'href' ) || '';
						tl.setAttribute( 'href', base + ( base.indexOf( '?' ) > -1 ? '&' : '?' ) + 'ref=' + encodeURIComponent( res.data.ref ) );
					}
					root.querySelector( '.aun-sp-find-step' ).hidden = true;
					foundForm.hidden = true;
					doneBox.hidden = false;
					doneBox.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
				} ).catch( function () { submitBtn.disabled = false; msg( submitMsg, t( 'net_err' ), 'is-error' ); } );
			}
		} );
	}

	// The reference-image lightbox lives in sp-lightbox.js (shared with the tracking
	// page) and binds itself via a delegated listener — nothing to wire up here.

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.aun-sp' ).forEach( init );
	} );
} )();
