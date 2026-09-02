#!/usr/bin/env node
'use strict';

/**
 * Executable DOM-fixture contract for Mosaic design workspace bootstrap races.
 *
 * Cases:
 * 1. Module initializes with NO edit form; form+editor arrive later.
 * 2. Unrelated <form> exists first; bootstrap must not trap on it.
 * 3. Edit form is replaced after first init; new editor must initialize.
 * 4. Repeated mutations remain idempotent.
 * 5. Delayed insertion beyond one microtask / one animation frame.
 *
 * Run: node Build/test-design-workspace-bootstrap.js
 */

const fs = require('fs');
const path = require('path');

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
}

class FixtureElement {
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
    return this.childNodes.map((node) => (typeof node === 'string' ? node : (node.textContent ?? ''))).join('');
  }

  set textContent(value) {
    this.childNodes = [String(value)];
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
    return String(selector).split(',').map((part) => part.trim()).filter(Boolean)
      .some((part) => this._matchesOne(part));
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
    this.ownerDocument?._notifyAdded?.(node);
    return node;
  }

  insertBefore(node, reference) {
    if (node.parentElement) {
      node.parentElement.removeChild(node);
    }
    node.parentElement = this;
    if (!reference) {
      this.childNodes.push(node);
    } else {
      const index = this.childNodes.indexOf(reference);
      if (index === -1) {
        this.childNodes.push(node);
      } else {
        this.childNodes.splice(index, 0, node);
      }
    }
    this.ownerDocument?._notifyAdded?.(node);
    return node;
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
}

class FixtureDocument {
  constructor() {
    this.body = new FixtureElement('body', this);
    this.documentElement = this.body;
    this._addedHandlers = [];
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

  onAdded(handler) {
    this._addedHandlers.push(handler);
  }

  _notifyAdded(node) {
    this._addedHandlers.forEach((handler) => handler(node));
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

const createSection = (document, fieldId, withSelect = false) => {
  const section = document.createElement('div');
  section.classList.add('form-section');
  section.dataset.id = fieldId;
  if (withSelect) {
    section.append(document.createElement('select'));
  }
  return section;
};

const buildMosaicEditForm = (document, { name = 'editform' } = {}) => {
  const moduleBody = document.querySelector('.t3js-module-body') ?? (() => {
    const created = document.createElement('div');
    created.classList.add('t3js-module-body');
    document.body.append(created);
    return created;
  })();

  const form = document.createElement('form');
  form.setAttribute('name', name);
  const tabContent = document.createElement('div');
  tabContent.classList.add('tab-content');

  const layoutSheet = document.createElement('div');
  layoutSheet.classList.add('tab-pane');
  IMAGE_SOURCE_FIELD_IDS.forEach((fieldId) => {
    layoutSheet.append(createSection(document, fieldId, fieldId === 'settings.source'));
  });

  const imagesSheet = document.createElement('div');
  imagesSheet.classList.add('tab-pane');
  imagesSheet.append(createSection(document, 'settings.designPreset', true));
  const designOverrides = createSection(document, 'settings.designOverrides');
  const editor = document.createElement('div');
  editor.setAttribute('data-mosaic-design-configurator', 'true');
  const storage = document.createElement('input');
  storage.setAttribute('data-design-storage', 'true');
  editor.append(storage);
  designOverrides.append(editor);
  imagesSheet.append(designOverrides);

  tabContent.append(layoutSheet, imagesSheet);
  form.append(tabContent);
  moduleBody.append(form);
  return { form, layoutSheet, imagesSheet, editor, moduleBody };
};

const resolveEditFormRoot = (document) => (
  document.querySelector('form[name="editform"]')
  ?? document.querySelector('form#EditDocumentController')
  ?? document.querySelector('.t3js-module-body form')
  ?? null
);

const resolveStableBootstrapRoot = (document) => (
  document.querySelector('.t3js-module-body')
  ?? document.body
  ?? document.documentElement
  ?? null
);

const consolidateWorkspacesFixture = (editor) => {
  if (editor.dataset.mosaicWorkspacesConsolidated === 'true') {
    const imagesSheet = editor.closest('.tab-pane.mosaic-images-sheet') ?? editor.closest('.tab-pane');
    const tabContent = imagesSheet?.parentElement;
    const layoutSheet = tabContent?.querySelector(':scope > .tab-pane.mosaic-layout-sheet');
    return { layoutSheet, imagesSheet, skipped: true };
  }

  const imagesSheet = editor.closest('.tab-pane');
  const tabContent = imagesSheet?.parentElement;
  const layoutSheet = [...(tabContent?.querySelectorAll(':scope > .tab-pane') ?? [])].find(
    (pane) => pane !== imagesSheet && pane.querySelector('.form-section[data-id="settings.source"]'),
  );
  if (!imagesSheet || !layoutSheet || imagesSheet === layoutSheet) {
    return { layoutSheet: null, imagesSheet, skipped: false };
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
    const section = layoutSheet.querySelector(`.form-section[data-id="${fieldName}"]`);
    if (section) {
      imagesSourceRow.append(section);
    }
  });

  editor.dataset.mosaicWorkspacesConsolidated = 'true';
  editor.dataset.mosaicDesignInitialized = 'true';
  return { layoutSheet, imagesSheet, skipped: false };
};

/**
 * Document-level bootstrap mirror of production contract:
 * observe stable ancestor, never trap on arbitrary first <form>.
 */
const createDocumentBootstrap = (document) => {
  const state = {
    observedRoot: null,
    initializedEditors: new Set(),
    bootstrapCount: 0,
  };

  const bootstrap = () => {
    state.bootstrapCount += 1;
    document.querySelectorAll('[data-mosaic-design-configurator]').forEach((editor) => {
      if (state.initializedEditors.has(editor) && editor.dataset.mosaicDesignInitialized === 'true') {
        consolidateWorkspacesFixture(editor);
        return;
      }
      const result = consolidateWorkspacesFixture(editor);
      if (result.layoutSheet && result.imagesSheet && editor.dataset.mosaicWorkspacesConsolidated === 'true') {
        state.initializedEditors.add(editor);
      }
    });
  };

  const setupObserver = () => {
    const root = resolveStableBootstrapRoot(document);
    if (!root) {
      return false;
    }
    state.observedRoot = root;
    document.onAdded((node) => {
      if (!(node instanceof FixtureElement)) {
        return;
      }
      const signal = node.matches('[data-mosaic-design-configurator]')
        || Boolean(node.querySelector?.('[data-mosaic-design-configurator]'))
        || node.matches('form[name="editform"]')
        || Boolean(node.querySelector?.('form[name="editform"]'))
        || node.matches('.tab-pane')
        || Boolean(node.querySelector?.('.form-section[data-id="settings.source"]'));
      if (signal) {
        bootstrap();
      }
    });
    return true;
  };

  return { state, bootstrap, setupObserver, resolveEditFormRoot: () => resolveEditFormRoot(document) };
};

const assertOwnership = (label, editor) => {
  const tabContent = editor.closest('.tab-content');
  const layoutSheet = tabContent?.querySelector('.tab-pane.mosaic-layout-sheet');
  const imagesSheet = tabContent?.querySelector('.tab-pane.mosaic-images-sheet');
  assert(Boolean(layoutSheet), `${label}: layout sheet consolidated`);
  assert(Boolean(imagesSheet), `${label}: images sheet marked`);
  assert(
    Boolean(layoutSheet.querySelector('.form-section[data-id="settings.designPreset"]')),
    `${label}: Layout owns Design preset`,
  );
  assert(
    Boolean(imagesSheet.querySelector('.form-section[data-id="settings.source"]')),
    `${label}: Images owns Source`,
  );
};

// CASE 1: no edit form at module init; form+editor inserted later via observer
(() => {
  const document = new FixtureDocument();
  const moduleBody = document.createElement('div');
  moduleBody.classList.add('t3js-module-body');
  document.body.append(moduleBody);
  const boot = createDocumentBootstrap(document);
  assert(boot.resolveEditFormRoot() === null, 'CASE1: no edit form at module evaluation');
  assert(boot.setupObserver() === true, 'CASE1: stable ancestor observer installs without edit form');
  assert(boot.state.observedRoot === moduleBody, 'CASE1: observer watches module body, not a form');
  boot.bootstrap();
  assert(document.querySelectorAll('[data-mosaic-design-configurator]').length === 0, 'CASE1: no editor yet');
  const built = buildMosaicEditForm(document);
  assertOwnership('CASE1', built.editor);
})();

// CASE 2: unrelated form exists first; must not trap bootstrap
(() => {
  const document = new FixtureDocument();
  const decoy = document.createElement('form');
  decoy.setAttribute('name', 'searchbox');
  document.body.append(decoy);
  const moduleBody = document.createElement('div');
  moduleBody.classList.add('t3js-module-body');
  document.body.append(moduleBody);

  const boot = createDocumentBootstrap(document);
  assert(boot.resolveEditFormRoot() === null, 'CASE2: decoy form must not resolve as edit form');
  assert(document.querySelector('form') === decoy, 'CASE2: arbitrary first form is the decoy');
  boot.setupObserver();
  assert(boot.state.observedRoot !== decoy, 'CASE2: observer must not bind to decoy form');
  assert(boot.state.observedRoot === moduleBody, 'CASE2: observer binds stable module body');
  const built = buildMosaicEditForm(document);
  assertOwnership('CASE2', built.editor);
  assert(built.form !== decoy, 'CASE2: mosaic edit form is distinct from decoy');
})();

// CASE 3: edit form replaced; new editor initializes
(() => {
  const document = new FixtureDocument();
  const moduleBody = document.createElement('div');
  moduleBody.classList.add('t3js-module-body');
  document.body.append(moduleBody);
  const boot = createDocumentBootstrap(document);
  boot.setupObserver();
  const first = buildMosaicEditForm(document);
  assertOwnership('CASE3-first', first.editor);
  first.form.remove();
  const second = buildMosaicEditForm(document);
  assert(second.editor !== first.editor, 'CASE3: replacement creates a new editor node');
  assertOwnership('CASE3-second', second.editor);
  assert(second.editor.dataset.mosaicDesignInitialized === 'true', 'CASE3: replacement editor initialized');
})();

// CASE 4: repeated mutations remain idempotent
(() => {
  const document = new FixtureDocument();
  const moduleBody = document.createElement('div');
  moduleBody.classList.add('t3js-module-body');
  document.body.append(moduleBody);
  const boot = createDocumentBootstrap(document);
  boot.setupObserver();
  const built = buildMosaicEditForm(document);
  const headersBefore = built.imagesSheet.querySelectorAll('.mosaic-images-header').length;
  boot.bootstrap();
  boot.bootstrap();
  boot.bootstrap();
  const headersAfter = built.imagesSheet.querySelectorAll('.mosaic-images-header').length;
  assert(headersBefore === 1, 'CASE4: one header after first consolidation');
  assert(headersAfter === 1, 'CASE4: repeated bootstraps do not duplicate headers');
  assertOwnership('CASE4', built.editor);
})();

// CASE 5: delayed insertion beyond one microtask / one rAF window
(() => {
  const document = new FixtureDocument();
  const moduleBody = document.createElement('div');
  moduleBody.classList.add('t3js-module-body');
  document.body.append(moduleBody);
  const boot = createDocumentBootstrap(document);
  boot.setupObserver();
  // Simulate short retries that find nothing (microtask + rAF equivalents).
  boot.bootstrap();
  boot.bootstrap();
  assert(document.querySelectorAll('[data-mosaic-design-configurator]').length === 0, 'CASE5: still empty after short retries');
  // Much later FormEngine insert — only the durable observer may see it.
  const built = buildMosaicEditForm(document);
  assertOwnership('CASE5', built.editor);
  assert(boot.state.bootstrapCount >= 3, 'CASE5: observer-driven bootstrap runs after delayed insert');
})();

// Production source contracts
const source = fs.readFileSync(
  path.join(__dirname, '..', 'Resources', 'Public', 'JavaScript', 'design-configurator.js'),
  'utf8',
);
assert(source.includes('setupDocumentBootstrapObserver'), 'SRC: document bootstrap observer helper');
assert(source.includes('resolveStableBootstrapRoot'), 'SRC: stable ancestor resolver');
assert(source.includes('resolveEditFormRoot'), 'SRC: edit-form resolver without arbitrary form fallback');
assert(!/resolveEditFormRoot[\s\S]{0,260}document\.querySelector\(\s*['"]form['"]\s*\)/.test(source), 'SRC: no arbitrary form fallback in resolveEditFormRoot');
assert(!source.includes('setupFormBootstrapObserver'), 'SRC: old form-scoped observer removed');
assert(source.includes('MutationObserver'), 'SRC: MutationObserver retained');
assert(/setupDocumentBootstrapObserver\(\);\s*bootstrapDesignEditors\(document\);/.test(source)
  || /setupDocumentBootstrapObserver\(\)[\s\S]{0,80}bootstrapDesignEditors\(document\)/.test(source),
'SRC: initialize installs document observer before/with bootstrap');

if (failures.length) {
  console.error('Design workspace bootstrap tests failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Design workspace bootstrap tests passed.');
console.log('CASE1_NO_EDITFORM_THEN_INSERT=PASS');
console.log('CASE2_UNRELATED_FORM_NO_TRAP=PASS');
console.log('CASE3_EDITFORM_REPLACEMENT=PASS');
console.log('CASE4_IDEMPOTENT_MUTATIONS=PASS');
console.log('CASE5_DELAYED_BEYOND_SHORT_RETRY=PASS');
process.exit(0);
