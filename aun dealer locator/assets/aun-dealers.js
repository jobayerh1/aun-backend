(function () {

    function el(tag, cls) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        return n;
    }

    function normalize(s) { return (s || '').toLowerCase().trim(); }

    function render(list) {
        var grid = document.getElementById('aunDealerGrid');
        if (!grid) return;
        grid.innerHTML = '';

        if (!list || !list.length) {
            var empty = el('div', 'aun-dealers-empty');
            empty.textContent = (AUN_DEALERS && AUN_DEALERS.i18n && AUN_DEALERS.i18n.no_results)
                ? AUN_DEALERS.i18n.no_results
                : 'No dealers found.';
            grid.appendChild(empty);
            return;
        }

        list.forEach(function (d) {
            var card = el('div', 'aun-dealer-card');

            var title = el('div', 'aun-dealer-title');
            title.textContent = d.name || '';
            card.appendChild(title);

            if (d.district) {
                var dist = el('div', 'aun-dealer-district');
                dist.textContent = d.district;
                card.appendChild(dist);
            }
            if (d.address) {
                var addr = el('div', 'aun-dealer-address');
                addr.textContent = d.address;
                card.appendChild(addr);
            }
            if (d.note) {
                var note = el('div', 'aun-dealer-note');
                note.textContent = d.note;
                card.appendChild(note);
            }

            var actions = el('div', 'aun-dealer-actions');
            var i18n = (AUN_DEALERS && AUN_DEALERS.i18n) || {};

            if (d.phone) {
                var callBtn = el('a', 'aun-btn-call');
                callBtn.href = 'tel:' + d.phone;
                var callIcon = el('i');
                callIcon.className = 'fa-solid fa-phone';
                callIcon.setAttribute('aria-hidden', 'true');
                var callLabel = el('span');
                callLabel.textContent = i18n.call || 'Call';
                callBtn.appendChild(callIcon);
                callBtn.appendChild(callLabel);
                actions.appendChild(callBtn);
            }

            // WhatsApp: number is already normalised to international format by PHP.
            // No hardcoded country-code prefix needed here.
            if (d.whatsapp) {
                var waBtn = el('a', 'aun-btn-wa');
                waBtn.href = 'https://wa.me/' + d.whatsapp;
                waBtn.target = '_blank';
                waBtn.rel = 'noopener noreferrer';
                var waIcon = el('i');
                waIcon.className = 'fa-brands fa-whatsapp';
                waIcon.setAttribute('aria-hidden', 'true');
                var waLabel = el('span');
                waLabel.textContent = i18n.whatsapp || 'WhatsApp';
                waBtn.appendChild(waIcon);
                waBtn.appendChild(waLabel);
                actions.appendChild(waBtn);
            }

            if (d.map_url) {
                var mapBtn = el('a', 'aun-btn-map');
                mapBtn.href = d.map_url;
                mapBtn.target = '_blank';
                mapBtn.rel = 'noopener noreferrer';
                var mapIcon = el('i');
                mapIcon.className = 'fa-solid fa-location-dot';
                mapIcon.setAttribute('aria-hidden', 'true');
                var mapLabel = el('span');
                mapLabel.textContent = i18n.navigate || 'Navigate';
                mapBtn.appendChild(mapIcon);
                mapBtn.appendChild(mapLabel);
                actions.appendChild(mapBtn);
            }

            if (actions.children.length) card.appendChild(actions);
            grid.appendChild(card);
        });
    }

    function boot() {
        var data = (window.AUN_DEALERS && Array.isArray(window.AUN_DEALERS.dealers))
            ? window.AUN_DEALERS.dealers
            : [];

        var i18n = (window.AUN_DEALERS && window.AUN_DEALERS.i18n) || {};

        // Pre-compute each dealer's searchable string once at boot.
        // Without this, every keystroke re-concatenates and normalises all fields
        // for every dealer — expensive on large lists.
        var indexed = data.map(function (d) {
            return {
                dealer: d,
                hay: normalize(
                    (d.name || '') + ' ' +
                    (d.district || '') + ' ' +
                    (d.address || '') + ' ' +
                    (d.note || '')
                )
            };
        });

        render(data);

        var search = document.getElementById('aunDealerSearch');
        if (search) {
            if (i18n.search_placeholder) {
                search.placeholder = i18n.search_placeholder;
            }
            search.addEventListener('input', function () {
                var q = normalize(search.value);
                if (!q) { render(data); return; }
                render(
                    indexed
                        .filter(function (item) { return item.hay.indexOf(q) !== -1; })
                        .map(function (item) { return item.dealer; })
                );
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
