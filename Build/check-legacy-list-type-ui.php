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
$pageTsStub = readFileOrFail($root . '/Configuration/PageTS/Mod/Wizards/NewContentElement.tsconfig', $failures);
$pageTsConfigPath = $root . '/Configuration/page.tsconfig';
$pageTsConfig = readFileOrFail($pageTsConfigPath, $failures);
$readme = readFileOrFail($root . '/README.md', $failures);
$language = readFileOrFail($root . '/Resources/Private/Language/locallang_be.xlf', $failures);

// 1–3. Global page.tsconfig hides legacy list_type from wizard/selector without addPageTSConfig()
if (!is_file($pageTsConfigPath)) {
    $failures[] = '1: Configuration/page.tsconfig must exist';
}
if (!str_contains($pageTsConfig, 'TCEFORM.tt_content.list_type')
    || !str_contains($pageTsConfig, 'removeItems')
    || !str_contains($pageTsConfig, 'mosaicgallery_pi1')
    || !str_contains($pageTsConfig, 'anatolkinmosaicgallery_pi1')
) {
    $failures[] = '2: page.tsconfig must remove both legacy list_type values via TCEFORM';
}
if (str_contains($extLocalconf, 'addPageTSConfig')) {
    $failures[] = '3: ext_localconf.php must not call addPageTSConfig()';
}

// Inactive stub must not reintroduce a manual wizard card
if (str_contains($pageTsStub, 'CType = list') || str_contains($pageTsStub, 'list_type = mosaicgallery_pi1')) {
    $failures[] = 'Stale New Content Element PageTS must not offer legacy list_type creation';
}
if (!str_contains($pageTsStub, 'Intentionally inactive')) {
    $failures[] = 'NewContentElement.tsconfig must remain an explicit inactive stub';
}

// 4. TYPO3 13 static TCA still contains both legacy values
if (!preg_match(
    "/foreach\s*\(\s*\[\s*'mosaicgallery_pi1'\s*,\s*'anatolkinmosaicgallery_pi1'\s*\]\s+as\s+\\\$legacyListType\s*\)/",
    $ttContent,
) || !preg_match("/list_type']\['config']\['items'\]\[\]\s*=\s*\[[\s\S]*?'value'\s*=>\s*\\\$legacyListType/", $ttContent)
) {
    $failures[] = '4: TYPO3 13 static TCA must still append both legacy list_type values';
}
if (!str_contains($ttContent, 'subtypes_addlist')
    || !str_contains($ttContent, 'addPiFlexFormValue($legacyListType')) {
    $failures[] = '4: Legacy subtypes_addlist / addPiFlexFormValue wiring must remain';
}

// no global tt_content.list_type itemsProcFunc
if (str_contains($ttContent, 'itemsProcFunc')
    || str_contains($ttContent, 'MosaicGalleryLegacyListTypeItems')
    || is_file($root . '/Classes/Backend/Form/MosaicGalleryLegacyListTypeItems.php')
) {
    $failures[] = 'Extension must not install a global list_type itemsProcFunc';
}

// provider registration (TYPO3 13 only) + fallback re-add when item missing
if (!str_contains($provider, 'class MosaicGalleryLegacyListTypeVisibilityProvider')) {
    $failures[] = 'MosaicGalleryLegacyListTypeVisibilityProvider class must exist';
}
if (!str_contains($provider, '!$kept')
    || !preg_match("/\\\$filtered\[\]\s*=\s*\[[\s\S]*?'value'\s*=>\s*\\\$keepLegacyValue/", $provider)
) {
    $failures[] = '5: Provider must re-add the current legacy item when missing from processed items';
}
if (!str_contains($extLocalconf, 'MosaicGalleryLegacyListTypeVisibilityProvider::class')) {
    $failures[] = 'ext_localconf.php must register the legacy visibility provider';
}
if (!str_contains($extLocalconf, 'TcaSelectItems::class')) {
    $failures[] = 'Provider registration must depend on TcaSelectItems';
}
if (!preg_match(
    '/getMajorVersion\(\)\s*<\s*14\s*\)\s*\{\s*\$GLOBALS\[\'TYPO3_CONF_VARS\'\]\[\'SYS\'\]\[\'formEngine\'\]\[\'formDataGroup\'\]\[\'tcaDatabaseRecord\'\]\[MosaicGalleryLegacyListTypeVisibilityProvider::class\]/s',
    $extLocalconf,
)) {
    $failures[] = 'Legacy visibility provider must be registered only for TYPO3 < 14';
}
if (!str_contains($services, 'MosaicGalleryLegacyListTypeVisibilityProvider')) {
    $failures[] = 'Services.yaml must register MosaicGalleryLegacyListTypeVisibilityProvider';
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

// Legacy items already removed (Page TSconfig removeItems / wizard path)
$itemsWithoutLegacy = [
    ['label' => 'Other plugin', 'value' => 'other_pi1'],
];

// Existing: provider still filters when legacy items are present in processed TCA
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
    $failures[] = 'New records must hide both legacy Mosaic Gallery list_type values';
}
if (!in_array('other_pi1', $newValues, true)) {
    $failures[] = 'Non-legacy list_type items must remain for new records';
}

$editOtherPresent = $visibilityProvider->addData([
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
$editOtherPresentValues = itemValues($editOtherPresent['processedTca']['columns']['list_type']['config']['items']);
if (in_array('mosaicgallery_pi1', $editOtherPresentValues, true)
    || in_array('anatolkinmosaicgallery_pi1', $editOtherPresentValues, true)
) {
    $failures[] = 'Ordinary edits must hide both legacy Mosaic Gallery list_type values';
}

$editAPresent = $visibilityProvider->addData([
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
$editAPresentValues = itemValues($editAPresent['processedTca']['columns']['list_type']['config']['items']);
if (!in_array('mosaicgallery_pi1', $editAPresentValues, true)
    || in_array('anatolkinmosaicgallery_pi1', $editAPresentValues, true)
) {
    $failures[] = 'Editing mosaicgallery_pi1 with present items must expose only that legacy value';
}

$editBPresent = $visibilityProvider->addData([
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
$editBPresentValues = itemValues($editBPresent['processedTca']['columns']['list_type']['config']['items']);
if (!in_array('anatolkinmosaicgallery_pi1', $editBPresentValues, true)
    || in_array('mosaicgallery_pi1', $editBPresentValues, true)
) {
    $failures[] = 'Editing anatolkinmosaicgallery_pi1 with present items must expose only that legacy value';
}

// A. NEW record where incoming processed items already had both legacy values removed
$newAbsent = $visibilityProvider->addData([
    'tableName' => 'tt_content',
    'command' => 'new',
    'databaseRow' => ['CType' => 'list', 'list_type' => ''],
    'processedTca' => [
        'columns' => [
            'list_type' => [
                'config' => [
                    'items' => $itemsWithoutLegacy,
                ],
            ],
        ],
    ],
]);
$newAbsentValues = itemValues($newAbsent['processedTca']['columns']['list_type']['config']['items']);
if (in_array('mosaicgallery_pi1', $newAbsentValues, true)
    || in_array('anatolkinmosaicgallery_pi1', $newAbsentValues, true)
) {
    $failures[] = 'A: New records must leave both legacy values absent when already removed';
}
if (!in_array('other_pi1', $newAbsentValues, true)) {
    $failures[] = 'A: Non-legacy list_type items must remain when legacy values were already removed';
}

// B. ordinary EDIT with legacy already removed
$editOtherAbsent = $visibilityProvider->addData([
    'tableName' => 'tt_content',
    'command' => 'edit',
    'databaseRow' => ['CType' => 'list', 'list_type' => 'other_pi1'],
    'processedTca' => [
        'columns' => [
            'list_type' => [
                'config' => [
                    'items' => $itemsWithoutLegacy,
                ],
            ],
        ],
    ],
]);
$editOtherAbsentValues = itemValues($editOtherAbsent['processedTca']['columns']['list_type']['config']['items']);
if (in_array('mosaicgallery_pi1', $editOtherAbsentValues, true)
    || in_array('anatolkinmosaicgallery_pi1', $editOtherAbsentValues, true)
) {
    $failures[] = 'B: Ordinary edits must leave both legacy values absent when already removed';
}

// C. EDIT mosaicgallery_pi1 with NO legacy entries incoming — provider appends only that value
$editAAbsent = $visibilityProvider->addData([
    'tableName' => 'tt_content',
    'command' => 'edit',
    'databaseRow' => ['CType' => 'list', 'list_type' => 'mosaicgallery_pi1'],
    'processedTca' => [
        'columns' => [
            'list_type' => [
                'config' => [
                    'items' => $itemsWithoutLegacy,
                ],
            ],
        ],
    ],
]);
$editAAbsentValues = itemValues($editAAbsent['processedTca']['columns']['list_type']['config']['items']);
if (!in_array('mosaicgallery_pi1', $editAAbsentValues, true)) {
    $failures[] = 'C: Editing mosaicgallery_pi1 must append that legacy value when missing';
}
if (in_array('anatolkinmosaicgallery_pi1', $editAAbsentValues, true)) {
    $failures[] = 'C: Editing mosaicgallery_pi1 must not append anatolkinmosaicgallery_pi1';
}
$editAAbsentLabel = '';
foreach ($editAAbsent['processedTca']['columns']['list_type']['config']['items'] as $item) {
    if (($item['value'] ?? '') === 'mosaicgallery_pi1') {
        $editAAbsentLabel = (string)($item['label'] ?? '');
        break;
    }
}
if (!str_contains($editAAbsentLabel, 'plugin.legacyCompatibility')) {
    $failures[] = 'E: Appended legacy item must use the legacy compatibility label';
}

// D. EDIT anatolkinmosaicgallery_pi1 with NO legacy entries incoming
$editBAbsent = $visibilityProvider->addData([
    'tableName' => 'tt_content',
    'command' => 'edit',
    'databaseRow' => ['CType' => 'list', 'list_type' => 'anatolkinmosaicgallery_pi1'],
    'processedTca' => [
        'columns' => [
            'list_type' => [
                'config' => [
                    'items' => $itemsWithoutLegacy,
                ],
            ],
        ],
    ],
]);
$editBAbsentValues = itemValues($editBAbsent['processedTca']['columns']['list_type']['config']['items']);
if (!in_array('anatolkinmosaicgallery_pi1', $editBAbsentValues, true)) {
    $failures[] = 'D: Editing anatolkinmosaicgallery_pi1 must append that legacy value when missing';
}
if (in_array('mosaicgallery_pi1', $editBAbsentValues, true)) {
    $failures[] = 'D: Editing anatolkinmosaicgallery_pi1 must not append mosaicgallery_pi1';
}
$editBAbsentLabel = '';
foreach ($editBAbsent['processedTca']['columns']['list_type']['config']['items'] as $item) {
    if (($item['value'] ?? '') === 'anatolkinmosaicgallery_pi1') {
        $editBAbsentLabel = (string)($item['label'] ?? '');
        break;
    }
}
if (!str_contains($editBAbsentLabel, 'plugin.legacyCompatibility')) {
    $failures[] = 'E: Appended anatolkinmosaicgallery_pi1 item must use the legacy compatibility label';
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
