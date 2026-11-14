const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/dispatch-location.js', 'utf8');
function setup(available = true) {
    let handler, success, failure, options, calls = 0, submits = 0;
    const button = {disabled:false, addEventListener: (_, fn) => {handler = fn;}};
    const feedback = {textContent:''};
    const elements = Object.fromEntries(['latitude','longitude','accuracy_meters','captured_at'].map(key => [key,{value:''}]));
    const form = {elements,querySelector: selector => selector === '[data-share-location]' ? button : feedback,requestSubmit: () => {submits++;}};
    const navigator = available ? {geolocation:{getCurrentPosition: (ok, bad, opts) => {calls++; success=ok; failure=bad; options=opts;}}} : {};
    vm.runInNewContext(source,{document:{querySelectorAll:()=>[form]},navigator,Date,Number});
    return {button,feedback,elements,click:()=>handler(),success: data=>success(data),failure:()=>failure(),calls:()=>calls,submits:()=>submits,options:()=>options};
}
test('location is requested only after the rider clicks and one accurate sample submits through the existing form', () => {
    const h=setup(); assert.equal(h.calls(),0); h.click(); assert.equal(h.calls(),1); assert.equal(h.button.disabled,true);
    const timestamp=Date.parse('2026-11-15T01:00:00Z');
    h.success({coords:{latitude:14.14,longitude:121.47,accuracy:10},timestamp});
    assert.equal(h.elements.latitude.value,14.14); assert.equal(h.elements.longitude.value,121.47); assert.equal(h.elements.accuracy_meters.value,10);
    assert.equal(h.elements.captured_at.value,'2026-11-15T01:00:00.000Z'); assert.equal(h.submits(),1);
    assert.equal(h.options().maximumAge,0); assert.equal(h.options().enableHighAccuracy,true);
});
test('denied permission leaves the form unsubmitted and allows a retry', () => {
    const h=setup(); h.click(); h.failure(); assert.equal(h.submits(),0); assert.equal(h.button.disabled,false); assert.match(h.feedback.textContent,/not shared/);
});
test('poor or invalid accuracy cannot be sent as a valid location', () => {
    for (const accuracy of [251,NaN,-1]) { const h=setup(); h.click(); h.success({coords:{latitude:14,longitude:121,accuracy},timestamp:Date.now()}); assert.equal(h.submits(),0); assert.equal(h.button.disabled,false); }
});
test('an unavailable geolocation API provides a manual-dispatch fallback without submitting', () => {
    const h=setup(false); h.click(); assert.equal(h.calls(),0); assert.equal(h.submits(),0); assert.match(h.feedback.textContent,/unavailable/);
});
