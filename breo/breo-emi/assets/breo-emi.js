/* Breo EMI Plans: builds the bank list + plan table from the JSON config and
 * runs the pop-up (open/close, Esc, focus trap, bank search). */
(function () {
	'use strict';
	var d = document;
	var fmt = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });

	function money(sym, n) { return sym + fmt.format(Math.round(n)); }
	function rate(r) { return (Math.round(r * 100) / 100) + '%'; }

	function board(el) {
		var cfg;
		try { cfg = JSON.parse(el.getAttribute('data-breo-emi')); } catch (e) { return; }
		var list = el.querySelector('.breo-emi-banklist');
		var body = el.querySelector('tbody');
		var nameEl = el.querySelector('[data-breo-emi-bank]');
		var priceEl = el.querySelector('[data-breo-emi-price]');
		var find = el.querySelector('[data-breo-emi-find]');
		var current = 0;

		function renderTable() {
			var b = cfg.banks[current];
			if (!b) return;
			nameEl.textContent = b[0];
			priceEl.textContent = money(cfg.symbol, cfg.price);
			var rows = '';
			var best = null;
			b[2].forEach(function (p, i) {
				var total = Math.round(cfg.price * (1 + p[1] / 100));
				var monthly = Math.round(total / p[0]);
				if (best === null || monthly < best.m) best = { m: monthly, i: i };
				rows += '<tr' + (p[1] === 0 ? ' class="is-zero"' : '') + '><td>' + p[0] + ' months</td><td>' +
					(p[1] === 0 ? '<span class="breo-emi-zero">0% interest</span>' : rate(p[1])) + '</td><td><strong>' +
					money(cfg.symbol, monthly) + '</strong></td><td>' + money(cfg.symbol, total) + '</td></tr>';
			});
			body.innerHTML = rows;
		}

		function renderBanks() {
			var q = find ? find.value.trim().toLowerCase() : '';
			var html = '';
			cfg.banks.forEach(function (b, i) {
				if (q && b[0].toLowerCase().indexOf(q) === -1) return;
				html += '<li><button type="button" role="option" data-i="' + i + '" aria-selected="' + (i === current) + '"' +
					(i === current ? ' class="is-active"' : '') + '>' + b[0].replace(/[<>&"]/g, '') + '</button></li>';
			});
			list.innerHTML = html || '<li class="breo-emi-none">No bank found</li>';
		}

		list.addEventListener('click', function (e) {
			var btn = e.target.closest('button[data-i]');
			if (!btn) return;
			current = +btn.getAttribute('data-i');
			renderBanks();
			renderTable();
		});
		if (find) find.addEventListener('input', renderBanks);

		var amount = el.parentNode && el.parentNode.querySelector('[data-breo-emi-amount]');
		if (amount) {
			amount.addEventListener('input', function () {
				var v = parseFloat(amount.value);
				cfg.price = isFinite(v) && v > 0 ? v : 0;
				renderTable();
			});
		}

		renderBanks();
		renderTable();
	}

	function trap(overlay, e) {
		if (e.key !== 'Tab') return;
		var f = overlay.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])');
		if (!f.length) return;
		var first = f[0], last = f[f.length - 1];
		if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
		else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
	}

	var lastTrigger = null;
	function open(overlay, trigger) {
		lastTrigger = trigger;
		overlay.hidden = false;
		requestAnimationFrame(function () { overlay.classList.add('is-open'); });
		d.documentElement.classList.add('breo-emi-lock');
		var c = overlay.querySelector('[data-breo-emi-close]');
		if (c) c.focus();
	}
	function close(overlay) {
		overlay.classList.remove('is-open');
		d.documentElement.classList.remove('breo-emi-lock');
		setTimeout(function () { overlay.hidden = true; }, 200);
		if (lastTrigger) lastTrigger.focus();
	}

	d.querySelectorAll('[data-breo-emi]').forEach(board);

	d.addEventListener('click', function (e) {
		var t = e.target.closest('[data-breo-emi-open]');
		if (t) {
			var o = d.getElementById(t.getAttribute('data-breo-emi-open'));
			if (o) { e.preventDefault(); open(o, t); }
			return;
		}
		var overlay = e.target.closest('.breo-emi-overlay');
		if (overlay && (e.target === overlay || e.target.closest('[data-breo-emi-close]'))) close(overlay);
	});
	d.addEventListener('keydown', function (e) {
		var o = d.querySelector('.breo-emi-overlay.is-open');
		if (!o) return;
		if (e.key === 'Escape') close(o);
		else trap(o, e);
	});
})();
