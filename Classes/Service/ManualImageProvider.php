<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;

final class ManualImageProvider
{
    public const FIELD_NAME = 'tx_anatolkinmosaicgallery_images';

    public function __construct(
        private readonly FileRepository $fileRepository,
    ) {
    }

    /**
     * @return list<FileReference>
     */
    public function getFileReferences(int $contentUid): array
    {
        if ($contentUid <= 0) {
            return [];
        }

        $references = $this->fileRepository->findByRelation(
            'tt_content',
            self::FIELD_NAME,
            $contentUid,
        );

        return array_values(array_filter(
            $references,
            static fn($reference) => $reference instanceof FileReference,
        ));
    }
}
