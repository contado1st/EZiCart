const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/product-variation-editor.js','utf8');

function setup(initial = 0) {
    const button = () => ({addEventListener(event, callback) {this.click = callback;}});
    const manual = button(), blank = button(), generate = button(), feedback = {}, summary = {};
    const attribute = {value:''}, option = {value:''};
    const names = [{value:''},{value:''}], values = [{value:''},{value:''}];
    const row = (type = '', value = '') => {
        const fields = Object.fromEntries(['type','value','price','stock','sku'].map(key => [key,{value:key === 'stock' ? '0' : '', name:`variations[__INDEX__][${key}]`, focus(){this.focused=true;}}]));
        fields.type.value = type; fields.value.value = value;
        return {fields, querySelector:selector => selector === '[data-variation-row]' ? null : fields[selector.match(/data-variation-(.+)\]/)?.[1]], querySelectorAll:() => Object.values(fields)};
    };
    const rows = {
        children:Array.from({length:initial},(_,i)=>row('Color',String(i))),
        append(fragment){this.children.push(fragment.row);}, addEventListener(){},
        querySelectorAll(selector){
            if(selector === '[data-variation-row]')return this.children;
            if(selector === '[name]')return this.children.flatMap(r=>Object.values(r.fields));
            const key = selector.match(/data-variation-(.+)\]/)?.[1];
            return this.children.map(r=>r.fields[key]);
        },
    };
    rows.children.forEach((r,i)=>Object.values(r.fields).forEach(f=>f.name=f.name.replace('__INDEX__',String(i))));
    const template = {content:{cloneNode(){const r=row();return {row:r,querySelector:selector=>selector==='[data-variation-row]'?r:r.querySelector(selector), querySelectorAll:()=>Object.values(r.fields)};}}};
    const toggle = {checked:true, addEventListener(){}, setAttribute(){}};
    const form = {querySelector:selector=>({'[name="stock"]':{},'[data-variations-toggle]':toggle,'[data-base-pricing]':{}})[selector]};
    const editor = {closest:()=>form,addEventListener(){},querySelector:selector=>({
        '[data-variation-rows]':rows,'[data-variation-template]':template,'[data-variation-feedback]':feedback,
        '[data-variation-options]':{},'[data-variation-summary]':summary,'[data-add-variation]':blank,
        '[data-generate-variations]':generate,'[data-add-manual-variation]':manual,
        '[data-manual-attribute]':attribute,'[data-manual-option]':option,
    })[selector],querySelectorAll:selector=>selector==='[data-attribute-name]'?names:values};
    vm.runInNewContext(source,{document:{querySelector:()=>editor},Intl});
    return {rows,manual,blank,generate,attribute,option,names,values,feedback,summary};
}

test('manual entry adds named editable fields and focuses the new price without overwriting existing rows',()=>{
    const h=setup(1); h.attribute.value=' Size / Color '; h.option.value=' Medium / Black '; h.manual.click();
    assert.equal(h.rows.children.length,2);
    const added=h.rows.children[1].fields;
    assert.equal(added.type.value,'Size / Color');
    assert.equal(added.value.value,'Medium / Black');
    assert.equal(added.price.name,'variations[1][price]');
    assert.equal(added.price.focused,true);
    assert.equal(h.rows.children[0].fields.value.value,'0');
    assert.equal(h.option.value,'');
});

test('manual entry rejects empty attributes, duplicate options and a full table',()=>{
    const h=setup();h.manual.click();assert.equal(h.rows.children.length,0);
    h.attribute.value='Color';h.option.value='Red';h.manual.click();
    h.attribute.value=' color ';h.option.value=' red ';h.manual.click();
    assert.equal(h.rows.children.length,1);assert.match(h.feedback.textContent,/already exists/);
    const full=setup(100);full.attribute.value='Size';full.option.value='Large';full.manual.click();
    assert.equal(full.rows.children.length,100);assert.match(full.feedback.textContent,/at most 100/);
});

test('generation and blank entry remain available alongside manually entered options',()=>{
    const h=setup();h.attribute.value='Color';h.option.value='Red';h.manual.click();
    h.names[0].value='Color';h.values[0].value='Red, Blue';h.generate.click();
    assert.deepEqual(h.rows.children.map(r=>r.fields.value.value),['Red','Blue']);
    h.blank.click();assert.equal(h.rows.children.length,3);
    assert.equal(h.rows.children[2].fields.type.name,'variations[2][type]');
});
