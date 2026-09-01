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
const CUSTOM_FIELD_GROUPS = {
  'settings.captionColor': 'gallery',
  'settings.applyTo': 'gallery',
  'settings.frameColor': 'frame',
  'settings.frameAccentColor': 'frame',
  'settings.frameWidth': 'frame',
  'settings.frameStyle': 'frame',
  'settings.borderRadius': 'frame',
  'settings.shadow': 'frame',
  'settings.backgroundColor': 'frame',
  'settings.lbOverlay': 'lightbox',
  'settings.lbOverlayAlpha': 'lightbox',
  'settings.lbNavColor': 'lightbox',
  'settings.lbCloseColor': 'lightbox',
  'settings.lbCaptionColor': 'lightbox',
  'settings.lbCaptionBg': 'lightbox',
  'settings.lbCaptionBgAlpha': 'lightbox',
  'settings.lbCaptionAlign': 'lightbox',
  'settings.lbCaptionSize': 'lightbox',
  'settings.lbCaptionStyle': 'lightbox',
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
const SOURCE_FOLDER = 'folder';
const SOURCE_MANUAL = 'manual';
const IMAGE_SOURCE_FIELD_IDS = [
  'settings.source',
  'settings.folder',
  'settings.recursive',
  'settings.sortBy',
  'settings.sortDir',
  'settings.useFalCaptions',
];
const FOLDER_ONLY_FIELD_IDS = [
  'settings.folder',
  'settings.recursive',
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
const workspaceRegistry = new WeakMap();

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

const isTruthyBoolean = (value) => value === true || value === 1 || value === '1';

const readControlValue = (control) => {
  if (!control) {
    return '';
  }
  if (control.type === 'checkbox') {
    return control.checked ? '1' : '0';
  }
  return control.value ?? '';
};

const writeControlValue = (control, value) => {
  if (!control) {
    return;
  }
  if (control.type === 'checkbox') {
    control.checked = isTruthyBoolean(value);
    return;
  }
  control.value = value == null ? '' : String(value);
};

const normalizeSingleCheckboxValue = (value) => (isTruthyBoolean(value) ? '1' : '0');

const isFormEngineCheckbox = (control) => Boolean(
  control
  && control.type === 'checkbox'
  && control.dataset?.formengineInputName,
);

const formEngineCheckboxStorage = (control) => {
  if (!isFormEngineCheckbox(control)) {
    return null;
  }
  const scope = control.closest('.form-section') ?? control.closest('form') ?? document;
  const canonicalName = control.dataset.formengineInputName;
  return [...scope.querySelectorAll('input[type="hidden"][name]')]
    .find((candidate) => candidate.name === canonicalName) ?? null;
};

const notifyFormEngineControlChange = (control) => {
  if (!control) {
    return;
  }
  control.dispatchEvent(new Event('input', { bubbles: true }));
  control.dispatchEvent(new Event('change', { bubbles: true }));
};

const readCanonicalControlValue = (control) => {
  if (!control) {
    return '';
  }
  if (isFormEngineCheckbox(control)) {
    const hidden = formEngineCheckboxStorage(control);
    if (hidden) {
      return normalizeSingleCheckboxValue(hidden.value);
    }
    return readControlValue(control);
  }
  const section = control.closest('.form-section');
  if (section && control.dataset?.formengineInputName) {
    return formEngineControlValue(section, control);
  }
  return readControlValue(control);
};

const writeCanonicalControlValue = (control, value, options = {}) => {
  if (!control) {
    return false;
  }
  const notify = options.notify !== false;
  if (isFormEngineCheckbox(control)) {
    const normalized = normalizeSingleCheckboxValue(value);
    const current = readCanonicalControlValue(control);
    if (current === normalized) {
      control.checked = isTruthyBoolean(normalized);
      if (notify) {
        notifyFormEngineControlChange(control);
      }
      return true;
    }
    const hidden = formEngineCheckboxStorage(control);
    if (!hidden) {
      return false;
    }
    if (options.notify === false) {
      control.checked = isTruthyBoolean(normalized);
      hidden.value = normalized;
      return readCanonicalControlValue(control) === normalized;
    }
    control.checked = isTruthyBoolean(current);
    control.click();
    return readCanonicalControlValue(control) === normalized;
  }
  writeControlValue(control, value);
  if (notify) {
    notifyFormEngineControlChange(control);
  }
  return true;
};

const updateCompactValueWidth = (control) => {
  if (!control || !control.hasAttribute('data-design-compact-value')) {
    return;
  }
  const length = String(control.value ?? '').length;
  const ch = Math.min(8, Math.max(3, length + 1));
  control.style.setProperty('--mosaic-compact-ch', String(ch));
};

const controlValue = (control, base) => {
  switch (control.dataset.designKind) {
    case 'boolean':
      return isTruthyBoolean(readControlValue(control));
    case 'integer': {
      const value = Number.parseInt(readControlValue(control), 10);
      return Number.isInteger(value) && value >= 0 ? value : base;
    }
    case 'number':
    case 'alpha': {
      const value = Number(readControlValue(control));
      if (!Number.isFinite(value) || value < 0) {
        return base;
      }
      const clamped = control.dataset.designKind === 'alpha' ? Math.min(1, value) : value;
      return String(clamped);
    }
    default:
      return readControlValue(control);
  }
};

const displayValue = (control, value) => {
  if (control.dataset.designKind === 'boolean' || control.type === 'checkbox') {
    writeControlValue(control, isTruthyBoolean(value) ? '1' : '0');
  } else {
    writeControlValue(control, value);
  }
  updateCompactValueWidth(control);
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

const fieldControl = (section) => {
  if (!section) {
    return null;
  }
  const checkbox = section.querySelector('input[type="checkbox"]');
  if (checkbox) {
    return checkbox;
  }
  const formEngineInput = section.querySelector(
    'input[data-formengine-input-name]:not([type="hidden"])',
  );
  if (formEngineInput) {
    return formEngineInput;
  }
  return section.querySelector(
    'select, input.form-control:not([type="hidden"]), input[type="text"], input[type="number"]',
  );
};

const formEngineControlValue = (section, control) => {
  if (!control) {
    return '';
  }

  const visibleValue = String(control.value ?? '');
  if (visibleValue !== '') {
    return visibleValue;
  }

  const canonicalName = control.dataset?.formengineInputName;
  if (!canonicalName) {
    return visibleValue;
  }

  const hidden = [...section.querySelectorAll('input[type="hidden"][name]')]
    .find((candidate) => candidate.name === canonicalName);

  return hidden?.value ?? visibleValue;
};

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
    let value = formEngineControlValue(section, control);
    if (kind === 'boolean') {
      value = isFormEngineCheckbox(control)
        ? isTruthyBoolean(readCanonicalControlValue(control))
        : control.checked;
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

const patternedPreviewSpan = (item, maxItemsPerRow) => {
  const ratio = Number(item.dataset.previewRatio) || 1;
  const orientation = ratio < 0.8 ? 'portrait' : (ratio > 1.8 ? 'wide' : (ratio > 1.15 ? 'landscape' : 'square'));
  const weight = item.dataset.previewWeight || 'medium';
  const minimumSpan = Math.ceil(24 / maxItemsPerRow);
  const weightFactor = { small: 1, medium: 1.15, large: 1.55 }[weight] ?? 1.15;
  const orientationFactor = { portrait: 0.9, square: 1, landscape: 1.1, wide: 1.25 }[orientation];
  return Math.min(24, Math.max(minimumSpan, Math.round(minimumSpan * weightFactor * orientationFactor)));
};

const layoutPatternedPreview = (preview, maxItemsPerRow) => {
  const grid = preview.querySelector('.mosaic-design-preview__items');
  const items = [...preview.querySelectorAll('.mosaic-design-preview__item')];
  if (!grid) return;
  if (preview.dataset.layoutMode !== 'patterned') {
    items.forEach((item) => {
      item.style.gridColumnEnd = '';
      item.style.gridRowEnd = '';
    });
    return;
  }
  items.forEach((item) => {
    item.style.gridColumnEnd = `span ${patternedPreviewSpan(item, maxItemsPerRow)}`;
    item.style.gridRowEnd = '';
  });
  window.requestAnimationFrame(() => {
    const style = window.getComputedStyle(grid);
    const rowUnit = Number.parseFloat(style.gridAutoRows) || 4;
    const gap = Number.parseFloat(style.rowGap) || 0;
    items.forEach((item) => {
      const height = Math.max(item.scrollHeight, item.getBoundingClientRect().height);
      item.style.gridRowEnd = `span ${Math.max(1, Math.ceil((height + gap) / (rowUnit + gap)))}`;
    });
  });
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
  const frameWidth = Math.max(0, Number(effective.frameWidth) || 0);
  setProperty('--preview-frame-band-key', `${frameWidth === 0 ? 0 : Math.min(frameWidth, Math.max(1, frameWidth * 0.1))}px`);
  setProperty('--preview-frame-band-quarter', `${frameWidth * 0.25}px`);
  setProperty('--preview-frame-band-third', `${frameWidth / 3}px`);
  setProperty('--preview-frame-band-forty', `${frameWidth * 0.4}px`);
  setProperty('--preview-frame-band-forty-five', `${frameWidth * 0.45}px`);
  setProperty('--preview-frame-band-sixty', `${frameWidth * 0.6}px`);
  setProperty('--preview-frame-band-two-thirds', `${frameWidth * 2 / 3}px`);
  setProperty('--preview-frame-band-three-quarters', `${frameWidth * 0.75}px`);
  setProperty('--preview-frame-band-total', `${frameWidth}px`);
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

let compactHelpSequence = 0;

const addCompactHelp = (section) => {
  const help = section?.querySelector([
    '.form-text',
    '.form-description',
    '[data-formengine-description]',
    '[data-formengine-field-description]',
  ].join(', '));
  const label = section?.querySelector('.form-label');
  const helpText = help?.textContent.trim();
  if (!section || !help || !label || !helpText || section.querySelector('[data-mosaic-compact-help]')) {
    return;
  }

  section.dataset.mosaicCompactHelpSection = 'true';
  help.dataset.mosaicCompactHelpDescription = 'true';
  if (!help.id) {
    compactHelpSequence += 1;
    help.id = `mosaic-compact-help-${compactHelpSequence}`;
  }
  help.setAttribute('role', 'tooltip');
  const labelRow = document.createElement('div');
  labelRow.className = 'mosaic-layout-header__label-row';
  label.before(labelRow);
  labelRow.append(label);
  const helpButton = document.createElement('button');
  helpButton.type = 'button';
  helpButton.className = 'mosaic-layout-header__help';
  helpButton.dataset.mosaicCompactHelp = 'true';
  helpButton.textContent = 'ⓘ';
  helpButton.setAttribute('aria-label', helpText);
  helpButton.setAttribute('aria-describedby', help.id);
  helpButton.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
  });
  labelRow.append(helpButton);
  labelRow.append(help);
  const control = section.querySelector('input, select');
  control?.setAttribute('aria-description', helpText);
  if (control) {
    const describedBy = new Set((control.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean));
    describedBy.add(help.id);
    control.setAttribute('aria-describedby', [...describedBy].join(' '));
  }
};

const unwrapFormEngineScalar = (value) => {
  if (Array.isArray(value)) {
    if (Object.prototype.hasOwnProperty.call(value, 'vDEF')) {
      return unwrapFormEngineScalar(value.vDEF);
    }
    if (value.length === 0) {
      return null;
    }
    if (value.length === 1) {
      return unwrapFormEngineScalar(value[0]);
    }
    return null;
  }
  if (value && typeof value === 'object') {
    return null;
  }
  return value;
};

const normalizeGallerySource = (value) => {
  const scalar = unwrapFormEngineScalar(value);
  if (scalar === null || scalar === undefined) {
    return SOURCE_FOLDER;
  }
  return String(scalar).trim() === SOURCE_MANUAL ? SOURCE_MANUAL : SOURCE_FOLDER;
};

const readGallerySource = (control) => {
  if (!control) {
    return SOURCE_FOLDER;
  }
  return normalizeGallerySource(readControlValue(control));
};

const resolveMosaicWorkspaceTabs = (layoutSheet, imagesSheet) => {
  if (!layoutSheet || !imagesSheet || layoutSheet === imagesSheet) {
    return null;
  }
  const tabContent = layoutSheet.parentElement;
  if (!tabContent?.classList.contains('tab-content')) {
    return null;
  }
  const panes = [...tabContent.querySelectorAll(':scope > .tab-pane')];
  if (!panes.includes(layoutSheet) || !panes.includes(imagesSheet)) {
    return null;
  }
  const nav = tabContent.previousElementSibling?.matches('.nav-tabs, .nav, ul.nav')
    ? tabContent.previousElementSibling
    : tabContent.parentElement?.querySelector('.nav-tabs, .nav, ul.nav');
  return {
    tabContent,
    nav,
    layoutSheet,
    imagesSheet,
    layoutPaneId: layoutSheet.id,
    imagesPaneId: imagesSheet.id,
  };
};

const activateImagesWorkspaceTab = (workspaces) => {
  const { layoutSheet, imagesSheet } = workspaces;
  const tabs = resolveMosaicWorkspaceTabs(layoutSheet, imagesSheet);
  if (!tabs) {
    return false;
  }

  const { nav, tabContent, imagesPaneId, layoutPaneId } = tabs;
  if (imagesPaneId && nav) {
    const imagesTrigger = nav.querySelector(
      `[href="#${CSS.escape(imagesPaneId)}"], [data-bs-target="#${CSS.escape(imagesPaneId)}"]`,
    );
    if (imagesTrigger && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
      bootstrap.Tab.getOrCreateInstance(imagesTrigger).show();
      return imagesSheet.classList.contains('active');
    }
  }

  tabContent.querySelectorAll(':scope > .tab-pane').forEach((pane) => {
    pane.classList.remove('active', 'show');
  });
  imagesSheet.classList.add('active', 'show');
  layoutSheet.classList.remove('active', 'show');

  if (nav && imagesPaneId && layoutPaneId) {
    nav.querySelectorAll('.nav-link, [data-bs-toggle="tab"]').forEach((link) => {
      const target = (link.getAttribute('href') ?? link.getAttribute('data-bs-target') ?? '').replace(/^#/, '');
      const isImages = target === imagesPaneId;
      link.classList.toggle('active', isImages);
      link.setAttribute('aria-selected', isImages ? 'true' : 'false');
      link.closest('.nav-item')?.classList.toggle('active', isImages);
    });
  }

  return imagesSheet.classList.contains('active');
};

const applyFolderOnlyVisibility = (imagesSheet, source) => {
  const isManual = source === SOURCE_MANUAL;
  FOLDER_ONLY_FIELD_IDS.forEach((fieldId) => {
    const section = imagesSheet.querySelector(`.form-section[data-id="${fieldId}"]`);
    if (section) {
      section.hidden = isManual;
    }
  });
};

const applyManualFieldVisibility = (imagesSheet, source) => {
  const manualSection = imagesSheet.querySelector('.form-section[data-id="tx_anatolkinmosaicgallery_images"]');
  if (manualSection) {
    manualSection.hidden = source !== SOURCE_MANUAL;
  }
};

const applyLegacyCaptionsVisibility = (imagesSheet, source) => {
  const disclosure = imagesSheet.querySelector('.mosaic-legacy-captions-disclosure');
  if (disclosure) {
    disclosure.hidden = source === SOURCE_MANUAL;
  }
};

const applySourceAwareWorkspace = (workspaces, source) => {
  const { imagesSheet } = workspaces;
  const resolvedSource = source ?? SOURCE_FOLDER;
  applyFolderOnlyVisibility(imagesSheet, resolvedSource);
  applyManualFieldVisibility(imagesSheet, resolvedSource);
  applyLegacyCaptionsVisibility(imagesSheet, resolvedSource);
  imagesSheet.dataset.mosaicSourceMode = resolvedSource;
  imagesSheet.classList.toggle('mosaic-source-manual', resolvedSource === SOURCE_MANUAL);
  imagesSheet.classList.toggle('mosaic-source-folder', resolvedSource !== SOURCE_MANUAL);
};

const bindSourceAwareWorkspace = (workspaces) => {
  const sourceControl = workspaces.imagesSheet.querySelector(
    '.form-section[data-id="settings.source"] select, .form-section[data-id="settings.source"] input',
  );
  const sync = () => applySourceAwareWorkspace(workspaces, readGallerySource(sourceControl));
  sync();
  sourceControl?.addEventListener('change', sync);
};

const mountContinueToImages = (editor, workspaces) => {
  const { layoutSheet } = workspaces;
  if (!layoutSheet || layoutSheet.querySelector('[data-mosaic-continue-to-images]')) {
    return;
  }
  const nav = document.createElement('div');
  nav.className = 'mosaic-layout-continue';
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'btn btn-default btn-sm mosaic-layout-continue__button';
  button.dataset.mosaicContinueToImages = 'true';
  button.textContent = editor.dataset.continueToImagesLabel ?? 'Continue to Images →';
  button.addEventListener('click', () => activateImagesWorkspaceTab(workspaces));
  nav.append(button);
  layoutSheet.append(nav);
};

const consolidateWorkspaces = (editor) => {
  const imagesSheet = editor.closest('.tab-pane');
  const tabContent = imagesSheet?.parentElement;
  const layoutSheet = [...(tabContent?.querySelectorAll(':scope > .tab-pane') ?? [])].find(
    (pane) => pane.querySelector(':scope > .form-section[data-id="settings.source"]'),
  );
  if (!imagesSheet || !layoutSheet || imagesSheet === layoutSheet) {
    return { layoutSheet: imagesSheet ?? null, imagesSheet: imagesSheet ?? null };
  }

  layoutSheet.classList.add('mosaic-layout-sheet');
  imagesSheet.classList.add('mosaic-images-sheet');

  const designSections = [...imagesSheet.querySelectorAll(':scope > .form-section')];
  designSections.forEach((section) => layoutSheet.append(section));

  const form = editor.closest('form');
  const metadataEditor = form?.querySelector('[data-mosaic-metadata-editor]');
  const metadataSection = metadataEditor?.closest('.form-section');
  const manualImagesSection = form?.querySelector(
    '.form-section[data-id="tx_anatolkinmosaicgallery_images"]',
  );

  const imagesHeader = document.createElement('div');
  imagesHeader.className = 'mosaic-images-header';
  const imagesSourceRow = document.createElement('div');
  imagesSourceRow.className = 'mosaic-layout-header__row mosaic-layout-header__row--source mosaic-images-header__row--source';
  imagesSourceRow.dataset.layoutHeaderRow = 'source';
  imagesHeader.append(imagesSourceRow);
  imagesSheet.prepend(imagesHeader);

  const moveFromLayout = (fieldName, target) => {
    const section = layoutSheet.querySelector(`:scope > .form-section[data-id="${fieldName}"]`);
    if (section) {
      target.append(section);
    }
    return section;
  };
  IMAGE_SOURCE_FIELD_IDS.forEach((fieldName) => moveFromLayout(fieldName, imagesSourceRow));

  if (manualImagesSection) {
    manualImagesSection.classList.add('mosaic-manual-images');
    if (metadataSection && imagesSheet.contains(metadataSection)) {
      imagesSheet.insertBefore(manualImagesSection, metadataSection);
    } else {
      imagesHeader.insertAdjacentElement('afterend', manualImagesSection);
    }
  }

  const captionsSection = layoutSheet.querySelector(':scope > .form-section[data-id="settings.captions"]');
  if (metadataSection) {
    imagesSheet.append(metadataSection);
  }
  if (captionsSection) {
    captionsSection.classList.add('mosaic-legacy-captions');
    const legacyDetails = document.createElement('details');
    legacyDetails.className = 'mosaic-legacy-captions-disclosure';
    const legacySummary = document.createElement('summary');
    legacySummary.textContent = captionsSection.querySelector('.form-label')?.textContent.trim() ?? '';
    legacyDetails.append(legacySummary, captionsSection);
    imagesSheet.append(legacyDetails);
  }

  const header = document.createElement('div');
  header.className = 'mosaic-layout-header';
  const settingsRow = document.createElement('div');
  settingsRow.className = 'mosaic-layout-header__row mosaic-layout-header__row--settings';
  settingsRow.dataset.layoutHeaderRow = 'settings';
  header.append(settingsRow);
  layoutSheet.prepend(header);
  editor.prepend(settingsRow);

  const moveSection = (fieldName, target) => {
    const section = layoutSheet.querySelector(`:scope > .form-section[data-id="${fieldName}"]`);
    if (section) {
      target.append(section);
    }
    return section;
  };
  LAYOUT_SETTINGS_FIELD_IDS.forEach((fieldName) => moveSection(fieldName, settingsRow));

  const metadataFallback = imagesSourceRow.querySelector('.form-section[data-id="settings.useFalCaptions"]');
  const maxWidth = settingsRow.querySelector('.form-section[data-id="settings.maxWidth"]');
  const maxItemsPerRow = settingsRow.querySelector('.form-section[data-id="settings.maxItemsPerRow"]');
  if (metadataFallback) {
    metadataFallback.dataset.mosaicInlineCheckbox = 'true';
  }
  addCompactHelp(maxWidth);
  addCompactHelp(maxItemsPerRow);

  const workspaces = { layoutSheet, imagesSheet, imagesSourceRow };
  workspaceRegistry.set(editor, workspaces);
  return workspaces;
};

const PROXY_CANONICAL_FIELD_IDS = new Set([
  'settings.gap',
  'settings.showCaptions',
  'settings.captionAlign',
  'settings.enableLightbox',
  'settings.enableLoadMore',
  'settings.loadMoreUseFrameStyle',
]);

const isProxyCanonicalSection = (node) => {
  if (!(node instanceof Element)) {
    return false;
  }
  if (node.matches('.form-section[data-id]')) {
    return PROXY_CANONICAL_FIELD_IDS.has(node.dataset.id ?? '');
  }
  return [...node.querySelectorAll('.form-section[data-id]')].some(
    (section) => PROXY_CANONICAL_FIELD_IDS.has(section.dataset.id ?? ''),
  );
};

const initializeEditor = (editor) => {
  if (editor.dataset.mosaicDesignInitialized === 'true') {
    return;
  }
  editor.dataset.mosaicDesignInitialized = 'true';

  const storage = editor.querySelector('[data-design-storage]');
  const workspaces = consolidateWorkspaces(editor);
  const sheet = workspaces.layoutSheet;
  const presetSection = sheet?.querySelector(':scope > .form-section[data-id="settings.designPreset"]');
  const configuratorSection = editor.closest('.form-section[data-id="settings.designOverrides"]');
  const presetSelector = presetSection?.querySelector('select');
  if (!storage || !sheet || !presetSelector || !configuratorSection) {
    return;
  }
  bindSourceAwareWorkspace(workspaces);
  mountContinueToImages(editor, workspaces);

  const customSections = [...sheet.querySelectorAll(':scope > .form-section')].filter(
    (section) => Object.prototype.hasOwnProperty.call(CUSTOM_FIELDS, section.dataset.id),
  );
  const presetSlot = editor.querySelector('[data-design-preset-slot]');
  if (presetSlot) {
    presetSlot.append(presetSection);
  }
  addCompactHelp(presetSection);
  const toolbar = editor.querySelector('.mosaic-design-configurator__toolbar');
  const settingsRow = editor.querySelector('[data-layout-header-row="settings"]');
  const previewHeading = editor.querySelector('.mosaic-design-preview__heading');
  const status = editor.querySelector('[data-design-status]');
  const resetAll = editor.querySelector('[data-design-reset-all]');
  if (previewHeading && status && resetAll) {
    const previewState = document.createElement('div');
    previewState.className = 'mosaic-design-preview__state';
    previewState.append(status, resetAll);
    previewHeading.append(previewState);
  }
  if (toolbar && settingsRow) {
    settingsRow.prepend(toolbar);
  }
  customSections.forEach((section) => {
    const group = CUSTOM_FIELD_GROUPS[section.dataset.id];
    const slot = editor.querySelector(`[data-design-custom-group="${group}"]`);
    if (slot) {
      slot.append(section);
    }
  });
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
  const listProxyControls = () => [...editor.querySelectorAll('[data-design-proxy]')];
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
  const proxyDefaultValue = (proxy) => {
    if (!proxy || !Object.prototype.hasOwnProperty.call(proxy.dataset, 'designProxyDefault')) {
      return null;
    }
    return String(proxy.dataset.designProxyDefault);
  };
  const applyValueToCanonical = (control, value, options = {}) => {
    if (!control) {
      return;
    }
    if (isFormEngineCheckbox(control)) {
      writeCanonicalControlValue(control, value, options);
      return;
    }
    writeControlValue(control, value);
  };
  const liveCanonicalValue = (control) => readCanonicalControlValue(control);
  const resolveInitialProxyValue = (control, proxy = null) => {
    if (control && isFormEngineCheckbox(control)) {
      const hidden = formEngineCheckboxStorage(control);
      if (hidden) {
        return normalizeSingleCheckboxValue(hidden.value);
      }
    }
    return initialProxyValue(control, proxy);
  };
  const initialProxyValue = (control, proxy = null) => {
    const proxyDefault = proxyDefaultValue(proxy);
    if (!control) {
      return proxyDefault ?? '';
    }
    if (control.type === 'checkbox') {
      if (control.checked) {
        return '1';
      }
      // Unchecked boxes are not yet authoritative on new records before FormEngine
      // applies DS defaults; prefer the server-provided creation default when present.
      if (proxyDefault !== null) {
        return proxyDefault;
      }
      return '0';
    }
    const current = String(control.value ?? '').trim();
    if (current !== '') {
      return control.value;
    }
    const defaultValue = String(control.defaultValue ?? '').trim();
    if (defaultValue !== '') {
      return control.defaultValue;
    }
    const formEngineDefault = String(
      control.dataset?.formengineDefaultValue
        ?? control.getAttribute('data-formengine-default-value')
        ?? '',
    ).trim();
    if (formEngineDefault !== '') {
      return formEngineDefault;
    }
    if (proxyDefault !== null) {
      return proxyDefault;
    }
    return control.value ?? '';
  };
  const layoutModeControl = canonicalControl('settings.layoutMode');
  const maxItemsPerRowControl = canonicalControl('settings.maxItemsPerRow');
  const maxItemsPerRowSection = maxItemsPerRowControl?.closest('.form-section[data-id="settings.maxItemsPerRow"]');
  const updatePreviewLayout = () => {
    const preview = editor.querySelector('[data-design-live-preview]');
    if (!preview) {
      return;
    }
    const value = String(layoutModeControl?.value ?? 'masonry');
    preview.dataset.layoutMode = ['masonry', 'mosaic', 'patterned', 'justified', 'grid'].includes(value) ? value : 'masonry';
    const densityValue = Number.parseInt(maxItemsPerRowControl?.value ?? '6', 10);
    const density = [4, 5, 6, 7, 8].includes(densityValue) ? densityValue : 6;
    preview.dataset.maxItemsPerRow = String(density);
    const densityInactive = preview.dataset.layoutMode !== 'patterned';
    if (maxItemsPerRowSection) {
      maxItemsPerRowSection.dataset.patternedDensityInactive = String(densityInactive);
      maxItemsPerRowSection.toggleAttribute('inert', densityInactive);
      maxItemsPerRowSection.setAttribute('aria-disabled', String(densityInactive));
    }
    layoutPatternedPreview(preview, density);
  };
  layoutModeControl?.addEventListener('change', updatePreviewLayout);
  maxItemsPerRowControl?.addEventListener('change', updatePreviewLayout);
  updatePreviewLayout();
  editor.querySelectorAll('.mosaic-design-preview__item img').forEach((image) => {
    if (!image.complete) {
      image.addEventListener('load', updatePreviewLayout, { once: true });
    }
  });
  const patternedPreviewGrid = editor.querySelector('.mosaic-design-preview__items');
  if (patternedPreviewGrid && typeof ResizeObserver === 'function') {
    let observedPatternedPreviewWidth = patternedPreviewGrid.clientWidth;
    new ResizeObserver(() => {
      const width = patternedPreviewGrid.clientWidth;
      if (width !== observedPatternedPreviewWidth) {
        observedPatternedPreviewWidth = width;
        updatePreviewLayout();
      }
    }).observe(patternedPreviewGrid);
  }
  const displayState = () => Object.fromEntries(listProxyControls().map(
    (proxy) => [proxy.dataset.designProxy.replace('settings.', ''), readControlValue(proxy)],
  ));
  const initialProxyValues = new Map();
  let proxyBindingController = new AbortController();
  let proxySyncLock = false;
  const updateProxyFieldState = (proxy) => {
    if (!proxy) {
      return;
    }
    const fieldName = proxy.dataset.designProxy;
    const initial = initialProxyValues.get(fieldName);
    const current = readControlValue(proxy);
    const modified = initial !== undefined && canonicalJson(current) !== canonicalJson(initial);
    const reset = proxy.closest('[data-design-proxy-field]')
      ?.querySelector('[data-design-proxy-reset]');
    if (reset) {
      reset.disabled = !modified;
      reset.hidden = !modified;
    }
  };
  const proxyDirty = () => listProxyControls().some((proxy) => {
    const initial = initialProxyValues.get(proxy.dataset.designProxy);
    if (initial === undefined) {
      return false;
    }
    return canonicalJson(readControlValue(proxy)) !== canonicalJson(initial);
  });
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
    const overridesDirty = preset !== savedPreset
      || canonicalJson(overrides) !== canonicalJson(savedDocument);
    const dirty = overridesDirty || proxyDirty();
    const statusPrefix = editor.querySelector('[data-design-status-prefix]');
    const statusName = editor.querySelector('[data-design-status-name]');
    const statusDetail = editor.querySelector('[data-design-status-detail]');
    const resetAll = editor.querySelector('[data-design-reset-all]');
    if (statusPrefix) {
      statusPrefix.textContent = dirty ? editor.dataset.previewingLabel : editor.dataset.savedLabel;
    }
    if (statusName) {
      statusName.textContent = presetLabel(dirty ? preset : savedPreset);
    }
    if (statusDetail) {
      const details = [];
      if (dirty) {
        details.push(editor.dataset.unsavedLabel);
      }
      if (preset !== 'custom' && count > 0) {
        details.push(`${count} ${editor.dataset.modifiedLabel}`);
      }
      statusDetail.textContent = details.length > 0 ? ` · ${details.join(' · ')}` : '';
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

  const bindDisplayProxies = ({ rebindAfterPersist = false } = {}) => {
    proxyBindingController.abort();
    proxyBindingController = new AbortController();
    const { signal } = proxyBindingController;

    if (rebindAfterPersist) {
      initialProxyValues.clear();
    }

    listProxyControls().forEach((proxy) => {
      const fieldName = proxy.dataset.designProxy;
      const resolveCanonical = () => canonicalControl(fieldName);
      const canonical = resolveCanonical();
      const persistedValue = canonical
        ? liveCanonicalValue(canonical)
        : resolveInitialProxyValue(null, proxy);

      if (rebindAfterPersist || !initialProxyValues.has(fieldName)) {
        if (persistedValue !== '' || proxyDefaultValue(proxy) !== null) {
          writeControlValue(proxy, persistedValue);
        }
        initialProxyValues.set(fieldName, canonical
          ? liveCanonicalValue(canonical)
          : readControlValue(proxy));
      }

      updateCompactValueWidth(proxy);
      updateProxyFieldState(proxy);

      if (!canonical) {
        return;
      }

      const canonicalSection = canonical.closest('.form-section');
      if (canonicalSection) {
        canonicalSection.hidden = true;
        canonicalSection.classList.add('mosaic-proxy-storage-field');
      }

      applyValueToCanonical(canonical, readControlValue(proxy), { notify: false });

      proxy.addEventListener('change', () => {
        if (proxySyncLock) {
          return;
        }
        proxySyncLock = true;
        try {
          const liveCanonical = resolveCanonical();
          if (!liveCanonical) {
            return;
          }
          const intended = readControlValue(proxy);
          if (!writeCanonicalControlValue(liveCanonical, intended)) {
            return;
          }
          updateCompactValueWidth(proxy);
          updateProxyFieldState(proxy);
          updateStatus();
          publishState();
        } finally {
          proxySyncLock = false;
        }
      }, { signal });

      proxy.addEventListener('input', () => {
        updateCompactValueWidth(proxy);
      }, { signal });

      const syncProxy = () => {
        if (proxySyncLock) {
          return;
        }
        proxySyncLock = true;
        try {
          const liveCanonical = resolveCanonical();
          if (!liveCanonical) {
            return;
          }
          writeControlValue(proxy, liveCanonicalValue(liveCanonical));
          updateCompactValueWidth(proxy);
          updateProxyFieldState(proxy);
          updateStatus();
          publishState();
        } finally {
          proxySyncLock = false;
        }
      };
      canonical.addEventListener('change', syncProxy, { signal });
      canonical.addEventListener('input', syncProxy, { signal });
    });

    editor.querySelectorAll('[data-design-compact-value]').forEach(updateCompactValueWidth);
  };

  const refreshProxyBaselinesAfterPersist = () => {
    if (!editor.isConnected) {
      return;
    }
    overrides = parseDocument(storage.value);
    bindDisplayProxies({ rebindAfterPersist: true });
    updateStatus();
    publishState();
  };

  bindDisplayProxies();

  if (formScope && typeof MutationObserver === 'function') {
    let refreshScheduled = false;
    const scheduleProxyRefresh = () => {
      if (refreshScheduled) {
        return;
      }
      refreshScheduled = true;
      window.requestAnimationFrame(() => {
        refreshScheduled = false;
        refreshProxyBaselinesAfterPersist();
      });
    };
    new MutationObserver((mutations) => {
      if (mutations.some((mutation) => [...mutation.addedNodes].some(isProxyCanonicalSection))) {
        scheduleProxyRefresh();
      }
    }).observe(formScope, { childList: true, subtree: true });
  }

  const editForm = storage.form || editor.closest('form');
  editForm?.addEventListener('submit', () => {
    window.requestAnimationFrame(() => {
      refreshProxyBaselinesAfterPersist();
    });
  }, { capture: true });

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
  sheet.addEventListener('blur', (event) => {
    if (currentPreset() === 'custom' && isCustomFieldEvent(event)) {
      publishState();
    }
  }, true);
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
    updatePreviewLayout();
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
    const resetProxy = event.target.closest('[data-design-proxy-reset]');
    if (resetProxy) {
      const proxyField = resetProxy.closest('[data-design-proxy-field]');
      const proxy = proxyField?.querySelector('[data-design-proxy]');
      if (!proxy) {
        return;
      }
      const canonical = canonicalControl(proxy.dataset.designProxy);
      const initial = initialProxyValues.get(proxy.dataset.designProxy);
      if (initial === undefined) {
        return;
      }
      if (proxySyncLock) {
        return;
      }
      proxySyncLock = true;
      try {
        writeControlValue(proxy, initial);
        if (canonical && !writeCanonicalControlValue(canonical, initial)) {
          return;
        }
        updateCompactValueWidth(proxy);
        updateProxyFieldState(proxy);
        updateStatus();
        publishState();
      } finally {
        proxySyncLock = false;
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
  }
  updateMode();
};

document.querySelectorAll('[data-mosaic-design-configurator]').forEach(initializeEditor);
