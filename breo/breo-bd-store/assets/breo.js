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

	/* ---------- product gallery */
	each('[data-breo-gallery]', function (g) {
		var thumbs = g.querySelectorAll('[data-thumb]'), slides = g.querySelectorAll('[data-slide]');
		Array.prototype.forEach.call(thumbs, function (t) {
			t.addEventListener('click', function () {
				var k = t.getAttribute('data-thumb');
				Array.prototype.forEach.call(slides, function (s) { s.classList.toggle('is-active', s.getAttribute('data-slide') === k); });
				Array.prototype.forEach.call(thumbs, function (x) { x.classList.toggle('is-active', x === t); });
			});
		});
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
