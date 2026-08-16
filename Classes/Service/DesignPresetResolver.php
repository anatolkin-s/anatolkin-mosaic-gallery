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

    /**
     * @var array<string, array{
     *     preset: string,
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
     * }>
     */
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
        if ($requestedPreset === self::PRESET_CUSTOM) {
            return $this->resolveCustom($settings, self::PRESET_CUSTOM);
        }

        if ($requestedPreset === self::PRESET_SITE) {
            $effectiveSitePreset = $sitePreset !== null && isset(self::BUILT_IN_PRESETS[$sitePreset])
                ? $sitePreset
                : self::PRESET_BOOTSTRAP;

            return self::BUILT_IN_PRESETS[$effectiveSitePreset];
        }

        if (isset(self::BUILT_IN_PRESETS[$requestedPreset])) {
            return self::BUILT_IN_PRESETS[$requestedPreset];
        }

        return $this->resolveCustom($settings, self::PRESET_LEGACY);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{
     *     preset: string,
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
    private function resolveCustom(array $settings, string $preset): array
    {
        return [
            'preset' => $preset,
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
