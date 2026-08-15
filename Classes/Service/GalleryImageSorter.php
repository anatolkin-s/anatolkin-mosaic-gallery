<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

use TYPO3\CMS\Core\Resource\File;

final class GalleryImageSorter
{
    /**
     * @param list<File> $files
     * @return list<File>
     */
    public function sortDeterministically(array $files, string $by, string $dir): array
    {
        usort(
            $files,
            static function (File $a, File $b) use ($by, $dir) {
                if ($by === 'mtime') {
                    $av = (int)($a->getProperty('modification_date') ?? 0);
                    $bv = (int)($b->getProperty('modification_date') ?? 0);
                } else {
                    $av = strtolower($a->getName());
                    $bv = strtolower($b->getName());
                }

                $cmp = $av <=> $bv;
                return $dir === 'desc' ? -$cmp : $cmp;
            },
        );

        return $files;
    }
}
