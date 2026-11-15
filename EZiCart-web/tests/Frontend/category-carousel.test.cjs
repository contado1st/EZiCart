const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/category-carousel.js', 'utf8');

function setup({ reduced = false, overflow = true } = {}) {
    class Element {
        constructor() { this.events = {}; this.hidden = false; this.dataset = {}; this.attrs = {}; }
        addEventListener(type, handler) { this.events[type] = handler; }
        setAttribute(name, value) { this.attrs[name] = value; }
        get offsetLeft() { return track.children.indexOf(this) * 210; }
        cloneNode() { return new Element(); }
        remove() { track.children.splice(track.children.indexOf(this), 1); }
    }
    const track = new Element();
    track.children = Array.from({ length: 5 }, () => new Element());
    track.clientWidth = overflow ? 620 : 1100;
    Object.defineProperty(track, 'scrollWidth', { get: () => track.children.length * 210 - 10 });
    track.append = child => track.children.push(child);
    track.scrollLeft = 0;
    track.moves = [];
    track.scrollTo = options => { track.moves.push(options); track.scrollLeft = options.left; };
    const controls = new Element(); const toggle = new Element();
    const previous = new Element(); const next = new Element();
    const carousel = new Element();
    carousel.querySelector = selector => ({
        '[data-carousel-track]': track, '[data-carousel-controls]': controls,
        '[data-carousel-toggle]': toggle, '[data-carousel-previous]': previous, '[data-carousel-next]': next,
    })[selector];
    carousel.contains = target => target === next || target === track;
    carousel.getBoundingClientRect = () => ({ top: 20, bottom: 400 });
    const motion = new Element(); motion.matches = reduced;
    const timers = new Map(); const frames = new Map(); let counter = 0; let observer; let now = 0;
    const document = new Element(); document.hidden = false; document.querySelectorAll = () => [carousel];
    const window = new Element();
    window.innerHeight = 800;
    window.matchMedia = () => motion;
    window.setTimeout = (fn, ms) => { timers.set(++counter, { fn, ms }); return counter; };
    window.clearTimeout = id => timers.delete(id);
    window.requestAnimationFrame = fn => { frames.set(++counter, fn); return counter; };
    window.cancelAnimationFrame = id => frames.delete(id);
    window.IntersectionObserver = class { constructor(callback) { observer = callback; } observe() {} };
    vm.runInNewContext(source, { document, window });
    const visible = value => observer([{ isIntersecting: value }]);
    const step = (ms = 16) => {
        now += ms;
        const pending = [...frames.values()]; frames.clear();
        pending.forEach(callback => callback(now));
    };
    const idle = () => {
        const pending = [...timers.values()]; timers.clear();
        pending.forEach(timer => { assert.equal(timer.ms, 800); timer.fn(); });
    };
    return { track, controls, toggle, previous, next, carousel, motion, document, window, timers, frames, visible, step, idle };
}

test('visible idle carousel moves on every animation frame without a slide interval', () => {
    const h = setup(); assert.equal(h.frames.size, 0);
    h.visible(true); h.step();
    h.step(); const first = h.track.scrollLeft;
    h.step(); const second = h.track.scrollLeft;
    assert.ok(first > 0 && first < 1);
    assert.ok(second > first && second < 2);
    assert.equal(h.timers.size, 0);
    assert.equal(h.track.moves.length, 0);
    assert.equal(h.frames.size, 1);
});

test('loop crosses the final category into an identical copy without an animated rewind', () => {
    const h = setup(); h.visible(true);
    assert.equal(h.track.children.length, 10);
    for (const copy of h.track.children.slice(5)) {
        assert.equal(copy.attrs['aria-hidden'], 'true');
        assert.equal(copy.attrs.tabindex, '-1');
    }
    h.carousel.events.mouseenter(); h.track.scrollLeft = 1049.8;
    h.carousel.events.mouseleave(); h.idle(); h.step(); h.step();
    assert.ok(h.track.scrollLeft > 0 && h.track.scrollLeft < 1);
    assert.equal(h.track.moves.length, 0);
    h.previous.events.click();
    assert.equal(h.track.scrollLeft, 840);
    assert.equal(h.track.moves.at(-1).behavior, 'smooth');
});

test('hover, keyboard focus and touch interruption suspend motion until idle', () => {
    const h = setup(); h.visible(true);
    h.carousel.events.mouseenter(); assert.equal(h.frames.size, 0);
    h.carousel.events.mouseleave(); assert.equal(h.frames.size, 0); h.idle(); assert.equal(h.frames.size, 1);
    h.carousel.events.focusin(); assert.equal(h.frames.size, 0);
    h.carousel.events.focusout({ relatedTarget: h.next }); assert.equal(h.timers.size, 0);
    h.carousel.events.focusout({ relatedTarget: null }); h.idle(); assert.equal(h.frames.size, 1);
    h.track.events.pointerdown(); assert.equal(h.frames.size, 0);
    h.window.events.pointercancel(); h.idle(); assert.equal(h.frames.size, 1);
});

test('explicit pause persists through visibility changes, and hidden pages stop frames', () => {
    const h = setup(); h.visible(true); h.toggle.events.click();
    assert.equal(h.toggle.textContent, 'Resume rotation'); assert.equal(h.frames.size, 0);
    h.visible(false); h.visible(true); assert.equal(h.frames.size, 0);
    h.toggle.events.click(); assert.equal(h.toggle.textContent, 'Pause rotation'); assert.equal(h.frames.size, 1);
    h.document.hidden = true; h.document.events.visibilitychange(); assert.equal(h.frames.size, 0);
    h.document.hidden = false; h.document.events.visibilitychange(); assert.equal(h.frames.size, 1);
    h.window.events.pagehide(); assert.equal(h.frames.size, 0);
    h.window.events.pageshow(); assert.equal(h.frames.size, 1); assert.equal(h.track.children.length, 10);
    h.visible(false); assert.equal(h.frames.size, 0);
});

test('reduced motion disables the loop and copies while preserving manual navigation', () => {
    const h = setup({ reduced: true }); h.visible(true);
    assert.equal(h.frames.size, 0); assert.equal(h.toggle.hidden, true); assert.equal(h.track.children.length, 5);
    h.next.events.click(); assert.equal(h.track.scrollLeft, 210); assert.equal(h.track.moves[0].behavior, 'auto');
    h.motion.matches = false; h.motion.events.change(); assert.equal(h.frames.size, 1); assert.equal(h.track.children.length, 10);
    h.motion.matches = true; h.motion.events.change(); assert.equal(h.frames.size, 0); assert.equal(h.track.children.length, 5);
});

test('fitting categories do not duplicate or rotate; resize rebuilds exactly one copy set', () => {
    const h = setup({ overflow: false }); h.visible(true);
    assert.equal(h.controls.hidden, true); assert.equal(h.frames.size, 0); assert.equal(h.track.children.length, 5);
    h.track.clientWidth = 620; h.window.events.resize();
    assert.equal(h.controls.hidden, false); assert.equal(h.frames.size, 1); assert.equal(h.track.children.length, 10);
    h.window.events.resize(); assert.equal(h.track.children.length, 10); assert.equal(h.frames.size, 1);
    h.track.clientWidth = 1100; h.window.events.resize();
    assert.equal(h.track.children.length, 5); assert.equal(h.frames.size, 0);
});
