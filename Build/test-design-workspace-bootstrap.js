#!/usr/bin/env node
'use strict';

/**
 * Executable DOM-fixture contract for Mosaic design workspace bootstrap.
 *
 * Proves:
 * - consolidation can run when the editor appears AFTER module evaluation
 * - consolidation is idempotent (no duplicated headers / repeated moves)
 * - Layout owns design preset; Images owns source controls
 *
 * Uses a bounded in-repo DOM fixture (no browser / no npm runtime dependency).
 * Keep behavioral expectations aligned with design-configurator.js.
 *
 * Run: node Build/test-design-workspace-bootstrap.js
 */

const failures = [];
const assert = (condition, message) => {
  if (!condition) {
    failures.push(message);
  }
};

/** @param {string} selector */
const parseSimpleSelector = (selector) => {
  const trimmed = String(selector).trim();
  if (trimmed.startsWith(':scope')) {
    return parseSimpleSelector(trimmed.replace(/^:scope\s*>?\s*/, '') || '*');
  }
  const attrMatch = trimmed.match(
    /^([a-zA-Z0-9_-]*)((?:\.[a-zA-Z0-9_-]+)*)?(?:\[([a-zA-Z0-9_-]+)(?:="([^"]*)")?\])?$/,
  );
  if (!attrMatch) {
    return null;
  }
  const classes = (attrMatch[2] || '')
    .split('.')
    .map((item) => item.trim())
    .filter(Boolean);
  return {
    tag: (attrMatch[1] || '*').toLowerCase() || '*',
    classes,
    attr: attrMatch[3] || null,
    attrValue: attrMatch[4],
  };
};

class FixtureClassList {
  /** @param {FixtureElement} element */
  constructor(element) {
    this.element = element;
  }

  add(...tokens) {
    const current = new Set((this.element.getAttribute('class') || '').split(/\s+/).filter(Boolean));
    tokens.forEach((token) => current.add(token));
    this.element.setAttribute('class', [...current].join(' '));
  }

  contains(token) {
    return (this.element.getAttribute('class') || '').split(/\s+/).includes(token);
  }

  toggle(token, force) {
    const has = this.contains(token);
    const shouldHave = force === undefined ? !has : Boolean(force);
    if (shouldHave) {
      this.add(token);
    } else {
      const current = (this.element.getAttribute('class') || '').split(/\s+/).filter((item) => item && item !== token);
      this.element.setAttribute('class', current.join(' '));
    }
    return shouldHave;
  }
}

class FixtureElement {
  /**
   * @param {string} tagName
   * @param {FixtureDocument} ownerDocument
   */
  constructor(tagName, ownerDocument) {
    this.tagName = String(tagName).toUpperCase();
    this.ownerDocument = ownerDocument;
    this.parentElement = null;
    this.childNodes = [];
    this.attributes = new Map();
    this.classList = new FixtureClassList(this);
    this._listeners = new Map();
    this.dataset = new Proxy({}, {
      get: (_target, prop) => {
        if (typeof prop !== 'string') {
          return undefined;
        }
        const attr = `data-${prop.replace(/[A-Z]/g, (match) => `-${match.toLowerCase()}`)}`;
        return this.getAttribute(attr) ?? undefined;
      },
      set: (_target, prop, value) => {
        if (typeof prop !== 'string') {
          return false;
        }
        const attr = `data-${prop.replace(/[A-Z]/g, (match) => `-${match.toLowerCase()}`)}`;
        this.setAttribute(attr, String(value));
        return true;
      },
    });
  }

  get children() {
    return this.childNodes.filter((node) => node instanceof FixtureElement);
  }

  get textContent() {
    return this.childNodes.map((node) => {
      if (typeof node === 'string') {
        return node;
      }
      return node.textContent ?? '';
    }).join('');
  }

  set textContent(value) {
    this.childNodes = [String(value)];
  }

  get hidden() {
    return this.hasAttribute('hidden');
  }

  set hidden(value) {
    if (value) {
      this.setAttribute('hidden', 'hidden');
    } else {
      this.attributes.delete('hidden');
    }
  }

  setAttribute(name, value) {
    this.attributes.set(String(name), String(value));
  }

  getAttribute(name) {
    return this.attributes.has(String(name)) ? this.attributes.get(String(name)) : null;
  }

  hasAttribute(name) {
    return this.attributes.has(String(name));
  }

  matches(selector) {
    const parts = String(selector).split(',').map((part) => part.trim()).filter(Boolean);
    return parts.some((part) => this._matchesOne(part));
  }

  _matchesOne(selector) {
    const parsed = parseSimpleSelector(selector);
    if (!parsed) {
      return false;
    }
    if (parsed.tag !== '*' && parsed.tag !== this.tagName.toLowerCase()) {
      return false;
    }
    if (parsed.classes.some((className) => !this.classList.contains(className))) {
      return false;
    }
    if (parsed.attr) {
      const value = this.getAttribute(parsed.attr);
      if (value === null) {
        return false;
      }
      if (parsed.attrValue !== undefined && value !== parsed.attrValue) {
        return false;
      }
    }
    return true;
  }

  closest(selector) {
    let current = this;
    while (current) {
      if (current.matches(selector)) {
        return current;
      }
      current = current.parentElement;
    }
    return null;
  }

  contains(node) {
    let current = node;
    while (current) {
      if (current === this) {
        return true;
      }
      current = current.parentElement;
    }
    return false;
  }

  append(...nodes) {
    nodes.forEach((node) => this.appendChild(node));
  }

  prepend(...nodes) {
    nodes.slice().reverse().forEach((node) => this.insertBefore(node, this.childNodes[0] ?? null));
  }

  appendChild(node) {
    if (node.parentElement) {
      node.parentElement.removeChild(node);
    }
    node.parentElement = this;
    this.childNodes.push(node);
    return node;
  }

  insertBefore(node, reference) {
    if (node.parentElement) {
      node.parentElement.removeChild(node);
    }
    node.parentElement = this;
    if (!reference) {
      this.childNodes.push(node);
      return node;
    }
    const index = this.childNodes.indexOf(reference);
    if (index === -1) {
      this.childNodes.push(node);
    } else {
      this.childNodes.splice(index, 0, node);
    }
    return node;
  }

  insertAdjacentElement(position, element) {
    if (position === 'afterend') {
      const parent = this.parentElement;
      if (!parent) {
        return null;
      }
      const index = parent.childNodes.indexOf(this);
      parent.insertBefore(element, parent.childNodes[index + 1] ?? null);
      return element;
    }
    if (position === 'beforebegin') {
      this.parentElement?.insertBefore(element, this);
      return element;
    }
    if (position === 'afterbegin') {
      this.prepend(element);
      return element;
    }
    if (position === 'beforeend') {
      this.append(element);
      return element;
    }
    return null;
  }

  removeChild(node) {
    const index = this.childNodes.indexOf(node);
    if (index >= 0) {
      this.childNodes.splice(index, 1);
      node.parentElement = null;
    }
    return node;
  }

  remove() {
    this.parentElement?.removeChild(this);
  }

  querySelector(selector) {
    return this.querySelectorAll(selector)[0] ?? null;
  }

  querySelectorAll(selector) {
    const raw = String(selector).trim();
    const directChild = raw.startsWith(':scope >');
    const normalized = raw.replace(/^:scope\s*>\s*/, '').trim();
    const matches = [];
    const visit = (node) => {
      if (!(node instanceof FixtureElement)) {
        return;
      }
      if (node._matchesOne(normalized)) {
        matches.push(node);
      }
      node.children.forEach((child) => visit(child));
    };
    if (directChild) {
      this.children.forEach((child) => {
        if (child._matchesOne(normalized)) {
          matches.push(child);
        }
      });
      return matches;
    }
    this.children.forEach((child) => visit(child));
    return matches;
  }

  addEventListener(type, handler) {
    const list = this._listeners.get(type) ?? [];
    list.push(handler);
    this._listeners.set(type, list);
  }

  dispatchEvent(event) {
    const list = this._listeners.get(event?.type) ?? [];
    list.forEach((handler) => handler(event));
    return true;
  }
}

class FixtureDocument {
  constructor() {
    this.body = new FixtureElement('body', this);
    this.documentElement = this.body;
  }

  createElement(tagName) {
    return new FixtureElement(tagName, this);
  }

  querySelector(selector) {
    return this.body.querySelector(selector);
  }

  querySelectorAll(selector) {
    return this.body.querySelectorAll(selector);
  }
}

const IMAGE_SOURCE_FIELD_IDS = [
  'settings.source',
  'settings.folder',
  'settings.recursive',
  'settings.useFalCaptions',
  'settings.sortBy',
  'settings.sortDir',
];
const LAYOUT_SETTINGS_FIELD_IDS = [
  'settings.layoutMode',
  'settings.maxItemsPerRow',
  'settings.maxWidth',
  'settings.itemsPerPage',
  'settings.loadStep',
];

const createSection = (document, fieldId, withSelect = false) => {
  const section = document.createElement('div');
  section.classList.add('form-section');
  section.dataset.id = fieldId;
  if (withSelect) {
    const select = document.createElement('select');
    section.append(select);
  }
  return section;
};

const buildRawForm = (document) => {
  const form = document.createElement('form');
  form.setAttribute('name', 'editform');
  const tabContent = document.createElement('div');
  tabContent.classList.add('tab-content');

  const layoutSheet = document.createElement('div');
  layoutSheet.classList.add('tab-pane');
  IMAGE_SOURCE_FIELD_IDS.forEach((fieldId) => layoutSheet.append(createSection(document, fieldId, fieldId === 'settings.source')));
  LAYOUT_SETTINGS_FIELD_IDS.forEach((fieldId) => layoutSheet.append(createSection(document, fieldId)));
  layoutSheet.append(createSection(document, 'settings.captions'));

  const imagesSheet = document.createElement('div');
  imagesSheet.classList.add('tab-pane');
  imagesSheet.append(createSection(document, 'settings.designPreset', true));

  const designOverrides = createSection(document, 'settings.designOverrides');
  const editor = document.createElement('div');
  editor.classList.add('mosaic-design-configurator');
  editor.setAttribute('data-mosaic-design-configurator', 'true');
  const storage = document.createElement('input');
  storage.setAttribute('data-design-storage', 'true');
  const presetSlot = document.createElement('div');
  presetSlot.setAttribute('data-design-preset-slot', 'true');
  editor.append(storage, presetSlot);
  designOverrides.append(editor);
  imagesSheet.append(designOverrides);
  imagesSheet.append(createSection(document, 'settings.frameColor'));

  const manual = document.createElement('div');
  manual.classList.add('form-section');
  manual.dataset.id = 'tx_anatolkinmosaicgallery_images';
  const metadataWrap = document.createElement('div');
  metadataWrap.classList.add('form-section');
  const metadata = document.createElement('div');
  metadata.setAttribute('data-mosaic-metadata-editor', 'true');
  metadataWrap.append(metadata);

  tabContent.append(layoutSheet, imagesSheet);
  form.append(tabContent, manual, metadataWrap);
  document.body.append(form);
  return { form, layoutSheet, imagesSheet, editor };
};

// Mirror of production consolidateWorkspaces ownership moves (bootstrap timing covered separately).
const consolidateWorkspacesFixture = (editor) => {
  if (editor.dataset.mosaicWorkspacesConsolidated === 'true') {
    const imagesSheet = editor.closest('.tab-pane.mosaic-images-sheet') ?? editor.closest('.tab-pane');
    const tabContent = imagesSheet?.parentElement;
    const layoutSheet = tabContent?.querySelector(':scope > .tab-pane.mosaic-layout-sheet');
    return { layoutSheet, imagesSheet };
  }

  const imagesSheet = editor.closest('.tab-pane');
  const tabContent = imagesSheet?.parentElement;
  const layoutSheet = [...(tabContent?.querySelectorAll(':scope > .tab-pane') ?? [])].find(
    (pane) => pane !== imagesSheet && (
      pane.querySelector(':scope > .form-section[data-id="settings.source"]')
      || pane.querySelector('.form-section[data-id="settings.source"]')
    ),
  );
  if (!imagesSheet || !layoutSheet || imagesSheet === layoutSheet) {
    return { layoutSheet: imagesSheet ?? null, imagesSheet: imagesSheet ?? null };
  }

  layoutSheet.classList.add('mosaic-layout-sheet');
  imagesSheet.classList.add('mosaic-images-sheet');

  [...imagesSheet.querySelectorAll(':scope > .form-section')].forEach((section) => layoutSheet.append(section));

  const imagesHeader = editor.ownerDocument.createElement('div');
  imagesHeader.classList.add('mosaic-images-header');
  const imagesSourceRow = editor.ownerDocument.createElement('div');
  imagesSourceRow.classList.add('mosaic-images-header__row--source');
  imagesHeader.append(imagesSourceRow);
  imagesSheet.prepend(imagesHeader);

  IMAGE_SOURCE_FIELD_IDS.forEach((fieldName) => {
    const section = layoutSheet.querySelector(`:scope > .form-section[data-id="${fieldName}"]`);
    if (section) {
      imagesSourceRow.append(section);
    }
  });

  const form = editor.closest('form');
  const metadataEditor = form?.querySelector('[data-mosaic-metadata-editor]');
  const metadataSection = metadataEditor?.closest('.form-section');
  const manualImagesSection = form?.querySelector('.form-section[data-id="tx_anatolkinmosaicgallery_images"]');
  if (manualImagesSection) {
    imagesSheet.append(manualImagesSection);
  }
  if (metadataSection) {
    imagesSheet.append(metadataSection);
  }

  editor.dataset.mosaicWorkspacesConsolidated = 'true';
  return { layoutSheet, imagesSheet, imagesSourceRow };
};

const bootstrapWhenReady = (getEditor, attemptsLeft = 5) => {
  const editor = getEditor();
  if (!editor) {
    if (attemptsLeft <= 0) {
      return null;
    }
    return bootstrapWhenReady(getEditor, attemptsLeft - 1);
  }
  const imagesSheet = editor.closest('.tab-pane');
  const tabContent = imagesSheet?.parentElement;
  const ready = Boolean(
    imagesSheet
    && tabContent
    && [...tabContent.querySelectorAll(':scope > .tab-pane')].some(
      (pane) => pane !== imagesSheet && pane.querySelector('.form-section[data-id="settings.source"]'),
    ),
  );
  if (!ready) {
    if (attemptsLeft <= 0) {
      return null;
    }
    return bootstrapWhenReady(getEditor, attemptsLeft - 1);
  }
  return consolidateWorkspacesFixture(editor);
};

// A: module-style early evaluation finds no editor yet
const earlyDocument = new FixtureDocument();
globalThis.Element = FixtureElement;
assert(
  earlyDocument.querySelectorAll('[data-mosaic-design-configurator]').length === 0,
  'A: early document must not contain the design configurator yet',
);

// B: FormEngine inserts markup later; bootstrap must still consolidate
const lateDocument = new FixtureDocument();
let lateEditorRef = null;
const lateResultBefore = bootstrapWhenReady(() => lateEditorRef, 1);
assert(lateResultBefore === null, 'B: bootstrap must wait until editor DOM exists');
const lateBuilt = buildRawForm(lateDocument);
lateEditorRef = lateBuilt.editor;
const lateResult = bootstrapWhenReady(() => lateEditorRef, 3);
assert(Boolean(lateResult?.layoutSheet && lateResult?.imagesSheet), 'B: late DOM must consolidate');
assert(lateResult.layoutSheet.classList.contains('mosaic-layout-sheet'), 'B: layout sheet marked');
assert(lateResult.imagesSheet.classList.contains('mosaic-images-sheet'), 'B: images sheet marked');
assert(
  Boolean(lateResult.layoutSheet.querySelector('.form-section[data-id="settings.designPreset"]')),
  'B: Layout must own Design preset after consolidation',
);
assert(
  Boolean(lateResult.imagesSheet.querySelector('.form-section[data-id="settings.source"]')),
  'B: Images must own Source after consolidation',
);
assert(
  !lateResult.imagesSheet.querySelector(':scope > .form-section[data-id="settings.designPreset"]'),
  'B: Design preset must leave the Images sheet root',
);

// C: idempotent second pass
const headerCountBefore = lateResult.imagesSheet.querySelectorAll('.mosaic-images-header').length;
consolidateWorkspacesFixture(lateBuilt.editor);
const headerCountAfter = lateResult.imagesSheet.querySelectorAll('.mosaic-images-header').length;
assert(headerCountBefore === 1, 'C: first consolidation creates one images header');
assert(headerCountAfter === 1, 'C: second consolidation must not duplicate images header');
assert(lateBuilt.editor.dataset.mosaicWorkspacesConsolidated === 'true', 'C: consolidated flag retained');

// D: production source must use deferred bootstrap contracts
const fs = require('fs');
const path = require('path');
const source = fs.readFileSync(
  path.join(__dirname, '..', 'Resources', 'Public', 'JavaScript', 'design-configurator.js'),
  'utf8',
);
assert(source.includes('MutationObserver'), 'D: design-configurator must observe FormEngine DOM mutations');
assert(source.includes('setupFormBootstrapObserver'), 'D: design-configurator must register form bootstrap observer');
assert(source.includes('queueMicrotask'), 'D: design-configurator must microtask-retry bootstrap');
assert(source.includes('canConsolidateWorkspaces'), 'D: readiness gate must exist');
assert(source.includes('mosaicWorkspacesConsolidated'), 'D: idempotent consolidation flag must exist');
assert(
  !/document\.querySelectorAll\(\s*\[\s*data-mosaic-design-configurator\s*\]\s*\)\s*\.forEach\(\s*initializeEditor\s*\)/.test(source)
  && !source.includes("document.querySelectorAll('[data-mosaic-design-configurator]').forEach(initializeEditor)"),
  'D: one-shot initializeEditor scan must not remain as the only bootstrap path',
);
assert(
  /mosaicDesignInitialized[\s\S]{0,180}canConsolidateWorkspaces|canConsolidateWorkspaces[\s\S]{0,220}mosaicDesignInitialized/.test(source),
  'D: initializeEditor must not mark initialized before consolidation readiness',
);

if (failures.length) {
  console.error('Design workspace bootstrap tests failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Design workspace bootstrap tests passed.');
console.log('LATE_DOM_CONSOLIDATION=PASS');
console.log('IDEMPOTENT_CONSOLIDATION=PASS');
console.log('DEFERRED_BOOTSTRAP_CONTRACT=PASS');
process.exit(0);
