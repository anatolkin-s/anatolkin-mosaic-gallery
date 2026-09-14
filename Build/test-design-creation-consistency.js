'use strict';
// Execute the production creation-sync closure, without a browser dependency.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../Resources/Public/JavaScript/design-configurator.js'), 'utf8');
const start = source.indexOf('  const syncCreationCanonicalValues = () => {');
assert.ok(start >= 0);
const end = source.indexOf('\n  };', start) + '\n  };'.length;
const sync = new Function('editor', 'currentPreset', 'effectiveDesign', 'currentBase', 'overrides',
  'customSections', 'CUSTOM_FIELDS', 'controlPaths', 'fieldControl', 'valueAtPath',
  source.slice(start, end) + '\nsyncCreationCanonicalValues();');
for (const [fresh, preset] of [[true, 'framed'], [false, 'framed'], [true, 'custom']]) {
  const radius = { type: 'number', value: '6' };
  const shadow = { type: 'checkbox', checked: true };
  const restricted = { type: 'number', value: '7' };
  const sections = [
    { dataset: { id: 'radius' }, control: radius },
    { dataset: { id: 'shadow' }, control: shadow },
    { dataset: { id: 'restricted' }, control: restricted },
  ];
  sync({ dataset: { freshCreation: String(fresh) } }, () => preset,
    () => ({ borderRadius: 0, shadow: false, frameWidth: 2 }), () => ({}), {}, sections,
    { radius: ['borderRadius'], shadow: ['shadow'], restricted: ['frameWidth'] },
    ['borderRadius', 'shadow'], section => section.control, (document, key) => document[key]);
  assert.equal(radius.value, fresh && preset !== 'custom' ? '0' : '6');
  assert.equal(shadow.checked, !(fresh && preset !== 'custom'));
  assert.equal(restricted.value, '7', 'Restricted canonical field never synchronized');
}
assert.match(source, /const updateMode[\s\S]*?syncCreationCanonicalValues\(\)/);
assert.match(source, /const persist[\s\S]*?syncCreationCanonicalValues\(\)/);
const resetStart = source.indexOf("    if (event.target.closest('[data-design-reset-all]')) {");
const resetEnd = source.indexOf('\n    }', resetStart);
const reset = new Function('controlPaths', 'deletePath', 'overrides', 'applyEffectiveValues', 'editor', 'currentBase', 'persist',
  source.slice(source.indexOf('\n', resetStart) + 1, resetEnd));
const overrides = { borderRadius: 9, shadow: true };
reset(['shadow'], (doc, key) => delete doc[key], overrides, () => {}, {}, () => ({}), () => {});
assert.deepEqual(overrides, { borderRadius: 9 }, 'Reset all preserves restricted overrides');
assert.match(source, /presetSection\?\.querySelector\('select'\) \?\? \{/);
console.log('Creation canonical sync, existing/custom guards, restricted reset and hidden preset: PASS');
