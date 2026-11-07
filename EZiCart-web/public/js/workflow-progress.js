(() => {
    const script = document.querySelector('script[data-workflow-url]');
    const root = document.querySelector('[data-workflow-attention]');
    const panels = [...document.querySelectorAll('[data-order-progress-id]')];
    if (!script || (!root && !panels.length)) return;
    const path = raw => { try { const url = new URL(raw, location.href); return url.origin === location.origin ? url.pathname : null; } catch { return null; } };
    const ids = new Set(panels.map(panel => panel.dataset.orderProgressId));
    document.querySelectorAll('form[action]').forEach(form => { const match = path(form.action)?.match(/\/orders\/(\d+)(?:\/|$)/); if (match) ids.add(match[1]); });
    for (const panel of panels) {
        panel.dataset.processSignature = JSON.stringify([panel.dataset.processStatus, panel.dataset.processOwner || null, panel.querySelector('[data-process-message]').textContent.trim()]);
        panel.querySelector('[data-process-reload]')?.addEventListener('click', () => location.reload());
    }
    let pending = false;
    let previousCount = null;
    let previousItems = '';
    const badge = (element, count) => {
        let mark = element.querySelector('[data-workflow-generated-badge]');
        if (!count) { mark?.remove(); return; }
        if (!mark) { mark = document.createElement('span'); mark.className = 'workflow-action-badge'; mark.dataset.workflowGeneratedBadge = '1'; element.append(mark); }
        mark.textContent = count > 99 ? '99+' : String(count);
        mark.setAttribute('aria-label', `${count} actions require attention`);
    };
    const update = async () => {
        if (pending || document.hidden) return;
        pending = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 12000);
        try {
            const url = new URL(script.dataset.workflowUrl, location.href);
            [...ids].slice(0, 100).forEach(id => url.searchParams.append('order_ids[]', id));
            const response = await fetch(url, {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
            if (!response.ok) throw new Error('Unable to refresh');
            const data = await response.json();
            if (!Number.isInteger(data.count) || !Array.isArray(data.items)) throw new Error('Invalid workflow response');
            if (root) {
                root.querySelector('[data-workflow-total]').textContent = data.count > 99 ? '99+' : String(data.count);
                root.querySelector('[data-workflow-total]').hidden = data.count === 0;
                root.querySelector('[data-workflow-summary]').textContent = data.count === 1 ? '1 action requires attention' : data.count ? `${data.count} actions require attention` : 'You are up to date';
                root.querySelector('[data-workflow-empty]').hidden = data.count > 0;
                const signature = JSON.stringify(data.items);
                if (signature !== previousItems) {
                    const list = root.querySelector('[data-workflow-items]'); list.replaceChildren();
                    for (const item of data.items) {
                        if (!path(item.url)) continue;
                        const row = document.createElement('li'); const link = document.createElement('a');
                        link.href = item.url; link.textContent = item.label; row.append(link);
                        if (item.count > 1) badge(link, item.count);
                        list.append(row);
                    }
                    previousItems = signature;
                }
                root.querySelector('[data-workflow-updated]').textContent = previousCount !== null && previousCount !== data.count ? 'Your task list has changed. Open the next step to see its current controls.' : '';
            }
            const targets = new Map(); const links = new Map();
            const add = (raw, key) => { const name = path(raw); if (!name) return; if (!targets.has(name)) targets.set(name, new Set()); targets.get(name).add(String(key)); };
            for (const item of data.items) {
                if (item.target) add(item.target, item.order_id);
                const name = path(item.url); if (name) links.set(name, (links.get(name) || 0) + item.count);
            }
            for (const [id, state] of Object.entries(data.progress || {})) if (state.requires_action && state.action_url) add(state.action_url, id);
            document.querySelectorAll('form[action]').forEach(form => {
                const count = targets.get(path(form.action))?.size || 0;
                form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach(button => {
                    if (button.tagName === 'BUTTON') badge(button, count);
                });
            });
            document.querySelectorAll('.workspace-nav-link[href]').forEach(link => badge(link, links.get(path(link.href)) || 0));
            for (const panel of panels) {
                const state = data.progress?.[panel.dataset.orderProgressId]; if (!state) continue;
                const signature = JSON.stringify([state.status, state.owner, state.message]);
                if (signature !== panel.dataset.processSignature) panel.querySelector('[data-process-reload]').hidden = false;
                const message = panel.querySelector('[data-process-message]');
                if (message.textContent !== state.message) message.textContent = state.message;
                panel.querySelector('[data-process-owner]').textContent = state.requires_action ? 'Your action is required' : state.owner ? `Waiting for ${state.owner_label}` : 'No further action required';
                panel.querySelectorAll('[data-process-step]').forEach(step => {
                    const index = Number(step.dataset.processStep);
                    step.classList.toggle('is-current', state.stage === index);
                    step.classList.toggle('is-complete', state.stage !== null && state.stage > index);
                    if (state.stage === index) step.setAttribute('aria-current', 'step'); else step.removeAttribute('aria-current');
                });
            }
            previousCount = data.count;
        } catch {
            if (root) root.querySelector('[data-workflow-updated]').textContent = 'Could not refresh. Any displayed counts are from the last successful check. Try Refresh next steps.';
        } finally { clearTimeout(timeout); pending = false; }
    };
    root?.querySelector('[data-workflow-refresh]').addEventListener('click', update);
    window.addEventListener('focus', update);
    document.addEventListener('visibilitychange', update);
    window.setInterval(update, 15000);
    update();
})();
