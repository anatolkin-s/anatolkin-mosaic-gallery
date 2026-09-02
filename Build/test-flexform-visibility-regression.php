<?php
declare(strict_types=1);

/**
 * FlexForm visibility regression (exclude gate + Mosaic permission semantics).
 *
 * Covers:
 * A. non-admin + no Mosaic custom restrictions => all mapped fields present
 * B. non-admin + one checked Mosaic restriction => only that field absent
 * C. admin => all fields present regardless of custom restrictions
 * D. TYPO3 14 dedicated CType works without list_type / Selected Plugin
 * E. TYPO3 13 dedicated CType FlexForm without legacy selector dependency
 * F. TYPO3 13 existing legacy list_type remains editable with current list_type
 *
 * Also proves a50222d-style <exclude>true</exclude> allow-list gate would hide
 * all FlexForm fields for non-admins without Allowed Excludefields grants.
 *
 * Run: php Build/test-flexform-visibility-regression.php
 */

$root = dirname(__DIR__);
$failures = [];

require_once $root . '/Classes/Backend/Permission/MosaicGalleryFlexFormPermissionDefinition.php';
require_once $root . '/Classes/Backend/Permission/MosaicGalleryFlexFormPermissionResolver.php';

use Anatolkin\MosaicGallery\Backend\Permission\MosaicGalleryFlexFormPermissionDefinition;
use Anatolkin\MosaicGallery\Backend\Permission\MosaicGalleryFlexFormPermissionResolver;

if (!class_exists(\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class)) {
    eval('namespace TYPO3\CMS\Core\Authentication; class BackendUserAuthentication { public function isAdmin(): bool { return false; } public function check(string $type, string $identifier): bool { return false; } }');
}
if (!interface_exists(\TYPO3\CMS\Backend\Form\FormDataProviderInterface::class)) {
    eval('namespace TYPO3\CMS\Backend\Form; interface FormDataProviderInterface { public function addData(array $result): array; }');
}

require_once $root . '/Classes/Backend/Form/FormDataProvider/MosaicGalleryFlexFormPermissionProvider.php';
require_once $root . '/Classes/Backend/Form/FormDataProvider/MosaicGalleryLegacyListTypeVisibilityProvider.php';

$flexFormPath = $root . '/Configuration/FlexForms/MosaicGallery.xml';
$ttContentPath = $root . '/Configuration/TCA/Overrides/tt_content.php';
$ttContent = (string)file_get_contents($ttContentPath);

$dom = new DOMDocument();
if (!@$dom->load($flexFormPath)) {
    fwrite(STDERR, "FLEXFORM_VISIBILITY_REGRESSION: FAIL\nUnable to parse MosaicGallery.xml\n");
    exit(1);
}
$xpath = new DOMXPath($dom);

$discoveredFields = [];
$excludeTrueCount = 0;
foreach ($xpath->query('//el/*') as $fieldNode) {
    if (!$fieldNode instanceof DOMElement) {
        continue;
    }
    $hasConfig = false;
    $isExcluded = false;
    foreach ($fieldNode->childNodes as $child) {
        if (!$child instanceof DOMElement) {
            continue;
        }
        if ($child->nodeName === 'config') {
            $hasConfig = true;
        }
        if ($child->nodeName === 'exclude' && strtolower(trim($child->textContent)) === 'true') {
            $isExcluded = true;
        }
    }
    if (!$hasConfig) {
        continue;
    }
    $discoveredFields[] = $fieldNode->nodeName;
    if ($isExcluded) {
        $excludeTrueCount++;
    }
}
$discoveredFields = array_values(array_unique($discoveredFields));
sort($discoveredFields);

$mappedFields = array_keys(MosaicGalleryFlexFormPermissionDefinition::fieldMap());
sort($mappedFields);

if ($excludeTrueCount !== 0) {
    $failures[] = 'EXCLUDE_TRUE must be 0 (found ' . $excludeTrueCount . ')';
}
if ($discoveredFields !== $mappedFields) {
    $failures[] = 'Discovered FlexForm fields must match permission map exactly';
}

/**
 * Reconstruct TYPO3 FormEngine exclude allow-list gate.
 *
 * @param list<string> $allFields
 * @param array<string, bool> $excludeFlags
 * @param list<string> $allowed
 * @return list<string>
 */
$filterByExcludeGate = static function (
    array $allFields,
    array $excludeFlags,
    bool $isAdmin,
    array $allowed,
): array {
    $visible = [];
    foreach ($allFields as $field) {
        if (empty($excludeFlags[$field])) {
            $visible[] = $field;
            continue;
        }
        if ($isAdmin || in_array($field, $allowed, true)) {
            $visible[] = $field;
        }
    }

    return $visible;
};

$allExcluded = array_fill_keys($discoveredFields, true);
$a50222dNonAdmin = $filterByExcludeGate($discoveredFields, $allExcluded, false, []);
$a50222dAdmin = $filterByExcludeGate($discoveredFields, $allExcluded, true, []);
$currentNonAdmin = $filterByExcludeGate($discoveredFields, [], false, []);

if (count($a50222dNonAdmin) !== 0) {
    $failures[] = 'Proof failed: a50222d-style exclude=true must hide all fields for non-admin without grants';
}
if (count($a50222dAdmin) !== count($discoveredFields)) {
    $failures[] = 'Proof failed: admin must still see excluded fields';
}
if (count($currentNonAdmin) !== count($discoveredFields)) {
    $failures[] = 'Proof failed: current XML (no exclude) must keep all fields visible for non-admin';
}

/** @return array<string, mixed> */
$buildSampleDs = static function (): array {
    $ds = ['sheets' => []];
    foreach (MosaicGalleryFlexFormPermissionDefinition::fieldMap() as $fieldName => $mapping) {
        $sheet = $mapping['sheet'];
        if (!isset($ds['sheets'][$sheet])) {
            $ds['sheets'][$sheet] = ['ROOT' => ['el' => []]];
        }
        $ds['sheets'][$sheet]['ROOT']['el'][$fieldName] = ['config' => ['type' => 'input']];
    }

    return $ds;
};

/** @param array<string, mixed> $ds @return list<string> */
$presentFields = static function (array $ds): array {
    $present = [];
    foreach ($ds['sheets'] ?? [] as $sheet) {
        foreach (array_keys($sheet['ROOT']['el'] ?? []) as $fieldName) {
            $present[] = (string)$fieldName;
        }
    }
    sort($present);

    return $present;
};

$resolver = new MosaicGalleryFlexFormPermissionResolver();
$provider = new Anatolkin\MosaicGallery\Backend\Form\FormDataProvider\MosaicGalleryFlexFormPermissionProvider($resolver);

$runProvider = static function (
    Anatolkin\MosaicGallery\Backend\Form\FormDataProvider\MosaicGalleryFlexFormPermissionProvider $provider,
    array $ds,
    object $backendUser,
    array $databaseRow,
): array {
    $GLOBALS['BE_USER'] = $backendUser;

    return $provider->addData([
        'tableName' => 'tt_content',
        'databaseRow' => $databaseRow,
        'processedTca' => [
            'columns' => [
                'pi_flexform' => [
                    'config' => [
                        'ds' => $ds,
                    ],
                ],
            ],
        ],
    ]);
};

// A: non-admin + no Mosaic custom restrictions => all mapped fields present
$nonAdminUnrestricted = new class extends \TYPO3\CMS\Core\Authentication\BackendUserAuthentication {
    public function isAdmin(): bool
    {
        return false;
    }

    public function check(string $type, string $identifier): bool
    {
        return false;
    }
};
$resultA = $runProvider($provider, $buildSampleDs(), $nonAdminUnrestricted, ['CType' => 'mosaicgallery_pi1']);
$presentA = $presentFields($resultA['processedTca']['columns']['pi_flexform']['config']['ds']);
if ($presentA !== $mappedFields) {
    $failures[] = 'A: unrestricted non-admin must retain all mapped FlexForm fields';
}

// B: non-admin + one checked Mosaic restriction => only that mapped field absent
$designPresetId = MosaicGalleryFlexFormPermissionDefinition::permissionIdentifier(
    MosaicGalleryFlexFormPermissionDefinition::CATEGORY_DESIGN,
    'hide_design_preset',
);
$nonAdminOneRestriction = new class ($designPresetId) extends \TYPO3\CMS\Core\Authentication\BackendUserAuthentication {
    public function __construct(private readonly string $restrictedIdentifier)
    {
    }

    public function isAdmin(): bool
    {
        return false;
    }

    public function check(string $type, string $identifier): bool
    {
        return $type === 'custom_options' && $identifier === $this->restrictedIdentifier;
    }
};
$resultB = $runProvider($provider, $buildSampleDs(), $nonAdminOneRestriction, ['CType' => 'mosaicgallery_pi1']);
$presentB = $presentFields($resultB['processedTca']['columns']['pi_flexform']['config']['ds']);
$expectedB = array_values(array_diff($mappedFields, ['settings.designPreset']));
sort($expectedB);
if ($presentB !== $expectedB) {
    $failures[] = 'B: one Mosaic restriction must hide only the mapped field';
}
if (in_array('settings.designPreset', $presentB, true)) {
    $failures[] = 'B: restricted field settings.designPreset must be absent';
}

// C: admin => all fields present regardless of custom restrictions
$adminRestricted = new class ($designPresetId) extends \TYPO3\CMS\Core\Authentication\BackendUserAuthentication {
    public function __construct(private readonly string $restrictedIdentifier)
    {
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function check(string $type, string $identifier): bool
    {
        return true;
    }
};
$resultC = $runProvider($provider, $buildSampleDs(), $adminRestricted, ['CType' => 'mosaicgallery_pi1']);
$presentC = $presentFields($resultC['processedTca']['columns']['pi_flexform']['config']['ds']);
if ($presentC !== $mappedFields) {
    $failures[] = 'C: admin must retain all mapped FlexForm fields even when custom restrictions are checked';
}

// D: TYPO3 14 dedicated CType works without list_type / Selected Plugin
if (!preg_match(
    '/getMajorVersion\(\)\s*>=\s*14\s*\)\s*\{\s*\$pluginArguments\[\]\s*=\s*\$flexForm;/s',
    $ttContent,
)) {
    $failures[] = 'D: TYPO3 14 must register FlexForm via registerPlugin argument, not list_type';
}
if (!preg_match(
    '/getMajorVersion\(\)\s*<\s*14\s*\)\s*\{[\s\S]*list_type[\s\S]*addPiFlexFormValue\(\$legacyListType/s',
    $ttContent,
)) {
    $failures[] = 'D/F: legacy list_type FlexForm wiring must remain behind TYPO3 < 14 guard';
}
$typo314Block = '';
if (preg_match(
    '/if\s*\(\s*\$typo3Version->getMajorVersion\(\)\s*>=\s*14\s*\)\s*\{(.*?)\}\s*else\s*\{/s',
    $ttContent,
    $m,
)) {
    $typo314Block = $m[1];
}
if ($typo314Block === '') {
    $failures[] = 'D: unable to isolate TYPO3 >= 14 TCA registration block';
} elseif (str_contains($typo314Block, 'list_type') || str_contains($typo314Block, 'addPiFlexFormValue')) {
    $failures[] = 'D: TYPO3 14 dedicated CType path must not depend on list_type / Selected Plugin';
}

// E: TYPO3 13 dedicated CType FlexForm without legacy selector dependency
if (!preg_match(
    "/addPiFlexFormValue\('\*',\s*\\\$flexForm,\s*\\\$pluginSignature\)/",
    $ttContent,
)) {
    $failures[] = 'E: TYPO3 13 dedicated CType must attach FlexForm via addPiFlexFormValue(*, signature)';
}
if (!preg_match(
    "/'--div--;'\s*\.\s*\\\$pluginTitle\s*\.\s*',pi_flexform,'/",
    $ttContent,
)) {
    $failures[] = 'E: TYPO3 13 dedicated CType showitem must include pi_flexform without list_type selector';
}

// F: TYPO3 13 existing legacy list_type remains editable with current list_type available
$legacyProvider = new Anatolkin\MosaicGallery\Backend\Form\FormDataProvider\MosaicGalleryLegacyListTypeVisibilityProvider();
$legacyEdit = $legacyProvider->addData([
    'tableName' => 'tt_content',
    'command' => 'edit',
    'databaseRow' => ['CType' => 'list', 'list_type' => 'mosaicgallery_pi1'],
    'processedTca' => [
        'columns' => [
            'list_type' => [
                'config' => [
                    'items' => [
                        ['label' => 'Other', 'value' => 'other_pi1'],
                        ['label' => 'Legacy A', 'value' => 'mosaicgallery_pi1'],
                        ['label' => 'Legacy B', 'value' => 'anatolkinmosaicgallery_pi1'],
                    ],
                ],
            ],
        ],
    ],
]);
$legacyValues = [];
foreach ($legacyEdit['processedTca']['columns']['list_type']['config']['items'] as $item) {
    if (is_array($item)) {
        $legacyValues[] = (string)($item['value'] ?? '');
    }
}
if (!in_array('mosaicgallery_pi1', $legacyValues, true)) {
    $failures[] = 'F: editing legacy mosaicgallery_pi1 must keep current list_type available';
}
if (in_array('anatolkinmosaicgallery_pi1', $legacyValues, true)) {
    $failures[] = 'F: editing mosaicgallery_pi1 must not also expose the other legacy signature';
}

$legacyAlt = $legacyProvider->addData([
    'tableName' => 'tt_content',
    'command' => 'edit',
    'databaseRow' => ['CType' => 'list', 'list_type' => 'anatolkinmosaicgallery_pi1'],
    'processedTca' => [
        'columns' => [
            'list_type' => [
                'config' => [
                    'items' => [
                        ['label' => 'Other', 'value' => 'other_pi1'],
                    ],
                ],
            ],
        ],
    ],
]);
$legacyAltValues = [];
foreach ($legacyAlt['processedTca']['columns']['list_type']['config']['items'] as $item) {
    if (is_array($item)) {
        $legacyAltValues[] = (string)($item['value'] ?? '');
    }
}
if (!in_array('anatolkinmosaicgallery_pi1', $legacyAltValues, true)) {
    $failures[] = 'F: editing anatolkinmosaicgallery_pi1 must re-add current list_type when missing';
}

// Dedicated CType permission provider must recognize mosaic records without list_type
if (!$resolver->isMosaicGalleryRecord(['databaseRow' => ['CType' => 'mosaicgallery_pi1']])) {
    $failures[] = 'E: resolver must recognize dedicated CType without list_type';
}
if (!$resolver->isMosaicGalleryRecord([
    'databaseRow' => ['CType' => 'list', 'list_type' => 'mosaicgallery_pi1'],
])) {
    $failures[] = 'F: resolver must recognize legacy list_type mosaicgallery_pi1';
}
if (!$resolver->isMosaicGalleryRecord([
    'databaseRow' => ['CType' => 'list', 'list_type' => 'anatolkinmosaicgallery_pi1'],
])) {
    $failures[] = 'F: resolver must recognize legacy list_type anatolkinmosaicgallery_pi1';
}

// Manual images / metadata are outside FlexForm exclude gate (separate TCA columns)
if (!str_contains($ttContent, 'tx_anatolkinmosaicgallery_metadata_overrides')
    || !str_contains($ttContent, 'ManualImageProvider::FIELD_NAME')
) {
    $failures[] = 'Manual Images / Image metadata TCA fields must remain outside FlexForm exclude gate';
}

if ($failures !== []) {
    fwrite(STDERR, "FLEXFORM_VISIBILITY_REGRESSION: FAIL\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "FLEXFORM_VISIBILITY_REGRESSION: PASS\n");
fwrite(STDOUT, 'EXCLUDE_TRUE=' . $excludeTrueCount . "\n");
fwrite(STDOUT, 'FLEXFORM_FIELDS=' . count($discoveredFields) . "\n");
fwrite(STDOUT, 'A50222D_NONADMIN_VISIBLE=' . count($a50222dNonAdmin) . "\n");
fwrite(STDOUT, 'CURRENT_NONADMIN_VISIBLE=' . count($currentNonAdmin) . "\n");
fwrite(STDOUT, "A=PASS\n");
fwrite(STDOUT, "B=PASS\n");
fwrite(STDOUT, "C=PASS\n");
fwrite(STDOUT, "D=PASS\n");
fwrite(STDOUT, "E=PASS\n");
fwrite(STDOUT, "F=PASS\n");
fwrite(STDOUT, "ROOT_CAUSE_HISTORICAL=a50222d_exclude_true_allowlist_gate\n");
fwrite(STDOUT, "ROOT_CAUSE_LIVE=admin_bypass_missing_for_globals_be_user\n");
fwrite(STDOUT, "CURRENT_XML_EXCLUDES_REMOVED=YES\n");
fwrite(STDOUT, "ADMIN_BYPASS_VIA_GLOBALS=PASS\n");
exit(0);
