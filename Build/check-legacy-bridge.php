<?php
declare(strict_types=1);

/**
 * Structural checks for the 0.4.1 legacy-upgrade compatibility bridge.
 * Run: php Build/check-legacy-bridge.php
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

$extLocalconf = readFileOrFail($root . '/ext_localconf.php', $failures);
$ttContent = readFileOrFail($root . '/Configuration/TCA/Overrides/tt_content.php', $failures);
$legacyWizard = readFileOrFail($root . '/Classes/Upgrades/MosaicGalleryLegacyCTypeMigration.php', $failures);
$retiredWizard = readFileOrFail($root . '/Classes/Upgrades/MosaicGalleryCTypeMigration.php', $failures);
$services = readFileOrFail($root . '/Configuration/Services.yaml', $failures);
$setup = readFileOrFail($root . '/ext_typoscript_setup.typoscript', $failures);
$emconf = readFileOrFail($root . '/ext_emconf.php', $failures);
$pageTs = readFileOrFail($root . '/Configuration/PageTS/Mod/Wizards/NewContentElement.tsconfig', $failures);
$designConfigurator = readFileOrFail($root . '/Classes/Backend/Form/Element/DesignConfiguratorElement.php', $failures);

$configurePluginCount = preg_match_all('/ExtensionUtility::configurePlugin\s*\(/', $extLocalconf);
if ($configurePluginCount !== 1) {
    $failures[] = 'ext_localconf.php must call configurePlugin exactly once, found ' . $configurePluginCount;
}
if (!str_contains($extLocalconf, 'PLUGIN_TYPE_CONTENT_ELEMENT')) {
    $failures[] = 'ext_localconf.php must keep PLUGIN_TYPE_CONTENT_ELEMENT';
}
if (!str_contains($extLocalconf, 'addTypoScript(')) {
    $failures[] = 'ext_localconf.php must register legacy aliases with addTypoScript()';
}
if (!str_contains($extLocalconf, 'defaultContentRendering')) {
    $failures[] = 'ext_localconf.php must use defaultContentRendering for legacy aliases';
}
if (!str_contains($extLocalconf, 'tt_content.list.20.mosaicgallery_pi1 < tt_content.mosaicgallery_pi1.20')) {
    $failures[] = 'Missing mosaicgallery_pi1 list.20 alias to mosaicgallery_pi1.20';
}
if (!str_contains($extLocalconf, 'tt_content.list.20.anatolkinmosaicgallery_pi1 < tt_content.mosaicgallery_pi1.20')) {
    $failures[] = 'Missing anatolkinmosaicgallery_pi1 list.20 alias to mosaicgallery_pi1.20';
}
if (!str_contains($extLocalconf, 'getMajorVersion() < 14')) {
    $failures[] = 'Frontend aliases must be gated to TYPO3 major version < 14';
}
$configurePluginPosition = strpos($extLocalconf, 'ExtensionUtility::configurePlugin(');
$addTypoScriptPosition = strpos($extLocalconf, 'ExtensionManagementUtility::addTypoScript(');
if ($configurePluginPosition === false || $addTypoScriptPosition === false || $configurePluginPosition > $addTypoScriptPosition) {
    $failures[] = 'addTypoScript() must be registered after configurePlugin()';
}

if (!str_contains($ttContent, "'mosaicgallery_pi1'") || !str_contains($ttContent, "'anatolkinmosaicgallery_pi1'")) {
    $failures[] = 'tt_content.php must register both legacy list_type signatures';
}
if (!str_contains($ttContent, 'subtypes_addlist')) {
    $failures[] = 'tt_content.php must restore list subtypes for FlexForm and metadata';
}
if (!preg_match("/getMajorVersion\(\)\s*<\s*14/", $ttContent)) {
    $failures[] = 'TCA list_type compatibility must be gated to TYPO3 major version < 14';
}
if (!str_contains($ttContent, 'registerPlugin')) {
    $failures[] = 'Canonical CType registerPlugin() must remain';
}

if (substr_count($legacyWizard, "UpgradeWizard('mosaicGalleryCTypeMigration')") < 2) {
    $failures[] = 'MosaicGalleryLegacyCTypeMigration must register identifier mosaicGalleryCTypeMigration on both TYPO3 13 and 14 branches';
}
if (!str_contains($legacyWizard, 'class MosaicGalleryLegacyCTypeMigration')) {
    $failures[] = 'Active wizard class MosaicGalleryLegacyCTypeMigration is missing';
}
if (!str_contains($legacyWizard, '\\TYPO3\\CMS\\Core\\Upgrades\\RepeatableInterface')
    || !str_contains($legacyWizard, '\\TYPO3\\CMS\\Install\\Updates\\RepeatableInterface')) {
    $failures[] = 'Both TYPO3 13 and 14 RepeatableInterface namespaces must be implemented';
}
if (!str_contains($legacyWizard, "'mosaicgallery_pi1' => 'mosaicgallery_pi1'")
    || !str_contains($legacyWizard, "'anatolkinmosaicgallery_pi1' => 'mosaicgallery_pi1'")) {
    $failures[] = 'Wizard mapping for both legacy signatures must be preserved';
}

if (str_contains($retiredWizard, 'UpgradeWizard')) {
    $failures[] = 'Retired MosaicGalleryCTypeMigration must not register an UpgradeWizard attribute';
}
if (!str_contains($retiredWizard, 'class MosaicGalleryCTypeMigration')) {
    $failures[] = 'Retired MosaicGalleryCTypeMigration class must remain for the previous class identity';
}

if (!str_contains($services, 'MosaicGalleryLegacyCTypeMigration')) {
    $failures[] = 'Services.yaml must register MosaicGalleryLegacyCTypeMigration';
}
if (preg_match('/Upgrades\\\\MosaicGalleryCTypeMigration:/', $services)) {
    $failures[] = 'Services.yaml must not register the retired MosaicGalleryCTypeMigration wizard class';
}

if (str_contains($setup, 'tt_content.list.20.')) {
    $failures[] = 'ext_typoscript_setup.typoscript must not contain list.20 aliases';
}

if (!str_contains($emconf, "'version' => '0.4.0'")) {
    $failures[] = 'ext_emconf.php version must remain 0.4.0 in this pass';
}

if (str_contains($extLocalconf, 'addPageTSConfig')) {
    $failures[] = 'Do not restore addPageTSConfig() wizard registration';
}
if (str_contains($pageTs, 'CType = list') && str_contains($extLocalconf, 'NewContentElement')) {
    $failures[] = 'Stale New Content Element PageTS must not be reactivated';
}

if (preg_match('/return \$flexForm\[[\'"]settings[\'"]\];/', $designConfigurator)) {
    $failures[] = 'DesignConfiguratorElement must not return raw FormEngine settings arrays';
}
if (!str_contains($designConfigurator, 'function unwrapFormEngineSettingValue(')
    || !str_contains($designConfigurator, 'function isVDefFormEngineWrapper(')
    || !str_contains($designConfigurator, 'function isSingleValueFormEngineWrapper(')) {
    $failures[] = 'DesignConfiguratorElement must narrowly unwrap recognized FormEngine settings wrappers';
}
if (str_contains($designConfigurator, 'flexFormRowData')) {
    $failures[] = 'DesignConfiguratorElement must not overlay flexFormRowData in this hotfix';
}
if (!str_contains($designConfigurator, "\$value = \$fieldValue['vDEF'] ?? ''")
    || !str_contains($designConfigurator, '$value === []')) {
    $failures[] = 'DesignConfiguratorElement must normalize empty legacy vDEF arrays to empty strings';
}

if ($failures === []) {
    fwrite(STDOUT, "Legacy bridge structural checks passed.\n");
    exit(0);
}

fwrite(STDERR, "Legacy bridge structural checks failed:\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
