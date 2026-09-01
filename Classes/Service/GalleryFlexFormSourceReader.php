<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class GalleryFlexFormSourceReader
{
    public const SOURCE_FOLDER = 'folder';
    public const SOURCE_MANUAL = 'manual';

    /** @var list<string> */
    private const SORT_BY_ALLOWED = ['name', 'mtime', 'random'];

    /** @var list<string> */
    private const SORT_DIR_ALLOWED = ['asc', 'desc'];

    public function readSource(mixed $flexForm): string
    {
        $settings = $this->normalizeFlexFormSettings($flexForm);
        $source = $this->stringValue($settings['source'] ?? null, self::SOURCE_FOLDER);

        return $source === self::SOURCE_MANUAL ? self::SOURCE_MANUAL : self::SOURCE_FOLDER;
    }

    /** @return array{source: string, folder: string, recursive: bool, sortBy: string, sortDir: string, captions: string, useFalCaptions: bool} */
    public function readSettings(mixed $flexForm): array
    {
        $settings = $this->normalizeFlexFormSettings($flexForm);

        return [
            'source' => $this->readSource($flexForm),
            'folder' => $this->stringValue($settings['folder'] ?? null, ''),
            'recursive' => $this->boolValue($settings['recursive'] ?? null, true),
            'sortBy' => $this->normalizeSortBy($settings['sortBy'] ?? null),
            'sortDir' => $this->normalizeSortDir($settings['sortDir'] ?? null),
            'captions' => $this->stringValue($settings['captions'] ?? null, ''),
            'useFalCaptions' => $this->boolValue($settings['useFalCaptions'] ?? null, true),
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

    private function normalizeSortBy(mixed $value): string
    {
        $sortBy = $this->stringValue($value, 'name');

        return \in_array($sortBy, self::SORT_BY_ALLOWED, true) ? $sortBy : 'name';
    }

    private function normalizeSortDir(mixed $value): string
    {
        $sortDir = $this->stringValue($value, 'asc');

        return \in_array($sortDir, self::SORT_DIR_ALLOWED, true) ? $sortDir : 'asc';
    }

    private function stringValue(mixed $value, string $default = ''): string
    {
        $scalar = $this->scalarValue($value);
        if ($scalar === null) {
            return $default;
        }

        if (\is_bool($scalar)) {
            return $scalar ? '1' : '0';
        }

        if (\is_int($scalar) || \is_float($scalar)) {
            return (string)$scalar;
        }

        if (\is_string($scalar)) {
            return $scalar;
        }

        return $default;
    }

    private function boolValue(mixed $value, bool $default): bool
    {
        $scalar = $this->scalarValue($value);
        if ($scalar === null) {
            return $default;
        }

        if (\is_bool($scalar)) {
            return $scalar;
        }

        if (\is_int($scalar) || \is_float($scalar)) {
            return $scalar !== 0;
        }

        if (\is_string($scalar)) {
            $normalized = strtolower(trim($scalar));
            if ($normalized === '') {
                return $default;
            }

            return match ($normalized) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => $default,
            };
        }

        return $default;
    }

    private function scalarValue(mixed $value): mixed
    {
        if (\is_array($value)) {
            if (\array_key_exists('vDEF', $value)) {
                return $this->scalarValue($value['vDEF']);
            }

            if ($value === []) {
                return null;
            }

            if (\count($value) === 1) {
                return $this->scalarValue(reset($value));
            }

            return null;
        }

        if (\is_object($value) || \is_resource($value)) {
            return null;
        }

        return $value;
    }
}
