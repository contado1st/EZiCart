const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/cart-selection.js', 'utf8');

class Check {
    constructor(value, cents, quantity, disabled = false) { this.value = value; this.dataset = {cents: String(cents), quantity: String(quantity)}; this.disabled = disabled; this.checked = !disabled; this.events = {}; }
    addEventListener(event, handler) { this.events[event] = handler; }
    change(checked) { this.checked = checked; this.events.change(); }
    closest() { return this.shop; }
}
function setup({saved = null, storageBlocked = false} = {}) {
    const a = new Check('1', 10000, 3), b = new Check('2_7', 20000, 1), unavailable = new Check('3', 99900, 1, true);
    const other = new Check('4', 5000, 1);
    [a,b,unavailable].forEach(item => {item.dataset.shopId = 'shop-1';});
    other.dataset.shopId = 'shop-2';
    const shops = [[a,b,unavailable], [other]].map(lines => {
        const check = new Check();
        const shop = {querySelector: () => check, querySelectorAll: selector => selector.includes(':not') ? lines.filter(line => !line.disabled) : lines};
        check.shop = shop;
        return {check, shop};
    });
    const output = Object.fromEntries(['[data-selected-count]', '[data-selected-button-count]', '[data-selected-total]', '[form="cart-checkout-selection"][type="submit"]'].map(key => [key, {}]));
    const input = {value:'1'}; let submissions = 0;
    const quantityForm = {querySelector: () => input, requestSubmit: () => submissions++};
    const quantityButtons = [-1,1].map(step => ({dataset:{qtyStep:String(step)}, closest: () => quantityForm, addEventListener(event, callback) {this.click = callback;}}));
    const page = {dataset:{buyer:'buyer-1'}, querySelector: selector => output[selector], querySelectorAll: selector => ({'[data-cart-item]':[a,b,unavailable,other], '[data-shop]':shops.map(entry => entry.shop), '[data-select-shop]':shops.map(entry => entry.check), '[data-qty-step]':quantityButtons})[selector]};
    const storage = {value: saved === null ? null : JSON.stringify(saved), getItem(key) {this.key=key; if(storageBlocked) throw new Error('Blocked'); return this.value;}, setItem(key,value) {if(storageBlocked) throw new Error('Blocked'); this.key=key; this.value=value;}};
    vm.runInNewContext(source, {document:{querySelector: () => page}, sessionStorage:storage, Intl});
    return {a,b,other,unavailable,shops,output,storage,input,quantityButtons,submissions:()=>submissions};
}
test('selection totals count product lines, include quantities in prices and exclude unavailable products', () => {
    const h = setup();
    assert.equal(h.output['[data-selected-count]'].textContent, '2');
    assert.match(h.output['[data-selected-total]'].textContent, /500\.00/);
    h.a.change(false);
    assert.equal(h.output['[data-selected-count]'].textContent, '1');
    assert.match(h.output['[data-selected-total]'].textContent, /200\.00/);
    assert.equal(h.shops[0].check.indeterminate, true);
});
test('selecting another shop switches the selection and leaves unavailable products unchecked', () => {
    const h = setup();
    h.shops[1].check.change(true);
    assert.equal(h.a.checked, false);
    assert.equal(h.b.checked, false);
    assert.equal(h.other.checked, true);
    assert.equal(h.unavailable.checked, false);
    h.shops[0].check.change(true);
    assert.equal(h.a.checked, true);
    assert.equal(h.b.checked, true);
    assert.equal(h.other.checked, false);
    assert.equal(h.unavailable.checked, false);
    h.shops[0].check.change(false);
    assert.equal(h.output['[form="cart-checkout-selection"][type="submit"]'].disabled, true);
});
test('restoring an old mixed-shop selection keeps only one shop and individual selection can switch shops', () => {
    const h = setup({saved:['1','4']});
    assert.equal(h.a.checked,true);
    assert.equal(h.other.checked,false);
    h.other.change(true);
    assert.equal(h.a.checked,false);
    assert.equal(h.other.checked,true);
});
test('selection survives a quantity reload and storage restrictions do not break checkout', () => {
    const h = setup({saved:['2_7']});
    assert.equal(h.a.checked, false);
    assert.equal(h.b.checked, true);
    assert.deepEqual(JSON.parse(h.storage.value), ['2_7']);
    assert.equal(h.storage.key, 'ezicart-cart-selection-buyer-1');
    const restricted = setup({storageBlocked:true});
    assert.equal(restricted.output['[data-selected-count]'].textContent, '2');
});
test('quantity steppers submit valid updates and do not exceed checkout quantity bounds', () => {
    const h = setup();
    h.quantityButtons[0].click();
    assert.equal(h.submissions(),0);
    h.quantityButtons[1].click();
    assert.equal(h.input.value,'2');
    assert.equal(h.submissions(),1);
    h.input.value='20'; h.quantityButtons[1].click();
    assert.equal(h.submissions(),1);
});
