<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Utility;

use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use MaxServ\FalS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\ResourceStorage;

class RemoteObjectUtility
{
    protected static array $clients = [];
    protected static array $driverConfigurations = [];

    public static function resolveClientForStorage(ResourceStorage $storage): ?S3ClientInterface
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

    public static function resolveDriverConfigurationForStorage(ResourceStorage $storage): array
    {
        $driverConfiguration = [];
        $storageIdentifier = $storage->getUid();

        if (array_key_exists($storageIdentifier, self::$driverConfigurations)) {
            $driverConfiguration = self::$driverConfigurations[$storageIdentifier];
        } elseif ($storage->getDriverType() === AmazonS3Driver::DRIVER_KEY) {
            $storageConfiguration = $storage->getConfiguration();

            if (array_key_exists('configurationKey', $storageConfiguration)) {
                // phpcs:ignore
                $driverConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$storageConfiguration['configurationKey']];
            }

            // strip the s3 protocol prefix from the bucket name
            if (str_starts_with($driverConfiguration['bucket'], 's3://')) {
                $driverConfiguration['bucket'] = substr($driverConfiguration['bucket'], 5);
            }
        }

        return $driverConfiguration;
    }

    public static function resolveCacheControlDirectivesForFile(AbstractFile $file, bool $isProcessed = false): string
    {
        $cacheControl = [];
        $directives = null;

        $configurationKey = ($isProcessed ? 'processed-file:' : 'file:') . $file->getType();
        $driverConfiguration = self::resolveDriverConfigurationForStorage($file->getStorage());

        if (isset($driverConfiguration['cacheControl'][$configurationKey])) {
            $directives = $driverConfiguration['cacheControl'][$configurationKey];
        }

        if ($directives['private'] ?? false) {
            $cacheControl[] = 'private';
        }

        if ((int)($directives['max-age'] ?? 0) > 0) {
            $cacheControl[] = 'max-age=' . (int)$directives['max-age'];
        }

        // if a no-store directive is set ignore earlier parts
        if ($directives['no-store'] ?? false) {
            $cacheControl = ['no-store'];
        }

        return implode(', ', $cacheControl);
    }
}
