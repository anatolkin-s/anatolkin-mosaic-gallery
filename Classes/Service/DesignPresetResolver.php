<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

final class DesignPresetResolver
{
    public const PRESET_LEGACY = 'legacy';
    public const PRESET_SITE = 'site';
    public const PRESET_BOOTSTRAP = 'bootstrap';
    public const PRESET_CLEAN = 'clean';
    public const PRESET_FRAMED = 'framed';
    public const PRESET_DARK = 'dark';
    public const PRESET_CUSTOM = 'custom';

    /** @var array<string, array<string, mixed>> */
    private const BUILT_IN_PRESETS = [
        self::PRESET_BOOTSTRAP => [
            'preset' => self::PRESET_BOOTSTRAP,
            'frameColor' => '#DEE2E6',
            'frameWidth' => '1',
            'frameStyle' => 'solid',
            'borderRadius' => 4,
            'shadow' => false,
            'backgroundColor' => '#F8F9FA',
            'applyTo' => 'container',
            'lightbox' => [
                'overlay' => '#212529',
                'overlayAlpha' => '0.94',
                'navColor' => '#F8F9FA',
                'closeColor' => '#F8F9FA',
                'captionColor' => '#F8F9FA',
                'captionBackground' => '#343A40',
                'captionBackgroundAlpha' => '0.78',
                'captionAlign' => 'left',
                'captionSize' => 'normal',
                'captionStyle' => 'regular',
            ],
        ],
        self::PRESET_CLEAN => [
            'preset' => self::PRESET_CLEAN,
            'frameColor' => '#A8B8A0',
            'frameWidth' => '1',
            'frameStyle' => 'solid',
            'borderRadius' => 8,
            'shadow' => false,
            'backgroundColor' => '#EEF2E8',
            'applyTo' => 'container',
            'lightbox' => [
                'overlay' => '#35413A',
                'overlayAlpha' => '0.93',
                'navColor' => '#F7F7F2',
                'closeColor' => '#F7F7F2',
                'captionColor' => '#F7F7F2',
                'captionBackground' => '#53645A',
                'captionBackgroundAlpha' => '0.72',
                'captionAlign' => 'left',
                'captionSize' => 'normal',
                'captionStyle' => 'regular',
            ],
        ],
        self::PRESET_FRAMED => [
            'preset' => self::PRESET_FRAMED,
            'frameColor' => '#A88467',
            'frameWidth' => '2',
            'frameStyle' => 'solid',
            'borderRadius' => 4,
            'shadow' => true,
            'backgroundColor' => '#F2E8D8',
            'applyTo' => 'tiles',
            'lightbox' => [
                'overlay' => '#3D332D',
                'overlayAlpha' => '0.93',
                'navColor' => '#F8F1E7',
                'closeColor' => '#F8F1E7',
                'captionColor' => '#F8F1E7',
                'captionBackground' => '#7A6454',
                'captionBackgroundAlpha' => '0.76',
                'captionAlign' => 'center',
                'captionSize' => 'normal',
                'captionStyle' => 'regular',
            ],
        ],
        self::PRESET_DARK => [
            'preset' => self::PRESET_DARK,
            'frameColor' => '#718579',
            'frameWidth' => '1',
            'frameStyle' => 'solid',
            'borderRadius' => 6,
            'shadow' => true,
            'backgroundColor' => '#2F3B35',
            'applyTo' => 'container',
            'lightbox' => [
                'overlay' => '#111815',
                'overlayAlpha' => '0.96',
                'navColor' => '#EFECE4',
                'closeColor' => '#EFECE4',
                'captionColor' => '#EFECE4',
                'captionBackground' => '#36483F',
                'captionBackgroundAlpha' => '0.80',
                'captionAlign' => 'left',
                'captionSize' => 'normal',
                'captionStyle' => 'regular',
            ],
        ],
    ];

    /**
     * @param array<string, mixed> $settings
     * @return array{
     *     preset: string,
     *     requestedPreset: string,
     *     effectivePreset: string,
     *     frameColor: string,
     *     frameWidth: string,
     *     frameStyle: string,
     *     borderRadius: int,
     *     shadow: bool,
     *     backgroundColor: string,
     *     applyTo: string,
     *     lightbox: array{
     *         overlay: string,
     *         overlayAlpha: string,
     *         navColor: string,
     *         closeColor: string,
     *         captionColor: string,
     *         captionBackground: string,
     *         captionBackgroundAlpha: string,
     *         captionAlign: string,
     *         captionSize: string,
     *         captionStyle: string
     *     }
     * }
     */
    public function resolve(array $settings, ?string $sitePreset = null): array
    {
        if (!array_key_exists('designPreset', $settings)) {
            return $this->resolveCustom($settings, self::PRESET_LEGACY);
        }

        $requestedPreset = (string)$settings['designPreset'];
        if ($requestedPreset === '' || $requestedPreset === self::PRESET_CUSTOM) {
            return $this->resolveCustom($settings, self::PRESET_CUSTOM);
        }

        if ($requestedPreset === self::PRESET_SITE) {
            $effectivePreset = $sitePreset !== null && isset(self::BUILT_IN_PRESETS[$sitePreset])
                ? $sitePreset
                : self::PRESET_BOOTSTRAP;

            return $this->applyOverrides(
                $this->withPresetMetadata(self::BUILT_IN_PRESETS[$effectivePreset], $requestedPreset, $effectivePreset),
                $this->resolveOverrideDocument($settings)
            );
        }

        if (isset(self::BUILT_IN_PRESETS[$requestedPreset])) {
            return $this->applyOverrides(
                $this->withPresetMetadata(
                    self::BUILT_IN_PRESETS[$requestedPreset],
                    $requestedPreset,
                    $requestedPreset
                ),
                $this->resolveOverrideDocument($settings)
            );
        }

        return $this->resolveCustom($settings, self::PRESET_LEGACY);
    }

    /**
     * Returns canonical preset bases for backend draft switching.
     *
     * @return array<string, array<string, mixed>>
     */
    public function resolveAvailablePresetBases(?string $sitePreset = null): array
    {
        $bases = [];
        foreach ([
            self::PRESET_SITE,
            self::PRESET_BOOTSTRAP,
            self::PRESET_CLEAN,
            self::PRESET_FRAMED,
            self::PRESET_DARK,
        ] as $preset) {
            $bases[$preset] = $this->resolve([
                'designPreset' => $preset,
                'designOverrides' => '{}',
            ], $sitePreset);
        }

        return $bases;
    }

    /**
     * @param array<string, mixed> $design
     * @return array<string, mixed>
     */
    private function withPresetMetadata(
        array $design,
        string $requestedPreset,
        string $effectivePreset
    ): array
    {
        $design['preset'] = $effectivePreset;
        $design['requestedPreset'] = $requestedPreset;
        $design['effectivePreset'] = $effectivePreset;

        return $design;
    }

    /**
     * @param array<string, mixed> $design
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function applyOverrides(array $design, array $overrides): array
    {
        foreach (['frameColor', 'frameWidth', 'frameStyle', 'borderRadius', 'shadow', 'backgroundColor', 'applyTo'] as $key) {
            if (array_key_exists($key, $overrides)) {
                $design[$key] = $overrides[$key];
            }
        }
        foreach ($overrides['lightbox'] ?? [] as $key => $value) {
            $design['lightbox'][$key] = $value;
        }

        return $design;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function resolveOverrideDocument(array $settings): array
    {
        if (array_key_exists('designOverrides', $settings)
            && trim((string)$settings['designOverrides']) !== ''
        ) {
            return $this->decodeOverrideDocument((string)$settings['designOverrides']);
        }

        return $this->normalizeOverrideDocument([
            'frameColor' => $settings['designOverrideFrameColor'] ?? null,
            'frameWidth' => $settings['designOverrideFrameWidth'] ?? null,
            'frameStyle' => $settings['designOverrideFrameStyle'] ?? null,
            'borderRadius' => $settings['designOverrideBorderRadius'] ?? null,
            'shadow' => $settings['designOverrideShadow'] ?? null,
            'backgroundColor' => $settings['designOverrideBackgroundColor'] ?? null,
            'applyTo' => $settings['designOverrideApplyTo'] ?? null,
            'lightbox' => [
                'overlay' => $settings['designOverrideLbOverlay'] ?? null,
                'overlayAlpha' => $settings['designOverrideLbOverlayAlpha'] ?? null,
                'navColor' => $settings['designOverrideLbNavColor'] ?? null,
                'closeColor' => $settings['designOverrideLbCloseColor'] ?? null,
                'captionColor' => $settings['designOverrideLbCaptionColor'] ?? null,
                'captionBackground' => $settings['designOverrideLbCaptionBg'] ?? null,
                'captionBackgroundAlpha' => $settings['designOverrideLbCaptionBgAlpha'] ?? null,
                'captionAlign' => $settings['designOverrideLbCaptionAlign'] ?? null,
                'captionSize' => $settings['designOverrideLbCaptionSize'] ?? null,
                'captionStyle' => $settings['designOverrideLbCaptionStyle'] ?? null,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function decodeOverrideDocument(string $json): array
    {
        try {
            $document = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($document) ? $this->normalizeOverrideDocument($document) : [];
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function normalizeOverrideDocument(array $document): array
    {
        $normalized = [];
        foreach (['frameColor', 'backgroundColor'] as $key) {
            $value = (string)($document[$key] ?? '');
            if ($value !== '') {
                $normalized[$key] = $value;
            }
        }
        $frameWidth = $this->normalizeNonNegativeNumber($document['frameWidth'] ?? null);
        if ($frameWidth !== null) {
            $normalized['frameWidth'] = $frameWidth;
        }
        $this->copyEnum($normalized, 'frameStyle', $document, ['none', 'solid', 'dashed', 'dotted']);
        $borderRadius = $this->normalizeNonNegativeInteger($document['borderRadius'] ?? null);
        if ($borderRadius !== null) {
            $normalized['borderRadius'] = $borderRadius;
        }
        if (is_bool($document['shadow'] ?? null)) {
            $normalized['shadow'] = $document['shadow'];
        } elseif (($document['shadow'] ?? null) === '1') {
            $normalized['shadow'] = true;
        } elseif (($document['shadow'] ?? null) === '0') {
            $normalized['shadow'] = false;
        }
        $this->copyEnum($normalized, 'applyTo', $document, ['container', 'tiles', 'both']);

        $lightbox = is_array($document['lightbox'] ?? null) ? $document['lightbox'] : [];
        $normalizedLightbox = [];
        foreach (['overlay', 'navColor', 'closeColor', 'captionColor', 'captionBackground'] as $key) {
            $value = (string)($lightbox[$key] ?? '');
            if ($value !== '') {
                $normalizedLightbox[$key] = $value;
            }
        }
        $this->copyAlpha($normalizedLightbox, 'overlayAlpha', $lightbox['overlayAlpha'] ?? null);
        $this->copyAlpha(
            $normalizedLightbox,
            'captionBackgroundAlpha',
            $lightbox['captionBackgroundAlpha'] ?? null
        );
        $this->copyEnum($normalizedLightbox, 'captionAlign', $lightbox, ['left', 'center', 'right']);
        $this->copyEnum($normalizedLightbox, 'captionSize', $lightbox, ['small', 'normal', 'large']);
        $this->copyEnum($normalizedLightbox, 'captionStyle', $lightbox, ['regular', 'italic', 'strong']);
        if ($normalizedLightbox !== []) {
            $normalized['lightbox'] = $normalizedLightbox;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $source
     * @param list<string> $allowedValues
     */
    private function copyEnum(
        array &$target,
        string $key,
        array $source,
        array $allowedValues
    ): void
    {
        $value = (string)($source[$key] ?? '');
        if (\in_array($value, $allowedValues, true)) {
            $target[$key] = $value;
        }
    }

    /** @param array<string, mixed> $target */
    private function copyAlpha(array &$target, string $key, mixed $value): void
    {
        $normalizedValue = $this->normalizeNumber($value);
        if ($normalizedValue === null) {
            return;
        }

        $target[$key] = $this->formatNumber(min(1.0, max(0.0, $normalizedValue)));
    }

    private function normalizeNonNegativeNumber(mixed $value): ?string
    {
        $normalizedValue = $this->normalizeNumber($value);
        if ($normalizedValue === null || $normalizedValue < 0) {
            return null;
        }

        return $this->formatNumber($normalizedValue);
    }

    private function normalizeNonNegativeInteger(mixed $value): ?int
    {
        $value = (string)($value ?? '');
        if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
            return null;
        }

        return (int)$value;
    }

    private function normalizeNumber(mixed $value): ?float
    {
        $value = (string)($value ?? '');
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $number = (float)$value;
        return is_finite($number) ? $number : null;
    }

    private function formatNumber(float $value): string
    {
        $formattedValue = rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
        return $formattedValue === '' || $formattedValue === '-0' ? '0' : $formattedValue;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function resolveCustom(array $settings, string $preset): array
    {
        return [
            'preset' => $preset,
            'requestedPreset' => $preset,
            'effectivePreset' => $preset,
            'frameColor' => (string)($settings['frameColor'] ?? ''),
            'frameWidth' => (string)($settings['frameWidth'] ?? ''),
            'frameStyle' => (string)($settings['frameStyle'] ?? ''),
            'borderRadius' => max(0, (int)($settings['borderRadius'] ?? 6)),
            'shadow' => (bool)($settings['shadow'] ?? false),
            'backgroundColor' => (string)($settings['backgroundColor'] ?? ''),
            'applyTo' => (string)($settings['applyTo'] ?? ''),
            'lightbox' => [
                'overlay' => (string)($settings['lbOverlay'] ?? ''),
                'overlayAlpha' => (string)($settings['lbOverlayAlpha'] ?? ''),
                'navColor' => (string)($settings['lbNavColor'] ?? ''),
                'closeColor' => (string)($settings['lbCloseColor'] ?? ''),
                'captionColor' => (string)($settings['lbCaptionColor'] ?? ''),
                'captionBackground' => (string)($settings['lbCaptionBg'] ?? ''),
                'captionBackgroundAlpha' => (string)($settings['lbCaptionBgAlpha'] ?? '1.00'),
                'captionAlign' => (string)($settings['lbCaptionAlign'] ?? 'left'),
                'captionSize' => (string)($settings['lbCaptionSize'] ?? 'normal'),
                'captionStyle' => (string)($settings['lbCaptionStyle'] ?? 'regular'),
            ],
        ];
    }
}
