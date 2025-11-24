<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

final class GalleryController extends ActionController
{
    public function listAction(): ResponseInterface
    {
        $assets = GeneralUtility::makeInstance(AssetCollector::class);
        $assets->addStyleSheet('mosaic-css', 'EXT:anatolkin_mosaic_gallery/Resources/Public/Css/mosaic.css');
        $assets->addJavaScript('imagesloaded', 'EXT:anatolkin_mosaic_gallery/Resources/Public/Js/imagesloaded.pkgd.min.js', ['defer' => true]);
        $assets->addJavaScript('masonry', 'EXT:anatolkin_mosaic_gallery/Resources/Public/Js/masonry.pkgd.min.js', ['defer' => true]);
        $assets->addJavaScript('mosaic-init', 'EXT:anatolkin_mosaic_gallery/Resources/Public/Js/mosaic-init.js', ['defer' => true]);

        $enableLightbox = (bool)($this->settings['enableLightbox'] ?? true);
        if ($enableLightbox) {
            $assets->addStyleSheet('glightbox-css', 'EXT:anatolkin_mosaic_gallery/Resources/Public/Css/glightbox.min.css');
            $assets->addJavaScript('glightbox-js', 'EXT:anatolkin_mosaic_gallery/Resources/Public/Js/glightbox.min.js', ['defer' => true]);
        }

        // settings
        $source    = (string)($this->settings['source'] ?? 'folder');
        $folderIn  = (string)($this->settings['folder'] ?? 'fileadmin/gallery/');
        $recursive = (bool)($this->settings['recursive'] ?? true);
        $gap       = (int)($this->settings['gap'] ?? 12);
        $maxWidth  = max(200, (int)($this->settings['maxWidth'] ?? 1200));
        $sortBy    = (string)($this->settings['sortBy'] ?? 'name');   // name|mtime|random
        $sortDir   = (string)($this->settings['sortDir'] ?? 'asc');   // asc|desc

        $showCaptions   = (bool)($this->settings['showCaptions'] ?? true);
        $useFalCaptions = (bool)($this->settings['useFalCaptions'] ?? true);

        $borderRadius= max(0, (int)($this->settings['borderRadius'] ?? 6));
        $shadow      = (bool)($this->settings['shadow'] ?? false);
        $background  = (string)($this->settings['background'] ?? '');

        $enableLoadMore = (bool)($this->settings['enableLoadMore'] ?? true);
        $itemsPerPage   = max(1, (int)($this->settings['itemsPerPage'] ?? 12));
        $loadStep       = max(1, (int)($this->settings['loadStep'] ?? $itemsPerPage));

        $items = [];
        $groupId = 'mosaic-' . $this->resolveContentUid();

        if ($source === 'folder') {
            try {
                $rf = GeneralUtility::makeInstance(ResourceFactory::class);
                $folder = $rf->getFolderObjectFromCombinedIdentifier($this->toCombinedIdentifier($folderIn));
                $files  = $this->collectFiles($folder, $recursive);
                $files  = $this->sortFiles($files, $sortBy, $sortDir);

                $lines = $this->splitLines((string)($this->settings['captions'] ?? ''));
                foreach ($files as $idx => $file) {
                    try {
                        $meta = $useFalCaptions ? $this->getLocalizedMeta($file) : [];
                    } catch (\Throwable $e) {
                        $meta = [];
                    }
                    $caption = $useFalCaptions
                        ? ($meta['title'] ?: ($meta['caption'] ?? '') ?: ($meta['description'] ?? ''))
                        : ($lines[$idx] ?? '');
                    $alt = ($meta['alternative'] ?? '') ?: $caption;

                    $items[] = [
                        'file'    => $file,
                        'caption' => (string)$caption,
                        'alt'     => (string)$alt,
                        'hidden'  => ($enableLoadMore && $idx >= $itemsPerPage),
                    ];
                }
            } catch (\Throwable $e) {
                // оставляем пустую галерею, чтобы не ронять страницу
            }
        }

        $hasMore = $enableLoadMore && \count($items) > $itemsPerPage;

        $this->view->assignMultiple([
            'items'          => $items,
            'gap'            => $gap,
            'maxWidth'       => $maxWidth,
            'showCaptions'   => $showCaptions,
            'borderRadius'   => $borderRadius,
            'shadow'         => $shadow,
            'background'     => $background,
            'enableLightbox' => $enableLightbox,
            'galleryGroup'   => $groupId,
            'enableLoadMore' => $enableLoadMore,
            'itemsPerPage'   => $itemsPerPage,
            'loadStep'       => $loadStep,
            'hasMore'        => $hasMore,
        ]);

        return $this->htmlResponse();
    }

    private function resolveContentUid(): int
    {
        $cObj = $this->request->getAttribute('currentContentObject');
        if ($cObj instanceof ContentObjectRenderer) {
            return (int)($cObj->data['uid'] ?? 0);
        }
        return 0;
    }

    private function collectFiles(Folder $folder, bool $recursive): array
    {
        $result = [];
        $allowed = ['jpg','jpeg','png','gif','webp','bmp','tif','tiff'];
        foreach ($folder->getFiles() as $file) {
            /** @var File $file */
            $ext = strtolower((string)$file->getExtension());
            if (in_array($ext, $allowed, true)) {
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

    private function sortFiles(array $files, string $by, string $dir): array
    {
        if ($by === 'random') {
            shuffle($files);
            return $files;
        }
        usort($files, static function (File $a, File $b) use ($by, $dir) {
            if ($by === 'mtime') {
                $av = (int)($a->getProperty('modification_date') ?? 0);
                $bv = (int)($b->getProperty('modification_date') ?? 0);
            } else {
                $av = strtolower($a->getName());
                $bv = strtolower($b->getName());
            }
            $cmp = $av <=> $bv;
            return $dir === 'desc' ? -$cmp : $cmp;
        });
        return $files;
    }

    /**
     * Безопасная локализация метаданных:
     * 1) Берём базовую запись (sys_language_uid=0) по file
     * 2) Если есть язык > 0, ищем overlay по l10n_parent и sys_language_uid
     * 3) Мерджим overlay поверх базы. Любая ошибка — тихий фоллбэк.
     */
    private function getLocalizedMeta(File $file): array
    {
        $ctx = GeneralUtility::makeInstance(Context::class);
        $langId = (int)$ctx->getPropertyFromAspect('language', 'id');

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_metadata');
        $base = $qb->select('*')
            ->from('sys_file_metadata')
            ->where(
                $qb->expr()->eq('file', $qb->createNamedParameter($file->getUid(), \PDO::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', 0)
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative() ?: [];

        if ($langId > 0 && !empty($base['uid'])) {
            $qb2 = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_metadata');
            $overlay = $qb2->select('*')
                ->from('sys_file_metadata')
                ->where(
                    $qb2->expr()->eq('l10n_parent', $qb2->createNamedParameter((int)$base['uid'], \PDO::PARAM_INT)),
                    $qb2->expr()->eq('sys_language_uid', $qb2->createNamedParameter($langId, \PDO::PARAM_INT))
                )
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative() ?: [];

            if ($overlay) {
                // overlay дополняет базу, пустые строки не перетирают
                foreach ($overlay as $k => $v) {
                    if (is_string($v) && $v !== '') {
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
        return array_values(array_filter($lines, static fn($v) => $v !== null));
    }

    private function toCombinedIdentifier(string $input): string
    {
        if (preg_match('#^\d+:/#', $input)) {
            return rtrim($input, '/') . '/';
        }
        $path = preg_replace('#^fileadmin/?#', '', $input);
        $path = '/' . trim($path, '/') . '/';
        return '1:' . $path;
    }
}
