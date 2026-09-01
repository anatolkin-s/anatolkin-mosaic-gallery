<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

/**
 * Pure resolution for inherited gallery caption/alt semantics.
 *
 * Caption Inherit uses Title only (never Description).
 * Alt Inherit uses Alternative only (never synthesized from Caption/Title/Description).
 */
final class GalleryInheritedMetadataResolver
{
    /**
     * @param array<string, mixed> $fileOverride
     */
    public static function resolveCaption(string $inheritedTitle, array $fileOverride): string
    {
        if (($fileOverride['caption']['mode'] ?? null) === 'custom') {
            return (string)($fileOverride['caption']['value'] ?? '');
        }

        return $inheritedTitle;
    }

    /**
     * @param array<string, mixed> $fileOverride
     */
    public static function resolveAlt(string $inheritedAlternative, array $fileOverride): string
    {
        $mode = $fileOverride['alt']['mode'] ?? null;
        if ($mode === 'custom') {
            return (string)($fileOverride['alt']['value'] ?? '');
        }
        if ($mode === 'empty') {
            return '';
        }

        return $inheritedAlternative;
    }
}
