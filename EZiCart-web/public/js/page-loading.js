(() => {
    const overlay = document.querySelector('[data-page-loading]');
    const content = document.querySelector('[data-page-content]');
    if (!overlay || !content) return;
    document.documentElement.classList.add('ezi-loading-enabled');
    document.querySelector('[data-skeleton-chart-loading]')?.removeAttribute('hidden');
    const stage = document.querySelector('[data-loading-stage]');
    const skeletonSelector = '.ezi-skeleton-bar, .ezi-skeleton-avatar, .ezi-skeleton-input, .ezi-skeleton-image, .ezi-skeleton-plot, .ezi-skeleton-block';
    const visibility = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
        entries.forEach((entry) => entry.target.classList.toggle('ezi-skeleton-visible', entry.isIntersecting));
    }) : null;
    const observe = (root) => root?.querySelectorAll(skeletonSelector).forEach((node) => {
        if (visibility) visibility.observe(node);
        else node.classList.add('ezi-skeleton-visible');
    });
    let routes = [];
    try {
        routes = JSON.parse(document.querySelector('[data-loading-routes]')?.textContent || '[]').map((route) => {
            const parts = route.path.split('/').filter(Boolean);
            const pattern = parts.map((part) => /^\{[^}]+\}$/.test(part)
                ? (part.endsWith('?}') ? '(?:/[^/]+)?' : '/[^/]+')
                : '/'+part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('');
            return { ...route, match: new RegExp('^'+(pattern || '/')+'/?$'), parameters: (route.path.match(/\{/g) || []).length };
        }).sort((a, b) => a.parameters - b.parameters || b.path.length - a.path.length);
    } catch { /* A route metadata issue must never prevent normal navigation. */ }
    const cachePrefix = 'ezi-layout-v2:'+(overlay.dataset.loadingScope || 'guest')+':';
    const cacheKey = (url) => cachePrefix+url.pathname+':'+Math.round(window.innerWidth / 8);
    const captureGeometry = () => {
        if (!stage || (window.scrollY || 0) > 80 || (content.scrollTop || 0) > 80) return;
        const bounds = content.getBoundingClientRect();
        const width = bounds.right - bounds.left;
        if (width < 100) return;
        const boxes = [];
        const seen = new Set();
        const selectors = 'h1, h2, h3, label, input:not([type="hidden"]), select, textarea, button, img, th, td, .dash-subtitle, .analytics-heading p, .product-gallery-box, .profile-cover, .profile-avatar, .analytics-line-chart, .analytics-status-layout svg, .analytics-bar-track';
        for (const node of content.querySelectorAll(selectors)) {
            if (boxes.length >= 100) break;
            if (node.closest('[hidden], [aria-hidden="true"]')) continue;
            const rect = node.getBoundingClientRect();
            if (rect.width < 3 || rect.height < 3 || rect.bottom <= bounds.top || rect.top >= window.innerHeight) continue;
            const tag = node.tagName;
            if (['TD', 'TH'].includes(tag) && node.querySelector('input, select, button, img')) continue;
            const block = ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'IMG', 'svg'].includes(tag) || ['product-gallery-box', 'profile-cover', 'profile-avatar', 'analytics-bar-track'].some(name => node.classList.contains(name));
            let left = rect.left; let top = rect.top; let boxWidth = rect.width;
            let height = block ? rect.height : Math.min(rect.height, tag === 'H1' ? 28 : 14);
            if (!block && document.createRange) {
                const range = document.createRange();
                range.selectNodeContents(node);
                const text = range.getBoundingClientRect();
                if (text.width > 0) { left = Math.max(rect.left, text.left); top = Math.max(rect.top, text.top); boxWidth = Math.min(rect.width, text.width); }
            }
            const x = Math.max(0, Math.round(left - bounds.left));
            const y = Math.max(0, Math.round(top - bounds.top));
            const w = Math.min(Math.round(boxWidth), Math.floor(width - x));
            height = Math.round(height);
            if (w < 3 || height < 3 || x >= width) continue;
            const box = [x, y, w, height, node.classList.contains('profile-avatar') ? Math.min(w, height) / 2 : (block ? 8 : 4)];
            const key = box.join(',');
            if (!seen.has(key)) { boxes.push(box); seen.add(key); }
        }
        if (boxes.length < 3) return;
        try {
            const storage = window.sessionStorage;
            storage.setItem(cacheKey(new URL(location.href)), JSON.stringify({ at: Date.now(), width, viewport: window.innerWidth, shape: overlay.dataset.loadingCurrent, boxes }));
            const keys = Array.from({length: storage.length}, (_, i) => storage.key(i)).filter((key) => key.startsWith(cachePrefix));
            while (keys.length > 20) storage.removeItem(keys.shift());
        } catch { /* Private browsing or storage limits fall back to the destination template. */ }
    };
    const clearStage = () => {
        if (!stage) return;
        stage.querySelectorAll(skeletonSelector).forEach((node) => visibility?.unobserve(node));
        stage.replaceChildren();
    };
    const renderSkeleton = (url) => {
        if (!stage) return;
        clearStage();
        overlay.dataset.loadingMatch = 'profile';
        try {
            const saved = JSON.parse(window.sessionStorage.getItem(cacheKey(url)) || 'null');
            const width = content.getBoundingClientRect().right - content.getBoundingClientRect().left;
            if (saved && saved.shape === overlay.dataset.loadingVariant && Date.now() - saved.at < 1800000
                && Math.abs(saved.width - width) <= 8 && Math.abs(saved.viewport - window.innerWidth) <= 8
                && Array.isArray(saved.boxes) && saved.boxes.length >= 3 && saved.boxes.length <= 100
                && saved.boxes.every((b) => Array.isArray(b) && b.length === 5 && b.every(Number.isFinite) && b[0] >= 0 && b[1] >= 0 && b[1] < 4000 && b[2] >= 3 && b[3] >= 3 && b[3] < 3000 && b[0] + b[2] <= width + 1 && b[4] >= 0 && b[4] <= 300)) {
                const shape = document.createElement('div');
                shape.className = 'ezi-skeleton';
                saved.boxes.forEach(([x, y, w, h, radius]) => {
                    const block = document.createElement('span'); block.className = 'ezi-skeleton-block';
                    Object.assign(block.style, {left: x+'px', top: y+'px', width: w+'px', height: h+'px', borderRadius: radius+'px'});
                    shape.append(block);
                });
                stage.append(shape);
                overlay.dataset.loadingMatch = 'geometry';
            }
        } catch { /* Invalid or unavailable cache is ignored. */ }
        if (overlay.dataset.loadingMatch !== 'geometry') {
            const template = document.querySelector(`[data-loading-template="${overlay.dataset.loadingVariant}"]`);
            if (template) stage.append(template.content.cloneNode(true));
        }
        observe(stage);
    };

    let delay;
    let recovery;
    let kind = null;
    let previousBusy = null;
    let busy = false;
    let destination = null;
    const restore = () => {
        clearTimeout(delay);
        clearTimeout(recovery);
        overlay.hidden = true;
        kind = null;
        if (busy) {
            if (previousBusy === null) content.removeAttribute('aria-busy');
            else content.setAttribute('aria-busy', previousBusy);
        }
        busy = false;
        destination = null;
        clearStage();
    };
    const position = () => {
        const bounds = content.getBoundingClientRect();
        const header = document.querySelector('.workspace-topbar, .navbar');
        const headerBottom = header ? Math.max(0, header.getBoundingClientRect().bottom) : 0;
        const left = Math.max(0, bounds.left);
        overlay.style.left = `${left}px`;
        overlay.style.top = `${Math.max(headerBottom, 0, Math.min(bounds.top, window.innerHeight - 80))}px`;
        overlay.style.width = `${Math.max(0, Math.min(bounds.right, window.innerWidth) - left)}px`;
        overlay.style.right = 'auto';
    };
    const variant = (url) => {
        const route = routes.find((route) => route.match.test(url.pathname));
        if (route) return route.shape;
        if (/^\/shops\/[^/]+\/?$/.test(url.pathname)) return 'shop';
        if (/^\/product\/[^/]+\/?$/.test(url.pathname)) return 'product';
        if (/^\/checkout\/?$/.test(url.pathname)) return 'checkout';
        if (/^\/cart\/?$/.test(url.pathname)) return 'cart';
        if (/^\/analytics\/?$/.test(url.pathname)) return 'analytics';
        if (/\/announcements\/?$/.test(url.pathname)) return 'announcements';
        if (/^\/notifications(\/|$)/.test(url.pathname)) return 'notifications';
        if (url.pathname === '/chat/contacts') return 'messages';
        if (/^\/chat\/(?:conversations\/)?[^/]+\/?$/.test(url.pathname)) return 'thread';
        if (/\/(chat|messages)(\/|$)/.test(url.pathname)) return 'messages';
        if (/^\/login\/?$/.test(url.pathname)) return 'login';
        if (/\/(register|forgot-password)(\/|$)/.test(url.pathname)) return 'auth';
        if (/\/(account)(\/|$)|\/(create|edit|dispute)$/.test(url.pathname)) return 'form';
        if (/\/orders\/[^/]+\/?$/.test(url.pathname)) return 'order';
        if (/^\/buyer\/dashboard\/?$/.test(url.pathname)) return 'purchases';
        if (/\/dashboard\/?$/.test(url.pathname)) return 'dashboard';
        if (url.pathname === '/') return 'storefront';
        if (/^\/search\/?$/.test(url.pathname)) return 'search';
        if (/^\/(admin|seller|courier|logistics|logistics-admin|finance|activity-logs|sales-reports|coverage)(\/|$)/.test(url.pathname)) return 'table';
        return 'content';
    };
    const start = (url, reason = 'navigation') => {
        captureGeometry();
        restore();
        kind = reason;
        destination = url;
        overlay.dataset.loadingVariant = reason === 'initial' ? (overlay.dataset.loadingCurrent || variant(url)) : variant(url);
        delay = setTimeout(() => {
            if (document.hidden) return restore();
            position();
            renderSkeleton(url);
            previousBusy = content.getAttribute('aria-busy');
            content.setAttribute('aria-busy', 'true');
            busy = true;
            overlay.hidden = false;
            // A browser may cancel navigation or download a response instead of replacing the document.
            recovery = setTimeout(restore, 12000);
        }, 140);
    };
    const eligible = (url, format = '') => {
        if (!navigator.onLine || url.origin !== window.location.origin || !/^https?:$/.test(url.protocol)) return false;
        if (/^(csv|xlsx|xls|pdf|zip|json)$/i.test(format || url.searchParams.get('format') || '')) return false;
        if (/\.(pdf|csv|xlsx?|zip|docx?|png|jpe?g|webp|svg|mp4)$/i.test(url.pathname)) return false;
        return !/\/(export|download|proof|evidence|documents|parcel-label|label|badge|qr)(\/|$)/i.test(url.pathname);
    };
    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        if (link.hasAttribute('download') || (link.target && link.target !== '_self') || link.closest('[data-no-loading]')) return;
        const url = new URL(link.href, window.location.href);
        if (!eligible(url) || (url.pathname === location.pathname && url.search === location.search)) return;
        queueMicrotask(() => { if (!event.defaultPrevented) start(url); });
    });
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.closest('[data-no-loading]')) return;
        const target = event.submitter?.formTarget || form.target;
        if (target && target !== '_self') return;
        if ((event.submitter?.formMethod || form.method).toLowerCase() === 'dialog') return;
        const url = new URL(event.submitter?.getAttribute('formaction') || form.action, location.href);
        const format = event.submitter?.name === 'format' ? event.submitter.value : form.elements.namedItem('format')?.value;
        if (!eligible(url, format)) return;
        // Confirmation dialogs and async forms retain control until they permit native submission.
        const destination = (event.submitter?.formMethod || form.method).toLowerCase() === 'get' ? url : new URL(location.href);
        queueMicrotask(() => { if (!event.defaultPrevented) start(destination); });
    });
    window.addEventListener('pageshow', () => { restore(); setTimeout(captureGeometry, 300); });
    window.addEventListener('pagehide', restore);
    window.addEventListener('focus', restore);
    window.addEventListener('offline', restore);
    window.addEventListener('resize', () => { if (!overlay.hidden) { position(); renderSkeleton(destination); } });
    window.addEventListener('load', () => { if (kind === 'initial') restore(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') restore(); });
    document.addEventListener('visibilitychange', () => { if (document.hidden) restore(); });

    const initiallyLoading = [];
    content.querySelectorAll('img').forEach((image) => {
        if (image.complete) return;
        image.classList.add('ezi-media-loading');
        const ready = new Promise((resolve) => {
            const clear = () => { image.classList.remove('ezi-media-loading'); resolve(); };
            image.addEventListener('load', clear, { once: true });
            image.addEventListener('error', clear, { once: true });
        });
        const rect = image.getBoundingClientRect();
        if (image.loading !== 'lazy' && rect.height > 0 && rect.top < innerHeight && rect.bottom > 0) initiallyLoading.push(ready);
    });
    if (document.readyState !== 'complete' && initiallyLoading.length) {
        start(new URL(location.href), 'initial');
        Promise.all(initiallyLoading).then(() => { if (kind === 'initial') restore(); });
    }
    observe(document);
    if (document.readyState === 'complete') setTimeout(captureGeometry, 300);
    document.fonts?.ready.then(() => setTimeout(captureGeometry, 0));
})();
