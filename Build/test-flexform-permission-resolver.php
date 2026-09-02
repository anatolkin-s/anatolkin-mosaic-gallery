<?php
declare(strict_types=1);

/**
 * Unit tests for Mosaic Gallery FlexForm permission resolver (Issue #11).
 * Run: php Build/test-flexform-permission-resolver.php
 */

$root = dirname(__DIR__);
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

$failures = [];
$resolver = new MosaicGalleryFlexFormPermissionResolver();
$allFields = array_keys(MosaicGalleryFlexFormPermissionDefinition::fieldMap());

// Default unrestricted
$hidden = $resolver->resolveHiddenFields(null, static fn(string $identifier): bool => false);
if ($hidden !== []) {
    $failures[] = 'No restrictions must leave all fields visible';
}

// Single restriction
$designPresetId = MosaicGalleryFlexFormPermissionDefinition::permissionIdentifier(
    MosaicGalleryFlexFormPermissionDefinition::CATEGORY_DESIGN,
    'hide_design_preset',
);
$hidden = $resolver->resolveHiddenFields(null, static fn(string $identifier): bool => $identifier === $designPresetId);
if ($hidden !== ['settings.designPreset']) {
    $failures[] = 'Single restriction must hide only the mapped field';
}

// Multiple restrictions union
$gapId = MosaicGalleryFlexFormPermissionDefinition::permissionIdentifier(
    MosaicGalleryFlexFormPermissionDefinition::CATEGORY_GENERAL,
    'hide_gap',
);
$sourceId = MosaicGalleryFlexFormPermissionDefinition::permissionIdentifier(
    MosaicGalleryFlexFormPermissionDefinition::CATEGORY_GENERAL,
    'hide_source',
);
$hidden = $resolver->resolveHiddenFields(null, static fn(string $identifier): bool => in_array($identifier, [$gapId, $sourceId], true));
sort($hidden);
$expected = ['settings.gap', 'settings.source'];
sort($expected);
if ($hidden !== $expected) {
    $failures[] = 'Multiple restrictions must hide the union of mapped fields';
}

// Admin bypass
$adminUser = new class extends \TYPO3\CMS\Core\Authentication\BackendUserAuthentication {
    public function isAdmin(): bool
    {
        return true;
    }

    public function check(string $type, string $identifier): bool
    {
        return true;
    }
};
$hidden = $resolver->resolveHiddenFields($adminUser);
if ($hidden !== []) {
    $failures[] = 'Administrators must never receive hidden FlexForm fields';
}

// Mosaic record detection
$mosaicCType = $resolver->isMosaicGalleryRecord([
    'databaseRow' => ['CType' => 'mosaicgallery_pi1'],
]);
if (!$mosaicCType) {
    $failures[] = 'Resolver must recognize canonical Mosaic Gallery CType';
}
$legacyList = $resolver->isMosaicGalleryRecord([
    'databaseRow' => ['CType' => 'list', 'list_type' => 'mosaicgallery_pi1'],
]);
if (!$legacyList) {
    $failures[] = 'Resolver must recognize legacy Mosaic Gallery list_type';
}
$otherPlugin = $resolver->isMosaicGalleryRecord([
    'databaseRow' => ['CType' => 'list', 'list_type' => 'other_pi1'],
]);
if ($otherPlugin) {
    $failures[] = 'Resolver must ignore unrelated list plugins';
}

// Hidden-value preservation architecture: provider removes DS nodes only
$gapPermissionId = MosaicGalleryFlexFormPermissionDefinition::permissionIdentifier(
    MosaicGalleryFlexFormPermissionDefinition::CATEGORY_GENERAL,
    'hide_gap',
);
$backendUser = new class ($gapPermissionId) extends \TYPO3\CMS\Core\Authentication\BackendUserAuthentication {
    public function __construct(private readonly string $restrictedIdentifier)
    {
    }

    public function isAdmin(): bool
    {
        return false;
    }

    public function check(string $type, string $identifier): bool
    {
        return $identifier === $this->restrictedIdentifier;
    }
};
$GLOBALS['BE_USER'] = $backendUser;
$provider = new Anatolkin\MosaicGallery\Backend\Form\FormDataProvider\MosaicGalleryFlexFormPermissionProvider($resolver);
$sampleDs = [
    'sheets' => [
        'sDEF' => [
            'ROOT' => [
                'el' => [
                    'settings.gap' => ['config' => ['type' => 'input']],
                    'settings.source' => ['config' => ['type' => 'select']],
                ],
            ],
        ],
        'sDESIGN' => [
            'ROOT' => [
                'el' => [
                    'settings.designPreset' => ['config' => ['type' => 'select']],
                ],
            ],
        ],
    ],
];
$result = $provider->addData([
    'tableName' => 'tt_content',
    'databaseRow' => ['CType' => 'mosaicgallery_pi1'],
    'processedTca' => [
        'columns' => [
            'pi_flexform' => [
                'config' => [
                    'ds' => $sampleDs,
                ],
            ],
        ],
    ],
]);
$processedDs = $result['processedTca']['columns']['pi_flexform']['config']['ds'];
if (isset($processedDs['sheets']['sDEF']['ROOT']['el']['settings.gap'])) {
    $failures[] = 'Provider must remove restricted fields from processed DS';
}
if (!isset($processedDs['sheets']['sDEF']['ROOT']['el']['settings.source'])) {
    $failures[] = 'Provider must leave unrestricted fields in processed DS';
}

// Unknown permission keys fail closed in checker, not runtime damage
foreach (MosaicGalleryFlexFormPermissionDefinition::fieldMap() as $mapping) {
    if (!MosaicGalleryFlexFormPermissionDefinition::isValidPermissionKey($mapping['key'])) {
        $failures[] = 'Permission map contains invalid key: ' . $mapping['key'];
    }
}

if ($failures !== []) {
    fwrite(STDERR, "FlexForm permission resolver tests failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "FlexForm permission resolver tests passed.\n");
exit(0);
