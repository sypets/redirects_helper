<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Redirects helper',
    'description' => '',
    'category' => 'module',
    'author' => 'Sybille Peters',
    'author_email' => 'sypets@gmx.de',
    'author_company' => '',
    'state' => 'beta',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.3-dev',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.9.999',
            'redirects' => '10.4.0-10.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
