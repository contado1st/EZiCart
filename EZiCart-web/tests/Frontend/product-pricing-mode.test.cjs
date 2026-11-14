const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function setup(enabled, prices = [], quantities = []) {
    const listeners = {};
    const toggle = { checked: enabled, addEventListener: (name, callback) => { listeners.change = callback; }, setAttribute: () => {} };
    const base = {}, panel = {}, summary = {}, stock = { value: '55' };
    const rows = { children: [], addEventListener: (name, callback) => { listeners.input = callback; }, querySelectorAll: selector => selector === '[data-variation-stock]' ? quantities.map(value => ({value})) : selector === '[data-variation-price]' ? prices.map(value => ({value})) : [] };
    const form = { querySelector: selector => ({ '[name="stock"]': stock, '[data-variations-toggle]': toggle, '[data-base-pricing]': base })[selector] };
    const button = { addEventListener: () => {} };
    const editor = { closest: () => form, addEventListener: () => {}, querySelector: selector => ({ '[data-variation-rows]': rows, '[data-variation-template]': {}, '[data-variation-feedback]': {}, '[data-variation-options]': panel, '[data-variation-summary]': summary, '[data-add-variation]': button, '[data-generate-variations]': button })[selector] };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/product-variation-editor.js'), 'utf8'), { document: { querySelector: () => editor }, Intl });
    return {toggle, base, panel, summary, stock, listeners};
}

test('base mode displays editable base fields and disables variation submission', () => {
    const state = setup(false);
    assert.equal(state.base.hidden, false);
    assert.equal(state.base.disabled, false);
    assert.equal(state.panel.hidden, true);
    assert.equal(state.panel.disabled, true);
});

test('toggle restores base values while preserving entered variation rows for switching back', () => {
    const state = setup(true, ['100', '250'], ['2', '3']);
    assert.equal(state.base.hidden, true);
    assert.equal(state.base.disabled, true);
    assert.equal(state.panel.disabled, false);
    assert.match(state.summary.textContent, /100\.00.*250\.00.*Combined stock: 5/);
    state.toggle.checked = false;
    state.listeners.change();
    assert.equal(state.base.hidden, false);
    assert.equal(state.stock.value, '55');
    state.toggle.checked = true;
    state.listeners.change();
    assert.match(state.summary.textContent, /Combined stock: 5/);
});

test('incomplete variation prices show instructions instead of a misleading range', () => {
    const state = setup(true, [''], ['0']);
    assert.match(state.summary.textContent, /Enter a price and stock/);
});
