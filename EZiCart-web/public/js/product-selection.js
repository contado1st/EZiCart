(() => {
    const form = document.querySelector('[data-product-selection]');
    if (!form) return;
    const select = form.querySelector('[name="variation_id"]');
    const quantity = form.querySelector('[name="quantity"]');
    const price = document.getElementById('basePriceDisplay');
    const stockLabel = document.getElementById('stockDisplay');
    const submits = form.querySelectorAll('[type="submit"]');
    const image = form.querySelector('[data-variation-image]');
    const breakdown = form.querySelector('[data-price-breakdown]');
    const clockOffset = form.dataset.serverNow ? Date.parse(form.dataset.serverNow) - Date.now() : 0;
    const update = () => {
        const option = select?.selectedOptions[0];
        const selected = !select || !!select.value;
        const stock = Math.min(Number(form.dataset.stock), select && selected ? Number(option.dataset.stock) : Number(form.dataset.stock));
        const fallback = Number(form.dataset.fallbackPrice ?? form.dataset.price) + (select && selected ? Number(option.dataset.fallbackAdjustment ?? option.dataset.adjustment) || 0 : 0);
        const currentTime = Date.now() + clockOffset;
        const flashActive = Number(form.dataset.flashRemaining) > 0 && (!form.dataset.flashStartsAt || Date.parse(form.dataset.flashStartsAt) <= currentTime) && Date.parse(form.dataset.flashEndsAt) > currentTime;
        const amount = flashActive ? Number(form.dataset.flashPrice) : fallback;
        const range = flashActive ? form.dataset.priceRange : (form.dataset.fallbackRange ?? form.dataset.priceRange);
        price.textContent = select && !selected && range
            ? range
            : new Intl.NumberFormat('en-PH', {style:'currency', currency:'PHP'}).format(amount);
        const badge = price.parentElement?.querySelector('[data-price-badge]');
        if (badge) badge.hidden = !flashActive && form.dataset.fallbackSale !== '1';
        stockLabel.textContent = select && !selected ? 'Choose an available combination' : `${stock} pieces available`;
        quantity.max = String(Math.max(0, Math.min(20, stock)));
        quantity.disabled = !selected || stock < 1;
        if (stock > 0 && Number(quantity.value) > Number(quantity.max)) quantity.value = quantity.max;
        if (breakdown) {
            const flashUnits = flashActive && selected ? Math.min(Number(quantity.value), Number(form.dataset.flashRemaining)) : 0;
            const remaining = Number(quantity.value) - flashUnits;
            const money = value => new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(value);
            const message = flashUnits > 0 ? `${flashUnits} × ${money(amount)} flash${remaining > 0 ? ` + ${remaining} × ${money(fallback)} current price` : ''}. Checkout confirms available units.` : '';
            if (breakdown.textContent !== message) breakdown.textContent = message;
        }
        submits.forEach(submit => { submit.disabled = form.dataset.shopOpen !== '1' || !selected || stock < 1; });
        image.hidden = !option?.dataset.image;
        if (!image.hidden) { image.src = option.dataset.image; image.alt = `Selected variation: ${option.textContent.trim()}`; }
        else image.removeAttribute('src');
    };
    select?.addEventListener('change', update);
    quantity.addEventListener?.('input', update);
    if (form.dataset.flashEndsAt) setInterval(update, 1000);
    update();
})();
