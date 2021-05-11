<?php

namespace MaxServ\FalS3\Utility;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use MaxServ\FalS3;
use MaxServ\FalS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * Class RemoteObjectUtility
 * @package MaxServ\FalS3\Utility
 */
class RemoteObjectUtility
{
    /**
     * @var array
     */
    protected static $clients = [];

    /**
     * @var array
     */
    protected static $driverConfigurations = [];

    /**
     * @param ResourceStorage $storage
     *
     * @return S3ClientInterface|null
     */
    public static function resolveClientForStorage(ResourceStorage $storage)
    {
        $client = null;
        $storageIdentifier = $storage->getUid();

        if (array_key_exists($storageIdentifier, self::$clients)) {
            $client = self::$clients[$storageIdentifier];
        } elseif ($storage->getDriverType() === AmazonS3Driver::DRIVER_KEY) {
            $driverConfiguration = self::resolveDriverConfigurationForStorage($storage);

            $client = new S3Client(
                [
                    'version' => '2006-03-01',
                    'region' => $driverConfiguration['region'],
                    'credentials' => [
                        'key' => $driverConfiguration['key'],
                        'secret' => $driverConfiguration['secret']
                    ]
                ]
            );

            self::$clients[$storageIdentifier] = $client;
        }

        return $client;
    }

    /**
     * @param ResourceStorage $storage
     *
     * @return array
     */
    public static function resolveDriverConfigurationForStorage(ResourceStorage $storage)
    {
        $driverConfiguration = [];
        $storageIdentifier = $storage->getUid();

        if (array_key_exists($storageIdentifier, self::$driverConfigurations)) {
            $driverConfiguration = self::$driverConfigurations[$storageIdentifier];
        } elseif ($storage->getDriverType() === AmazonS3Driver::DRIVER_KEY) {
            $storageConfiguration = $storage->getConfiguration();

            if (array_key_exists('configurationKey', $storageConfiguration)) {
                $driverConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$storageConfiguration['configurationKey']];
            }

            // strip the s3 protocol prefix from the bucket name
            if (strpos($driverConfiguration['bucket'], 's3://') === 0) {
                $driverConfiguration['bucket'] = substr($driverConfiguration['bucket'], 5);
            }
        }

        return $driverConfiguration;
    }

    /**
     * @param AbstractFile $file
     * @param bool $isProcessed
     *
     * @return string
     */
    public static function resolveCacheControlDirectivesForFile(AbstractFile $file, $isProcessed = false)
    {
        $cacheControl = [];
        $directives = null;

        $configurationKey = ($isProcessed ? 'processed-file:' : 'file:') . $file->getType();
        $driverConfiguration = self::resolveDriverConfigurationForStorage($file->getStorage());

        if (
            array_key_exists('cacheControl', $driverConfiguration)
            && is_array($driverConfiguration['cacheControl'])
            && array_key_exists($configurationKey, $driverConfiguration['cacheControl'])
        ) {
            $directives = $driverConfiguration['cacheControl'][$configurationKey];
        }

        if (
            is_array($directives)
            && ((array_key_exists('private', $directives) && $directives['private']))
        ) {
            $cacheControl[] = 'private';
        }

        if (
            is_array($directives)
            && array_key_exists('max-age', $directives)
            && $directives['max-age'] > 0
        ) {
            $cacheControl[] = 'max-age=' . (int)$directives['max-age'];
        }

        // if a no-store directive is set ignore earlier parts
        if (
            is_array($directives)
            && array_key_exists('no-store', $directives)
            && $directives['no-store']
        ) {
            $cacheControl = ['no-store'];
        }

        return implode(', ', $cacheControl);
    }
}
