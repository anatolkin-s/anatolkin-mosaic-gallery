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
};

const initialize = () => {
  document.querySelectorAll('[data-mosaic-metadata-editor]').forEach(initializeEditor);
};

initialize();
