(() => {
    const form = document.querySelector('[data-product-selection]');
    const feedback = form?.querySelector('[data-cart-feedback]');
    if (!form || !feedback) return;
    form.addEventListener('submit', async event => {
        if (event.submitter?.value === 'buy_now') return;
        event.preventDefault();
        if (form.dataset.cartSaving === '1') return;
        form.dataset.cartSaving = '1';
        const buttons = [...form.querySelectorAll('[type="submit"]')];
        const disabled = buttons.map(button => button.disabled);
        const data = new FormData(form);
        buttons.forEach(button => { button.disabled = true; });
        feedback.hidden = false;
        feedback.textContent = 'Saving to your cart...';
        feedback.classList.remove('is-error');
        try {
            const response = await fetch(form.action, {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: data});
            let result;
            try { result = await response.json(); }
            catch (_) { throw new Error('Unable to confirm the save. Refresh your session and check your cart before trying again.'); }
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Could not save this item. Refresh and try again.');
            feedback.textContent = result.message;
            document.querySelectorAll('[data-cart-count]').forEach(badge => { badge.textContent = String(result.count); badge.hidden = result.count === 0; });
        } catch (error) {
            feedback.textContent = error.message || 'Unable to confirm the save. Check your cart before trying again.';
            feedback.classList.add('is-error');
        } finally {
            buttons.forEach((button, index) => { button.disabled = disabled[index]; });
            delete form.dataset.cartSaving;
        }
    });
})();
