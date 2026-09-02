<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Backend\Permission;

/**
 * Canonical FlexForm field → backend user-group restriction mapping for Issue #11.
 *
 * Unchecked custom permission = field remains available (opt-in deny).
 * Checked custom permission = field hidden in FormEngine for non-admin editors.
 */
final class MosaicGalleryFlexFormPermissionDefinition
{
    public const CATEGORY_GENERAL = 'tx_anatolkin_mosaic_gallery_general';
    public const CATEGORY_DESIGN = 'tx_anatolkin_mosaic_gallery_design';
    public const ICON_IDENTIFIER = 'mosaic-gallery-extension';

    private const LANGUAGE_FILE =
        'LLL:EXT:anatolkin_mosaic_gallery/Resources/Private/Language/locallang_be.xlf:';

    /** @var array<string, array{sheet: string, category: string, key: string, hideLabel: string}> */
    private const FIELD_MAP = [
        'settings.source' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_source',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.source',
        ],
        'settings.folder' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_folder',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.folder',
        ],
        'settings.recursive' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_recursive',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.recursive',
        ],
        'settings.sortBy' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_sort_by',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.sortBy',
        ],
        'settings.sortDir' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_sort_dir',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.sortDir',
        ],
        'settings.gap' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_gap',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.gap',
        ],
        'settings.layoutMode' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_layout_mode',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.layoutMode',
        ],
        'settings.maxItemsPerRow' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_max_items_per_row',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.maxItemsPerRow',
        ],
        'settings.maxWidth' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_max_width',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.maxWidth',
        ],
        'settings.enableLightbox' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_enable_lightbox',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.enableLightbox',
        ],
        'settings.showCaptions' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_show_captions',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.showCaptions',
        ],
        'settings.captionAlign' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_caption_align',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.captionAlign',
        ],
        'settings.useFalCaptions' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_use_fal_captions',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.useFalCaptions',
        ],
        'settings.enableLoadMore' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_enable_load_more',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.enableLoadMore',
        ],
        'settings.loadMoreUseFrameStyle' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_load_more_use_frame_style',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.loadMoreUseFrameStyle',
        ],
        'settings.itemsPerPage' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_items_per_page',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.itemsPerPage',
        ],
        'settings.loadStep' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_load_step',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.loadStep',
        ],
        'settings.captions' => [
            'sheet' => 'sDEF',
            'category' => self::CATEGORY_GENERAL,
            'key' => 'hide_captions',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.captions',
        ],
        'settings.designPreset' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_design_preset',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.designPreset',
        ],
        'settings.frameColor' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_frame_color',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.frameColor',
        ],
        'settings.frameAccentColor' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_frame_accent_color',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.frameAccentColor',
        ],
        'settings.frameWidth' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_frame_width',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.frameWidth',
        ],
        'settings.frameStyle' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_frame_style',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.frameStyle',
        ],
        'settings.borderRadius' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_border_radius',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.borderRadius',
        ],
        'settings.shadow' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_shadow',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.shadow',
        ],
        'settings.backgroundColor' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_background_color',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.backgroundColor',
        ],
        'settings.captionColor' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_caption_color',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.captionColor',
        ],
        'settings.applyTo' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_apply_to',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.applyTo',
        ],
        'settings.lbOverlay' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_lb_overlay',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.lbOverlay',
        ],
        'settings.lbOverlayAlpha' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_lb_overlay_alpha',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.lbOverlayAlpha',
        ],
        'settings.lbNavColor' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_lb_nav_color',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.lbNavColor',
        ],
        'settings.lbCloseColor' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_lb_close_color',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.lbCloseColor',
        ],
        'settings.lbCaptionColor' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_lb_caption_color',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.lbCaptionColor',
        ],
        'settings.lbCaptionBg' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_lb_caption_bg',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.lbCaptionBg',
        ],
        'settings.lbCaptionBgAlpha' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_lb_caption_bg_alpha',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.lbCaptionBgAlpha',
        ],
        'settings.lbCaptionAlign' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_lb_caption_align',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.lbCaptionAlign',
        ],
        'settings.lbCaptionSize' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_lb_caption_size',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.lbCaptionSize',
        ],
        'settings.lbCaptionStyle' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_lb_caption_style',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.lbCaptionStyle',
        ],
        'settings.designOverrides' => [
            'sheet' => 'sDESIGN',
            'category' => self::CATEGORY_DESIGN,
            'key' => 'hide_design_overrides',
            'hideLabel' => self::LANGUAGE_FILE . 'permissions.hide.settings.designOverrides',
        ],
    ];

    /**
     * @return array<string, array{sheet: string, category: string, key: string, hideLabel: string}>
     */
    public static function fieldMap(): array
    {
        return self::FIELD_MAP;
    }

    /**
     * @return array<string, array{header: string, items: array<string, list<string>>}>
     */
    public static function customPermOptionCategories(): array
    {
        $categories = [
            self::CATEGORY_GENERAL => [
                'header' => self::LANGUAGE_FILE . 'permissions.category.general',
                'items' => [],
            ],
            self::CATEGORY_DESIGN => [
                'header' => self::LANGUAGE_FILE . 'permissions.category.design',
                'items' => [],
            ],
        ];

        foreach (self::FIELD_MAP as $mapping) {
            $categories[$mapping['category']]['items'][$mapping['key']] = [
                $mapping['hideLabel'],
                self::ICON_IDENTIFIER,
            ];
        }

        return $categories;
    }

    public static function permissionIdentifier(string $category, string $key): string
    {
        return $category . ':' . $key;
    }

    public static function isValidPermissionKey(string $key): bool
    {
        return $key !== '' && !str_contains($key, ':') && !str_contains($key, ';');
    }
}
