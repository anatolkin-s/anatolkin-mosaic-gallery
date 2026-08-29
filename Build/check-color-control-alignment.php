<?php
declare(strict_types=1);

/**
 * Structural checks for Design Configurator color-control alignment.
 * Run: php Build/check-color-control-alignment.php
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

$js = readFileOrFail($root . '/Resources/Public/JavaScript/design-configurator.js', $failures);
$css = readFileOrFail($root . '/Resources/Public/Backend/Css/form-layout.css', $failures);
$element = readFileOrFail(
    $root . '/Classes/Backend/Form/Element/DesignConfiguratorElement.php',
    $failures,
);
$flexForm = readFileOrFail($root . '/Configuration/FlexForms/MosaicGallery.xml', $failures);

// 1. Custom Core row gets an explicit custom marker/value
if (!preg_match("/setAttribute\\(\\s*'data-mosaic-color-control-row'\\s*,\\s*'custom'\\s*\\)/", $js)) {
    $failures[] = '1: Custom Core rows must be marked data-mosaic-color-control-row="custom"';
}
if (!str_contains($element, 'data-mosaic-color-control-row="preset"')) {
    $failures[] = '1: Named-preset rows must be marked data-mosaic-color-control-row="preset"';
}

// 2. CSS targets the decorated Core row directly (no configurator ancestry)
if (preg_match(
    '/\.mosaic-design-configurator[^\n{]*\.form-wizards-wrap\[data-mosaic-color-control-row/',
    $css,
) || preg_match(
    '/\.mosaic-design-configurator\.is-custom[^\n{]*typo3-backend-color-picker/',
    $css,
) || preg_match(
    '/\.mosaic-design-configurator\.is-custom[^\n{]*\.form-control-wrap/',
    $css,
)) {
    $failures[] = '2: Custom color-row CSS must not require .mosaic-design-configurator ancestry';
}
if (!preg_match(
    '/\.form-wizards-wrap\[data-mosaic-color-control-row="custom"\]\s*\{[\s\S]*?display:\s*grid/s',
    $css,
)) {
    $failures[] = '2: CSS must target .form-wizards-wrap[data-mosaic-color-control-row="custom"] directly';
}

// 3. Element and aside forced into the same grid row
if (!preg_match(
    '/\[data-mosaic-color-control-row="custom"\]\s*>\s*\.form-wizards-item-element\s*\{[\s\S]*?grid-row:\s*1/s',
    $css,
) || !preg_match(
    '/\[data-mosaic-color-control-row="custom"\][\s\S]*?grid-column:\s*2[\s\S]*?grid-row:\s*1/s',
    $css,
)) {
    $failures[] = '3: Custom row must force element and aside onto the same grid row';
}

// 4. Both aside class forms supported
if (!str_contains($css, '.form-wizards-item-aside')
    || !str_contains($css, '.form-wizards-item-aside--field-control')
) {
    $failures[] = '4: CSS must support both .form-wizards-item-aside and --field-control forms';
}

// 5. Core typo3-backend-color-picker remains untouched structurally
if (!str_contains($flexForm, '<renderType>colorpicker</renderType>')) {
    $failures[] = '5: Custom-mode FlexForm color fields must keep Core renderType=colorpicker';
}
if (preg_match('/control\.parentElement\s*\?\.?\s*append\s*\(/', $js)
    || preg_match('/typo3-backend-color-picker[\s\S]{0,120}append\(/', $js)
) {
    $failures[] = '5: Must not append eyedropper inside typo3-backend-color-picker / parentElement';
}
if (!str_contains($js, 'form-wizards-wrap')
    || !str_contains($js, 'form-wizards-item-aside')
) {
    $failures[] = '5: Custom eyedropper must mount on Core form-wizards aside row';
}

// 6. No absolute positioning
if (preg_match(
    '/\[data-mosaic-color-control-row[^\]]*\][^{]*\{[^}]*position:\s*absolute/s',
    $css,
) || preg_match(
    '/\.mosaic-design-configurator__control[^{]*\{[^}]*position:\s*absolute/s',
    $css,
) || preg_match(
    '/\.mosaic-design-eyedropper[^{]*\{[^}]*position:\s*absolute/s',
    $css,
)) {
    $failures[] = '6: Color-control alignment must not rely on absolute positioning';
}

// 7. No TYPO3-major-specific hardcoded offsets
if (preg_match('/typo3[\s_-]*(?:v)?(?:13|14)[\s\S]{0,40}(?:top|left|margin|padding)\s*:\s*-?\d+px/i', $css)
    || preg_match('/(?:top|left|margin-(?:top|left)|padding-(?:top|left))\s*:\s*-?\d+px[\s\S]{0,60}typo3[\s_-]*(?:v)?(?:13|14)/i', $css)
) {
    $failures[] = '7: Must not use TYPO3-major-specific hardcoded pixel offsets';
}

// 8. EyeDropper remains optional
if (!str_contains($js, 'window.EyeDropper')
    || !preg_match('/if\s*\(\s*window\.EyeDropper\s*\)/', $js)
) {
    $failures[] = '8: EyeDropper feature must remain optional behind window.EyeDropper';
}

// 9. Named-preset controls remain aligned independently
if (!preg_match(
    '/\.mosaic-design-configurator__control[^{]*\{[\s\S]*?display:\s*flex/s',
    $css,
) || !str_contains($element, 'mosaic-design-configurator__picker')
    || !str_contains($element, 'data-design-color-picker')
) {
    $failures[] = '9: Named-preset color controls must remain independently flex-aligned';
}

if (!str_contains($element, 'data-design-reset-field')
    || !str_contains($js, 'data-design-reset-field')
    || !str_contains($js, 'data-design-reset-all')
) {
    $failures[] = 'Reset field/all controls must remain present';
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
