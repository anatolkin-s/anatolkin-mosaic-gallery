const parseDocument = (value) => {
  try {
    const document = JSON.parse(value);
    return document && typeof document === 'object' && !Array.isArray(document) ? document : {};
  } catch (error) {
    return {};
  }
};

const clone = (value) => JSON.parse(JSON.stringify(value));
const pathSegments = (path) => path.split('.');
const normalizePreset = (value) => value === '' || value === 'custom' ? 'custom' : value;

const valueAtPath = (document, path) => pathSegments(path).reduce(
  (value, segment) => value && typeof value === 'object' ? value[segment] : undefined,
  document,
);

const setPath = (document, path, value) => {
  const segments = pathSegments(path);
  let target = document;
  segments.forEach((segment, index) => {
    if (index === segments.length - 1) {
      target[segment] = value;
      return;
    }
    target[segment] = target[segment] && typeof target[segment] === 'object' ? target[segment] : {};
    target = target[segment];
  });
};

const deletePath = (document, path) => {
  const segments = pathSegments(path);
  const parents = [];
  let target = document;
  for (const segment of segments.slice(0, -1)) {
    if (!target[segment] || typeof target[segment] !== 'object') {
      return;
    }
    parents.push([target, segment]);
    target = target[segment];
  }
  delete target[segments.at(-1)];
  parents.reverse().forEach(([parent, segment]) => {
    if (Object.keys(parent[segment]).length === 0) {
      delete parent[segment];
    }
  });
};

const countLeaves = (document) => Object.values(document).reduce(
  (count, value) => count + (value && typeof value === 'object' && !Array.isArray(value)
    ? countLeaves(value)
    : 1),
  0,
);

const canonicalJson = (value) => JSON.stringify(value, (key, item) => {
  if (!item || typeof item !== 'object' || Array.isArray(item)) {
    return item;
  }
  return Object.keys(item).sort().reduce((result, itemKey) => {
    result[itemKey] = item[itemKey];
    return result;
  }, {});
});

const controlValue = (control, base) => {
  switch (control.dataset.designKind) {
    case 'boolean':
      return control.value === '1';
    case 'integer': {
      const value = Number.parseInt(control.value, 10);
      return Number.isInteger(value) && value >= 0 ? value : base;
    }
    case 'number':
    case 'alpha': {
      const value = Number(control.value);
      if (!Number.isFinite(value) || value < 0) {
        return base;
      }
      const clamped = control.dataset.designKind === 'alpha' ? Math.min(1, value) : value;
      return String(clamped);
    }
    default:
      return control.value;
  }
};

const displayValue = (control, value) => {
  control.value = control.dataset.designKind === 'boolean' ? (value ? '1' : '0') : String(value);
  const swatch = control.closest('[data-design-field]')?.querySelector('[data-design-swatch]');
  if (swatch) {
    swatch.style.backgroundColor = String(value);
  }
};

const updateFieldState = (control, modified) => {
  const reset = control.closest('[data-design-field]')?.querySelector('[data-design-reset-field]');
  if (reset) {
    reset.disabled = !modified;
    reset.hidden = !modified;
  }
};

const applyEffectiveValues = (editor, base, document) => {
  editor.querySelectorAll('[data-design-control]').forEach((control) => {
    const path = control.dataset.designPath;
    const baseValue = valueAtPath(base, path);
    const overrideValue = valueAtPath(document, path);
    const modified = overrideValue !== undefined;
    control.dataset.designBaseValue = JSON.stringify(baseValue);
    displayValue(control, modified ? overrideValue : baseValue);
    updateFieldState(control, modified);
  });
};

const effectiveDesign = (base, document) => {
  const effective = clone(base);
  const merge = (target, source) => Object.entries(source).forEach(([key, value]) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      target[key] = target[key] && typeof target[key] === 'object' ? target[key] : {};
      merge(target[key], value);
    } else {
      target[key] = value;
    }
  });
  merge(effective, document);
  return effective;
};

const initializeEditor = (editor) => {
  if (editor.dataset.mosaicDesignInitialized === 'true') {
    return;
  }
  editor.dataset.mosaicDesignInitialized = 'true';

  const storage = editor.querySelector('[data-design-storage]');
  const sheet = editor.closest('.tab-pane');
  const presetSection = sheet?.querySelector(':scope > .form-section[data-id="settings.designPreset"]');
  const configuratorSection = editor.closest('.form-section[data-id="settings.designOverrides"]');
  const presetSelector = presetSection?.querySelector('select');
  if (!storage || !sheet || !presetSelector || !configuratorSection) {
    return;
  }

  const customSections = [...sheet.querySelectorAll(':scope > .form-section')].filter(
    (section) => section !== presetSection && section !== configuratorSection,
  );
  const bases = parseDocument(editor.dataset.presetBases);
  const labels = parseDocument(editor.dataset.presetLabels);
  const savedPreset = normalizePreset(editor.dataset.savedPreset);
  const savedDocument = parseDocument(editor.dataset.savedOverrides);
  let document = parseDocument(storage.value);

  const currentPreset = () => normalizePreset(presetSelector.value);
  const presetLabel = (preset) => labels[preset] ?? preset;
  const currentBase = () => bases[currentPreset()] ?? null;

  const publishState = () => {
    const preset = currentPreset();
    const base = currentBase();
    editor.dispatchEvent(new CustomEvent('mosaic-design-change', {
      bubbles: true,
      detail: {
        preset,
        overrides: clone(document),
        effective: base ? effectiveDesign(base, document) : {},
      },
    }));
  };

  const updateStatus = () => {
    const preset = currentPreset();
    const count = countLeaves(document);
    const dirty = preset !== savedPreset || canonicalJson(document) !== canonicalJson(savedDocument);
    const preview = editor.querySelector('[data-design-preview]');
    const modifications = editor.querySelector('[data-design-modifications]');
    const resetAll = editor.querySelector('[data-design-reset-all]');
    if (preview) {
      preview.hidden = !dirty;
      preview.textContent = dirty
        ? `${editor.dataset.previewingLabel}: ${presetLabel(preset)} · ${editor.dataset.unsavedLabel}`
        : '';
    }
    if (modifications) {
      modifications.textContent = count > 0
        ? `${presetLabel(preset)} · ${count} ${editor.dataset.modifiedLabel.toLowerCase()}`
        : '';
    }
    if (resetAll) {
      const resetLabel = preset === 'site' ? editor.dataset.siteDefaultLabel : presetLabel(preset);
      resetAll.textContent = editor.dataset.resetAllTemplate.replace('%s', resetLabel);
      resetAll.disabled = count === 0;
    }
  };

  const updateMode = () => {
    const custom = currentPreset() === 'custom';
    customSections.forEach((section) => {
      section.hidden = !custom;
    });
    configuratorSection.hidden = custom;
    if (!custom) {
      applyEffectiveValues(editor, currentBase(), document);
    }
    updateStatus();
    publishState();
  };

  const persist = () => {
    storage.value = JSON.stringify(document);
    storage.dispatchEvent(new Event('change', { bubbles: true }));
    updateStatus();
    publishState();
  };

  presetSelector.addEventListener('change', updateMode);
  editor.addEventListener('change', (event) => {
    const control = event.target.closest('[data-design-control]');
    if (!control) {
      return;
    }
    const path = control.dataset.designPath;
    const base = valueAtPath(currentBase(), path);
    const value = controlValue(control, base);
    displayValue(control, value);
    if (canonicalJson(value) === canonicalJson(base)) {
      deletePath(document, path);
      updateFieldState(control, false);
    } else {
      setPath(document, path, value);
      updateFieldState(control, true);
    }
    persist();
  });

  editor.addEventListener('input', (event) => {
    const control = event.target.closest('[data-design-control][data-design-kind="color"]');
    if (control) {
      const swatch = control.closest('[data-design-field]')?.querySelector('[data-design-swatch]');
      if (swatch) {
        swatch.style.backgroundColor = control.value;
      }
    }
  });

  editor.addEventListener('click', (event) => {
    const resetField = event.target.closest('[data-design-reset-field]');
    if (resetField) {
      const control = resetField.closest('[data-design-field]')?.querySelector('[data-design-control]');
      if (control) {
        deletePath(document, control.dataset.designPath);
        displayValue(control, valueAtPath(currentBase(), control.dataset.designPath));
        updateFieldState(control, false);
        persist();
      }
      return;
    }
    if (event.target.closest('[data-design-reset-all]')) {
      document = {};
      applyEffectiveValues(editor, currentBase(), document);
      persist();
    }
  });

  updateMode();
};

document.querySelectorAll('[data-mosaic-design-configurator]').forEach(initializeEditor);
