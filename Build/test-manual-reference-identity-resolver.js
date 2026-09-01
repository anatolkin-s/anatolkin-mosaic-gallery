#!/usr/bin/env node
'use strict';

/**
 * Executable contract tests for collapsed manual FileReference identity resolution.
 * Keep in sync with Resources/Public/JavaScript/metadata-editor.js.
 */

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

const parseManualReferenceMap = (mapValue) => {
  try {
    const parsed = JSON.parse(mapValue ?? '{}');
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch (error) {
    return {};
  }
};

const ensureRelationIdentityMap = (state) => {
  if (!state.relationIdentityMap) {
    state.relationIdentityMap = {
      byObjectId: {},
      byReferenceUid: {},
    };
  }
  return state.relationIdentityMap;
};

const rememberManualReferenceIdentity = (state, reference, fileUid) => {
  if (!Number.isInteger(fileUid) || fileUid <= 0) {
    return;
  }
  const identityMap = ensureRelationIdentityMap(state);
  const objectId = reference.dataset.objectId ?? '';
  const referenceUid = Number.parseInt(reference.dataset.objectUid ?? '', 10);
  if (objectId !== '') {
    identityMap.byObjectId[objectId] = fileUid;
  }
  if (Number.isInteger(referenceUid) && referenceUid > 0) {
    identityMap.byReferenceUid[String(referenceUid)] = fileUid;
  }
};

const readUidLocal = (referenceNode) => {
  const candidates = [...referenceNode.querySelectorAll('input[name*="[uid_local]"], input[data-formengine-input-name*="[uid_local]"]')];
  for (const input of candidates) {
    const fileUid = parseFileUidLocalValue(input.value);
    if (fileUid) {
      return fileUid;
    }
  }
  return null;
};

const resolveManualReferenceFileUid = (reference, serverMap, state) => {
  const liveUid = readUidLocal(reference);
  if (liveUid) {
    rememberManualReferenceIdentity(state, reference, liveUid);
    return liveUid;
  }

  const identityMap = state?.relationIdentityMap;
  const objectId = reference.dataset.objectId ?? '';
  if (objectId !== '' && identityMap?.byObjectId?.[objectId]) {
    return identityMap.byObjectId[objectId];
  }

  const referenceUid = String(reference.dataset.objectUid ?? '');
  if (referenceUid !== '' && identityMap?.byReferenceUid?.[referenceUid]) {
    return identityMap.byReferenceUid[referenceUid];
  }

  if (referenceUid !== '' && serverMap[referenceUid]) {
    const fileUid = Number.parseInt(String(serverMap[referenceUid]), 10);
    if (Number.isInteger(fileUid) && fileUid > 0) {
      rememberManualReferenceIdentity(state, reference, fileUid);
      return fileUid;
    }
  }

  return null;
};

const isDeletedManualReference = (reference) => (
  reference.classList.contains('t3js-inline-record-deleted')
  || reference.classList.contains('form-irre-object--deleted')
);

const failures = [];

function assertSame(expected, actual, message) {
  if (expected !== actual) {
    failures.push(`${message} (expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)})`);
  }
}

function createReference({
  objectId = 'data-1-tt_content-10-tx_anatolkinmosaicgallery_images-42',
  objectUid = '42',
  classes = 'form-irre-object panel-collapsed t3js-not-loaded',
  uidLocalValues = [],
} = {}) {
  const node = {
    classList: {
      contains: (className) => classes.split(/\s+/).includes(className),
    },
    dataset: {
      objectId,
      objectUid,
    },
    querySelectorAll: (selector) => uidLocalValues.map((value) => ({ value })),
  };
  return node;
}

const state = {};
const serverMap = parseManualReferenceMap('{"42":6,"43":7}');

// Persisted collapsed reference resolves via server map without uid_local body.
assertSame(
  6,
  resolveManualReferenceFileUid(createReference({
    objectId: 'data-1-tt_content-10-tx_anatolkinmosaicgallery_images-42',
    objectUid: '42',
  }), serverMap, state),
  'Collapsed persisted reference resolves file UID from server bootstrap map',
);

// Runtime cache survives subsequent collapse without uid_local.
const cachedReference = createReference({
  objectId: 'data-new-object-1',
  objectUid: '0',
  uidLocalValues: ['sys_file_9'],
});
assertSame(9, resolveManualReferenceFileUid(cachedReference, {}, state), 'Live uid_local seeds runtime identity cache');
assertSame(
  9,
  resolveManualReferenceFileUid(createReference({
    objectId: 'data-new-object-1',
    objectUid: '0',
    classes: 'form-irre-object panel-collapsed t3js-not-loaded',
    uidLocalValues: [],
  }), {}, state),
  'Collapsed unsaved reference keeps file UID from runtime cache',
);

// t3js-not-loaded is not deletion.
const collapsed = createReference({
  objectId: 'data-1-tt_content-10-tx_anatolkinmosaicgallery_images-43',
  objectUid: '43',
  classes: 'form-irre-object t3js-not-loaded panel-collapsed',
});
assertSame(false, isDeletedManualReference(collapsed), 't3js-not-loaded must not be treated as deletion');
assertSame(7, resolveManualReferenceFileUid(collapsed, serverMap, state), 't3js-not-loaded reference still resolves identity');

// Unknown relation without mapping stays unresolved.
assertSame(
  null,
  resolveManualReferenceFileUid(createReference({ objectUid: '999', objectId: 'unknown' }), {}, {}),
  'Unknown relation without identity mapping remains unresolved',
);

if (failures.length > 0) {
  console.error('Manual reference identity resolver tests failed:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('Manual reference identity resolver tests passed.');
