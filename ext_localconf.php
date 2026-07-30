<?php

declare(strict_types=1);

use MaxServ\FalS3\Driver\AmazonS3Driver;
use MaxServ\FalS3\Service\Extraction\ExifToolMetadataExtraction;
use MaxServ\FalS3\Service\Extraction\PdfinfoMetadataExtraction;
use MaxServ\FalS3\Service\Extraction\PhpMetadataExtraction;
use MaxServ\FalS3\Service\Extraction\TikaLanguageDetector;
use MaxServ\FalS3\Service\Extraction\TikaMetadataExtraction;
use TYPO3\CMS\Core\Resource\Driver\DriverRegistry;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die();

$driverRegistry = GeneralUtility::makeInstance(
    DriverRegistry::class
);

$driverRegistry->registerDriverClass(
    AmazonS3Driver::class,
    AmazonS3Driver::DRIVER_KEY,
    'S3 driver for FAL',
    'FILE:EXT:fal_s3/Configuration/FlexForm/AmazonS3DriverFlexForm.xml'
);

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations']['offlineStorage'] = [
    'bucket' => '',
    'region' => '',
    'key' => '',
    'secret' => '',
    'title' => 'Dummy S3 configuration (offline)',
    'publicBaseUrl' => '',
    'defaultFolder' => 'user_upload',
    'basePath' => '/assets/',
    'cacheControl' => [
        'file:' . (string)FileType::TEXT->value => [
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

// Register cache 'tx_fal_s3'
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_fal_s3']['groups'] ??= ['system'];

if (ExtensionManagementUtility::isLoaded('extractor')) {
    // phpcs:disable
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\Causal\Extractor\Service\Extraction\PhpMetadataExtraction::class] = [
        'className' => PhpMetadataExtraction::class
    ];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\Causal\Extractor\Service\Extraction\ExifToolMetadataExtraction::class] = [
        'className' => ExifToolMetadataExtraction::class
    ];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\Causal\Extractor\Service\Extraction\PdfinfoMetadataExtraction::class] = [
        'className' => PdfinfoMetadataExtraction::class
    ];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\Causal\Extractor\Service\Extraction\TikaMetadataExtraction::class] = [
        'className' => TikaMetadataExtraction::class
    ];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\Causal\Extractor\Service\Extraction\TikaLanguageDetector::class] = [
        'className' => TikaLanguageDetector::class
    ];
    // phpcs:enable
}
