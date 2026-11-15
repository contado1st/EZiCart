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
        if (event.defaultPrevented || document.querySelector('dialog[open]')) return;
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
    mobile.addEventListener('change', () => close(sidebar.contains(document.activeElement)));
    close();

    // Nav Group Accordion Dropdown State Persistence
    const nav = sidebar.querySelector('.workspace-navigation');
    if (nav) {
        const userRole = nav.dataset.userRole || 'default';
        const storageKey = `ezicart_sidebar_nav_${userRole}`;
        const dropdowns = nav.querySelectorAll('details.workspace-nav-dropdown');

        const persistDropdowns = () => {
            try {
                let data = {};
                try {
                    const raw = localStorage.getItem(storageKey);
                    if (raw) data = JSON.parse(raw);
                } catch (_) {}
                const state = data.groups || {};
                dropdowns.forEach(details => {
                    const gid = details.dataset.navGroup;
                    if (gid) state[gid] = details.open;
                });
                const activeEl = nav.querySelector('details.workspace-nav-dropdown[data-active-group="true"]');
                data.groups = state;
                if (activeEl) data.lastActive = activeEl.dataset.navGroup;
                localStorage.setItem(storageKey, JSON.stringify(data));
            } catch (_) {}
        };

        dropdowns.forEach(details => {
            details.addEventListener('toggle', persistDropdowns);
        });
    }
})();
