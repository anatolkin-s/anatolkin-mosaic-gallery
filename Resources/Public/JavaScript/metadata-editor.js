const SOURCE_MANUAL = 'manual';
const MANUAL_IMAGES_FIELD = 'tx_anatolkinmosaicgallery_images';
const MANUAL_IMAGES_FIELD_MARKER = `[${MANUAL_IMAGES_FIELD}]`;

const editorState = new WeakMap();
const formBootstrapState = new WeakMap();

const createDocument = (value) => {
  try {
    const document = JSON.parse(value);
    if (document && typeof document === 'object' && !Array.isArray(document)) {
      document.schemaVersion = 1;
      document.files = document.files && typeof document.files === 'object' && !Array.isArray(document.files)
        ? document.files
        : {};
      return document;
    }
  } catch (error) {
    // Invalid stored data is replaced only after an editor control is changed.
  }
  return { schemaVersion: 1, files: {} };
};

const getViewHost = (editor) => editor;

const updateInputState = (row, property) => {
  const mode = row.querySelector(`[data-mosaic-mode][data-mosaic-property="${property}"]`);
  const value = row.querySelector(`[data-mosaic-value][data-mosaic-property="${property}"]`);
  if (mode && value) {
    value.disabled = mode.value !== 'custom';
  }
};

const updateSummary = (editor, row, property) => {
  const mode = row.querySelector(`[data-mosaic-mode][data-mosaic-property="${property}"]`);
  const value = row.querySelector(`[data-mosaic-value][data-mosaic-property="${property}"]`);
  const badge = row.querySelector(`[data-mosaic-summary-badge="${property}"]`);
  const summaryValue = row.querySelector(`[data-mosaic-summary-value="${property}"]`);
  const statusState = row.querySelector(`[data-mosaic-status-state="${property}"]`);
  const statusValue = row.querySelector(`[data-mosaic-status-value="${property}"]`);
  if (!mode || !value || !badge || !summaryValue || !statusState || !statusValue) {
    return;
  }
  const labels = {
    custom: editor.dataset.mosaicCustomLabel,
    empty: editor.dataset.mosaicDecorativeLabel,
    inherit: editor.dataset.mosaicInheritLabel,
  };
  badge.textContent = labels[mode.value] ?? labels.inherit;
  summaryValue.textContent = property === 'caption' && mode.value === 'custom' ? value.value : '';
  statusState.textContent = labels[mode.value] ?? labels.inherit;
  statusValue.textContent = mode.value === 'custom' ? value.value : '';
};

const applyView = (editor, view) => {
  const allowedViews = new Set(['grid', 'list', 'table']);
  const nextView = allowedViews.has(view) ? view : 'table';
  const viewHost = getViewHost(editor);
  viewHost.dataset.mosaicImagesView = nextView;
  editor.querySelectorAll('[data-mosaic-images-view-button]').forEach((button) => {
    button.setAttribute('aria-pressed', button.dataset.mosaicImagesViewButton === nextView ? 'true' : 'false');
  });
};

const readPreferredView = () => {
  try {
    return localStorage.getItem('anatolkin-mosaic-gallery.metadata-view') ?? 'table';
  } catch (error) {
    return 'table';
  }
};

const storePreferredView = (view) => {
  try {
    localStorage.setItem('anatolkin-mosaic-gallery.metadata-view', view);
  } catch (error) {
    // A blocked browser preference must not affect metadata editing.
  }
};

const persistRow = (editor, row) => {
  const storage = editor.querySelector('[data-mosaic-metadata-storage]');
  if (!storage) {
    return;
  }
  const uid = Number.parseInt(row.dataset.mosaicFileUid, 10);
  if (!Number.isInteger(uid) || uid <= 0) {
    return;
  }
  const document = createDocument(storage.value);
  const readProperty = (property) => ({
    mode: row.querySelector(`[data-mosaic-mode][data-mosaic-property="${property}"]`).value,
    value: row.querySelector(`[data-mosaic-value][data-mosaic-property="${property}"]`).value,
  });
  document.files[String(uid)] = {
    fileUid: uid,
    caption: readProperty('caption'),
    alt: readProperty('alt'),
  };
  storage.value = JSON.stringify(document);
  storage.dispatchEvent(new Event('change', { bubbles: true }));
};

const persistVisibleRows = (editor) => {
  editor.querySelectorAll('[data-mosaic-file-uid]').forEach((row) => {
    persistRow(editor, row);
  });
};

const findManualImagesSection = (formScope) => {
  if (!formScope) {
    return null;
  }

  const byDataId = formScope.querySelector(`.form-section[data-id="${MANUAL_IMAGES_FIELD}"]`);
  if (byDataId) {
    return byDataId;
  }

  const marker = formScope.querySelector([
    `[data-formengine-input-name*="${MANUAL_IMAGES_FIELD_MARKER}"]`,
    `input[name*="${MANUAL_IMAGES_FIELD_MARKER}"]`,
    `[data-form-field*="${MANUAL_IMAGES_FIELD_MARKER}"]`,
    `typo3-formengine-container-files[data-form-field*="${MANUAL_IMAGES_FIELD_MARKER}"]`,
  ].join(', '));
  if (!marker) {
    return null;
  }

  const section = marker.closest('.form-section');
  if (section) {
    section.dataset.id = MANUAL_IMAGES_FIELD;
    return section;
  }

  return marker.closest('.mosaic-manual-images') ?? null;
};

const findManualFilesContainer = (manualSection) => {
  if (!manualSection) {
    return null;
  }

  return manualSection.querySelector(
    `typo3-formengine-container-files[data-form-field*="${MANUAL_IMAGES_FIELD_MARKER}"], `
    + `[data-form-field*="${MANUAL_IMAGES_FIELD_MARKER}"]`,
  );
};

const findManualRecordsContainer = (manualSection) => {
  const filesContainer = findManualFilesContainer(manualSection) ?? manualSection;
  return filesContainer.querySelector('[id$="_records"]')
    ?? filesContainer.querySelector('[data-sortable-record-uids]')
    ?? filesContainer.querySelector('.t3js-inline-container');
};

const parseFileUidLocalValue = (value) => {
  if (value === null || value === undefined) {
    return null;
  }
  if (typeof value === 'number' && Number.isInteger(value) && value > 0) {
    return value;
  }
  const normalized = String(value).trim();
  if (normalized === '') {
    return null;
  }
  if (/^\d+$/.test(normalized)) {
    const numericUid = Number.parseInt(normalized, 10);
    return numericUid > 0 ? numericUid : null;
  }
  const entityMatch = normalized.match(/^sys_file_(\d+)$/);
  if (!entityMatch) {
    return null;
  }
  const entityUid = Number.parseInt(entityMatch[1], 10);
  return entityUid > 0 ? entityUid : null;
};

const readUidLocal = (referenceNode) => {
  const candidates = referenceNode.querySelectorAll([
    'input[name*="[uid_local]"]',
    'input[data-formengine-input-name*="[uid_local]"]',
  ].join(', '));

  for (const input of candidates) {
    const fileUid = parseFileUidLocalValue(input.value);
    if (fileUid) {
      return fileUid;
    }
  }

  return null;
};

const findSourceControl = (editor) => {
  const scopes = [
    editor.closest('.mosaic-images-sheet'),
    editor.closest('form'),
    editor.closest('.tab-content'),
    document,
  ].filter(Boolean);

  for (const scope of scopes) {
    const control = scope.querySelector(
      '.form-section[data-id="settings.source"] select, '
      + '.form-section[data-id="settings.source"] input, '
      + 'select[name*="[source]"], input[name*="[source]"]',
    );
    if (control) {
      return control;
    }
  }

  return null;
};

const readReferenceName = (referenceNode) => {
  const panelTitle = referenceNode.querySelector('.panel-title, .form-irre-object-header-title, [class*="file-name"]');
  const titleText = panelTitle?.textContent?.trim();
  if (titleText) {
    return titleText;
  }
  const imageAlt = referenceNode.querySelector('.form-irre-object-header img[alt], .panel-heading img[alt]')?.getAttribute('alt')?.trim();
  if (imageAlt) {
    return imageAlt;
  }
  return '';
};

const readReferenceThumbnail = (referenceNode) => {
  const image = referenceNode.querySelector(
    '.form-irre-object-header img[src], .panel-heading img[src], img.t3-tceforms-filelistthumbnail[src]',
  );
  return image?.getAttribute('src')?.trim() ?? '';
};

const readReferenceIdentifier = (referenceNode, fallbackName) => {
  const identifier = referenceNode.querySelector('[data-identifier], [data-file-identifier], small');
  const value = identifier?.textContent?.trim();
  return value && value !== fallbackName ? value : '';
};

const readManualFileReferences = (manualSection) => {
  const recordsContainer = findManualRecordsContainer(manualSection);
  if (!recordsContainer) {
    return [];
  }

  const references = [...recordsContainer.querySelectorAll('.form-irre-object')]
    .filter((reference) => !reference.classList.contains('panel-hidden')
      && !reference.classList.contains('t3-form-field-container-inline-hidden'));

  const items = [];
  references.forEach((reference) => {
    const fileUid = readUidLocal(reference);
    if (!fileUid) {
      return;
    }
    const name = readReferenceName(reference);
    items.push({
      fileUid,
      name,
      thumbnailUrl: readReferenceThumbnail(reference),
      identifier: readReferenceIdentifier(reference, name),
    });
  });

  return items;
};

const resolveLiveSource = (editor) => {
  const sourceControl = findSourceControl(editor);
  if (sourceControl) {
    if (sourceControl.type === 'checkbox') {
      return sourceControl.checked ? SOURCE_MANUAL : 'folder';
    }
    return String(sourceControl.value ?? '').trim() === SOURCE_MANUAL ? SOURCE_MANUAL : 'folder';
  }

  const imagesSheet = editor.closest('.mosaic-images-sheet');
  if (imagesSheet?.dataset.mosaicSourceMode === SOURCE_MANUAL) {
    return SOURCE_MANUAL;
  }

  return editor.dataset.mosaicInitialSource === SOURCE_MANUAL ? SOURCE_MANUAL : 'folder';
};

const formatImageCount = (editor, count) => {
  const format = editor.dataset.mosaicImageCountFormat ?? '%s images';
  return format.replace('%s', String(count));
};

const updateImageCount = (editor, count) => {
  const summary = editor.querySelector('.mosaic-metadata-workspace__summary > span:first-child');
  if (summary) {
    summary.textContent = formatImageCount(editor, count);
  }
};

const applyPropertyEntry = (row, property, entry) => {
  const modeControl = row.querySelector(`[data-mosaic-mode][data-mosaic-property="${property}"]`);
  const valueControl = row.querySelector(`[data-mosaic-value][data-mosaic-property="${property}"]`);
  if (!modeControl || !valueControl) {
    return;
  }
  const mode = entry?.mode === 'custom' || entry?.mode === 'empty' ? entry.mode : 'inherit';
  modeControl.value = mode;
  valueControl.value = typeof entry?.value === 'string' ? entry.value : '';
  valueControl.disabled = mode !== 'custom';
};

const initializeMetadataRow = (editor, row) => {
  updateInputState(row, 'caption');
  updateInputState(row, 'alt');
  updateSummary(editor, row, 'caption');
  updateSummary(editor, row, 'alt');
};

const preserveServerFolderItems = (editor) => {
  if (Object.prototype.hasOwnProperty.call(editor.dataset, 'mosaicServerFolderItemsHtml')) {
    return;
  }
  const folderItems = editor.querySelector(':scope > .mosaic-metadata-items');
  editor.dataset.mosaicServerFolderItemsHtml = folderItems?.innerHTML ?? '';
};

const restoreServerFolderItems = (editor) => {
  let folderItems = editor.querySelector(':scope > .mosaic-metadata-items');
  const tableHead = editor.querySelector(':scope > .mosaic-metadata-table-head');
  const html = editor.dataset.mosaicServerFolderItemsHtml ?? '';
  if (!folderItems && html !== '' && tableHead) {
    folderItems = document.createElement('div');
    folderItems.className = 'mosaic-metadata-items';
    tableHead.insertAdjacentElement('afterend', folderItems);
  }
  if (folderItems) {
    folderItems.innerHTML = html;
    folderItems.removeAttribute('hidden');
    folderItems.querySelectorAll('[data-mosaic-file-uid]').forEach((row) => initializeMetadataRow(editor, row));
  }
  tableHead?.removeAttribute('hidden');
  updateImageCount(editor, folderItems?.querySelectorAll('[data-mosaic-file-uid]').length ?? 0);
};

const updateEmptyStates = (editor, source, itemCount) => {
  const scaffold = editor.querySelector('[data-mosaic-manual-live-scaffold]');
  const manualEmpty = editor.querySelector('[data-mosaic-metadata-empty-manual]');
  const folderEmpty = editor.querySelector('[data-mosaic-metadata-empty-folder]');
  const noImagesEmpty = editor.querySelector('[data-mosaic-metadata-empty-noimages]');
  const tableHead = editor.querySelector('[data-mosaic-manual-live-scaffold] .mosaic-metadata-table-head')
    ?? editor.querySelector(':scope > .mosaic-metadata-table-head');

  if (source === SOURCE_MANUAL) {
    scaffold?.removeAttribute('hidden');
    folderEmpty?.setAttribute('hidden', 'hidden');
    noImagesEmpty?.setAttribute('hidden', 'hidden');
    if (manualEmpty) {
      if (itemCount === 0) {
        manualEmpty.removeAttribute('hidden');
      } else {
        manualEmpty.setAttribute('hidden', 'hidden');
      }
    }
    if (tableHead) {
      if (itemCount === 0) {
        tableHead.setAttribute('hidden', 'hidden');
      } else {
        tableHead.removeAttribute('hidden');
      }
    }
    return;
  }

  scaffold?.setAttribute('hidden', 'hidden');
  manualEmpty?.setAttribute('hidden', 'hidden');
  if (tableHead && scaffold?.contains(tableHead)) {
    tableHead.setAttribute('hidden', 'hidden');
  }
  if (folderEmpty && itemCount === 0) {
    folderEmpty.removeAttribute('hidden');
  } else {
    folderEmpty?.setAttribute('hidden', 'hidden');
  }
};

const buildMetadataRow = (editor, reference, storedDocument) => {
  const template = editor.querySelector('[data-mosaic-metadata-item-template]');
  if (!template?.content?.firstElementChild) {
    return null;
  }

  const row = template.content.firstElementChild.cloneNode(true);
  row.dataset.mosaicFileUid = String(reference.fileUid);
  row.classList.remove('is-metadata-expanded');

  const media = row.querySelector('.mosaic-metadata-item__media');
  if (media) {
    media.replaceChildren();
    if (reference.thumbnailUrl) {
      const image = document.createElement('img');
      image.src = reference.thumbnailUrl;
      image.alt = reference.name;
      media.append(image);
    }
  }

  const nameNode = row.querySelector('.mosaic-metadata-item__identity strong');
  if (nameNode) {
    nameNode.textContent = reference.name;
  }
  const identifierNode = row.querySelector('.mosaic-metadata-item__identity small');
  if (identifierNode) {
    identifierNode.textContent = reference.identifier;
  }

  const entry = storedDocument.files[String(reference.fileUid)] ?? {};
  applyPropertyEntry(row, 'caption', entry.caption);
  applyPropertyEntry(row, 'alt', entry.alt);

  const editButton = row.querySelector('[data-mosaic-edit-metadata]');
  if (editButton) {
    editButton.setAttribute('aria-expanded', 'false');
    editButton.setAttribute('aria-label', editor.dataset.mosaicEditLabel ?? '');
    const visibleLabel = editButton.querySelector('.mosaic-metadata-item__edit-label');
    if (visibleLabel) {
      visibleLabel.textContent = editor.dataset.mosaicEditLabel ?? '';
    }
  }

  initializeMetadataRow(editor, row);
  return row;
};

const syncLiveManualMetadata = (editor) => {
  if (resolveLiveSource(editor) !== SOURCE_MANUAL) {
    return;
  }

  const form = editor.closest('form');
  const manualSection = findManualImagesSection(form);
  const scaffold = editor.querySelector('[data-mosaic-manual-live-scaffold]');
  const items = scaffold?.querySelector('.mosaic-metadata-items');
  const template = editor.querySelector('[data-mosaic-metadata-item-template]');
  if (!manualSection || !items || !template) {
    return;
  }

  persistVisibleRows(editor);
  const storage = editor.querySelector('[data-mosaic-metadata-storage]');
  const storedDocument = createDocument(storage?.value ?? '');
  const references = readManualFileReferences(manualSection);

  items.replaceChildren();
  references.forEach((reference) => {
    const row = buildMetadataRow(editor, reference, storedDocument);
    if (row) {
      items.append(row);
    }
  });

  updateImageCount(editor, references.length);
  updateEmptyStates(editor, SOURCE_MANUAL, references.length);
};

const applySourceMode = (editor) => {
  const source = resolveLiveSource(editor);
  editor.dataset.mosaicLiveSource = source;

  if (source === SOURCE_MANUAL) {
    editor.querySelector('[data-mosaic-manual-live-scaffold]')?.removeAttribute('hidden');
    editor.querySelector(':scope > .mosaic-metadata-items')?.setAttribute('hidden', 'hidden');
    editor.querySelector(':scope > .mosaic-metadata-table-head')?.setAttribute('hidden', 'hidden');
    syncLiveManualMetadata(editor);
    return;
  }

  editor.querySelector(':scope > .mosaic-metadata-items')?.removeAttribute('hidden');
  editor.querySelector(':scope > .mosaic-metadata-table-head')?.removeAttribute('hidden');
  restoreServerFolderItems(editor);
  const folderCount = editor.querySelectorAll(':scope > .mosaic-metadata-items [data-mosaic-file-uid]').length;
  updateEmptyStates(editor, source, folderCount);
  updateImageCount(editor, folderCount);
};

const disconnectManualObservers = (state) => {
  state.filesObserver?.disconnect();
  state.recordsObserver?.disconnect();
  state.filesObserver = null;
  state.recordsObserver = null;
  state.filesObserverTarget = null;
  state.recordsObserverTarget = null;
};

const observeManualRelations = (editor) => {
  const state = editorState.get(editor);
  if (!state) {
    return;
  }

  const form = editor.closest('form');
  const manualSection = findManualImagesSection(form);
  const filesContainer = findManualFilesContainer(manualSection);
  if (!filesContainer) {
    disconnectManualObservers(state);
    return;
  }

  let frame = null;
  const scheduleSync = () => {
    if (frame !== null) {
      cancelAnimationFrame(frame);
    }
    frame = requestAnimationFrame(() => {
      frame = null;
      if (resolveLiveSource(editor) === SOURCE_MANUAL) {
        syncLiveManualMetadata(editor);
      }
    });
  };

  const bindRecordsObserver = () => {
    const recordsContainer = findManualRecordsContainer(manualSection);
    if (!recordsContainer) {
      state.recordsObserver?.disconnect();
      state.recordsObserver = null;
      state.recordsObserverTarget = null;
      return;
    }
    if (state.recordsObserverTarget === recordsContainer) {
      return;
    }
    state.recordsObserver?.disconnect();
    state.recordsObserver = new MutationObserver((mutations) => {
      const relevant = mutations.some((mutation) => {
        const target = mutation.target instanceof Element ? mutation.target : mutation.target.parentElement;
        return target && !target.closest('[data-mosaic-metadata-items]');
      });
      if (relevant) {
        scheduleSync();
      }
    });
    state.recordsObserver.observe(recordsContainer, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class', 'hidden'],
    });
    state.recordsObserverTarget = recordsContainer;
    scheduleSync();
  };

  if (state.filesObserverTarget !== filesContainer) {
    state.filesObserver?.disconnect();
    state.filesObserver = new MutationObserver((mutations) => {
      const relevant = mutations.some((mutation) => {
        const target = mutation.target instanceof Element ? mutation.target : mutation.target.parentElement;
        return target && !target.closest('[data-mosaic-metadata-items]');
      });
      if (!relevant) {
        return;
      }
      bindRecordsObserver();
      scheduleSync();
    });
    state.filesObserver.observe(filesContainer, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class', 'hidden', 'id'],
    });
    state.filesObserverTarget = filesContainer;
  }

  bindRecordsObserver();
};

const convertLegacyCaptions = (editor) => {
  const storage = editor.querySelector('[data-mosaic-metadata-storage]');
  if (!storage) {
    return;
  }
  let captions;
  try {
    captions = JSON.parse(editor.dataset.mosaicLegacyCaptions);
  } catch (error) {
    return;
  }
  if (!Array.isArray(captions)) {
    return;
  }

  const document = createDocument(storage.value);
  editor.querySelectorAll('[data-mosaic-file-uid]').forEach((row, index) => {
    const uid = Number.parseInt(row.dataset.mosaicFileUid, 10);
    if (!Number.isInteger(uid)) {
      return;
    }
    const key = String(uid);
    const entry = document.files[key] && typeof document.files[key] === 'object'
      ? document.files[key]
      : {};
    const hasCustomCaption = entry.caption?.mode === 'custom'
      && typeof entry.caption.value === 'string';
    if (!hasCustomCaption) {
      const value = captions[index] ?? '';
      entry.caption = { mode: 'custom', value };
      const modeControl = row.querySelector('[data-mosaic-mode][data-mosaic-property="caption"]');
      const valueControl = row.querySelector('[data-mosaic-value][data-mosaic-property="caption"]');
      if (modeControl && valueControl) {
        modeControl.value = 'custom';
        valueControl.value = value;
        valueControl.disabled = false;
        updateSummary(editor, row, 'caption');
      }
    }
    entry.fileUid = uid;
    document.files[key] = entry;
  });
  document.legacyCaptionsConverted = true;
  storage.value = JSON.stringify(document);
  storage.dispatchEvent(new Event('change', { bubbles: true }));

  editor.querySelector('[data-mosaic-conversion-panel]')?.classList.add('d-none');
  const status = editor.querySelector('[data-mosaic-conversion-status]');
  if (status) {
    const unmatchedCount = Number.parseInt(status.dataset.unmatchedLineCount, 10);
    const extra = unmatchedCount > 0
      ? ` ${status.dataset.extraText.replace('%d', String(unmatchedCount))}`
      : '';
    status.textContent = `${status.dataset.successText}${extra}`;
    status.classList.remove('d-none');
  }
};

const bindEditorListeners = (editor, state) => {
  state.abortController?.abort();
  state.abortController = new AbortController();
  const { signal } = state.abortController;

  editor.addEventListener('change', (event) => {
    const control = event.target.closest('[data-mosaic-property]');
    const row = control?.closest('[data-mosaic-file-uid]');
    if (!control || !row) {
      return;
    }
    updateInputState(row, control.dataset.mosaicProperty);
    updateSummary(editor, row, control.dataset.mosaicProperty);
    persistRow(editor, row);
  }, { signal });

  editor.addEventListener('input', (event) => {
    const control = event.target.closest('[data-mosaic-value]');
    const row = control?.closest('[data-mosaic-file-uid]');
    if (control && row) {
      updateSummary(editor, row, control.dataset.mosaicProperty);
      persistRow(editor, row);
    }
  }, { signal });

  editor.addEventListener('click', (event) => {
    const viewButton = event.target.closest('[data-mosaic-images-view-button]');
    if (viewButton) {
      const view = viewButton.dataset.mosaicImagesViewButton;
      applyView(editor, view);
      storePreferredView(view);
      return;
    }
    const editButton = event.target.closest('[data-mosaic-edit-metadata]');
    if (editButton) {
      const row = editButton.closest('[data-mosaic-file-uid]');
      const expanded = row?.classList.toggle('is-metadata-expanded') ?? false;
      const label = expanded ? editor.dataset.mosaicHideLabel : editor.dataset.mosaicEditLabel;
      editButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      editButton.setAttribute('aria-label', label);
      const visibleLabel = editButton.querySelector('.mosaic-metadata-item__edit-label');
      if (visibleLabel) {
        visibleLabel.textContent = label;
      }
      return;
    }
    if (event.target.closest('.mosaic-metadata-help__button')) {
      event.preventDefault();
      event.stopPropagation();
      return;
    }
    if (event.target.closest('[data-mosaic-convert-legacy]')) {
      convertLegacyCaptions(editor);
    }
  }, { signal });
};

const refreshEditor = (editor) => {
  if (!(editor instanceof Element) || !editor.matches('[data-mosaic-metadata-editor]') || !editor.isConnected) {
    return;
  }

  let state = editorState.get(editor);
  if (!state) {
    state = {};
    editorState.set(editor, state);
  }

  if (editor.dataset.mosaicMetadataInitialized !== 'true') {
    editor.dataset.mosaicMetadataInitialized = 'true';
    bindEditorListeners(editor, state);
    preserveServerFolderItems(editor);
    applyView(editor, readPreferredView());
    editor.querySelectorAll('[data-mosaic-file-uid]').forEach((row) => initializeMetadataRow(editor, row));
  }

  applySourceMode(editor);
  observeManualRelations(editor);
};

const initializeEditor = (editor) => {
  refreshEditor(editor);
};

const editorsInRoot = (root) => {
  if (!(root instanceof Element)) {
    return [];
  }
  if (root.matches('[data-mosaic-metadata-editor]')) {
    return [root];
  }
  return [...root.querySelectorAll('[data-mosaic-metadata-editor]')];
};

const bootstrapForm = (form) => {
  if (!(form instanceof HTMLFormElement)) {
    return;
  }
  editorsInRoot(form).forEach((editor) => {
    refreshEditor(editor);
  });
};

const resolveFormBootstrapRoot = () => (
  document.querySelector('form[name="editform"]')
  ?? document.querySelector('form#EditDocumentController')
  ?? document.querySelector('.t3js-module-body form')
  ?? document.querySelector('form')
);

const setupFormBootstrapObserver = () => {
  const form = resolveFormBootstrapRoot();
  if (!form || formBootstrapState.has(form)) {
    return;
  }

  const observer = new MutationObserver((mutations) => {
    const touched = mutations.some((mutation) => {
      if (mutation.type === 'childList') {
        return [...mutation.addedNodes].some((node) => {
          if (!(node instanceof Element)) {
            return false;
          }
          return node.matches('[data-mosaic-metadata-editor]')
            || node.querySelector('[data-mosaic-metadata-editor]')
            || node.matches(`typo3-formengine-container-files[data-form-field*="${MANUAL_IMAGES_FIELD_MARKER}"]`)
            || node.querySelector(`typo3-formengine-container-files[data-form-field*="${MANUAL_IMAGES_FIELD_MARKER}"]`);
        });
      }
      return false;
    });
    if (touched) {
      bootstrapForm(form);
    }
  });

  observer.observe(form, { childList: true, subtree: true });
  formBootstrapState.set(form, observer);
};

const handleSourceChange = () => {
  const form = resolveFormBootstrapRoot();
  if (form) {
    bootstrapForm(form);
  }
};

const handleWorkspaceConsolidated = (event) => {
  const form = event.target.closest('form') ?? resolveFormBootstrapRoot();
  if (form) {
    bootstrapForm(form);
  }
};

const initialize = () => {
  const form = resolveFormBootstrapRoot();
  if (form) {
    bootstrapForm(form);
    setupFormBootstrapObserver();
  } else {
    document.querySelectorAll('[data-mosaic-metadata-editor]').forEach(initializeEditor);
  }
  document.addEventListener('mosaic:sourcechange', handleSourceChange);
  document.addEventListener('mosaic:workspaceconsolidated', handleWorkspaceConsolidated);
};

initialize();
