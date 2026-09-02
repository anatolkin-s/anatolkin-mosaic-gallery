<?php
declare(strict_types=1);

defined('TYPO3') || die();

use Anatolkin\MosaicGallery\Backend\Permission\MosaicGalleryFlexFormPermissionDefinition;
use TYPO3\CMS\Core\Information\Typo3Version;

// TYPO3 13.4 documents customPermOptions registration in ext_tables.php.
// TYPO3 14.3 documents the same registration in ext_localconf.php.
if ((new Typo3Version())->getMajorVersion() < 14) {
    MosaicGalleryFlexFormPermissionDefinition::registerCustomPermOptions();
}
