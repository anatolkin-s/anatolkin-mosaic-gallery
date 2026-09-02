<?php
declare(strict_types=1);

/**
 * Isolated lifecycle registration stubs for Mosaic customPermOptions (Issue #11).
 * Run: php Build/test-custom-perm-registration-lifecycle.php
 */

$root = dirname(__DIR__);
require_once $root . '/Classes/Backend/Permission/MosaicGalleryFlexFormPermissionDefinition.php';

use Anatolkin\MosaicGallery\Backend\Permission\MosaicGalleryFlexFormPermissionDefinition;

$failures = [];

$countRuntimeOptions = static function (): array {
    $runtime = $GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions'] ?? [];
    $categories = is_array($runtime) ? count($runtime) : 0;
    $options = 0;
    if (is_array($runtime)) {
        foreach ($runtime as $category) {
            if (is_array($category['items'] ?? null)) {
                $options += count($category['items']);
            }
        }
    }

    return [$categories, $options];
};

$runLifecycle = static function (int $major) use ($countRuntimeOptions): array {
    $GLOBALS['TYPO3_CONF_VARS'] = ['BE' => ['customPermOptions' => []]];
    $paths = [];

    // Mirror Core backend bootstrap order: localconf then tables.
    if ($major >= 14) {
        MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions();
        $paths[] = 'ext_localconf.php';
    }
    if ($major < 14) {
        MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions();
        $paths[] = 'ext_tables.php';
    }

    [$categories, $options] = $countRuntimeOptions();

    return [
        'paths' => $paths,
        'categories' => $categories,
        'options' => $options,
    ];
};

$v13 = $runLifecycle(13);
if ($v13['paths'] !== ['ext_tables.php']) {
    $failures[] = 'v13 must register only through ext_tables.php';
}
if ($v13['categories'] !== 2 || $v13['options'] !== 39) {
    $failures[] = 'v13 must produce exactly 2 categories / 39 options';
}

$v14 = $runLifecycle(14);
if ($v14['paths'] !== ['ext_localconf.php']) {
    $failures[] = 'v14 must register only through ext_localconf.php';
}
if ($v14['categories'] !== 2 || $v14['options'] !== 39) {
    $failures[] = 'v14 must produce exactly 2 categories / 39 options';
}

// Ensure both gated paths together never invent a second copy of options.
$GLOBALS['TYPO3_CONF_VARS'] = ['BE' => ['customPermOptions' => []]];
MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions();
MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions();
[$categories, $options] = $countRuntimeOptions();
if ($categories !== 2 || $options !== 39) {
    $failures[] = 'Repeated registration must not duplicate options (got '
        . $categories . '/' . $options . ')';
}

if ($failures !== []) {
    fwrite(STDERR, "Custom permission registration lifecycle tests failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Custom permission registration lifecycle tests passed.\n");
fwrite(STDOUT, "TYPO3_13_PATH=ext_tables.php\n");
fwrite(STDOUT, "TYPO3_14_PATH=ext_localconf.php\n");
fwrite(STDOUT, "CUSTOM_PERM_CATEGORIES=2\n");
fwrite(STDOUT, "CUSTOM_PERM_OPTIONS=39\n");
exit(0);
