<?php
declare(strict_types=1);

/**
 * Structural checks for upper layout workspace (source/settings rows) responsive CSS.
 * Run: php Build/check-layout-workspace-responsive.php
 */

$root = dirname(__DIR__);
$failures = [];

function readFileOrFail(string $path, array &$failures): string
{
    if (!is_file($path)) {
        $failures[] = 'Missing file: ' . $path;
        return '';
    }
    $contents = file_get_contents($path);
    if ($contents === false) {
        $failures[] = 'Unreadable file: ' . $path;
        return '';
    }
    return $contents;
}

$css = readFileOrFail($root . '/Resources/Public/Backend/Css/form-layout.css', $failures);
$js = readFileOrFail($root . '/Resources/Public/JavaScript/design-configurator.js', $failures);

function extractRuleBody(string $css, string $selector): string
{
    $pattern = '/' . preg_quote($selector, '/') . '\s*\{([^}]+)\}/s';
    preg_match($pattern, $css, $match);
    return $match[1] ?? '';
}

$sourceRowRule = extractRuleBody($css, '.mosaic-layout-header__row--source');
$settingsRowRule = extractRuleBody($css, '.mosaic-layout-header__row--settings');
$layoutSheetRule = extractRuleBody($css, '.tab-content > .tab-pane.active.mosaic-layout-sheet');

// A. source row uses intrinsic auto-fit/auto-fill sizing
if ($sourceRowRule === '') {
    $failures[] = 'A: Missing .mosaic-layout-header__row--source rule';
} elseif (!preg_match(
    '/grid-template-columns\s*:\s*repeat\(\s*auto-fit\s*,\s*minmax\s*\(\s*min\s*\(/',
    $sourceRowRule,
)) {
    $failures[] = 'A: Source row must use intrinsic repeat(auto-fit, minmax(min(...), 1fr))';
}

// B. settings row uses intrinsic auto-fit/auto-fill sizing
if ($settingsRowRule === '') {
    $failures[] = 'B: Missing .mosaic-layout-header__row--settings rule';
} elseif (!preg_match(
    '/grid-template-columns\s*:\s*repeat\(\s*auto-fit\s*,\s*minmax\s*\(\s*min\s*\(/',
    $settingsRowRule,
)) {
    $failures[] = 'B: Settings row must use intrinsic repeat(auto-fit, minmax(min(...), 1fr))';
}

// C. upper rows no longer depend on fixed 1099px → 6-col → 699px → 1-col authority
if (preg_match(
    '/@container\s*\(\s*max-width:\s*1099px\s*\)\s*\{[^}]*mosaic-layout-header__row/s',
    $css,
) || preg_match(
    '/@container\s*\(\s*max-width:\s*699px\s*\)\s*\{[^}]*mosaic-layout-header__row/s',
    $css,
)) {
    $failures[] = 'C: Remove outer 1099px/699px @container authority from layout header rows';
}

// D. no fixed repeat(5/6/7) upper row column system remains
foreach ([5, 6, 7] as $columns) {
    if (preg_match(
        '/\.mosaic-layout-header__row--(?:source|settings)\s*\{[^}]*grid-template-columns\s*:\s*repeat\(\s*' . $columns . '\s*,/s',
        $css,
    )) {
        $failures[] = "D: Layout header rows must not use fixed repeat({$columns}, ...) columns";
    }
}
if (preg_match(
    '/\.mosaic-layout-header__row--source\s*\{[^}]*grid-template-columns:[^;]*minmax\(\s*7rem/s',
    $css,
) || preg_match(
    '/\.mosaic-layout-header__row--settings\s*\{[^}]*grid-template-columns:[^;]*minmax\(\s*10rem\s*,\s*1\.3fr/s',
    $css,
)) {
    $failures[] = 'D: Obsolete fixed multi-track source/settings column templates must be removed';
}

// E. Folder has safe width treatment without narrow overflow
if (!preg_match(
    '/\.mosaic-layout-header__row--source\s*>\s*\.form-section\[data-id="settings\.folder"\]\s*\{[^}]*grid-column:\s*span\s*2/s',
    $css,
)) {
    $failures[] = 'E: Folder must span two intrinsic tracks at wider source-row widths';
}
if (!preg_match(
    '/@container\s+mosaic-layout-source\s*\([^)]+\)\s*\{[^}]*settings\.folder[^}]*grid-column:\s*1\s*\/\s*-1/s',
    $css,
)) {
    $failures[] = 'E: Folder must span the full source row at narrow local container widths';
}
if (preg_match(
    '/@container\s+mosaic-layout-source\s*\([^)]+\)\s*\{[^}]*settings\.folder[^}]*grid-column:\s*span\s*1/s',
    $css,
)) {
    $failures[] = 'E: Folder must not collapse to a single narrow intrinsic track';
}
if (!preg_match(
    '/\[data-id="settings\.folder"\][\s\S]{0,500}\.form-wizards-wrap[\s\S]{0,200}minmax\s*\(\s*0\s*,\s*1fr\s*\)\s*auto/s',
    $css,
) || !preg_match(
    '/\[data-id="settings\.folder"\][\s\S]{0,500}\.form-wizards-item-element[\s\S]{0,120}min-width:\s*0/s',
    $css,
)) {
    $failures[] = 'E: Folder Core field wrapper must remain shrinkable inside its row';
}
if (preg_match(
    '/\[data-id="settings\.folder"\][\s\S]{0,800}(?:display:\s*none|visibility:\s*hidden|overflow-x:\s*hidden)/s',
    $css,
)) {
    $failures[] = 'E: Folder controls must not be hidden or overflow-masked';
}
if (!preg_match(
    '/\[data-id="settings\.folder"\][\s\S]{0,500}\.form-wizards-item-aside[\s\S]{0,120}flex:\s*0\s*0\s*auto/s',
    $css,
)) {
    $failures[] = 'E: Folder Core action buttons must keep fixed auto-sized tracks';
}

// F. recursive/Subfolders remains compact
if (!preg_match(
    '/\.mosaic-layout-header__row--source\s*>\s*\.form-section\[data-id="settings\.recursive"\][^{]*\{[^}]*width:\s*auto/s',
    $css,
) && !preg_match(
    '/\.mosaic-layout-header__row--source\s*>\s*\.form-section\[data-id="settings\.recursive"\][^{]*\{[^}]*:is\(\.form-check[^)]+\)\s*\{[^}]*width:\s*auto/s',
    $css,
)) {
    $failures[] = 'F: Subfolders checkbox controls must stay compact (width: auto)';
}

// G. numeric upper fields use bounded compact widths
if (!preg_match(
    '/\.mosaic-layout-header__row--settings[\s\S]{0,400}\[data-id="settings\.maxItemsPerRow"\][\s\S]{0,200}--mosaic-layout-compact-ch:\s*3/s',
    $css,
) || !preg_match(
    '/\.mosaic-layout-header__row--settings[\s\S]{0,400}\[data-id="settings\.maxWidth"\][\s\S]{0,200}--mosaic-layout-compact-ch:\s*6/s',
    $css,
) || !preg_match(
    '/\.mosaic-layout-header__row--settings[\s\S]{0,500}1ch/s',
    $css,
)) {
    $failures[] = 'G: Upper numeric fields must use ch-based compact inline sizing';
}

// H. metadata fallback remains compact in Images source row
if (!preg_match(
    '/\.mosaic-images-header__row--source[\s\S]{0,200}\[data-id="settings\.useFalCaptions"\]\[data-mosaic-inline-checkbox="true"\]/s',
    $css,
) || !preg_match(
    '/\[data-id="settings\.useFalCaptions"\]\[data-mosaic-inline-checkbox="true"\][^{]*\{[^}]*width:\s*auto/s',
    $css,
)) {
    $failures[] = 'H: Metadata fallback checkbox must remain compact inline in Images workspace';
}

// I. no TYPO3-major responsive branching on layout workspace
if (preg_match('/typo3[\s_-]*(?:v)?(?:13|14)/i', $css)
    && preg_match('/mosaic-layout-header[\s\S]{0,120}typo3[\s_-]*(?:v)?(?:13|14)/i', $css)
) {
    $failures[] = 'I: Layout workspace must not branch on TYPO3 major version';
}

// J. no absolute-position layout hacks on upper workspace rows
if (preg_match(
    '/\.mosaic-layout-header(?:__row)?[^{]*\{[^}]*position:\s*absolute/s',
    $css,
)) {
    $failures[] = 'J: Layout workspace must not use absolute-position layout hacks';
}

// J2. no overflow-x masking on layout workspace
if (preg_match(
    '/\.mosaic-layout-(?:header|sheet)[^{]*\{[^}]*overflow-x:\s*hidden/s',
    $css,
)) {
    $failures[] = 'J: Layout workspace must not mask horizontal overflow';
}

// J3. no viewport-width folder workaround
if (preg_match(
    '/@media[^{]+\{[^}]*settings\.folder/s',
    $css,
)) {
    $failures[] = 'J: Folder layout must use local source container queries, not viewport breakpoints';
}

// K. outer layout sheet simplified or intentionally single-column (no obsolete 12-col requirement)
if ($layoutSheetRule === '') {
    $failures[] = 'K: Missing .mosaic-layout-sheet rule';
} elseif (preg_match('/grid-template-columns\s*:\s*repeat\(\s*12\s*,/', $layoutSheetRule)) {
    $failures[] = 'K: Outer mosaic-layout-sheet should not keep obsolete 12-column grid after intrinsic upper rows';
}

// L. consolidateWorkspaces DOM contract preserved in JS (field inventory only)
$requiredSourceFields = [
    'settings.source',
    'settings.folder',
    'settings.recursive',
    'settings.sortBy',
    'settings.sortDir',
];
$requiredSettingsFields = [
    'settings.layoutMode',
    'settings.maxItemsPerRow',
    'settings.maxWidth',
    'settings.itemsPerPage',
    'settings.loadStep',
];
$requiredImagesSourceFields = array_merge($requiredSourceFields, ['settings.useFalCaptions']);
foreach ($requiredImagesSourceFields as $field) {
    if (!str_contains($js, 'IMAGE_SOURCE_FIELD_IDS') || (!str_contains($js, "'{$field}'") && !str_contains($js, "\"{$field}\""))) {
        $failures[] = "L: Images workspace must own source field {$field}";
    }
}
foreach ($requiredSettingsFields as $field) {
    if (!str_contains($js, "'{$field}'") && !str_contains($js, "\"{$field}\"")) {
        $failures[] = "L: consolidateWorkspaces must preserve settings field {$field}";
    }
}
if (!str_contains($js, 'mosaic-layout-header__row--source')
    || !str_contains($js, 'mosaic-layout-header__row--settings')
    || !str_contains($js, 'mosaic-images-header')
    || !str_contains($js, 'consolidateWorkspaces')
) {
    $failures[] = 'L: consolidateWorkspaces structure markers must remain in design-configurator.js';
}

// Obsolete direct-child span rules on layout sheet upper fields should be gone
if (preg_match(
    '/\.tab-content\s*>\s*\.tab-pane\.active\.mosaic-layout-sheet\s*>\s*\.form-section\[data-id="settings\.folder"\]\s*\{[^}]*grid-column:\s*span\s*6/s',
    $css,
)) {
    $failures[] = 'Remove obsolete direct-child folder span-6 rule on mosaic-layout-sheet';
}

if ($failures === []) {
    fwrite(STDOUT, "Layout workspace responsive checks passed.\n");
    exit(0);
}

fwrite(STDERR, "Layout workspace responsive checks failed:\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
