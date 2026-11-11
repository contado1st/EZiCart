(() => {
    const groups = [];
    document.querySelectorAll('[data-workspace-tabs]').forEach((navigation) => {
        const links = Array.from(navigation.querySelectorAll('[data-workspace-tab]'));
        const entries = links.map((link) => ({link, panel: document.getElementById(link.dataset.workspaceTab)}));
        if (entries.length < 2 || entries.some(({panel}) => !panel)) return;

        navigation.setAttribute('role', 'tablist');
        entries.forEach(({link, panel}) => {
            link.setAttribute('role', 'tab');
            link.setAttribute('aria-controls', panel.id);
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', link.id);
            panel.setAttribute('tabindex', '0');
            panel.dataset.workspaceTabPanel = '';
            if (panel.parentElement.matches('.dash-workflow-grid, .workspace-admin-grid, .ops-grid')) {
                panel.parentElement.classList.add('workspace-tab-layout');
            }
            // Keep the current section after filters and numbered-page navigation.
            panel.querySelectorAll('.workspace-page-nav a').forEach((pageLink) => {
                const url = new URL(pageLink.href, window.location.href);
                url.searchParams.set('workspace_tab', panel.id);
                url.hash = panel.id;
                pageLink.href = url.href;
            });
            panel.querySelectorAll('form').forEach((form) => {
                if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
                let input = form.querySelector('input[name="workspace_tab"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'workspace_tab';
                    form.appendChild(input);
                }
                input.value = panel.id;
            });
        });

        const entryForTarget = (target) => entries.find(({panel}) => panel === target || panel.contains(target));
        const select = (entry, {updateUrl = false, focus = false} = {}) => {
            const previousFocus = document.activeElement;
            entries.forEach(({link, panel}) => {
                const selected = panel === entry.panel;
                link.setAttribute('aria-selected', String(selected));
                link.tabIndex = selected ? 0 : -1;
                panel.hidden = !selected;
            });
            if (updateUrl) {
                const url = new URL(window.location.href);
                url.searchParams.set('workspace_tab', entry.panel.id);
                url.hash = entry.panel.id;
                window.history.pushState(null, '', url.href);
            }
            if (focus || entries.some(({panel}) => panel.hidden && panel.contains(previousFocus))) entry.link.focus();
        };
        const restore = (initial = false) => {
            const url = new URL(window.location.href);
            let target;
            try { target = document.getElementById(decodeURIComponent(url.hash.slice(1))); } catch { /* Ignore malformed fragments. */ }
            const error = initial && entries.find(({panel}) => panel.querySelector('[aria-invalid="true"], .auth-field-error'));
            const entry = error || (target && entryForTarget(target))
                || entries.find(({panel}) => panel.id === url.searchParams.get('workspace_tab'))
                || entries.find(({panel}) => panel.id === navigation.dataset.defaultTab) || entries[0];
            select(entry);
        };
        entries.forEach((entry, index) => {
            entry.link.addEventListener('click', (event) => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button > 0) return;
                event.preventDefault();
                select(entry, {updateUrl: true});
            });
            entry.link.addEventListener('keydown', (event) => {
                const destinations = {ArrowRight: (index + 1) % entries.length, ArrowLeft: (index - 1 + entries.length) % entries.length, Home: 0, End: entries.length - 1};
                if (!(event.key in destinations)) return;
                event.preventDefault();
                select(entries[destinations[event.key]], {updateUrl: true, focus: true});
            });
        });
        groups.push({entryForTarget, select, restore});
        restore(true);
    });
    const restoreAll = () => groups.forEach((group) => group.restore());
    window.addEventListener('popstate', restoreAll);
    window.addEventListener('hashchange', restoreAll);
    // Existing workflow links can point to a heading or form inside a hidden panel.
    document.addEventListener('click', (event) => {
        const anchor = event.target.closest('a[href]');
        if (!anchor || anchor.hasAttribute('data-workspace-tab')) return;
        const targetUrl = new URL(anchor.href, window.location.href);
        if (targetUrl.origin !== window.location.origin || targetUrl.pathname !== window.location.pathname || !targetUrl.hash) return;
        let target;
        try { target = document.getElementById(decodeURIComponent(targetUrl.hash.slice(1))); } catch { return; }
        if (!target) return;
        groups.forEach((group) => {
            const entry = group.entryForTarget(target);
            if (entry) group.select(entry);
        });
    });
    document.addEventListener('invalid', (event) => {
        groups.forEach((group) => {
            const entry = group.entryForTarget(event.target);
            if (entry) group.select(entry);
        });
    }, true);
})();
