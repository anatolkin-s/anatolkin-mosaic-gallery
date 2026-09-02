<?php
declare(strict_types=1);

defined('TYPO3') || die();

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Anatolkin\MosaicGallery\Backend\Form\Element\DesignConfiguratorElement;
use Anatolkin\MosaicGallery\Backend\Form\Element\MetadataOverridesElement;
use Anatolkin\MosaicGallery\Backend\Form\FormDataProvider\MosaicGalleryFlexFormDefaultsProvider;
use Anatolkin\MosaicGallery\Backend\Form\FormDataProvider\MosaicGalleryFlexFormPermissionProvider;
use Anatolkin\MosaicGallery\Backend\Form\FormDataProvider\MosaicGalleryLegacyListTypeVisibilityProvider;
use Anatolkin\MosaicGallery\Backend\Permission\MosaicGalleryFlexFormPermissionDefinition;
use Anatolkin\MosaicGallery\Controller\GalleryController;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaFlexPrepare;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaFlexProcess;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaSelectItems;

// Configure Extbase plugin
ExtensionUtility::configurePlugin(
    'MosaicGallery',
    'Pi1',
    [
        GalleryController::class => 'list',
    ],
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);

// TYPO3 13 only: keep unmigrated list_type records renderable before the wizard runs.
// Register in defaultContentRendering so the copy happens after Extbase
// plugin configuration defines tt_content.mosaicgallery_pi1, including Site Set sites.
if ((new Typo3Version())->getMajorVersion() < 14) {
    ExtensionManagementUtility::addTypoScript(
        'anatolkin_mosaic_gallery',
        'setup',
        '
tt_content.list.20.mosaicgallery_pi1 < tt_content.mosaicgallery_pi1.20
tt_content.list.20.anatolkinmosaicgallery_pi1 < tt_content.mosaicgallery_pi1.20
        ',
        'defaultContentRendering'
    );
}

// Register the per-file metadata FormEngine element.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1755129600] = [
    'nodeName' => 'mosaicGalleryMetadataOverrides',
    'priority' => 40,
    'class' => MetadataOverridesElement::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1755129601] = [
    'nodeName' => 'mosaicGalleryDesignConfigurator',
    'priority' => 40,
    'class' => DesignConfiguratorElement::class,
];

// Site TypoScript creation defaults for new Mosaic Gallery records (Issue #2).
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][MosaicGalleryFlexFormDefaultsProvider::class] = [
    'depends' => [
        TcaFlexPrepare::class,
    ],
];
$tcaFlexProcessDepends = &$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][TcaFlexProcess::class]['depends'];
if (!is_array($tcaFlexProcessDepends)) {
    $tcaFlexProcessDepends = [TcaFlexPrepare::class];
}
if (!in_array(MosaicGalleryFlexFormDefaultsProvider::class, $tcaFlexProcessDepends, true)) {
    $tcaFlexProcessDepends[] = MosaicGalleryFlexFormDefaultsProvider::class;
}
if (!in_array(MosaicGalleryFlexFormPermissionProvider::class, $tcaFlexProcessDepends, true)) {
    $tcaFlexProcessDepends[] = MosaicGalleryFlexFormPermissionProvider::class;
}
unset($tcaFlexProcessDepends);

// Issue #11: opt-in deny restrictions via native backend user-group custom permissions.
foreach (MosaicGalleryFlexFormPermissionDefinition::customPermOptionCategories() as $categoryKey => $category) {
    $GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions'][$categoryKey] = $category;
}
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][MosaicGalleryFlexFormPermissionProvider::class] = [
    'depends' => [
        MosaicGalleryFlexFormDefaultsProvider::class,
    ],
];

// TYPO3 13 only: hide legacy Mosaic Gallery list_type choices from FormEngine UI
// while keeping static TCA items valid for DataHandler on existing records.
if ((new Typo3Version())->getMajorVersion() < 14) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][MosaicGalleryLegacyListTypeVisibilityProvider::class] = [
        'depends' => [
            TcaSelectItems::class,
        ],
    ];
}
