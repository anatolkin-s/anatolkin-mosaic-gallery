<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

/**
 * Pure resolution for Manual-source gallery metadata inheritance.
 *
 * Caption inherits TYPO3 FileReference description (never title).
 * Alt inherits TYPO3 FileReference alternative, with explicit Mosaic empty/custom modes.
 */
final class ManualGalleryMetadataResolver
{
    /**
     * @param array<string, mixed> $fileOverride
     */
    public static function resolveCaption(string $referenceDescription, array $fileOverride): string
    {
        if (($fileOverride['caption']['mode'] ?? null) === 'custom') {
            return (string)($fileOverride['caption']['value'] ?? '');
        }

        return $referenceDescription;
    }

    /**
     * @param array<string, mixed> $fileOverride
     */
    public static function resolveAlt(string $referenceAlternative, array $fileOverride): string
    {
        $mode = $fileOverride['alt']['mode'] ?? null;
        if ($mode === 'custom') {
            return (string)($fileOverride['alt']['value'] ?? '');
        }
        if ($mode === 'empty') {
            return '';
        }

        return $referenceAlternative;
    }
}
