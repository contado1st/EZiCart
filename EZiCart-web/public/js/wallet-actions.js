(() => {
    const pending = new WeakSet();
    const starts = new WeakMap();
    const notify = (message, tone) => document.dispatchEvent(new CustomEvent('ezicart:feedback', {detail:{message,tone}}));
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-wallet-review], [data-wallet-send], [data-wallet-issue], [data-wallet-redeem]')) return;
        const content = form.closest('[data-wallet-task-content]');
        if (!content || event.defaultPrevented) return;
        event.preventDefault();
        if (pending.has(form)) return;
        pending.add(form);
        if (!starts.has(content)) starts.set(content, [...content.childNodes]);
        const data = new FormData(form);
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20000);
        const button = event.submitter;
        if (button) button.disabled = true;
        const oldError = content.querySelector('[data-wallet-task-error]'); oldError?.remove();
        form.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(form.action, {method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:data,signal:controller.signal});
            let result;
            try { result = await response.json(); } catch { throw new Error('Could not confirm the response. Check your wallet history before retrying a transfer.'); }
            if (!response.ok || !result.success) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'This wallet action could not be completed.');
            if (typeof result.html !== 'string') throw new Error('The wallet response is incomplete. Check history before retrying.');
            // Only this same-origin, authorized Blade endpoint supplies the fragment.
            content.innerHTML = result.html;
            if (Number.isInteger(result.available_cents)) document.querySelectorAll('[data-wallet-available]').forEach(node => {node.textContent = new Intl.NumberFormat('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2}).format(result.available_cents / 100);});
            const heading = content.querySelector('h2');
            if (heading) {heading.tabIndex = -1; heading.focus();}
            notify(result.message || 'Recipient verified. Review the details before confirming.', result.message ? 'success' : 'info');
            const reset = document.createElement('button'); reset.type = 'button'; reset.className = 'dash-btn-sm dash-btn-outline'; reset.textContent = 'Start another wallet action';
            reset.addEventListener('click', () => {
                content.replaceChildren(...starts.get(content));
                content.querySelector('form')?.reset();
                const key = content.querySelector('[name="request_key"]');
                if (key && crypto.randomUUID) key.value = crypto.randomUUID();
                content.querySelector('input:not([type="hidden"])')?.focus();
            });
            content.append(reset);
        } catch (error) {
            const message = error.name === 'AbortError' ? 'The response timed out. The request may still complete; check wallet history before retrying.' : (error.message || 'Unable to confirm this wallet operation. Check your history before retrying.');
            const note = document.createElement('p'); note.className = 'wallet-task-error'; note.dataset.walletTaskError = '1'; note.setAttribute('role','alert'); note.textContent = message; content.prepend(note);
            notify(message, 'error');
        } finally {
            clearTimeout(timeout); pending.delete(form); form.removeAttribute('aria-busy'); if (button?.isConnected) button.disabled = false;
        }
    });
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-wallet-copy]');
        if (!button) return;
        const input = document.getElementById(button.dataset.walletCopy);
        try {await navigator.clipboard.writeText(input.value); notify(input.id === 'issued-demo-code' ? 'Funding code copied.' : 'Receive reference copied.', 'success');}
        catch {input.focus(); input.select(); notify('Copy the selected value manually.', 'warning');}
    });
})();
