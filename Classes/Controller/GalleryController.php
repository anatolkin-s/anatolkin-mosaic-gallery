<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Controller;

use Anatolkin\MosaicGallery\Service\DesignPresetResolver;
use Anatolkin\MosaicGallery\Service\GalleryMetadataOverrideResolver;
use Anatolkin\MosaicGallery\Service\GalleryImageSorter;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

final class GalleryController extends ActionController
{
    public function __construct(
        private readonly DesignPresetResolver $designPresetResolver,
    ) {
    }

    public function listAction(): ResponseInterface
    {
        $assets = GeneralUtility::makeInstance(AssetCollector::class);
        $assets->addStyleSheet(
            'mosaic-css',
            'EXT:anatolkin_mosaic_gallery/Resources/Public/Css/mosaic.css'
        );
        $assets->addJavaScript(
            'imagesloaded',
            'EXT:anatolkin_mosaic_gallery/Resources/Public/Js/imagesloaded.pkgd.min.js',
            ['defer' => true]
        );
        $assets->addJavaScript(
            'masonry',
            'EXT:anatolkin_mosaic_gallery/Resources/Public/Js/masonry.pkgd.min.js',
            ['defer' => true]
        );
        $assets->addJavaScript(
            'mosaic-init',
            'EXT:anatolkin_mosaic_gallery/Resources/Public/Js/mosaic-init.js',
            ['defer' => true]
        );

        $enableLightbox = (bool)($this->settings['enableLightbox'] ?? true);
        if ($enableLightbox) {
            $assets->addStyleSheet(
                'glightbox-css',
                'EXT:anatolkin_mosaic_gallery/Resources/Public/Css/glightbox.min.css'
            );
            $assets->addJavaScript(
                'glightbox-js',
                'EXT:anatolkin_mosaic_gallery/Resources/Public/Js/glightbox.min.js',
                ['defer' => true]
            );
        }

        // Settings
        $source    = (string)($this->settings['source'] ?? 'folder');
        $folderIn  = (string)($this->settings['folder'] ?? 'fileadmin/gallery/');
        $recursive = (bool)($this->settings['recursive'] ?? true);
        $gapValue  = trim((string)($this->settings['gap'] ?? ''));
        $gap       = $gapValue === '' ? 12 : (int)$gapValue;
        $maxWidth  = max(200, (int)($this->settings['maxWidth'] ?? 1800));
        $sortBy    = (string)($this->settings['sortBy'] ?? 'name');   // name|mtime|random
        $sortDir   = (string)($this->settings['sortDir'] ?? 'asc');   // asc|desc
        $layoutMode = (string)($this->settings['layoutMode'] ?? 'masonry');
        if (!\in_array($layoutMode, ['masonry', 'mosaic', 'grid'], true)) {
            $layoutMode = 'masonry';
        }

        $showCaptions   = (bool)($this->settings['showCaptions'] ?? true);
        $useFalCaptions = (bool)($this->settings['useFalCaptions'] ?? true);
        $captionAlign   = (string)($this->settings['captionAlign'] ?? 'left');
        if (!\in_array($captionAlign, ['left', 'center', 'right'], true)) {
            $captionAlign = 'left';
        }

        $design = $this->designPresetResolver->resolve($this->settings);
        $frameBands = $this->resolveFrameBands($design['frameWidth']);

        $enableLoadMore = (bool)($this->settings['enableLoadMore'] ?? true);
        $itemsPerPage   = max(1, (int)($this->settings['itemsPerPage'] ?? 12));
        $loadStep       = max(1, (int)($this->settings['loadStep'] ?? $itemsPerPage));

        $items   = [];
        $groupId = 'mosaic-' . $this->resolveContentUid();

        if ($source === 'folder') {
            try {
                $rf     = GeneralUtility::makeInstance(ResourceFactory::class);
                $folder = $rf->getFolderObjectFromCombinedIdentifier(
                    $this->toCombinedIdentifier($folderIn)
                );
                $files  = $this->collectFiles($folder, $recursive);
                $metadataDocument = GeneralUtility::makeInstance(GalleryMetadataOverrideResolver::class)
                    ->decodeDocument($this->resolveMetadataOverridesValue());
                $metadataOverrides = $metadataDocument['files'];
                $legacyCaptionsConverted = $metadataDocument['legacyCaptionsConverted'];
                if ($sortBy === 'random') {
                    shuffle($files);
                } else {
                    $files = GeneralUtility::makeInstance(GalleryImageSorter::class)
                        ->sortDeterministically($files, $sortBy, $sortDir);
                }

                $lines = $this->splitLines((string)($this->settings['captions'] ?? ''));

                foreach ($files as $idx => $file) {
                    try {
                        $meta = $useFalCaptions ? $this->getLocalizedMeta($file) : [];
                    } catch (\Throwable $e) {
                        $meta = [];
                    }

                    // Safe access to FAL metadata: all keys are optional.
                    $title       = $meta['title'] ?? '';
                    $captionMeta = $meta['caption'] ?? '';
                    $description = $meta['description'] ?? '';

                    $caption = $useFalCaptions
                        ? ($title !== '' ? $title : ($captionMeta !== '' ? $captionMeta : $description))
                        : ($legacyCaptionsConverted ? '' : ($lines[$idx] ?? ''));

                    $alt = ($meta['alternative'] ?? '') ?: $caption;

                    $fileOverride = $metadataOverrides[(string)$file->getUid()] ?? [];
                    if (($fileOverride['caption']['mode'] ?? null) === 'custom') {
                        $caption = $fileOverride['caption']['value'];
                    }
                    if (($fileOverride['alt']['mode'] ?? null) === 'custom') {
                        $alt = $fileOverride['alt']['value'];
                    } elseif (($fileOverride['alt']['mode'] ?? null) === 'empty') {
                        $alt = '';
                    }

                    $items[] = [
                        'file'    => $file,
                        'caption' => (string)$caption,
                        'alt'     => (string)$alt,
                        'hidden'  => ($enableLoadMore && $idx >= $itemsPerPage),
                        'layoutSpan' => $this->resolveLayoutSpan($file, $layoutMode),
                    ];
                }
            } catch (\Throwable $e) {
                // Fail silently for this content element instead of breaking the whole page.
            }
        }

        $hasMore = $enableLoadMore && \count($items) > $itemsPerPage;

        $this->view->assignMultiple([
            'items'          => $items,
            'gap'            => $gap,
            'maxWidth'       => $maxWidth,
            'layoutMode'     => $layoutMode,
            'showCaptions'   => $showCaptions,
            'captionAlign'   => $captionAlign,
            'design'         => $design,
            'frameBands'     => $frameBands,
            'enableLightbox' => $enableLightbox,
            'galleryGroup'   => $groupId,
            'enableLoadMore' => $enableLoadMore,
            'itemsPerPage'   => $itemsPerPage,
            'loadStep'       => $loadStep,
            'hasMore'        => $hasMore,
        ]);

        return $this->htmlResponse();
    }

    private function resolveLayoutSpan(File $file, string $layoutMode): string
    {
        if ($layoutMode !== 'mosaic') {
            return 'normal';
        }

        try {
            $width = (int)$file->getProperty('width');
            $height = (int)$file->getProperty('height');
            // A 1.6 ratio reserves two-column spans for clearly wide images rather than ordinary landscapes.
            return $width > 0 && $height > 0 && ($width / $height) >= 1.6 ? 'wide' : 'normal';
        } catch (\Throwable) {
            return 'normal';
        }
    }

    /** @return array{key: string, quarter: string, third: string, forty: string, fortyFive: string, sixty: string, twoThirds: string, threeQuarters: string, total: string} */
    private function resolveFrameBands(mixed $frameWidth): array
    {
        $width = max(0.0, (float)$frameWidth);

        return [
            'key' => $this->formatFrameBand($width === 0.0 ? 0.0 : min($width, max(1.0, $width * 0.1))),
            'quarter' => $this->formatFrameBand($width * 0.25),
            'third' => $this->formatFrameBand($width / 3),
            'forty' => $this->formatFrameBand($width * 0.4),
            'fortyFive' => $this->formatFrameBand($width * 0.45),
            'sixty' => $this->formatFrameBand($width * 0.6),
            'twoThirds' => $this->formatFrameBand($width * 2 / 3),
            'threeQuarters' => $this->formatFrameBand($width * 0.75),
            'total' => $this->formatFrameBand($width),
        ];
    }

    private function formatFrameBand(float $width): string
    {
        return rtrim(rtrim(sprintf('%.4F', $width), '0'), '.');
    }

    private function resolveContentUid(): int
    {
        $cObj = $this->request->getAttribute('currentContentObject');
        if ($cObj instanceof ContentObjectRenderer) {
            return (int)($cObj->data['uid'] ?? 0);
        }
        return 0;
    }

    private function resolveMetadataOverridesValue(): string
    {
        $cObj = $this->request->getAttribute('currentContentObject');
        if ($cObj instanceof ContentObjectRenderer) {
            $contentRow = $cObj->data;
            try {
                $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
                if ($languageAspect instanceof LanguageAspect) {
                    $overlaidRow = GeneralUtility::makeInstance(PageRepository::class)
                        ->getLanguageOverlay('tt_content', $contentRow, $languageAspect);
                    if (is_array($overlaidRow)) {
                        $contentRow = $overlaidRow;
                    }
                }
            } catch (\Throwable) {
                // Keep the current content-object row when an overlay cannot be resolved.
            }

            return (string)($contentRow['tx_anatolkinmosaicgallery_metadata_overrides'] ?? '');
        }
        return '';
    }

    private function collectFiles(Folder $folder, bool $recursive): array
    {
        $result  = [];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'];

        foreach ($folder->getFiles() as $file) {
            /** @var File $file */
            $ext = strtolower((string)$file->getExtension());
            if (\in_array($ext, $allowed, true)) {
                $result[] = $file;
            }
        }

        if ($recursive) {
            foreach ($folder->getSubfolders() as $sub) {
                $result = array_merge($result, $this->collectFiles($sub, true));
            }
        }

        return $result;
    }

    /**
     * Returns localized FAL metadata for a given file.
     *
     * Strategy:
     * 1) Fetch base record (sys_language_uid = 0) by file UID.
     * 2) If current language > 0, look for an overlay (l10n_parent + sys_language_uid).
     * 3) Merge overlay on top of base; empty strings in overlay do not overwrite base values.
     *
     * Any DB/context error falls back to base or an empty metadata array.
     */
    private function getLocalizedMeta(File $file): array
    {
        $ctx    = GeneralUtility::makeInstance(Context::class);
        $langId = (int)$ctx->getPropertyFromAspect('language', 'id');

        $qb   = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_metadata');
        $base = $qb->select('*')
            ->from('sys_file_metadata')
            ->where(
                $qb->expr()->eq(
                    'file',
                    $qb->createNamedParameter($file->getUid(), \PDO::PARAM_INT)
                ),
                $qb->expr()->eq('sys_language_uid', 0)
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative() ?: [];

        if ($langId > 0 && !empty($base['uid'])) {
            $qb2     = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_file_metadata');
            $overlay = $qb2->select('*')
                ->from('sys_file_metadata')
                ->where(
                    $qb2->expr()->eq(
                        'l10n_parent',
                        $qb2->createNamedParameter((int)$base['uid'], \PDO::PARAM_INT)
                    ),
                    $qb2->expr()->eq(
                        'sys_language_uid',
                        $qb2->createNamedParameter($langId, \PDO::PARAM_INT)
                    )
                )
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative() ?: [];

            if ($overlay) {
                // Overlay extends base metadata; empty strings do not overwrite base values.
                foreach ($overlay as $k => $v) {
                    if (\is_string($v) && $v !== '') {
                        $base[$k] = $v;
                    }
                }
            }
        }

        return [
            'title'       => (string)($base['title'] ?? ''),
            'description' => (string)($base['description'] ?? ''),
            'alternative' => (string)($base['alternative'] ?? ''),
            'caption'     => (string)($base['caption'] ?? ''),
            'copyright'   => (string)($base['copyright'] ?? ''),
        ];
    }

    private function splitLines(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        return array_values(
            array_filter(
                $lines,
                static fn($v) => $v !== null
            )
        );
    }

    private function toCombinedIdentifier(string $input): string
    {
        if (preg_match('#^\d+:/#', $input)) {
            return rtrim($input, '/') . '/';
        }

        $path = preg_replace('#^fileadmin/?#', '', $input);
        $path = '/' . trim((string)$path, '/') . '/';

        return '1:' . $path;
    }
}
