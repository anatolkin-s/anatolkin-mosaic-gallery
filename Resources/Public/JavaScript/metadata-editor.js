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

const updateInputState = (row, property) => {
  const mode = row.querySelector(`[data-mosaic-mode][data-mosaic-property="${property}"]`);
  const value = row.querySelector(`[data-mosaic-value][data-mosaic-property="${property}"]`);
  if (mode && value) {
    value.disabled = mode.value !== 'custom';
  }
};

const persistRow = (editor, row) => {
  const storage = editor.querySelector('[data-mosaic-metadata-storage]');
  if (!storage) {
    return;
  }
  const uid = Number.parseInt(row.dataset.mosaicFileUid, 10);
  if (!Number.isInteger(uid)) {
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

const initializeEditor = (editor) => {
  if (editor.dataset.mosaicMetadataInitialized === 'true') {
    return;
  }
  editor.dataset.mosaicMetadataInitialized = 'true';
  editor.querySelectorAll('[data-mosaic-file-uid]').forEach((row) => {
    updateInputState(row, 'caption');
    updateInputState(row, 'alt');
  });
  editor.addEventListener('change', (event) => {
    const control = event.target.closest('[data-mosaic-property]');
    const row = control?.closest('[data-mosaic-file-uid]');
    if (!control || !row) {
      return;
    }
    updateInputState(row, control.dataset.mosaicProperty);
    persistRow(editor, row);
  });
  editor.addEventListener('input', (event) => {
    const control = event.target.closest('[data-mosaic-value]');
    const row = control?.closest('[data-mosaic-file-uid]');
    if (control && row) {
      persistRow(editor, row);
    }
  });
  editor.addEventListener('click', (event) => {
    if (event.target.closest('[data-mosaic-convert-legacy]')) {
      convertLegacyCaptions(editor);
    }
  });
};

const initialize = () => {
  document.querySelectorAll('[data-mosaic-metadata-editor]').forEach(initializeEditor);
};

initialize();
