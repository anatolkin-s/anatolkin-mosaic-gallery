<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Backend\Form\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProviderInterface;

/**
 * TYPO3 13 FormEngine-only filter for Mosaic Gallery legacy list_type items.
 *
 * Static TCA keeps both legacy signatures valid for DataHandler. This provider
 * removes them from the request-specific processed item list except when editing
 * an existing record that already uses one of those signatures.
 */
final class MosaicGalleryLegacyListTypeVisibilityProvider implements FormDataProviderInterface
{
    /** @var list<string> */
    private const LEGACY_LIST_TYPES = [
        'mosaicgallery_pi1',
        'anatolkinmosaicgallery_pi1',
    ];

    private const LANGUAGE_FILE =
        'LLL:EXT:anatolkin_mosaic_gallery/Resources/Private/Language/locallang_be.xlf:';

    public function addData(array $result): array
    {
        if (($result['tableName'] ?? '') !== 'tt_content') {
            return $result;
        }

        $items = $result['processedTca']['columns']['list_type']['config']['items'] ?? null;
        if (!is_array($items)) {
            return $result;
        }

        $keepLegacyValue = $this->resolveLegacyValueToKeep($result);
        $filtered = [];
        $kept = false;

        foreach ($items as $item) {
            if (!is_array($item)) {
                $filtered[] = $item;
                continue;
            }

            $value = $this->itemValue($item);
            if (!in_array($value, self::LEGACY_LIST_TYPES, true)) {
                $filtered[] = $item;
                continue;
            }

            if ($keepLegacyValue === null || $value !== $keepLegacyValue) {
                continue;
            }

            $filtered[] = $this->withLegacyCompatibilityLabel($item);
            $kept = true;
        }

        if ($keepLegacyValue !== null && !$kept) {
            $filtered[] = [
                'label' => self::LANGUAGE_FILE . 'plugin.legacyCompatibility',
                'value' => $keepLegacyValue,
                'icon' => 'mosaic-gallery-plugin',
            ];
        }

        $result['processedTca']['columns']['list_type']['config']['items'] = array_values($filtered);

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function resolveLegacyValueToKeep(array $result): ?string
    {
        if (($result['command'] ?? '') === 'new') {
            return null;
        }

        $row = $result['databaseRow'] ?? [];
        if (!is_array($row)) {
            return null;
        }

        $cType = $this->scalarField($row['CType'] ?? '');
        if ($cType !== 'list') {
            return null;
        }

        $listType = $this->scalarField($row['list_type'] ?? '');
        if (!in_array($listType, self::LEGACY_LIST_TYPES, true)) {
            return null;
        }

        return $listType;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function itemValue(array $item): string
    {
        return trim((string)($item['value'] ?? $item[1] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function withLegacyCompatibilityLabel(array $item): array
    {
        if (array_key_exists('label', $item)) {
            $item['label'] = self::LANGUAGE_FILE . 'plugin.legacyCompatibility';
        } else {
            $item[0] = self::LANGUAGE_FILE . 'plugin.legacyCompatibility';
        }
        $item['icon'] = $item['icon'] ?? 'mosaic-gallery-plugin';

        return $item;
    }

    private function scalarField(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return trim((string)$value);
    }
}
