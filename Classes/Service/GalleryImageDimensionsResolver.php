<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Extbase\Service\ImageService;

final class GalleryImageDimensionsResolver
{
    public function __construct(private readonly ImageService $imageService)
    {
    }

    /**
     * Use the same crop/scale instructions as f:uri.image(maxWidth: ...).
     * Read the derivative itself so Core's rounding and no-upscale behavior apply.
     * @return array{previewWidth: int, previewHeight: int}|array{}
     */
    public function resolvePreviewDimensions(File $file, int $maxWidth, ?FileReference $fileReference = null): array
    {
        try {
            $image = $fileReference ?? $file;
            $crop = $image->hasProperty('crop') ? (string)($image->getProperty('crop') ?? '') : '';
            $cropArea = CropVariantCollection::create($crop)->getCropArea('default');
            $processed = $this->imageService->applyProcessingInstructions($image, [
                'width' => null,
                'height' => null,
                'minWidth' => null,
                'minHeight' => null,
                'maxWidth' => $maxWidth,
                'maxHeight' => null,
                'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
            ]);
            $width = (int)$processed->getProperty('width');
            $height = (int)$processed->getProperty('height');
            return $width > 0 && $height > 0 ? ['previewWidth' => $width, 'previewHeight' => $height] : [];
        } catch (\Throwable) {
            // Missing metadata or failed processing must not invent intrinsic geometry.
            return [];
        }
    }

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
