<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Anatolkin Mosaic Gallery',
    'description' => 'Flexible FAL image galleries for TYPO3 with multiple responsive layouts, design controls, metadata tools, and optional GLightbox.',
    'category' => 'plugin',
    'author' => 'Sergey Fedorov',
    'author_email' => 'typo3@anatolkin.com',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '0.4.3',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
            'php'   => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests'  => [],
    ],
];

