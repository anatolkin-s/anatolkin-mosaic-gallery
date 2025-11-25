<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Anatolkin Mosaic Gallery',
    'description' => 'Masonry-like image gallery for TYPO3 using FAL with optional GLightbox.',
    'category' => 'plugin',
    'author' => 'Sergey Fedorov',
    'author_email' => 'typo3@anatolkin.com',
    'state' => 'beta',
    'clearCacheOnLoad' => 1,
    'version' => '0.1.8',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.99.99',
            'php'   => '8.1.0-8.3.99',
        ],
        'conflicts' => [],
        'suggests'  => [],
    ],
];

