(() => {
    const page = document.querySelector('.pdp');
    if (!page) return;
    const form = page.querySelector('[data-product-selection]');
    const select = form?.querySelector('[name="variation_id"]');
    const choices = form?.querySelector('[data-variation-buttons]');
    if (select && choices) {
        choices.hidden = false;
        select.classList.add('pdp-visually-hidden');
        const sync = () => choices.querySelectorAll('[data-variation-choice]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.variationChoice === select.value)));
        choices.addEventListener('click', event => {
            const button = event.target.closest('[data-variation-choice]');
            if (!button || button.disabled) return;
            select.value = button.dataset.variationChoice;
            select.dispatchEvent(new Event('change', {bubbles: true}));
        });
        select.addEventListener('change', sync);
        sync();
    }
    form?.querySelectorAll('[data-quantity-step]').forEach(button => button.addEventListener('click', () => {
        const input = form.querySelector('[name="quantity"]');
        if (input.disabled) return;
        input.value = String(Math.max(1, Math.min(Number(input.max), (Number(input.value) || 1) + Number(button.dataset.quantityStep))));
        input.dispatchEvent(new Event('change', {bubbles: true}));
    }));
    page.querySelector('[data-product-share]')?.addEventListener('click', async () => {
        const status = page.querySelector('[data-share-status]');
        const url = new URL(window.location.href);
        url.search = ''; url.hash = '';
        try {
            await navigator.clipboard.writeText(url.href);
            status.textContent = 'Product link copied';
        } catch {
            status.textContent = 'Copy the product address from your browser to share it.';
        }
    });
})();
