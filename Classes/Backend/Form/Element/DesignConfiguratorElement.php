<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Backend\Form\Element;

use Anatolkin\MosaicGallery\Service\DesignPresetResolver;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

final class DesignConfiguratorElement extends AbstractFormElement
{
    private const LANGUAGE_FILE =
        'LLL:EXT:anatolkin_mosaic_gallery/Resources/Private/Language/locallang_be.xlf:';
    private const FRONTEND_LANGUAGE_FILE =
        'LLL:EXT:anatolkin_mosaic_gallery/Resources/Private/Language/locallang.xlf:';

    /** @var list<array{path: string, label: string, type: string, options?: array<string, string>, step?: string}> */
    private const CONTROLS = [
        ['path' => 'frameColor', 'label' => 'flexform.frameColor', 'type' => 'color'],
        ['path' => 'frameAccentColor', 'label' => 'flexform.frameAccentColor', 'type' => 'color'],
        ['path' => 'frameWidth', 'label' => 'flexform.frameWidth', 'type' => 'number', 'step' => '1'],
        ['path' => 'frameStyle', 'label' => 'flexform.frameStyle', 'type' => 'select', 'options' => [
            'none' => 'flexform.frameStyle.none', 'solid' => 'flexform.frameStyle.solid',
            'dashed' => 'flexform.frameStyle.dashed', 'dotted' => 'flexform.frameStyle.dotted',
            'double' => 'flexform.frameStyle.double', 'groove' => 'flexform.frameStyle.groove',
            'ridge' => 'flexform.frameStyle.ridge', 'triple' => 'flexform.frameStyle.triple',
            'doubleOuterStrong' => 'flexform.frameStyle.doubleOuterStrong',
            'doubleInnerStrong' => 'flexform.frameStyle.doubleInnerStrong',
            'gallery' => 'flexform.frameStyle.gallery',
        ]],
        ['path' => 'borderRadius', 'label' => 'flexform.borderRadius', 'type' => 'integer', 'step' => '1'],
        ['path' => 'shadow', 'label' => 'flexform.shadow', 'type' => 'boolean', 'options' => [
            '1' => 'flexform.designOverride.on', '0' => 'flexform.designOverride.off',
        ]],
        ['path' => 'backgroundColor', 'label' => 'flexform.backgroundColor', 'type' => 'color'],
        ['path' => 'captionColor', 'label' => 'flexform.captionColor', 'type' => 'color'],
        ['path' => 'applyTo', 'label' => 'flexform.applyTo', 'type' => 'select', 'options' => [
            'container' => 'flexform.applyTo.container', 'tiles' => 'flexform.applyTo.tiles',
            'both' => 'flexform.applyTo.both',
        ]],
        ['path' => 'lightbox.overlay', 'label' => 'flexform.lbOverlay', 'type' => 'color'],
        ['path' => 'lightbox.overlayAlpha', 'label' => 'flexform.lbOverlayAlpha', 'type' => 'alpha', 'step' => '0.01'],
        ['path' => 'lightbox.navColor', 'label' => 'flexform.lbNavColor', 'type' => 'color'],
        ['path' => 'lightbox.closeColor', 'label' => 'flexform.lbCloseColor', 'type' => 'color'],
        ['path' => 'lightbox.captionColor', 'label' => 'flexform.lbCaptionColor', 'type' => 'color'],
        ['path' => 'lightbox.captionBackground', 'label' => 'flexform.lbCaptionBg', 'type' => 'color'],
        ['path' => 'lightbox.captionBackgroundAlpha', 'label' => 'flexform.lbCaptionBgAlpha', 'type' => 'alpha', 'step' => '0.01'],
        ['path' => 'lightbox.captionAlign', 'label' => 'flexform.lbCaptionAlign', 'type' => 'select', 'options' => [
            'left' => 'flexform.lbCaptionAlign.left', 'center' => 'flexform.lbCaptionAlign.center',
            'right' => 'flexform.lbCaptionAlign.right',
        ]],
        ['path' => 'lightbox.captionSize', 'label' => 'flexform.lbCaptionSize', 'type' => 'select', 'options' => [
            'small' => 'flexform.lbCaptionSize.small', 'normal' => 'flexform.lbCaptionSize.normal',
            'large' => 'flexform.lbCaptionSize.large',
        ]],
        ['path' => 'lightbox.captionStyle', 'label' => 'flexform.lbCaptionStyle', 'type' => 'select', 'options' => [
            'regular' => 'flexform.lbCaptionStyle.regular', 'italic' => 'flexform.lbCaptionStyle.italic',
            'strong' => 'flexform.lbCaptionStyle.strong',
        ]],
    ];

    public function render(): array
    {
        $resultArray = $this->initializeResultArray();
        $parameterArray = $this->data['parameterArray'];
        $fieldId = StringUtility::getUniqueId('formengine-input-');
        $fieldName = (string)$parameterArray['itemFormElName'];
        $settings = $this->readSettings($this->data['databaseRow']['pi_flexform'] ?? '');
        $settings['designOverrides'] = (string)$this->scalarValue($parameterArray['itemFormElValue'] ?? '');

        $resolver = GeneralUtility::makeInstance(DesignPresetResolver::class);
        $overrides = $resolver->resolveOverrideDocument($settings);
        $presetBases = $resolver->resolveAvailablePresetBases();
        $savedPreset = (string)($settings['designPreset'] ?? '');
        $savedPreset = $savedPreset === '' ? DesignPresetResolver::PRESET_CUSTOM : $savedPreset;
        if ($savedPreset !== DesignPresetResolver::PRESET_CUSTOM && !isset($presetBases[$savedPreset])) {
            $savedPreset = DesignPresetResolver::PRESET_CUSTOM;
        }
        $previewPreset = isset($presetBases[$savedPreset]) ? $savedPreset : DesignPresetResolver::PRESET_BOOTSTRAP;
        $base = $presetBases[$previewPreset];
        if (isset($presetBases[$savedPreset])) {
            foreach (self::CONTROLS as $control) {
                $path = $control['path'];
                if ($this->hasPath($overrides, $path)
                    && $this->valueAtPath($overrides, $path) === $this->valueAtPath($base, $path)
                ) {
                    $this->removePath($overrides, $path);
                }
            }
        }
        $effective = $resolver->resolve([
            'designPreset' => $previewPreset,
            'designOverrides' => (string)json_encode($overrides, JSON_UNESCAPED_SLASHES),
        ]);
        $presetLabels = [];
        foreach (array_keys($presetBases) as $preset) {
            $presetLabels[$preset] = $this->presetLabel($preset);
        }
        $presetLabels[DesignPresetResolver::PRESET_CUSTOM] = $this->presetLabel(DesignPresetResolver::PRESET_CUSTOM);
        $savedLabel = $presetLabels[$savedPreset] ?? $presetLabels[DesignPresetResolver::PRESET_CUSTOM];
        $modifiedCount = $this->countLeaves($overrides);

        $hiddenValue = htmlspecialchars(
            (string)json_encode($overrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
        );
        $presetBasesJson = htmlspecialchars(
            (string)json_encode($presetBases, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
        );
        $presetLabelsJson = htmlspecialchars(
            (string)json_encode($presetLabels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
        );
        $controlPathsJson = htmlspecialchars(
            (string)json_encode(array_column(self::CONTROLS, 'path'), JSON_UNESCAPED_SLASHES),
            ENT_QUOTES,
        );

        $html = '<div class="form-control-wrap mosaic-design-configurator" data-mosaic-design-configurator'
            . ' data-saved-preset="' . htmlspecialchars($savedPreset, ENT_QUOTES) . '"'
            . ' data-saved-overrides="' . $hiddenValue . '"'
            . ' data-preset-bases="' . $presetBasesJson . '"'
            . ' data-preset-labels="' . $presetLabelsJson . '"'
            . ' data-control-paths="' . $controlPathsJson . '"'
            . ' data-saved-label="' . $this->label('design.configurator.saved') . '"'
            . ' data-previewing-label="' . $this->label('design.configurator.previewing') . '"'
            . ' data-unsaved-label="' . $this->label('design.configurator.unsaved') . '"'
            . ' data-reset-all-template="' . $this->label('design.configurator.resetAll') . '"'
            . ' data-site-default-label="' . $this->label('design.configurator.siteDefault') . '"'
            . ' data-eyedropper-label="' . $this->label('design.configurator.eyedropper') . '"'
            . ' data-modified-label="' . $this->label('design.configurator.modified') . '">'
            . '<input type="hidden" id="' . htmlspecialchars($fieldId, ENT_QUOTES) . '"'
            . ' name="' . htmlspecialchars($fieldName, ENT_QUOTES) . '" value="' . $hiddenValue . '"'
            . ' data-formengine-input-name="' . htmlspecialchars($fieldName, ENT_QUOTES) . '"'
            . ' data-design-storage>'
            . '<div class="mosaic-design-configurator__toolbar">'
            . '<div class="mosaic-design-configurator__preset" data-design-preset-slot></div>'
            . '<div class="mosaic-design-configurator__status" data-design-status><strong>'
            . $this->label('design.configurator.saved') . ': '
            . '<span data-design-saved>' . htmlspecialchars($savedLabel, ENT_QUOTES) . '</span></strong>'
            . '<span class="form-text" data-design-preview hidden></span>'
            . '<span class="form-text" data-design-modifications></span></div>'
            . '<button type="button" class="btn btn-default btn-sm" data-design-reset-all'
            . ($modifiedCount === 0 ? ' disabled' : '') . '>'
            . $this->formatLabel(
                'design.configurator.resetAll',
                $previewPreset === DesignPresetResolver::PRESET_SITE
                    ? $this->rawLabel('design.configurator.siteDefault')
                    : $presetLabels[$previewPreset],
            ) . '</button></div>'
            . $this->renderPreview($fieldId)
            . $this->renderDisplayControls()
            . '<div class="mosaic-design-configurator__grid" data-design-controls>';

        foreach (self::CONTROLS as $control) {
            $path = $control['path'];
            $baseValue = $this->valueAtPath($base, $path);
            $effectiveValue = $this->valueAtPath($effective, $path);
            $isModified = $this->hasPath($overrides, $path);
            $html .= $this->renderControl($control, $baseValue, $effectiveValue, $isModified);
        }

        $html .= '</div></div>';
        $resultArray['html'] = $html;
        $resultArray['stylesheetFiles'][] =
            'EXT:anatolkin_mosaic_gallery/Resources/Public/Backend/Css/form-layout.css';
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            '@anatolkin/mosaic-gallery/design-configurator.js',
        );

        return $resultArray;
    }

    private function renderPreview(string $fieldId): string
    {
        $previewId = $fieldId . '-preview-title';
        $caption = $this->label('design.preview.sampleCaption');
        $images = [
            $this->previewImageUrl('landscape.svg'),
            $this->previewImageUrl('portrait.svg'),
            $this->previewImageUrl('square.svg'),
            $this->previewImageUrl('wide.svg'),
            $this->previewImageUrl('contrast.svg'),
        ];

        $galleryColumns = ['', '', ''];
        $extraItems = '';
        foreach ($images as $index => $image) {
            $item = '<figure class="mosaic-design-preview__item" data-preview-fixture="' . $index . '"'
                . ($index > 2 ? ' data-design-preview-extra hidden' : '') . '>'
                . '<img src="' . htmlspecialchars($image, ENT_QUOTES) . '" alt="">'
                . '<figcaption>' . $caption . '</figcaption></figure>';
            if ($index < 3) {
                $galleryColumns[$index] = $item;
            } else {
                $extraItems .= $item;
            }
        }
        $galleryItems = '';
        foreach ($galleryColumns as $column) {
            $galleryItems .= '<div class="mosaic-design-preview__column" data-design-preview-column>'
                . $column . '</div>';
        }

        return '<section class="mosaic-design-preview" data-design-live-preview aria-labelledby="'
            . htmlspecialchars($previewId, ENT_QUOTES) . '">'
            . '<div class="mosaic-design-preview__heading"><div><strong id="'
            . htmlspecialchars($previewId, ENT_QUOTES) . '">' . $this->label('design.preview.title') . '</strong>'
            . '<div class="form-text">' . $this->label('design.preview.help') . '</div></div></div>'
            . '<div class="mosaic-design-preview__panels">'
            . '<div class="mosaic-design-preview__panel mosaic-design-preview__gallery">'
            . '<div class="mosaic-design-preview__panel-label">' . $this->label('design.preview.gallery') . '</div>'
            . '<div class="mosaic-design-preview__gallery-surface" data-preview-gallery-surface>'
            . '<div class="mosaic-design-preview__items">' . $galleryItems . '</div>'
            . '<div data-design-preview-extras hidden>' . $extraItems . '</div>'
            . '<div class="mosaic-design-preview__actions"><button type="button" class="btn btn-default btn-sm"'
            . ' data-design-preview-load-more>' . $this->frontendLabel('gallery.loadMore') . '</button></div>'
            . '</div></div>'
            . '<div class="mosaic-design-preview__panel mosaic-design-preview__lightbox">'
            . '<div class="mosaic-design-preview__panel-label">' . $this->label('design.preview.lightbox') . '</div>'
            . '<div class="mosaic-design-preview__lightbox-surface" data-preview-lightbox-surface>'
            . '<span class="mosaic-design-preview__close" aria-hidden="true">×</span>'
            . '<span class="mosaic-design-preview__nav mosaic-design-preview__nav--previous" aria-hidden="true">‹</span>'
            . '<figure class="mosaic-design-preview__lightbox-figure"><img src="'
            . htmlspecialchars($images[4], ENT_QUOTES) . '" alt=""><figcaption>' . $caption
            . '</figcaption></figure>'
            . '<span class="mosaic-design-preview__nav mosaic-design-preview__nav--next" aria-hidden="true">›</span>'
            . '</div></div></div></section>';
    }

    private function renderDisplayControls(): string
    {
        $booleanOptions = '<option value="0">' . $this->label('flexform.designOverride.off') . '</option>'
            . '<option value="1">' . $this->label('flexform.designOverride.on') . '</option>';
        $field = static fn(string $id, string $label, string $control): string =>
            '<label class="mosaic-design-display-controls__field"><span>' . $label . '</span>' . $control . '</label>';

        return '<div class="mosaic-design-display-controls" data-design-display-controls>'
            . $field('gap', $this->label('flexform.gap'), '<input type="number" min="0" class="form-control form-control-sm" data-design-proxy="settings.gap">')
            . $field('showCaptions', $this->label('flexform.showCaptions'), '<select class="form-select form-select-sm" data-design-proxy="settings.showCaptions">' . $booleanOptions . '</select>')
            . $field('captionAlign', $this->label('flexform.captionAlign'), '<select class="form-select form-select-sm" data-design-proxy="settings.captionAlign"><option value="left">' . $this->label('flexform.captionAlign.left') . '</option><option value="center">' . $this->label('flexform.captionAlign.center') . '</option><option value="right">' . $this->label('flexform.captionAlign.right') . '</option></select>')
            . $field('enableLightbox', $this->label('flexform.enableLightbox'), '<select class="form-select form-select-sm" data-design-proxy="settings.enableLightbox">' . $booleanOptions . '</select>')
            . $field('enableLoadMore', $this->label('flexform.enableLoadMore'), '<select class="form-select form-select-sm" data-design-proxy="settings.enableLoadMore">' . $booleanOptions . '</select>')
            . $field('loadMoreUseFrameStyle', $this->label('flexform.loadMoreUseFrameStyle'), '<select class="form-select form-select-sm" data-design-proxy="settings.loadMoreUseFrameStyle">' . $booleanOptions . '</select>')
            . '</div>';
    }

    private function previewImageUrl(string $fileName): string
    {
        return PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName(
            'EXT:anatolkin_mosaic_gallery/Resources/Public/Backend/Images/DesignPreview/' . $fileName,
        ));
    }

    /** @param array{path: string, label: string, type: string, options?: array<string, string>, step?: string} $control */
    private function renderControl(array $control, mixed $baseValue, mixed $effectiveValue, bool $modified): string
    {
        $path = $control['path'];
        $type = $control['type'];
        $baseJson = htmlspecialchars((string)json_encode($baseValue), ENT_QUOTES);
        $value = $type === 'boolean' ? ($effectiveValue ? '1' : '0') : (string)$effectiveValue;
        $attributes = ' data-design-control data-design-path="' . htmlspecialchars($path, ENT_QUOTES) . '"'
            . ' data-design-kind="' . $type . '" data-design-base-value="' . $baseJson . '"';

        if ($type === 'select' || $type === 'boolean') {
            $controlHtml = '<select class="form-select form-select-sm"' . $attributes . '>';
            foreach ($control['options'] ?? [] as $optionValue => $labelKey) {
                $optionValue = (string)$optionValue;
                $controlHtml .= '<option value="' . htmlspecialchars($optionValue, ENT_QUOTES) . '"'
                    . ($optionValue === $value ? ' selected' : '') . '>' . $this->label($labelKey) . '</option>';
            }
            $controlHtml .= '</select>';
        } else {
            $inputType = ($type === 'number' || $type === 'integer' || $type === 'alpha') ? 'number' : 'text';
            $inputAttributes = $inputType === 'number'
                ? ' min="0"' . ($type === 'alpha' ? ' max="1"' : '')
                    . ' step="' . htmlspecialchars($control['step'] ?? '1', ENT_QUOTES) . '"'
                : '';
            $controlHtml = ($type === 'color'
                ? '<input type="color" class="mosaic-design-configurator__picker" data-design-color-picker'
                    . ' value="' . htmlspecialchars($value, ENT_QUOTES) . '" aria-label="'
                    . $this->label('design.configurator.colorPicker') . '">'
                : '')
                . '<input type="' . $inputType . '" class="form-control form-control-sm" value="'
                . htmlspecialchars($value, ENT_QUOTES) . '"' . $inputAttributes . $attributes . '>'
                . ($type === 'color'
                    ? '<button type="button" class="btn btn-default btn-sm" data-design-eyedropper hidden title="'
                        . $this->label('design.configurator.eyedropper') . '" aria-label="'
                        . $this->label('design.configurator.eyedropper') . '">⌾</button>'
                    : '');
        }

        return '<div class="mosaic-design-configurator__field" data-design-field="'
            . htmlspecialchars($path, ENT_QUOTES) . '"><label class="form-label">' . $this->label($control['label'])
            . '</label><div class="mosaic-design-configurator__control">' . $controlHtml
            . '<button type="button" class="btn btn-default btn-sm" data-design-reset-field'
            . ($modified ? '' : ' disabled hidden') . ' title="' . $this->label('design.configurator.reset') . '">'
            . $this->label('design.configurator.reset') . '</button></div></div>';
    }

    /** @return array<string, mixed> */
    private function readSettings(mixed $flexForm): array
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
        if (is_array($flexForm['settings'] ?? null)) {
            return $flexForm['settings'];
        }

        $settings = [];
        foreach (($flexForm['data'] ?? []) as $sheet) {
            foreach (($sheet['lDEF'] ?? []) as $fieldName => $fieldValue) {
                if (!str_starts_with((string)$fieldName, 'settings.')) {
                    continue;
                }
                $settings[substr((string)$fieldName, 9)] = $this->scalarValue($fieldValue['vDEF'] ?? '');
            }
        }
        return $settings;
    }

    private function scalarValue(mixed $value): mixed
    {
        if (is_array($value) && array_key_exists(0, $value)) {
            return $this->scalarValue($value[0]);
        }
        return $value;
    }

    /** @param array<string, mixed> $document */
    private function valueAtPath(array $document, string $path): mixed
    {
        $value = $document;
        foreach (explode('.', $path) as $segment) {
            $value = is_array($value) ? ($value[$segment] ?? '') : '';
        }
        return $value;
    }

    /** @param array<string, mixed> $document */
    private function hasPath(array $document, string $path): bool
    {
        $value = $document;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }
        return true;
    }

    /** @param array<string, mixed> $document */
    private function removePath(array &$document, string $path): void
    {
        $segments = explode('.', $path);
        if (count($segments) === 1) {
            unset($document[$segments[0]]);
            return;
        }
        $parent = $segments[0];
        $child = $segments[1];
        if (is_array($document[$parent] ?? null)) {
            unset($document[$parent][$child]);
            if ($document[$parent] === []) {
                unset($document[$parent]);
            }
        }
    }

    /** @param array<string, mixed> $document */
    private function countLeaves(array $document): int
    {
        $count = 0;
        foreach ($document as $value) {
            $count += is_array($value) ? $this->countLeaves($value) : 1;
        }
        return $count;
    }

    private function presetLabel(string $preset): string
    {
        return $this->rawLabel('flexform.designPreset.' . match ($preset) {
            DesignPresetResolver::PRESET_SITE => 'site',
            DesignPresetResolver::PRESET_CLEAN => 'clean',
            DesignPresetResolver::PRESET_FRAMED => 'framed',
            DesignPresetResolver::PRESET_DARK => 'dark',
            DesignPresetResolver::PRESET_CUSTOM, DesignPresetResolver::PRESET_LEGACY => 'custom',
            default => 'bootstrap',
        });
    }

    private function label(string $key): string
    {
        return htmlspecialchars($this->rawLabel($key), ENT_QUOTES);
    }

    private function frontendLabel(string $key): string
    {
        return htmlspecialchars($this->getLanguageService()->sL(self::FRONTEND_LANGUAGE_FILE . $key), ENT_QUOTES);
    }

    private function rawLabel(string $key): string
    {
        return $this->getLanguageService()->sL(self::LANGUAGE_FILE . $key);
    }

    private function formatLabel(string $key, string $value): string
    {
        return htmlspecialchars(sprintf($this->getLanguageService()->sL(self::LANGUAGE_FILE . $key), $value), ENT_QUOTES);
    }
}
