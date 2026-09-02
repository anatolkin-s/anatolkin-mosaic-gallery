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
$extLocalconf = @file_get_contents($root . '/ext_localconf.php');
$services = @file_get_contents($root . '/Configuration/Services.yaml');
$ttContent = @file_get_contents($root . '/Configuration/TCA/Overrides/tt_content.php');
$language = @file_get_contents($root . '/Resources/Private/Language/locallang_be.xlf');

if ($extLocalconf === false || $services === false || $ttContent === false || $language === false) {
    fwrite(STDERR, "FLEXFORM_PERMISSION_CONTRACT: FAIL\n");
    fwrite(STDERR, "Unable to read required repository files\n");
    exit(1);
}

if (!is_file($flexFormPath)) {
    $failures[] = 'Missing FlexForm file: Configuration/FlexForms/MosaicGallery.xml';
} else {
    $dom = new DOMDocument();
    if (!@$dom->load($flexFormPath)) {
        $failures[] = 'FlexForm XML parse failed';
    } else {
        $xpath = new DOMXPath($dom);
        $excludeNodes = $xpath->query('//exclude');
        if ($excludeNodes !== false && $excludeNodes->length > 0) {
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

if (!str_contains($extLocalconf, 'customPermOptions')) {
    $failures[] = 'ext_localconf.php must register customPermOptions';
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

$categories = MosaicGalleryFlexFormPermissionDefinition::customPermOptionCategories();
$optionCount = 0;
foreach ($categories as $category) {
    $optionCount += count($category['items']);
}

if ($optionCount !== count(MosaicGalleryFlexFormPermissionDefinition::fieldMap())) {
    $failures[] = 'customPermOptions item count must match FlexForm field map';
}

if ($failures !== []) {
    fwrite(STDERR, "FLEXFORM_PERMISSION_CONTRACT: FAIL\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "FLEXFORM_PERMISSION_CONTRACT: PASS\n");
fwrite(STDOUT, 'SHEETS=' . ($sheetCount ?? 0) . "\n");
fwrite(STDOUT, 'FLEXFORM_FIELDS=' . ($fieldCount ?? 0) . "\n");
fwrite(STDOUT, 'PERMISSION_MAPPED_FIELDS=' . ($mappedCount ?? 0) . "\n");
fwrite(STDOUT, "UNMAPPED_FIELDS=0\n");
fwrite(STDOUT, "UNKNOWN_PERMISSION_FIELDS=0\n");
fwrite(STDOUT, 'CUSTOM_PERM_CATEGORIES=' . count($categories) . "\n");
fwrite(STDOUT, 'CUSTOM_PERM_OPTIONS=' . $optionCount . "\n");
exit(0);
