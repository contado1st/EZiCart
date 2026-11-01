const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/page-loading.js', 'utf8');

function harness(options = {}) {
    class Element {
        constructor() {
            this.attributes = new Map(); this.style = {}; this.dataset = {}; this.hidden = false;
            this.children = []; this.className = ''; this.tagName = 'SPAN';
            this.classes = new Set(); this.classList = { add: name => this.classes.add(name), remove: name => this.classes.delete(name), toggle: (name, on) => on ? this.classes.add(name) : this.classes.delete(name), contains: name => this.classes.has(name) || this.className.split(' ').includes(name) };
        }
        getAttribute(name) { return this.attributes.get(name) ?? null; }
        setAttribute(name, value) { this.attributes.set(name, value); }
        removeAttribute(name) { this.attributes.delete(name); }
        hasAttribute(name) { return this.attributes.has(name); }
        closest(selector) { return selector === 'a[href]' && this.href ? this : null; }
        querySelector() { return null; }
        querySelectorAll() { return this.children.flatMap(child => [child, ...child.querySelectorAll()]); }
        append(child) { this.children.push(child); }
        replaceChildren(...children) { this.children = children; }
        getBoundingClientRect() { return { left: 220, right: 1200, top: 64, bottom: 800, width: 980, height: 736 }; }
    }
    class Form extends Element {
        constructor() { super(); this.action = 'https://demo.test/orders'; this.target = ''; this.method = 'post'; this.elements = { namedItem: () => null }; }
    }
    const overlay = new Element(); overlay.hidden = true; overlay.dataset.loadingScope = 'test-scope'; overlay.dataset.loadingCurrent = options.current || 'dashboard';
    const content = new Element();
    content.querySelectorAll = () => options.nodes || [];
    const stage = new Element();
    const stored = options.stored || new Map();
    const storage = { get length() { return stored.size; }, key: index => [...stored.keys()][index], getItem: key => stored.get(key) ?? null, setItem: (key, value) => { if (options.storageBlocked) throw Error('Storage blocked'); stored.set(key, value); }, removeItem: key => stored.delete(key) };
    const docEvents = new Map(); const winEvents = new Map(); const timers = new Map(); const microtasks = [];
    let next = 0;
    const document = {
        hidden: false, readyState: 'complete', documentElement: new Element(),
        querySelector: selector => {
            if (selector === '[data-page-loading]') return overlay;
            if (selector === '[data-page-content]') return content;
            if (selector === '[data-loading-stage]') return stage;
            if (selector === '[data-loading-routes]') return {textContent: JSON.stringify(options.routes || [])};
            if (selector.startsWith('[data-loading-template=')) return {content: {cloneNode: () => { const node = new Element(); node.className = 'template-'+overlay.dataset.loadingVariant; return node; }}};
            return null;
        },
        createElement: tag => { const node = new Element(); node.tagName = tag.toUpperCase(); return node; },
        querySelectorAll: () => [], addEventListener: (name, fn) => docEvents.set(name, fn),
    };
    const window = { location: new URL('https://demo.test'+(options.path || '/dashboard')), sessionStorage: storage, innerHeight: 800, innerWidth: 1200, addEventListener: (name, fn) => winEvents.set(name, fn) };
    const context = { document, window, location: window.location, navigator: { onLine: true }, URL, Element, HTMLFormElement: Form, innerHeight: 800,
        setTimeout: (fn, duration) => { timers.set(++next, { fn, duration }); return next; }, clearTimeout: id => timers.delete(id), queueMicrotask: fn => microtasks.push(fn) };
    vm.runInNewContext(source, context);
    const flush = () => { while (microtasks.length) microtasks.shift()(); };
    const run = duration => { for (const [id, timer] of [...timers]) if (timer.duration === duration) { timers.delete(id); timer.fn(); } };
    const click = (href, options = {}) => {
        const link = new Element(); link.href = href;
        Object.assign(link, options.link || {});
        if (options.download) link.setAttribute('download', '');
        const event = { target: link, button: 0, defaultPrevented: false, ...options };
        docEvents.get('click')(event); return event;
    };
    return { overlay, content, stage, stored, document, window, context, Form, Element, docEvents, winEvents, timers, flush, run, click };
}

test('native navigation shows a delayed content skeleton, keeps navigation geometry and restores on pageshow', () => {
    const h = harness(); h.click('https://demo.test/seller/orders'); h.flush();
    assert.equal(h.overlay.hidden, true);
    h.run(140);
    assert.equal(h.overlay.hidden, false);
    assert.equal(h.overlay.style.left, '220px'); assert.equal(h.overlay.style.top, '64px');
    assert.equal(h.overlay.dataset.loadingVariant, 'table');
    assert.equal(h.content.getAttribute('aria-busy'), 'true');
    h.winEvents.get('pageshow')();
    assert.equal(h.overlay.hidden, true); assert.equal(h.content.getAttribute('aria-busy'), null);
});

test('download, export, external, fragment, modified and new-tab links do not start a skeleton', () => {
    for (const [href, options] of [
        ['https://demo.test/analytics/export?format=pdf', {}], ['https://demo.test/report?format=csv', {}],
        ['https://demo.test/order/1/proof', {}], ['https://demo.test/file.xlsx', {}],
        ['https://demo.test/finance/evidence/refund/1', {}], ['https://demo.test/seller/orders/1/parcel-label', {}],
        ['https://external.test/page', {}], ['https://demo.test/dashboard#section', {}],
        ['https://demo.test/orders', {ctrlKey: true}], ['https://demo.test/orders', {link: {target: '_blank'}}],
        ['https://demo.test/orders', {download: true}],
    ]) {
        const h = harness(); h.click(href, options); h.flush(); h.run(140);
        assert.equal(h.overlay.hidden, true, href);
    }
});

test('cancelled click handlers retain control after the event finishes bubbling', () => {
    const h = harness(); const event = h.click('https://demo.test/search'); event.defaultPrevented = true;
    h.flush(); h.run(140); assert.equal(h.overlay.hidden, true);
});

test('cancelled form confirmation does not show loading; an accepted native submit does', () => {
    const h = harness(); const form = new h.Form();
    const event = {target: form, defaultPrevented: true};
    h.docEvents.get('submit')(event); h.flush(); h.run(140); assert.equal(h.overlay.hidden, true);
    event.defaultPrevented = false; h.docEvents.get('submit')(event); h.flush(); h.run(140);
    assert.equal(h.overlay.hidden, false);
});

test('export forms and alternate submit targets are excluded', () => {
    const h = harness(); const form = new h.Form();
    h.docEvents.get('submit')({target: form, submitter: {name: 'format', value: 'pdf', getAttribute: () => null}});
    h.flush(); h.run(140); assert.equal(h.overlay.hidden, true);
    form.target = '_blank'; h.docEvents.get('submit')({target: form});
    h.flush(); h.run(140); assert.equal(h.overlay.hidden, true);
});

test('Escape, offline and recovery timeout clear loading without overwriting prior busy state', () => {
    for (const reset of [h => h.docEvents.get('keydown')({key: 'Escape'}), h => h.winEvents.get('offline')(), h => h.run(12000)]) {
        const h = harness(); h.content.setAttribute('aria-busy', 'false');
        h.click('https://demo.test/search'); h.flush(); h.run(140); reset(h);
        assert.equal(h.overlay.hidden, true); assert.equal(h.content.getAttribute('aria-busy'), 'false');
    }
});

test('destinations choose tailored product, checkout, analytics, list and message layouts', () => {
    for (const [path, expected] of [['/login', 'login'], ['/seller/products/create', 'form'], ['/search?q=bag', 'search'], ['/product/12', 'product'], ['/chat/3', 'thread'], ['/checkout', 'checkout'], ['/cart', 'cart'], ['/analytics', 'analytics'], ['/admin/announcements', 'announcements'], ['/notifications', 'notifications'], ['/seller/orders/7', 'order'], ['/buyer/dashboard', 'purchases']]) {
        const h = harness(); h.click('https://demo.test' + path); h.flush(); h.run(140);
        assert.equal(h.overlay.dataset.loadingVariant, expected);
    }
});

test('Laravel destination metadata supports base paths, optional parameters and static-route precedence', () => {
    const routes = [{path: '/shop/products/{sku}', shape: 'product'}, {path: '/shop/products/new', shape: 'form'}, {path: '/shop/inbox/{thread?}', shape: 'messages'}];
    for (const [path, shape] of [['/shop/products/15', 'product'], ['/shop/products/new', 'form'], ['/shop/inbox', 'messages']]) {
        const h = harness({routes}); h.click('https://demo.test'+path); h.flush(); h.run(140);
        assert.equal(h.overlay.dataset.loadingVariant, shape);
    }
});

const savedLayout = (changes = {}) => ({at: Date.now(), width: 980, viewport: 1200, shape: 'table', boxes: [[24,24,300,28,4],[24,80,250,42,8],[24,160,500,14,4]], ...changes});
test('visited destination reuses measured rectangles and discards them on resize', () => {
    const stored = new Map([['ezi-layout-v2:test-scope:/seller/orders:150', JSON.stringify(savedLayout())]]);
    const h = harness({stored}); h.click('https://demo.test/seller/orders'); h.flush(); h.run(140);
    assert.equal(h.overlay.dataset.loadingMatch, 'geometry');
    assert.equal(h.stage.children[0].children.length, 3);
    assert.equal(h.stage.children[0].children[0].style.left, '24px');
    h.window.innerWidth = 980; h.winEvents.get('resize')();
    assert.equal(h.overlay.dataset.loadingMatch, 'profile');
    assert.equal(h.stage.children[0].className, 'template-table');
});

test('expired, mismatched and invalid cached measurements safely use a template', () => {
    for (const changes of [{at: 1}, {shape: 'form'}, {width: 700}, {boxes: [[-1,0,300,28,4]]}]) {
        const stored = new Map([['ezi-layout-v2:test-scope:/seller/orders:150', JSON.stringify(savedLayout(changes))]]);
        const h = harness({stored}); h.click('https://demo.test/seller/orders'); h.flush(); h.run(140);
        assert.equal(h.overlay.dataset.loadingMatch, 'profile');
    }
});

test('measurement stores only geometry, not page text, field values or query strings', () => {
    const h = harness({path: '/seller/products?private-search=secret', current: 'table'});
    const nodes = [0,1,2].map(i => { const node = new h.Element(); node.tagName = 'INPUT'; node.value = 'PRIVATE FIELD'; node.textContent = 'PRIVATE NAME'; node.getBoundingClientRect = () => ({left:244, top:100+i*60, right:544, bottom:142+i*60, width:300, height:42}); return node; });
    h.content.querySelectorAll = () => nodes;
    h.run(300);
    assert.equal(h.stored.size, 1);
    const [key, json] = [...h.stored][0];
    assert.ok(!key.includes('private-search'));
    assert.ok(!json.includes('PRIVATE'));
    assert.equal(JSON.parse(json).boxes.length, 3);
});

test('native post submissions retain the current page shape rather than guessing the redirect', () => {
    const h = harness({path: '/admin/announcements'}); const form = new h.Form();
    form.action = 'https://demo.test/admin/announcements/1/toggle';
    h.docEvents.get('submit')({target: form, defaultPrevented: false}); h.flush(); h.run(140);
    assert.equal(h.overlay.dataset.loadingVariant, 'announcements');
});

test('offline or background navigation does not cover the usable page', () => {
    const h = harness(); h.context.navigator.onLine = false;
    h.click('https://demo.test/orders'); h.flush(); h.run(140); assert.equal(h.overlay.hidden, true);
    h.context.navigator.onLine = true; h.document.hidden = true;
    h.click('https://demo.test/orders'); h.flush(); h.run(140); assert.equal(h.overlay.hidden, true);
});
