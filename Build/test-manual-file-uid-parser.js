#!/usr/bin/env node
'use strict';

/**
 * Executable contract tests for TYPO3 FormEngine uid_local normalization.
 * Keep parseFileUidLocalValue in sync with Resources/Public/JavaScript/metadata-editor.js.
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

const cases = [
  ['', null],
  ['0', null],
  ['6', 6],
  [6, 6],
  ['  123  ', 123],
  ['sys_file_6', 6],
  ['sys_file_123', 123],
  ['sys_file_0', null],
  ['sys_file_reference_6', null],
  ['sys_file_reference_123', null],
  ['not-a-uid', null],
  ['sys_file_', null],
  ['sys_file_abc', null],
  [-1, null],
];

let failures = 0;

for (const [input, expected] of cases) {
  const actual = parseFileUidLocalValue(input);
  if (actual !== expected) {
  failures += 1;
    console.error(
      `FAIL parseFileUidLocalValue(${JSON.stringify(input)}) => ${JSON.stringify(actual)}, expected ${JSON.stringify(expected)}`,
    );
  }
}

if (failures > 0) {
  console.error(`${failures} uid_local parser test(s) failed.`);
  process.exit(1);
}

console.log('Manual file uid parser tests passed.');
