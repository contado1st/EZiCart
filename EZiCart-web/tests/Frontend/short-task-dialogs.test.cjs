const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/short-task-dialogs.js', 'utf8');

function setup({supported = true, hash = '', busy = false} = {}) {
    const notices = []; const classes = new Set(); const created = [];
    const element = () => ({attrs:{},events:{},dataset:{},setAttribute(name,value){this.attrs[name]=value;},addEventListener(name,fn){this.events[name]=fn;},focus(){this.focused=true;}});
    const trigger = element(); trigger.dataset.taskOpen = 'wallet-send';
    const heading = {id:'',textContent:'Send demo funds'};
    const panel = {id:'wallet-send',querySelector(selector){return selector === 'h2' ? heading : selector === 'form[aria-busy="true"]' && busy ? {} : null;},before(node){this.dialog=node;}};
    const document = {body:{classList:{add:name=>classes.add(name),remove:name=>classes.delete(name)}},
        querySelectorAll:selector=>selector === '[data-task-panel]' ? [panel] : [trigger],
        querySelector:()=>created.find(node=>node.open) || null,dispatchEvent:event=>notices.push(event.detail),
        createElement(tag){const node=element(); node.tag=tag; node.append=(...nodes)=>{node.children=nodes;}; node.showModal=()=>{node.open=true;}; node.close=()=>{node.open=false; node.events.close();}; created.push(node); return node;}};
    vm.runInNewContext(source,{document,location:{hash},HTMLDialogElement:supported ? class {} : undefined,CustomEvent:class {constructor(name,options){this.detail=options.detail;}}});
    return {trigger,panel,classes,created,notices,open(){trigger.events.click({preventDefault(){}});}};
}

test('unsupported dialogs keep ordinary section links and original content usable',()=>{
    const h=setup({supported:false});
    assert.equal(h.created.length,0);
    assert.equal(h.trigger.events.click,undefined);
});

test('opening preserves the actual task nodes and closing returns focus and expanded state',()=>{
    const h=setup(); h.open();
    assert.equal(h.panel.dialog.children[1],h.panel);
    assert.equal(h.trigger.attrs['aria-expanded'],'true');
    assert.equal(h.classes.has('short-task-open'),true);
    h.panel.dialog.close();
    assert.equal(h.trigger.focused,true);
    assert.equal(h.trigger.attrs['aria-expanded'],'false');
    assert.equal(h.classes.has('short-task-open'),false);
});

test('deep links open their task with the accessible expanded state',()=>{
    const h=setup({hash:'#wallet-send'});
    assert.equal(h.panel.dialog.open,true);
    assert.equal(h.trigger.attrs['aria-expanded'],'true');
});

test('closing a pending wallet task warns that the server may still finish',()=>{
    const h=setup({busy:true}); h.open(); h.panel.dialog.close();
    assert.equal(h.notices[0].tone,'warning');
    assert.match(h.notices[0].message,/may still complete/);
});
