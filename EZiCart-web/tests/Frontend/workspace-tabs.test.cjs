const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/workspace-tabs.js', 'utf8');

class Element {
    constructor(id = '') { this.id = id; this.attributes = {}; this.dataset = {}; this.events = {}; this.children = []; }
    setAttribute(name, value) { this.attributes[name] = value; }
    getAttribute(name) { return this.attributes[name] ?? null; }
    hasAttribute(name) { return name in this.attributes; }
    addEventListener(name, handler) { this.events[name] = handler; }
    contains(target) { return this.children.includes(target); }
    focus() { this.focused = true; }
    querySelectorAll() { return []; }
    querySelector() { return null; }
    closest() { return this; }
    click(options = {}) {
        const event = {button: 0, preventDefault() { this.defaultPrevented = true; }, ...options};
        this.events.click(event);
        return event;
    }
    key(key) { this.events.keydown({key, preventDefault() {}}); }
}

function setup({query = '', hash = '', defaultTab = '', errorPanel = '', incomplete = false} = {}) {
    const location = new URL(`https://shop.example.test/courier/dashboard${query}${hash}`);
    const windowEvents = {}, documentEvents = {}, pushes = [];
    const parentClasses = new Set();
    const parent = {matches: () => true, classList: {add: name => parentClasses.add(name)}};
    const panels = ['pickups', 'deliveries'].map(id => Object.assign(new Element(id), {parentElement: parent}));
    const links = panels.map(panel => {
        const link = new Element(`tab-${panel.id}`);
        link.dataset.workspaceTab = panel.id;
        return link;
    });
    const input = new Element('parcel-code');
    panels[1].children.push(input);
    const pagination = {href: 'https://shop.example.test/courier/dashboard?deliveries=2&__tab=account-token&search=parcel'};
    const form = Object.assign(new Element(), {getAttribute: () => 'get', appendChild(input) {this.input = input;}});
    panels.forEach((panel, index) => {
        panel.querySelectorAll = selector => selector === 'form' ? [form] : index === 1 ? [pagination] : [];
        panel.querySelector = () => panel.id === errorPanel ? input : null;
    });
    const navigation = Object.assign(new Element(), {dataset: {defaultTab}, querySelectorAll: () => links});
    const document = {
        querySelectorAll: () => [navigation],
        getElementById: id => [...panels.filter((_, index) => !(incomplete && index === 1)), input].find(element => element.id === id) || null,
        createElement: () => new Element(),
        addEventListener: (event, handler) => documentEvents[event] = handler,
    };
    const window = {
        location,
        history: {pushState(state, title, href) {pushes.push(href); location.href = href;}},
        addEventListener: (event, handler) => windowEvents[event] = handler,
    };
    vm.runInNewContext(source, {document, window, URL});
    return {panels, links, input, pagination, form, navigation, location, parentClasses, windowEvents, documentEvents, pushes, document};
}

test('shows the default queue with one keyboard tab stop and matching accessible panel', () => {
    const ui = setup({defaultTab: 'deliveries'});
    assert.equal(ui.panels[0].hidden, true);
    assert.equal(ui.panels[1].hidden, false);
    assert.equal(ui.links[1].tabIndex, 0);
    assert.equal(ui.links[0].tabIndex, -1);
    assert.equal(ui.links[1].attributes['aria-controls'], 'deliveries');
    assert.equal(ui.panels[1].attributes['aria-labelledby'], 'tab-deliveries');
    assert.equal(ui.parentClasses.has('workspace-tab-layout'), true);
});

test('clicking a tab opens its queue and keeps the existing account and filter URL', () => {
    const ui = setup({query: '?__tab=account-token&search=parcel'});
    assert.equal(ui.links[1].click().defaultPrevented, true);
    assert.equal(ui.panels[1].hidden, false);
    assert.equal(ui.location.searchParams.get('__tab'), 'account-token');
    assert.equal(ui.location.searchParams.get('search'), 'parcel');
    assert.equal(ui.location.searchParams.get('workspace_tab'), 'deliveries');
    assert.equal(ui.location.hash, '#deliveries');
});

test('reload restores a query-selected tab and a direct field fragment opens its owning panel', () => {
    assert.equal(setup({query: '?workspace_tab=deliveries'}).panels[1].hidden, false);
    assert.equal(setup({hash: '#parcel-code', query: '?workspace_tab=pickups'}).panels[1].hidden, false);
    assert.equal(setup({hash: '#%E0%A4%A'}).panels[0].hidden, false);
});

test('arrow, Home and End keys change tabs, wrap and move focus', () => {
    const ui = setup();
    ui.links[0].key('ArrowLeft');
    assert.equal(ui.panels[1].hidden, false);
    assert.equal(ui.links[1].focused, true);
    ui.links[1].key('Home');
    assert.equal(ui.panels[0].hidden, false);
    ui.links[0].key('End');
    assert.equal(ui.panels[1].hidden, false);
    ui.links[1].key('ArrowRight');
    assert.equal(ui.panels[0].hidden, false);
});

test('browser back restores the previous tab from the URL', () => {
    const ui = setup();
    ui.links[1].click();
    ui.location.href = 'https://shop.example.test/courier/dashboard?workspace_tab=pickups#pickups';
    ui.windowEvents.popstate();
    assert.equal(ui.panels[0].hidden, false);
    assert.equal(ui.links[0].attributes['aria-selected'], 'true');
});

test('pagination and filter submissions retain their panel without losing account parameters', () => {
    const ui = setup();
    const url = new URL(ui.pagination.href);
    assert.equal(url.searchParams.get('deliveries'), '2');
    assert.equal(url.searchParams.get('__tab'), 'account-token');
    assert.equal(url.searchParams.get('search'), 'parcel');
    assert.equal(url.searchParams.get('workspace_tab'), 'deliveries');
    assert.equal(url.hash, '#deliveries');
    assert.equal(ui.form.input.name, 'workspace_tab');
    assert.equal(ui.form.input.type, 'hidden');
});

test('an existing workflow anchor reveals a hidden field before browser scrolling', () => {
    const ui = setup();
    const anchor = Object.assign(new Element(), {href: 'https://shop.example.test/courier/dashboard#parcel-code'});
    ui.documentEvents.click({target: anchor});
    assert.equal(ui.panels[1].hidden, false);
});

test('invalid fields and server validation errors reveal the corresponding panel', () => {
    const ui = setup();
    ui.documentEvents.invalid({target: ui.input});
    assert.equal(ui.panels[1].hidden, false);
    assert.equal(setup({errorPanel: 'deliveries'}).panels[1].hidden, false);
    assert.equal(setup({errorPanel: 'deliveries', hash: '#pickups'}).panels[1].hidden, false);
    ui.document.activeElement = ui.input;
    ui.links[0].click();
    assert.equal(ui.links[0].focused, true);
});

test('modified clicks keep standard browser new-tab behavior', () => {
    const ui = setup();
    assert.equal(ui.links[1].click({ctrlKey: true}).defaultPrevented, undefined);
    assert.equal(ui.pushes.length, 0);
});

test('missing panel markup leaves the document visible and ordinary navigation usable', () => {
    const ui = setup({incomplete: true});
    assert.equal(ui.panels[0].hidden, undefined);
    assert.equal(ui.panels[1].hidden, undefined);
    assert.equal(ui.navigation.attributes.role, undefined);
});
