<?php
declare(strict_types=1);

defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Anatolkin\MosaicGallery\Controller\GalleryController;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// Configure Extbase plugin
ExtensionUtility::configurePlugin(
    'MosaicGallery',
    'Pi1',
    [
        GalleryController::class => 'list',
    ],
    []
);

// Register backend icons (Extension Manager, plugin icon, etc.)
(static function (): void {
    /** @var IconRegistry $registry */
    $registry = GeneralUtility::makeInstance(IconRegistry::class);

    // Icon for the extension (Extension Manager, etc.)
    $registry->registerIcon(
        'mosaic-gallery-extension',
        SvgIconProvider::class,
        ['source' => 'EXT:mosaic_gallery/Resources/Public/Icons/Extension.svg']
    );

    // Icon for the content element / plugin
    $registry->registerIcon(
        'mosaic-gallery-plugin',
        SvgIconProvider::class,
        ['source' => 'EXT:mosaic_gallery/Resources/Public/Icons/PluginMosaic.svg']
    );
})();

// Register Page TSconfig for the "New Content Element" wizard
ExtensionManagementUtility::addPageTSConfig(
    "@import 'EXT:mosaic_gallery/Configuration/TsConfig/Page/NewContentElementWizard.typoscript'"
);
