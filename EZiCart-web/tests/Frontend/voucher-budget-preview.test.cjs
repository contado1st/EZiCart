const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/js/voucher-budget-preview.js', 'utf8');

function setup({available = 10000, ...input} = {}) {
    const fields = Object.fromEntries(Object.entries({type: 'fixed', value: '50', max_discount: '', usage_limit: '3', ...input}).map(([key, value]) => [key, {value}]));
    const events = {};
    const form = {querySelector: selector => fields[selector.match(/name="([^"]+)"/)[1]], addEventListener: (event, callback) => {events[event] = callback;}};
    const status = {dataset: {availableCents: String(available)}, closest: () => form};
    vm.runInNewContext(source, {document: {querySelectorAll: () => [status]}});
    return {fields, status, events};
}

test('fixed offer shows maximum liability, available funds and exact shortfall', () => {
    const h = setup();
    assert.match(h.status.textContent, /Required: PHP 150\.00/);
    assert.match(h.status.textContent, /Available: PHP 100\.00/);
    assert.match(h.status.textContent, /Shortfall: PHP 50\.00/);
});
test('percent offer needs a cap and reserves that cap per redemption', () => {
    const h = setup({type: 'percent', value: '10'});
    assert.match(h.status.textContent, /maximum discount/);
    h.fields.max_discount.value = '20'; h.events.input();
    assert.match(h.status.textContent, /Required: PHP 60\.00/);
    assert.match(h.status.textContent, /Sufficient campaign funds/);
});
test('fixed cap and cent amounts are reflected when the owner edits the form', () => {
    const h = setup({value: '50', max_discount: '10.01', usage_limit: '2', available: 2000});
    assert.match(h.status.textContent, /Required: PHP 20\.02/);
    assert.match(h.status.textContent, /Shortfall: PHP 0\.02/);
    h.fields.usage_limit.value = '1'; h.events.change();
    assert.match(h.status.textContent, /Required: PHP 10\.01/);
    assert.match(h.status.textContent, /Sufficient campaign funds/);
});
test('empty or invalid limits explain what to enter instead of showing a fake total', () => {
    const h = setup({usage_limit: ''});
    assert.match(h.status.textContent, /Enter a valid redemption limit/);
    assert.doesNotMatch(h.status.textContent, /NaN/);
    h.fields.usage_limit.value = '-1'; h.events.input();
    assert.match(h.status.textContent, /Enter a valid redemption limit/);
});
