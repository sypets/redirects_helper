<?php

defined('TYPO3_MODE') or die();

// Add field to sys_redirect table
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
    'sys_redirect',
    [
        'protected' => [
            'exclude' => true,
            'label' => 'LLL:EXT:redirects_helper/Resources/Private/Language/locallang_db.xlf:sys_redirect.protected',
            'description' => 'LLL:EXT:redirects_helper/Resources/Private/Language/locallang_db.xlf:sys_redirect.protected.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        0 => '',
                        1 => '',
                    ]
                ],
            ]
        ]
    ]
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'sys_redirect',
    'protected'
);
