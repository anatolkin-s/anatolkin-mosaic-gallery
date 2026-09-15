'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../Resources/Public/Js/mosaic-init.js'), 'utf8');
function fixture(mode, cachedBatch = false) {
  const frames = new Map(), timers = new Map();
  let sequence = 0, masonryLayouts = 0, masonryCreated = 0, requests = 0;
  class Events {
    constructor() { this.listeners = {}; }
    addEventListener(type, fn) { (this.listeners[type] ??= new Set()).add(fn); }
    removeEventListener(type, fn) { this.listeners[type]?.delete(fn); }
    emit(type, target = this) { for (const fn of [...(this.listeners[type] ?? [])]) fn({ target }); }
  }
  const classList = (...values) => {
    const set = new Set(values);
    return { add: value => set.add(value), remove: value => set.delete(value), contains: value => set.has(value) };
  };
  const grid = new Events();
  grid.style = {}; grid.clientWidth = 1200;
  const items = Array.from({ length: 4 }, (_, index) => {
    const item = { style: {}, classList: classList(...(index >= 2 ? ['is-hidden'] : [])), scrollHeight: 120,
      getBoundingClientRect: () => ({ height: 120 }),
      getAttribute: name => name === 'data-aspect-ratio' ? '2' : 'medium' };
    const img = new Events();
    img.tagName = 'IMG'; img.complete = index === 1 || index >= 2; img.loading = 'lazy';
    img.attrs = { src: index >= 2 ? 'placeholder' : `initial-${index}`, ...(index >= 2 ? { 'data-src': `deferred-${index}` } : {}) };
    img.getAttribute = name => img.attrs[name] ?? null;
    img.setAttribute = (name, value) => { img.attrs[name] = value; if (name === 'src') { requests++; img.complete = cachedBatch; } };
    img.removeAttribute = name => delete img.attrs[name];
    img.closest = () => item;
    Object.defineProperty(img, 'naturalWidth', { get() { throw new Error('Known geometry must precede naturalWidth'); } });
    item.querySelector = selector => selector === 'img' ? img : null;
    item.querySelectorAll = selector => selector === 'img' || (selector === 'img[data-src]' && img.attrs['data-src']) ? [img] : [];
    item.img = img;
    return item;
  });
  grid.querySelectorAll = selector => selector.includes(':not') ? items.filter(i => !i.classList.contains('is-hidden'))
    : selector.includes('.is-hidden') ? items.filter(i => i.classList.contains('is-hidden')) : items;
  grid.querySelector = selector => selector === '.mosaic-sizer' ? {} : grid.querySelectorAll(selector)[0] ?? null;
  const button = new Events(); button.attrs = {}; button.removed = false;
  button.setAttribute = (name, value) => { button.attrs[name] = value; };
  button.removeAttribute = name => delete button.attrs[name];
  button.remove = () => { button.removed = true; };
  const container = new Events(); container.classList = classList('is-layout-pending');
  container.style = { getPropertyValue: () => '12' };
  container.getAttribute = name => ({ 'data-layout-mode': mode, 'data-step': '1', 'data-lightbox': '0' }[name] ?? null);
  container.querySelector = selector => selector === '.mosaic-grid' ? grid : selector === '.mosaic-load-more' ? button : null;
  const document = new Events(); document.querySelectorAll = () => [container];
  const window = new Events();
  window.imagesLoaded = () => { throw new Error('imagesLoaded must never be invoked'); };
  window.Masonry = function () { masonryCreated++; this.layout = () => masonryLayouts++; };
  window.getComputedStyle = () => ({ gridAutoRows: '4' });
  window.requestAnimationFrame = fn => { frames.set(++sequence, fn); return sequence; };
  vm.runInNewContext(source, { document, window,
    Image: function () { throw new Error('Proxy Image construction is forbidden'); },
    setTimeout: fn => { timers.set(++sequence, fn); return sequence; }, clearTimeout: id => timers.delete(id),
    setInterval: () => { throw new Error('Unexpected interval'); }, clearInterval() {},
  });
  document.emit('DOMContentLoaded');
  const complete = (index, type) => { const img = items[index].img; img.complete = true; grid.emit(type, img); img.emit(type); };
  const flushFrames = () => { const callbacks = [...frames.values()]; frames.clear(); callbacks.forEach(fn => fn()); };
  return { container, grid, button, items, frames, timers, complete, flushFrames,
    requests: () => requests, layouts: () => masonryLayouts, created: () => masonryCreated };
}
for (const mode of ['masonry', 'mosaic', 'patterned', 'justified', 'grid']) {
  const f = fixture(mode);
  assert.equal(f.items[0].img.complete, false);
  assert.ok(f.container.classList.contains('is-layout-ready'), `${mode}: ready before images load`);
  assert.equal(f.requests(), 0, `${mode}: no startup src promotion`);
  assert.equal(f.timers.size, 0, `${mode}: no initial image wait timeout`);
  if (['masonry', 'mosaic'].includes(mode)) { assert.equal(f.created(), 1); assert.equal(f.layouts(), 1); }
  if (mode === 'justified') assert.ok(f.items[0].style.width);
  if (mode === 'patterned') assert.ok(f.items[0].style.gridRowEnd);
  f.complete(0, 'load'); f.complete(1, 'error');
  assert.equal(f.frames.size, 1, `${mode}: coalesced load/error relayout`);
  f.flushFrames();
  if (['masonry', 'mosaic'].includes(mode)) assert.equal(f.layouts(), 2);
  f.button.emit('click');
  assert.equal(f.requests(), 1);
  assert.equal(f.items[2].img.attrs.src, 'deferred-2');
  assert.equal(f.items[3].img.attrs.src, 'placeholder');
  assert.equal(f.button.disabled, true);
  f.button.emit('click'); assert.equal(f.requests(), 1, 'Busy button cannot activate another batch');
  f.complete(2, 'load');
  assert.equal(f.items[2].classList.contains('is-hidden'), false);
  assert.equal(f.button.disabled, false);
  assert.equal(f.items[2].img.listeners.error.size, 0, 'Native waiter cleans listeners');
  f.button.emit('click'); f.complete(3, 'error');
  assert.equal(f.requests(), 2);
  assert.equal(f.items[3].classList.contains('is-hidden'), false);
  assert.equal(f.button.removed, true);
  assert.equal(f.timers.size, 0);
}
const cached = fixture('masonry', true); cached.button.emit('click');
assert.equal(cached.items[2].classList.contains('is-hidden'), false, 'Cached batch completes immediately');
const timeout = fixture('mosaic'); timeout.button.emit('click');
[...timeout.timers.values()].forEach(fn => fn());
assert.equal(timeout.items[2].classList.contains('is-hidden'), false, 'Timeout cannot leave batch blocked');
assert.equal(timeout.items[2].img.listeners.load.size, 0, 'Timeout cleans listeners');
assert.ok(!source.includes('window.imagesLoaded'));
console.log('Native lazy initialization, geometry, coalesced events and repeated Load More across five layouts: PASS');
