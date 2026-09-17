<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\FileType;

defined('TYPO3') || die();

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations']['contentStorage'] = [
    'endpoint' => 'http://minio:10101',
    'use_path_style_endpoint' => true,
    'bucket' => 'typo3-' . (new Typo3Version())->getMajorVersion(),
    'region' => 'us-east-1',
    'key' => 'ddevminio',
    'secret' => 'ddevminio',
    'title' => 'TYPO3 Content Storage',
    'publicBaseUrl' => 'https://fals3.ddev.site:10101/typo3-' . (new Typo3Version())->getMajorVersion() . '/',
    'defaultFolder' => 'user_upload',
    'basePath' => '',
    'cacheControl' => [
        'file:' . FileType::TEXT->value => [
            'max-age' => 3600,
            'private' => true
        ],
        'file:' . FileType::IMAGE->value => [
            'max-age' => 86400
        ],
        'processed-file:' . FileType::IMAGE->value => [
            'max-age' => 604800
        ],
        'file:' . FileType::AUDIO->value => [
            'max-age' => 86400
        ],
        'file:' . FileType::VIDEO->value => [
            'max-age' => 86400
        ],
        'file:' . FileType::APPLICATION->value => [
            'no-store' => true
        ]
    ]
];
