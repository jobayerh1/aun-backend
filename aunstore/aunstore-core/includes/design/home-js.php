<?php
/**
 * Behaviour for [aunstore_home]: scroll reveal, count-up numbers, the hero photos (Movies / Sport / Gaming),
 * the lineup explorer (tabs, filters,
 * autoplay) and the warranty walkthrough. Plain JavaScript, no dependencies.
 * If this script never runs (blocked or delayed), the page is still fully readable: nothing is hidden
 * until the script adds .aunx-js.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function aunstore_home_js() {
	return <<<'JS'
(function () {
	'use strict';
	var root = document.querySelector('.aunx');
	if (!root || root.getAttribute('data-aunx-on')) return;
	root.setAttribute('data-aunx-on', '1');

	var reduce = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	var each = function (list, fn) { Array.prototype.forEach.call(list, fn); };

	/* ── count-up ─────────────────────────────────────────────── */
	function countUp(el) {
		var target = parseFloat(el.getAttribute('data-count'));
		var suffix = el.getAttribute('data-suffix') || '';
		var plain = !!el.getAttribute('data-plain'); // years stay "2014"; five-figure numbers get a comma ("30,000")
		var fmt = function (v) { return (plain || target < 10000 ? String(v) : v.toLocaleString('en-US')) + suffix; };
		if (reduce || isNaN(target)) { el.textContent = fmt(target); return; }
		var from = plain ? Math.max(0, target - 40) : 0;
		var start = null, dur = 1300;
		function step(ts) {
			if (start === null) start = ts;
			var p = Math.min(1, (ts - start) / dur);
			var eased = 1 - Math.pow(1 - p, 3);
			el.textContent = fmt(Math.round(from + (target - from) * eased));
			if (p < 1) requestAnimationFrame(step);
		}
		requestAnimationFrame(step);
	}

	/* ── reveal on scroll ─────────────────────────────────────── */
	var revealEls = root.querySelectorAll('[data-rv]');
	if ('IntersectionObserver' in window && !reduce) {
		root.classList.add('aunx-js');
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (!e.isIntersecting) return;
				e.target.classList.add('is-in');
				if (e.target.classList.contains('aunx-stats')) each(e.target.querySelectorAll('[data-count]'), countUp);
				io.unobserve(e.target);
			});
		}, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });
		each(revealEls, function (el) { io.observe(el); });
	}

	/* ── lineup explorer ──────────────────────────────────────── */
	var exp = root.querySelector('[data-aunx-exp]');
	if (exp) {
		var tabs = root.querySelectorAll('.aunx-tab');
		var panels = exp.querySelectorAll('.aunx-panel');
		var imgs = exp.querySelectorAll('.aunx-exp-img');
		var filters = root.querySelectorAll('.aunx-filter');
		var current = 0, timer = null, playing = !reduce, DUR = 6000;

		/* turntable: each model turns from front to back (its ports) and round again; swipe turns it by hand */
		var stage = exp.querySelector('.aunx-exp-stage');
		var TURN = 2800, turnTimer = null, turnAuto = !reduce, turnSeen = false;
		var vs = [];
		each(imgs, function (d, n) { vs[n] = { el: d, views: d.querySelectorAll('.aunx-view'), idx: 0 }; });
		// a model stays up long enough to turn round once (front, back)
		var durOf = function (i) { var k = vs[i] ? vs[i].views.length : 1; return k > 1 ? Math.max(DUR, k * TURN - 250) : DUR; };
		var loadViews = function (i) {
			// the picked model's views load now (not lazily: a hidden view must be ready before it turns into sight)
			each(vs[i].views, function (v) { v.loading = 'eager'; var src = v.getAttribute('data-src'); if (src) { v.src = src; v.removeAttribute('data-src'); } });
		};
		if (vs[0]) loadViews(0);
		var turnTo = function (i, k, dir) {
			var st = vs[i];
			if (!st || st.views.length < 2) return;
			k = (k + st.views.length) % st.views.length;
			if (k === st.idx) return;
			var prev = st.idx;
			st.idx = k;
			st.el.classList.toggle('is-back', dir < 0);
			each(st.views, function (v, n) { v.classList.toggle('is-on', n === k); v.classList.toggle('is-out', n === prev); });
			if (stage) { stage.classList.add('is-turning'); setTimeout(function () { stage.classList.remove('is-turning'); }, 900); }
		};
		var resetViews = function (i) {
			var st = vs[i];
			if (!st) return;
			st.idx = 0;
			st.el.classList.remove('is-back');
			each(st.views, function (v, n) { v.classList.remove('is-out'); v.classList.toggle('is-on', n === 0); });
		};
		var turnLoop = function () {
			clearInterval(turnTimer);
			if (!turnAuto || !turnSeen) return;
			turnTimer = setInterval(function () { turnTo(current, vs[current].idx + 1, 1); }, TURN);
		};
		var turnByHand = function (k, dir) {
			turnAuto = false;
			clearInterval(turnTimer);
			stop();
			turnTo(current, k, dir);
		};
		if (stage && window.PointerEvent) {
			var x0 = null;
			stage.addEventListener('pointerdown', function (e) { x0 = e.clientX; });
			stage.addEventListener('pointerup', function (e) {
				if (x0 === null) return;
				var dx = e.clientX - x0;
				x0 = null;
				if (Math.abs(dx) > 40) turnByHand(vs[current].idx + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
			});
			stage.addEventListener('pointercancel', function () { x0 = null; });
		}

		var visible = function (i) { return !tabs[i].classList.contains('is-dim'); };

		function select(i, animateNumbers) {
			current = i;
			each(tabs, function (t, n) {
				t.classList.toggle('is-active', n === i);
				t.classList.remove('is-playing');
				t.setAttribute('aria-selected', n === i ? 'true' : 'false');
			});
			each(panels, function (p, n) { p.classList.toggle('is-active', n === i); });
			loadViews(i);
			resetViews(i);
			each(imgs, function (im, n) { im.classList.toggle('is-active', n === i); });
			turnLoop();
			if (animateNumbers) each(panels[i].querySelectorAll('[data-count]'), countUp);
			if (playing) {
				void tabs[i].offsetWidth; // restart the progress bar animation
				tabs[i].style.setProperty('--dur', durOf(i) + 'ms');
				tabs[i].classList.add('is-playing');
			}
			if (tabs[i].scrollIntoView && window.innerWidth < 550 && exp.getBoundingClientRect().top < window.innerHeight) {
				var rail = tabs[i].parentNode;
				rail.scrollTo({ left: tabs[i].offsetLeft - 18, behavior: reduce ? 'auto' : 'smooth' });
			}
		}

		function next() {
			var n = current;
			for (var k = 0; k < tabs.length; k++) {
				n = (n + 1) % tabs.length;
				if (visible(n)) break;
			}
			select(n, true);
		}

		function schedule() {
			clearTimeout(timer);
			if (playing) timer = setTimeout(function () { next(); schedule(); }, durOf(current));
		}
		function stop() {
			playing = false;
			clearTimeout(timer);
			each(tabs, function (t) { t.classList.remove('is-playing'); });
		}

		each(tabs, function (t, n) {
			t.addEventListener('click', function () { stop(); if (visible(n)) select(n, true); });
		});

		each(filters, function (f) {
			f.addEventListener('click', function () {
				var key = f.getAttribute('data-filter');
				each(filters, function (o) {
					o.classList.toggle('is-active', o === f);
					o.setAttribute('aria-pressed', o === f ? 'true' : 'false');
				});
				var first = -1;
				each(tabs, function (t, n) {
					var ok = key === 'all' || (' ' + t.getAttribute('data-tags') + ' ').indexOf(' ' + key + ' ') > -1;
					t.classList.toggle('is-dim', !ok);
					if (ok && first < 0) first = n;
				});
				stop();
				if (first > -1 && !visible(current)) select(first, true); else if (first > -1 && key !== 'all') select(first, true);
			});
		});

		// Autoplay only while the explorer is on screen and the pointer is not over it.
		var onScreen = false, hovering = false;
		var sync = function () {
			clearTimeout(timer);
			each(tabs, function (t) { t.classList.remove('is-playing'); });
			turnSeen = onScreen;
			turnLoop();
			if (playing && onScreen && !hovering) { select(current, false); schedule(); }
		};
		exp.addEventListener('mouseenter', function () { hovering = true; sync(); });
		exp.addEventListener('mouseleave', function () { hovering = false; sync(); });
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (es) { onScreen = es[0].isIntersecting; sync(); }, { threshold: 0.35 }).observe(exp);
		}
	}

	/* ── hero: real projection photos (Movies / Sport / Gaming) ─ */
	var heroEl = root.querySelector('[data-aunx-hero]');
	var shots = heroEl ? heroEl.querySelectorAll('.aunx-hero-shot') : [];
	if (shots.length) {
		var heroTabs = heroEl.querySelectorAll('.aunx-hero-tab');
		var heroCards = heroEl.querySelectorAll('.aunx-hshot');
		var word = heroEl.querySelector('[data-aunx-word]');
		var hs = 0, hsTimer = null, HS_DUR = 6000, hsAuto = !reduce && shots.length > 1, hsSeen = false;

		var showShot = function (i) {
			hs = i;
			each(shots, function (img, n) {
				if (n === i) img.loading = 'eager'; // a hidden photo must not wait for lazy-loading once it is chosen
				img.classList.toggle('is-active', n === i);
			});
			each(heroCards, function (c, n) { c.classList.toggle('is-active', n === i); });
			each(heroTabs, function (t, n) {
				t.classList.toggle('is-active', n === i);
				t.setAttribute('aria-pressed', n === i ? 'true' : 'false');
			});
			var next = shots[i].getAttribute('data-word');
			if (word && word.textContent !== next) {
				if (reduce) { word.textContent = next; }
				else {
					word.classList.add('is-out');
					setTimeout(function () { word.textContent = next; word.classList.remove('is-out'); }, 280);
				}
			}
			heroEl.classList.remove('is-playing');
			if (hsAuto && hsSeen) {
				void heroEl.offsetWidth; // restart the progress line
				heroEl.style.setProperty('--dur', HS_DUR + 'ms');
				heroEl.classList.add('is-playing');
			}
		};
		var runHero = function () {
			clearInterval(hsTimer);
			if (!hsAuto || !hsSeen) return;
			showShot(hs);
			hsTimer = setInterval(function () { showShot((hs + 1) % shots.length); }, HS_DUR);
		};
		each(heroTabs, function (t, n) {
			t.addEventListener('click', function () { hsAuto = false; clearInterval(hsTimer); showShot(n); });
		});
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (es) { hsSeen = es[0].isIntersecting; runHero(); }, { threshold: 0.2 }).observe(heroEl);
		} else { hsSeen = true; runHero(); }

		// Keep "world of <word>" on one line for every word, shrinking the headline if the column is too narrow,
		// so the page never jumps when the word changes.
		var h1 = heroEl.querySelector('h1');
		var line = h1 && h1.querySelector('em');
		var fitHeadline = function () {
			if (!line || !word) return;
			h1.classList.add('is-fit');
			h1.style.fontSize = '';
			var shown = word.textContent, widest = 0;
			each(shots, function (img) {
				word.textContent = img.getAttribute('data-word');
				widest = Math.max(widest, line.scrollWidth);
			});
			word.textContent = shown;
			var room = h1.clientWidth;
			if (widest > room) h1.style.fontSize = Math.floor(parseFloat(getComputedStyle(h1).fontSize) * room / widest) + 'px';
		};
		var fitTimer = null;
		fitHeadline();
		window.addEventListener('resize', function () { clearTimeout(fitTimer); fitTimer = setTimeout(fitHeadline, 150); });
		if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitHeadline);
	}

	/* ── warranty walkthrough ─────────────────────────────────── */
	var stepsBox = root.querySelector('[data-aunx-steps]');
	if (stepsBox) {
		var steps = stepsBox.querySelectorAll('.aunx-step');
		var si = 0, stepTimer = null;
		var setStep = function (i) {
			si = i;
			each(steps, function (s, n) {
				s.classList.toggle('is-active', n === i);
				s.setAttribute('aria-expanded', n === i ? 'true' : 'false');
			});
			// The support-chat illustration shows the conversation up to the active step.
			each(stepsBox.querySelectorAll('.aunx-msg'), function (m) {
				m.classList.toggle('is-on', parseInt(m.getAttribute('data-step'), 10) <= i);
			});
		};
		each(steps, function (s, n) {
			s.addEventListener('click', function () { clearInterval(stepTimer); stepTimer = null; setStep(n); });
		});
		if (!reduce && 'IntersectionObserver' in window) {
			new IntersectionObserver(function (es) {
				clearInterval(stepTimer);
				if (es[0].isIntersecting && stepTimer !== null) {
					stepTimer = setInterval(function () { setStep((si + 1) % steps.length); }, 3800);
				}
			}, { threshold: 0.4 }).observe(stepsBox);
			stepTimer = 0; // "autoplay allowed" until the visitor clicks a step
		}
	}

	/* ── in-page anchors scroll smoothly ──────────────────────── */
	each(root.querySelectorAll('a[href^="#aunx-"]'), function (a) {
		a.addEventListener('click', function (e) {
			var target = document.getElementById(a.getAttribute('href').slice(1));
			if (!target) return;
			e.preventDefault();
			var y = target.getBoundingClientRect().top + window.pageYOffset - 70;
			window.scrollTo({ top: y, behavior: reduce ? 'auto' : 'smooth' });
		});
	});
})();
JS;
}
