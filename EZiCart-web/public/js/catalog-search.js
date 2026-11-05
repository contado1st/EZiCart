(() => {
    const filters = document.querySelector('[data-catalog-filters]');
    if (!filters) return;
    const desktop = window.matchMedia('(min-width: 900px)');
    filters.open = desktop.matches;
    desktop.addEventListener('change', event => { filters.open = event.matches; });
})();
