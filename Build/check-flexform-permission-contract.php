<?php
declare(strict_types=1);

/**
 * Contract check for Mosaic Gallery FlexForm permission metadata (Issue #11).
 * Run: php Build/check-flexform-permission-contract.php
 */

$root = dirname(__DIR__);
$failures = [];

require_once $root . '/Classes/Backend/Permission/MosaicGalleryFlexFormPermissionDefinition.php';

use Anatolkin\MosaicGallery\Backend\Permission\MosaicGalleryFlexFormPermissionDefinition;

$flexFormPath = $root . '/Configuration/FlexForms/MosaicGallery.xml';
$extLocalconfPath = $root . '/ext_localconf.php';
$extTablesPath = $root . '/ext_tables.php';
$extLocalconf = @file_get_contents($extLocalconfPath);
$extTables = @file_get_contents($extTablesPath);
$services = @file_get_contents($root . '/Configuration/Services.yaml');
$ttContent = @file_get_contents($root . '/Configuration/TCA/Overrides/tt_content.php');
$language = @file_get_contents($root . '/Resources/Private/Language/locallang_be.xlf');

if ($extLocalconf === false || $services === false || $ttContent === false || $language === false) {
    fwrite(STDERR, "FLEXFORM_PERMISSION_CONTRACT: FAIL\n");
    fwrite(STDERR, "Unable to read required repository files\n");
    exit(1);
}

if (!is_file($extTablesPath) || $extTables === false) {
    $failures[] = 'ext_tables.php must exist for TYPO3 13 customPermOptions registration';
}

$sheetCount = 0;
$fieldCount = 0;
$mappedCount = 0;
$excludeCount = 0;

if (!is_file($flexFormPath)) {
    $failures[] = 'Missing FlexForm file: Configuration/FlexForms/MosaicGallery.xml';
} else {
    $dom = new DOMDocument();
    if (!@$dom->load($flexFormPath)) {
        $failures[] = 'FlexForm XML parse failed';
    } else {
        $xpath = new DOMXPath($dom);
        $excludeNodes = $xpath->query('//exclude');
        $excludeCount = $excludeNodes instanceof DOMNodeList ? $excludeNodes->length : 0;
        if ($excludeCount > 0) {
            $failures[] = 'FlexForm must not contain Issue #11 exclude=true entries';
        }

        $sheetNodes = $xpath->query('/T3DataStructure/sheets/*');
        $sheetCount = $sheetNodes instanceof DOMNodeList ? $sheetNodes->length : 0;
        $discoveredFields = [];

        if ($sheetNodes instanceof DOMNodeList) {
            foreach ($sheetNodes as $sheetNode) {
                if (!$sheetNode instanceof DOMElement) {
                    continue;
                }
                $elNodes = $xpath->query('.//el', $sheetNode);
                if (!$elNodes instanceof DOMNodeList) {
                    continue;
                }
                foreach ($elNodes as $elNode) {
                    if (!$elNode instanceof DOMElement) {
                        continue;
                    }
                    foreach ($elNode->childNodes as $fieldNode) {
                        if (!$fieldNode instanceof DOMElement) {
                            continue;
                        }
                        $hasConfig = false;
                        foreach ($fieldNode->childNodes as $child) {
                            if ($child instanceof DOMElement && $child->nodeName === 'config') {
                                $hasConfig = true;
                                break;
                            }
                        }
                        if ($hasConfig) {
                            $discoveredFields[] = $fieldNode->nodeName;
                        }
                    }
                }
            }
        }

        $discoveredFields = array_values(array_unique($discoveredFields));
        sort($discoveredFields);
        $mappedFields = array_keys(MosaicGalleryFlexFormPermissionDefinition::fieldMap());
        sort($mappedFields);

        $unmapped = array_values(array_diff($discoveredFields, $mappedFields));
        $unknown = array_values(array_diff($mappedFields, $discoveredFields));

        if ($unmapped !== []) {
            $failures[] = 'Unmapped FlexForm fields: ' . implode(', ', $unmapped);
        }
        if ($unknown !== []) {
            $failures[] = 'Unknown permission mappings: ' . implode(', ', $unknown);
        }

        foreach (MosaicGalleryFlexFormPermissionDefinition::fieldMap() as $fieldName => $mapping) {
            if (!MosaicGalleryFlexFormPermissionDefinition::isValidPermissionKey($mapping['key'])) {
                $failures[] = 'Invalid permission key for ' . $fieldName . ': ' . $mapping['key'];
            }
            if (str_contains($mapping['category'], ':') || str_contains($mapping['category'], ';')) {
                $failures[] = 'Invalid permission category for ' . $fieldName;
            }
        }

        $fieldCount = count($discoveredFields);
        $mappedCount = count($mappedFields);
    }
}

// Version-correct registration contract (source + lifecycle stubs).
if (!str_contains((string)$extTables, 'MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions')) {
    $failures[] = 'ext_tables.php must call MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions()';
}
if (!preg_match(
    '/getMajorVersion\(\)\s*<\s*14\s*\)\s*\{\s*MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions\(\);/s',
    (string)$extTables,
)) {
    $failures[] = 'TYPO3 13 path: ext_tables.php must register customPermOptions only when major < 14';
}
if (preg_match(
    '/getMajorVersion\(\)\s*>=\s*14\s*\)\s*\{\s*MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions\(\);/s',
    (string)$extTables,
)) {
    $failures[] = 'ext_tables.php must not register customPermOptions for TYPO3 >= 14';
}

if (!str_contains($extLocalconf, 'MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions')) {
    $failures[] = 'ext_localconf.php must call MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions()';
}
if (!preg_match(
    '/getMajorVersion\(\)\s*>=\s*14\s*\)\s*\{\s*MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions\(\);/s',
    $extLocalconf,
)) {
    $failures[] = 'TYPO3 14 path: ext_localconf.php must register customPermOptions only when major >= 14';
}
if (preg_match(
    '/getMajorVersion\(\)\s*<\s*14\s*\)\s*\{\s*MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions\(\);/s',
    $extLocalconf,
)) {
    $failures[] = 'ext_localconf.php must not register customPermOptions for TYPO3 < 14';
}

// Ensure no ungated duplicate registration loops remain.
if (preg_match(
    '/foreach\s*\(\s*MosaicGalleryFlexFormPermissionDefinition::customPermOptionCategories\(\)/',
    $extLocalconf . (string)$extTables,
)) {
    $failures[] = 'Registration must use registerCustomPermOptions(), not duplicated foreach maps';
}

if (!str_contains($extLocalconf, 'MosaicGalleryFlexFormPermissionProvider::class')) {
    $failures[] = 'ext_localconf.php must register MosaicGalleryFlexFormPermissionProvider';
}
if (!str_contains($services, 'MosaicGalleryFlexFormPermissionProvider')) {
    $failures[] = 'Services.yaml must register MosaicGalleryFlexFormPermissionProvider';
}
if (!str_contains($services, 'MosaicGalleryFlexFormPermissionResolver')) {
    $failures[] = 'Services.yaml must register MosaicGalleryFlexFormPermissionResolver';
}
if (!str_contains($ttContent, 'flexform.permissionsHelp')) {
    $failures[] = 'tt_content TCA must expose flexform.permissionsHelp on pi_flexform';
}
if (!str_contains($language, 'permissions.category.general')) {
    $failures[] = 'locallang_be.xlf must define permission category labels';
}
if (!str_contains($language, 'Custom module options')) {
    $failures[] = 'permissionsHelp must reference Custom module options UI terminology';
}
if (!str_contains($language, 'Module Permissions')) {
    $failures[] = 'permissionsHelp must reference Module Permissions UI terminology';
}

$categories = MosaicGalleryFlexFormPermissionDefinition::customPermOptionCategories();
$optionCount = 0;
foreach ($categories as $category) {
    $optionCount += count($category['items']);
}

if ($optionCount !== count(MosaicGalleryFlexFormPermissionDefinition::fieldMap())) {
    $failures[] = 'customPermOptions item count must match FlexForm field map';
}

/**
 * Isolated lifecycle stub: prove registration path selection without full TYPO3 bootstrap.
 *
 * @return array{categories: int, options: int, path: string}
 */
$simulateRegistration = static function (int $majorVersion) use ($root): array {
    $GLOBALS['TYPO3_CONF_VARS'] = ['BE' => ['customPermOptions' => []]];

    $registerFromTables = static function () use ($majorVersion): void {
        if ($majorVersion < 14) {
            MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions();
        }
    };
    $registerFromLocalconf = static function () use ($majorVersion): void {
        if ($majorVersion >= 14) {
            MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions();
        }
    };

    // Mirror Core lifecycle order for backend: localconf first, then tables.
    $registerFromLocalconf();
    $registerFromTables();

    $runtime = $GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions'] ?? [];
    $runtimeCategories = is_array($runtime) ? count($runtime) : 0;
    $runtimeOptions = 0;
    if (is_array($runtime)) {
        foreach ($runtime as $category) {
            if (is_array($category['items'] ?? null)) {
                $runtimeOptions += count($category['items']);
            }
        }
    }

    $path = $majorVersion < 14 ? 'ext_tables.php' : 'ext_localconf.php';

    return [
        'categories' => $runtimeCategories,
        'options' => $runtimeOptions,
        'path' => $path,
    ];
};

$v13 = $simulateRegistration(13);
if ($v13['path'] !== 'ext_tables.php' || $v13['categories'] !== 2 || $v13['options'] !== 39) {
    $failures[] = 'TYPO3 13 lifecycle stub must register exactly 2/39 via ext_tables.php';
}
$v14 = $simulateRegistration(14);
if ($v14['path'] !== 'ext_localconf.php' || $v14['categories'] !== 2 || $v14['options'] !== 39) {
    $failures[] = 'TYPO3 14 lifecycle stub must register exactly 2/39 via ext_localconf.php';
}

// Idempotency: double registration must not invent extra categories.
$GLOBALS['TYPO3_CONF_VARS'] = ['BE' => ['customPermOptions' => []]];
MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions();
MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions();
$idempotent = $GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions'];
$idempotentOptions = 0;
foreach ($idempotent as $category) {
    $idempotentOptions += count($category['items'] ?? []);
}
if (count($idempotent) !== 2 || $idempotentOptions !== 39) {
    $failures[] = 'registerCustomPermOptions must remain idempotent (2 categories / 39 options)';
}

if ($failures !== []) {
    fwrite(STDERR, "FLEXFORM_PERMISSION_CONTRACT: FAIL\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "FLEXFORM_PERMISSION_CONTRACT: PASS\n");
fwrite(STDOUT, 'SHEETS=' . $sheetCount . "\n");
fwrite(STDOUT, 'FLEXFORM_FIELDS=' . $fieldCount . "\n");
fwrite(STDOUT, 'PERMISSION_MAPPED_FIELDS=' . $mappedCount . "\n");
fwrite(STDOUT, "UNMAPPED_FIELDS=0\n");
fwrite(STDOUT, "UNKNOWN_PERMISSION_FIELDS=0\n");
fwrite(STDOUT, 'CUSTOM_PERM_CATEGORIES=' . count($categories) . "\n");
fwrite(STDOUT, 'CUSTOM_PERM_OPTIONS=' . $optionCount . "\n");
fwrite(STDOUT, 'EXCLUDE_TRUE=' . $excludeCount . "\n");
fwrite(STDOUT, "TYPO3_13_REGISTRATION_PATH=ext_tables.php\n");
fwrite(STDOUT, "TYPO3_14_REGISTRATION_PATH=ext_localconf.php\n");
fwrite(STDOUT, "LIFECYCLE_STUB_V13=PASS\n");
fwrite(STDOUT, "LIFECYCLE_STUB_V14=PASS\n");
exit(0);
