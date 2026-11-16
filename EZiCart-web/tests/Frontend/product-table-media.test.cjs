const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/product-table-media.js', 'utf8');

function setup(cachedFailure = false) {
    const fallback = {hiddenFromAssistiveTechnology:true,removeAttribute(){this.hiddenFromAssistiveTechnology=false;}};
    const image = {hidden:false,complete:cachedFailure,naturalWidth:cachedFailure ? 0 : 48,matches:()=>true,parentElement:{querySelector:()=>fallback}};
    let error;
    vm.runInNewContext(source,{document:{addEventListener:(name,fn)=>{error=fn;},querySelectorAll:()=>[image]}});
    return {image,fallback,fail:target=>error({target})};
}

test('a failed thumbnail reveals an accessible fallback without changing the product name or row',()=>{
    const h=setup(); h.fail(h.image);
    assert.equal(h.image.hidden,true);
    assert.equal(h.fallback.hiddenFromAssistiveTechnology,false);
});

test('an already failed cached image receives the same fallback',()=>{
    const h=setup(true);
    assert.equal(h.image.hidden,true);
    assert.equal(h.fallback.hiddenFromAssistiveTechnology,false);
});

test('successful thumbnails stay visible and unrelated media errors are left to their own handlers',()=>{
    const h=setup(); const other={matches:()=>false,hidden:false}; h.fail(other);
    assert.equal(h.image.hidden,false);
    assert.equal(other.hidden,false);
    assert.equal(h.fallback.hiddenFromAssistiveTechnology,true);
});
