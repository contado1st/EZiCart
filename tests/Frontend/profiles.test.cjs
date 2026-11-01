const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/profiles.js', 'utf8');

function setup() {
    class Node {
        constructor() { this.events = {}; this.value = ''; this.files = []; this.hidden = false; this.checked = false; this.dataset = {}; this.attrs = new Map(); this.textContent = ''; }
        addEventListener(type, fn) { this.events[type] = fn; }
        getAttribute(name) { return this.attrs.get(name) ?? null; }
        set src(value) { this.attrs.set('src', value); }
        removeAttribute(name) { this.attrs.delete(name); }
        setCustomValidity(message) { this.validityMessage = message; }
        reportValidity() { this.validityReported = true; }
    }
    const name = new Node(); name.textContent = 'Original shop';
    const nameInput = new Node(); const theme = new Node(); theme.value = 'rose'; const status = new Node(); const fallback = new Node();
    const uploads = {}; const images = {}; const removes = {};
    for(const type of ['avatar','banner']) {
        uploads[type] = new Node(); uploads[type].dataset.profileUpload = type;
        images[type] = new Node(); images[type].src = '/existing-'+type;
        removes[type] = new Node();
    }
    const classes = new Set(); const editor = {
        classList: {toggle: (name, active) => active ? classes.add(name) : classes.delete(name)},
        querySelector(selector) {
            if(selector === '[data-profile-name]') return name;
            if(selector === '[data-profile-name-input]') return nameInput;
            if(selector === '[data-profile-theme]') return theme;
            if(selector === '[data-profile-preview-status]') return status;
            if(selector === '[data-profile-avatar-fallback]') return fallback;
            for(const type of ['avatar','banner']) {
                if(selector === `[data-profile-preview="${type}"]`) return images[type];
                if(selector === `[data-profile-remove="${type}"]`) return removes[type];
            }
        },
        querySelectorAll: () => Object.values(uploads),
    };
    let next = 0; const revoked = []; const events = {};
    vm.runInNewContext(source, {document: {querySelector: () => editor}, window: {addEventListener: (type, fn) => events[type] = fn}, URL: {createObjectURL: () => 'blob:preview-'+(++next), revokeObjectURL: url => revoked.push(url)}});
    return {name, nameInput, theme, classes, uploads, images, removes, fallback, status, revoked, events};
}

test('theme and display-name previews are immediate and treat entered markup as text', () => {
    const h = setup(); h.theme.value = 'teal'; h.theme.events.change();
    assert.deepEqual([...h.classes], ['profile-theme-teal']);
    h.nameInput.events.input({target: {value: '<script>literal</script>'}});
    assert.equal(h.name.textContent, '<script>literal</script>');
    h.nameInput.events.input({target: {value: ''}}); assert.equal(h.name.textContent, 'Original shop');
});

test('valid image preview clears removal and revokes superseded blobs', () => {
    const h = setup(); const input = h.uploads.avatar; h.removes.avatar.checked = true;
    input.files = [{size: 1000, type: 'image/png'}]; input.events.change();
    assert.equal(h.images.avatar.getAttribute('src'), 'blob:preview-1'); assert.equal(h.removes.avatar.checked, false); assert.equal(h.fallback.hidden, true);
    input.events.change(); assert.deepEqual(h.revoked, ['blob:preview-1']);
    h.events.pagehide(); assert.deepEqual(h.revoked, ['blob:preview-1','blob:preview-2']);
});

test('unsafe or oversized files are rejected before creating a preview', () => {
    for(const [type, file] of [['avatar', {size: 1000, type: 'image/svg+xml'}], ['avatar', {size: 3*1024*1024, type: 'image/png'}], ['banner', {size: 3*1024*1024, type: 'image/jpeg'}]]) {
        const h = setup(); h.uploads[type].files = [file]; h.uploads[type].events.change();
        assert.equal(h.uploads[type].validityReported, true); assert.equal(h.images[type].getAttribute('src'), '/existing-'+type);
    }
});

test('removal preview can be cancelled without deleting the saved image', () => {
    const h = setup(); h.removes.avatar.checked = true; h.removes.avatar.events.change();
    assert.equal(h.images.avatar.hidden, true); assert.equal(h.fallback.hidden, false);
    h.removes.avatar.checked = false; h.removes.avatar.events.change();
    assert.equal(h.images.avatar.getAttribute('src'), '/existing-avatar'); assert.equal(h.images.avatar.hidden, false);
});
