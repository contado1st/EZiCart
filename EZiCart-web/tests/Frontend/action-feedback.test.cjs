const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/action-feedback.js', 'utf8');

function setup(seeds = [], inline = []) {
    class Element {
        constructor(tag = 'div') {this.tagName = tag; this.children = []; this.events = {}; this.dataset = {}; this.attrs = {}; this.classList = {add() {},remove() {}};}
        setAttribute(key, value) {this.attrs[key] = value;}
        removeAttribute(key) {delete this.attrs[key];}
        querySelector(tag) {return this.children.find(child => child.tagName === tag);}
        append(...nodes) {nodes.forEach(node => {node.parent = this; this.children.push(node);});}
        get firstElementChild() {return this.children[0];}
        remove() {this.parent.children = this.parent.children.filter(node => node !== this);}
        addEventListener(name, fn) {this.events[name] = fn;}
        closest() {return null;}
    }
    class Form extends Element {constructor() {super('form'); this.method = 'post';}}
    const region = new Element(); const events = {}; const windowEvents = {}; const timers = [];
    const doc = {querySelector: selector => selector === '[data-action-feedback-region]' ? region : {textContent: JSON.stringify(seeds)},
        querySelectorAll: selector => selector.includes('.feature-feedback') ? inline : [], createElement: tag => new Element(tag), addEventListener: (name, fn) => {events[name] = fn;}};
    vm.runInNewContext(source, {document:doc,window:{addEventListener:(name,fn)=>{windowEvents[name]=fn;}},HTMLFormElement:Form,queueMicrotask,
        setTimeout: fn => {timers.push(fn); return timers.length;},clearTimeout() {}});
    return {region,events,windowEvents,timers,Form,Element};
}

test('server feedback is escaped text, deduplicated, and dismissible', () => {
    const h = setup([{message:'<script>not markup</script>',tone:'success'},{message:'<script>not markup</script>',tone:'success'}]);
    assert.equal(h.region.children.length, 1);
    assert.equal(h.region.children[0].querySelector('p').textContent, '<script>not markup</script>');
    h.region.children[0].querySelector('button').events.click();
    assert.equal(h.region.children.length, 0);
});

test('asynchronous failure notifications remain errors and the stack stays bounded', () => {
    const h = setup();
    for (let n = 0; n < 4; n++) h.events['ezicart:feedback']({detail:{message:`Failure ${n}`,tone:'error'}});
    assert.equal(h.region.children.length, 3);
    assert.equal(h.region.children[2].attrs.role, 'alert');
    assert.equal(h.region.children[2].dataset.tone, 'error');
});

test('an inline error remains readable without a duplicate live announcement', () => {
    const inline = {attrs:{role:'alert'},textContent:'Action not completed. Check stock.',querySelector:()=>({textContent:'Check stock.'}),removeAttribute(name){delete this.attrs[name];}};
    const h = setup([{message:'Check stock.',tone:'error'}], [inline]);
    assert.equal(inline.attrs.role, undefined);
    assert.equal(inline.hidden, undefined);
    assert.equal(h.region.children[0].attrs.role, 'alert');
});

test('accepted writes show pending feedback without disabling named submit values or claiming success', async () => {
    const h = setup(); const form = new h.Form(); const button = new h.Element('button');
    const event = {target:form,submitter:button,defaultPrevented:false,preventDefault() {this.defaultPrevented = true;}};
    h.events.submit(event); await new Promise(resolve => setImmediate(resolve));
    assert.equal(form.attrs['aria-busy'], 'true');
    assert.equal(button.disabled, undefined);
    assert.equal(h.region.children.length, 0);
    const retry = {...event,defaultPrevented:false}; h.events.submit(retry);
    assert.equal(retry.defaultPrevented, true);
    h.timers[0]();
    assert.match(h.region.children[0].querySelector('p').textContent, /Check the result/);
    h.windowEvents.pageshow();
    assert.equal(form.attrs['aria-busy'], undefined);
});

test('cancelled or asynchronous submissions do not become pending native writes', async () => {
    const h = setup(); const form = new h.Form();
    h.events.submit({target:form,defaultPrevented:true});
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(form.attrs['aria-busy'], undefined);
    assert.equal(h.region.children.length, 0);
});

test('approval and rejection choices update button and confirmation tones without changing the action', () => {
    const h = setup(); const choice = {value:'reject'}; const button = {name:'',dataset:{}};
    const form = {dataset:{},querySelector:()=>choice,querySelectorAll:()=>[button]};
    const event = {target:{closest:()=>form}};
    h.events.change(event);
    assert.equal(button.dataset.actionTone, 'danger');
    assert.equal(form.dataset.decisionTone, 'danger');
    choice.value = 'approve'; h.events.change(event);
    assert.equal(button.dataset.actionTone, 'success');
    assert.equal(form.dataset.decisionTone, 'normal');
    choice.value = 'under_review'; h.events.change(event);
    assert.equal(button.dataset.actionTone, undefined);
    assert.equal(form.dataset.decisionTone, 'normal');
});
