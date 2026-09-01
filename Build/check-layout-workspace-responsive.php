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
$imagesHeaderRule = extractRuleBody($css, '.mosaic-images-header');
$settingsRowRule = extractRuleBody($css, '.mosaic-layout-header__row--settings');
$layoutSheetRule = extractRuleBody($css, '.tab-content > .tab-pane.active.mosaic-layout-sheet');

// A. source row uses parent-owned container-query grid (wide single row, flexible when wrapped)
if ($sourceRowRule === '') {
    $failures[] = 'A: Missing .mosaic-layout-header__row--source rule';
} elseif (preg_match('/container-name:\s*mosaic-/s', $sourceRowRule)) {
    $failures[] = 'A: Source row must not define its own container query context (no self-container regression)';
} elseif ($imagesHeaderRule === '' || !preg_match('/container-name:\s*mosaic-images-source/s', $imagesHeaderRule)) {
    $failures[] = 'A: Images header parent must define mosaic-images-source container';
} elseif (!preg_match(
    '/@container mosaic-images-source \(min-width:\s*64rem\)[\s\S]{0,500}grid-template-columns:[\s\S]{0,300}minmax\(\s*7/s',
    $css,
)) {
    $failures[] = 'A: Wide source row must resolve to one compact Source/Folder + pair row via container query';
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

// E. Folder/Source width is contextual, not globally fixed
if (!preg_match(
    '/@container mosaic-images-source \(min-width:\s*64rem\)[\s\S]{0,700}settings\.source[\s\S]{0,200}inline-size:[\s\S]{0,80}11rem/s',
    $css,
)) {
    $failures[] = 'E: Wide source row may compact Source select without forcing Sort/Direction to wrap';
}
if (!preg_match(
    '/@container mosaic-images-source \(max-width:\s*63\.99rem\)[\s\S]{0,700}settings\.source[\s\S]{0,200}inline-size:\s*100%/s',
    $css,
)) {
    $failures[] = 'E: Wrapped/medium source row must restore flexible/full-width Source';
}
if (!preg_match(
    '/@container mosaic-images-source \(max-width:\s*35\.99rem\)[\s\S]{0,900}settings\.folder[\s\S]{0,200}(?:inline-size|width):\s*100%/s',
    $css,
)) {
    $failures[] = 'E: Narrow source row must give Folder full available width';
}
if (!preg_match(
    '/@container mosaic-images-source \(max-width:\s*35\.99rem\)[\s\S]{0,500}grid-template-columns:\s*minmax\(0,\s*1fr\)/s',
    $css,
)) {
    $failures[] = 'E: Narrow source row must stack Source and Folder on full-width tracks';
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

// H2. Subfolders + Metadata fallback stay paired on one row at narrow widths
if (!preg_match(
    '/mosaic-layout-header__source-pair--recursive-fallback/s',
    $css,
) || !preg_match(
    '/mosaic-layout-header__source-pair--recursive-fallback[\s\S]{0,400}grid-template-columns:\s*repeat\(2/s',
    $css,
) || !preg_match(
    '/@container mosaic-images-source[\s\S]{0,800}mosaic-layout-header__source-pair--recursive-fallback/s',
    $css,
)) {
    $failures[] = 'H2: Subfolders and Metadata fallback must share a stable narrow-width pair row';
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
    'settings.useFalCaptions',
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
    || !str_contains($js, 'findManualImagesSection')
    || !str_contains($js, 'mosaic-layout-header__source-pair--recursive-fallback')
) {
    $failures[] = 'L: consolidateWorkspaces structure markers must remain in design-configurator.js';
}

// M. explicit manual source-mode selector and compact manual composition
if (!preg_match('/data-mosaic-source-mode="manual"/', $css) || !preg_match('/imagesSourceRow.*data-mosaic-source-mode/s', $js)) {
    $failures[] = 'M: Manual source mode must use explicit data-mosaic-source-mode selectors';
}
if (!preg_match(
    '/@container mosaic-images-source \(min-width:\s*64rem\)[\s\S]{0,900}data-mosaic-source-mode="manual"[\s\S]{0,300}grid-template-columns/s',
    $css,
)) {
    $failures[] = 'M: Manual mode must not reserve folder/sort wide-grid tracks';
}
if (!preg_match(
    '/mosaic-images-sheet\[data-mosaic-source-mode="manual"\][\s\S]{0,300}margin-bottom:\s*\.5rem/s',
    $css,
) || !preg_match(
    '/mosaic-images-sheet\[data-mosaic-source-mode="manual"\][\s\S]{0,500}margin-bottom:\s*\.75rem/s',
    $css,
)) {
    $failures[] = 'M: Manual Images workspace must use compact vertical rhythm without blank spacer panels';
}
if (!preg_match(
    '/@container mosaic-images-source \(min-width:\s*64rem\)[\s\S]{0,500}grid-template-columns:[\s\S]{0,300}minmax\(\s*7/s',
    $css,
)) {
    $failures[] = 'M: Folder wide source row contract must remain unchanged';
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
