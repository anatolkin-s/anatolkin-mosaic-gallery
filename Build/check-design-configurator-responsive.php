<?php
declare(strict_types=1);

/**
 * Structural checks for Design Configurator responsive Custom/group grids.
 * Run: php Build/check-design-configurator-responsive.php
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
$element = readFileOrFail(
    $root . '/Classes/Backend/Form/Element/DesignConfiguratorElement.php',
    $failures,
);
$flexForm = readFileOrFail($root . '/Configuration/FlexForms/MosaicGallery.xml', $failures);

// Extract Custom grid rule body
$customGridMatch = [];
preg_match(
    '/\.mosaic-design-configurator\.is-custom\s+\.mosaic-design-configurator__custom\s*\{([^}]+)\}/s',
    $css,
    $customGridMatch,
);
$customGridRule = $customGridMatch[1] ?? '';

// 1. Custom grid must not be permanently fixed at four columns without intrinsic fallback
if ($customGridRule === '') {
    $failures[] = '1: Missing .mosaic-design-configurator.is-custom .mosaic-design-configurator__custom rule';
} elseif (preg_match('/grid-template-columns\s*:\s*repeat\(\s*4\s*,/', $customGridRule)
    && !preg_match('/auto-fit|auto-fill|minmax\s*\(\s*min\s*\(/', $customGridRule)
) {
    $failures[] = '1: Custom grid must not stay fixed at four columns without intrinsic/group-local fallback';
}
if (!preg_match('/grid-template-columns\s*:\s*repeat\(\s*auto-fit\s*,\s*minmax\s*\(\s*min\s*\(/', $customGridRule)) {
    $failures[] = '1: Custom grid must use intrinsic auto-fit minmax(min(...), 1fr) sizing';
}

// Confirm no outer-only 4→2→1 override still targets __custom
if (preg_match(
    '/@container[^{]+\{[^}]*\.mosaic-design-configurator\.is-custom\s+\.mosaic-design-configurator__custom/s',
    $css,
)) {
    $failures[] = '1: Remove outer @container overrides for __custom; rely on intrinsic sizing';
}

// 2. No TYPO3-major responsive branching
if (preg_match('/typo3[\s_-]*(?:v)?(?:13|14)/i', $css)
    && preg_match('/(?:grid-template-columns|@container)[\s\S]{0,80}typo3[\s_-]*(?:v)?(?:13|14)/i', $css)
) {
    $failures[] = '2: Responsive layout must not depend on TYPO3 major version';
}

// 3. No Mosaic min-width larger than available group / no overflow hacks
if (preg_match(
    '/\.mosaic-design-configurator(?:__group|__custom|__field)[^{]*\{[^}]*min-width\s*:\s*(?:[2-9]\d{2,}|[1-9]\d{3,})px/s',
    $css,
)) {
    $failures[] = '3: Mosaic design fields must not impose large fixed min-width that forces overflow';
}
if (!preg_match('/\.mosaic-design-configurator__group\s*\{[\s\S]*?min-width:\s*0/s', $css)
    || !preg_match('/\.mosaic-design-configurator__field\s*\{[\s\S]*?min-width:\s*0/s', $css)
    || !preg_match('/\.mosaic-design-configurator__custom\s*>\s*\.form-section\s*\{[\s\S]*?min-width:\s*0/s', $css)
) {
    $failures[] = '3: group/field/custom sections must keep min-width: 0';
}

// 4. No absolute-position responsive hacks on configurator grids
if (preg_match(
    '/\.mosaic-design-configurator__(?:groups|custom|group|field|grid)[^{]*\{[^}]*position:\s*absolute/s',
    $css,
)) {
    $failures[] = '4: Must not use absolute-position responsive hacks for design grids';
}

// 5. Custom colors stay Core-owned type=color without Mosaic DOM augmentation
if (str_contains($css, 'data-mosaic-color-control-row="custom"')
    || str_contains($css, 'typo3-backend-color-picker')
    || str_contains($js, 'ensureCustomColorControlRow')
    || str_contains($js, 'CUSTOM_COLOR_SECTION_IDS')
    || str_contains($flexForm, '<renderType>colorpicker</renderType>')
    || !preg_match('/<type>color<\/type>/', $flexForm)
) {
    $failures[] = '5: Custom mode must use Core type=color without Mosaic DOM augmentation or legacy colorpicker';
}

// Groups can collapse via intrinsic sizing
if (!preg_match(
    '/\.mosaic-design-configurator__groups\s*\{[\s\S]*?grid-template-columns\s*:\s*repeat\(\s*auto-fit\s*,\s*minmax\s*\(\s*min\s*\(/s',
    $css,
)) {
    $failures[] = 'Outer groups must use intrinsic auto-fit so they can collapse to one column';
}

// Custom Gallery display controls (Gap / Captions / Alignment) fill width intrinsically
$galleryControlsMatch = [];
preg_match(
    '/\.mosaic-design-configurator\.is-custom\s+\[data-design-group="gallery"\]\s*>\s*\[data-design-controls\]\s*\{([^}]+)\}/s',
    $css,
    $galleryControlsMatch,
);
$galleryControlsRule = $galleryControlsMatch[1] ?? '';
if ($galleryControlsRule === '') {
    $failures[] = 'Custom Gallery [data-design-controls] must have a scoped responsive/intrinsic grid rule';
} elseif (!preg_match(
    '/grid-template-columns\s*:\s*repeat\(\s*auto-fit\s*,\s*minmax\s*\(\s*min\s*\(/',
    $galleryControlsRule,
)) {
    $failures[] = 'Custom Gallery display controls must use intrinsic auto-fit sizing';
} elseif (preg_match('/grid-template-columns\s*:\s*repeat\(\s*4\s*,/', $galleryControlsRule)) {
    $failures[] = 'Custom Gallery display controls must not keep a fixed repeat(4, ...) layout';
}
if (!str_contains($css, '[data-design-group="gallery"]')) {
    $failures[] = 'Gallery Custom display-control rule must be scoped to data-design-group="gallery"';
}

// Existing Custom FlexForm field intrinsic rule remains unchanged
if (!preg_match(
    '/\.mosaic-design-configurator\.is-custom\s+\.mosaic-design-configurator__custom\s*\{[\s\S]*?repeat\(\s*auto-fit\s*,\s*minmax\s*\(\s*min\(\s*10rem/',
    $css,
)) {
    $failures[] = 'Existing Custom field intrinsic grid (min 10rem) must remain unchanged';
}

// Named preset inner grid responds to each group's available width
$presetGridMatch = [];
preg_match(
    '/\.mosaic-design-configurator:not\(\.is-custom\)\s+\.mosaic-design-configurator__grid\s*\{([^}]+)\}/s',
    $css,
    $presetGridMatch,
);
$presetGridRule = $presetGridMatch[1] ?? '';
if ($presetGridRule === '') {
    $failures[] = 'Named preset inner grid must use a scoped intrinsic auto-fit rule';
} elseif (!preg_match(
    '/grid-template-columns\s*:\s*repeat\(\s*auto-fit\s*,\s*minmax\s*\(\s*min\s*\(\s*9\.5rem/',
    $presetGridRule,
)) {
    $failures[] = 'Named preset inner grid must use intrinsic auto-fit with ~9.5rem ordinary-field minimum';
} elseif (preg_match('/grid-template-columns\s*:\s*repeat\(\s*[234]\s*,/', $presetGridRule)) {
    $failures[] = 'Named preset inner grid must not use fixed repeat(2|3|4) columns';
}

if (preg_match(
    '/@container[^{]+\{[^}]*\.mosaic-design-configurator__grid[^}]*grid-template-columns\s*:\s*repeat\(\s*[234]\s*,/s',
    $css,
)) {
    $failures[] = 'Named preset __grid must not rely on outer @container fixed column steps';
}

if (preg_match('/\.mosaic-design-configurator__grid\s*\{[^}]*grid-template-columns\s*:\s*repeat\(\s*4\s*,/', $css)) {
    $failures[] = 'Global __grid must not keep a fixed repeat(4) preset fallback';
}

if (!preg_match(
    '/\.mosaic-design-configurator__group\s*\{[\s\S]*?container-type:\s*inline-size/s',
    $css,
)) {
    $failures[] = 'L: each design group must be a local inline-size container';
}

if (!preg_match(
    '/\[data-design-field-kind="color"\][\s\S]{0,120}grid-column:\s*span\s*2/',
    $css,
)) {
    $failures[] = 'F/L: color fields must be able to span two local tracks';
}

if (!preg_match('/flex-wrap:\s*nowrap/', $css)
    || !str_contains($css, 'data-mosaic-color-control-row="preset"')
) {
    $failures[] = 'F: color controls must remain one-row (nowrap) flex rows';
}

// Preset controls remain independently marked/aligned
if (!str_contains($element, 'data-mosaic-color-control-row="preset"')
    || !str_contains($element, 'data-design-color-picker')
    || !str_contains($element, 'data-design-eyedropper')
    || !str_contains($element, 'data-design-reset-field')
    || !preg_match('/\.mosaic-design-configurator__control[^{]*\{[\s\S]*?display:\s*flex/s', $css)
) {
    $failures[] = 'Preset color controls must remain extension-owned and flex-aligned';
}

if (preg_match(
    '/@container[^{]+\{[^}]*(?:data-design-color-picker|data-design-eyedropper|data-design-reset-field)[^}]*(?:display:\s*none|visibility:\s*hidden)/s',
    $css,
) || preg_match(
    '/(?:data-design-color-picker|data-design-eyedropper|data-design-reset-field)[^{]*\{[^}]*(?:display:\s*none|visibility:\s*hidden)/s',
    $css,
)) {
    $failures[] = 'G: Preset color actions must not be hidden on narrow layouts';
}

// A/B/C. Boolean Design Configurator controls use checkboxes, not On/Off selects
if (!str_contains($element, 'mosaic-design-configurator__control--checkbox')
    || !preg_match('/type="checkbox" value="1"/', $element)
    || !preg_match("/'path'\\s*=>\\s*'shadow'[\\s\\S]{0,80}'type'\\s*=>\\s*'boolean'/", $element)
) {
    $failures[] = 'A: named-preset boolean controls must render as checkboxes';
}
foreach ([
    'settings.showCaptions',
    'settings.enableLightbox',
    'settings.enableLoadMore',
    'settings.loadMoreUseFrameStyle',
] as $booleanProxy) {
    if (!preg_match(
        '/renderBooleanProxy\(\s*[\'"]' . preg_quote($booleanProxy, '/') . '[\'"]/',
        $element,
    )) {
        $failures[] = "B: boolean display proxy {$booleanProxy} must use checkbox";
    }
}
if (!preg_match(
    '/type="checkbox" value="1" data-design-proxy="/',
    $element,
) || !str_contains($element, 'mosaic-design-display-controls__field--checkbox')
) {
    $failures[] = 'B: boolean display proxies must render checkbox markup';
}
if (preg_match(
    '/data-design-proxy="settings\.(?:showCaptions|enableLightbox|enableLoadMore|loadMoreUseFrameStyle)"[\s\S]{0,200}<option value="0">/',
    $element,
) || str_contains($element, 'flexform.designOverride.on')
    || str_contains($element, 'flexform.designOverride.off')
) {
    $failures[] = 'C: Design Configurator must not keep boolean On/Off <select> controls';
}

// D. checkbox read/write helpers preserve "1"/"0"
if (!preg_match('/const readControlValue = \\(control\\) => \\{/', $js)
    || !preg_match('/const writeControlValue = \\(control, value\\) => \\{/', $js)
    || !preg_match('/control\\.checked \\? [\'"]1[\'"] : [\'"]0[\'"]/', $js)
    || !preg_match('/control\\.checked = isTruthyBoolean\\(value\\)/', $js)
    || preg_match('/Boolean\\(\\s*[\'"]0[\'"]\\s*\\)/', $js)
) {
    $failures[] = 'D: boolean read/write helpers must preserve "1"/"0" without Boolean("0")';
}
if (!str_contains($js, 'readControlValue(proxy)')
    || !str_contains($js, 'writeControlValue(proxy,')
    || !str_contains($js, 'writeControlValue(control,')
) {
    $failures[] = 'D: proxy/control sync paths must use readControlValue/writeControlValue';
}

// E. compact numeric marker/sizing
if (!str_contains($element, 'data-design-compact-value')
    || !str_contains($js, 'updateCompactValueWidth')
    || !str_contains($js, '--mosaic-compact-ch')
    || !preg_match('/\[data-design-compact-value\][^{]*\{[^}]*--mosaic-compact-ch/s', $css)
) {
    $failures[] = 'E: compact numeric marker and ch-based sizing must exist';
}

// H/I/J. Load More lives inside Gallery subgroup; no outer loadMore card; proxies unique
if (!preg_match('/data-design-subgroup="loadMore"/', $element)
    || !str_contains($element, 'renderLoadMoreSubgroup')
) {
    $failures[] = 'H: Load More must render as a Gallery subgroup';
}
if (preg_match("/'loadMore'\\s*=>/", $element)
    || preg_match('/data-design-group="loadMore"/', $element)
) {
    $failures[] = 'I: obsolete outer loadMore design group must be removed';
}
foreach (['settings.enableLoadMore', 'settings.loadMoreUseFrameStyle'] as $proxyName) {
    if (preg_match_all(
        '/renderBooleanProxy\(\s*[\'"]' . preg_quote($proxyName, '/') . '[\'"]/',
        $element,
        $matches,
    ) !== 1) {
        $failures[] = "J: {$proxyName} must be rendered exactly once";
    }
}
if (!preg_match(
    '/data-design-group="gallery"[\s\S]*?data-design-subgroup="loadMore"/',
    $element,
) && !preg_match(
    '/\$group === [\'"]gallery[\'"][\s\S]*?renderLoadMoreSubgroup/',
    $element,
)) {
    $failures[] = 'H: Load More subgroup must be attached under Gallery rendering';
}

// Display proxy persistence + reset semantics
if (!str_contains($element, 'data-design-proxy-reset')
    || !str_contains($element, 'data-design-proxy-field')
    || !str_contains($js, 'initialProxyValues')
    || !str_contains($js, 'updateProxyFieldState')
    || !str_contains($js, 'proxyDirty')
) {
    $failures[] = 'K/M: display proxy baseline, reset, and dirty tracking must exist';
}
if (!preg_match('/writeCanonicalControlValue\\(\\s*liveCanonical, intended\\)/', $js)
    || !preg_match('/data-design-proxy-reset/', $js)
) {
    $failures[] = 'L: proxy reset must write through canonical adapter';
}
if (!preg_match('/refreshProxyBaselinesAfterPersist/', $js)
    || !preg_match('/bindDisplayProxies\\(\\{ rebindAfterPersist: true \\}\\)/', $js)
) {
    $failures[] = 'Post-save proxy refresh must rebuild baselines from persisted canonical fields';
}
if (!preg_match('/const dirty = overridesDirty \\|\\| proxyDirty\\(\\)/', $js)
    || !preg_match('/countLeaves\\(\\s*overrides\\s*\\)/', $js)
) {
    $failures[] = 'M/N: Unsaved status must include proxy dirty while override count stays separate';
}
$resolver = readFileOrFail($root . '/Classes/Service/DesignPresetResolver.php', $failures);
if (preg_match('/showCaptions|enableLightbox|enableLoadMore|loadMoreUseFrameStyle/', $resolver)) {
    $failures[] = 'O: display proxies must not be added to DesignPresetResolver documents';
}

if ($failures === []) {
    fwrite(STDOUT, "Design configurator responsive layout checks passed.\n");
    exit(0);
}

fwrite(STDERR, "Design configurator responsive layout checks failed:\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
