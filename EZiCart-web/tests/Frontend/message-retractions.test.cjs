const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/message-retractions.js','utf8');

async function setup({data={messages:[]},hidden=false,ok=true,reject=false}={}) {
    const events={}, windowEvents={};let timer, requests=0;
    const attachment={dataset:{messageAttachment:'8'},replaceChildren(node){this.notice=node;}};
    const content={replaceChildren(node){this.notice=node;}};
    const body={textContent:'Original body'};
    const message={dataset:{directMessage:'4',ownMessage:'1'},querySelector:selector=>selector==='[data-message-content]'?content:body,querySelectorAll:()=>[attachment]};
    const page={dataset:{messageRetractions:'/chat/conversations/2/retractions'},querySelectorAll:()=>[message]};
    const document={hidden,querySelector:()=>page,createElement:()=>({}),addEventListener:(event,handler)=>events[event]=handler};
    const draft={value:'Unsent draft stays here'};
    vm.runInNewContext(source,{document,window:{location:{href:'https://shop.example/chat/conversations/2',origin:'https://shop.example'},addEventListener:(event,handler)=>windowEvents[event]=handler},URL,
        fetch:async url=>{requests++;assert.match(url,/message_ids%5B%5D=4/);if(reject)throw new Error('Offline');return {ok,json:async()=>data};},
        setInterval:handler=>{timer=handler;return 1;},clearInterval:()=>{},
    });
    await new Promise(resolve=>setImmediate(resolve));
    return {attachment,content,body,draft,document,events,windowEvents,timer,requests:()=>requests};
}

test('whole-message unsend replaces its content without reloading or clearing the composer',async()=>{
    const h=await setup({data:{messages:[{id:4,unsent:true,attachment_ids:[8]}]}});
    assert.equal(h.content.notice.textContent,'You unsent this message.');
    assert.equal(h.draft.value,'Unsent draft stays here');
});

test('individual attachment status removes its links while preserving message text',async()=>{
    const h=await setup({data:{messages:[{id:4,unsent:false,attachment_ids:[8]}]}});
    assert.equal(h.attachment.notice.textContent,'Attachment unsent.');
    assert.equal(h.body.textContent,'Original body');
    assert.equal(h.content.notice,undefined);
});

test('a fully retracted attachment-only message updates its placeholder and ignores unknown IDs',async()=>{
    const h=await setup({data:{messages:[{id:999,unsent:true},{id:4,unsent:false,attachments_only_unsent:true,attachment_ids:[8]}]}});
    assert.equal(h.body.textContent,'Attachments unsent.');
    assert.equal(h.content.notice,undefined);
});

test('hidden tabs do not poll and connection or permission failure preserves visible history',async()=>{
    const hidden=await setup({hidden:true});assert.equal(hidden.requests(),0);
    hidden.document.hidden=false;hidden.events.visibilitychange();
    await new Promise(resolve=>setImmediate(resolve));assert.equal(hidden.requests(),1);
    const offline=await setup({reject:true});assert.equal(offline.content.notice,undefined);
    const denied=await setup({ok:false,data:{messages:[{id:4,unsent:true}]}});assert.equal(denied.content.notice,undefined);
});
