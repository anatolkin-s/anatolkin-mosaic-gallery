<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Upgrades;

if (class_exists(\TYPO3\CMS\Core\Upgrades\AbstractListTypeToCTypeUpdate::class)) {
    abstract class AbstractMosaicGalleryListTypeToCTypeUpdate
        extends \TYPO3\CMS\Core\Upgrades\AbstractListTypeToCTypeUpdate
    {
    }
} elseif (class_exists(\TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate::class)) {
    abstract class AbstractMosaicGalleryListTypeToCTypeUpdate
        extends \TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate
    {
    }
} else {
    abstract class AbstractMosaicGalleryListTypeToCTypeUpdate
    {
    }
}
