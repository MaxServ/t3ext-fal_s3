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
	'readOnlyBucket' => '',
	'key' => '',
	'secret' => '',
	'publicBaseUrl' => '',
	'title' => 'Dummy S3 configuration (offline)'
);