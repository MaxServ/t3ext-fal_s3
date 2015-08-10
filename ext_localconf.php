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
	'title' => 'Dummy S3 configuration (offline)'
);

\TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::getInstance()->registerExtractionService(
	'MaxServ\\FalS3\\MetaData\\ImageDimensionsExtractor'
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