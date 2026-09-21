/* Breo BD Store 2.0: header menus, mobile nav, hero slider, sticky buy bar,
 * lazy-playing videos, product gallery, quantity stepper, scroll reveal. */
(function () {
	'use strict';
	var d = document, html = d.documentElement;
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var hasIO = 'IntersectionObserver' in window;
	function each(sel, fn, root) { Array.prototype.forEach.call((root || d).querySelectorAll(sel), fn); }

	/* ---------- header dropdowns (hover on desktop, click/tap everywhere) */
	var drops = d.querySelectorAll('[data-breo-drop]');
	var hover = window.matchMedia('(hover: hover) and (min-width: 961px)');
	function setOpen(li, open) {
		li.classList.toggle('is-open', open);
		var b = li.querySelector('button');
		if (b) b.setAttribute('aria-expanded', open ? 'true' : 'false');
	}
	function closeAll(except) { Array.prototype.forEach.call(drops, function (li) { if (li !== except) setOpen(li, false); }); }
	Array.prototype.forEach.call(drops, function (li) {
		var btn = li.querySelector('button'), t;
		li.addEventListener('mouseenter', function () { if (!hover.matches) return; clearTimeout(t); closeAll(li); setOpen(li, true); });
		li.addEventListener('mouseleave', function () { if (!hover.matches) return; t = setTimeout(function () { setOpen(li, false); }, 180); });
		btn.addEventListener('click', function (e) { e.preventDefault(); var o = !li.classList.contains('is-open'); closeAll(li); setOpen(li, o); });
		li.addEventListener('focusout', function (e) { if (!li.contains(e.relatedTarget)) setOpen(li, false); });
	});
	d.addEventListener('click', function (e) { if (!e.target.closest('[data-breo-drop]')) closeAll(); });

	/* ---------- mobile nav */
	var burger = d.querySelector('[data-breo-burger]'), mnav = d.getElementById('breo-mnav');
	function toggleNav(open) {
		if (!burger || !mnav) return;
		open = typeof open === 'boolean' ? open : mnav.hidden;
		mnav.hidden = !open;
		burger.setAttribute('aria-expanded', open ? 'true' : 'false');
		html.classList.toggle('breo-nav-open', open);
	}
	if (burger) burger.addEventListener('click', function () { toggleNav(); });
	window.addEventListener('resize', function () { if (window.innerWidth > 960) toggleNav(false); });
	d.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(); toggleNav(false); } });

	/* ---------- hero slider */
	each('[data-breo-slider]', function (sl) {
		var slides = sl.querySelectorAll('.breo-slide'), dots = sl.querySelectorAll('[data-go]'), n = slides.length, i = 0, timer;
		if (n < 2) return;
		function go(k) {
			i = (k + n) % n;
			Array.prototype.forEach.call(slides, function (s, j) { s.classList.toggle('is-active', j === i); s.setAttribute('aria-hidden', j === i ? 'false' : 'true'); });
			Array.prototype.forEach.call(dots, function (b, j) { b.classList.toggle('is-active', j === i); });
		}
		function stop() { clearInterval(timer); }
		function start() { stop(); if (!reduce) timer = setInterval(function () { go(i + 1); }, 6500); }
		each('[data-dir]', function (b) { b.addEventListener('click', function () { go(i + parseInt(b.getAttribute('data-dir'), 10)); start(); }); }, sl);
		Array.prototype.forEach.call(dots, function (b) { b.addEventListener('click', function () { go(parseInt(b.getAttribute('data-go'), 10)); start(); }); });
		sl.addEventListener('mouseenter', stop);
		sl.addEventListener('mouseleave', start);
		sl.addEventListener('focusin', stop);
		var x0 = null;
		sl.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
		sl.addEventListener('touchend', function (e) {
			if (x0 === null) return;
			var dx = e.changedTouches[0].clientX - x0;
			if (Math.abs(dx) > 45) { go(i + (dx < 0 ? 1 : -1)); start(); }
			x0 = null;
		});
		d.addEventListener('visibilitychange', function () { if (d.hidden) stop(); else start(); });
		go(0);
		start();
	});

	/* ---------- sticky "name · price · buy" bar on product pages */
	var sticky = d.querySelector('[data-breo-sticky]');
	var buy = d.getElementById('buy');
	var first = d.querySelector('.breo-pdp > .breo-s');
	if (sticky && buy && first && hasIO) {
		var pastHero = false, buyVisible = false;
		var render = function () {
			var show = pastHero && !buyVisible;
			sticky.classList.toggle('is-visible', show);
			sticky.setAttribute('aria-hidden', show ? 'false' : 'true');
			each('a', function (a) { a.tabIndex = show ? 0 : -1; }, sticky);
		};
		new IntersectionObserver(function (es) { pastHero = !es[0].isIntersecting; render(); }).observe(first);
		new IntersectionObserver(function (es) { buyVisible = es[0].isIntersecting; render(); }, { threshold: 0.15 }).observe(buy);
	}

	/* ---------- videos: load and play only while on screen */
	var vids = d.querySelectorAll('[data-breo-video]');
	if (vids.length && hasIO) {
		var vio = new IntersectionObserver(function (es) {
			es.forEach(function (e) {
				var v = e.target;
				if (e.isIntersecting) {
					if (v.preload !== 'auto') v.preload = reduce ? 'metadata' : 'auto';
					if (!reduce) { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
				} else if (!v.paused) {
					v.pause();
				}
			});
		}, { threshold: 0.2 });
		Array.prototype.forEach.call(vids, function (v) { vio.observe(v); });
	}

	/* ---------- product gallery: thumbs, hover zoom and a full-screen viewer */
	function hydrate(img) {
		// WP Rocket lazy-loads the slides that start hidden; show one and it must be real.
		if (!img) return;
		var s = img.getAttribute('data-lazy-src');
		if (s) { img.src = s; img.removeAttribute('data-lazy-src'); }
		var ss = img.getAttribute('data-lazy-srcset');
		if (ss) { img.srcset = ss; img.removeAttribute('data-lazy-srcset'); }
		var sz = img.getAttribute('data-lazy-sizes');
		if (sz) { img.sizes = sz.replace(/^auto,\s*/, ''); img.removeAttribute('data-lazy-sizes'); }
	}

	each('[data-breo-gallery]', function (g) {
		var thumbs = g.querySelectorAll('[data-thumb]'), slides = g.querySelectorAll('[data-slide]');
		var main = g.querySelector('[data-breo-zoom]');
		var at = 0;

		function show(k) {
			at = k;
			Array.prototype.forEach.call(slides, function (s) {
				var on = +s.getAttribute('data-slide') === k;
				s.classList.toggle('is-active', on);
				if (on) hydrate(s.querySelector('img'));
			});
			Array.prototype.forEach.call(thumbs, function (x) {
				var on = +x.getAttribute('data-thumb') === k;
				x.classList.toggle('is-active', on);
				x.setAttribute('aria-selected', on ? 'true' : 'false');
			});
		}
		Array.prototype.forEach.call(thumbs, function (t) {
			t.addEventListener('click', function () { show(+t.getAttribute('data-thumb')); });
		});

		/* hover zoom, desktop pointers only */
		if (main && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
			main.addEventListener('mousemove', function (e) {
				var img = g.querySelector('.breo-buy__slide.is-active img');
				if (!img) return;
				var r = main.getBoundingClientRect();
				img.style.transformOrigin = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%';
			});
			main.addEventListener('mouseenter', function () { main.classList.add('is-zoom'); });
			main.addEventListener('mouseleave', function () {
				main.classList.remove('is-zoom');
				var img = g.querySelector('.breo-buy__slide.is-active img');
				if (img) img.style.transformOrigin = '';
			});
		}

		/* full-screen viewer */
		var box, boxImg, cnt, keys, opener;
		function render() {
			var fig = slides[at];
			if (!fig) return;
			var img = fig.querySelector('img');
			// on a phone the page-sized image is already downloaded: don't pull the full one too
			var full = fig.getAttribute('data-full');
			boxImg.src = (window.innerWidth < 700 && img && img.currentSrc) ? img.currentSrc : (full || (img || {}).currentSrc || '');
			boxImg.alt = (img || {}).alt || '';
			if (cnt) cnt.textContent = (at + 1) + ' / ' + slides.length;
			show(at);
		}
		function go(step) { at = (at + step + slides.length) % slides.length; render(); }
		function close() {
			if (!box) return;
			box.classList.remove('is-open');
			if (opener && document.contains(opener)) opener.focus({ preventScroll: true });
			box.style.pointerEvents = 'none'; // belt and braces if the stylesheet is stale
			document.documentElement.style.overflow = '';
			document.removeEventListener('keydown', keys);
			setTimeout(function () { if (box && !box.classList.contains('is-open')) box.hidden = true; }, 200);
		}
		function open(k) {
			if (!slides.length) return;
			at = k;
			// fall back to the expand button: a click does not always leave focus behind
			opener = ( document.activeElement && document.activeElement !== document.body )
				? document.activeElement
				: ( main && main.querySelector('[data-breo-open]') ) || main;
			if (main) main.classList.remove('is-zoom'); // the overlay swallows mouseleave
			if (!box) {
				box = document.createElement('div');
				box.className = 'breo-lb';
				box.setAttribute('role', 'dialog');
				box.setAttribute('aria-modal', 'true');
				box.setAttribute('aria-label', 'Product images');
				box.innerHTML = '<button type="button" class="breo-lb__x" aria-label="Close">&times;</button>'
					+ '<button type="button" class="breo-lb__nav breo-lb__prev" aria-label="Previous image">&#8249;</button>'
					+ '<figure class="breo-lb__stage"><img alt=""></figure>'
					+ '<button type="button" class="breo-lb__nav breo-lb__next" aria-label="Next image">&#8250;</button>'
					+ '<p class="breo-lb__count"></p>';
				document.body.appendChild(box);
				boxImg = box.querySelector('img');
				cnt = box.querySelector('.breo-lb__count');
				box.querySelector('.breo-lb__x').addEventListener('click', close);
				box.querySelector('.breo-lb__prev').addEventListener('click', function () { go(-1); });
				box.querySelector('.breo-lb__next').addEventListener('click', function () { go(1); });
				box.addEventListener('click', function (e) { if (e.target === box || e.target.classList.contains('breo-lb__stage')) close(); });
				var x0 = null;
				box.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
				box.addEventListener('touchend', function (e) {
					if (x0 === null) return;
					var dx = e.changedTouches[0].clientX - x0;
					if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
					x0 = null;
				});
				if (slides.length < 2) {
					box.querySelector('.breo-lb__prev').hidden = true;
					box.querySelector('.breo-lb__next').hidden = true;
				}
			}
			keys = function (e) {
				if (e.key === 'Escape') close();
				else if (e.key === 'ArrowRight') go(1);
				else if (e.key === 'ArrowLeft') go(-1);
				else if (e.key === 'Tab') { // keep the keyboard inside the viewer
					var f = Array.prototype.filter.call(box.querySelectorAll('button'), function (b) { return !b.hidden; });
					if (!f.length) return;
					var i = f.indexOf(document.activeElement);
					e.preventDefault();
					f[(i + (e.shiftKey ? -1 : 1) + f.length) % f.length].focus();
				}
			};
			document.addEventListener('keydown', keys);
			box.hidden = false;
			box.style.pointerEvents = '';
			// rAF gives the fade a frame to start from; the timer covers a throttled/background tab,
			// where rAF may not run and the viewer would stay invisible and unclickable.
			var reveal = function () { box.classList.add('is-open'); };
			requestAnimationFrame(reveal);
			setTimeout(reveal, 50);
			document.documentElement.style.overflow = 'hidden';
			render();
			box.querySelector('.breo-lb__x').focus({ preventScroll: true });
		}
		if (main) {
			// the expand button already covers the keyboard, so click is enough here
			main.addEventListener('click', function () { open(at); });
		}
	});

	/* ---------- quantity stepper */
	each('[data-breo-qty]', function (q) {
		var inp = q.querySelector('input');
		each('[data-step]', function (b) {
			b.addEventListener('click', function () {
				var max = parseInt(inp.max, 10) || 99;
				var v = (parseInt(inp.value, 10) || 1) + parseInt(b.getAttribute('data-step'), 10);
				inp.value = Math.max(1, Math.min(max, v));
			});
		}, q);
	});

	/* ---------- reveal on scroll */
	var rev = d.querySelectorAll('[data-reveal]');
	if (rev.length) {
		if (hasIO && !reduce) {
			var rio = new IntersectionObserver(function (es) {
				es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); rio.unobserve(e.target); } });
			}, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 });
			Array.prototype.forEach.call(rev, function (el) { rio.observe(el); });
		} else {
			Array.prototype.forEach.call(rev, function (el) { el.classList.add('is-in'); });
		}
	}
})();
