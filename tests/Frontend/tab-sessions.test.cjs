const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/tab-sessions.js', 'utf8');

function setup(scoped = true) {
    const token = 'a'.repeat(32);
    const entries = new Map([['ezi.tab-session.v1', scoped ? 'b'.repeat(32) : token]]);
    const link = {href: '/chat/conversations/3?panel=1', target: '', dataset: {}, hasAttribute: () => false};
    const form = {
        action: '/chat/conversations/3/messages', dataset: {}, hiddenInput: null,
        getAttribute() { return this.action; },
        setAttribute(name, value) { this.action = value; },
        querySelector() { return this.hiddenInput; },
        append(input) { this.hiddenInput = input; },
    };
    const frame = {
        url: '/chat?panel=1', matches: () => true,
        getAttribute() { return this.url; }, setAttribute(name, value) { this.url = value; },
    };
    const calls = []; const events = {}; let replaced = null;
    const location = {href: `http://localhost:8000/chat?panel=1${scoped ? '&__tab='+token : ''}`, origin: 'http://localhost:8000', replace: (url) => replaced = url};
    const window = {location, fetch: async (resource, options) => { calls.push({resource, options}); return {}; }};
    const document = {
        documentElement: {}, createElement: () => ({}), addEventListener: (name, handler) => events[name] = handler,
        querySelectorAll: (selector) => ({'a[href]': [link], form: [form], 'iframe[data-src], img[src]': [frame]})[selector] || [],
    };
    vm.runInNewContext(source, {
        window, document, URL, Headers, Request,
        sessionStorage: {getItem: (name) => entries.get(name), setItem: (name, value) => entries.set(name, value)},
        MutationObserver: class {observe() {}},
    });
    return {token, link, form, frame, calls, entries, location, window, replaced, events};
}

test('embedded navigation, message submission and lazy frame keep the active tab selector', () => {
    const h = setup();
    for (const raw of [h.link.href, h.form.action, h.frame.url]) {
        const url = new URL(raw);
        assert.equal(url.searchParams.get('__tab'), h.token);
    }
    assert.equal(new URL(h.link.href).searchParams.get('panel'), '1');
    assert.equal(h.form.hiddenInput.name, '__tab');
    assert.equal(h.form.hiddenInput.value, h.token);
    assert.equal(h.entries.get('ezi.tab-session.v1'), h.token);
    assert.equal(new URL(h.location.href).searchParams.get('__tab'), h.token);
});

test('same-site requests carry the active tab header and external requests do not receive it', async () => {
    const h = setup();
    await h.window.fetch('/communication-counts', {headers: {Accept: 'application/json'}});
    assert.equal(h.calls[0].options.headers.get('X-Ezi-Tab-Session'), h.token);
    assert.equal(h.calls[0].options.headers.get('Accept'), 'application/json');
    await h.window.fetch('https://example.test/external', {headers: {Accept: 'application/json'}});
    assert.equal(h.calls[1].options.headers['X-Ezi-Tab-Session'], undefined);
});

test('an unscoped frame restores the parent tab selector before its next navigation', () => {
    const h = setup(false);
    assert.equal(new URL(h.replaced).searchParams.get('__tab'), h.token);
    assert.equal(new URL(h.replaced).searchParams.get('panel'), '1');
});
