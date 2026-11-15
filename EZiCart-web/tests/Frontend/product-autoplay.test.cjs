const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function setup({reduced = false, saveData = false} = {}) {
    let callback;
    const events = {};
    const videos = [0, 1].map(() => ({paused: true, muted: false, hiddenSlide: false, listeners: {},
        addEventListener(name, handler) {this.listeners[name] = handler;},
        closest() {return this.hiddenSlide ? {} : null;},
        pause() {this.paused = true;},
        play() {this.paused = false; this.listeners.play?.(); return Promise.resolve();}}));
    const document = {hidden: false, addEventListener(name, handler) {events[name] = handler;}, querySelectorAll(selector) {return selector === 'video[data-product-autoplay]' ? videos : [];}};
    const preference = {matches: reduced, addEventListener(name, handler) {events.motion = handler;}};
    const window = {navigator: {connection: {saveData}}, matchMedia: () => preference, addEventListener(name, handler) {events[name] = handler;}};
    class Observer {constructor(handler) {callback = handler;} observe() {}}
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/product-media.js'), 'utf8'), {document, window, IntersectionObserver: Observer});
    return {videos, document, events, preference, observe(index, visible) {callback([{target: videos[index], isIntersecting: visible, intersectionRatio: visible ? 0.8 : 0}]);}};
}

test('visible video autoplays muted and pauses when it leaves the viewport', () => {
    const h = setup(); h.observe(0, true);
    assert.equal(h.videos[0].paused, false); assert.equal(h.videos[0].muted, true);
    h.observe(0, false); assert.equal(h.videos[0].paused, true);
});
test('manual play pauses another product video and page exit pauses all', () => {
    const h = setup(); h.videos[0].play(); h.videos[1].play();
    assert.equal(h.videos[0].paused, true); assert.equal(h.videos[1].paused, false);
    h.events.pagehide(); assert.ok(h.videos.every(video => video.paused));
});
test('hidden slides and background tabs cannot autoplay', () => {
    const h = setup(); h.videos[0].hiddenSlide = true; h.observe(0, true);
    assert.equal(h.videos[0].paused, true);
    h.videos[0].hiddenSlide = false; h.observe(0, true);
    h.document.hidden = true; h.events.visibilitychange();
    assert.equal(h.videos[0].paused, true);
});
test('reduced motion and data saving disable automatic playback while allowing manual play', () => {
    for (const options of [{reduced: true}, {saveData: true}]) {
        const h = setup(options); h.observe(0, true);
        assert.equal(h.videos[0].paused, true);
        h.videos[0].play(); assert.equal(h.videos[0].paused, false);
    }
});
