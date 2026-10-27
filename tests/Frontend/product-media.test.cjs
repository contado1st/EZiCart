const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/product-media.js', 'utf8');

function setup() {
    class Element {
        constructor() { this.events = {}; this.hidden = false; this.attrs = {}; this.dataset = {}; this.textContent = ''; }
        addEventListener(name, handler) { this.events[name] = handler; }
        setAttribute(name, value) { this.attrs[name] = value; }
        focus() { this.focused = true; }
        closest(selector) { return selector === 'video' && this.isVideo ? this : null; }
    }
    const video = new Element(); video.isVideo = true; video.pauses = 0; video.pause = () => video.pauses++;
    const slides = ['image', 'video', 'image'].map((kind) => {
        const slide = new Element(); slide.dataset.kind = kind;
        slide.querySelectorAll = () => kind === 'video' ? [video] : [];
        return slide;
    });
    const thumbnails = slides.map(() => new Element());
    const status = new Element(); const track = new Element(); const previous = new Element(); const next = new Element();
    const controls = new Element(); const thumbnailGroup = new Element(); const classes = new Set(); const gallery = new Element();
    gallery.classList = { add: (value) => classes.add(value) };
    gallery.querySelectorAll = (selector) => selector === '[data-gallery-slide]' ? slides : thumbnails;
    gallery.querySelector = (selector) => ({ '[data-gallery-status]': status, '[data-gallery-track]': track, '[data-gallery-previous]': previous, '[data-gallery-next]': next, '[data-gallery-controls]': controls, '[data-gallery-thumbnails]': thumbnailGroup })[selector];
    const windowEvents = {};
    vm.runInNewContext(source, { document: { querySelectorAll: (selector) => selector === '[data-product-gallery]' ? [gallery] : [] }, window: { addEventListener: (type, handler) => windowEvents[type] = handler } });
    return { slides, video, thumbnails, status, track, previous, next, gallery, classes, windowEvents, Element };
}

test('gallery selection wraps, updates visible media and announces thumbnail selection', () => {
    const h = setup();
    assert.equal(h.slides[0].hidden, false);
    assert.equal(h.slides[1].hidden, true);
    h.previous.events.click(); assert.equal(h.slides[2].hidden, false);
    h.next.events.click(); assert.equal(h.slides[0].hidden, false);
    h.thumbnails[1].events.click();
    assert.equal(h.slides[1].hidden, false);
    assert.equal(h.thumbnails[1].attrs['aria-pressed'], 'true');
    assert.equal(h.thumbnails[0].attrs['aria-pressed'], 'false');
    assert.equal(h.status.textContent, '2 / 3 · Video');
});

test('leaving a video and leaving the page pauses playback while video keyboard controls keep working', () => {
    const h = setup(); h.thumbnails[1].events.click();
    let prevented = false;
    h.gallery.events.keydown({ target: h.video, key: 'ArrowRight', preventDefault: () => prevented = true });
    assert.equal(prevented, false); assert.equal(h.slides[1].hidden, false);
    const before = h.video.pauses; h.next.events.click();
    assert.equal(h.video.pauses, before + 1);
    h.windowEvents.pagehide(); assert.equal(h.video.pauses, before + 2);
});

test('keyboard navigation and horizontal touch swipe select media without hijacking vertical scrolling', () => {
    const h = setup(); const image = new h.Element();
    let prevented = false;
    h.gallery.events.keydown({ target: image, key: 'End', preventDefault: () => prevented = true });
    assert.equal(prevented, true); assert.equal(h.slides[2].hidden, false);
    h.track.events.pointerdown({ target: image, pointerType: 'touch', clientX: 100, clientY: 100 });
    h.track.events.pointerup({ clientX: 30, clientY: 110 }); assert.equal(h.slides[0].hidden, false);
    h.track.events.pointerdown({ target: image, pointerType: 'touch', clientX: 100, clientY: 100 });
    h.track.events.pointerup({ clientX: 95, clientY: 190 }); assert.equal(h.slides[0].hidden, false);
});
