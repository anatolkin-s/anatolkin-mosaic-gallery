<?php
declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'mosaic-gallery-extension' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:anatolkin_mosaic_gallery/Resources/Public/Icons/Extension.svg',
    ],
    'mosaic-gallery-plugin' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:anatolkin_mosaic_gallery/Resources/Public/Icons/PluginMosaic.svg',
    ],
];
