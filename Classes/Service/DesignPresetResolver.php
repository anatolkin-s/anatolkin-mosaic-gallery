<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

final class DesignPresetResolver
{
    /**
     * @param array<string, mixed> $settings
     * @return array{
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
     *         captionBackground: string
     *     }
     * }
     */
    public function resolve(array $settings): array
    {
        return [
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
            ],
        ];
    }
}
