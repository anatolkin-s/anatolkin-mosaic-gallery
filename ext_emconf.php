<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Mosaic Gallery',
    'description' => 'Masonry-like image gallery for TYPO3 using FAL with optional lightbox.',
    'category' => 'plugin',
    'author' => 'Sergey Fedorov',
    'author_email' => 'sfedorov@emsintl.com',
    'state' => 'beta',
    'clearCacheOnLoad' => 1,
    'version' => '0.1.4',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.99.99',
            'php'   => '8.1.0-8.3.99'
        ],
        'conflicts' => [],
        'suggests' => []
    ]
];

