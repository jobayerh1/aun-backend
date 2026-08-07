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
			price_h: 'Price for your parts', payable_h: 'Approved — amount payable',
			delivery: 'Delivery', pay_online: 'Pay online now', pay_wait: 'Opening payment…',
			cod_default: 'Prefer cash on delivery? Nothing to do — just pay when we hand over the parts.',
			paid_msg: 'Payment received — thank you.',
			decide_h: 'Do you want to go ahead?', decide_sub: 'Approve and we will start sourcing your parts. Nothing is charged yet.',
			pay_h: 'How would you like to pay?', pay_sub: 'Pay online now, or simply pay cash when we hand the parts over.',
			refunded_msg: 'Refunded ৳{amount} on {date}.', refund_due_msg: 'This request was cancelled after payment — your refund is being processed.',
			approved_msg: 'Thank you — your quote is approved. We will start sourcing your parts.',
			declined_msg: 'Your quote has been declined. Contact us any time if you change your mind.',
			history: 'Progress history',
			ov: { submitted: 'Submitted', in_progress: 'In progress', quote_sent: 'Quote — awaiting your approval', approved: 'Approved — sourcing parts', waiting_customer: 'Waiting on you', ready: 'Ready to dispatch', closed: 'Completed', declined: 'Quote declined', rejected: 'Rejected' },
			it: { pending: 'Pending review', quoted: 'Price quoted', applied: 'Ordered from the factory', at_factory: 'Being prepared at the factory', shipped: 'Shipped from the factory — on its way to Bangladesh', arrived: 'Arrived at AUN, Dhaka', dispatched: 'Out for delivery to you', delivered: 'Delivered to you', unavailable: 'Unavailable' },
			ith: { pending: 'We have your request and are checking the part and its availability.', quoted: 'We have sent you the price. We order the part once you approve it.', applied: 'We have placed the order with the factory and are waiting for them to confirm it.', at_factory: 'The factory has confirmed your part and is preparing it for shipment. This is usually the longest step.', shipped: 'Your part has left the factory and is in transit to Bangladesh. It has not reached us yet.', arrived: 'Your part has reached our Dhaka office. We will courier it to you next.', dispatched: 'We have handed your part to the courier. You can track the parcel with the link below.', delivered: 'The courier has delivered your part. Thank you for staying with AUN.', unavailable: 'We are unable to supply this part. See the note above for the reason.' }
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
			price_h: 'আপনার পার্টসের মূল্য', payable_h: 'অনুমোদিত — প্রদেয় পরিমাণ',
			delivery: 'ডেলিভারি চার্জ', pay_online: 'এখনই অনলাইনে পেমেন্ট করুন', pay_wait: 'পেমেন্ট পেজ খোলা হচ্ছে…',
			cod_default: 'ক্যাশ অন ডেলিভারি পছন্দ? কিছু করতে হবে না — পার্টস হাতে পাওয়ার সময় পেমেন্ট করবেন।',
			paid_msg: 'পেমেন্ট পাওয়া গেছে — ধন্যবাদ।',
			decide_h: 'আপনি কি এগিয়ে যেতে চান?', decide_sub: 'অনুমোদন করলে আমরা পার্টস সংগ্রহ শুরু করব। এখনই কোনো টাকা কাটা হবে না।',
			pay_h: 'আপনি কীভাবে পেমেন্ট করতে চান?', pay_sub: 'এখনই অনলাইনে পেমেন্ট করুন, অথবা পার্টস হাতে পাওয়ার সময় ক্যাশ পরিশোধ করুন।',
			refunded_msg: '{date} তারিখে ৳{amount} ফেরত দেওয়া হয়েছে।', refund_due_msg: 'পেমেন্টের পর অনুরোধটি বাতিল হয়েছে — আপনার টাকা ফেরতের প্রক্রিয়া চলছে।',
			approved_msg: 'ধন্যবাদ — আপনার কোটেশন অনুমোদিত হয়েছে। আমরা পার্টস সংগ্রহ শুরু করব।',
			declined_msg: 'আপনার কোটেশন বাতিল করা হয়েছে। মত পরিবর্তন হলে যেকোনো সময় যোগাযোগ করুন।',
			history: 'অগ্রগতির ইতিহাস',
			ov: { submitted: 'জমা হয়েছে', in_progress: 'প্রক্রিয়াধীন', quote_sent: 'কোটেশন — আপনার অনুমোদনের অপেক্ষায়', approved: 'অনুমোদিত — পার্টস সংগ্রহ চলছে', waiting_customer: 'আপনার জন্য অপেক্ষমাণ', ready: 'পাঠানোর জন্য প্রস্তুত', closed: 'সম্পন্ন', declined: 'কোটেশন বাতিল', rejected: 'বাতিল' },
			it: { pending: 'যাচাই চলছে', quoted: 'মূল্য জানানো হয়েছে', applied: 'ফ্যাক্টরিতে অর্ডার করা হয়েছে', at_factory: 'ফ্যাক্টরিতে প্রস্তুত হচ্ছে', shipped: 'ফ্যাক্টরি থেকে পাঠানো হয়েছে — বাংলাদেশের পথে', arrived: 'ঢাকায় AUN-এ পৌঁছেছে', dispatched: 'আপনার ঠিকানায় পাঠানো হয়েছে', delivered: 'আপনি বুঝে পেয়েছেন', unavailable: 'অনুপলব্ধ' },
			ith: { pending: 'আমরা আপনার অনুরোধ পেয়েছি এবং পার্টটি ও তার প্রাপ্যতা যাচাই করছি।', quoted: 'আমরা আপনাকে মূল্য জানিয়েছি। আপনি অনুমোদন দিলেই আমরা পার্টটি অর্ডার করব।', applied: 'আমরা ফ্যাক্টরিতে অর্ডার দিয়েছি এবং তাদের নিশ্চিতকরণের অপেক্ষায় আছি।', at_factory: 'ফ্যাক্টরি আপনার পার্টটি নিশ্চিত করেছে এবং পাঠানোর জন্য প্রস্তুত করছে। সাধারণত এই ধাপেই সবচেয়ে বেশি সময় লাগে।', shipped: 'আপনার পার্টটি ফ্যাক্টরি থেকে রওনা হয়েছে এবং বাংলাদেশে আসছে। এটি এখনও আমাদের কাছে পৌঁছায়নি।', arrived: 'আপনার পার্টটি ঢাকায় আমাদের অফিসে পৌঁছেছে। এরপর আমরা কুরিয়ারে আপনার কাছে পাঠাব।', dispatched: 'আমরা আপনার পার্টটি কুরিয়ারে দিয়ে দিয়েছি। নিচের লিংক দিয়ে পার্সেলটি ট্র্যাক করতে পারবেন।', delivered: 'কুরিয়ার আপনার পার্টটি পৌঁছে দিয়েছে। AUN-এর সঙ্গে থাকার জন্য ধন্যবাদ।', unavailable: 'আমরা এই পার্টটি সরবরাহ করতে পারছি না। কারণ উপরে উল্লেখ করা হয়েছে।' }
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
		// including the nested overall (ov) / per-part (it) / explanation (ith) maps.
		try {
			var _o = JSON.parse( root.getAttribute( 'data-i18n' ) || 'null' );
			if ( _o ) {
				[ 'en', 'bn' ].forEach( function ( L ) {
					if ( ! _o[ L ] ) { return; }
					for ( var k in _o[ L ] ) {
						var v = _o[ L ][ k ];
						if ( ( k === 'ov' || k === 'it' || k === 'ith' ) && v && typeof v === 'object' ) {
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
		function itHelp( key ) { return ( d().ith && d().ith[ key ] ) || I18N.en.ith[ key ] || ''; }

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
					// A status name alone doesn't tell the customer where the part physically
					// is or what happens next — spell it out under the chip.
					var help = itHelp( p.key );
					if ( help ) { list.appendChild( el( 'div', 'aun-sp-track-help', help ) ); }
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

				// Any priced request shows its cost; only a pending one shows the buttons.
				if ( r.quote ) { body.appendChild( quoteBlock( r, chip ) ); }
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
					var pname = ( lang === 'bn' && p.label_bn ) ? p.label_bn : p.label;
					var a = el( 'a', 'aun-sp-refimg' );
					a.href = p.ref_image;
					a.setAttribute( 'data-full', p.ref_image );
					a.setAttribute( 'data-caption', pname ); // shown under the lightbox image
					a.title = pname;
					var img = el( 'img' );
					img.src = p.ref_image;
					img.alt = pname;
					img.loading = 'lazy';
					a.appendChild( img );
					a.appendChild( el( 'span', null, pname ) );
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
			// "Please review" only reads correctly while a decision is pending; once
			// approved the same figure is what they owe, and otherwise it is simply
			// the price of the parts.
			var head = t( 'price_h' );
			if ( r.quote.awaiting ) { head = t( 'quote_h' ); }
			else if ( r.status_key === 'approved' ) { head = t( 'payable_h' ); }
			box.appendChild( el( 'div', 'aun-sp-quote-h', head ) );
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
			// Delivery is part of what they pay, so show it as its own line.
			if ( r.delivery ) {
				var dl = el( 'div', 'aun-sp-quote-line' );
				dl.appendChild( el( 'span', null, t( 'delivery' ) ) );
				dl.appendChild( el( 'span', 'aun-sp-quote-price', '৳' + r.delivery ) );
				box.appendChild( dl );
			}
			var tot = el( 'div', 'aun-sp-quote-total' );
			tot.appendChild( el( 'span', null, t( 'total' ) ) );
			tot.appendChild( el( 'span', null, '৳' + r.quote.total ) );
			box.appendChild( tot );
			if ( r.quote.note ) { box.appendChild( el( 'div', 'aun-sp-quote-note', r.quote.note ) ); }

			// One block owns the decision/payment area, and it renders exactly ONE
			// state — otherwise (as happened) an Approve prompt and a Pay button can
			// sit on screen together and the customer can't tell what to do.
			box.appendChild( actionArea( r, chip ) );

			return box;
		}

		/**
		 * The single place that tells the customer what to do next. Exactly one of:
		 *   1. decide   — a quote is waiting: Approve or Decline
		 *   2. pay      — approved and unpaid: pay online, or do nothing for COD
		 *   3. settled  — paid, refunded, or nothing owed: a plain statement
		 * Keeping these mutually exclusive is what stops "Approve" and "Pay" being
		 * on screen at the same time.
		 */
		function actionArea( r, chip ) {
			var wrap = el( 'div', 'aun-sp-act' );

			// 3a. Refunded / refund on its way — outranks everything else.
			if ( r.order && r.order.refunded ) {
				wrap.appendChild( el( 'div', 'aun-sp-act-note is-refund',
					'↩ ' + t( 'refunded_msg' ).replace( '{amount}', r.order.refund_amount ).replace( '{date}', r.order.refund_date ) ) );
				return wrap;
			}
			if ( r.order && r.order.refund_due ) {
				wrap.appendChild( el( 'div', 'aun-sp-act-note is-refund', t( 'refund_due_msg' ) ) );
				return wrap;
			}
			// 3b. Paid.
			if ( r.order && r.order.paid ) {
				wrap.appendChild( el( 'div', 'aun-sp-act-note is-paid', '✓ ' + t( 'paid_msg' ) ) );
				return wrap;
			}
			// 1. A decision is pending — nothing else may appear.
			if ( r.quote && r.quote.awaiting ) {
				wrap.appendChild( decideStage( r, chip, wrap ) );
				return wrap;
			}
			// 2. Approved and unpaid.
			if ( r.can_pay ) {
				wrap.appendChild( payStage( r ) );
				return wrap;
			}
			if ( r.quote && r.quote.pay ) {
				wrap.appendChild( el( 'div', 'aun-sp-act-note', r.quote.pay ) );
			}
			return wrap;
		}

		/** Stage 1 — approve or decline the quote. */
		function decideStage( r, chip, host ) {
			var box = el( 'div', 'aun-sp-stage' );
			box.appendChild( el( 'div', 'aun-sp-stage-h', t( 'decide_h' ) ) );
			box.appendChild( el( 'div', 'aun-sp-stage-sub', t( 'decide_sub' ) ) );

			var actions = el( 'div', 'aun-sp-stage-actions' );
			var ok = el( 'button', 'aun-sp-btn aun-sp-btn-approve' );
			ok.type = 'button';
			ok.textContent = t( 'approve' );
			var no = el( 'button', 'aun-sp-btn aun-sp-btn-ghost' );
			no.type = 'button';
			no.textContent = t( 'decline' );
			var qm = el( 'div', 'aun-sp-quote-msg' );

			ok.addEventListener( 'click', function () { decide( r, 'approve', host, qm, chip ); } );
			no.addEventListener( 'click', function () { decide( r, 'decline', host, qm, chip ); } );
			actions.appendChild( ok );
			actions.appendChild( no );
			box.appendChild( actions );
			box.appendChild( qm );
			return box;
		}

		/**
		 * Stage 2 — approved and unpaid. Paying online is opt-in: taking it creates
		 * the order (so the amount is always current); ignoring it means cash on
		 * delivery, which never creates an order at all.
		 */
		function payStage( r ) {
			var wrap = el( 'div', 'aun-sp-stage' );
			wrap.appendChild( el( 'div', 'aun-sp-stage-h', t( 'pay_h' ) ) );
			wrap.appendChild( el( 'div', 'aun-sp-stage-sub', t( 'pay_sub' ) ) );

			var btn  = el( 'button', 'aun-sp-btn aun-sp-pay-btn' );
			btn.type = 'button';
			btn.textContent = t( 'pay_online' ) + ( r.quote && r.quote.total ? ' — ৳' + r.quote.total : '' );

			var msg = el( 'div', 'aun-sp-quote-payhint', t( 'cod_default' ) );

			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				var was = btn.textContent;
				btn.textContent = t( 'pay_wait' );
				var fd = new FormData();
				fd.append( 'ref', r.ref );
				post( 'aun_sp_pay', fd ).then( function ( res ) {
					if ( res && res.success && res.data && res.data.pay_url ) {
						window.location.href = res.data.pay_url; // straight to the gateway
						return;
					}
					btn.disabled = false;
					btn.textContent = was;
					msg.textContent = ( res && res.data && res.data.message ) || t( 'net_err' );
					msg.className = 'aun-sp-quote-payhint is-error';
				} ).catch( function () {
					btn.disabled = false;
					btn.textContent = was;
					msg.textContent = t( 'net_err' );
					msg.className = 'aun-sp-quote-payhint is-error';
				} );
			} );

			wrap.appendChild( btn );
			wrap.appendChild( msg );
			return wrap;
		}

		/**
		 * Send the decision, then REPLACE the whole action area with whatever comes
		 * next. Replacing (rather than appending) is what guarantees the customer is
		 * never looking at a stale Approve prompt or a second Pay button.
		 */
		function decide( r, decision, host, qm, chip ) {
			qm.textContent = '…';
			var fd = new FormData();
			fd.append( 'ref', r.ref );
			fd.append( 'decision', decision );
			post( 'aun_sp_approve', fd ).then( function ( res ) {
				if ( ! res || ! res.success ) {
					qm.textContent = ( res && res.data && res.data.message ) || t( 'net_err' );
					return;
				}
				var approved = ( decision === 'approve' );
				// Keep the card header honest.
				if ( chip ) {
					var nk = approved ? 'approved' : 'declined';
					chip.textContent = ovLabel( nk );
					chip.className = 'aun-sp-warranty ' + statusClass( nk );
				}
				// Mutate our copy so a later re-render agrees with the screen.
				if ( r.quote ) { r.quote.awaiting = false; }
				r.status_key = approved ? 'approved' : 'declined';
				if ( ! approved ) { r.can_pay = false; }

				host.textContent = '';
				host.appendChild( el( 'div', 'aun-sp-act-note ' + ( approved ? 'is-ok' : '' ),
					approved ? t( 'approved_msg' ) : t( 'declined_msg' ) ) );
				// Only an approved quote leads anywhere; a declined one is finished.
				if ( approved && ( res.data || {} ).can_pay ) {
					host.appendChild( payStage( r ) );
				}
				host.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
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

	// The "take it like this" example photos open in the shared lightbox
	// (sp-lightbox.js), which binds itself via a delegated listener.

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.aun-sp-track' ).forEach( init );
	} );
} )();
