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
        ['path' => 'frameColor', 'label' => 'design.field.primary', 'type' => 'color'],
        ['path' => 'frameAccentColor', 'label' => 'design.field.accent', 'type' => 'color'],
        ['path' => 'frameWidth', 'label' => 'design.field.width', 'type' => 'number', 'step' => '1'],
        ['path' => 'frameStyle', 'label' => 'design.field.style', 'type' => 'select', 'options' => [
            'none' => 'flexform.frameStyle.none', 'solid' => 'flexform.frameStyle.solid',
            'dashed' => 'flexform.frameStyle.dashed', 'dotted' => 'flexform.frameStyle.dotted',
            'double' => 'flexform.frameStyle.double', 'groove' => 'flexform.frameStyle.groove',
            'ridge' => 'flexform.frameStyle.ridge', 'triple' => 'flexform.frameStyle.triple',
            'doubleOuterStrong' => 'flexform.frameStyle.doubleOuterStrong',
            'doubleInnerStrong' => 'flexform.frameStyle.doubleInnerStrong',
            'gallery' => 'flexform.frameStyle.gallery',
        ]],
        ['path' => 'borderRadius', 'label' => 'design.field.radius', 'type' => 'integer', 'step' => '1'],
        ['path' => 'shadow', 'label' => 'design.field.shadow', 'type' => 'boolean', 'options' => [
            '1' => 'flexform.designOverride.on', '0' => 'flexform.designOverride.off',
        ]],
        ['path' => 'backgroundColor', 'label' => 'design.field.background', 'type' => 'color'],
        ['path' => 'captionColor', 'label' => 'design.field.captionColor', 'type' => 'color'],
        ['path' => 'applyTo', 'label' => 'design.field.backgroundTarget', 'type' => 'select', 'options' => [
            'container' => 'flexform.applyTo.container', 'tiles' => 'flexform.applyTo.tiles',
            'both' => 'flexform.applyTo.both',
        ]],
        ['path' => 'lightbox.overlay', 'label' => 'design.field.overlay', 'type' => 'color'],
        ['path' => 'lightbox.overlayAlpha', 'label' => 'design.field.opacity', 'type' => 'alpha', 'step' => '0.01'],
        ['path' => 'lightbox.navColor', 'label' => 'design.field.navigation', 'type' => 'color'],
        ['path' => 'lightbox.closeColor', 'label' => 'design.field.close', 'type' => 'color'],
        ['path' => 'lightbox.captionColor', 'label' => 'design.field.captionColor', 'type' => 'color'],
        ['path' => 'lightbox.captionBackground', 'label' => 'design.field.captionBackground', 'type' => 'color'],
        ['path' => 'lightbox.captionBackgroundAlpha', 'label' => 'design.field.backgroundOpacity', 'type' => 'alpha', 'step' => '0.01'],
        ['path' => 'lightbox.captionAlign', 'label' => 'design.field.alignment', 'type' => 'select', 'options' => [
            'left' => 'flexform.lbCaptionAlign.left', 'center' => 'flexform.lbCaptionAlign.center',
            'right' => 'flexform.lbCaptionAlign.right',
        ]],
        ['path' => 'lightbox.captionSize', 'label' => 'design.field.size', 'type' => 'select', 'options' => [
            'small' => 'flexform.lbCaptionSize.small', 'normal' => 'flexform.lbCaptionSize.normal',
            'large' => 'flexform.lbCaptionSize.large',
        ]],
        ['path' => 'lightbox.captionStyle', 'label' => 'design.field.style', 'type' => 'select', 'options' => [
            'regular' => 'flexform.lbCaptionStyle.regular', 'italic' => 'flexform.lbCaptionStyle.italic',
            'strong' => 'flexform.lbCaptionStyle.strong',
        ]],
    ];

    /** @var array<string, list<string>> */
    private const CONTROL_GROUPS = [
        'gallery' => ['captionColor', 'applyTo'],
        'frame' => [
            'frameColor', 'frameAccentColor', 'frameWidth', 'frameStyle',
            'borderRadius', 'shadow', 'backgroundColor',
        ],
        'lightbox' => [
            'lightbox.overlay', 'lightbox.overlayAlpha', 'lightbox.navColor', 'lightbox.closeColor',
            'lightbox.captionColor', 'lightbox.captionBackground', 'lightbox.captionBackgroundAlpha',
            'lightbox.captionAlign', 'lightbox.captionSize', 'lightbox.captionStyle',
        ],
        'loadMore' => [],
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
            . '<div class="mosaic-design-configurator__status" data-design-status>'
            . '<span data-design-status-prefix>' . $this->label('design.configurator.saved') . '</span>: '
            . '<span class="mosaic-design-configurator__preset-name">“<span data-design-status-name>'
            . htmlspecialchars($savedLabel, ENT_QUOTES) . '</span>”</span>'
            . '<span data-design-status-detail>'
            . ($modifiedCount > 0 ? ' · ' . $modifiedCount . ' ' . $this->label('design.configurator.modified') : '')
            . '</span></div>'
            . '<button type="button" class="btn btn-default btn-sm" data-design-reset-all'
            . ($modifiedCount === 0 ? ' disabled' : '') . '>'
            . $this->formatLabel(
                'design.configurator.resetAll',
                $previewPreset === DesignPresetResolver::PRESET_SITE
                    ? $this->rawLabel('design.configurator.siteDefault')
                    : $presetLabels[$previewPreset],
            ) . '</button></div>'
            . $this->renderPreview($fieldId)
            . $this->renderControlGroups($base, $effective, $overrides, $settings)
            . '</div>';
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
        $ratios = [1.6, 0.6667, 1.0, 2.1111, 1.5238];
        $weights = ['medium', 'small', 'medium', 'large', 'small'];

        $galleryColumns = ['', '', ''];
        $extraItems = '';
        foreach ($images as $index => $image) {
            $item = '<figure class="mosaic-design-preview__item" data-preview-fixture="' . $index
                . '" data-preview-ratio="' . $ratios[$index] . '" data-preview-weight="' . $weights[$index] . '"'
                . ($index > 2 ? ' data-design-preview-extra hidden' : '') . '>'
                . '<span class="mosaic-design-preview__frame"><img src="'
                . htmlspecialchars($image, ENT_QUOTES) . '" alt=""></span>'
                . '<figcaption>' . $caption . '</figcaption></figure>';
            if ($index < 3) {
                $galleryColumns[$index] = $item;
            } else {
                $extraItems .= $item;
            }
        }
        $galleryItems = '';
        foreach ($galleryColumns as $index => $column) {
            $galleryItems .= '<div class="mosaic-design-preview__column" data-design-preview-column'
                . ($index === 0 ? ' data-preview-span="wide"' : '') . '>'
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
            . '<div class="mosaic-design-preview__items">' . $galleryItems
            . '<div data-design-preview-extras hidden>' . $extraItems . '</div></div>'
            . '<div class="mosaic-design-preview__actions"><button type="button" class="btn btn-default btn-sm"'
            . ' data-design-preview-load-more>' . $this->frontendLabel('gallery.loadMore') . '</button></div>'
            . '</div></div>'
            . '<div class="mosaic-design-preview__panel mosaic-design-preview__lightbox">'
            . '<div class="mosaic-design-preview__panel-label">' . $this->label('design.preview.lightbox') . '</div>'
            . '<div class="mosaic-design-preview__lightbox-surface" data-preview-lightbox-surface>'
            . '<span class="mosaic-design-preview__close" aria-hidden="true">×</span>'
            . '<span class="mosaic-design-preview__nav mosaic-design-preview__nav--previous" aria-hidden="true">‹</span>'
            . '<figure class="mosaic-design-preview__lightbox-figure">'
            . '<span class="mosaic-design-preview__frame"><img src="'
            . htmlspecialchars($images[4], ENT_QUOTES) . '" alt=""></span><figcaption>' . $caption
            . '</figcaption></figure>'
            . '<span class="mosaic-design-preview__nav mosaic-design-preview__nav--next" aria-hidden="true">›</span>'
            . '</div></div></div></section>';
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $effective @param array<string, mixed> $overrides @param array<string, mixed> $settings */
    private function renderControlGroups(array $base, array $effective, array $overrides, array $settings): string
    {
        $controls = [];
        foreach (self::CONTROLS as $control) {
            $controls[$control['path']] = $control;
        }

        $html = '<div class="mosaic-design-configurator__groups">';
        foreach (self::CONTROL_GROUPS as $group => $paths) {
            $html .= '<section class="mosaic-design-configurator__group" data-design-group="' . $group . '">'
                . '<h3>' . $this->label('design.group.' . $group) . '</h3>'
                . '<div class="mosaic-design-configurator__grid" data-design-controls>'
                . $this->renderDisplayControls($group, $settings);
            foreach ($paths as $path) {
                $control = $controls[$path];
                $html .= $this->renderControl(
                    $control,
                    $this->valueAtPath($base, $path),
                    $this->valueAtPath($effective, $path),
                    $this->hasPath($overrides, $path),
                );
            }
            $html .= '</div><div class="mosaic-design-configurator__custom" data-design-custom-group="'
                . $group . '"></div></section>';
        }
        return $html . '</div>';
    }

    /** @param array<string, mixed> $settings */
    private function renderDisplayControls(string $group, array $settings): string
    {
        $booleanOptions = '<option value="0">' . $this->label('flexform.designOverride.off') . '</option>'
            . '<option value="1">' . $this->label('flexform.designOverride.on') . '</option>';
        $field = fn(string $labelKey, string $control): string =>
            '<label class="mosaic-design-display-controls__field"><span>'
            . $this->label($labelKey) . '</span>' . $control . '</label>';
        $proxyDefault = fn(string $fieldName): string => $this->proxyDefaultAttribute($fieldName, $settings);

        return match ($group) {
            'gallery' => $field('design.field.gap', '<input type="number" min="0" class="form-control form-control-sm" data-design-proxy="settings.gap"' . $proxyDefault('settings.gap') . '>')
                . $field('design.field.captions', '<select class="form-select form-select-sm" data-design-proxy="settings.showCaptions"' . $proxyDefault('settings.showCaptions') . '>' . $booleanOptions . '</select>')
                . $field('design.field.alignment', '<select class="form-select form-select-sm" data-design-proxy="settings.captionAlign"' . $proxyDefault('settings.captionAlign') . '><option value="left">' . $this->label('flexform.captionAlign.left') . '</option><option value="center">' . $this->label('flexform.captionAlign.center') . '</option><option value="right">' . $this->label('flexform.captionAlign.right') . '</option></select>'),
            'lightbox' => $field('design.field.enabled', '<select class="form-select form-select-sm" data-design-proxy="settings.enableLightbox"' . $proxyDefault('settings.enableLightbox') . '>' . $booleanOptions . '</select>'),
            'loadMore' => $field('design.field.enabled', '<select class="form-select form-select-sm" data-design-proxy="settings.enableLoadMore"' . $proxyDefault('settings.enableLoadMore') . '>' . $booleanOptions . '</select>')
                . $field('design.field.buttonFrame', '<select class="form-select form-select-sm" data-design-proxy="settings.loadMoreUseFrameStyle"' . $proxyDefault('settings.loadMoreUseFrameStyle') . '>' . $booleanOptions . '</select>'),
            default => '',
        };
    }

    /** @param array<string, mixed> $settings */
    private function proxyDefaultAttribute(string $fieldName, array $settings): string
    {
        $value = $this->resolveProxyDefaultValue($fieldName, $settings);
        if ($value === null) {
            return '';
        }

        return ' data-design-proxy-default="' . htmlspecialchars($value, ENT_QUOTES) . '"';
    }

    /**
     * Resolve the server-side value that Design Configurator proxies should initialize from.
     *
     * Priority:
     * 1. Explicit processed settings / FlexForm vDEF (authoritative for existing records)
     * 2. For command=new only: processed FlexForm DS config.default
     *
     * Boolean "0" is a valid value and must not be treated as missing.
     *
     * @param array<string, mixed> $settings
     */
    private function resolveProxyDefaultValue(string $fieldName, array $settings): ?string
    {
        $key = substr($fieldName, 9);
        if (array_key_exists($key, $settings)) {
            $normalized = $this->normalizeProxyScalar($settings[$key]);
            // Keep "0"; only treat null/empty-string as absent so DS defaults can apply on new records.
            if ($normalized !== null && $normalized !== '') {
                return $normalized;
            }
            if ($normalized === '0') {
                return '0';
            }
        }

        $vDef = $this->resolveFlexFormVDefRaw($fieldName);
        if ($vDef !== null && $vDef !== '') {
            return $vDef;
        }
        if ($vDef === '0') {
            return '0';
        }

        if (($this->data['command'] ?? '') !== 'new') {
            return null;
        }

        return $this->resolveProcessedDataStructureDefault($fieldName);
    }

    private function normalizeProxyScalar(mixed $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_string($value)) {
            return $value;
        }
        $scalar = $this->scalarValue($value);
        if ($scalar === null || $scalar === [] || is_array($scalar)) {
            return null;
        }
        if (is_bool($scalar)) {
            return $scalar ? '1' : '0';
        }

        return (string)$scalar;
    }

    private function resolveFlexFormVDefRaw(string $fieldName): ?string
    {
        $flexForm = $this->data['databaseRow']['pi_flexform'] ?? '';
        if (!is_array($flexForm)) {
            return null;
        }

        $sheet = $this->flexFormSheetForField($fieldName);
        if (!is_array($flexForm['data'][$sheet]['lDEF'][$fieldName] ?? null)) {
            return null;
        }
        if (!array_key_exists('vDEF', $flexForm['data'][$sheet]['lDEF'][$fieldName])) {
            return null;
        }

        return $this->normalizeProxyScalar($flexForm['data'][$sheet]['lDEF'][$fieldName]['vDEF']);
    }

    private function resolveProcessedDataStructureDefault(string $fieldName): ?string
    {
        $sheet = $this->flexFormSheetForField($fieldName);
        $default = $this->data['processedTca']['columns']['pi_flexform']['config']['ds']['sheets'][$sheet]['ROOT']['el'][$fieldName]['config']['default']
            ?? null;

        return $this->normalizeProxyScalar($default);
    }

    private function flexFormSheetForField(string $fieldName): string
    {
        return str_starts_with($fieldName, 'settings.design') || str_starts_with($fieldName, 'settings.frame')
            || str_starts_with($fieldName, 'settings.border')
            || str_starts_with($fieldName, 'settings.shadow')
            || str_starts_with($fieldName, 'settings.background')
            || str_starts_with($fieldName, 'settings.captionColor')
            || str_starts_with($fieldName, 'settings.applyTo')
            || str_starts_with($fieldName, 'settings.lb')
            ? 'sDESIGN'
            : 'sDEF';
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
                    ? '<button type="button" class="btn btn-default btn-sm mosaic-design-eyedropper"'
                        . ' data-design-eyedropper data-mosaic-action-tooltip hidden aria-label="'
                        . $this->label('design.configurator.eyedropper') . '">' . $this->actionIcon('eyedropper') . '</button>'
                    : '');
        }

        return '<div class="mosaic-design-configurator__field" data-design-field="'
            . htmlspecialchars($path, ENT_QUOTES) . '"><label class="form-label">' . $this->label($control['label'])
            . '</label><div class="mosaic-design-configurator__control'
            . ($type === 'color' ? '" data-mosaic-color-control-row' : '"')
            . '>' . $controlHtml
            . '<button type="button" class="btn btn-default btn-sm mosaic-design-reset-field"'
            . ' data-design-reset-field data-mosaic-action-tooltip aria-label="'
            . $this->label('design.configurator.reset') . '"'
            . ($modified ? '' : ' disabled hidden') . '>' . $this->actionIcon('reset') . '</button></div></div>';
    }

    private function actionIcon(string $icon): string
    {
        $path = $icon === 'eyedropper'
            ? '<path d="m15.5 3.5 5 5-9.5 9.5-5.5.5.5-5.5z"/><path d="m13 6 5 5M4 20h7"/>'
            : '<path d="M4 8v5h5"/><path d="M5.5 12a7 7 0 1 0 2-5"/><path d="M4 8l3.5-3"/>';

        return '<svg class="mosaic-backend-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            . $path . '</svg>';
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
            $settings = [];
            foreach ($flexForm['settings'] as $key => $value) {
                $settings[$key] = $this->unwrapFormEngineSettingValue($value);
            }
            return $settings;
        }

        $settings = [];
        foreach (($flexForm['data'] ?? []) as $sheet) {
            foreach (($sheet['lDEF'] ?? []) as $fieldName => $fieldValue) {
                if (!str_starts_with((string)$fieldName, 'settings.')) {
                    continue;
                }
                $value = $fieldValue['vDEF'] ?? '';
                $settings[substr((string)$fieldName, 9)] = $value === []
                    ? ''
                    : $this->scalarValue($value);
            }
        }
        return $settings;
    }

    /**
     * Unwrap single-value TYPO3 FormEngine FlexForm wrappers only.
     * Multi-value lists and structured documents are left unchanged.
     */
    private function unwrapFormEngineSettingValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if ($value === []) {
            return '';
        }
        if ($this->isVDefFormEngineWrapper($value)) {
            return $this->unwrapFormEngineSettingValue($value['vDEF']);
        }
        if ($this->isSingleValueFormEngineWrapper($value)) {
            return $this->unwrapFormEngineSettingValue($value[0]);
        }
        return $value;
    }

    /** @param array<string|int, mixed> $value */
    private function isVDefFormEngineWrapper(array $value): bool
    {
        if (!array_key_exists('vDEF', $value)) {
            return false;
        }
        foreach (array_keys($value) as $key) {
            if ($key !== 'vDEF' && $key !== '_TRANSFORM_') {
                return false;
            }
        }
        return true;
    }

    /** @param array<string|int, mixed> $value */
    private function isSingleValueFormEngineWrapper(array $value): bool
    {
        if (!array_key_exists(0, $value)) {
            return false;
        }
        foreach (array_keys($value) as $key) {
            if ($key !== 0 && $key !== '_TRANSFORM_') {
                return false;
            }
        }
        return true;
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
