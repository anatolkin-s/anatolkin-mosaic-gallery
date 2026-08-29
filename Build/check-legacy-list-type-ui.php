<?php
declare(strict_types=1);

/**
 * Structural/behavioral checks for hiding legacy list_type from TYPO3 13 creation UI.
 * Run: php Build/check-legacy-list-type-ui.php
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

/** @param list<array<string, mixed>> $items @return list<string> */
function itemValues(array $items): array
{
    $values = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $values[] = (string)($item['value'] ?? $item[1] ?? '');
    }
    return $values;
}

$ttContent = readFileOrFail($root . '/Configuration/TCA/Overrides/tt_content.php', $failures);
$provider = readFileOrFail(
    $root . '/Classes/Backend/Form/FormDataProvider/MosaicGalleryLegacyListTypeVisibilityProvider.php',
    $failures,
);
$extLocalconf = readFileOrFail($root . '/ext_localconf.php', $failures);
$services = readFileOrFail($root . '/Configuration/Services.yaml', $failures);
$pageTs = readFileOrFail($root . '/Configuration/PageTS/Mod/Wizards/NewContentElement.tsconfig', $failures);
$readme = readFileOrFail($root . '/README.md', $failures);
$language = readFileOrFail($root . '/Resources/Private/Language/locallang_be.xlf', $failures);

// A. no manual legacy New Content Wizard entry is active
if (str_contains($pageTs, 'CType = list') || str_contains($pageTs, 'list_type = mosaicgallery_pi1')) {
    $failures[] = 'A: Stale New Content Element PageTS must not offer legacy list_type creation';
}
if (!str_contains($pageTs, 'Intentionally inactive')) {
    $failures[] = 'A: NewContentElement.tsconfig must remain an explicit inactive stub';
}
if (str_contains($extLocalconf, 'addPageTSConfig')) {
    $failures[] = 'A: Do not reactivate addPageTSConfig() for New Content Element wizard';
}

// B. TYPO3 13 static TCA still contains both legacy values
if (!preg_match(
    "/foreach\s*\(\s*\[\s*'mosaicgallery_pi1'\s*,\s*'anatolkinmosaicgallery_pi1'\s*\]\s+as\s+\\\$legacyListType\s*\)/",
    $ttContent,
) || !preg_match("/list_type']\['config']\['items'\]\[\]\s*=\s*\[[\s\S]*?'value'\s*=>\s*\\\$legacyListType/", $ttContent)
) {
    $failures[] = 'B: TYPO3 13 static TCA must still append both legacy list_type values';
}
if (!str_contains($ttContent, 'subtypes_addlist')
    || !str_contains($ttContent, 'addPiFlexFormValue($legacyListType')) {
    $failures[] = 'B: Legacy subtypes_addlist / addPiFlexFormValue wiring must remain';
}

// C. no global tt_content.list_type itemsProcFunc is installed by this extension
if (str_contains($ttContent, 'itemsProcFunc')
    || str_contains($ttContent, 'MosaicGalleryLegacyListTypeItems')
    || is_file($root . '/Classes/Backend/Form/MosaicGalleryLegacyListTypeItems.php')
) {
    $failures[] = 'C: Extension must not install a global list_type itemsProcFunc';
}

// D. provider is registered only for TYPO3 13
if (!str_contains($provider, 'class MosaicGalleryLegacyListTypeVisibilityProvider')) {
    $failures[] = 'D: MosaicGalleryLegacyListTypeVisibilityProvider class must exist';
}
if (!str_contains($extLocalconf, 'MosaicGalleryLegacyListTypeVisibilityProvider::class')) {
    $failures[] = 'D: ext_localconf.php must register the legacy visibility provider';
}
if (!str_contains($extLocalconf, 'TcaSelectItems::class')) {
    $failures[] = 'D: Provider registration must depend on TcaSelectItems';
}
if (!preg_match(
    '/getMajorVersion\(\)\s*<\s*14\s*\)\s*\{\s*\$GLOBALS\[\'TYPO3_CONF_VARS\'\]\[\'SYS\'\]\[\'formEngine\'\]\[\'formDataGroup\'\]\[\'tcaDatabaseRecord\'\]\[MosaicGalleryLegacyListTypeVisibilityProvider::class\]/s',
    $extLocalconf,
)) {
    $failures[] = 'D: Legacy visibility provider must be registered only for TYPO3 < 14';
}
if (!str_contains($services, 'MosaicGalleryLegacyListTypeVisibilityProvider')) {
    $failures[] = 'D: Services.yaml must register MosaicGalleryLegacyListTypeVisibilityProvider';
}

if (!interface_exists(\TYPO3\CMS\Backend\Form\FormDataProviderInterface::class)) {
    eval('namespace TYPO3\CMS\Backend\Form; interface FormDataProviderInterface { public function addData(array $result): array; }');
}

require $root . '/Classes/Backend/Form/FormDataProvider/MosaicGalleryLegacyListTypeVisibilityProvider.php';
$visibilityProvider = new Anatolkin\MosaicGallery\Backend\Form\FormDataProvider\MosaicGalleryLegacyListTypeVisibilityProvider();

$baseItems = [
    ['label' => 'Other plugin', 'value' => 'other_pi1'],
    ['label' => 'Legacy A', 'value' => 'mosaicgallery_pi1'],
    ['label' => 'Legacy B', 'value' => 'anatolkinmosaicgallery_pi1'],
];

// E. new record processed items hide both legacy values
$newResult = $visibilityProvider->addData([
    'tableName' => 'tt_content',
    'command' => 'new',
    'databaseRow' => ['CType' => 'list', 'list_type' => ''],
    'processedTca' => [
        'columns' => [
            'list_type' => [
                'config' => [
                    'items' => $baseItems,
                ],
            ],
        ],
    ],
]);
$newValues = itemValues($newResult['processedTca']['columns']['list_type']['config']['items']);
if (in_array('mosaicgallery_pi1', $newValues, true) || in_array('anatolkinmosaicgallery_pi1', $newValues, true)) {
    $failures[] = 'E: New records must hide both legacy Mosaic Gallery list_type values';
}
if (!in_array('other_pi1', $newValues, true)) {
    $failures[] = 'E: Non-legacy list_type items must remain for new records';
}

// F. ordinary edit hides both legacy values
$editOther = $visibilityProvider->addData([
    'tableName' => 'tt_content',
    'command' => 'edit',
    'databaseRow' => ['CType' => 'list', 'list_type' => 'other_pi1'],
    'processedTca' => [
        'columns' => [
            'list_type' => [
                'config' => [
                    'items' => $baseItems,
                ],
            ],
        ],
    ],
]);
$editOtherValues = itemValues($editOther['processedTca']['columns']['list_type']['config']['items']);
if (in_array('mosaicgallery_pi1', $editOtherValues, true)
    || in_array('anatolkinmosaicgallery_pi1', $editOtherValues, true)
) {
    $failures[] = 'F: Ordinary edits must hide both legacy Mosaic Gallery list_type values';
}

// G. mosaicgallery_pi1 edit exposes only mosaicgallery_pi1
$editA = $visibilityProvider->addData([
    'tableName' => 'tt_content',
    'command' => 'edit',
    'databaseRow' => ['CType' => 'list', 'list_type' => 'mosaicgallery_pi1'],
    'processedTca' => [
        'columns' => [
            'list_type' => [
                'config' => [
                    'items' => $baseItems,
                ],
            ],
        ],
    ],
]);
$editAValues = itemValues($editA['processedTca']['columns']['list_type']['config']['items']);
if (!in_array('mosaicgallery_pi1', $editAValues, true)) {
    $failures[] = 'G: Editing mosaicgallery_pi1 must expose that legacy value';
}
if (in_array('anatolkinmosaicgallery_pi1', $editAValues, true)) {
    $failures[] = 'G: Editing mosaicgallery_pi1 must not expose anatolkinmosaicgallery_pi1';
}
$editALabel = '';
foreach ($editA['processedTca']['columns']['list_type']['config']['items'] as $item) {
    if (($item['value'] ?? '') === 'mosaicgallery_pi1') {
        $editALabel = (string)($item['label'] ?? '');
        break;
    }
}
if (!str_contains($editALabel, 'plugin.legacyCompatibility')) {
    $failures[] = 'G: Kept legacy item must use the legacy compatibility label';
}

// H. anatolkinmosaicgallery_pi1 edit exposes only anatolkinmosaicgallery_pi1
$editB = $visibilityProvider->addData([
    'tableName' => 'tt_content',
    'command' => 'edit',
    'databaseRow' => ['CType' => 'list', 'list_type' => ['anatolkinmosaicgallery_pi1']],
    'processedTca' => [
        'columns' => [
            'list_type' => [
                'config' => [
                    'items' => $baseItems,
                ],
            ],
        ],
    ],
]);
$editBValues = itemValues($editB['processedTca']['columns']['list_type']['config']['items']);
if (!in_array('anatolkinmosaicgallery_pi1', $editBValues, true)) {
    $failures[] = 'H: Editing anatolkinmosaicgallery_pi1 must expose that legacy value';
}
if (in_array('mosaicgallery_pi1', $editBValues, true)) {
    $failures[] = 'H: Editing anatolkinmosaicgallery_pi1 must not expose mosaicgallery_pi1';
}

if (!str_contains($language, 'plugin.legacyCompatibility')) {
    $failures[] = 'Backend language must define plugin.legacyCompatibility';
}
if (!str_contains($readme, 'not** offered when creating new Plugin')
    && !str_contains($readme, 'not offered when creating new Plugin')) {
    $failures[] = 'README must explain that legacy list_type signatures are hidden from creation UI';
}
if (!str_contains($extLocalconf, 'tt_content.list.20.mosaicgallery_pi1 < tt_content.mosaicgallery_pi1.20')
    || !str_contains($extLocalconf, 'tt_content.list.20.anatolkinmosaicgallery_pi1 < tt_content.mosaicgallery_pi1.20')) {
    $failures[] = 'Frontend legacy TypoScript aliases must remain';
}

if ($failures === []) {
    fwrite(STDOUT, "Legacy list_type creation-UI checks passed.\n");
    exit(0);
}

fwrite(STDERR, "Legacy list_type creation-UI checks failed:\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
