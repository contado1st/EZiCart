const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/wallet-actions.js', 'utf8');

function setup(fetcher) {
    const events = {}; const notifications = []; const calls = []; const timers = [];
    class Element {
        constructor() {this.events = {}; this.isConnected = true; this.attrs = {}; this.children = []; this.dataset = {};}
        setAttribute(name, value) {this.attrs[name] = value;}
        removeAttribute(name) {delete this.attrs[name];}
        addEventListener(name, handler) {this.events[name] = handler;}
        focus() {this.focused = true;}
    }
    class Form extends Element {
        constructor() {super(); this.action = 'https://shop.test/wallet/send/review'; this.resetCount = 0;}
        matches() {return true;}
        closest() {return content;}
        reset() {this.resetCount++;}
    }
    const form = new Form(); const key = {value:'old-key'}; const heading = new Element(); const content = new Element(); const balance = {textContent:'100.00'};
    content.childNodes = [form];
    content.querySelector = selector => selector === 'h2' ? heading : selector === '[name="request_key"]' ? key : selector === 'form' ? form : null;
    content.prepend = node => content.children.unshift(node); content.append = node => content.children.push(node);
    content.replaceChildren = (...nodes) => {content.childNodes = nodes;};
    Object.defineProperty(content, 'innerHTML', {set(value) {this.html = value; this.childNodes = [];},get() {return this.html;}});
    const button = new Element();
    vm.runInNewContext(source, {HTMLFormElement:Form,FormData:class {},CustomEvent:class {constructor(name, options) {this.detail = options.detail;}},
        document:{addEventListener:(name,fn)=>{events[name]=fn;},dispatchEvent:event=>notifications.push(event.detail),createElement:()=>new Element(),querySelectorAll:()=>[balance]},
        crypto:{randomUUID:()=> 'fresh-key'},Intl,navigator:{},AbortController,setTimeout:fn=>{timers.push(fn); return timers.length;},clearTimeout(){},fetch:async (...args)=>{calls.push(args); return fetcher(...args);}});
    return {form,content,key,button,balance,calls,notifications,timers,submit:()=>events.submit({target:form,submitter:button,defaultPrevented:false,preventDefault(){this.defaultPrevented=true;}})};
}

test('recipient review stays in the task panel and restores the original form nodes for another operation', async () => {
    const h = setup(async () => ({ok:true,json:async()=>({success:true,html:'<h2>Reviewed recipient</h2>'})}));
    await h.submit();
    assert.equal(h.calls[0][1].credentials, 'same-origin');
    assert.equal(h.content.html, '<h2>Reviewed recipient</h2>');
    assert.equal(h.notifications[0].tone, 'info');
    h.content.children.at(-1).events.click();
    assert.equal(h.content.childNodes[0], h.form);
    assert.equal(h.form.resetCount, 1);
    assert.equal(h.key.value, 'fresh-key');
});

test('only a successful server result updates the displayed balance and shows a posted receipt', async () => {
    const h = setup(async () => ({ok:true,json:async()=>({success:true,html:'<h2>Posted receipt</h2>',message:'Posted once.',available_cents:7500})}));
    await h.submit();
    assert.equal(h.balance.textContent, '75.00');
    assert.equal(h.notifications[0].tone, 'success');
    assert.equal(h.button.disabled, false);
});

test('validation failure preserves form inputs and balance and treats error details as text', async () => {
    const h = setup(async () => ({ok:false,json:async()=>({errors:{balance:['<b>Insufficient funds</b>']}})}));
    await h.submit();
    assert.equal(h.content.childNodes[0], h.form);
    assert.equal(h.balance.textContent, '100.00');
    assert.equal(h.content.children[0].textContent, '<b>Insufficient funds</b>');
    assert.equal(h.notifications[0].tone, 'error');
    assert.equal(h.button.disabled, false);
});

test('a pending request cannot be sent twice and an unknown response does not claim failure or success', async () => {
    let finish;
    const h = setup(() => new Promise(resolve => {finish = resolve;}));
    const first = h.submit(); await h.submit();
    assert.equal(h.calls.length, 1);
    finish({ok:true,json:async()=>{throw new SyntaxError();}}); await first;
    assert.match(h.notifications[0].message, /Check your wallet history/);
    assert.equal(h.balance.textContent, '100.00');
});

test('a timeout preserves the request key and warns that the server may still finish', async () => {
    const h = setup((url, options) => new Promise((resolve,reject) => options.signal.addEventListener('abort', () => reject(Object.assign(new Error(), {name:'AbortError'})))));
    const sending = h.submit(); h.timers[0](); await sending;
    assert.match(h.notifications[0].message, /may still complete/);
    assert.equal(h.key.value, 'old-key');
    assert.equal(h.button.disabled, false);
    assert.equal(h.balance.textContent, '100.00');
});
