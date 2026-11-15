(() => {
    const page = document.querySelector('[data-message-retractions]');
    if (!page) return;
    const messages = new Map([...page.querySelectorAll('[data-direct-message]')].map(node => [String(node.dataset.directMessage), node]));
    if (!messages.size) return;
    let busy = false;
    const notice = text => {
        const node = document.createElement('p');
        node.className = 'conversation-unsent';
        node.textContent = text;
        return node;
    };
    const refresh = async () => {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const url = new URL(page.dataset.messageRetractions, window.location.href);
            if (url.origin !== window.location.origin) return;
            [...messages.keys()].slice(0, 40).forEach(id => url.searchParams.append('message_ids[]', id));
            const response = await fetch(url.href, {credentials:'same-origin', headers:{Accept:'application/json'}, cache:'no-store'});
            if (!response.ok) return;
            const data = await response.json();
            if (!Array.isArray(data.messages)) return;
            data.messages.forEach(state => {
                const article = messages.get(String(state.id));
                if (!article) return;
                if (state.unsent === true) {
                    article.querySelector('[data-message-content]')?.replaceChildren(notice(article.dataset.ownMessage === '1' ? 'You unsent this message.' : 'This message was unsent.'));
                    return;
                }
                if (state.attachments_only_unsent === true) {
                    const body = article.querySelector('[data-message-body]');
                    if (body) body.textContent = 'Attachments unsent.';
                }
                if (!Array.isArray(state.attachment_ids)) return;
                article.querySelectorAll('[data-message-attachment]').forEach(attachment => {
                    if (state.attachment_ids.some(id => String(id) === attachment.dataset.messageAttachment)) {
                        attachment.replaceChildren(notice('Attachment unsent.'));
                    }
                });
            });
        } catch (_) { /* Keep the readable history on a temporary connection failure. */ }
        finally { busy = false; }
    };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('pageshow', refresh);
    const timer = setInterval(refresh, 15000);
    window.addEventListener('pagehide', () => clearInterval(timer), {once:true});
    refresh();
})();
