(() => {
    const editor = document.querySelector('[data-variation-editor]');
    if (!editor) return;
    const rows = editor.querySelector('[data-variation-rows]');
    const template = editor.querySelector('[data-variation-template]');
    const feedback = editor.querySelector('[data-variation-feedback]');
    const stock = editor.closest('form').querySelector('[name="stock"]');
    const toggle = editor.closest('form').querySelector('[data-variations-toggle]');
    const base = editor.closest('form').querySelector('[data-base-pricing]');
    const optionsPanel = editor.querySelector('[data-variation-options]');
    const summary = editor.querySelector('[data-variation-summary]');
    let next = Math.max(-1, ...[...rows.querySelectorAll('[name]')].map(input => Number(input.name.match(/variations\[(\d+)\]/)?.[1] ?? -1))) + 1;
    const updateStock = () => {
        const inputs = [...rows.querySelectorAll('[data-variation-stock]')];
        const total = inputs.reduce((sum, input) => sum + Math.max(0, Number(input.value) || 0), 0);
        if (!toggle) {
            stock.readOnly = inputs.length > 0;
            if (inputs.length) stock.value = total;
            return;
        }
        const prices = [...rows.querySelectorAll('[data-variation-price]')].map(input => Number(input.value));
        const valid = prices.length && prices.every(price => Number.isFinite(price) && price >= 0.01);
        const money = value => new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(value);
        summary.textContent = valid ? `Estimated price: ${money(Math.min(...prices))}${Math.min(...prices) !== Math.max(...prices) ? ` – ${money(Math.max(...prices))}` : ''} · Combined stock: ${total}` : 'Enter a price and stock for each variation to preview the product card.';
    };
    const updateMode = () => {
        base.hidden = toggle.checked;
        base.disabled = toggle.checked;
        optionsPanel.hidden = !toggle.checked;
        optionsPanel.disabled = !toggle.checked;
        toggle.setAttribute('aria-expanded', String(toggle.checked));
        updateStock();
    };
    if (toggle) toggle.addEventListener('change', updateMode);
    const add = (type = '', value = '') => {
        if (rows.children.length >= 100) { feedback.textContent = 'A product can have at most 100 combinations.'; return; }
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('[data-variation-row]');
        fragment.querySelectorAll('[name]').forEach(input => input.name = input.name.replace('__INDEX__', String(next)));
        fragment.querySelector('[data-variation-type]').value = type;
        fragment.querySelector('[data-variation-value]').value = value;
        next++;
        rows.append(fragment); updateStock();
        return row;
    };
    editor.querySelector('[data-add-variation]').addEventListener('click', () => add());
    editor.querySelector('[data-add-manual-variation]')?.addEventListener('click', () => {
        const attribute = editor.querySelector('[data-manual-attribute]');
        const option = editor.querySelector('[data-manual-option]');
        const type = attribute.value.trim(), value = option.value.trim();
        if (!type || !value || type.length > 50 || value.length > 50) {
            feedback.textContent = 'Enter an attribute and an option, each up to 50 characters.';
            return;
        }
        const duplicate = [...rows.querySelectorAll('[data-variation-row]')].some(row =>
            row.querySelector('[data-variation-type]').value.trim().toLowerCase() === type.toLowerCase()
            && row.querySelector('[data-variation-value]').value.trim().toLowerCase() === value.toLowerCase());
        if (duplicate) { feedback.textContent = 'That variation already exists. Edit its row below.'; return; }
        const row = add(type, value);
        if (!row) return;
        option.value = '';
        row.querySelector('[data-variation-price]').focus();
        feedback.textContent = 'Manual variation added. Enter its price and stock before saving the product.';
    });
    editor.addEventListener('click', event => {
        const remove = event.target.closest('[data-remove-variation]');
        if (remove) { remove.closest('[data-variation-row]').remove(); updateStock(); feedback.textContent = 'Row removed. Save the product to retire this variation.'; }
    });
    rows.addEventListener('input', updateStock);
    editor.querySelector('[data-generate-variations]').addEventListener('click', () => {
        const names = [...editor.querySelectorAll('[data-attribute-name]')].map(input => input.value.trim());
        const options = [...editor.querySelectorAll('[data-attribute-options]')].map(input => [...new Set(input.value.split(',').map(value => value.trim()).filter(Boolean))]);
        if (!names[0] || !options[0].length || (!!names[1] !== !!options[1].length)) { feedback.textContent = 'Enter an attribute name and its options. Complete both fields for a second attribute.'; return; }
        const type = names.filter(Boolean).join(' / ');
        const combinations = options[0].flatMap(first => options[1].length ? options[1].map(second => `${first} / ${second}`) : [first]);
        const current = [...rows.querySelectorAll('[data-variation-row]')].map(row => `${row.querySelector('[data-variation-type]').value.trim()}:${row.querySelector('[data-variation-value]').value.trim()}`.toLowerCase());
        const missing = combinations.filter(value => !current.includes(`${type}:${value}`.toLowerCase()));
        if (type.length > 50 || combinations.some(value => value.length > 50) || missing.length + rows.children.length > 100) { feedback.textContent = 'Use shorter attributes/options and at most 100 total combinations.'; return; }
        missing.forEach(value => add(type, value));
        feedback.textContent = `${missing.length} combinations added. Set prices, stock, SKUs and optional photos before saving.`;
    });
    if (toggle) updateMode(); else updateStock();
})();
