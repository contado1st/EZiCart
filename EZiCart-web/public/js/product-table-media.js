(() => {
    const recover = image => {
        if (!image.matches?.('[data-product-table-image]')) return;
        image.hidden = true;
        image.parentElement?.querySelector('.product-table-image-fallback')?.removeAttribute('aria-hidden');
    };
    document.addEventListener('error', event => recover(event.target), true);
    document.querySelectorAll('[data-product-table-image]').forEach(image => {
        if (image.complete && image.naturalWidth === 0) recover(image);
    });
})();
