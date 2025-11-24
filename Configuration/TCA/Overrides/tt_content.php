<?php
declare(strict_types=1);

defined('TYPO3') || die();

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {
    // Extension key of this plugin
    $extensionKey = 'mosaic_gallery';

    // Plugin name as defined in registerPlugin()
    $pluginName = 'Pi1';

    // Plugin signature used in TCA for list_type, e.g. anatolkinmosaicgallery_pi1
    $pluginSignature = str_replace('_', '', $extensionKey) . '_' . strtolower($pluginName);

    // Ensure "items" array for list_type exists to avoid RuntimeException
    if (
        !isset($GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'])
        || !is_array($GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'])
    ) {
        $GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'] = [];
    }

    // Register "Anatolkin Mosaic Gallery" in the list of plugins (CType = list, field list_type)
    $GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'][] = [
        'Anatolkin Mosaic Gallery', // Label shown in the BE content type selector
        $pluginSignature,           // list_type value, e.g. anatolkinmosaicgallery_pi1
        'mosaic-gallery-plugin',    // iconIdentifier (defined in ext_localconf.php)
    ];

    // Enable FlexForm configuration for this plugin
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature]
        = 'pi_flexform';

    // Absolute path to FlexForm XML from the project root
    $flexFormPath = Environment::getProjectPath()
        . '/packages/mosaic_gallery/Configuration/FlexForms/MosaicGallery.xml';

    ExtensionManagementUtility::addPiFlexFormValue(
        $pluginSignature,
        'FILE:' . $flexFormPath
    );
})();

