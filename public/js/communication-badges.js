(() => {
    const script = document.querySelector('script[data-communication-count-url]');
    if (!script) return;
    let pending = false;
    let refreshAgain = false;
    const update = async () => {
        if (document.hidden) return;
        if (pending) { refreshAgain = true; return; }
        pending = true;
        try {
            const response = await fetch(script.dataset.communicationCountUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) return;
            const counts = await response.json();
            document.querySelectorAll('[data-communication-badge]').forEach(badge => {
                const count = counts[badge.dataset.communicationBadge];
                if (!Number.isInteger(count) || count < 0) return;
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.hidden = count === 0;
                const link = badge.closest('[data-communication-label]');
                if (link) link.setAttribute('aria-label', `${link.dataset.communicationLabel}${count ? `, ${count} unread` : ''}`);
            });
        } catch { /* Preserve the last known count if the connection is unavailable. */ }
        finally {
            pending = false;
            if (refreshAgain) { refreshAgain = false; update(); }
        }
    };
    window.setInterval(update, 30000);
    window.addEventListener('focus', update);
    document.addEventListener('visibilitychange', update);
    document.addEventListener('ezicart:message-panel-loaded', update);
})();
