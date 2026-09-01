<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Backend\Form\DisplayCondition;

use Anatolkin\MosaicGallery\Service\GalleryFlexFormSourceReader;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ManualImageSourceCondition
{
    /**
     * @param array{record?: array<string, mixed>} $parameters
     */
    public function isManualImageSource(array $parameters): bool
    {
        $record = $parameters['record'] ?? [];
        $flexForm = $record['pi_flexform'] ?? '';

        return GeneralUtility::makeInstance(GalleryFlexFormSourceReader::class)
            ->readSource($flexForm) === GalleryFlexFormSourceReader::SOURCE_MANUAL;
    }
}
