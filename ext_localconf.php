<?php

defined('TYPO3_MODE') or die();

call_user_func(function () {
    /** @var \TYPO3\CMS\Core\Resource\Driver\DriverRegistry $driverRegistry */
    $driverRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Resource\Driver\DriverRegistry::class
    );

    $driverRegistry->registerDriverClass(
        \MaxServ\FalS3\Driver\AmazonS3Driver::class,
        MaxServ\FalS3\Driver\AmazonS3Driver::DRIVER_KEY,
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
            'file:' . (string)\TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_TEXT => [
                'max-age' => 3600,
                'private' => true
            ],
            'file:' . \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_IMAGE => [
                'max-age' => 86400
            ],
            'processed-file:' . \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_IMAGE => [
                'max-age' => 604800
            ],
            'file:' . \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_AUDIO => [
                'max-age' => 86400
            ],
            'file:' . \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_VIDEO => [
                'max-age' => 86400
            ],
            'file:' . \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_APPLICATION => [
                'no-store' => true
            ]
        ]
    ];

    if (class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)) {
        $typo3Branch = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Information\Typo3Version::class
        )->getBranch();
    } else {
        $typo3Branch = TYPO3_branch;
    }

    // Since TYPO3 v10.2, all signal slots have been migrated to PSR-14 events.
    // Only configure the deprecated signal slots in TYPO3 v8 and v9.
    if (version_compare($typo3Branch, '10.4', '<')) {
        /* @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher $signalSlotDispatcher */
        $signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
        );

        /*
         * In https://github.com/TYPO3/TYPO3.CMS/blob/v8.7.9/typo3/sysext/filelist/Classes/FileList.php#L742 a
         * processed file for a preview thumbnail uses an empty configuration array whereas 64x64 is stored in the
         * database and causes a miss and a thumbnail being generated over and over.
         *
         * @deprecated This signal slot is only necessary for TYPO# v8, because in TYPO3 v9+ the dimensions are
         * passed from within the configuration
         */
        if (version_compare($typo3Branch, '9.0', '<')) {
            $signalSlotDispatcher->connect(
                \TYPO3\CMS\Core\Resource\ResourceStorage::class,
                'preFileProcess',
                \MaxServ\FalS3\Processing\ImagePreviewConfiguration::class,
                'onPreFileProcess'
            );
        }

        $signalSlotDispatcher->connect(
            \TYPO3\CMS\Core\Resource\Index\FileIndexRepository::class,
            'recordUpdated',
            \MaxServ\FalS3\MetaData\RecordMonitor::class,
            'recordUpdatedOrCreated'
        );

        $signalSlotDispatcher->connect(
            \TYPO3\CMS\Core\Resource\Index\FileIndexRepository::class,
            'recordCreated',
            \MaxServ\FalS3\MetaData\RecordMonitor::class,
            'recordUpdatedOrCreated'
        );

        $signalSlotDispatcher->connect(
            \TYPO3\CMS\Core\Resource\Index\MetaDataRepository::class,
            'recordUpdated',
            \MaxServ\FalS3\MetaData\RecordMonitor::class,
            'recordUpdatedOrCreated'
        );

        $signalSlotDispatcher->connect(
            \TYPO3\CMS\Core\Resource\Index\MetaDataRepository::class,
            'recordCreated',
            \MaxServ\FalS3\MetaData\RecordMonitor::class,
            'recordUpdatedOrCreated'
        );

        // cache control, trigger an update of remote objects if a change is made locally (eg. by running the scheduler)
        $signalSlotDispatcher->connect(
            \TYPO3\CMS\Core\Resource\Index\MetaDataRepository::class,
            'recordUpdated',
            \MaxServ\FalS3\Resource\Event\RemoteObjectUpdateEvent::class,
            'onLocalMetadataRecordUpdatedOrCreated'
        );

        $signalSlotDispatcher->connect(
            \TYPO3\CMS\Core\Resource\Index\MetaDataRepository::class,
            'recordCreated',
            \MaxServ\FalS3\Resource\Event\RemoteObjectUpdateEvent::class,
            'onLocalMetadataRecordUpdatedOrCreated'
        );

        $signalSlotDispatcher->connect(
            \TYPO3\CMS\Core\Resource\ResourceStorage::class,
            'postFileProcess',
            \MaxServ\FalS3\Resource\Event\RemoteObjectUpdateEvent::class,
            'onPostFileProcess'
        );
    }

    // Register cache 'tx_fal_s3'
    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_fal_s3']['groups'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_fal_s3']['groups'] = [
            'system'
        ];
    }

    // register extractor
    \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::class)
        ->registerExtractionService(\MaxServ\FalS3\Service\Extraction\ImageDimensionsExtraction::class);

    if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('extractor')) {
        // phpcs:disable
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\Causal\Extractor\Service\Extraction\PhpMetadataExtraction::class] = [
            'className' => \MaxServ\FalS3\Service\Extraction\PhpMetadataExtraction::class
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\Causal\Extractor\Service\Extraction\ExifToolMetadataExtraction::class] = [
            'className' => \MaxServ\FalS3\Service\Extraction\ExifToolMetadataExtraction::class
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\Causal\Extractor\Service\Extraction\PdfinfoMetadataExtraction::class] = [
            'className' => \MaxServ\FalS3\Service\Extraction\PdfinfoMetadataExtraction::class
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\Causal\Extractor\Service\Extraction\TikaMetadataExtraction::class] = [
            'className' => \MaxServ\FalS3\Service\Extraction\TikaMetadataExtraction::class
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\Causal\Extractor\Service\Extraction\TikaLanguageDetector::class] = [
            'className' => \MaxServ\FalS3\Service\Extraction\TikaLanguageDetector::class
        ];
        // phpcs:enable
    }

    // Register icon for FalS3 flush cache menu item
    /** @var \TYPO3\CMS\Core\Imaging\IconRegistry $iconRegistry */
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Imaging\IconRegistry::class
    );

    $iconRegistry->registerIcon(
        \MaxServ\FalS3\Toolbar\ToolbarItem::ITEM_ICON_IDENTIFIER,
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        [
            'source' => 'EXT:fal_s3/Resources/Public/Icons/FlushCache.svg'
        ]
    );

    // Register additional clear cache menu item
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['additionalBackendItems']['cacheActions'][\MaxServ\FalS3\Toolbar\ToolbarItem::ITEM_KEY] = \MaxServ\FalS3\Toolbar\ToolbarItem::class;
});
