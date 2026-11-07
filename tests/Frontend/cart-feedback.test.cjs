const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/cart-feedback.js', 'utf8');
function setup(fetcher) {
    let submit; const classes = new Set();
    const feedback = {hidden:true, textContent:'', classList:{remove:value=>classes.delete(value), add:value=>classes.add(value)}};
    const buttons = [{disabled:false},{disabled:false}], badge = {textContent:'0',hidden:true};
    const form = {action:'/cart/add/1?__tab=buyer',dataset:{},querySelector:()=>feedback,querySelectorAll:()=>buttons,addEventListener:(_,handler)=>submit=handler};
    const calls = [];
    vm.runInNewContext(source,{document:{querySelector:()=>form,querySelectorAll:()=>[badge]},FormData:class {constructor(input){this.form=input;}},fetch:async (...args)=>{calls.push(args);return fetcher(...args);}});
    return {feedback,buttons,badge,calls,classes,form,submit: value=>submit({submitter:{value},preventDefault(){}})};
}
test('Buy Now retains native form submission and never sends the asynchronous add request', async () => {
    const h = setup(async ()=>{throw new Error('Must not run');});
    await h.submit('buy_now');
    assert.equal(h.calls.length,0);
});
test('successful add keeps the buyer on the product page and updates the distinct-line cart badge', async () => {
    const h = setup(async ()=>({ok:true,json:async ()=>({message:'Item added to your saved cart.',count:2})}));
    await h.submit('');
    assert.equal(h.calls[0][0],'/cart/add/1?__tab=buyer');
    assert.equal(h.calls[0][1].headers.Accept,'application/json');
    assert.equal(h.badge.textContent,'2');
    assert.equal(h.badge.hidden,false);
    assert.equal(h.feedback.textContent,'Item added to your saved cart.');
    assert.ok(h.buttons.every(button=>!button.disabled));
});
test('a pending save blocks repeated clicks and server errors remain visible without changing the badge', async () => {
    let finish;
    const h = setup(()=>new Promise(resolve=>finish=resolve));
    const first = h.submit('');
    await h.submit('');
    assert.equal(h.calls.length,1);
    finish({ok:false,json:async ()=>({errors:{cart:['Not enough stock.']}})});
    await first;
    assert.equal(h.feedback.textContent,'Not enough stock.');
    assert.equal(h.classes.has('is-error'),true);
    assert.equal(h.badge.textContent,'0');
    assert.ok(h.buttons.every(button=>!button.disabled));
});
test('expired HTML responses give a recovery message instead of exposing a JSON parsing error', async () => {
    const h = setup(async ()=>({ok:false,json:async ()=>{throw new SyntaxError('Unexpected token');}}));
    await h.submit('');
    assert.match(h.feedback.textContent,/Refresh your session and check your cart/);
    assert.doesNotMatch(h.feedback.textContent,/Unexpected token/);
});
