<?php
declare(strict_types=1);

defined('TYPO3') || die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {

    // Register static TypoScript include for Anatolkin Mosaic Gallery
    ExtensionManagementUtility::addStaticFile(
        'mosaic_gallery',                    // Extension key
        'Configuration/TypoScript',         // Path to TypoScript directory
        'Anatolkin Mosaic Gallery (Assets & Masonry)' // Label in "Include TypoScript sets"
    );
})();
