<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class GalleryFlexFormSourceReader
{
    public const SOURCE_FOLDER = 'folder';
    public const SOURCE_MANUAL = 'manual';

    public function readSource(mixed $flexForm): string
    {
        $settings = $this->normalizeFlexFormSettings($flexForm);
        $source = (string)($settings['source'] ?? self::SOURCE_FOLDER);

        return $source === self::SOURCE_MANUAL ? self::SOURCE_MANUAL : self::SOURCE_FOLDER;
    }

    /** @return array<string, mixed> */
    public function readSettings(mixed $flexForm): array
    {
        $settings = $this->normalizeFlexFormSettings($flexForm);

        return [
            'source' => $this->readSource($flexForm),
            'folder' => (string)($settings['folder'] ?? ''),
            'recursive' => (bool)$this->scalarValue($settings['recursive'] ?? true),
            'sortBy' => (string)($settings['sortBy'] ?? 'name'),
            'sortDir' => (string)($settings['sortDir'] ?? 'asc'),
            'captions' => (string)($settings['captions'] ?? ''),
            'useFalCaptions' => (bool)$this->scalarValue($settings['useFalCaptions'] ?? true),
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeFlexFormSettings(mixed $flexForm): array
    {
        if (is_string($flexForm) && trim($flexForm) !== '') {
            try {
                $flexForm = GeneralUtility::makeInstance(FlexFormService::class)
                    ->convertFlexFormContentToArray($flexForm);
            } catch (\Throwable) {
                return [];
            }
        }

        if (!is_array($flexForm)) {
            return [];
        }

        if (isset($flexForm['settings']) && is_array($flexForm['settings'])) {
            return $flexForm['settings'];
        }

        $sheet = $flexForm['data']['sDEF']['lDEF'] ?? [];
        if (!is_array($sheet)) {
            return [];
        }

        $settings = [];
        foreach ($sheet as $fieldName => $fieldDefinition) {
            if (!is_array($fieldDefinition)) {
                continue;
            }
            $key = str_starts_with((string)$fieldName, 'settings.') ? substr((string)$fieldName, 9) : (string)$fieldName;
            $settings[$key] = $fieldDefinition['vDEF'] ?? null;
        }

        return $settings;
    }

    private function scalarValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        return $this->scalarValue(reset($value));
    }
}
