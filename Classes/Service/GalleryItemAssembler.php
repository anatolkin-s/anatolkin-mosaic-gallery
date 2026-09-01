<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;

final class GalleryItemAssembler
{
    public function __construct(
        private readonly GalleryImageDimensionsResolver $dimensionsResolver,
    ) {
    }

    /**
     * @param list<File> $files
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $metadataDocument
     * @return list<array<string, mixed>>
     */
    public function assembleFromFiles(
        array $files,
        array $settings,
        array $metadataDocument,
        string $layoutMode,
        bool $enableLoadMore,
        int $itemsPerPage,
    ): array {
        $metadataOverrides = is_array($metadataDocument['files'] ?? null) ? $metadataDocument['files'] : [];
        $legacyCaptionsConverted = ($metadataDocument['legacyCaptionsConverted'] ?? false) === true;
        $legacyLines = $this->splitLines((string)($settings['captions'] ?? ''));
        $useFalCaptions = (bool)($settings['useFalCaptions'] ?? true);

        $items = [];
        foreach ($files as $idx => $file) {
            if (!$file instanceof File) {
                continue;
            }

            $items[] = $this->assembleItem(
                $file,
                null,
                $idx,
                $metadataOverrides,
                $legacyCaptionsConverted,
                $legacyLines,
                $useFalCaptions,
                $layoutMode,
                $enableLoadMore,
                $itemsPerPage,
            );
        }

        return $items;
    }

    /**
     * @param list<FileReference> $fileReferences
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $metadataDocument
     * @return list<array<string, mixed>>
     */
    public function assembleFromFileReferences(
        array $fileReferences,
        array $settings,
        array $metadataDocument,
        string $layoutMode,
        bool $enableLoadMore,
        int $itemsPerPage,
    ): array {
        $metadataOverrides = is_array($metadataDocument['files'] ?? null) ? $metadataDocument['files'] : [];
        $useFalCaptions = (bool)($settings['useFalCaptions'] ?? true);

        $items = [];
        foreach ($fileReferences as $idx => $fileReference) {
            if (!$fileReference instanceof FileReference) {
                continue;
            }

            try {
                $file = $fileReference->getOriginalFile();
            } catch (\Throwable) {
                continue;
            }

            if (!$file instanceof File) {
                continue;
            }

            $items[] = $this->assembleItem(
                $file,
                $fileReference,
                $idx,
                $metadataOverrides,
                true,
                [],
                $useFalCaptions,
                $layoutMode,
                $enableLoadMore,
                $itemsPerPage,
            );
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $metadataOverrides
     * @param list<string> $legacyLines
     * @return array<string, mixed>
     */
    private function assembleItem(
        File $file,
        ?FileReference $fileReference,
        int $idx,
        array $metadataOverrides,
        bool $legacyCaptionsConverted,
        array $legacyLines,
        bool $useFalCaptions,
        string $layoutMode,
        bool $enableLoadMore,
        int $itemsPerPage,
    ): array {
        $aspectRatio = $this->dimensionsResolver->resolveAspectRatio($file, $fileReference);

        if ($fileReference !== null) {
            return $this->assembleManualReferenceItem(
                $file,
                $fileReference,
                $idx,
                $metadataOverrides[(string)$file->getUid()] ?? [],
                $aspectRatio,
                $layoutMode,
                $enableLoadMore,
                $itemsPerPage,
            );
        }

        try {
            $meta = $useFalCaptions ? $file->getMetaData()->get() : [];
        } catch (\Throwable) {
            $meta = [];
        }

        $metadata = [
            'title' => (string)($meta['title'] ?? ''),
            'caption' => (string)($meta['caption'] ?? ''),
            'description' => (string)($meta['description'] ?? ''),
            'alternative' => (string)($meta['alternative'] ?? ''),
            'copyright' => (string)($meta['copyright'] ?? ''),
        ];

        $caption = $useFalCaptions
            ? ($metadata['caption'] !== ''
                ? $metadata['caption']
                : ($metadata['title'] !== '' ? $metadata['title'] : $metadata['description']))
            : ($legacyCaptionsConverted ? '' : ($legacyLines[$idx] ?? ''));

        $alt = $metadata['alternative'] ?: $caption;

        $fileOverride = $metadataOverrides[(string)$file->getUid()] ?? [];
        if (($fileOverride['caption']['mode'] ?? null) === 'custom') {
            $caption = $fileOverride['caption']['value'];
        }
        if (($fileOverride['alt']['mode'] ?? null) === 'custom') {
            $alt = $fileOverride['alt']['value'];
        } elseif (($fileOverride['alt']['mode'] ?? null) === 'empty') {
            $alt = '';
        }

        return [
            'file' => $file,
            'renderFile' => $file,
            'fileReference' => null,
            'metadata' => $metadata,
            'caption' => (string)$caption,
            'alt' => (string)$alt,
            'hidden' => ($enableLoadMore && $idx >= $itemsPerPage),
            'layoutSpan' => $this->dimensionsResolver->resolveLayoutSpan($file, $layoutMode, null),
            'aspectRatio' => $aspectRatio,
            'patternWeight' => $this->resolvePatternWeight($idx, $layoutMode),
        ];
    }

    /**
     * @param array<string, mixed> $fileOverride
     * @return array<string, mixed>
     */
    private function assembleManualReferenceItem(
        File $file,
        FileReference $fileReference,
        int $idx,
        array $fileOverride,
        float $aspectRatio,
        string $layoutMode,
        bool $enableLoadMore,
        int $itemsPerPage,
    ): array {
        try {
            $fileMeta = $file->getMetaData()->get();
        } catch (\Throwable) {
            $fileMeta = [];
        }

        $metadata = [
            'title' => (string)$fileReference->getProperty('title'),
            'caption' => (string)($fileMeta['caption'] ?? ''),
            'description' => (string)$fileReference->getProperty('description'),
            'alternative' => (string)$fileReference->getProperty('alternative'),
            'copyright' => (string)($fileMeta['copyright'] ?? ''),
        ];

        $caption = ManualGalleryMetadataResolver::resolveCaption(
            (string)$fileReference->getProperty('description'),
            $fileOverride,
        );
        $alt = ManualGalleryMetadataResolver::resolveAlt(
            (string)$fileReference->getProperty('alternative'),
            $fileOverride,
        );

        return [
            'file' => $file,
            'renderFile' => $fileReference,
            'fileReference' => $fileReference,
            'metadata' => $metadata,
            'caption' => (string)$caption,
            'alt' => (string)$alt,
            'hidden' => ($enableLoadMore && $idx >= $itemsPerPage),
            'layoutSpan' => $this->dimensionsResolver->resolveLayoutSpan($file, $layoutMode, $fileReference),
            'aspectRatio' => $aspectRatio,
            'patternWeight' => $this->resolvePatternWeight($idx, $layoutMode),
        ];
    }

    private function resolvePatternWeight(int $index, string $layoutMode): string
    {
        if ($layoutMode !== 'patterned') {
            return 'medium';
        }

        $weights = ['medium', 'small', 'medium', 'large', 'small', 'medium', 'small', 'large', 'medium', 'small'];

        return $weights[$index % \count($weights)];
    }

    /** @return list<string> */
    private function splitLines(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);

        return array_values(array_filter($lines, static fn($value) => $value !== null));
    }
}
