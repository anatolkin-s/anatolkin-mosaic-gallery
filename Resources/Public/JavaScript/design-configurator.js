const parseDocument = (value) => {
  try {
    const document = JSON.parse(value);
    return document && typeof document === 'object' && !Array.isArray(document) ? document : {};
  } catch (error) {
    return {};
  }
};

const pathSegments = (path) => path.split('.');

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

const baseValue = (control) => JSON.parse(control.dataset.designBaseValue);

const controlValue = (control) => {
  switch (control.dataset.designKind) {
    case 'boolean':
      return control.value === '1';
    case 'integer': {
      const value = Number.parseInt(control.value, 10);
      return Number.isInteger(value) && value >= 0 ? value : baseValue(control);
    }
    case 'number':
    case 'alpha': {
      const value = Number(control.value);
      if (!Number.isFinite(value) || value < 0) {
        return baseValue(control);
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

const updateStatus = (editor, document) => {
  const count = countLeaves(document);
  const status = editor.querySelector('[data-design-status]');
  if (status) {
    status.textContent = count > 0
      ? `${editor.dataset.presetLabel} · ${editor.dataset.modifiedLabel} (${count})`
      : editor.dataset.presetLabel;
  }
  const resetAll = editor.querySelector('[data-design-reset-all]');
  if (resetAll) {
    resetAll.disabled = count === 0;
  }
};

const persist = (editor, document) => {
  const storage = editor.querySelector('[data-design-storage]');
  if (!storage) {
    return;
  }
  storage.value = JSON.stringify(document);
  storage.dispatchEvent(new Event('change', { bubbles: true }));
  updateStatus(editor, document);
};

const updateFieldState = (control, modified) => {
  const reset = control.closest('[data-design-field]')?.querySelector('[data-design-reset-field]');
  if (reset) {
    reset.disabled = !modified;
    reset.hidden = !modified;
  }
  displayValue(control, controlValue(control));
};

const initializeEditor = (editor) => {
  if (editor.dataset.mosaicDesignInitialized === 'true') {
    return;
  }
  editor.dataset.mosaicDesignInitialized = 'true';
  const storage = editor.querySelector('[data-design-storage]');
  if (!storage) {
    return;
  }
  let document = parseDocument(storage.value);
  editor.querySelectorAll('[data-design-control]').forEach((control) => {
    displayValue(control, controlValue(control));
  });
  updateStatus(editor, document);

  editor.addEventListener('change', (event) => {
    const control = event.target.closest('[data-design-control]');
    if (!control) {
      return;
    }
    const value = controlValue(control);
    const base = baseValue(control);
    displayValue(control, value);
    if (JSON.stringify(value) === JSON.stringify(base)) {
      deletePath(document, control.dataset.designPath);
      updateFieldState(control, false);
    } else {
      setPath(document, control.dataset.designPath, value);
      updateFieldState(control, true);
    }
    persist(editor, document);
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
        displayValue(control, baseValue(control));
        updateFieldState(control, false);
        persist(editor, document);
      }
      return;
    }
    if (event.target.closest('[data-design-reset-all]')) {
      document = {};
      editor.querySelectorAll('[data-design-control]').forEach((control) => {
        displayValue(control, baseValue(control));
        updateFieldState(control, false);
      });
      persist(editor, document);
    }
  });
};

document.querySelectorAll('[data-mosaic-design-configurator]').forEach(initializeEditor);
