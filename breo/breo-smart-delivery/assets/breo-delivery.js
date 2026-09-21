/* Breo Smart Delivery: area picker on the product page. Remembers the chosen
 * area for this visitor, and on a stale cached nonce ('bad_nonce') mints a
 * fresh one and replays the request once. */
(function () {
	'use strict';
	var cfg = window.breoDelivery;
	if (!cfg) return;
	var KEY = 'breoDlvZone';

	function post(data) {
		var fd = new FormData();
		Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.catch(function () { return null; });
	}

	document.querySelectorAll('[data-breo-dlv]').forEach(function (box) {
		var sel = box.querySelector('[data-breo-dlv-zone]');
		var out = box.querySelector('[data-breo-dlv-out]');
		if (!sel || !out) return;
		var original = out.innerHTML;

		function show(zone, retried) {
			return post({ action: 'breo_delivery_estimate', zone_id: zone, product_id: box.getAttribute('data-product') || 0, nonce: cfg.nonce }).then(function (res) {
				if (sel.value !== zone) return; // changed again meanwhile
				if (res && res.success) { out.innerHTML = res.data; return; }
				var d = res && res.data;
				if (d && d.code === 'bad_nonce' && !retried) {
					return post({ action: 'breo_delivery_nonce' }).then(function (n) {
						if (n && n.success && n.data && n.data.nonce) {
							cfg.nonce = n.data.nonce;
							return show(zone, true);
						}
						out.textContent = cfg.i18n.fail;
					});
				}
				out.textContent = cfg.i18n.fail;
			});
		}

		sel.addEventListener('change', function () {
			var zone = sel.value;
			try { if (zone) localStorage.setItem(KEY, zone); else localStorage.removeItem(KEY); } catch (e) {}
			if (!zone) { out.innerHTML = original; return; }
			out.innerHTML = '<span class="breo-dlv__wait">' + cfg.i18n.wait + '</span>';
			show(zone, false);
		});

		var saved = null;
		try { saved = localStorage.getItem(KEY); } catch (e) {}
		if (saved && sel.querySelector('option[value="' + saved.replace(/\D/g, '') + '"]')) {
			sel.value = saved;
			sel.dispatchEvent(new Event('change'));
		}
	});
})();
