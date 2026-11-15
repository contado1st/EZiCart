const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/workflow-progress.js', 'utf8');
class Element {
    constructor(tag = 'div') { this.tagName = tag.toUpperCase(); this.children = []; this.dataset = {}; this.textContent = ''; this.hidden = false; this.events = {}; }
    append(node) {node.parent = this; this.children.push(node);}
    replaceChildren() {this.children = [];}
    remove() {if(this.parent) this.parent.children = this.parent.children.filter(child => child !== this);}
    setAttribute(name, value) {this[name] = value;}
    focus() {this.focused = true;}
    addEventListener(name, handler) {this.events[name] = handler;}
    querySelector() {return this.children.find(child => child.dataset.workflowGeneratedBadge);}
}
async function setup() {
    const elements = Object.fromEntries(['total','summary','empty','items','updated','refresh','open','dialog','close'].map(name => [`[data-workflow-${name}]`, new Element()]));
    const dialog = elements['[data-workflow-dialog]'];
    dialog.open = false;
    dialog.showModal = () => {dialog.open = true;};
    dialog.close = () => {dialog.open = false; dialog.events.close();};
    dialog.getBoundingClientRect = () => ({left: 20, right: 740, top: 20, bottom: 500});
    const bodyClasses = new Set();
    const root = {querySelector: selector => elements[selector]};
    const button = new Element('button');
    const form = {action: 'https://shop.test/seller/orders/1/confirm-handover?__tab=abc', querySelectorAll: () => [button]};
    const script = {dataset: {workflowUrl: 'https://shop.test/workflow-attention'}};
    let payload = {count: 1, items: [{order_id: 1, label:'Parcel: confirm handover', url:'https://shop.test/seller/orders/1', target:form.action, count:1}], progress: {1:{requires_action:true, action_url:form.action}}};
    let fail = false; let poll; const calls = [];
    const document = {hidden:false, body: {classList: {add: name => bodyClasses.add(name), remove: name => bodyClasses.delete(name)}}, querySelector: selector => selector.includes('script') ? script : root,
        querySelectorAll: selector => selector === 'form[action]' ? [form] : [],
        createElement: tag => new Element(tag), addEventListener() {}};
    vm.runInNewContext(source, {document, location: new URL('https://shop.test/seller/orders/1'), URL, AbortController, setTimeout, clearTimeout,
        fetch: async url => {calls.push(url); if (fail) throw new Error('Offline'); return {ok:true, json:async () => payload};},
        window: {setInterval: handler => poll = handler, addEventListener() {}}});
    await new Promise(resolve => setImmediate(resolve));
    return {elements, button, document, calls, bodyClasses, poll: () => poll(), setPayload: value => payload = value, setOffline: value => fail = value};
}

test('actions stay closed until requested and closing restores the launcher and page scrolling', async () => {
    const h = await setup();
    const dialog = h.elements['[data-workflow-dialog]'];
    const trigger = h.elements['[data-workflow-open]'];
    assert.equal(dialog.open, false);
    trigger.events.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(dialog.open, true);
    assert.equal(trigger['aria-expanded'], 'true');
    assert.equal(h.bodyClasses.has('workflow-attention-open'), true);
    assert.equal(h.calls.length, 2);
    h.elements['[data-workflow-close]'].events.click();
    assert.equal(dialog.open, false);
    assert.equal(trigger['aria-expanded'], 'false');
    assert.equal(trigger.focused, true);
    assert.equal(h.bodyClasses.has('workflow-attention-open'), false);
});

test('backdrop clicks close the popup but clicks inside and drags from inside do not', async () => {
    const h = await setup();
    const dialog = h.elements['[data-workflow-dialog]'];
    h.elements['[data-workflow-open]'].events.click();
    const inside = {target: dialog, clientX: 40, clientY: 40};
    const outside = {target: dialog, clientX: 5, clientY: 5};
    dialog.events.pointerdown(inside); dialog.events.click(inside);
    assert.equal(dialog.open, true);
    dialog.events.pointerdown(inside); dialog.events.click(outside);
    assert.equal(dialog.open, true);
    dialog.events.pointerdown(outside); dialog.events.click(outside);
    assert.equal(dialog.open, false);
});
test('action badges use live tasks and remove themselves when the required action is complete', async () => {
    const h = await setup();
    assert.equal(h.button.children[0].textContent, '1');
    assert.equal(h.elements['[data-workflow-total]'].textContent, '1');
    h.setPayload({count:0, items:[], progress:{}}); await h.poll();
    assert.equal(h.button.children.length, 0);
    assert.equal(h.elements['[data-workflow-total]'].hidden, true);
    assert.equal(h.elements['[data-workflow-empty]'].hidden, false);
});
test('failed refresh preserves the last known action counts and explains connection failure', async () => {
    const h = await setup(); h.setOffline(true); await h.poll();
    assert.equal(h.button.children[0].textContent, '1');
    assert.match(h.elements['[data-workflow-updated]'].textContent, /last successful check/);
});
test('task descriptions are text and external task links are ignored', async () => {
    const h = await setup();
    h.setPayload({count:2, items:[{label:'<script>alert(1)</script>',url:'https://shop.test/orders/1',count:1},{label:'External',url:'https://external.test/orders/2',count:1}],progress:{}});
    await h.poll();
    const rows = h.elements['[data-workflow-items]'].children;
    assert.equal(rows.length, 1);
    assert.equal(rows[0].children[0].textContent, '<script>alert(1)</script>');
});
test('background tabs do not poll until visible again', async () => {
    const h = await setup(); const initial = h.calls.length;
    h.document.hidden = true; await h.poll(); assert.equal(h.calls.length, initial);
    h.document.hidden = false; await h.poll(); assert.equal(h.calls.length, initial + 1);
});
