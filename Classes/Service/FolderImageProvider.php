<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;

final class FolderImageProvider
{
    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'bmp',
        'tif',
        'tiff',
    ];

    public function __construct(
        private readonly ResourceFactory $resourceFactory,
    ) {
    }

    /**
     * @return list<File>
     */
    public function getImages(string $folderInput, bool $recursive): array
    {
        if (trim($folderInput) === '') {
            return [];
        }

        $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier(
            $this->toCombinedIdentifier($folderInput),
        );

        return $this->collectFiles($folder, $recursive);
    }

    /**
     * @return list<File>
     */
    private function collectFiles(Folder $folder, bool $recursive): array
    {
        $result = [];

        foreach ($folder->getFiles() as $file) {
            $extension = strtolower((string)$file->getExtension());
            if (in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                $result[] = $file;
            }
        }

        if ($recursive) {
            foreach ($folder->getSubfolders() as $subfolder) {
                $result = array_merge($result, $this->collectFiles($subfolder, true));
            }
        }

        return $result;
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
