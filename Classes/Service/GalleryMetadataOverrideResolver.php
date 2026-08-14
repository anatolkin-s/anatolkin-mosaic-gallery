<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

final class GalleryMetadataOverrideResolver
{
    /**
     * @return array<string, array{caption?: array{mode: string, value: string}, alt?: array{mode: string, value: string}}>
     */
    public function decode(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($document)
            || ($document['schemaVersion'] ?? null) !== 1
            || !is_array($document['files'] ?? null)
        ) {
            return [];
        }

        $overrides = [];
        foreach ($document['files'] as $fileUid => $entry) {
            if ((!is_string($fileUid) && !is_int($fileUid)) || !is_array($entry)) {
                continue;
            }

            $normalizedEntry = [];
            $caption = $this->normalizeProperty($entry['caption'] ?? null, ['inherit', 'custom']);
            if ($caption !== null) {
                $normalizedEntry['caption'] = $caption;
            }
            $alt = $this->normalizeProperty($entry['alt'] ?? null, ['inherit', 'custom', 'empty']);
            if ($alt !== null) {
                $normalizedEntry['alt'] = $alt;
            }
            if ($normalizedEntry !== []) {
                $overrides[(string)$fileUid] = $normalizedEntry;
            }
        }

        return $overrides;
    }

    /** @param list<string> $allowedModes @return array{mode: string, value: string}|null */
    private function normalizeProperty(mixed $property, array $allowedModes): ?array
    {
        if (!is_array($property)
            || !in_array($property['mode'] ?? null, $allowedModes, true)
            || !is_string($property['value'] ?? null)
        ) {
            return null;
        }

        return [
            'mode' => $property['mode'],
            'value' => $property['value'],
        ];
    }
}
