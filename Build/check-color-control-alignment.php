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

// 1. No arbitrary control.parentElement?.append(button) for Custom eyedropper
if (preg_match('/control\.parentElement\s*\?\.?\s*append\s*\(/', $js)) {
    $failures[] = '1: Custom color eyedropper must not append via control.parentElement';
}

// 2. Stable extension-owned/decorated color-control wrapper
if (!str_contains($js, 'data-mosaic-color-control-row')
    || !str_contains($js, 'ensureCustomColorControlRow')
) {
    $failures[] = '2: JS must decorate a stable data-mosaic-color-control-row wrapper';
}
if (!str_contains($element, 'data-mosaic-color-control-row')) {
    $failures[] = '2: Named-preset color controls must expose data-mosaic-color-control-row';
}

// 3. Named-preset color controls remain inside a flex/grid row
if (!preg_match(
    '/\.mosaic-design-configurator__control[^{]*\{[\s\S]*?display:\s*flex/s',
    $css,
)) {
    $failures[] = '3: Named-preset .mosaic-design-configurator__control must remain a flex row';
}
if (!str_contains($element, 'mosaic-design-configurator__picker')
    || !str_contains($element, 'data-design-color-picker')
) {
    $failures[] = '3: Named-preset color controls must keep extension-owned picker markup';
}

// 4. Custom native colorpicker remains Core-owned
if (!str_contains($flexForm, '<renderType>colorpicker</renderType>')) {
    $failures[] = '4: Custom-mode FlexForm color fields must keep Core renderType=colorpicker';
}
if (str_contains($js, 'typo3-backend-color-picker') && preg_match(
    '/typo3-backend-color-picker[\s\S]{0,120}append\(/',
    $js,
)) {
    $failures[] = '4: Must not append eyedropper inside typo3-backend-color-picker';
}
if (!str_contains($js, 'form-wizards-wrap')
    || !str_contains($js, 'form-wizards-item-aside')
) {
    $failures[] = '4: Custom eyedropper must mount on Core form-wizards aside row';
}

// 5. No absolute-position fix for color-control alignment
if (preg_match(
    '/\[data-mosaic-color-control-row\][^{]*\{[^}]*position:\s*absolute/s',
    $css,
) || preg_match(
    '/\.mosaic-design-configurator__control[^{]*\{[^}]*position:\s*absolute/s',
    $css,
) || preg_match(
    '/\.mosaic-design-eyedropper[^{]*\{[^}]*position:\s*absolute/s',
    $css,
)) {
    $failures[] = '5: Color-control alignment must not rely on absolute positioning';
}

// 6. No TYPO3-major-specific hardcoded offsets
if (preg_match('/typo3[\s_-]*(?:v)?(?:13|14)[\s\S]{0,40}(?:top|left|margin|padding)\s*:\s*-?\d+px/i', $css)
    || preg_match('/(?:top|left|margin-(?:top|left)|padding-(?:top|left))\s*:\s*-?\d+px[\s\S]{0,60}typo3[\s_-]*(?:v)?(?:13|14)/i', $css)
) {
    $failures[] = '6: Must not use TYPO3-major-specific hardcoded pixel offsets';
}

// 7. EyeDropper remains optional
if (!str_contains($js, 'window.EyeDropper')
    || !preg_match('/if\s*\(\s*window\.EyeDropper\s*\)/', $js)
) {
    $failures[] = '7: EyeDropper feature must remain optional behind window.EyeDropper';
}

// 8. Reset buttons unchanged semantically
if (!str_contains($element, 'data-design-reset-field')
    || !str_contains($js, 'data-design-reset-field')
    || !str_contains($js, 'data-design-reset-all')
) {
    $failures[] = '8: Reset field/all controls must remain present';
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
