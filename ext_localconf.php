<?php
declare(strict_types=1);
defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Anatolkin\MosaicGallery\Controller\GalleryController;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;

ExtensionUtility::configurePlugin(
  'MosaicGallery',
  'Pi1',
  [GalleryController::class => 'list'],
  []
);

// Register BE icons
(static function () {
    /** @var IconRegistry $registry */
    $registry = GeneralUtility::makeInstance(IconRegistry::class);

    // Icon for extension (Extension Manager, etc.)
    $registry->registerIcon(
        'mosaic-gallery-extension',
        SvgIconProvider::class,
        ['source' => 'EXT:mosaic_gallery/Resources/Public/Icons/Extension.svg']
    );

    // Icon for content element / plugin
    $registry->registerIcon(
        'mosaic-gallery-plugin',
        SvgIconProvider::class,
        ['source' => 'EXT:mosaic_gallery/Resources/Public/Icons/PluginMosaic.svg']
    );
})();
