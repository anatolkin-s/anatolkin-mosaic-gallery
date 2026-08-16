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
const CUSTOM_FIELDS = {
  'settings.frameColor': ['frameColor', 'string'],
  'settings.frameAccentColor': ['frameAccentColor', 'string'],
  'settings.frameWidth': ['frameWidth', 'string'],
  'settings.frameStyle': ['frameStyle', 'string'],
  'settings.borderRadius': ['borderRadius', 'integer'],
  'settings.shadow': ['shadow', 'boolean'],
  'settings.backgroundColor': ['backgroundColor', 'string'],
  'settings.captionColor': ['captionColor', 'string'],
  'settings.applyTo': ['applyTo', 'string'],
  'settings.lbOverlay': ['lightbox.overlay', 'string'],
  'settings.lbOverlayAlpha': ['lightbox.overlayAlpha', 'string'],
  'settings.lbNavColor': ['lightbox.navColor', 'string'],
  'settings.lbCloseColor': ['lightbox.closeColor', 'string'],
  'settings.lbCaptionColor': ['lightbox.captionColor', 'string'],
  'settings.lbCaptionBg': ['lightbox.captionBackground', 'string'],
  'settings.lbCaptionBgAlpha': ['lightbox.captionBackgroundAlpha', 'string'],
  'settings.lbCaptionAlign': ['lightbox.captionAlign', 'string'],
  'settings.lbCaptionSize': ['lightbox.captionSize', 'string'],
  'settings.lbCaptionStyle': ['lightbox.captionStyle', 'string'],
};
const MULTI_COLOR_FRAME_STYLES = new Set([
  'double',
  'groove',
  'ridge',
  'triple',
  'doubleOuterStrong',
  'doubleInnerStrong',
  'gallery',
]);

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
  const picker = control.closest('[data-design-field]')?.querySelector('[data-design-color-picker]');
  if (picker && /^#[\da-f]{6}$/i.test(String(value))) {
    picker.value = String(value);
  }
};

const updateFieldState = (control, modified) => {
  const reset = control.closest('[data-design-field]')?.querySelector('[data-design-reset-field]');
  if (reset) {
    reset.disabled = !modified;
    reset.hidden = !modified;
  }
};

const applyEffectiveValues = (editor, base, overrides, paths = []) => {
  const controls = new Map([...editor.querySelectorAll('[data-design-control]')].map(
    (control) => [control.dataset.designPath, control],
  ));
  (paths.length > 0 ? paths : [...controls.keys()]).forEach((path) => {
    const control = controls.get(path);
    if (!control) {
      return;
    }
    const baseValue = valueAtPath(base, path);
    const overrideValue = valueAtPath(overrides, path);
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

const fieldControl = (section) => section?.querySelector(
  'select, input[type="checkbox"], input.form-control, input[type="text"], input[type="number"]',
);

const customDesign = (sections) => {
  const design = {
    preset: 'custom',
    requestedPreset: 'custom',
    effectivePreset: 'custom',
    lightbox: {},
  };
  sections.forEach((section) => {
    const mapping = CUSTOM_FIELDS[section.dataset.id];
    const control = mapping ? fieldControl(section) : null;
    if (!control) {
      return;
    }
    const [path, kind] = mapping;
    let value = control.value;
    if (kind === 'boolean') {
      value = control.checked;
    } else if (kind === 'integer') {
      value = Math.max(0, Number.parseInt(value, 10) || 0);
    }
    setPath(design, path, value);
  });
  return design;
};

const colorWithAlpha = (color, alpha) => {
  const match = /^#([\da-f]{2})([\da-f]{2})([\da-f]{2})$/i.exec(color);
  if (!match) {
    return color;
  }
  return `rgba(${Number.parseInt(match[1], 16)}, ${Number.parseInt(match[2], 16)}, ${Number.parseInt(match[3], 16)}, ${alpha})`;
};

const deriveAccentColor = (frameColor) => {
  const match = /^#([\da-f]{2})([\da-f]{2})([\da-f]{2})$/i.exec(frameColor);
  if (!match) {
    return frameColor;
  }
  const channels = match.slice(1).map((channel) => Number.parseInt(channel, 16));
  const luminance = (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
  const target = luminance < 128 ? 255 : 0;
  return `#${channels.map((channel) => Math.round((channel * 0.65) + (target * 0.35))
    .toString(16).padStart(2, '0')).join('')}`.toUpperCase();
};

const renderPreview = (editor, effective) => {
  const preview = editor.querySelector('[data-design-live-preview]');
  if (!preview || !effective?.lightbox) {
    return;
  }
  const setProperty = (name, value) => preview.style.setProperty(name, value);
  setProperty('--preview-frame-color', effective.frameColor);
  setProperty(
    '--preview-frame-accent',
    effective.frameAccentColor || deriveAccentColor(effective.frameColor),
  );
  setProperty('--preview-frame-width', `${effective.frameWidth}px`);
  setProperty('--preview-radius', `${effective.borderRadius}px`);
  setProperty('--preview-background', effective.backgroundColor);
  setProperty('--preview-gallery-caption', effective.captionColor || 'inherit');
  setProperty('--preview-shadow', effective.shadow ? '0 6px 14px rgba(0, 0, 0, .22)' : 'none');
  setProperty('--preview-overlay', colorWithAlpha(effective.lightbox.overlay, effective.lightbox.overlayAlpha));
  setProperty('--preview-nav', effective.lightbox.navColor);
  setProperty('--preview-close', effective.lightbox.closeColor);
  setProperty('--preview-caption', effective.lightbox.captionColor);
  setProperty(
    '--preview-caption-background',
    colorWithAlpha(effective.lightbox.captionBackground, effective.lightbox.captionBackgroundAlpha),
  );
  preview.dataset.applyBackground = effective.applyTo;
  preview.dataset.frameStyle = effective.frameStyle;
  preview.dataset.captionAlign = effective.lightbox.captionAlign;
  preview.dataset.captionSize = effective.lightbox.captionSize;
  preview.dataset.captionStyle = effective.lightbox.captionStyle;
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
  const presetSlot = editor.querySelector('[data-design-preset-slot]');
  if (presetSlot) {
    presetSlot.append(presetSection);
  }
  const bases = parseDocument(editor.dataset.presetBases);
  const labels = parseDocument(editor.dataset.presetLabels);
  const controlPaths = (() => {
    try {
      const paths = JSON.parse(editor.dataset.controlPaths);
      return Array.isArray(paths) ? paths : [];
    } catch (error) {
      return [];
    }
  })();
  const savedPreset = normalizePreset(editor.dataset.savedPreset);
  const savedDocument = parseDocument(editor.dataset.savedOverrides);
  let overrides = parseDocument(storage.value);

  const currentPreset = () => normalizePreset(presetSelector.value);
  const presetLabel = (preset) => labels[preset] ?? preset;
  const currentBase = () => bases[currentPreset()] ?? null;
  const isCustomFieldEvent = (event) => {
    const section = event.target.closest('.form-section[data-id]');
    return Boolean(section && Object.prototype.hasOwnProperty.call(CUSTOM_FIELDS, section.dataset.id));
  };
  const proxyControls = [...editor.querySelectorAll('[data-design-proxy]')];
  const formScope = (() => {
    let scope = sheet.parentElement;
    while (scope && scope !== storage.form) {
      const hasCanonicalFields = [...scope.querySelectorAll('.form-section[data-id]')].some(
        (section) => section.dataset.id === 'settings.gap',
      );
      if (hasCanonicalFields) {
        return scope;
      }
      scope = scope.parentElement;
    }
    return storage.form || editor.closest('form') || sheet.parentElement;
  })();
  const canonicalControl = (fieldName) => {
    const section = [...formScope.querySelectorAll('.form-section[data-id]')].find(
      (candidate) => candidate.dataset.id === fieldName,
    );
    if (section) {
      return fieldControl(section);
    }
    return [...formScope.querySelectorAll('[name]')].find(
      (control) => control.name.includes('[pi_flexform]')
        && control.name.endsWith(`[${fieldName}][vDEF]`),
    ) ?? null;
  };
  const canonicalValue = (control, fieldName = '') => control?.type === 'checkbox'
    ? (control.checked ? '1' : '0')
    : (fieldName === 'settings.gap' && String(control?.value ?? '').trim() === ''
      ? '12'
      : (control?.value ?? ''));
  const displayState = () => Object.fromEntries(proxyControls.map(
    (proxy) => [proxy.dataset.designProxy.replace('settings.', ''), proxy.value],
  ));
  const updateAccentVisibility = (frameStyle) => {
    const relevant = MULTI_COLOR_FRAME_STYLES.has(frameStyle);
    const configuratorAccent = editor.querySelector('[data-design-field="frameAccentColor"]');
    if (configuratorAccent) {
      configuratorAccent.hidden = !relevant;
    }
    const customAccent = customSections.find(
      (section) => section.dataset.id === 'settings.frameAccentColor',
    );
    if (customAccent && currentPreset() === 'custom') {
      customAccent.hidden = !relevant;
    }
  };

  const publishState = () => {
    const preset = currentPreset();
    const base = currentBase();
    const effective = preset === 'custom'
      ? customDesign(customSections)
      : (base ? effectiveDesign(base, overrides) : {});
    updateAccentVisibility(effective.frameStyle);
    editor.dispatchEvent(new CustomEvent('mosaic-design-change', {
      bubbles: true,
      detail: {
        preset,
        overrides: clone(overrides),
        effective,
        display: displayState(),
      },
    }));
  };

  const updateStatus = () => {
    const preset = currentPreset();
    const count = countLeaves(overrides);
    const dirty = preset !== savedPreset || canonicalJson(overrides) !== canonicalJson(savedDocument);
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
      modifications.textContent = preset !== 'custom' && count > 0
        ? `${presetLabel(preset)} · ${count} ${editor.dataset.modifiedLabel.toLowerCase()}`
        : '';
    }
    if (resetAll) {
      const resetLabel = preset === 'site' ? editor.dataset.siteDefaultLabel : presetLabel(preset);
      resetAll.textContent = editor.dataset.resetAllTemplate.replace('%s', resetLabel);
      resetAll.hidden = preset === 'custom';
      resetAll.disabled = preset === 'custom' || count === 0;
    }
  };

  const updateMode = () => {
    const custom = currentPreset() === 'custom';
    customSections.forEach((section) => {
      section.hidden = !custom;
    });
    configuratorSection.hidden = false;
    editor.classList.toggle('is-custom', custom);
    if (!custom) {
      applyEffectiveValues(editor, currentBase(), overrides, controlPaths);
    }
    updateStatus();
    publishState();
  };

  const persist = () => {
    storage.value = JSON.stringify(overrides);
    storage.dispatchEvent(new Event('change', { bubbles: true }));
    updateStatus();
    publishState();
  };

  presetSelector.addEventListener('change', updateMode);
  proxyControls.forEach((proxy) => {
    const canonical = canonicalControl(proxy.dataset.designProxy);
    if (!canonical) {
      return;
    }
    proxy.value = canonicalValue(canonical, proxy.dataset.designProxy);
    proxy.addEventListener('change', () => {
      if (canonical.type === 'checkbox') {
        canonical.checked = proxy.value === '1';
        canonical.value = canonical.checked ? '1' : '0';
      } else {
        canonical.value = proxy.value;
      }
      canonical.dispatchEvent(new Event('input', { bubbles: true }));
      canonical.dispatchEvent(new Event('change', { bubbles: true }));
      publishState();
    });
    const syncProxy = () => {
      proxy.value = canonicalValue(canonical, proxy.dataset.designProxy);
      publishState();
    };
    canonical.addEventListener('change', syncProxy);
    canonical.addEventListener('input', syncProxy);
  });
  sheet.addEventListener('change', (event) => {
    if (currentPreset() === 'custom' && isCustomFieldEvent(event)) {
      publishState();
    }
  });
  sheet.addEventListener('input', (event) => {
    if (currentPreset() === 'custom' && isCustomFieldEvent(event)) {
      publishState();
    }
  });
  editor.addEventListener('mosaic-design-change', (event) => {
    renderPreview(editor, event.detail.effective);
    const preview = editor.querySelector('[data-design-live-preview]');
    if (!preview) return;
    preview.style.setProperty('--preview-gap', `${event.detail.display.gap || 0}px`);
    preview.dataset.showCaptions = event.detail.display.showCaptions;
    preview.dataset.enableLightbox = event.detail.display.enableLightbox;
    preview.dataset.enableLoadMore = event.detail.display.enableLoadMore;
    preview.dataset.loadMoreFrame = event.detail.display.loadMoreUseFrameStyle;
    preview.dataset.galleryCaptionAlign = event.detail.display.captionAlign;
  });
  editor.addEventListener('change', (event) => {
    const control = event.target.closest('[data-design-control]');
    if (!control) {
      return;
    }
    const path = control.dataset.designPath;
    const base = valueAtPath(currentBase(), path);
    if (control.dataset.designKind === 'color' && !/^#[\da-f]{6}$/i.test(control.value)) {
      displayValue(control, valueAtPath(overrides, path) ?? base);
      return;
    }
    const value = controlValue(control, base);
    displayValue(control, value);
    if (canonicalJson(value) === canonicalJson(base)) {
      deletePath(overrides, path);
      updateFieldState(control, false);
    } else {
      setPath(overrides, path, value);
      updateFieldState(control, true);
    }
    persist();
  });

  editor.addEventListener('input', (event) => {
    const control = event.target.closest('[data-design-control][data-design-kind="color"]');
    if (control && /^#[\da-f]{6}$/i.test(control.value)) {
      displayValue(control, control.value);
    }
  });

  editor.addEventListener('click', (event) => {
    const eyedropper = event.target.closest('[data-design-eyedropper]');
    if (eyedropper && window.EyeDropper) {
      const control = eyedropper.closest('[data-design-field]')?.querySelector('[data-design-control]');
      if (control) {
        new window.EyeDropper().open().then(({ sRGBHex }) => {
          control.value = sRGBHex;
          control.dispatchEvent(new Event('change', { bubbles: true }));
        }).catch(() => {});
      }
      return;
    }
    const previewLoadMore = event.target.closest('[data-design-preview-load-more]');
    if (previewLoadMore) {
      const columns = [...editor.querySelectorAll('[data-design-preview-column]')];
      editor.querySelectorAll('[data-design-preview-extra]').forEach((item) => {
        item.hidden = false;
        const shortestColumn = columns.reduce((shortest, column) => (
          column.getBoundingClientRect().height < shortest.getBoundingClientRect().height
            ? column
            : shortest
        ));
        shortestColumn.append(item);
      });
      previewLoadMore.hidden = true;
      return;
    }
    const resetField = event.target.closest('[data-design-reset-field]');
    if (resetField) {
      const control = resetField.closest('[data-design-field]')?.querySelector('[data-design-control]');
      if (control) {
        deletePath(overrides, control.dataset.designPath);
        displayValue(control, valueAtPath(currentBase(), control.dataset.designPath));
        updateFieldState(control, false);
        persist();
      }
      return;
    }
    if (event.target.closest('[data-design-reset-all]')) {
      overrides = {};
      applyEffectiveValues(editor, currentBase(), overrides, controlPaths);
      persist();
    }
  });

  editor.querySelectorAll('[data-design-color-picker]').forEach((picker) => {
    const control = picker.closest('[data-design-field]')?.querySelector('[data-design-control]');
    picker.addEventListener('input', () => {
      control.value = picker.value.toUpperCase();
      control.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });
  if (window.EyeDropper) {
    editor.querySelectorAll('[data-design-eyedropper]').forEach((button) => { button.hidden = false; });
    customSections.filter((section) => ['settings.frameColor', 'settings.frameAccentColor', 'settings.backgroundColor', 'settings.captionColor', 'settings.lbOverlay', 'settings.lbNavColor', 'settings.lbCloseColor', 'settings.lbCaptionColor', 'settings.lbCaptionBg'].includes(section.dataset.id)).forEach((section) => {
      const control = fieldControl(section);
      const button = window.document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-default btn-sm mosaic-design-eyedropper';
      button.textContent = '⌾';
      button.title = editor.dataset.eyedropperLabel;
      button.setAttribute('aria-label', editor.dataset.eyedropperLabel);
      button.addEventListener('click', () => {
        new window.EyeDropper().open().then(({ sRGBHex }) => {
          control.value = sRGBHex;
          control.dispatchEvent(new Event('input', { bubbles: true }));
          control.dispatchEvent(new Event('change', { bubbles: true }));
        }).catch(() => {});
      });
      control.parentElement?.append(button);
    });
  }
  updateMode();
};

document.querySelectorAll('[data-mosaic-design-configurator]').forEach(initializeEditor);
