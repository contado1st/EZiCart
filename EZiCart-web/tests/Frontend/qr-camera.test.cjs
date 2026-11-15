const {test} = require('node:test');
const assert = require('node:assert/strict');
const fixture = require('../Fixtures/qr-webcam.json');
const camera = import('../../resources/js/qr-camera.js');
const flush = () => new Promise(resolve => setImmediate(resolve));

class Element {
    constructor() { this.events = {}; this.children = []; this.hidden = false; this.value = ''; this.textContent = 'Use camera'; this.classList = {add() {}, toggle() {}}; }
    addEventListener(name, fn) { (this.events[name] ||= []).push(fn); }
    async fire(name, value = {}) { for (const fn of this.events[name] || []) await fn(value); }
    append(value) {this.children.push(value);}
    replaceChildren() {this.children = [];}
    insertAdjacentElement(_, element) {this.picker = element;}
    setAttribute() {}
    dispatchEvent() {}
    focus() {}
}

async function setup({acquire, result = null, batch = false, secure = true} = {}) {
    const elements = Object.fromEntries(['start','stop','video','status','method'].map(name => [name, new Element()]));
    elements.video.play = async () => {};
    let submits = 0;
    const form = {querySelector: selector => selector === '[name="method"]' ? elements.method : elements[selector.match(/-(start|stop|video|status)\]/)?.[1]], closest: () => null,
        addEventListener() {}, requestSubmit: () => submits++};
    const input = new Element(); input.closest = () => form;
    const doc = new Element(); doc.hidden = false;
    doc.querySelectorAll = selector => selector === (batch ? '[data-qr-batch]' : '[data-qr-input]') ? [input] : [];
    doc.createElement = () => new Element();
    const tracks = [];
    const calls = [];
    const media = {getUserMedia: async constraints => {
        calls.push(constraints);
        if (acquire) return acquire(constraints);
        const track = new Element(); track.stopped = false; track.stop = () => {track.stopped = true;}; track.getSettings = () => ({deviceId: 'built-in'}); tracks.push(track);
        return {getTracks: () => [track], getVideoTracks: () => [track]};
    }, enumerateDevices: async () => ['built-in','usb'].map(deviceId => ({kind:'videoinput',deviceId,label:deviceId}))};
    const browser = new Element(); browser.isSecureContext = secure;
    browser.setTimeout = callback => {browser.nextScan = callback; return 1;}; browser.clearTimeout = () => {browser.nextScan = null;};
    browser.BarcodeDetector = class {async detect() {return result ? [{rawValue: result}] : [];}};
    (await camera).initQrScanners(doc, browser, {mediaDevices: media});
    return {elements, input, tracks, calls, doc, browser, media, submitted: () => submits};
}

test('webcam constraints prefer a rear camera but retry with any webcam when unsupported', async () => {
    const calls = [];
    const media = {getUserMedia: async constraints => {calls.push(constraints); if (calls.length === 1) throw {name:'OverconstrainedError'}; return 'webcam';}};
    assert.equal(await (await camera).openCamera(media), 'webcam');
    assert.deepEqual(calls, [{audio:false,video:{facingMode:{ideal:'environment'}}},{audio:false,video:true}]);
});

test('permission denial does not retry or bypass the browser decision', async () => {
    let calls = 0;
    await assert.rejects((await camera).openCamera({getUserMedia: async () => {calls++; throw Object.assign(new Error(), {name:'NotAllowedError'});}}), {name:'NotAllowedError'});
    assert.equal(calls, 1);
});

test('native QR support avoids loading the software decoder', async () => {
    let loaded = false;
    const browser = {BarcodeDetector: class {static async getSupportedFormats() {return ['qr_code'];} async detect() {return [{rawValue:'EZR:BADGE'}];}}};
    const decode = await (await camera).createQrDecoder(browser, {}, async () => {loaded = true;});
    assert.equal(await decode({}), 'EZR:BADGE');
    assert.equal(loaded, false);
});

test('local software decoder reads a real parcel QR frame when native QR support is missing or broken', async () => {
    const size = (fixture.length + 8) * 6;
    const pixels = new Uint8ClampedArray(size * size * 4).fill(255);
    fixture.forEach((row, y) => [...row].forEach((bit, x) => {
        if (bit !== '1') return;
        for (let dy = 0; dy < 6; dy++) for (let dx = 0; dx < 6; dx++) {
            const offset = (((y + 4) * 6 + dy) * size + (x + 4) * 6 + dx) * 4;
            pixels[offset] = pixels[offset+1] = pixels[offset+2] = 0;
        }
    }));
    const context = {drawImage() {}, getImageData: () => ({data:pixels,width:size,height:size})};
    const doc = {createElement: () => ({getContext: () => context})};
    const video = {readyState:2,videoWidth:size,videoHeight:size};
    for (const browser of [{}, {BarcodeDetector: class {async detect() {throw new Error('Unsupported');}}}]) {
        const decode = await (await camera).createQrDecoder(browser, doc);
        assert.equal(await decode(video), 'EZP:WEBCAM-TEST');
    }
});

test('camera selection switches to a USB webcam and stops the previous stream', async () => {
    const h = await setup();
    await h.elements.start.fire('click'); await flush();
    const picker = h.elements.start.picker;
    assert.equal(picker.hidden, false);
    picker.children[0].value = 'usb';
    await picker.children[0].fire('change'); await flush();
    assert.equal(h.tracks[0].stopped, true);
    assert.deepEqual(h.calls[1], {audio:false,video:{deviceId:{exact:'usb'}}});
});

test('closing during a pending permission request releases a late camera stream', async () => {
    let resolve;
    const pending = new Promise(done => {resolve = done;});
    let stopped = false;
    const h = await setup({acquire: () => pending});
    const starting = h.elements.start.fire('click'); await flush();
    await h.elements.stop.fire('click');
    resolve({getTracks: () => [{stop: () => {stopped = true;}}]});
    await starting;
    assert.equal(stopped, true);
    assert.equal(h.elements.video.srcObject, null);
    assert.equal(h.elements.start.disabled, false);
});

test('a captured single QR fills the form, releases the webcam, and submits through existing verification', async () => {
    const h = await setup({result:'EZP:PARCEL'});
    await h.elements.start.fire('click'); await flush();
    assert.equal(h.input.value, 'EZP:PARCEL');
    assert.equal(h.elements.method.value, 'camera');
    assert.equal(h.tracks[0].stopped, true);
    assert.equal(h.submitted(), 1);
});

test('batch scanning keeps existing lines and does not add the same parcel twice', async () => {
    const h = await setup({batch:true,result:'EZP:PARCEL'});
    h.input.value = 'EZP:OTHER';
    await h.elements.start.fire('click'); await flush();
    await h.browser.nextScan();
    assert.equal(h.input.value, 'EZP:OTHER\nEZP:PARCEL');
    assert.equal(h.submitted(), 0);
});

test('leaving the page turns off the webcam and does not reopen it automatically', async () => {
    const h = await setup();
    await h.elements.start.fire('click'); await flush();
    h.doc.hidden = true; await h.doc.fire('visibilitychange');
    assert.equal(h.tracks[0].stopped, true);
    assert.equal(h.elements.video.srcObject, null);
    h.doc.hidden = false; await h.doc.fire('visibilitychange');
    assert.equal(h.calls.length, 1);
});

test('HTTP pages do not request camera access and explain how to use HTTPS', async () => {
    const h = await setup({secure:false});
    await h.elements.start.fire('click');
    assert.equal(h.calls.length, 0);
    assert.match(h.elements.status.textContent, /HTTPS/);
});

test('missing, blocked, and busy webcams show different recovery messages', async () => {
    const {cameraErrorMessage} = await camera;
    assert.match(cameraErrorMessage({name:'NotAllowedError'}), /permission is blocked/);
    assert.match(cameraErrorMessage({name:'NotFoundError'}), /No camera was found/);
    assert.match(cameraErrorMessage({name:'NotReadableError'}), /Close other apps/);
});
