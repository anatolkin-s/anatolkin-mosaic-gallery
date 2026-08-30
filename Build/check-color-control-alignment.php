<?php
declare(strict_types=1);

/**
 * Structural checks for Design Configurator color-control alignment.
 * Custom mode uses Core-native colorpicker only; eyedropper is preset-owned.
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

// 1. FlexForm Custom color fields still use Core colorpicker
if (!str_contains($flexForm, '<renderType>colorpicker</renderType>')) {
    $failures[] = '1: Custom-mode FlexForm color fields must keep Core renderType=colorpicker';
}

// 2. JS does NOT decorate .form-wizards-wrap for Custom color controls
if (str_contains($js, 'ensureCustomColorControlRow')
    || str_contains($js, 'CUSTOM_COLOR_SECTION_IDS')
    || preg_match("/setAttribute\\(\\s*'data-mosaic-color-control-row'\\s*,\\s*'custom'\\s*\\)/", $js)
    || (str_contains($js, 'form-wizards-wrap') && str_contains($js, 'data-mosaic-color-control-row'))
) {
    $failures[] = '2: JS must not decorate .form-wizards-wrap for Custom color controls';
}

// 3. JS does NOT dynamically add .mosaic-design-eyedropper to Core Custom fields
if (preg_match('/createElement\\(\\s*[\'"]button[\'"]\\s*\\)[\\s\\S]{0,240}mosaic-design-eyedropper/', $js)
    || preg_match('/mosaic-design-eyedropper[\\s\\S]{0,240}(?:append|appendChild)\\s*\\(/', $js)
) {
    $failures[] = '3: JS must not dynamically inject .mosaic-design-eyedropper for Custom colorpickers';
}

// 4. CSS has NO custom marker
if (str_contains($css, 'data-mosaic-color-control-row="custom"')
    || str_contains($css, "data-mosaic-color-control-row='custom'")
) {
    $failures[] = '4: CSS must not define data-mosaic-color-control-row="custom"';
}

// 5. CSS does NOT style Core colorpicker internals for Mosaic alignment
if (preg_match('/\\.form-wizards-wrap\\[data-mosaic-color-control-row/', $css)
    || preg_match('/typo3-backend-color-picker/', $css)
    || preg_match('/\\.form-wizards-item-aside--field-control/', $css)
) {
    $failures[] = '5: CSS must not style Core colorpicker / form-wizards internals for Mosaic alignment';
}

// 6. Named preset rows still use preset marker
if (!str_contains($element, 'data-mosaic-color-control-row="preset"')) {
    $failures[] = '6: Named-preset rows must keep data-mosaic-color-control-row="preset"';
}

// 7. Named preset controls remain flex-aligned
if (!preg_match(
    '/\\.mosaic-design-configurator__control[^{]*\\{[\\s\\S]*?display:\\s*flex/s',
    $css,
) || !str_contains($element, 'mosaic-design-configurator__picker')
    || !str_contains($element, 'data-design-color-picker')
) {
    $failures[] = '7: Named-preset color controls must remain flex-aligned with extension-owned picker';
}

// 8. Named preset EyeDropper remains optional
if (!str_contains($js, 'window.EyeDropper')
    || !preg_match('/if\\s*\\(\\s*window\\.EyeDropper\\s*\\)/', $js)
    || !str_contains($js, '[data-design-eyedropper]')
    || !str_contains($element, 'data-design-eyedropper')
) {
    $failures[] = '8: Named-preset EyeDropper must remain optional behind window.EyeDropper';
}

// 9. Reset controls unchanged
if (!str_contains($element, 'data-design-reset-field')
    || !str_contains($js, 'data-design-reset-field')
    || !str_contains($js, 'data-design-reset-all')
) {
    $failures[] = '9: Reset field/all controls must remain present';
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
