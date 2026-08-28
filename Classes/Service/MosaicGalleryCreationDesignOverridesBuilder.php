<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

/**
 * Encodes site TypoScript creation defaults into the canonical designOverrides JSON shape.
 *
 * The builder does not diff against preset bases; DesignConfiguratorElement removes
 * overrides equal to the active preset base after render.
 */
final class MosaicGalleryCreationDesignOverridesBuilder
{
    /** @var list<string> */
    private const NAMED_PRESETS = [
        DesignPresetResolver::PRESET_SITE,
        DesignPresetResolver::PRESET_BOOTSTRAP,
        DesignPresetResolver::PRESET_CLEAN,
        DesignPresetResolver::PRESET_FRAMED,
        DesignPresetResolver::PRESET_DARK,
    ];

    /** @var list<string> */
    private const DESIGN_KEYS = [
        'frameColor',
        'frameAccentColor',
        'frameWidth',
        'frameStyle',
        'borderRadius',
        'shadow',
        'backgroundColor',
        'captionColor',
        'applyTo',
        'lbOverlay',
        'lbOverlayAlpha',
        'lbNavColor',
        'lbCloseColor',
        'lbCaptionColor',
        'lbCaptionBg',
        'lbCaptionBgAlpha',
        'lbCaptionAlign',
        'lbCaptionSize',
        'lbCaptionStyle',
    ];

    /** @var array<string, list<string>> */
    private const KEY_PATHS = [
        'frameColor' => ['frameColor'],
        'frameAccentColor' => ['frameAccentColor'],
        'frameWidth' => ['frameWidth'],
        'frameStyle' => ['frameStyle'],
        'borderRadius' => ['borderRadius'],
        'shadow' => ['shadow'],
        'backgroundColor' => ['backgroundColor'],
        'captionColor' => ['captionColor'],
        'applyTo' => ['applyTo'],
        'lbOverlay' => ['lightbox', 'overlay'],
        'lbOverlayAlpha' => ['lightbox', 'overlayAlpha'],
        'lbNavColor' => ['lightbox', 'navColor'],
        'lbCloseColor' => ['lightbox', 'closeColor'],
        'lbCaptionColor' => ['lightbox', 'captionColor'],
        'lbCaptionBg' => ['lightbox', 'captionBackground'],
        'lbCaptionBgAlpha' => ['lightbox', 'captionBackgroundAlpha'],
        'lbCaptionAlign' => ['lightbox', 'captionAlign'],
        'lbCaptionSize' => ['lightbox', 'captionSize'],
        'lbCaptionStyle' => ['lightbox', 'captionStyle'],
    ];

    public function __construct(
        private readonly MosaicGalleryCreationDefaultsDefinition $creationDefaultsDefinition,
        private readonly DesignPresetResolver $designPresetResolver,
    ) {
    }

    /**
     * @param array<string, scalar> $siteDefaults
     */
    public function buildJson(array $siteDefaults): ?string
    {
        if (!$this->isNamedPresetCreation($siteDefaults)) {
            return null;
        }

        $document = $this->buildDocument($siteDefaults);
        if ($document === []) {
            return null;
        }

        $normalized = $this->designPresetResolver->decodeOverrideDocument(
            (string)json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );
        if ($normalized === []) {
            return null;
        }

        return (string)json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, scalar> $siteDefaults
     */
    public function isNamedPresetCreation(array $siteDefaults): bool
    {
        if (!array_key_exists('designPreset', $siteDefaults)) {
            return false;
        }

        $preset = $this->creationDefaultsDefinition->normalizeValue(
            'designPreset',
            $siteDefaults['designPreset'],
        );
        if ($preset === null || $preset === '') {
            return false;
        }

        return in_array($preset, self::NAMED_PRESETS, true);
    }

    /**
     * @param array<string, scalar> $siteDefaults
     * @return array<string, mixed>
     */
    private function buildDocument(array $siteDefaults): array
    {
        $document = [];

        foreach (self::DESIGN_KEYS as $key) {
            if (!array_key_exists($key, $siteDefaults)) {
                continue;
            }

            $normalized = $this->creationDefaultsDefinition->normalizeValue($key, $siteDefaults[$key]);
            if ($normalized === null) {
                continue;
            }

            $this->assignDocumentValue($document, self::KEY_PATHS[$key], $key, $normalized);
        }

        return $document;
    }

    /**
     * @param list<string> $path
     */
    private function assignDocumentValue(array &$document, array $path, string $key, string $normalized): void
    {
        $definition = MosaicGalleryCreationDefaultsDefinition::fieldDefinition($key);
        if ($definition === null) {
            return;
        }
        $kind = $definition['kind'] ?? 'string';

        $value = match ($kind) {
            'boolean' => $normalized === '1',
            'integer' => (int)$normalized,
            default => $normalized,
        };

        $target = &$document;
        foreach ($path as $index => $segment) {
            if ($index === count($path) - 1) {
                $target[$segment] = $value;
                return;
            }
            $target[$segment] = is_array($target[$segment] ?? null) ? $target[$segment] : [];
            $target = &$target[$segment];
        }
    }
}
