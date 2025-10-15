<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\AbstractFile;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations']['contentStorage'] = [
    'endpoint' => 'http://minio:10101',
    'use_path_style_endpoint' => true,
    'bucket' => 'typo3-' . (new Typo3Version())->getMajorVersion(),
    'region' => 'us-east-1',
    'key' => 'ddevminio',
    'secret' => 'ddevminio',
    'title' => 'TYPO3 Content Storage',
    'publicBaseUrl' => 'https://fals3.ddev.site:10101/typo3-12/',
    'defaultFolder' => 'user_upload',
    'basePath' => '',
    'cacheControl' => [
        'file:' . (string)AbstractFile::FILETYPE_TEXT => [
            'max-age' => 3600,
            'private' => true
        ],
        'file:' . AbstractFile::FILETYPE_IMAGE => [
            'max-age' => 86400
        ],
        'processed-file:' . AbstractFile::FILETYPE_IMAGE => [
            'max-age' => 604800
        ],
        'file:' . AbstractFile::FILETYPE_AUDIO => [
            'max-age' => 86400
        ],
        'file:' . AbstractFile::FILETYPE_VIDEO => [
            'max-age' => 86400
        ],
        'file:' . AbstractFile::FILETYPE_APPLICATION => [
            'no-store' => true
        ]
    ]
];
