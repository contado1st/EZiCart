(() => {
    'use strict';

    const parameter = '__tab';
    const storageKey = 'ezi.tab-session.v1';
    const valid = (value) => typeof value === 'string' && /^[a-f0-9]{32}$/i.test(value);
    const createToken = () => {
        if (window.crypto?.getRandomValues) {
            const bytes = new Uint8Array(16);
            window.crypto.getRandomValues(bytes);
            return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
        }

        return `${Date.now().toString(16)}${Math.random().toString(16).slice(2)}`.padEnd(32, '0').slice(0, 32);
    };

    const readToken = () => {
        const url = new URL(window.location.href);
        const fromUrl = url.searchParams.get(parameter);
        if (valid(fromUrl)) {
            sessionStorage.setItem(storageKey, fromUrl.toLowerCase());
            url.searchParams.delete(parameter);
            history.replaceState(history.state, '', url.href);
            return fromUrl.toLowerCase();
        }

        let token = sessionStorage.getItem(storageKey);
        if (!valid(token)) {
            token = createToken();
            sessionStorage.setItem(storageKey, token);
        }

        url.searchParams.set(parameter, token.toLowerCase());
        window.location.replace(url.href);
        return null;
    };

    const token = readToken();
    if (!token) return;

    const withToken = (rawUrl, selectedToken = token) => {
        try {
            const url = new URL(rawUrl, window.location.href);
            if (url.origin !== window.location.origin || url.protocol !== 'http:' && url.protocol !== 'https:') return rawUrl;
            url.searchParams.set(parameter, selectedToken);
            return url.href;
        } catch {
            return rawUrl;
        }
    };

    const decorateLinks = (root = document) => {
        root.querySelectorAll?.('a[href]').forEach((link) => {
            if (link.hasAttribute('download') || link.dataset.tabSession === 'skip') return;
            if (link.target === '_blank') {
                if (link.dataset.tabSession === 'new-tab' && !link.dataset.tabSessionToken) {
                    link.dataset.tabSessionToken = createToken();
                }
                if (link.dataset.tabSession === 'new-tab') {
                    link.href = withToken(link.href, link.dataset.tabSessionToken);
                } else {
                    link.href = withToken(link.href);
                }
                return;
            }
            link.href = withToken(link.href);
        });
    };

    const decorateForms = (root = document) => {
        root.querySelectorAll?.('form').forEach((form) => {
            if (form.dataset.tabSession === 'skip') return;
            const action = form.getAttribute('action') || window.location.href;
            try {
                if (new URL(action, window.location.href).origin !== window.location.origin) return;
            } catch {
                return;
            }

            const tokenInput = form.querySelector('input[name="__tab"]');
            if (tokenInput) tokenInput.value = token;
            else {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = parameter;
                input.value = token;
                form.append(input);
            }

            form.setAttribute('action', withToken(action));
        });
    };

    const decorateSessionResources = (root = document) => {
        root.querySelectorAll?.('iframe[data-src], img[src]').forEach((resource) => {
            const attribute = resource.matches('iframe[data-src]') ? 'data-src' : 'src';
            const value = resource.getAttribute(attribute);
            if (value) resource.setAttribute(attribute, withToken(value));
        });
    };

    decorateLinks();
    decorateForms();
    decorateSessionResources();

    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('a[href]');
        if (!link || link.hasAttribute('download')) return;
        const selectedToken = link.target === '_blank' && link.dataset.tabSession === 'new-tab'
            ? (link.dataset.tabSessionToken || createToken())
            : token;
        if (link.target === '_blank' && link.dataset.tabSession === 'new-tab') {
            link.dataset.tabSessionToken = selectedToken;
        }
        link.href = withToken(link.href, selectedToken);
    }, true);

    document.addEventListener('submit', (event) => decorateForms(event.target.ownerDocument), true);

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) return;
                if (node.matches('a[href]')) node.href = withToken(node.href);
                if (node.matches('form')) decorateForms(node.parentElement || document);
                decorateLinks(node);
                decorateForms(node);
                decorateSessionResources(node);
            });
        }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });

    if (typeof window.fetch === 'function') {
        const originalFetch = window.fetch.bind(window);
        window.fetch = (resource, options = {}) => {
            const url = typeof resource === 'string' || resource instanceof URL ? resource : resource.url;
            let target;
            try { target = new URL(url, window.location.href); } catch { return originalFetch(resource, options); }
            if (target.origin !== window.location.origin) return originalFetch(resource, options);

            const headers = new Headers(options.headers || (resource instanceof Request ? resource.headers : undefined));
            headers.set('X-Ezi-Tab-Session', token);
            return originalFetch(resource, { ...options, headers });
        };
    }
})();
