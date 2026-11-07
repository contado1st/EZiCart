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
    addEventListener(name, handler) {this.events[name] = handler;}
    querySelector() {return this.children.find(child => child.dataset.workflowGeneratedBadge);}
}
async function setup() {
    const elements = Object.fromEntries(['total','summary','empty','items','updated','refresh'].map(name => [`[data-workflow-${name}]`, new Element()]));
    const root = {querySelector: selector => elements[selector]};
    const button = new Element('button');
    const form = {action: 'https://shop.test/seller/orders/1/confirm-handover?__tab=abc', querySelectorAll: () => [button]};
    const script = {dataset: {workflowUrl: 'https://shop.test/workflow-attention'}};
    let payload = {count: 1, items: [{order_id: 1, label:'Parcel: confirm handover', url:'https://shop.test/seller/orders/1', target:form.action, count:1}], progress: {1:{requires_action:true, action_url:form.action}}};
    let fail = false; let poll; const calls = [];
    const document = {hidden:false, querySelector: selector => selector.includes('script') ? script : root,
        querySelectorAll: selector => selector === 'form[action]' ? [form] : [],
        createElement: tag => new Element(tag), addEventListener() {}};
    vm.runInNewContext(source, {document, location: new URL('https://shop.test/seller/orders/1'), URL, AbortController, setTimeout, clearTimeout,
        fetch: async url => {calls.push(url); if (fail) throw new Error('Offline'); return {ok:true, json:async () => payload};},
        window: {setInterval: handler => poll = handler, addEventListener() {}}});
    await new Promise(resolve => setImmediate(resolve));
    return {elements, button, document, calls, poll: () => poll(), setPayload: value => payload = value, setOffline: value => fail = value};
}
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
