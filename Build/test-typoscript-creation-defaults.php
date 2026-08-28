<?php
declare(strict_types=1);

/**
 * Behavioral fixtures for MosaicGalleryCreationDefaultsDefinition.
 * Run: php Build/test-typoscript-creation-defaults.php
 */

require dirname(__DIR__) . '/Classes/Service/MosaicGalleryCreationDefaultsDefinition.php';

use Anatolkin\MosaicGallery\Service\MosaicGalleryCreationDefaultsDefinition;

$definition = new MosaicGalleryCreationDefaultsDefinition();
$failures = [];

/** @return array<string, mixed> */
function sampleDataStructure(): array
{
    return [
        'sheets' => [
            'sDEF' => [
                'ROOT' => [
                    'el' => [
                        'settings.gap' => ['config' => ['default' => '12']],
                        'settings.enableLightbox' => ['config' => ['default' => '1']],
                        'settings.layoutMode' => ['config' => ['default' => 'masonry']],
                    ],
                ],
            ],
            'sDESIGN' => [
                'ROOT' => [
                    'el' => [
                        'settings.frameWidth' => ['config' => ['default' => '2']],
                        'settings.frameAccentColor' => ['config' => ['default' => '']],
                        'settings.lbOverlayAlpha' => ['config' => ['default' => '0.92']],
                    ],
                ],
            ],
        ],
    ];
}

function assertSame(mixed $expected, mixed $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

function assertNull(mixed $actual, string $message, array &$failures): void
{
    if ($actual !== null) {
        $failures[] = $message . ' (expected null, got ' . var_export($actual, true) . ')';
    }
}

function assertXmlDefault(
    MosaicGalleryCreationDefaultsDefinition $definition,
    string $key,
    mixed $invalidValue,
    string $sheet,
    string $field,
    string $expectedDefault,
    string $message,
    array &$failures,
): void {
    $patched = $definition->applyToDataStructure(sampleDataStructure(), [$key => $invalidValue]);
    assertSame(
        $expectedDefault,
        $patched['sheets'][$sheet]['ROOT']['el'][$field]['config']['default'],
        $message,
        $failures,
    );
}

// 1. no settings.defaults -> XML DS defaults remain untouched
$ds = sampleDataStructure();
$unchanged = $definition->applyToDataStructure($ds, []);
assertSame('12', $unchanged['sheets']['sDEF']['ROOT']['el']['settings.gap']['config']['default'], 'Case 1 gap unchanged', $failures);

// 2. defaults.gap = 20
$patched = $definition->applyToDataStructure(sampleDataStructure(), ['gap' => 20]);
assertSame('20', $patched['sheets']['sDEF']['ROOT']['el']['settings.gap']['config']['default'], 'Case 2 gap=20', $failures);

// 3. invalid defaults.gap
assertXmlDefault($definition, 'gap', 'garbage', 'sDEF', 'settings.gap', '12', 'Case 3 invalid gap ignored', $failures);

// 4. defaults.enableLightbox = 0
$lightboxOff = $definition->applyToDataStructure(sampleDataStructure(), ['enableLightbox' => 0]);
assertSame('0', $lightboxOff['sheets']['sDEF']['ROOT']['el']['settings.enableLightbox']['config']['default'], 'Case 4 enableLightbox=0', $failures);

// 5. defaults.frameWidth = 0
$frameZero = $definition->applyToDataStructure(sampleDataStructure(), ['frameWidth' => 0]);
assertSame('0', $frameZero['sheets']['sDESIGN']['ROOT']['el']['settings.frameWidth']['config']['default'], 'Case 5 frameWidth=0', $failures);

// 6. valid select layoutMode = grid
$grid = $definition->applyToDataStructure(sampleDataStructure(), ['layoutMode' => 'grid']);
assertSame('grid', $grid['sheets']['sDEF']['ROOT']['el']['settings.layoutMode']['config']['default'], 'Case 6 layoutMode=grid', $failures);

// 7. invalid select layoutMode = garbage
$badSelect = $definition->applyToDataStructure(sampleDataStructure(), ['layoutMode' => 'garbage']);
assertSame('masonry', $badSelect['sheets']['sDEF']['ROOT']['el']['settings.layoutMode']['config']['default'], 'Case 7 invalid select ignored', $failures);

// 8. optional empty color where allowed
$emptyAccent = $definition->applyToDataStructure(sampleDataStructure(), ['frameAccentColor' => '']);
assertSame('', $emptyAccent['sheets']['sDESIGN']['ROOT']['el']['settings.frameAccentColor']['config']['default'], 'Case 8 empty accent', $failures);

// 11. partial pre-filled NEW flexform: definition only patches DS, not databaseRow
$partial = $definition->applyToDataStructure(sampleDataStructure(), ['gap' => 20]);
assertSame('20', $partial['sheets']['sDEF']['ROOT']['el']['settings.gap']['config']['default'], 'Case 11 DS gap patched only', $failures);

// BOOLEAN normalization
assertSame('0', $definition->normalizeValue('enableLightbox', 0), 'Boolean enableLightbox=0 accepted', $failures);
assertSame('1', $definition->normalizeValue('enableLightbox', 1), 'Boolean enableLightbox=1 accepted', $failures);
assertNull($definition->normalizeValue('enableLightbox', 2), 'Boolean enableLightbox=2 rejected', $failures);
assertNull($definition->normalizeValue('enableLightbox', -1), 'Boolean enableLightbox=-1 rejected', $failures);
assertNull($definition->normalizeValue('enableLightbox', ''), 'Boolean enableLightbox empty rejected', $failures);
assertSame('0', $definition->normalizeValue('enableLightbox', 'off'), 'Boolean enableLightbox=off accepted', $failures);
assertSame('1', $definition->normalizeValue('enableLightbox', 'on'), 'Boolean enableLightbox=on accepted', $failures);
assertXmlDefault($definition, 'enableLightbox', 2, 'sDEF', 'settings.enableLightbox', '1', 'Boolean invalid enableLightbox=2 preserves XML default', $failures);

// INTEGER normalization
assertSame('0', $definition->normalizeValue('frameWidth', 0), 'Integer frameWidth=0 accepted', $failures);
assertSame('2', $definition->normalizeValue('frameWidth', 2.0), 'Integer frameWidth=2.0 accepted', $failures);
assertNull($definition->normalizeValue('frameWidth', 2.5), 'Integer frameWidth=2.5 rejected', $failures);
assertNull($definition->normalizeValue('frameWidth', '2.5'), 'Integer frameWidth="2.5" rejected', $failures);
assertNull($definition->normalizeValue('frameWidth', 13), 'Integer frameWidth=13 rejected by max', $failures);
assertXmlDefault($definition, 'frameWidth', 2.5, 'sDESIGN', 'settings.frameWidth', '2', 'Integer invalid frameWidth=2.5 preserves XML default', $failures);

// ALPHA normalization
assertSame('0', $definition->normalizeValue('lbOverlayAlpha', 0), 'Alpha lbOverlayAlpha=0 accepted', $failures);
assertSame('0.92', $definition->normalizeValue('lbOverlayAlpha', 0.92), 'Alpha lbOverlayAlpha=0.92 accepted', $failures);
assertSame('1', $definition->normalizeValue('lbOverlayAlpha', 1), 'Alpha lbOverlayAlpha=1 accepted', $failures);
assertNull($definition->normalizeValue('lbOverlayAlpha', -0.1), 'Alpha lbOverlayAlpha=-0.1 rejected', $failures);
assertNull($definition->normalizeValue('lbOverlayAlpha', 1.1), 'Alpha lbOverlayAlpha=1.1 rejected', $failures);
assertXmlDefault($definition, 'lbOverlayAlpha', 1.1, 'sDESIGN', 'settings.lbOverlayAlpha', '0.92', 'Alpha invalid lbOverlayAlpha=1.1 preserves XML default', $failures);

// Provider gate checks (static source inspection)
$providerSource = file_get_contents(dirname(__DIR__) . '/Classes/Backend/Form/FormDataProvider/MosaicGalleryFlexFormDefaultsProvider.php');
if ($providerSource === false || !str_contains($providerSource, "command'] ?? '') !== 'new'")) {
    $failures[] = 'Case 9 provider must gate command=new';
}
if ($providerSource === false || str_contains($providerSource, "databaseRow'][self::FLEX_FIELD]")) {
    $failures[] = 'Case 11 provider must not write databaseRow pi_flexform values';
}

if ($failures !== []) {
    fwrite(STDERR, "TypoScript creation-defaults fixture test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "TypoScript creation-defaults fixture test passed.\n");
exit(0);
