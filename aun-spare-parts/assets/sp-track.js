( function () {
	'use strict';

	var I18N = {
		en: {
			searching: 'Searching…',
			not_found: 'No request found. Check your SP- number, or contact us on WhatsApp.',
			enter: 'Please enter your reference or phone.',
			net_err: 'Network error — please try again.',
			reupload_h: 'We need a clear, correct photo to continue:', match_example: 'Please take the photo just like this example:', track_parcel: 'Track your parcel on Pathao',
			send_photo: 'Send photo', choose_first: 'Choose a photo first.',
			uploading: 'Uploading…', upload_done: 'Thanks — we received your new photo.',
			eta: 'ETA ',
			quote_h: 'Quote — please review', total: 'Total', approve: 'Approve & proceed', decline: 'Decline',
			approved_msg: 'Thank you — your quote is approved. We will start sourcing your parts.',
			declined_msg: 'Your quote has been declined. Contact us any time if you change your mind.',
			history: 'Progress history',
			ov: { submitted: 'Submitted', in_progress: 'In progress', quote_sent: 'Quote — awaiting your approval', approved: 'Approved — sourcing parts', waiting_customer: 'Waiting on you', ready: 'Ready to dispatch', closed: 'Completed', declined: 'Quote declined', rejected: 'Rejected' },
			it: { pending: 'Pending', quoted: 'Quoted', applied: 'Applied to factory', at_factory: 'At factory', shipped: 'Shipped', arrived: 'Arrived', dispatched: 'Dispatched', delivered: 'Delivered', unavailable: 'Unavailable' }
		},
		bn: {
			searching: 'খোঁজা হচ্ছে…',
			not_found: 'কোনো অনুরোধ পাওয়া যায়নি। SP- নম্বরটি দেখুন, বা WhatsApp-এ যোগাযোগ করুন।',
			enter: 'আপনার রেফারেন্স বা ফোন নম্বর লিখুন।',
			net_err: 'নেটওয়ার্ক সমস্যা — আবার চেষ্টা করুন।',
			reupload_h: 'এগিয়ে যেতে আমাদের একটি স্পষ্ট ও সঠিক ছবি দরকার:', match_example: 'অনুগ্রহ করে এই উদাহরণ ছবির মতো করে তুলুন:', track_parcel: 'পাঠাও-এ আপনার পার্সেল ট্র্যাক করুন',
			send_photo: 'ছবি পাঠান', choose_first: 'প্রথমে একটি ছবি নির্বাচন করুন।',
			uploading: 'আপলোড হচ্ছে…', upload_done: 'ধন্যবাদ — আমরা আপনার নতুন ছবি পেয়েছি।',
			eta: 'আনুমানিক ',
			quote_h: 'কোটেশন — অনুগ্রহ করে দেখুন', total: 'মোট', approve: 'অনুমোদন করুন', decline: 'বাতিল করুন',
			approved_msg: 'ধন্যবাদ — আপনার কোটেশন অনুমোদিত হয়েছে। আমরা পার্টস সংগ্রহ শুরু করব।',
			declined_msg: 'আপনার কোটেশন বাতিল করা হয়েছে। মত পরিবর্তন হলে যেকোনো সময় যোগাযোগ করুন।',
			history: 'অগ্রগতির ইতিহাস',
			ov: { submitted: 'জমা হয়েছে', in_progress: 'প্রক্রিয়াধীন', quote_sent: 'কোটেশন — আপনার অনুমোদনের অপেক্ষায়', approved: 'অনুমোদিত — পার্টস সংগ্রহ চলছে', waiting_customer: 'আপনার জন্য অপেক্ষমাণ', ready: 'পাঠানোর জন্য প্রস্তুত', closed: 'সম্পন্ন', declined: 'কোটেশন বাতিল', rejected: 'বাতিল' },
			it: { pending: 'অপেক্ষমাণ', quoted: 'কোট করা হয়েছে', applied: 'ফ্যাক্টরিতে আবেদন', at_factory: 'ফ্যাক্টরিতে', shipped: 'পাঠানো হয়েছে', arrived: 'পৌঁছেছে', dispatched: 'ডেলিভারিতে', delivered: 'ডেলিভারি সম্পন্ন', unavailable: 'অনুপলব্ধ' }
		}
	};

	function statusClass( key ) {
		if ( key === 'delivered' || key === 'arrived' || key === 'dispatched' || key === 'ready' || key === 'approved' ) { return 'in'; }
		if ( key === 'unavailable' || key === 'rejected' || key === 'declined' ) { return 'out'; }
		return 'mid';
	}
	function el( tag, cls, txt ) { var e = document.createElement( tag ); if ( cls ) { e.className = cls; } if ( txt != null ) { e.textContent = txt; } return e; }

	function init( root ) {
		var input   = root.querySelector( '.aun-sp-tq' );
		var btn     = root.querySelector( '.aun-sp-track-btn' );
		if ( ! btn || ! input ) { return; }

		var ajax    = root.getAttribute( 'data-ajax' );
		var nonce   = root.getAttribute( 'data-nonce' );

		// Merge admin-edited translations (Spare Parts → Translations) over the defaults,
		// including the nested overall (ov) / per-part (it) status-label maps.
		try {
			var _o = JSON.parse( root.getAttribute( 'data-i18n' ) || 'null' );
			if ( _o ) {
				[ 'en', 'bn' ].forEach( function ( L ) {
					if ( ! _o[ L ] ) { return; }
					for ( var k in _o[ L ] ) {
						var v = _o[ L ][ k ];
						if ( ( k === 'ov' || k === 'it' ) && v && typeof v === 'object' ) {
							for ( var s in v ) { I18N[ L ][ k ][ s ] = v[ s ]; }
						} else {
							I18N[ L ][ k ] = v;
						}
					}
				} );
			}
		} catch ( e ) {}

		var tabs    = root.querySelectorAll( '.aun-sp-ttab' );
		var msg     = root.querySelector( '.aun-sp-track-msg' );
		var results = root.querySelector( '.aun-sp-track-results' );

		var by       = 'ref';
		var ph       = { ref: 'SP-2026-0001', phone: '01XXXXXXXXX' };
		var lastReqs = [];

		// Language is decided server-side (TranslatePress / WP locale) — no toggle here,
		// so the tracker always speaks the same language as the page around it.
		var lang = root.getAttribute( 'data-lang' ) === 'en' ? 'en' : 'bn';
		function d() { return I18N[ lang ] || I18N.en; }
		function t( k ) { return d()[ k ] || I18N.en[ k ] || k; }
		function ovLabel( key ) { return ( d().ov && d().ov[ key ] ) || I18N.en.ov[ key ] || key; }
		function itLabel( key ) { return ( d().it && d().it[ key ] ) || I18N.en.it[ key ] || key; }

		tabs.forEach( function ( tb ) {
			tb.addEventListener( 'click', function () {
				tabs.forEach( function ( x ) { x.classList.remove( 'is-active' ); } );
				tb.classList.add( 'is-active' );
				by = tb.getAttribute( 'data-tb' );
				input.placeholder = ph[ by ] || '';
				input.focus();
			} );
		} );

		function setMsg( txt, k ) { msg.textContent = txt || ''; msg.classList.remove( 'is-error', 'is-ok' ); if ( k ) { msg.classList.add( k ); } }

		// POST helper with one automatic retry on a stale nonce (the page — and its
		// embedded nonce — can be served from a long-lived cache).
		function post( action, fd ) {
			fd.append( 'action', action );
			fd.append( '_nonce', nonce );
			// admin-ajax runs outside TranslatePress's URL context — tell the server
			// which language to reply in.
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

		function track() {
			var q = input.value.trim();
			if ( ! q ) { setMsg( t( 'enter' ), 'is-error' ); return; }
			btn.disabled = true;
			setMsg( t( 'searching' ) );
			results.innerHTML = '';
			var fd = new FormData();
			fd.append( 'by', by );
			fd.append( 'query', q );
			post( 'aun_sp_track', fd ).then( function ( res ) {
				btn.disabled = false;
				if ( ! res || ! res.success ) { setMsg( ( res && res.data && res.data.message ) || t( 'not_found' ), 'is-error' ); return; }
				setMsg( '' );
				lastReqs = res.data.requests || [];
				render( lastReqs );
			} ).catch( function () { btn.disabled = false; setMsg( t( 'net_err' ), 'is-error' ); } );
		}
		btn.addEventListener( 'click', track );
		input.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); track(); } } );

		// Deep link from SMS: ?ref=SP-... (or ?phone=01...) opens straight to the request.
		try {
			var params = new URLSearchParams( window.location.search );
			var pRef   = params.get( 'ref' );
			var pPhone = params.get( 'phone' );
			if ( pRef ) {
				input.value = pRef;
				track();
			} else if ( pPhone ) {
				by = 'phone';
				tabs.forEach( function ( x ) { x.classList.toggle( 'is-active', x.getAttribute( 'data-tb' ) === 'phone' ); } );
				input.value = pPhone;
				track();
			}
		} catch ( e ) {}

		function render( reqs ) {
			results.innerHTML = '';
			if ( ! reqs || ! reqs.length ) { return; }
			var done    = { closed: 1, declined: 1, rejected: 1 };
			var hideEta = { arrived: 1, dispatched: 1, delivered: 1, unavailable: 1 };

			reqs.forEach( function ( r ) {
				var terminal = !! done[ r.status_key ];
				// Completed/closed requests start collapsed to keep the page tidy; the
				// header toggles them open.
				var card = el( 'div', 'aun-sp-track-card' + ( terminal ? ' is-collapsed' : '' ) );

				var head = el( 'div', 'aun-sp-track-head' );
				head.appendChild( el( 'span', 'aun-sp-track-ref', r.ref ) );
				var chip = el( 'span', 'aun-sp-warranty ' + statusClass( r.status_key ), ovLabel( r.status_key ) );
				head.appendChild( chip );
				head.appendChild( el( 'span', 'aun-sp-track-chev', '' ) );
				head.addEventListener( 'click', function () { card.classList.toggle( 'is-collapsed' ); } );
				card.appendChild( head );

				var body = el( 'div', 'aun-sp-track-body' );
				body.appendChild( el( 'div', 'aun-sp-track-model', ( r.model || '' ) + ( r.created ? ' · ' + r.created : '' ) ) );
				if ( r.rejected && r.reason ) { body.appendChild( el( 'div', 'aun-sp-track-reason', r.reason ) ); }

				var list = el( 'div', 'aun-sp-track-parts' );
				( r.parts || [] ).forEach( function ( p ) {
					var row   = el( 'div', 'aun-sp-track-part' );
					var pname = ( lang === 'bn' && p.label_bn ) ? p.label_bn : p.label;
					// Show the quantity when more than one was requested.
					if ( p.qty && p.qty > 1 ) { pname += ' × ' + p.qty; }
					row.appendChild( el( 'span', 'aun-sp-track-pname', pname ) );
					// Only show an ETA while the part is still on its way (not once it has
					// arrived, been dispatched, delivered, or is unavailable).
					var showEta = p.eta && ! hideEta[ p.key ];
					var st = itLabel( p.key ) + ( showEta ? ' · ' + t( 'eta' ).replace( /\s*$/, '' ) + ' ' + p.eta : '' );
					row.appendChild( el( 'span', 'aun-sp-warranty ' + statusClass( p.key ), st ) );
					list.appendChild( row );
					// Once dispatched, the admin can attach a Pathao consignment — show a
					// clickable chip that opens Pathao's public tracking page.
					if ( p.tracking_url ) {
						var ca = el( 'a', 'aun-sp-track-courier' );
						ca.href = p.tracking_url;
						ca.target = '_blank';
						ca.rel = 'noopener';
						ca.appendChild( el( 'span', null, t( 'track_parcel' ) ) );
						ca.appendChild( el( 'span', 'aun-sp-track-courier-no', p.tracking_no ) );
						list.appendChild( ca );
					}
				} );
				body.appendChild( list );

				if ( r.status_key === 'quote_sent' && r.quote ) { body.appendChild( quoteBlock( r, chip ) ); }
				if ( r.waiting ) { body.appendChild( reupload( r.ref, r.parts ) ); }
				if ( r.timeline && r.timeline.length ) { body.appendChild( timeline( r.timeline ) ); }

				card.appendChild( body );
				results.appendChild( card );
			} );
		}

		function reupload( ref, parts ) {
			var box = el( 'div', 'aun-sp-reupload' );
			box.appendChild( el( 'div', 'aun-sp-reupload-h', t( 'reupload_h' ) ) );
			// Show the example/tutorial photo(s) for the requested part(s) so the customer
			// can copy the exact style (e.g. where the LCD serial is) and re-upload it right.
			var examples = ( parts || [] ).filter( function ( p ) { return p.ref_image; } );
			if ( examples.length ) {
				box.appendChild( el( 'div', 'aun-sp-reupload-exh', t( 'match_example' ) ) );
				var exWrap = el( 'div', 'aun-sp-reupload-examples' );
				examples.forEach( function ( p ) {
					var a = el( 'a', 'aun-sp-refimg' );
					a.href = p.ref_image;
					a.setAttribute( 'data-full', p.ref_image );
					var img = el( 'img' );
					img.src = p.ref_image;
					img.alt = 'example';
					img.loading = 'lazy';
					a.appendChild( img );
					a.appendChild( el( 'span', null, ( lang === 'bn' && p.label_bn ) ? p.label_bn : p.label ) );
					exWrap.appendChild( a );
				} );
				box.appendChild( exWrap );
			}
			var file = el( 'input' );
			file.type = 'file';
			file.accept = 'image/*';
			var send = el( 'button', 'aun-sp-btn' );
			send.type = 'button';
			send.textContent = t( 'send_photo' );
			var m = el( 'span', 'aun-sp-reupload-msg' );
			send.addEventListener( 'click', function () {
				if ( ! file.files.length ) { m.textContent = t( 'choose_first' ); return; }
				send.disabled = true;
				m.textContent = t( 'uploading' );
				var fd = new FormData();
				fd.append( 'ref', ref );
				fd.append( 'photo', file.files[0] );
				post( 'aun_sp_reupload', fd ).then( function ( res ) {
					if ( res && res.success ) {
						// Hide the picker + button and confirm with a tick.
						box.innerHTML = '';
						box.className = 'aun-sp-reupload is-done';
						box.appendChild( el( 'span', 'aun-sp-reupload-tick', '✓' ) );
						box.appendChild( el( 'span', 'aun-sp-reupload-okmsg', ( res.data && res.data.message ) || t( 'upload_done' ) ) );
					} else {
						send.disabled = false;
						m.textContent = ( res && res.data && res.data.message ) || t( 'net_err' );
					}
				} ).catch( function () { send.disabled = false; m.textContent = t( 'net_err' ); } );
			} );
			box.appendChild( file );
			box.appendChild( send );
			box.appendChild( m );
			return box;
		}

		function quoteBlock( r, chip ) {
			var box = el( 'div', 'aun-sp-quote' );
			box.appendChild( el( 'div', 'aun-sp-quote-h', t( 'quote_h' ) ) );
			( r.parts || [] ).forEach( function ( p ) {
				if ( ! p.price || p.price === '0.00' ) { return; }
				var line = el( 'div', 'aun-sp-quote-line' );
				var name = ( lang === 'bn' && p.label_bn ) ? p.label_bn : p.label;
				var qty  = p.qty && p.qty > 1 ? p.qty : 1;
				// With a quantity, spell the maths out (2 × ৳1,500.00) so the customer
				// can see exactly what they are approving — then the line total.
				line.appendChild( el( 'span', null, qty > 1 ? name + '  (' + qty + ' × ৳' + p.price + ')' : name ) );
				line.appendChild( el( 'span', 'aun-sp-quote-price', '৳' + ( p.line_total || p.price ) ) );
				box.appendChild( line );
			} );
			var tot = el( 'div', 'aun-sp-quote-total' );
			tot.appendChild( el( 'span', null, t( 'total' ) ) );
			tot.appendChild( el( 'span', null, '৳' + r.quote.total ) );
			box.appendChild( tot );
			if ( r.quote.note ) { box.appendChild( el( 'div', 'aun-sp-quote-note', r.quote.note ) ); }
			if ( r.quote.pay ) { box.appendChild( el( 'div', 'aun-sp-quote-pay', r.quote.pay ) ); }

			var actions = el( 'div', 'aun-sp-quote-actions' );
			var ok = el( 'button', 'aun-sp-btn' );
			ok.type = 'button';
			ok.textContent = t( 'approve' );
			var no = el( 'button', 'aun-sp-btn aun-sp-btn-ghost' );
			no.type = 'button';
			no.textContent = t( 'decline' );
			var qm = el( 'div', 'aun-sp-quote-msg' );
			ok.addEventListener( 'click', function () { decide( r.ref, 'approve', actions, qm, chip ); } );
			no.addEventListener( 'click', function () { decide( r.ref, 'decline', actions, qm, chip ); } );
			actions.appendChild( ok );
			actions.appendChild( no );
			box.appendChild( actions );
			box.appendChild( qm );
			return box;
		}

		function decide( ref, decision, actions, qm, chip ) {
			qm.textContent = '…';
			var fd = new FormData();
			fd.append( 'ref', ref );
			fd.append( 'decision', decision );
			post( 'aun_sp_approve', fd ).then( function ( res ) {
				if ( res && res.success ) {
					actions.style.display = 'none';
					qm.textContent = ( decision === 'approve' ) ? t( 'approved_msg' ) : t( 'declined_msg' );
					// Reflect the new state on the card header too, so the chip no longer
					// says "awaiting your approval" after the customer has decided.
					if ( chip ) {
						var nk = ( decision === 'approve' ) ? 'approved' : 'declined';
						chip.textContent = ovLabel( nk );
						chip.className = 'aun-sp-warranty ' + statusClass( nk );
					}
				} else {
					qm.textContent = ( res && res.data && res.data.message ) || t( 'net_err' );
				}
			} ).catch( function () { qm.textContent = t( 'net_err' ); } );
		}

		function timeline( events ) {
			var box = el( 'div', 'aun-sp-timeline' );
			box.appendChild( el( 'div', 'aun-sp-timeline-h', t( 'history' ) ) );
			events.slice().reverse().forEach( function ( ev ) {
				var item = el( 'div', 'aun-sp-tl-item' );
				item.appendChild( el( 'div', 'aun-sp-tl-text', ev.text ) );
				item.appendChild( el( 'div', 'aun-sp-tl-date', ev.date ) );
				box.appendChild( item );
			} );
			return box;
		}
	}

	// Reference-image lightbox (delegated) — same behaviour as the request form, used here
	// for the "take it like this" example photos in the re-upload box.
	function openLightbox( src ) {
		if ( ! src ) { return; }
		var ov = document.createElement( 'div' );
		ov.className = 'aun-sp-lightbox';
		var img = document.createElement( 'img' );
		img.src = src;
		ov.appendChild( img );
		ov.addEventListener( 'click', function () { if ( ov.parentNode ) { ov.parentNode.removeChild( ov ); } } );
		document.body.appendChild( ov );
	}
	document.addEventListener( 'click', function ( e ) {
		var a = e.target && e.target.closest ? e.target.closest( '.aun-sp-refimg' ) : null;
		if ( a ) { e.preventDefault(); openLightbox( a.getAttribute( 'data-full' ) || a.getAttribute( 'href' ) ); }
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.aun-sp-track' ).forEach( init );
	} );
} )();
