(() => {
    if (typeof HTMLDialogElement === 'undefined') return;
    document.querySelectorAll('[data-task-panel]').forEach(panel => {
        const triggers = [...document.querySelectorAll('[data-task-open]')].filter(trigger => trigger.dataset.taskOpen === panel.id);
        if (!triggers.length || !panel.querySelector('h2')) return;
        const dialog = document.createElement('dialog');
        dialog.className = 'short-task-dialog';
        dialog.id = `${panel.id}-dialog`;
        const heading = panel.querySelector('h2');
        heading.id ||= `${panel.id}-heading`;
        dialog.setAttribute('aria-labelledby', heading.id);
        const close = document.createElement('button');
        close.type = 'button'; close.className = 'short-task-close'; close.textContent = 'Close';
        close.setAttribute('aria-label', `Close ${heading.textContent.trim()}`);
        let opener;
        panel.before(dialog); dialog.append(close, panel);
        close.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => {
            document.body.classList.remove('short-task-open');
            if (panel.querySelector('form[aria-busy="true"]')) document.dispatchEvent(new CustomEvent('ezicart:feedback', {detail:{message:'The request may still complete. Check your wallet history before trying again.',tone:'warning'}}));
            triggers.forEach(trigger => trigger.setAttribute('aria-expanded', 'false'));
            opener?.focus({preventScroll:true});
        });
        triggers.forEach(trigger => {
            trigger.setAttribute('aria-haspopup', 'dialog');
            trigger.setAttribute('aria-controls', dialog.id);
            trigger.setAttribute('aria-expanded', 'false');
            trigger.addEventListener('click', event => {
                event.preventDefault();
                if (document.querySelector('dialog[open]')) return;
                opener = trigger;
                dialog.showModal(); document.body.classList.add('short-task-open'); close.focus();
                triggers.forEach(item => item.setAttribute('aria-expanded', 'true'));
            });
        });
        // Failed server validation and normal deep links reopen the relevant short task.
        if (location.hash === `#${panel.id}` || panel.querySelector('[data-task-reopen]')) {
            if (!document.querySelector('dialog[open]')) {
                opener = triggers[0]; dialog.showModal(); document.body.classList.add('short-task-open'); close.focus();
                triggers.forEach(item => item.setAttribute('aria-expanded', 'true'));
            }
        }
    });
})();
