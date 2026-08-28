<?php
declare(strict_types=1);

/**
 * Structural checks for TypoScript creation defaults (Issue #2).
 * Run: php Build/check-typoscript-defaults-contract.php
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

$reader = readFileOrFail($root . '/Classes/Service/FrontendTypoScriptDefaultsReader.php', $failures);
$definition = readFileOrFail($root . '/Classes/Service/MosaicGalleryCreationDefaultsDefinition.php', $failures);
$overridesBuilder = readFileOrFail($root . '/Classes/Service/MosaicGalleryCreationDesignOverridesBuilder.php', $failures);
$provider = readFileOrFail(
    $root . '/Classes/Backend/Form/FormDataProvider/MosaicGalleryFlexFormDefaultsProvider.php',
    $failures,
);
$designConfiguratorJs = readFileOrFail($root . '/Resources/Public/JavaScript/design-configurator.js', $failures);
$designConfiguratorElement = readFileOrFail($root . '/Classes/Backend/Form/Element/DesignConfiguratorElement.php', $failures);
$extLocalconf = readFileOrFail($root . '/ext_localconf.php', $failures);
$services = readFileOrFail($root . '/Configuration/Services.yaml', $failures);
$setup = readFileOrFail($root . '/Configuration/TypoScript/setup.typoscript', $failures);
$readme = readFileOrFail($root . '/README.md', $failures);
$controller = readFileOrFail($root . '/Classes/Controller/GalleryController.php', $failures);
$resolver = readFileOrFail($root . '/Classes/Service/DesignPresetResolver.php', $failures);

if (!str_contains($reader, 'class FrontendTypoScriptDefaultsReader')) {
    $failures[] = 'FrontendTypoScriptDefaultsReader class must exist';
}
if (!str_contains($reader, 'FrontendTypoScriptFactory')) {
    $failures[] = 'FrontendTypoScriptDefaultsReader must use FrontendTypoScriptFactory';
}
if (!str_contains($reader, 'settings.defaults')) {
    $failures[] = 'FrontendTypoScriptDefaultsReader must resolve settings.defaults';
}
if (!str_contains($reader, '@internal')) {
    $failures[] = 'FrontendTypoScriptDefaultsReader must document @internal Core API isolation';
}

if (!str_contains($definition, 'class MosaicGalleryCreationDefaultsDefinition')) {
    $failures[] = 'MosaicGalleryCreationDefaultsDefinition class must exist';
}
if (!str_contains($definition, 'FIELD_MAP')) {
    $failures[] = 'MosaicGalleryCreationDefaultsDefinition must define an explicit FIELD_MAP allowlist';
}
if (!str_contains($definition, 'fieldDefinition')) {
    $failures[] = 'MosaicGalleryCreationDefaultsDefinition must expose fieldDefinition helper';
}
if (str_contains($definition, 'siteDesignPreset')) {
    $failures[] = 'siteDesignPreset must not be exposed in creation defaults definition';
}

if (!str_contains($overridesBuilder, 'class MosaicGalleryCreationDesignOverridesBuilder')) {
    $failures[] = 'MosaicGalleryCreationDesignOverridesBuilder class must exist';
}
if (str_contains($overridesBuilder, 'BUILT_IN_PRESETS')) {
    $failures[] = 'Creation design overrides builder must not embed preset base values';
}
if (!str_contains($overridesBuilder, 'lightbox')) {
    $failures[] = 'Creation design overrides builder must map lightbox override paths';
}

if (!str_contains($provider, 'class MosaicGalleryFlexFormDefaultsProvider')) {
    $failures[] = 'MosaicGalleryFlexFormDefaultsProvider class must exist';
}
if (!str_contains($provider, "command'] ?? '') !== 'new'")) {
    $failures[] = 'FormDataProvider must gate on command=new';
}
if (!str_contains($provider, 'effectivePid')) {
    $failures[] = 'FormDataProvider must use effectivePid for TypoScript context';
}
if (preg_match('/databaseRow[^\n;]*vDEF/', $provider)) {
    $failures[] = 'FormDataProvider must not mutate databaseRow vDEF directly';
}
if (!str_contains($provider, 'MosaicGalleryCreationDesignOverridesBuilder')) {
    $failures[] = 'FormDataProvider must synthesize named-preset designOverrides defaults';
}
if (!str_contains($provider, 'settings.designOverrides')) {
    $failures[] = 'FormDataProvider must target settings.designOverrides creation default';
}

if (preg_match("/settings\\.gap'\\s*&&[\\s\\S]*'12'/m", $designConfiguratorJs)) {
    $failures[] = 'design-configurator.js must not hardcode settings.gap fallback 12';
}
if (!str_contains($designConfiguratorJs, 'designProxyDefault')) {
    $failures[] = 'design-configurator.js must read server-provided proxy defaults';
}
if (!str_contains($designConfiguratorElement, 'data-design-proxy-default')) {
    $failures[] = 'DesignConfiguratorElement must expose proxy defaults from FormEngine values';
}

if (!str_contains($extLocalconf, 'MosaicGalleryFlexFormDefaultsProvider::class')) {
    $failures[] = 'ext_localconf.php must register MosaicGalleryFlexFormDefaultsProvider';
}
if (!str_contains($extLocalconf, 'TcaFlexPrepare::class')) {
    $failures[] = 'FormDataProvider registration must depend on TcaFlexPrepare';
}
if (!preg_match(
    '/\[TcaFlexProcess::class\]\[\'depends\'\][\s\S]*MosaicGalleryFlexFormDefaultsProvider::class/',
    $extLocalconf,
)) {
    $failures[] = 'TcaFlexProcess must depend on MosaicGalleryFlexFormDefaultsProvider';
}
if (str_contains($extLocalconf, 'ModifyFlexFormDataStructureEvent')) {
    $failures[] = 'Do not reference nonexistent ModifyFlexFormDataStructureEvent';
}

if (!str_contains($services, 'FrontendTypoScriptDefaultsReader')) {
    $failures[] = 'Services.yaml must register FrontendTypoScriptDefaultsReader';
}
if (!str_contains($services, 'MosaicGalleryCreationDefaultsDefinition')) {
    $failures[] = 'Services.yaml must register MosaicGalleryCreationDefaultsDefinition';
}
if (!str_contains($services, 'MosaicGalleryCreationDesignOverridesBuilder')) {
    $failures[] = 'Services.yaml must register MosaicGalleryCreationDesignOverridesBuilder';
}
if (!str_contains($services, 'MosaicGalleryFlexFormDefaultsProvider')) {
    $failures[] = 'Services.yaml must register MosaicGalleryFlexFormDefaultsProvider';
}

if (!str_contains($setup, 'source = folder')) {
    $failures[] = 'setup.typoscript must keep runtime setting source = folder';
}
if (!str_contains($setup, 'folder = fileadmin/gallery/')) {
    $failures[] = 'setup.typoscript must keep runtime setting folder = fileadmin/gallery/';
}
if (!str_contains($setup, 'recursive = 1')) {
    $failures[] = 'setup.typoscript must keep runtime setting recursive = 1';
}
if (!str_contains($setup, 'gap = 12')) {
    $failures[] = 'setup.typoscript must keep runtime setting gap = 12';
}
$documentsDefaultsNamespace =
    str_contains($setup, 'settings.defaults')
    || preg_match(
        '/settings\s*\{[\s\S]*?#\s*defaults\s*\{/',
        $setup
    ) === 1;
if (!$documentsDefaultsNamespace) {
    $failures[] = 'setup.typoscript must document settings.defaults namespace';
}

if (!str_contains($readme, 'settings.defaults')) {
    $failures[] = 'README must document settings.defaults creation defaults';
}
if (str_contains($readme, 'siteDesignPreset')) {
    $failures[] = 'README must not document siteDesignPreset for Issue #2';
}

if (!str_contains($controller, "sortBy'] ?? 'name'")) {
    $failures[] = 'GalleryController historical sortBy fallback must remain name';
}
if (!str_contains($controller, "itemsPerPage'] ?? 12")) {
    $failures[] = 'GalleryController historical itemsPerPage fallback must remain 12';
}
if (str_contains($controller, 'GallerySettingsResolver') || str_contains($controller, 'CreationDefaultsDefinition')) {
    $failures[] = 'GalleryController must not use runtime creation-default resolver';
}

if (!str_contains($resolver, "shadow'] ?? false")) {
    $failures[] = 'DesignPresetResolver historical shadow fallback must remain false';
}
if (!str_contains($resolver, "PRESET_LEGACY")) {
    $failures[] = 'DesignPresetResolver legacy preset path must remain';
}
if (str_contains($resolver, 'siteDesignPreset')) {
    $failures[] = 'DesignPresetResolver must not add siteDesignPreset handling in Issue #2';
}

$allowedKeys = [
    'source', 'folder', 'recursive', 'sortBy', 'sortDir', 'gap', 'layoutMode', 'maxItemsPerRow', 'maxWidth',
    'enableLightbox', 'showCaptions', 'captionAlign', 'useFalCaptions', 'enableLoadMore', 'loadMoreUseFrameStyle',
    'itemsPerPage', 'loadStep', 'designPreset', 'frameColor', 'frameAccentColor', 'frameWidth', 'frameStyle',
    'borderRadius', 'shadow', 'backgroundColor', 'captionColor', 'applyTo', 'lbOverlay', 'lbOverlayAlpha',
    'lbNavColor', 'lbCloseColor', 'lbCaptionColor', 'lbCaptionBg', 'lbCaptionBgAlpha', 'lbCaptionAlign',
    'lbCaptionSize', 'lbCaptionStyle',
];
foreach ($allowedKeys as $key) {
    if (!str_contains($definition, "'$key'")) {
        $failures[] = 'Creation defaults allowlist must include key: ' . $key;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "TypoScript creation-defaults contract check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "TypoScript creation-defaults contract check passed.\n");
exit(0);
