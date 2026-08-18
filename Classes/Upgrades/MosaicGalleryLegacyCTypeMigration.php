<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Upgrades;

trait MosaicGalleryCTypeMigrationDefinition
{
    protected function getListTypeToCTypeMapping(): array
    {
        return [
            'mosaicgallery_pi1' => 'mosaicgallery_pi1',
            'anatolkinmosaicgallery_pi1' => 'mosaicgallery_pi1',
        ];
    }

    public function getTitle(): string
    {
        return 'Migrate Mosaic Gallery plugins to a dedicated content type';
    }

    public function getDescription(): string
    {
        return 'Migrates existing Mosaic Gallery plugin records from list_type to CType.';
    }
}

if (
    class_exists(\TYPO3\CMS\Core\Attribute\UpgradeWizard::class)
    && class_exists(\TYPO3\CMS\Core\Upgrades\AbstractListTypeToCTypeUpdate::class)
    && interface_exists(\TYPO3\CMS\Core\Upgrades\RepeatableInterface::class)
) {
    #[\TYPO3\CMS\Core\Attribute\UpgradeWizard('mosaicGalleryCTypeMigration')]
    final class MosaicGalleryLegacyCTypeMigration extends AbstractMosaicGalleryListTypeToCTypeUpdate implements \TYPO3\CMS\Core\Upgrades\RepeatableInterface
    {
        use MosaicGalleryCTypeMigrationDefinition;
    }
} elseif (
    class_exists(\TYPO3\CMS\Install\Attribute\UpgradeWizard::class)
    && class_exists(\TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate::class)
    && interface_exists(\TYPO3\CMS\Install\Updates\RepeatableInterface::class)
) {
    #[\TYPO3\CMS\Install\Attribute\UpgradeWizard('mosaicGalleryCTypeMigration')]
    final class MosaicGalleryLegacyCTypeMigration extends AbstractMosaicGalleryListTypeToCTypeUpdate implements \TYPO3\CMS\Install\Updates\RepeatableInterface
    {
        use MosaicGalleryCTypeMigrationDefinition;
    }
} else {
    final class MosaicGalleryLegacyCTypeMigration extends AbstractMosaicGalleryListTypeToCTypeUpdate
    {
    }
}
