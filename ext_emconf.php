<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Anatolkin Mosaic Gallery',
    'description' => 'Flexible FAL image galleries for TYPO3 13.4/14.3 with responsive layouts, design controls, metadata tools and optional GLightbox. Install via Composer or TER; after installation or update run `php vendor/bin/typo3 extension:setup` on Composer installations and add the Anatolkin Mosaic Gallery Site Set (or use the legacy TypoScript integration).',
    'category' => 'plugin',
    'author' => 'Sergey Fedorov',
    'author_email' => 'typo3@anatolkin.com',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '0.5.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
            'php'   => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests'  => [],
    ],
];

