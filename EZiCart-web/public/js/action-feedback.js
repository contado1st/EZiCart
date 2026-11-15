(() => {
    const region = document.querySelector('[data-action-feedback-region]');
    if (!region) return;
    const pending = new Map();
    const show = (message, tone = 'info') => {
        if (typeof message !== 'string' || !message.trim()) return;
        const safeTone = ['success', 'error', 'warning', 'info'].includes(tone) ? tone : 'info';
        if ([...region.children].some(node => node.querySelector('p')?.textContent === message)) return;
        while (region.children.length >= 3) region.firstElementChild.remove();
        const notice = document.createElement('div');
        notice.className = 'action-feedback-notice'; notice.dataset.tone = safeTone;
        notice.setAttribute('role', safeTone === 'error' ? 'alert' : 'status');
        const text = document.createElement('p'); text.textContent = message;
        const close = document.createElement('button'); close.type = 'button'; close.textContent = 'Dismiss';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.addEventListener('click', () => notice.remove());
        notice.append(text, close); region.append(notice);
        if (safeTone === 'success' || safeTone === 'info') {
            let expiry;
            const schedule = () => { clearTimeout(expiry); expiry = setTimeout(() => { if (!notice.contains?.(document.activeElement)) notice.remove(); }, 8000); };
            notice.addEventListener('pointerenter', () => clearTimeout(expiry));
            notice.addEventListener('focusin', () => clearTimeout(expiry));
            notice.addEventListener('pointerleave', schedule);
            notice.addEventListener('focusout', schedule);
            schedule();
        }
    };
    document.addEventListener('ezicart:feedback', event => show(event.detail?.message, event.detail?.tone));
    const restore = () => {
        pending.forEach((state, form) => {
            clearTimeout(state.timeout);
            form.removeAttribute('aria-busy');
            state.button?.classList.remove('action-is-pending');
        });
        pending.clear();
    };
    window.addEventListener('pageshow', restore);
    const tones = {approve:'success',approved:'success',accept:'success',reject:'danger',rejected:'danger',decline:'danger',delete:'danger',cancel:'danger',cancelled:'danger',retry:'warning',recover:'warning'};
    const colorDecision = form => {
        const choice = form.querySelector('[name="decision"], [name="resolution"], [name="outcome"]');
        form.querySelectorAll('button[type="submit"]').forEach(button => {
            const value = (button.name && ['decision','resolution','outcome'].includes(button.name) ? button.value : choice?.value)?.toLowerCase();
            if (tones[value]) button.dataset.actionTone = tones[value];
            else if (choice) delete button.dataset.actionTone;
        });
        if (choice) form.dataset.decisionTone = tones[choice.value?.toLowerCase()] === 'danger' ? 'danger' : 'normal';
    };
    document.querySelectorAll('form[data-decision]').forEach(colorDecision);
    document.addEventListener('change', event => {
        const form = event.target.closest('form[data-decision]');
        if (form) colorDecision(form);
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.closest('[data-no-feedback]')) return;
        if (pending.has(form)) { event.preventDefault(); return; }
        queueMicrotask(() => {
            if (event.defaultPrevented || form.method.toLowerCase() === 'get') return;
            form.setAttribute('aria-busy', 'true');
            const button = event.submitter;
            button?.classList.add('action-is-pending');
            const timeout = setTimeout(() => {
                // A timeout is not proof that the operation failed or is safe to repeat.
                show('Still waiting for a response. Check the result before submitting this action again.', 'warning');
            }, 20000);
            pending.set(form, {button, timeout});
        });
    });
    let invalidTimer;
    document.addEventListener('invalid', event => {
        clearTimeout(invalidTimer);
        invalidTimer = setTimeout(() => show(event.target.validationMessage || 'Check the highlighted details before continuing.', 'error'), 50);
    }, true);
    try {
        const seeds = JSON.parse(document.querySelector('[data-action-feedback-seed]')?.textContent || '[]');
        seeds.forEach(item => {
            show(item.message, item.tone);
            document.querySelectorAll('.feature-feedback, .dash-alert, .ops-alert, .communications-notice').forEach(node => {
                if ((node.querySelector('p')?.textContent || node.textContent).trim() !== item.message.trim()) return;
                if (item.tone === 'success') node.hidden = true;
                else { node.removeAttribute('role'); node.removeAttribute('aria-live'); }
            });
        });
    } catch { /* Inline server feedback still works when enhancement cannot initialize. */ }
})();
