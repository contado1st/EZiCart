(() => {
    const page = document.querySelector('[data-cart-selection]');
    if (!page) return;
    const items = [...page.querySelectorAll('[data-cart-item]')];
    const available = items.filter(item => !item.disabled);
    const storageKey = `ezicart-cart-selection-${page.dataset.buyer}`;
    let saved = null;
    try { saved = JSON.parse(sessionStorage.getItem(storageKey)); } catch (_) { /* Selection works without storage. */ }
    if (Array.isArray(saved) && page.dataset.variationPreviousKey && page.dataset.variationNewKey) {
        saved = [...new Set(saved.map(key => key === page.dataset.variationPreviousKey ? page.dataset.variationNewKey : key))];
    }
    if (Array.isArray(saved)) available.forEach(item => { item.checked = saved.includes(item.value); });
    const keepShop = shopId => available.forEach(item => { if (item.dataset.shopId !== shopId) item.checked = false; });
    const initial = available.find(item => item.checked);
    if (initial) keepShop(initial.dataset.shopId);
    const money = new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'});
    let quoteVersion = 0;
    let quoteTimer;
    const refreshQuote = selected => {
        if (!page.dataset.quoteUrl) return;
        const version = ++quoteVersion;
        clearTimeout(quoteTimer);
        if (!selected.length) return;
        const button = page.querySelector('[form="cart-checkout-selection"][type="submit"]');
        const total = page.querySelector('[data-selected-total]');
        button.disabled = true;
        total.textContent = 'Checking offers…';
        quoteTimer = setTimeout(async () => {
            try {
                const response = await fetch(page.dataset.quoteUrl, {method: 'POST', credentials: 'same-origin', headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('#cart-checkout-selection [name="_token"]')?.value ?? '',
                }, body: JSON.stringify({selected_keys: selected.map(item => item.value)})});
                const data = await response.json();
                if (version !== quoteVersion) return;
                if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat()[0] : data.message || 'Review the selected items.');
                total.textContent = money.format(data.subtotal_cents / 100);
                selected.forEach(item => {
                    const line = item.closest('[data-cart-line]');
                    const fresh = data.lines?.[item.value];
                    if (!line || !fresh) return;
                    line.querySelector('[data-line-total]').textContent = money.format(fresh.item_cents / 100);
                    line.querySelectorAll('[data-line-breakdown]').forEach(node => { node.textContent = fresh.segments.map(segment => `${segment.quantity} × ${money.format(segment.unit_cents / 100)} ${segment.label}`).join(' + '); });
                });
                page.querySelector('[data-selection-hint]').textContent = data.message;
                button.disabled = false;
            } catch (error) {
                if (version !== quoteVersion) return;
                total.textContent = 'Review selection';
                page.querySelector('[data-selection-hint]').textContent = error.message || 'Could not refresh offers. Reload the cart.';
            }
        }, 150);
    };
    const update = () => {
        const selected = available.filter(item => item.checked);
        page.querySelector('[data-selected-count]').textContent = String(selected.length);
        page.querySelector('[data-selected-button-count]').textContent = `(${selected.length})`;
        page.querySelector('[data-selected-total]').textContent = money.format(selected.reduce((sum, item) => sum + Number(item.dataset.totalCents ?? Number(item.dataset.cents) * Number(item.dataset.quantity)), 0) / 100);
        page.querySelectorAll('[data-shop]').forEach(shop => {
            const check = shop.querySelector('[data-select-shop]');
            const eligible = [...shop.querySelectorAll('[data-cart-item]')].filter(item => !item.disabled);
            const count = eligible.filter(item => item.checked).length;
            check.checked = eligible.length > 0 && count === eligible.length;
            check.indeterminate = count > 0 && count < eligible.length;
            check.disabled = eligible.length === 0;
        });
        page.querySelector('[form="cart-checkout-selection"][type="submit"]').disabled = selected.length === 0;
        try { sessionStorage.setItem(storageKey, JSON.stringify(selected.map(item => item.value))); } catch (_) { /* Optional preference. */ }
        refreshQuote(selected);
    };
    page.querySelectorAll('[data-select-shop]').forEach(box => box.addEventListener('change', () => {
        const eligible = [...box.closest('[data-shop]').querySelectorAll('[data-cart-item]:not(:disabled)')];
        if (box.checked && eligible.length) keepShop(eligible[0].dataset.shopId);
        eligible.forEach(item => { item.checked = box.checked; });
        update();
    }));
    items.forEach(item => item.addEventListener('change', () => { if (item.checked) keepShop(item.dataset.shopId); update(); }));
    page.querySelectorAll('[data-qty-step]').forEach(button => button.addEventListener('click', () => {
        const form = button.closest('form');
        const input = form.querySelector('[name="quantity"]');
        const value = Number(input.value) + Number(button.dataset.qtyStep);
        if (value < 1 || value > 20) return;
        input.value = String(value);
        form.requestSubmit();
    }));
    if (items.length) update();
})();
