(() => {
    const page = document.querySelector('[data-cart-selection]');
    if (!page) return;
    const items = [...page.querySelectorAll('[data-cart-item]')];
    const available = items.filter(item => !item.disabled);
    const storageKey = `ezicart-cart-selection-${page.dataset.buyer}`;
    let saved = null;
    try { saved = JSON.parse(sessionStorage.getItem(storageKey)); } catch (_) { /* Selection works without storage. */ }
    if (Array.isArray(saved)) available.forEach(item => { item.checked = saved.includes(item.value); });
    const keepShop = shopId => available.forEach(item => { if (item.dataset.shopId !== shopId) item.checked = false; });
    const initial = available.find(item => item.checked);
    if (initial) keepShop(initial.dataset.shopId);
    const money = new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'});
    const update = () => {
        const selected = available.filter(item => item.checked);
        page.querySelector('[data-selected-count]').textContent = String(selected.length);
        page.querySelector('[data-selected-button-count]').textContent = `(${selected.length})`;
        page.querySelector('[data-selected-total]').textContent = money.format(selected.reduce((sum, item) => sum + Number(item.dataset.cents) * Number(item.dataset.quantity), 0) / 100);
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
