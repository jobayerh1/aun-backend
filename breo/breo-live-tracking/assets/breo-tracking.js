/* Breo Live Tracking: submit the lookup, render the server's HTML, and on a
 * stale cached nonce ('bad_nonce') mint a fresh one and replay exactly once. */
(function () {
	'use strict';
	var cfg = window.breoTracking;
	var root = document.querySelector('[data-breo-trk]');
	if (!cfg || !root) return;

	var form = root.querySelector('form');
	var input = root.querySelector('#breo-trk-q');
	var btn = root.querySelector('.breo-trk__btn');
	var out = root.querySelector('.breo-trk__result');
	var t = cfg.i18n || {};

	function esc(s) {
		var d = document.createElement('div');
		d.textContent = String(s);
		return d.innerHTML;
	}
	function note(msg) { out.innerHTML = '<div class="breo-trk-note is-error" role="alert">' + esc(msg) + '</div>'; }
	function post(data) {
		var fd = new FormData();
		Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.catch(function () { return { success: false, data: t.conn }; });
	}
	function lookup(q, retried) {
		return post({ action: 'breo_track_order', search: q, nonce: cfg.nonce }).then(function (res) {
			if (res && res.success) {
				out.innerHTML = res.data.html;
				out.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				return;
			}
			var d = res ? res.data : null;
			if (d && d.code === 'bad_nonce' && !retried) {
				return post({ action: 'breo_track_nonce' }).then(function (n) {
					if (n && n.success && n.data && n.data.nonce) {
						cfg.nonce = n.data.nonce;
						return lookup(q, true);
					}
					note(t.reload);
				});
			}
			note(typeof d === 'string' ? d : (d && d.message) || t.generic);
		});
	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var q = input.value.trim();
		if (!q) { note(t.empty); input.focus(); return; }
		btn.disabled = true;
		btn.textContent = t.tracking;
		out.innerHTML = '<div class="breo-trk-loading"><span></span>' + esc(t.fetching) + '</div>';
		lookup(q, false).then(function () {
			btn.disabled = false;
			btn.textContent = t.track;
		});
	});

	// Deep link: /track-order/?order=1024 runs the lookup straight away.
	if (input.value.trim()) {
		form.dispatchEvent(new Event('submit', { cancelable: true }));
	}
})();
