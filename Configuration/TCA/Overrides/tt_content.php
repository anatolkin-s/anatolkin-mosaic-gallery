<?php
declare(strict_types=1);
defined('TYPO3') || die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function () {
    $extKey = 'mosaic_gallery';
    $pluginSignature = 'mosaicgallery_pi1';

    // Use our icon in the plugins list
    ExtensionManagementUtility::addPlugin(
        ['Mosaic Gallery', $pluginSignature, 'mosaic-gallery-plugin'],
        'list_type',
        $extKey
    );

    // FlexForm hookup
    ExtensionManagementUtility::addPiFlexFormValue(
        $pluginSignature,
        'FILE:EXT:' . $extKey . '/Configuration/FlexForms/MosaicGallery.xml'
    );

    // Row icon in tt_content for this list_type
    $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['list-' . $pluginSignature] = 'mosaic-gallery-plugin';
})();
