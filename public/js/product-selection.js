(() => {
    const form = document.querySelector('[data-product-selection]');
    if (!form) return;
    const select = form.querySelector('[name="variation_id"]');
    const quantity = form.querySelector('[name="quantity"]');
    const price = document.getElementById('basePriceDisplay');
    const stockLabel = document.getElementById('stockDisplay');
    const submit = form.querySelector('[type="submit"]');
    const image = form.querySelector('[data-variation-image]');
    const update = () => {
        const option = select?.selectedOptions[0];
        const selected = !select || !!select.value;
        const stock = Math.min(Number(form.dataset.stock), select && selected ? Number(option.dataset.stock) : Number(form.dataset.stock));
        const amount = Number(form.dataset.price) + (select && selected ? Number(option.dataset.adjustment) || 0 : 0);
        price.textContent = new Intl.NumberFormat('en-PH', {style:'currency', currency:'PHP'}).format(amount);
        stockLabel.textContent = select && !selected ? 'Choose an available combination' : `${stock} pieces available`;
        quantity.max = String(Math.max(0, stock));
        quantity.disabled = !selected || stock < 1;
        if (stock > 0 && Number(quantity.value) > stock) quantity.value = String(stock);
        submit.disabled = form.dataset.shopOpen !== '1' || !selected || stock < 1;
        image.hidden = !option?.dataset.image;
        if (!image.hidden) { image.src = option.dataset.image; image.alt = `Selected variation: ${option.textContent.trim()}`; }
        else image.removeAttribute('src');
    };
    select?.addEventListener('change', update);
    update();
})();
