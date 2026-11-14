const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

function productSetup({fallback = 450, variation = false} = {}) {
    const base = Date.parse('2026-11-14T12:00:00+08:00');
    let clock = base;
    class Clock extends Date { static now() { return clock; } }
    const timers = [];
    const price = {parentElement: {querySelector: () => badge}}, badge = {}, stock = {}, image = {removeAttribute() {}}, breakdown = {};
    const handlers = {};
    const quantity = {value: '2', addEventListener: (name, fn) => { handlers[name] = fn; }};
    const select = variation ? {value: '1', selectedOptions: [{dataset: {stock: '7', adjustment: '0', fallbackAdjustment: '50'}, textContent: 'Blue'}], addEventListener() {}} : null;
    const form = {dataset: {stock: '20', price: '299', priceRange: 'PHP 299', fallbackPrice: String(fallback), fallbackRange: `PHP ${fallback}`, fallbackSale: fallback < 450 ? '1' : '0',
        shopOpen: '1', flashPrice: '299', flashRemaining: '1', flashStartsAt: new Date(base + 60000).toISOString(), flashEndsAt: new Date(base + 3600000).toISOString(), serverNow: new Date(base).toISOString()},
        querySelector: selector => ({'[name="variation_id"]': select, '[name="quantity"]': quantity, '[data-variation-image]': image, '[data-price-breakdown]': breakdown})[selector], querySelectorAll: () => [{}, {}]};
    vm.runInNewContext(fs.readFileSync('public/js/product-selection.js', 'utf8'), {document: {querySelector: () => form, getElementById: id => id === 'basePriceDisplay' ? price : stock}, Intl, Date: Clock, setInterval: fn => { timers.push(fn); }});
    return {price, badge, quantity, breakdown, tick(at) { clock = base + at; timers[0](); }, input(value) { quantity.value = String(value); handlers.input(); }};
}

test('flash price starts at its boundary, splits a quantity preview and expires without leaving a sale badge', () => {
    const page = productSetup();
    assert.match(page.price.textContent, /450\.00/);
    assert.equal(page.badge.hidden, true);
    page.tick(60000);
    assert.match(page.price.textContent, /299\.00/);
    assert.match(page.breakdown.textContent, /1 × .*299\.00 flash \+ 1 × .*450\.00 current price/);
    assert.equal(page.badge.hidden, false);
    page.input(1);
    assert.doesNotMatch(page.breakdown.textContent, /\+/);
    page.tick(3600000);
    assert.match(page.price.textContent, /450\.00/);
    assert.equal(page.badge.hidden, true);
    assert.equal(page.breakdown.textContent, '');
});

test('a selected variant uses its store-sale fallback after the flash slot ends', () => {
    const page = productSetup({fallback: 360, variation: true});
    page.tick(60000);
    assert.match(page.breakdown.textContent, /410\.00 current price/);
    page.tick(3600000);
    assert.match(page.price.textContent, /410\.00/);
    assert.equal(page.badge.hidden, false);
});

test('countdown uses the server clock, stops at zero and never displays a negative duration', () => {
    const base = Date.parse('2026-11-14T12:00:00+08:00');
    let clock = base + 7200000, update, stopped;
    class Clock extends Date { static now() { return clock; } }
    const node = {dataset: {endsAt: new Date(base + 62000).toISOString(), serverNow: new Date(base).toISOString()}};
    vm.runInNewContext(fs.readFileSync('public/js/flash-promotions.js', 'utf8'), {document: {querySelectorAll: () => [node]}, Date: Clock,
        setInterval(fn) { update = fn; return 7; }, clearInterval(id) { stopped = id; }});
    assert.equal(node.textContent, '0h 1m 2s');
    clock += 62000; update();
    assert.match(node.textContent, /Ended/);
    assert.equal(stopped, 7);
});

test('cart selection applies only the newest server quote after qualifying items are unchecked', async () => {
    const timers = new Map(), requests = [], storage = {};
    let next = 0;
    const output = Object.fromEntries(['[data-selected-count]', '[data-selected-button-count]', '[data-selected-total]', '[data-selection-hint]', '[form="cart-checkout-selection"][type="submit"]'].map(key => [key, {}]));
    const line = () => { const total = {}, labels = [{}, {}]; return {total, labels, querySelector: () => total, querySelectorAll: () => labels}; };
    const item = value => { const row = line(); return {value, checked: true, disabled: false, dataset: {shopId: '1', cents: '45000', quantity: '1'}, row,
        addEventListener(name, fn) { this.change = checked => { this.checked = checked; fn(); }; }, closest: () => row}; };
    const a = item('1'), b = item('2');
    const shopCheck = {addEventListener() {}}, shop = {querySelector: () => shopCheck, querySelectorAll: () => [a, b]};
    const page = {dataset: {buyer: 'buyer-1', quoteUrl: '/cart/selection-quote'}, querySelector: selector => output[selector],
        querySelectorAll: selector => selector === '[data-cart-item]' ? [a, b] : selector === '[data-shop]' ? [shop] : selector === '[data-select-shop]' ? [shopCheck] : []};
    vm.runInNewContext(fs.readFileSync('public/js/cart-selection.js', 'utf8'), {document: {querySelector: selector => selector === '[data-cart-selection]' ? page : {value: 'csrf'}}, Intl,
        sessionStorage: {getItem: () => null, setItem(key, value) { storage[key] = value; }},
        setTimeout(fn) { timers.set(++next, fn); return next; }, clearTimeout(id) { timers.delete(id); },
        fetch(url, options) { return new Promise(resolve => requests.push({resolve, options})); }});
    const oldRequest = timers.get(1)();
    b.change(false);
    const latestRequest = timers.get(2)();
    assert.deepEqual(JSON.parse(requests[1].options.body).selected_keys, ['1']);
    requests[1].resolve({ok: true, json: async () => ({subtotal_cents: 45000, message: 'Current offers', lines: {'1': {item_cents: 45000, segments: [{quantity: 1, unit_cents: 45000, label: 'Current price'}]}}})});
    await latestRequest;
    assert.match(output['[data-selected-total]'].textContent, /450\.00/);
    assert.equal(output['[form="cart-checkout-selection"][type="submit"]'].disabled, false);
    assert.match(a.row.labels[0].textContent, /450\.00/);
    requests[0].resolve({ok: true, json: async () => ({subtotal_cents: 10000, lines: {}})});
    await oldRequest;
    assert.match(output['[data-selected-total]'].textContent, /450\.00/);
});
