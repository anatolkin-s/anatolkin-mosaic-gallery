<?php
declare(strict_types=1);
defined('TYPO3') || die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function () {
    $extKey = 'mosaic_gallery';
    $pluginSignature = str_replace('_', '', $extKey) . '_pi1'; // mosaicgallery_pi1

    ExtensionManagementUtility::addPlugin(
        ['Mosaic Gallery', $pluginSignature, 'content-image'],
        'list_type',
        $extKey
    );

    ExtensionManagementUtility::addPiFlexFormValue(
        $pluginSignature,
        'FILE:EXT:' . $extKey . '/Configuration/FlexForms/MosaicGallery.xml'
    );

    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature] = 'pi_flexform';
})();
