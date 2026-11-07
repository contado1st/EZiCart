const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/js/product-selection.js', 'utf8');
function setup({variation = false, shopOpen = true, stock = 50} = {}) {
    let change;
    const select = variation ? {value: '', selectedOptions: [{dataset: {stock: '7', adjustment: '20', image: '/selected.jpg'}, textContent: 'Blue'}], addEventListener(name, handler) {change = handler;}} : null;
    const quantity = {value: '50'}, price = {}, label = {}, image = {removeAttribute() {delete this.src;}};
    const submits = [{}, {}];
    const form = {dataset: {stock: String(stock), price: '200', shopOpen: shopOpen ? '1' : '0'},
        querySelector: selector => ({'[name="variation_id"]': select, '[name="quantity"]': quantity, '[data-variation-image]': image})[selector], querySelectorAll: () => submits};
    vm.runInNewContext(source, {document: {querySelector: () => form, getElementById: name => name === 'basePriceDisplay' ? price : label}, Intl});
    return {select, quantity, price, label, image, submits, change};
}
test('both purchase buttons require a stocked variation and update its price and image', () => {
    const h = setup({variation: true});
    assert.equal(h.quantity.disabled, true);
    assert.ok(h.submits.every(button => button.disabled));
    h.select.value = '1'; h.change();
    assert.equal(h.quantity.max, '7'); assert.equal(h.quantity.value, '7');
    assert.ok(h.submits.every(button => !button.disabled));
    assert.equal(h.image.src, '/selected.jpg');
    assert.match(h.price.textContent, /220\.00/);
});
test('quantity respects checkout limit and both buttons disable for closed or out-of-stock shops', () => {
    const h = setup();
    assert.equal(h.quantity.max, '20'); assert.equal(h.quantity.value, '20');
    assert.ok(setup({shopOpen:false}).submits.every(button => button.disabled));
    assert.ok(setup({stock:0}).submits.every(button => button.disabled));
});
