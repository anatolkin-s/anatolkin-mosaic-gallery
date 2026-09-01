<?php
declare(strict_types=1);

/**
 * Structural checks for Design Configurator color-control architecture.
 * Custom FlexForm colors use TYPO3 Core type=color; eyedropper is preset-owned.
 * Run: php Build/check-color-control-alignment.php
 */

$root = dirname(__DIR__);
$failures = [];

/** @var list<string> */
const CANONICAL_COLOR_FIELDS = [
    'settings.frameColor',
    'settings.frameAccentColor',
    'settings.backgroundColor',
    'settings.captionColor',
    'settings.lbOverlay',
    'settings.lbNavColor',
    'settings.lbCloseColor',
    'settings.lbCaptionColor',
    'settings.lbCaptionBg',
];

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

$js = readFileOrFail($root . '/Resources/Public/JavaScript/design-configurator.js', $failures);
$css = readFileOrFail($root . '/Resources/Public/Backend/Css/form-layout.css', $failures);
$element = readFileOrFail(
    $root . '/Classes/Backend/Form/Element/DesignConfiguratorElement.php',
    $failures,
);
$flexForm = readFileOrFail($root . '/Configuration/FlexForms/MosaicGallery.xml', $failures);

// A. every canonical Custom color field uses native type=color
foreach (CANONICAL_COLOR_FIELDS as $field) {
    if (!preg_match(
        '/<' . preg_quote($field, '/') . '>\s*<label>[^<]*<\/label>\s*<config>\s*<type>color<\/type>/',
        $flexForm,
    )) {
        $failures[] = "A: {$field} must use native <type>color</type>";
    }
}

// B. legacy renderType=colorpicker must NOT remain for those fields / FlexForm
if (str_contains($flexForm, '<renderType>colorpicker</renderType>')) {
    $failures[] = 'B: FlexForm must not retain renderType=colorpicker for color fields';
}
foreach (CANONICAL_COLOR_FIELDS as $field) {
    if (preg_match(
        '/<' . preg_quote($field, '/') . '>\s*<label>[^<]*<\/label>\s*<config>([\s\S]*?)<\/config>/',
        $flexForm,
        $block,
    ) && str_contains($block[1], '<eval>')) {
        $failures[] = "B: {$field} must not keep obsolete eval configuration";
    }
}

// Inventory completeness: exactly the expected color fields, no extras of type=color outside inventory
preg_match_all(
    '/<(settings\.[A-Za-z0-9_]+)>\s*<label>[^<]*<\/label>\s*<config>\s*<type>color<\/type>/',
    $flexForm,
    $found,
);
$foundFields = $found[1] ?? [];
sort($foundFields);
$expected = CANONICAL_COLOR_FIELDS;
sort($expected);
if ($foundFields !== $expected) {
    $failures[] = 'A/B: type=color inventory mismatch. found=' . implode(',', $foundFields);
}

// C. Mosaic JS does not decorate/inject into Core color controls
if (str_contains($js, 'ensureCustomColorControlRow')
    || str_contains($js, 'CUSTOM_COLOR_SECTION_IDS')
    || preg_match("/setAttribute\\(\\s*'data-mosaic-color-control-row'\\s*,\\s*'custom'\\s*\\)/", $js)
    || (str_contains($js, 'form-wizards-wrap') && str_contains($js, 'data-mosaic-color-control-row'))
    || preg_match('/createElement\\(\\s*[\'"]button[\'"]\\s*\\)[\\s\\S]{0,240}mosaic-design-eyedropper/', $js)
    || preg_match('/mosaic-design-eyedropper[\\s\\S]{0,240}(?:append|appendChild)\\s*\\(/', $js)
) {
    $failures[] = 'C: JS must not decorate or inject into Core Custom color controls';
}

// D. Mosaic CSS does not depend on Core colorpicker internals for alignment
if (preg_match('/\\.form-wizards-wrap\\[data-mosaic-color-control-row/', $css)
    || preg_match('/typo3-backend-color-picker/', $css)
    || preg_match('/\\.form-wizards-item-aside--field-control/', $css)
    || str_contains($css, 'data-mosaic-color-control-row="custom"')
) {
    $failures[] = 'D: CSS must not style Core colorpicker / form-wizards internals for Mosaic alignment';
}

// E. named-preset extension-owned color control still exists
if (!str_contains($element, 'data-mosaic-color-control-row="preset"')
    || !str_contains($element, 'mosaic-design-configurator__picker')
    || !str_contains($element, 'data-design-color-picker')
) {
    $failures[] = 'E: Named-preset extension-owned color controls must remain';
}

// F. optional named-preset EyeDropper remains guarded
if (!str_contains($js, 'window.EyeDropper')
    || !preg_match('/if\\s*\\(\\s*window\\.EyeDropper\\s*\\)/', $js)
    || !str_contains($js, '[data-design-eyedropper]')
    || !str_contains($element, 'data-design-eyedropper')
) {
    $failures[] = 'F: Named-preset EyeDropper must remain optional behind window.EyeDropper';
}

// G. reset controls remain
if (!str_contains($element, 'data-design-reset-field')
    || !str_contains($js, 'data-design-reset-field')
    || !str_contains($js, 'data-design-reset-all')
) {
    $failures[] = 'G: Reset field/all controls must remain present';
}

// H. no legacy Custom augmentation markers return
if (str_contains($js, 'ensureCustomColorControlRow')
    || str_contains($js, 'CUSTOM_COLOR_SECTION_IDS')
    || str_contains($css, 'data-mosaic-color-control-row="custom"')
) {
    $failures[] = 'H: Legacy Custom color augmentation markers must remain absent';
}

// Named preset flex alignment
if (!preg_match(
    '/\\.mosaic-design-configurator__control[^{]*\\{[\\s\\S]*?display:\\s*flex/s',
    $css,
)) {
    $failures[] = 'Named-preset color controls must remain flex-aligned';
}

// I. Custom native color lifecycle: Core visible input + capture blur, no Mosaic hidden writes
if (!preg_match(
    '/const fieldControl = \\(section\\) => \\{[\\s\\S]*?input\\[data-formengine-input-name\\]:not\\(\\[type="hidden"\\]\\)/',
    $js,
)) {
    $failures[] = 'I: fieldControl must prefer Core visible FormEngine input before generic fallbacks';
}
if (!preg_match(
    "/sheet\\.addEventListener\\(\\s*'blur'\\s*,\\s*\\(event\\)\\s*=>\\s*\\{[\\s\\S]*?isCustomFieldEvent\\(\\s*event\\s*\\)[\\s\\S]*?\\}\\s*,\\s*true\\s*\\)/",
    $js,
)) {
    $failures[] = 'I: Custom field lifecycle must observe blur in capture phase on sheet';
}
if (!preg_match("/sheet\\.addEventListener\\(\\s*'change'[\\s\\S]*?isCustomFieldEvent\\(\\s*event\\s*\\)/", $js)
    || !preg_match("/sheet\\.addEventListener\\(\\s*'input'[\\s\\S]*?isCustomFieldEvent\\(\\s*event\\s*\\)/", $js)
) {
    $failures[] = 'I: sheet must retain input and change listeners for Custom fields';
}
if (preg_match("/dispatchEvent\\(\\s*new Event\\(\\s*'blur'/", $js)) {
    $failures[] = 'I: must not dispatch synthetic blur events for Custom color sync';
}
if (preg_match(
    '/querySelector\\([^)]*type=["\']hidden["\'][^)]*\\)[\\s\\S]{0,120}\\.value\\s*=/',
    $js,
)) {
    $failures[] = 'I: must not manually assign canonical hidden color inputs';
}

// J. Custom native color initial hydration: visible wins, hidden fallback on load
if (!preg_match('/const formEngineControlValue = \\(section, control\\) => \\{/', $js)) {
    $failures[] = 'J: formEngineControlValue helper must exist for Core color hydration';
}
if (!preg_match(
    '/formEngineControlValue[\\s\\S]*?visibleValue !== [\'"]{2}/',
    $js,
)) {
    $failures[] = 'J: visible non-empty value must take precedence over hidden canonical value';
}
if (!preg_match(
    '/formEngineControlValue[\\s\\S]*?control\\.dataset\\?\\.formengineInputName/',
    $js,
)) {
    $failures[] = 'J: hydration must resolve canonical name from visible FormEngine input dataset';
}
if (!preg_match(
    '/formEngineControlValue[\\s\\S]*?candidate\\.name === canonicalName/',
    $js,
)) {
    $failures[] = 'J: hidden fallback must match canonical field by exact name equality';
}
if (!preg_match('/let value = formEngineControlValue\\(section, control\\)/', $js)) {
    $failures[] = 'J: customDesign must read canonical values via formEngineControlValue';
}

/**
 * Mirror of formEngineControlValue for source-level fixture checks (no browser).
 *
 * @return non-empty-string
 */
function formEngineControlValueFixture(string $visibleValue, ?string $canonicalName, ?string $hiddenValue): string
{
    if ($visibleValue !== '') {
        return $visibleValue;
    }
    if ($canonicalName === null || $canonicalName === '') {
        return $visibleValue;
    }

    return $hiddenValue ?? $visibleValue;
}

$hydrationFixtures = [
    ['visible' => '', 'canonical' => 'data[foo]', 'hidden' => '#DE0000', 'expected' => '#DE0000'],
    ['visible' => '#00AA00', 'canonical' => 'data[foo]', 'hidden' => '#DE0000', 'expected' => '#00AA00'],
    ['visible' => '', 'canonical' => 'data[foo]', 'hidden' => '', 'expected' => ''],
];
foreach ($hydrationFixtures as $fixture) {
    $actual = formEngineControlValueFixture(
        $fixture['visible'],
        $fixture['canonical'],
        $fixture['hidden'],
    );
    if ($actual !== $fixture['expected']) {
        $failures[] = sprintf(
            'J: hydration fixture failed visible=%s hidden=%s expected=%s got=%s',
            $fixture['visible'] === '' ? "''" : $fixture['visible'],
            $fixture['hidden'] === '' ? "''" : $fixture['hidden'],
            $fixture['expected'],
            $actual,
        );
    }
}

// Persisted hex defaults remain on migrated fields (compatibility)
$defaultSamples = [
    'settings.frameColor' => '#b40000',
    'settings.backgroundColor' => '#e5e5e5',
    'settings.lbOverlay' => '#2c5222',
    'settings.lbNavColor' => '#FFFFFF',
    'settings.lbCaptionBg' => '#b40000',
];
foreach ($defaultSamples as $field => $default) {
    if (!preg_match(
        '/<' . preg_quote($field, '/') . '>\s*<label>[^<]*<\/label>\s*<config>[\s\S]*?<default>'
            . preg_quote($default, '/') . '<\/default>/',
        $flexForm,
    )) {
        $failures[] = "Persisted default for {$field} must remain {$default}";
    }
}

if ($failures === []) {
    fwrite(STDOUT, "Color control alignment checks passed.\n");
    exit(0);
}

fwrite(STDERR, "Color control alignment checks failed:\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
