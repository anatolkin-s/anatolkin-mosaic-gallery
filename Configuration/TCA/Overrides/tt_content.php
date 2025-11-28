<?php
declare(strict_types=1);

defined('TYPO3') || die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {
    // Extension key (для путей EXT:anatolkin_mosaic_gallery/...)
    $extensionKey = 'anatolkin_mosaic_gallery';

    // ВНУТРЕННЯЯ сигнатура плагина (Extbase + TypoScript)
    // ВАЖНО: должна совпадать с тем, что даёт configurePlugin('MosaicGallery','Pi1')
    // => mosaicgallery_pi1
    $pluginSignature = 'mosaicgallery_pi1';

    // Гарантируем, что items для list_type — массив
    if (
        !isset($GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'])
        || !is_array($GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'])
    ) {
        $GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'] = [];
    }

    // Регистрация нашего плагина в списке "Insert plugin"
    $GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'][] = [
        'Anatolkin Mosaic Gallery',   // Лейбл в BE
        $pluginSignature,             // list_type = mosaicgallery_pi1
        'mosaic-gallery-plugin',      // iconIdentifier (см. ext_localconf.php)
    ];

    // Включаем FlexForm для этого плагина
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature]
        = 'pi_flexform';

    // Подключаем FlexForm, уже с правильным EXT:anatolkin_mosaic_gallery/...
    ExtensionManagementUtility::addPiFlexFormValue(
        $pluginSignature,
        'FILE:EXT:' . $extensionKey . '/Configuration/FlexForms/MosaicGallery.xml'
    );
})();
