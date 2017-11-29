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
    'basePath' => '/assets/'
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
unset($driverRegistry, $signalSlotDispatcher);
