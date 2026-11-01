(() => {
    document.querySelectorAll('[data-activity-toggle]').forEach((button) => {
        const row = document.getElementById(button.getAttribute('aria-controls'));
        if (!row) return;
        row.hidden = true;
        button.hidden = false;
        button.setAttribute('aria-expanded', 'false');
        button.addEventListener('click', () => {
            const open = button.getAttribute('aria-expanded') !== 'true';
            row.hidden = !open;
            button.setAttribute('aria-expanded', String(open));
            button.querySelector('[data-activity-toggle-label]').textContent = open ? 'Hide changes' : 'View changes';
        });
    });
})();
