<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

/** @var \TYPO3\CMS\Core\Resource\Driver\DriverRegistry $driverRegistry */
$driverRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    'TYPO3\\CMS\\Core\\Resource\\Driver\\DriverRegistry'
);

$driverRegistry->registerDriverClass(
    'MaxServ\\FalS3\\Driver\\AmazonS3Driver',
    MaxServ\FalS3\Driver\AmazonS3Driver::DRIVER_KEY,
    'S3 driver for FAL',
    'FILE:EXT:fal_s3/Configuration/FlexForm/AmazonS3DriverFlexForm.xml'
);

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations']['offlineStorage'] = array(
    'bucket' => '',
    'region' => '',
    'key' => '',
    'secret' => '',
    'title' => 'Dummy S3 configuration (offline)',
    'publicBaseUrl' => '',
    'defaultFolder' => 'user_upload',
    'basePath' => '/assets/',
    'cacheControl' => array(
        'file:' . (string) \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_TEXT => array(
            'max-age' => 3600,
            'private' => true
        ),
        'file:' . \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_IMAGE => array(
            'max-age' => 86400
        ),
        'processed-file:' . \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_IMAGE => array(
            'max-age' => 604800
        ),
        'file:' . \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_AUDIO => array(
            'max-age' => 86400
        ),
        'file:' . \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_VIDEO => array(
            'max-age' => 86400
        ),
        'file:' . \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_APPLICATION => array(
            'no-store' => true
        )
    )
);

/* @var $signalSlotDispatcher \TYPO3\CMS\Extbase\SignalSlot\Dispatcher */
$signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Extbase\SignalSlot\Dispatcher');
$signalSlotDispatcher->connect(
    'TYPO3\\CMS\\Core\\Resource\\Index\\FileIndexRepository',
    'recordUpdated',
    'MaxServ\\FalS3\\MetaData\\RecordMonitor',
    'recordUpdatedOrCreated'
);

$signalSlotDispatcher->connect(
    'TYPO3\\CMS\\Core\\Resource\\Index\\FileIndexRepository',
    'recordCreated',
    'MaxServ\\FalS3\\MetaData\\RecordMonitor',
    'recordUpdatedOrCreated'
);

$signalSlotDispatcher->connect(
    'TYPO3\\CMS\\Core\\Resource\\Index\\MetaDataRepository',
    'recordUpdated',
    'MaxServ\\FalS3\\MetaData\\RecordMonitor',
    'recordUpdatedOrCreated'
);

$signalSlotDispatcher->connect(
    'TYPO3\\CMS\\Core\\Resource\\Index\\MetaDataRepository',
    'recordCreated',
    'MaxServ\\FalS3\\MetaData\\RecordMonitor',
    'recordUpdatedOrCreated'
);

$signalSlotDispatcher->connect(
    'TYPO3\\CMS\\Core\\Resource\\ResourceStorage',
    'preFileProcess',
    'MaxServ\\FalS3\\Processing\\ImagePreviewConfiguration',
    'onPreFileProcess'
);

// cache control, trigger an update of remote objects if a changes is made locally (eg. by running the scheduler)
$signalSlotDispatcher->connect(
    'TYPO3\\CMS\\Core\\Resource\\Index\\MetaDataRepository',
    'recordUpdated',
    'MaxServ\\FalS3\\CacheControl\\RemoteObjectUpdater',
    'onLocalMetadataRecordUpdatedOrCreated'
);

$signalSlotDispatcher->connect(
    'TYPO3\\CMS\\Core\\Resource\\Index\\MetaDataRepository',
    'recordCreated',
    'MaxServ\\FalS3\\CacheControl\\RemoteObjectUpdater',
    'onLocalMetadataRecordUpdatedOrCreated'
);

$signalSlotDispatcher->connect(
    'TYPO3\\CMS\\Core\\Resource\\ResourceStorage',
    'postFileProcess',
    'MaxServ\\FalS3\\CacheControl\\RemoteObjectUpdater',
    'onPostFileProcess'
);

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['deployer']['configuration']['FalS3.yaml'] = array(
    'converter' => 'MaxServ\\FalS3\\Configuration\\ConfigurationConverter',
    'definition' => 'MaxServ\\FalS3\\Configuration\\ConfigurationDefinition',
    'loader' => 'MaxServ\\FalS3\\Configuration\\ConfigurationLoader'
);

// Register cache 'tx_fal_s3'
if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_fal_s3']['groups'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_fal_s3']['groups'] = array(
        'system'
    );
}

if (class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)) {
    $typo3Branch = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Information\Typo3Version::class
    )->getBranch();
} else {
    $typo3Branch = TYPO3_branch;
}

// Adding the ToolbarItem only works on reliable on TYPO3 v8 and higher
if (version_compare($typo3Branch, '8.0', '>=')) {
    // Register icon for FalS3 flush cache menu item
    /** @var \TYPO3\CMS\Core\Imaging\IconRegistry $iconRegistry */
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
    $iconRegistry->registerIcon(
        \MaxServ\FalS3\Toolbar\ToolbarItem::ITEM_ICON_IDENTIFIER,
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        [
            'source' => 'EXT:fal_s3/Resources/Public/Icons/FlushCache.svg'
        ]
    );

    // Register additional clear cache menu item
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['additionalBackendItems']['cacheActions'][\MaxServ\FalS3\Toolbar\ToolbarItem::ITEM_KEY] = \MaxServ\FalS3\Toolbar\ToolbarItem::class;
}

unset($driverRegistry, $signalSlotDispatcher, $typo3Branch, $iconRegistry);
