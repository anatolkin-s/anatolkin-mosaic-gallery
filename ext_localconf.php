<?php
declare(strict_types=1);

defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Anatolkin\MosaicGallery\Backend\Form\Element\MetadataOverridesElement;
use Anatolkin\MosaicGallery\Controller\GalleryController;

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

// Register the per-file metadata FormEngine element.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1755129600] = [
    'nodeName' => 'mosaicGalleryMetadataOverrides',
    'priority' => 40,
    'class' => MetadataOverridesElement::class,
];
