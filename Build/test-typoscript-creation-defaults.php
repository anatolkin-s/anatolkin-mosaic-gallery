<?php
declare(strict_types=1);

/**
 * Behavioral fixtures for TypoScript creation defaults (Issue #2).
 * Run: php Build/test-typoscript-creation-defaults.php
 */

require dirname(__DIR__) . '/Classes/Service/DesignPresetResolver.php';
require dirname(__DIR__) . '/Classes/Service/MosaicGalleryCreationDefaultsDefinition.php';
require dirname(__DIR__) . '/Classes/Service/MosaicGalleryCreationDesignOverridesBuilder.php';

use Anatolkin\MosaicGallery\Service\DesignPresetResolver;
use Anatolkin\MosaicGallery\Service\MosaicGalleryCreationDefaultsDefinition;
use Anatolkin\MosaicGallery\Service\MosaicGalleryCreationDesignOverridesBuilder;

$definition = new MosaicGalleryCreationDefaultsDefinition();
$resolver = new DesignPresetResolver();
$overridesBuilder = new MosaicGalleryCreationDesignOverridesBuilder($definition, $resolver);
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
                        'settings.designOverrides' => ['config' => ['default' => '']],
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

/** @param array<string, mixed> $dataStructure @param array<string, scalar> $siteDefaults */
function applyProviderDesignOverridesDefault(
    array $dataStructure,
    array $siteDefaults,
    MosaicGalleryCreationDesignOverridesBuilder $overridesBuilder,
): array {
    $existingDefault = $dataStructure['sheets']['sDESIGN']['ROOT']['el']['settings.designOverrides']['config']['default'] ?? null;
    if (is_string($existingDefault) && trim($existingDefault) !== '' && trim($existingDefault) !== '{}') {
        return $dataStructure;
    }

    $overridesJson = $overridesBuilder->buildJson($siteDefaults);
    if ($overridesJson === null || $overridesJson === '' || $overridesJson === '{}') {
        return $dataStructure;
    }

    $dataStructure['sheets']['sDESIGN']['ROOT']['el']['settings.designOverrides']['config']['default'] = $overridesJson;

    return $dataStructure;
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

// A. Named Clean preset overrides synthesis
$cleanDefaults = [
    'designPreset' => 'clean',
    'frameWidth' => 3,
    'borderRadius' => 11,
    'lbOverlayAlpha' => 0.55,
    'lbCaptionAlign' => 'right',
];
$cleanJson = $overridesBuilder->buildJson($cleanDefaults);
$cleanDocument = $cleanJson !== null ? json_decode($cleanJson, true, 512, JSON_THROW_ON_ERROR) : [];
assertSame(
    [
        'frameWidth' => '3',
        'borderRadius' => 11,
        'lightbox' => [
            'overlayAlpha' => '0.55',
            'captionAlign' => 'right',
        ],
    ],
    $cleanDocument,
    'Case A clean preset overrides document',
    $failures,
);

// B. Complete design-default mapping
$completeDefaults = [
    'designPreset' => 'bootstrap',
    'frameColor' => '#112233',
    'frameAccentColor' => '#445566',
    'frameWidth' => 4,
    'frameStyle' => 'dashed',
    'borderRadius' => 5,
    'shadow' => 1,
    'backgroundColor' => '#AABBCC',
    'captionColor' => '#DDEEFF',
    'applyTo' => 'tiles',
    'lbOverlay' => '#010203',
    'lbOverlayAlpha' => 0.44,
    'lbNavColor' => '#040506',
    'lbCloseColor' => '#070809',
    'lbCaptionColor' => '#0A0B0C',
    'lbCaptionBg' => '#0D0E0F',
    'lbCaptionBgAlpha' => 0.33,
    'lbCaptionAlign' => 'center',
    'lbCaptionSize' => 'large',
    'lbCaptionStyle' => 'italic',
];
$completeJson = $overridesBuilder->buildJson($completeDefaults);
$completeDocument = $completeJson !== null ? json_decode($completeJson, true, 512, JSON_THROW_ON_ERROR) : [];
assertSame('#112233', $completeDocument['frameColor'] ?? null, 'Case B frameColor mapping', $failures);
assertSame('#445566', $completeDocument['frameAccentColor'] ?? null, 'Case B frameAccentColor mapping', $failures);
assertSame('4', $completeDocument['frameWidth'] ?? null, 'Case B frameWidth mapping', $failures);
assertSame('dashed', $completeDocument['frameStyle'] ?? null, 'Case B frameStyle mapping', $failures);
assertSame(5, $completeDocument['borderRadius'] ?? null, 'Case B borderRadius mapping', $failures);
assertSame(true, $completeDocument['shadow'] ?? null, 'Case B shadow mapping', $failures);
assertSame('#AABBCC', $completeDocument['backgroundColor'] ?? null, 'Case B backgroundColor mapping', $failures);
assertSame('#DDEEFF', $completeDocument['captionColor'] ?? null, 'Case B captionColor mapping', $failures);
assertSame('tiles', $completeDocument['applyTo'] ?? null, 'Case B applyTo mapping', $failures);
assertSame('#010203', $completeDocument['lightbox']['overlay'] ?? null, 'Case B lbOverlay mapping', $failures);
assertSame('0.44', $completeDocument['lightbox']['overlayAlpha'] ?? null, 'Case B lbOverlayAlpha mapping', $failures);
assertSame('#040506', $completeDocument['lightbox']['navColor'] ?? null, 'Case B lbNavColor mapping', $failures);
assertSame('#070809', $completeDocument['lightbox']['closeColor'] ?? null, 'Case B lbCloseColor mapping', $failures);
assertSame('#0A0B0C', $completeDocument['lightbox']['captionColor'] ?? null, 'Case B lbCaptionColor mapping', $failures);
assertSame('#0D0E0F', $completeDocument['lightbox']['captionBackground'] ?? null, 'Case B lbCaptionBg mapping', $failures);
assertSame('0.33', $completeDocument['lightbox']['captionBackgroundAlpha'] ?? null, 'Case B lbCaptionBgAlpha mapping', $failures);
assertSame('center', $completeDocument['lightbox']['captionAlign'] ?? null, 'Case B lbCaptionAlign mapping', $failures);
assertSame('large', $completeDocument['lightbox']['captionSize'] ?? null, 'Case B lbCaptionSize mapping', $failures);
assertSame('italic', $completeDocument['lightbox']['captionStyle'] ?? null, 'Case B lbCaptionStyle mapping', $failures);

// C. Custom preset keeps individual FlexForm defaults and skips override synthesis
$customDefaults = ['frameWidth' => 7, 'borderRadius' => 9];
assertNull($overridesBuilder->buildJson($customDefaults), 'Case C custom preset skips overrides JSON', $failures);
$customDs = $definition->applyToDataStructure(sampleDataStructure(), $customDefaults);
assertSame('7', $customDs['sheets']['sDESIGN']['ROOT']['el']['settings.frameWidth']['config']['default'], 'Case C custom preset keeps frameWidth DS default', $failures);
assertSame('', $customDs['sheets']['sDESIGN']['ROOT']['el']['settings.designOverrides']['config']['default'], 'Case C custom preset leaves designOverrides empty', $failures);

// D. Named preset without design keys does not synthesize designOverrides
$namedOnlyPreset = ['designPreset' => 'clean'];
assertNull($overridesBuilder->buildJson($namedOnlyPreset), 'Case D no design keys -> no overrides JSON', $failures);
$namedOnlyDs = applyProviderDesignOverridesDefault(
    $definition->applyToDataStructure(sampleDataStructure(), $namedOnlyPreset),
    $namedOnlyPreset,
    $overridesBuilder,
);
assertSame('', $namedOnlyDs['sheets']['sDESIGN']['ROOT']['el']['settings.designOverrides']['config']['default'], 'Case D no designOverrides default synthesized', $failures);

// E. Prefilled designOverrides must remain authoritative
$prefilledDs = sampleDataStructure();
$prefilledDs['sheets']['sDESIGN']['ROOT']['el']['settings.designOverrides']['config']['default'] = '{"frameWidth":"9"}';
$prefilledResult = applyProviderDesignOverridesDefault(
    $definition->applyToDataStructure($prefilledDs, $cleanDefaults),
    $cleanDefaults,
    $overridesBuilder,
);
assertSame('{"frameWidth":"9"}', $prefilledResult['sheets']['sDESIGN']['ROOT']['el']['settings.designOverrides']['config']['default'], 'Case E prefilled designOverrides preserved', $failures);

// Provider integration for named preset
$integratedDs = applyProviderDesignOverridesDefault(
    $definition->applyToDataStructure(sampleDataStructure(), $cleanDefaults),
    $cleanDefaults,
    $overridesBuilder,
);
$integratedDefault = $integratedDs['sheets']['sDESIGN']['ROOT']['el']['settings.designOverrides']['config']['default'] ?? '';
$integratedDocument = json_decode((string)$integratedDefault, true, 512, JSON_THROW_ON_ERROR);
assertSame('3', $integratedDocument['frameWidth'] ?? null, 'Provider injects clean preset overrides', $failures);

// Site preset uses same override synthesis without preset-base logic in builder
$siteDefaults = ['designPreset' => 'site', 'frameWidth' => 2];
$siteJson = $overridesBuilder->buildJson($siteDefaults);
$siteDocument = $siteJson !== null ? json_decode($siteJson, true, 512, JSON_THROW_ON_ERROR) : [];
assertSame('2', $siteDocument['frameWidth'] ?? null, 'Site preset synthesizes explicit overrides only', $failures);

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
if ($providerSource === false || !str_contains($providerSource, 'MosaicGalleryCreationDesignOverridesBuilder')) {
    $failures[] = 'Provider must synthesize named-preset designOverrides defaults';
}

// F. Gap proxy contract
$js = file_get_contents(dirname(__DIR__) . '/Resources/Public/JavaScript/design-configurator.js');
if ($js === false) {
    $failures[] = 'Case F design-configurator.js unreadable';
} else {
    if (preg_match("/settings\\.gap'\\s*&&[\\s\\S]*'12'/m", $js) === 1) {
        $failures[] = 'Case F gap proxy must not hardcode fallback 12';
    }
    if (!str_contains($js, 'designProxyDefault')) {
        $failures[] = 'Case F gap proxy must read server-provided designProxyDefault';
    }
    if (!str_contains($js, 'defaultValue')) {
        $failures[] = 'Case F gap proxy must read canonical defaultValue';
    }
}
$designElement = file_get_contents(dirname(__DIR__) . '/Classes/Backend/Form/Element/DesignConfiguratorElement.php');
if ($designElement === false || !str_contains($designElement, 'data-design-proxy-default')) {
    $failures[] = 'Case F DesignConfiguratorElement must expose proxy defaults from FormEngine values';
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
