(() => {
    document.querySelectorAll('[data-voucher-budget]').forEach(status => {
        const form = status.closest('form');
        const field = name => form.querySelector(`[name="${name}"]`);
        const money = cents => `PHP ${(cents / 100).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        const update = () => {
            const value = Math.round(Number(field('value')?.value) * 100);
            const max = Number(field('max_discount')?.value);
            const percent = field('type')?.value === 'percent';
            const cap = percent ? Math.round(max * 100) : Math.min(value, max > 0 ? Math.round(max * 100) : value);
            const uses = Number(field('usage_limit')?.value);
            const available = Number(status.dataset.availableCents);
            if (!Number.isInteger(uses) || uses < 1 || !Number.isFinite(cap) || cap < 1) {
                status.textContent = `Available: ${money(available)}. Enter a valid redemption limit${percent ? ' and maximum discount' : ' and discount'} to calculate the reserved budget. Scheduled offers reserve funds immediately.`;
                return;
            }
            const required = cap * uses;
            status.textContent = `Required: ${money(required)} · Available: ${money(available)} · ${required > available ? `Shortfall: ${money(required - available)}. Add campaign funds before creating this voucher.` : 'Sufficient campaign funds.'} Funds are reserved immediately, including scheduled offers.`;
        };
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        update();
    });
})();
