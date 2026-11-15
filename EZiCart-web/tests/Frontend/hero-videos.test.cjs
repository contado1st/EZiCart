const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/hero-videos.js', 'utf8');

function setup({ reduced = false, rejectPlay = false, pendingPlay = false } = {}) {
    class Element {
        constructor() {
            this.events = {}; this.dataset = {}; this.hidden = false; this.classes = new Set();
            this.classList = {
                remove: name => this.classes.delete(name),
                toggle: (name, active) => active ? this.classes.add(name) : this.classes.delete(name),
            };
        }
        addEventListener(name, handler) { this.events[name] = handler; }
        getAttribute(name) { return this[name] || null; }
    }
    let resolvePlay; let rejecting = rejectPlay;
    const videos = [0, 1].map(index => {
        const video = new Element(); video.dataset = { src: `clip-${index}.mp4`, poster: `poster-${index}.jpg` };
        video.loads = 0; video.plays = 0; video.paused = true; video.currentTime = 0; video.duration = 8;
        video.load = () => video.loads++;
        video.pause = () => { video.paused = true; };
        video.play = () => {
            video.plays++; video.paused = false;
            if (rejecting) return Promise.reject(new Error('Autoplay blocked'));
            if (pendingPlay) return new Promise(resolve => { resolvePlay = resolve; });
            return Promise.resolve();
        };
        return video;
    });
    const poster = new Element(); poster.src = 'poster-0.jpg';
    const controls = new Element(); const toggle = new Element(); const next = new Element(); const status = new Element();
    controls.querySelector = selector => ({ '[data-hero-video-toggle]': toggle, '[data-hero-video-next]': next, '[data-hero-video-status]': status })[selector];
    const hero = new Element(); hero.querySelector = () => controls;
    hero.getBoundingClientRect = () => ({ top: 0, bottom: 600 });
    const media = new Element(); media.parentElement = hero;
    media.querySelectorAll = () => videos; media.querySelector = () => poster;
    const motion = new Element(); motion.matches = reduced;
    const document = new Element(); document.hidden = false; document.querySelectorAll = () => [media];
    const window = new Element(); window.innerHeight = 900; window.matchMedia = () => motion;
    let intersection;
    window.IntersectionObserver = class { constructor(callback) { intersection = callback; } observe() {} };
    vm.runInNewContext(source, { document, window });
    const visible = value => intersection([{ isIntersecting: value }]);
    const flush = async () => { await Promise.resolve(); await Promise.resolve(); };
    return { videos, poster, controls, toggle, next, status, motion, document, window, visible, flush,
        allowPlay: () => { rejecting = false; }, resolvePlay: () => resolvePlay() };
}

test('hero lazily plays muted clips, prefetches near the end and loops through both', async () => {
    const h = setup(); assert.equal(h.videos[0].loads, 0); assert.equal(h.controls.hidden, false);
    h.visible(true); await h.flush();
    assert.equal(h.videos[0].muted, true); assert.equal(h.videos[0].classes.has('is-active'), true);
    assert.equal(h.videos[1].loads, 0);
    h.videos[0].currentTime = 6.5; h.videos[0].events.timeupdate();
    assert.equal(h.videos[1].loads, 1); assert.equal(h.videos[1].plays, 0);
    h.videos[0].events.ended(); await h.flush();
    assert.equal(h.videos[1].classes.has('is-active'), true); assert.equal(h.videos[0].paused, true);
    h.videos[1].events.ended(); await h.flush();
    assert.equal(h.videos[0].classes.has('is-active'), true); assert.equal(h.videos[0].currentTime, 0);
});

test('pause persists through scrolling and hidden pages stop playback', async () => {
    const h = setup(); h.visible(true); await h.flush(); h.toggle.events.click();
    assert.equal(h.toggle.textContent, 'Play video'); assert.equal(h.videos[0].paused, true);
    h.visible(false); h.visible(true); await h.flush(); assert.equal(h.videos[0].paused, true);
    h.toggle.events.click(); await h.flush(); assert.equal(h.videos[0].paused, false);
    h.document.hidden = true; h.document.events.visibilitychange(); assert.equal(h.videos[0].paused, true);
    h.document.hidden = false; h.document.events.visibilitychange(); await h.flush();
    assert.equal(h.videos[0].paused, false);
    h.window.events.pagehide(); assert.equal(h.videos[0].paused, true);
});

test('reduced motion uses posters without loading video and allows manual scene selection', async () => {
    const h = setup({ reduced: true }); h.visible(true); await h.flush();
    assert.equal(h.videos[0].loads, 0); assert.equal(h.toggle.hidden, true);
    h.next.events.click(); await h.flush();
    assert.equal(h.poster.src, 'poster-1.jpg'); assert.equal(h.status.textContent, 'Scene 2 of 2');
    assert.equal(h.videos[1].loads, 0);
    h.motion.matches = false; h.motion.events.change(); await h.flush();
    assert.equal(h.videos[1].classes.has('is-active'), true);
    h.motion.matches = true; h.motion.events.change();
    assert.equal(h.videos[1].paused, true); assert.equal(h.videos[1].classes.has('is-active'), false);
});

test('blocked autoplay keeps a poster and provides a working play retry', async () => {
    const h = setup({ rejectPlay: true }); h.visible(true); await h.flush();
    assert.equal(h.toggle.textContent, 'Play video'); assert.equal(h.poster.src, 'poster-0.jpg');
    assert.equal(h.videos[0].classes.has('is-active'), false);
    h.allowPlay(); h.toggle.events.click(); await h.flush();
    assert.equal(h.videos[0].classes.has('is-active'), true);
});

test('a late play promise cannot resume a hero after it leaves the viewport', async () => {
    const h = setup({ pendingPlay: true }); h.visible(true); h.visible(false);
    h.resolvePlay(); await h.flush();
    assert.equal(h.videos[0].paused, true); assert.equal(h.videos[0].classes.has('is-active'), false);
});
