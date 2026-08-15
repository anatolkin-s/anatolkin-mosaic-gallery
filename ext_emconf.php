<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Anatolkin Mosaic Gallery',
    'description' => 'Mosaic image gallery for TYPO3 using FAL with optional GLightbox.',
    'category' => 'plugin',
    'author' => 'Sergey Fedorov',
    'author_email' => 'typo3@anatolkin.com',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '0.3.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
            'php'   => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests'  => [],
    ],
];

