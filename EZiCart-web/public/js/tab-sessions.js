(() => {
    'use strict';
    const script = document.currentScript;
    const key = 'ezi.tab-session.v2';
    const valid = value => typeof value === 'string' && /^[a-f0-9]{32}$/i.test(value);
    const create = () => Array.from(window.crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
    const read = name => { try { return sessionStorage.getItem(name); } catch { return null; } };
    const save = token => { try { sessionStorage.setItem(key, token); } catch { /* URL still preserves refresh. */ } };
    const current = new URL(window.location.href);
    const urlToken = current.searchParams.get('__tab');
    const stored = read(key);
    const embedded = window.top !== window;
    let token;
    if (embedded) {
        try { token = new URL(window.parent.location.href).searchParams.get('__tab'); } catch { /* Same-origin frames normally share the parent. */ }
        token = valid(token) ? token : valid(stored) ? stored : urlToken;
    } else if (current.searchParams.get('__new_account') === '1' && valid(urlToken)) {
        token = urlToken;
    } else {
        token = valid(stored) ? stored : valid(urlToken) && (script?.dataset.tabAuthenticated !== '1' || read('ezi.tab-session.v1') === urlToken) ? urlToken : create();
    }
    token = valid(token) ? token.toLowerCase() : create();
    const withToken = (raw, selected = token, fresh = false) => {
        const url = new URL(raw, current);
        if (url.origin !== current.origin || !/^https?:$/.test(url.protocol)) return raw;
        url.searchParams.set('__tab', selected);
        if (fresh) url.searchParams.set('__new_account', '1');
        else url.searchParams.delete('__new_account');
        return url.href;
    };
    let ready = false;
    let release = () => {};
    const login = () => {
        token = create();
        save(token);
        window.location.replace(withToken(script?.dataset.tabLoginUrl || '/login', token, true));
        return null;
    };
    const claim = async () => {
        if (embedded) return true;
        if (window.navigator.locks) {
            return new Promise((resolve, reject) => {
                window.navigator.locks.request(`ezi-tab:${token}`, {ifAvailable: true}, lock => {
                    if (!lock) { resolve(false); return; }
                    return new Promise(done => { release = done; resolve(true); });
                }).catch(reject);
            });
        }
        // HTTP development hosts without Web Locks still detect copied/duplicated tabs.
        if (window.BroadcastChannel) {
            const channel = new window.BroadcastChannel(`ezi-tab:${token}`);
            const identity = create();
            let occupied = false;
            let owner = false;
            channel.onmessage = ({data}) => {
                if (data.type === 'probe') {
                    if (owner) channel.postMessage({type: 'owned'});
                    else {
                        if (data.identity < identity) occupied = true;
                        channel.postMessage({type: 'contender', identity});
                    }
                } else if (data.type === 'contender' && data.identity < identity) {
                    occupied = true;
                } else if (data.type === 'owned') occupied = true;
            };
            release = () => channel.close();
            channel.postMessage({type: 'probe', identity});
            await new Promise(resolve => setTimeout(resolve, 180));
            owner = !occupied;
            return owner;
        }
        // Fail closed rather than silently share authentication on unsupported browsers.
        return null;
    };
    const decorateForm = form => {
        if (form.dataset.tabSession === 'skip') return;
        const action = form.getAttribute('action') || current.href;
        if (new URL(action, current).origin !== current.origin) return;
        form.setAttribute('action', withToken(action));
        let input = form.querySelector('input[name="__tab"]');
        if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = '__tab'; form.append(input); }
        input.value = token;
    };
    const decorateLink = (link, fresh = false) => {
        if (link.dataset.tabSession === 'skip') return;
        link.href = withToken(link.href, fresh ? create() : token, fresh);
    };
    const decorate = (root = document) => {
        root.querySelectorAll?.('a[href]').forEach(link => decorateLink(link, link.dataset.tabSession === 'new-tab'));
        root.querySelectorAll?.('form').forEach(decorateForm);
        root.querySelectorAll?.('iframe[data-src], img[src]').forEach(resource => {
            const attribute = resource.matches('iframe[data-src]') ? 'data-src' : 'src';
            const value = resource.getAttribute(attribute);
            if (value) resource.setAttribute(attribute, withToken(value));
        });
    };
    for (const name of ['click', 'auxclick', 'contextmenu', 'submit']) {
        document.addEventListener(name, event => {
            if (!ready) { event.preventDefault(); event.stopImmediatePropagation(); return; }
            if (name === 'submit') { decorateForm(event.target); return; }
            const link = event.target.closest?.('a[href]');
            if (!link) return;
            // Downloads use this account. Other tabs acquire their own ownership.
            const fresh = link.dataset.tabSession === 'new-tab' || !link.hasAttribute('download') && link.target !== '_blank' && (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1 || name === 'contextmenu');
            decorateLink(link, fresh);
        }, true);
    }
    window.addEventListener('pagehide', () => { ready = false; release(); });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
    window.eziTabSessionReady = (async () => {
        const owned = await claim();
        if (owned === null) {
            document.body.textContent = 'This browser cannot isolate account tabs. Open EZiCart in a current browser with tab storage and Web Locks or BroadcastChannel enabled.';
            return null;
        }
        if (!owned) return login();
        save(token);
        if (script?.dataset.tabBootstrap === '1' || urlToken !== token) {
            window.location.replace(withToken(script?.dataset.tabTarget || current.href));
            return null;
        }
        if (current.searchParams.has('__new_account')) window.history.replaceState(null, '', withToken(current.href));
        decorate();
        new MutationObserver(mutations => {
            for (const mutation of mutations) for (const node of mutation.addedNodes) {
                if (node.nodeType !== 1) continue;
                if (node.matches('a[href]')) decorateLink(node, node.dataset.tabSession === 'new-tab');
                if (node.matches('form')) decorateForm(node);
                decorate(node);
            }
        }).observe(document.documentElement, {childList: true, subtree: true});
        ready = true;
        return token;
    })();
    if (typeof window.fetch === 'function') {
        const original = window.fetch.bind(window);
        window.fetch = async (resource, options = {}) => {
            const raw = typeof resource === 'string' || resource instanceof URL ? resource : resource.url;
            if (new URL(raw, current).origin !== current.origin) return original(resource, options);
            const selected = await window.eziTabSessionReady;
            if (!selected) throw new Error('Tab session is opening. Please retry after navigation.');
            const headers = new Headers(options.headers || (resource instanceof Request ? resource.headers : undefined));
            headers.set('X-Ezi-Tab-Session', selected);
            const method = (options.method || (resource instanceof Request ? resource.method : 'GET')).toUpperCase();
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrf && !['GET', 'HEAD', 'OPTIONS'].includes(method) && !headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', csrf);
            return original(resource, {...options, headers});
        };
    }
})();
