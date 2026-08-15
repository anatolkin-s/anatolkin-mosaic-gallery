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
        return $this->decodeDocument($json)['files'];
    }

    /**
     * @return array{
     *     legacyCaptionsConverted: bool,
     *     files: array<string, array{caption?: array{mode: string, value: string}, alt?: array{mode: string, value: string}}>
     * }
     */
    public function decodeDocument(string $json): array
    {
        if (trim($json) === '') {
            return $this->emptyDocument();
        }

        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->emptyDocument();
        }

        if (!is_array($document)
            || ($document['schemaVersion'] ?? null) !== 1
            || !is_array($document['files'] ?? null)
        ) {
            return $this->emptyDocument();
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

        return [
            'legacyCaptionsConverted' => ($document['legacyCaptionsConverted'] ?? false) === true,
            'files' => $overrides,
        ];
    }

    /** @return array{legacyCaptionsConverted: false, files: array{}} */
    private function emptyDocument(): array
    {
        return [
            'legacyCaptionsConverted' => false,
            'files' => [],
        ];
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
