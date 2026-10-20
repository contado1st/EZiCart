(() => {
    const toggle = document.querySelector('[data-workspace-toggle]');
    const sidebar = document.getElementById('workspace-sidebar');
    const overlay = document.querySelector('.workspace-overlay');
    if (!toggle || !sidebar || !overlay) return;

    const mobile = window.matchMedia('(max-width: 900px)');
    const close = (restoreFocus = false) => {
        document.body.classList.remove('workspace-nav-open');
        overlay.hidden = true;
        sidebar.inert = mobile.matches;
        toggle.setAttribute('aria-expanded', String(!mobile.matches && !document.body.classList.contains('workspace-nav-collapsed')));
        if (restoreFocus) toggle.focus();
    };
    toggle.addEventListener('click', () => {
        if (mobile.matches) {
            if (document.body.classList.contains('workspace-nav-open')) return close(true);
            document.body.classList.add('workspace-nav-open');
            sidebar.inert = false;
            overlay.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            sidebar.querySelector('[data-workspace-close]').focus();
        } else {
            document.body.classList.toggle('workspace-nav-collapsed');
            toggle.setAttribute('aria-expanded', String(!document.body.classList.contains('workspace-nav-collapsed')));
        }
    });
    document.querySelectorAll('[data-workspace-close]').forEach(button => button.addEventListener('click', () => close(true)));
    sidebar.addEventListener('click', event => {
        if (mobile.matches && event.target.closest('a')) close();
    });
    document.addEventListener('keydown', event => {
        if (!mobile.matches || !document.body.classList.contains('workspace-nav-open')) return;
        if (event.key === 'Escape') close(true);
        if (event.key === 'Tab') {
            const focusable = [...sidebar.querySelectorAll('a[href], button')];
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    mobile.addEventListener('change', () => close());
    close();
})();
