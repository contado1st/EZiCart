(() => {
    document.querySelectorAll('[data-cart-variation-preview]').forEach(form => {
        const select = form.querySelector('[data-variant-preview-select]');
        const image = form.querySelector('[data-variant-preview-image]');
        const caption = form.querySelector('[data-variant-preview-caption]');
        if (!select || !image || !caption) return;
        const update = () => {
            const option = select.selectedOptions[0];
            const label = option?.dataset.previewLabel || '';
            let url = null;
            try {
                if (option?.dataset.previewImage) {
                    const candidate = new URL(option.dataset.previewImage, window.location.href);
                    if (['http:', 'https:'].includes(candidate.protocol)) url = candidate.href;
                }
            } catch (_) { /* Missing/invalid images use the text fallback. */ }
            image.hidden = !url;
            if (url) image.src = url; else image.removeAttribute('src');
            image.alt = label || 'Selected variation';
            caption.textContent = !label ? 'Choose a variation to preview its photo.'
                : !url ? `No photo available: ${label}`
                    : `${option.dataset.previewOwnImage === '1' ? 'Variation photo' : 'Product photo'}: ${label}`;
        };
        image.addEventListener('error', () => {
            image.hidden = true;
            image.removeAttribute('src');
            caption.textContent = `Photo unavailable: ${select.selectedOptions[0]?.dataset.previewLabel || 'Selected variation'}`;
        });
        select.addEventListener('change', update);
        update();
    });
})();
