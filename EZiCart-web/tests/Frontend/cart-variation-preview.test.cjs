const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/cart-variation-preview.js', 'utf8');

function fixture(options, index = 0) {
    const events = {}, imageEvents = {};
    const select = {selectedOptions:[options[index]], addEventListener:(event, handler) => events[event] = handler};
    const image = {src:'', hidden:false, addEventListener:(event, handler) => imageEvents[event] = handler, removeAttribute(name) { delete this[name]; }};
    const caption = {};
    const form = {querySelector: selector => ({'[data-variant-preview-select]':select, '[data-variant-preview-image]':image, '[data-variant-preview-caption]':caption})[selector]};
    return {form, image, caption, choose(i) {select.selectedOptions = [options[i]]; events.change();}, fail:() => imageEvents.error()};
}
function run(forms) {
    vm.runInNewContext(source, {document:{querySelectorAll:() => forms.map(item => item.form)}, window:{location:{href:'https://shop.example/cart'}}, URL});
}
const option = (label, url, own = '1') => ({dataset:{previewLabel:label, previewImage:url, previewOwnImage:own}});

test('selection previews its variation image and product-photo fallback without submitting', () => {
    const h = fixture([option('Color: Red','/storage/red.png'), option('Color: Blue','/storage/product.png','0')]);
    run([h]);
    assert.equal(h.image.src,'https://shop.example/storage/red.png');
    assert.equal(h.image.hidden,false);
    assert.equal(h.caption.textContent,'Variation photo: Color: Red');
    h.choose(1);
    assert.equal(h.image.src,'https://shop.example/storage/product.png');
    assert.equal(h.caption.textContent,'Product photo: Color: Blue');
});

test('missing, broken and unsafe images use a text fallback and a later choice recovers', () => {
    const h = fixture([option('No photo',''), option('Unsafe','javascript:alert(1)'), option('Available','/ok.png')]);
    run([h]);
    assert.equal(h.image.hidden,true);
    assert.equal(h.image.src,undefined);
    h.choose(1);
    assert.equal(h.image.hidden,true);
    h.choose(2);
    assert.equal(h.image.hidden,false);
    h.fail();
    assert.equal(h.image.hidden,true);
    assert.match(h.caption.textContent,/Photo unavailable/);
    h.choose(2);
    assert.equal(h.image.hidden,false);
});

test('each cart row keeps its own preview and labels are treated as text', () => {
    const a = fixture([option('<img src=x>','/a.png'), option('New','/new.png')]);
    const b = fixture([option('Other product','/b.png')]);
    run([a,b]);
    assert.equal(a.caption.textContent,'Variation photo: <img src=x>');
    a.choose(1);
    assert.equal(b.image.src,'https://shop.example/b.png');
    assert.equal(b.caption.textContent,'Variation photo: Other product');
});
