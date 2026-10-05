<?php
/**
 * Behaviour for the designed info pages: scroll reveal, count-up stats and the document contents list
 * (highlights the clause in view; folds away on phones). Plain JavaScript. Without it every page is
 * still complete — nothing is hidden until the script marks the page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function aunstore_pages_js() {
	return <<<'JS'
(function () {
	'use strict';
	if (window.__aunpOn) return;
	window.__aunpOn = true;
	var each = function (list, fn) { Array.prototype.forEach.call(list, fn); };
	var reduce = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	var roots = document.querySelectorAll('.aunx.aunp-hero, .aunx.aunp-sec, .aunx.aunp-updated');
	if (!roots.length) return;

	function countUp(el) {
		var target = parseFloat(el.getAttribute('data-count'));
		var suffix = el.getAttribute('data-suffix') || '';
		var plain = !!el.getAttribute('data-plain');
		var fmt = function (v) { return (plain || target < 10000 ? String(v) : v.toLocaleString('en-US')) + suffix; };
		if (reduce || isNaN(target)) { el.textContent = fmt(target); return; }
		var from = plain ? Math.max(0, target - 40) : 0, start = null;
		(function step(ts) {
			if (ts === undefined) return requestAnimationFrame(step);
			if (start === null) start = ts;
			var p = Math.min(1, (ts - start) / 1300);
			el.textContent = fmt(Math.round(from + (target - from) * (1 - Math.pow(1 - p, 3))));
			if (p < 1) requestAnimationFrame(step);
		})();
	}

	/* ── reveal on scroll ──────────────────────────────────────── */
	var items = [];
	each(roots, function (r) { r.classList.add('aunx-js'); each(r.querySelectorAll('[data-rv]'), function (el) { items.push(el); }); });
	var show = function (el) {
		el.classList.add('is-in');
		if (el.classList.contains('aunp-stats')) each(el.querySelectorAll('[data-count]'), countUp);
	};
	if ('IntersectionObserver' in window && !reduce) {
		var io = new IntersectionObserver(function (es) {
			es.forEach(function (e) { if (e.isIntersecting) { show(e.target); io.unobserve(e.target); } });
		}, { rootMargin: '0px 0px -8% 0px' });
		items.forEach(function (el) { io.observe(el); });
	} else {
		items.forEach(show);
	}

	/* ── document contents list ────────────────────────────────── */
	each(document.querySelectorAll('.aunp-doc'), function (doc) {
		var box = doc.querySelector('.aunp-toc-box');
		var links = doc.querySelectorAll('.aunp-toc nav a');
		var narrow = function () { return window.innerWidth < 850; };
		if (box && narrow()) box.removeAttribute('open');
		var setActive = function (id) {
			each(links, function (a) { a.classList.toggle('is-active', a.getAttribute('href') === '#' + id); });
		};
		each(links, function (a) {
			a.addEventListener('click', function () {
				setActive(a.getAttribute('href').slice(1));
				if (box && narrow()) box.removeAttribute('open');
			});
		});
		if (!('IntersectionObserver' in window)) return;
		var seen = {};
		var spy = new IntersectionObserver(function (es) {
			es.forEach(function (e) { seen[e.target.id] = e.isIntersecting; });
			for (var i = 0; i < links.length; i++) {
				var id = links[i].getAttribute('href').slice(1);
				if (seen[id]) { setActive(id); break; }
			}
		}, { rootMargin: '-120px 0px -55% 0px' });
		each(doc.querySelectorAll('.aunp-clause'), function (c) { spy.observe(c); });
	});
})();
JS;
}
