<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Anatolkin Mosaic Gallery',
    'description' => 'Flexible FAL image galleries for TYPO3 13.4/14.3 with responsive layouts, design controls, metadata tools and optional GLightbox. Composer install: composer require anatolkin/anatolkin-mosaic-gallery:^0.5, then php vendor/bin/typo3 extension:setup; add the Anatolkin Mosaic Gallery Site Set under Site Management > Sites > Sets for this Site, or use the documented legacy TypoScript integration (use one integration method only). Composer update: composer update anatolkin/anatolkin-mosaic-gallery -W, then run php vendor/bin/typo3 extension:setup; existing integration normally remains active, flush caches if needed, and verify backend editing and frontend output. Classic TER/Extension Manager installation is also supported without Composer commands. No database migration is required for 0.5.1.',
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

