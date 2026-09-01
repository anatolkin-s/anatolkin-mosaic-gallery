<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

/**
 * Allowlisted TypoScript creation-default keys mapped to FlexForm DS locations.
 *
 * Normalization is fail-closed per field: invalid site values keep the XML default.
 */
final class MosaicGalleryCreationDefaultsDefinition
{
    /** @var array<string, array{sheet: string, field: string, kind: string, allowed?: list<string>, min?: int, max?: int, allowEmpty?: bool}> */
    private const FIELD_MAP = [
        'source' => ['sheet' => 'sDEF', 'field' => 'settings.source', 'kind' => 'select', 'allowed' => ['folder', 'manual']],
        'folder' => ['sheet' => 'sDEF', 'field' => 'settings.folder', 'kind' => 'string'],
        'recursive' => ['sheet' => 'sDEF', 'field' => 'settings.recursive', 'kind' => 'boolean'],
        'sortBy' => ['sheet' => 'sDEF', 'field' => 'settings.sortBy', 'kind' => 'select', 'allowed' => ['name', 'mtime', 'random']],
        'sortDir' => ['sheet' => 'sDEF', 'field' => 'settings.sortDir', 'kind' => 'select', 'allowed' => ['asc', 'desc']],
        'gap' => ['sheet' => 'sDEF', 'field' => 'settings.gap', 'kind' => 'integer', 'min' => 0],
        'layoutMode' => [
            'sheet' => 'sDEF',
            'field' => 'settings.layoutMode',
            'kind' => 'select',
            'allowed' => ['masonry', 'mosaic', 'patterned', 'justified', 'grid'],
        ],
        'maxItemsPerRow' => [
            'sheet' => 'sDEF',
            'field' => 'settings.maxItemsPerRow',
            'kind' => 'select',
            'allowed' => ['4', '5', '6', '7', '8'],
        ],
        'maxWidth' => ['sheet' => 'sDEF', 'field' => 'settings.maxWidth', 'kind' => 'integer', 'min' => 1],
        'enableLightbox' => ['sheet' => 'sDEF', 'field' => 'settings.enableLightbox', 'kind' => 'boolean'],
        'showCaptions' => ['sheet' => 'sDEF', 'field' => 'settings.showCaptions', 'kind' => 'boolean'],
        'captionAlign' => [
            'sheet' => 'sDEF',
            'field' => 'settings.captionAlign',
            'kind' => 'select',
            'allowed' => ['left', 'center', 'right'],
        ],
        'useFalCaptions' => ['sheet' => 'sDEF', 'field' => 'settings.useFalCaptions', 'kind' => 'boolean'],
        'enableLoadMore' => ['sheet' => 'sDEF', 'field' => 'settings.enableLoadMore', 'kind' => 'boolean'],
        'loadMoreUseFrameStyle' => ['sheet' => 'sDEF', 'field' => 'settings.loadMoreUseFrameStyle', 'kind' => 'boolean'],
        'itemsPerPage' => ['sheet' => 'sDEF', 'field' => 'settings.itemsPerPage', 'kind' => 'integer', 'min' => 1],
        'loadStep' => ['sheet' => 'sDEF', 'field' => 'settings.loadStep', 'kind' => 'integer', 'min' => 1],
        'designPreset' => [
            'sheet' => 'sDESIGN',
            'field' => 'settings.designPreset',
            'kind' => 'select',
            'allowed' => ['', 'site', 'bootstrap', 'clean', 'framed', 'dark'],
        ],
        'frameColor' => ['sheet' => 'sDESIGN', 'field' => 'settings.frameColor', 'kind' => 'color'],
        'frameAccentColor' => [
            'sheet' => 'sDESIGN',
            'field' => 'settings.frameAccentColor',
            'kind' => 'color',
            'allowEmpty' => true,
        ],
        'frameWidth' => ['sheet' => 'sDESIGN', 'field' => 'settings.frameWidth', 'kind' => 'integer', 'min' => 0, 'max' => 12],
        'frameStyle' => [
            'sheet' => 'sDESIGN',
            'field' => 'settings.frameStyle',
            'kind' => 'select',
            'allowed' => [
                'none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge',
                'triple', 'doubleOuterStrong', 'doubleInnerStrong', 'gallery',
            ],
        ],
        'borderRadius' => ['sheet' => 'sDESIGN', 'field' => 'settings.borderRadius', 'kind' => 'integer', 'min' => 0],
        'shadow' => ['sheet' => 'sDESIGN', 'field' => 'settings.shadow', 'kind' => 'boolean'],
        'backgroundColor' => ['sheet' => 'sDESIGN', 'field' => 'settings.backgroundColor', 'kind' => 'color'],
        'captionColor' => [
            'sheet' => 'sDESIGN',
            'field' => 'settings.captionColor',
            'kind' => 'color',
            'allowEmpty' => true,
        ],
        'applyTo' => [
            'sheet' => 'sDESIGN',
            'field' => 'settings.applyTo',
            'kind' => 'select',
            'allowed' => ['container', 'tiles', 'both'],
        ],
        'lbOverlay' => ['sheet' => 'sDESIGN', 'field' => 'settings.lbOverlay', 'kind' => 'color'],
        'lbOverlayAlpha' => ['sheet' => 'sDESIGN', 'field' => 'settings.lbOverlayAlpha', 'kind' => 'alpha'],
        'lbNavColor' => ['sheet' => 'sDESIGN', 'field' => 'settings.lbNavColor', 'kind' => 'color'],
        'lbCloseColor' => ['sheet' => 'sDESIGN', 'field' => 'settings.lbCloseColor', 'kind' => 'color'],
        'lbCaptionColor' => ['sheet' => 'sDESIGN', 'field' => 'settings.lbCaptionColor', 'kind' => 'color'],
        'lbCaptionBg' => ['sheet' => 'sDESIGN', 'field' => 'settings.lbCaptionBg', 'kind' => 'color'],
        'lbCaptionBgAlpha' => ['sheet' => 'sDESIGN', 'field' => 'settings.lbCaptionBgAlpha', 'kind' => 'alpha'],
        'lbCaptionAlign' => [
            'sheet' => 'sDESIGN',
            'field' => 'settings.lbCaptionAlign',
            'kind' => 'select',
            'allowed' => ['left', 'center', 'right'],
        ],
        'lbCaptionSize' => [
            'sheet' => 'sDESIGN',
            'field' => 'settings.lbCaptionSize',
            'kind' => 'select',
            'allowed' => ['small', 'normal', 'large'],
        ],
        'lbCaptionStyle' => [
            'sheet' => 'sDESIGN',
            'field' => 'settings.lbCaptionStyle',
            'kind' => 'select',
            'allowed' => ['regular', 'italic', 'strong'],
        ],
    ];

    /** @return list<string> */
    public function getAllowedKeys(): array
    {
        return array_keys(self::FIELD_MAP);
    }

    /**
     * @return array{sheet: string, field: string, kind: string, allowed?: list<string>, min?: int, max?: int, allowEmpty?: bool}|null
     */
    public static function fieldDefinition(string $key): ?array
    {
        return self::FIELD_MAP[$key] ?? null;
    }

    /**
     * @param array<string, mixed> $dataStructure
     * @param array<string, scalar> $siteDefaults
     * @return array<string, mixed>
     */
    public function applyToDataStructure(array $dataStructure, array $siteDefaults): array
    {
        if ($siteDefaults === [] || !isset($dataStructure['sheets']) || !is_array($dataStructure['sheets'])) {
            return $dataStructure;
        }

        foreach ($siteDefaults as $key => $rawValue) {
            if (!is_string($key) || !isset(self::FIELD_MAP[$key])) {
                continue;
            }

            $normalized = $this->normalizeValue($key, $rawValue);
            if ($normalized === null) {
                continue;
            }

            $definition = self::FIELD_MAP[$key];
            $sheet = $definition['sheet'];
            $field = $definition['field'];
            if (!is_array($dataStructure['sheets'][$sheet]['ROOT']['el'][$field]['config'] ?? null)) {
                continue;
            }

            $dataStructure['sheets'][$sheet]['ROOT']['el'][$field]['config']['default'] = $normalized;
        }

        return $dataStructure;
    }

    public function normalizeValue(string $key, mixed $value): string|int|null
    {
        if (!isset(self::FIELD_MAP[$key])) {
            return null;
        }

        $definition = self::FIELD_MAP[$key];

        return match ($definition['kind']) {
            'boolean' => $this->normalizeBoolean($value),
            'integer' => $this->normalizeInteger($value, $definition['min'] ?? null, $definition['max'] ?? null),
            'alpha' => $this->normalizeAlpha($value),
            'select' => $this->normalizeSelect($value, $definition['allowed'] ?? []),
            'color' => $this->normalizeColor($value, (bool)($definition['allowEmpty'] ?? false)),
            'string' => $this->normalizeString($value),
            default => null,
        };
    }

    private function normalizeBoolean(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            if ($value === 1) {
                return '1';
            }
            if ($value === 0) {
                return '0';
            }

            return null;
        }

        if (is_float($value)) {
            if ($value === 1.0) {
                return '1';
            }
            if ($value === 0.0) {
                return '0';
            }

            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $stringValue = strtolower(trim($value));
        if ($stringValue === '') {
            return null;
        }

        return match ($stringValue) {
            '1', 'true', 'yes', 'on' => '1',
            '0', 'false', 'no', 'off' => '0',
            default => null,
        };
    }

    private function normalizeInteger(mixed $value, ?int $min, ?int $max): ?string
    {
        if ($value === null || is_array($value) || is_object($value) || is_bool($value)) {
            return null;
        }

        $intValue = null;

        if (is_int($value)) {
            $intValue = $value;
        } elseif (is_float($value)) {
            if (!is_finite($value) || $value !== (float)(int)$value) {
                return null;
            }
            $intValue = (int)$value;
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || preg_match('/^-?\d+$/', $trimmed) !== 1) {
                return null;
            }
            $intValue = (int)$trimmed;
        } else {
            return null;
        }

        if ($min !== null && $intValue < $min) {
            return null;
        }
        if ($max !== null && $intValue > $max) {
            return null;
        }

        return (string)$intValue;
    }

    private function normalizeAlpha(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value) || is_bool($value)) {
            return null;
        }

        $number = null;

        if (is_int($value) || is_float($value)) {
            $number = (float)$value;
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || !is_numeric($trimmed)) {
                return null;
            }
            $number = (float)$trimmed;
        } else {
            return null;
        }

        if (!is_finite($number)) {
            return null;
        }

        if ($number < 0.0 || $number > 1.0) {
            return null;
        }

        $formatted = rtrim(rtrim(sprintf('%.2F', $number), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /** @param list<string> $allowed */
    private function normalizeSelect(mixed $value, array $allowed): ?string
    {
        $stringValue = (string)$value;
        if (!in_array($stringValue, $allowed, true)) {
            return null;
        }

        return $stringValue;
    }

    private function normalizeColor(mixed $value, bool $allowEmpty): ?string
    {
        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            return $allowEmpty ? '' : null;
        }

        if (preg_match('/^#([\da-f]{3}|[\da-f]{6})$/i', $stringValue) !== 1) {
            return null;
        }

        if (strlen($stringValue) === 4) {
            $stringValue = '#' . $stringValue[1] . $stringValue[1]
                . $stringValue[2] . $stringValue[2]
                . $stringValue[3] . $stringValue[3];
        }

        return strtoupper($stringValue);
    }

    private function normalizeString(mixed $value): ?string
    {
        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            return null;
        }

        return $stringValue;
    }
}
