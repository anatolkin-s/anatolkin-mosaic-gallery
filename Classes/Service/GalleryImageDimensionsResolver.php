<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;

final class GalleryImageDimensionsResolver
{
    public function resolveAspectRatio(File $file, ?FileReference $fileReference = null): float
    {
        if ($fileReference !== null) {
            return $this->resolveCroppedAspectRatio($file, $fileReference);
        }

        return $this->resolveOriginalAspectRatio($file);
    }

    public function resolveLayoutSpan(File $file, string $layoutMode, ?FileReference $fileReference = null): string
    {
        if ($layoutMode !== 'mosaic') {
            return 'normal';
        }

        return $this->resolveAspectRatio($file, $fileReference) >= 1.6 ? 'wide' : 'normal';
    }

    private function resolveOriginalAspectRatio(File $file): float
    {
        try {
            $width = (int)$file->getProperty('width');
            $height = (int)$file->getProperty('height');

            return $width > 0 && $height > 0 ? $width / $height : 1.0;
        } catch (\Throwable) {
            return 1.0;
        }
    }

    private function resolveCroppedAspectRatio(File $file, FileReference $fileReference): float
    {
        try {
            $crop = (string)($fileReference->getProperty('crop') ?? '');
            if ($crop === '') {
                return $this->resolveOriginalAspectRatio($file);
            }

            $cropArea = CropVariantCollection::create($crop)->getDefaultCropArea();
            $originalWidth = (int)$file->getProperty('width');
            $originalHeight = (int)$file->getProperty('height');
            if ($originalWidth <= 0 || $originalHeight <= 0) {
                return 1.0;
            }

            $croppedWidth = $cropArea->makeCropBasedOnWidth($originalWidth);
            $croppedHeight = $cropArea->makeCropBasedOnHeight($originalHeight);

            return $croppedWidth > 0 && $croppedHeight > 0 ? $croppedWidth / $croppedHeight : 1.0;
        } catch (\Throwable) {
            return $this->resolveOriginalAspectRatio($file);
        }
    }
}
