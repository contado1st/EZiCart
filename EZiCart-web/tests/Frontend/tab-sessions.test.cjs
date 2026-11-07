const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {webcrypto} = require('node:crypto');
const source = fs.readFileSync('public/js/tab-sessions.js', 'utf8');
const A = 'a'.repeat(32), B = 'b'.repeat(32);
function locks() {
    const held = new Set();
    return {request: async (name, options, callback) => {
        if (held.has(name)) return callback(null);
        held.add(name);
        try { await callback({name}); } finally { held.delete(name); }
    }};
}
async function setup({token = A, stored, authenticated = false, bootstrap = false, fresh = false, shared = locks(), parent, channel} = {}) {
    const entries = new Map(stored ? [['ezi.tab-session.v2', stored]] : []);
    const link = {href: '/chat/conversations/3?panel=1', target: '', dataset: {}, hasAttribute: () => false};
    const form = {action: '/chat/conversations/3/messages', dataset: {}, hiddenInput: null,
        getAttribute() {return this.action;}, setAttribute(name, value) {this.action = value;},
        querySelector() {return this.hiddenInput;}, append(input) {this.hiddenInput = input;}};
    const frame = {url: '/chat?panel=1', matches: () => true,
        getAttribute() {return this.url;}, setAttribute(name, value) {this.url = value;}};
    const calls = [], events = {}, lifecycle = {};
    const location = {href: `https://shop.test/chat?panel=1${token ? '&__tab='+token : ''}${fresh ? '&__new_account=1' : ''}`, origin: 'https://shop.test', replace(url) {this.replaced = url;}, reload() {this.reloaded = true;}};
    const window = {location, crypto: webcrypto, navigator: {locks: shared}, BroadcastChannel: channel,
        addEventListener: (name, handler) => lifecycle[name] = handler,
        history: {replaceState: (state, title, url) => location.href = url},
        fetch: async (resource, options) => {calls.push({resource, options}); return {};}};
    window.top = parent || window; window.parent = parent || window;
    const document = {currentScript: {dataset: {tabAuthenticated: authenticated ? '1' : '0', tabBootstrap: bootstrap ? '1' : '0', tabLoginUrl: '/login'}},
        documentElement: {}, createElement: () => ({}), addEventListener: (name, handler) => events[name] = handler,
        querySelector: () => ({content: 'this-tabs-csrf'}),
        querySelectorAll: selector => ({'a[href]': [link], form: [form], 'iframe[data-src], img[src]': [frame]})[selector] || []};
    vm.runInNewContext(source, {window, document, URL, Headers, Request, setTimeout,
        sessionStorage: {getItem: name => entries.get(name), setItem: (name, value) => entries.set(name, value)},
        MutationObserver: class {observe() {}}});
    await window.eziTabSessionReady;
    return {link, form, frame, calls, entries, location, window, events, lifecycle};
}
test('embedded navigation, form submission and lazy frame inherit parent account', async () => {
    const h = await setup({token: A, stored: B, parent: {location: {href: 'https://shop.test/?__tab='+A}}});
    for (const raw of [h.link.href, h.form.action, h.frame.url]) assert.equal(new URL(raw).searchParams.get('__tab'), A);
    assert.equal(h.form.hiddenInput.value, A);
    assert.equal(new URL(h.link.href).searchParams.get('panel'), '1');
});
test('same-origin fetch uses tab header and tab CSRF; external fetch is untouched', async () => {
    const h = await setup();
    await h.window.fetch('/chat', {method: 'POST'});
    assert.equal(h.calls[0].options.headers.get('X-Ezi-Tab-Session'), A);
    assert.equal(h.calls[0].options.headers.get('X-CSRF-TOKEN'), 'this-tabs-csrf');
    await h.window.fetch('https://external.test/chat', {});
    assert.equal(h.calls[1].options.headers, undefined);
});
test('bare navigation restores current tab account before rendering', async () => {
    const h = await setup({token: null, stored: A, bootstrap: true});
    assert.equal(new URL(h.location.replaced).searchParams.get('__tab'), A);
});
test('duplicated tabs with cloned storage fork to a new login without changing original', async () => {
    const shared = locks();
    const first = await setup({shared, stored: A});
    const second = await setup({shared, stored: A});
    const destination = new URL(second.location.replaced);
    assert.equal(destination.pathname, '/login');
    assert.notEqual(destination.searchParams.get('__tab'), A);
    assert.equal(first.location.replaced, undefined);
    assert.equal(first.entries.get('ezi.tab-session.v2'), A);
});
test('refresh releases then reclaims same account', async () => {
    const shared = locks();
    const first = await setup({shared, stored: A});
    first.lifecycle.pagehide();
    await new Promise(resolve => setImmediate(resolve));
    const reload = await setup({shared, stored: A});
    assert.equal(reload.location.replaced, undefined);
    assert.equal(reload.entries.get('ezi.tab-session.v2'), A);
});
test('pasting another account URL does not replace this tabs account', async () => {
    const h = await setup({stored: B, token: A});
    assert.equal(new URL(h.location.replaced).searchParams.get('__tab'), B);
});
test('explicit new-account link overrides cloned session storage', async () => {
    const h = await setup({stored: A, token: B, fresh: true});
    assert.equal(h.entries.get('ezi.tab-session.v2'), B);
    assert.equal(h.location.replaced, undefined);
    assert.equal(new URL(h.location.href).searchParams.has('__new_account'), false);
});
test('fresh tab cannot inherit an authenticated URL without its own storage', async () => {
    const h = await setup({authenticated: true});
    assert.notEqual(new URL(h.location.replaced).searchParams.get('__tab'), A);
});
test('ctrl and middle clicks create fresh selectors; normal click keeps current account', async () => {
    const h = await setup();
    const event = {target: {closest: () => h.link}, ctrlKey: true};
    h.events.click(event);
    assert.notEqual(new URL(h.link.href).searchParams.get('__tab'), A);
    assert.equal(new URL(h.link.href).searchParams.get('__new_account'), '1');
    h.events.auxclick({...event, ctrlKey: false, button: 1});
    assert.notEqual(new URL(h.link.href).searchParams.get('__tab'), A);
    h.events.click({...event, ctrlKey: false});
    assert.equal(new URL(h.link.href).searchParams.get('__tab'), A);
});

test('BroadcastChannel fallback separates duplicated tabs when Web Locks is unavailable', async () => {
    const channels = new Set();
    class Channel {
        constructor(name) {this.name = name; channels.add(this);}
        postMessage(data) {
            for (const peer of channels) if (peer !== this && peer.name === this.name) queueMicrotask(() => peer.onmessage?.({data}));
        }
        close() {channels.delete(this);}
    }
    const first = await setup({stored: A, shared: null, channel: Channel});
    const second = await setup({stored: A, shared: null, channel: Channel});
    assert.equal(first.location.replaced, undefined);
    assert.equal(new URL(second.location.replaced).pathname, '/login');
    assert.notEqual(second.entries.get('ezi.tab-session.v2'), A);
    first.lifecycle.pagehide(); second.lifecycle.pagehide();
});

test('explicit new account sidebar action creates an independent login URL', async () => {
    const h = await setup();
    h.link.href = '/login'; h.link.target = '_blank'; h.link.dataset.tabSession = 'new-tab';
    h.events.click({target: {closest: () => h.link}});
    assert.equal(new URL(h.link.href).pathname, '/login');
    assert.notEqual(new URL(h.link.href).searchParams.get('__tab'), A);
    assert.equal(new URL(h.link.href).searchParams.get('__new_account'), '1');
});
