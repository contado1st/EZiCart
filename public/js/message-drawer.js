(() => {
    const drawer = document.getElementById('message-drawer');
    if (!drawer) return;
    const toggles = [...document.querySelectorAll('[data-message-drawer-toggle]')];
    const closeButton = drawer.querySelector('[data-message-drawer-close]');
    const frame = drawer.querySelector('[data-message-drawer-frame]');
    let returnFocus = null;
    const close = () => {
        drawer.hidden = true;
        toggles.forEach(toggle => toggle.setAttribute('aria-expanded', 'false'));
        if (returnFocus?.isConnected) returnFocus.focus();
    };
    const escape = event => {
        if (event.defaultPrevented || document.querySelector('dialog[open]')) return;
        if (event.key === 'Escape' && !drawer.hidden) { event.preventDefault(); close(); }
    };
    toggles.forEach(toggle => {
        toggle.hidden = false;
        toggle.setAttribute('aria-controls', drawer.id);
        toggle.setAttribute('aria-expanded', 'false');
        toggle.addEventListener('click', event => {
            event.preventDefault();
            if (!drawer.hidden) { close(); return; }
            returnFocus = toggle;
            drawer.hidden = false;
            toggles.forEach(button => button.setAttribute('aria-expanded', 'true'));
            if (!frame.hasAttribute('src')) frame.src = frame.dataset.src;
            closeButton.focus();
        });
    });
    closeButton.addEventListener('click', close);
    document.addEventListener('keydown', escape);
    frame.addEventListener('load', () => {
        document.dispatchEvent(new Event('ezicart:message-panel-loaded'));
        try { frame.contentDocument?.addEventListener('keydown', escape); } catch { /* Cross-origin pages cannot be inspected. */ }
    });
})();
