<?php
declare(strict_types=1);

defined('TYPO3') || die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

(static function (): void {
    $extensionName = 'MosaicGallery';
    $pluginName = 'Pi1';
    $pluginTitle = 'LLL:EXT:anatolkin_mosaic_gallery/Resources/Private/Language/locallang_be.xlf:plugin.title';
    $pluginDescription = 'LLL:EXT:anatolkin_mosaic_gallery/Resources/Private/Language/locallang_be.xlf:plugin.description';
    $pluginGroup = 'gallery';
    $pluginGroupLabel = 'LLL:EXT:anatolkin_mosaic_gallery/Resources/Private/Language/locallang_be.xlf:plugin.group';
    $flexForm = 'FILE:EXT:anatolkin_mosaic_gallery/Configuration/FlexForms/MosaicGallery.xml';
    $metadataOverridesField = 'tx_anatolkinmosaicgallery_metadata_overrides';
    $typo3Version = new Typo3Version();

    ExtensionManagementUtility::addTCAcolumns('tt_content', [
        $metadataOverridesField => [
            'label' => 'LLL:EXT:anatolkin_mosaic_gallery/Resources/Private/Language/locallang_be.xlf:metadata.title',
            'config' => [
                'type' => 'user',
                'renderType' => 'mosaicGalleryMetadataOverrides',
            ],
        ],
    ]);

    ExtensionManagementUtility::addTcaSelectItemGroup(
        'tt_content',
        'CType',
        $pluginGroup,
        $pluginGroupLabel,
        'after:plugins',
    );

    $pluginArguments = [
        $extensionName,
        $pluginName,
        $pluginTitle,
        'mosaic-gallery-plugin',
        $pluginGroup,
        $pluginDescription,
    ];
    if ($typo3Version->getMajorVersion() >= 14) {
        $pluginArguments[] = $flexForm;
    }

    $pluginSignature = ExtensionUtility::registerPlugin(...$pluginArguments);

    if ($typo3Version->getMajorVersion() >= 14) {
        ExtensionManagementUtility::addToAllTCAtypes(
            'tt_content',
            $metadataOverridesField,
            $pluginSignature,
            'after:pi_flexform',
        );
    } else {
        ExtensionManagementUtility::addToAllTCAtypes(
            'tt_content',
            '--div--;' . $pluginTitle . ',pi_flexform,' . $metadataOverridesField,
            $pluginSignature,
            'after:palette:headers',
        );
        ExtensionManagementUtility::addPiFlexFormValue('*', $flexForm, $pluginSignature);
    }

    if ($typo3Version->getMajorVersion() < 14) {
        if (
            !isset($GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'])
            || !is_array($GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'])
        ) {
            $GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'] = [];
        }

        // Keep both legacy signatures in static TCA so DataHandler continues to
        // accept persisted values. FormEngine UI visibility is filtered request-
        // specifically by MosaicGalleryLegacyListTypeVisibilityProvider.
        foreach (['mosaicgallery_pi1', 'anatolkinmosaicgallery_pi1'] as $legacyListType) {
            $GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'][] = [
                'label' => 'LLL:EXT:anatolkin_mosaic_gallery/Resources/Private/Language/locallang_be.xlf:plugin.legacyCompatibility',
                'value' => $legacyListType,
                'icon' => 'mosaic-gallery-plugin',
            ];
            $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$legacyListType]
                = 'pi_flexform,' . $metadataOverridesField;
            ExtensionManagementUtility::addPiFlexFormValue($legacyListType, $flexForm);
        }
    }
})();
