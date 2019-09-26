<?php
namespace MaxServ\FalS3\CacheControl;

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

use Aws;
use MaxServ\FalS3;
use TYPO3\CMS\Core;

/**
 * Class RemoteObjectUpdater
 * @package MaxServ\FalS3\CacheControl
 */
class RemoteObjectUpdater
{
    /**
     * Update the dimension of an image directly after creation
     *
     * @param array $data
     *
     * @return array Array of passed arguments, single item in it wich is unmodified $data
     */
    public function onLocalMetadataRecordUpdatedOrCreated(array $data)
    {
        $file = null;

        try {
            $file = Core\Resource\ResourceFactory::getInstance()->getFileObject(
                $data['file']
            );
        } catch (\Exception $e) {
            $file = null;
        }

        if ($file->getStorage()->getDriverType() !== FalS3\Driver\AmazonS3Driver::DRIVER_KEY) {
            return;
        }

        $this->updateCacheControlDirectivesForRemoteObject($file);

        if ($file instanceof Core\Resource\File) {
            $processedFileRepository = Core\Utility\GeneralUtility::makeInstance(
                Core\Resource\ProcessedFileRepository::class
            );
            if ($processedFileRepository instanceof Core\Resource\ProcessedFileRepository) {
                $processedFiles = $processedFileRepository->findAllByOriginalFile($file);
                array_walk(
                    $processedFiles,
                    function (Core\Resource\ProcessedFile $processedFile) {
                        $this->updateCacheControlDirectivesForRemoteObject($processedFile);
                    }
                );
            }
        }
        
        return [$data];
    }

    /**
     * If a processed file is created (eg. a thumbnail) update the remote metadata.
     *
     * Because this method can be invoked without updating the actual file check
     * the modification time of the remote object. Triggering an index for FAL and
     * using the method above will force updating regardless of the modification time.
     *
     * @param Core\Resource\Service\FileProcessingService $fileProcessingService
     * @param Core\Resource\Driver\DriverInterface $driver
     * @param Core\Resource\ProcessedFile $processedFile
     * @param Core\Resource\FileInterface $fileObject
     * @param string $taskType
     * @param array $configuration
     * @return void
     */
    public function onPostFileProcess(
        Core\Resource\Service\FileProcessingService $fileProcessingService,
        Core\Resource\Driver\DriverInterface $driver,
        Core\Resource\ProcessedFile $processedFile,
        Core\Resource\FileInterface $fileObject,
        $taskType,
        array $configuration
    ) {
        $fileInfo = $driver->getFileInfoByIdentifier($processedFile->getIdentifier());

        if (is_array($fileInfo)
            && array_key_exists('mtime', $fileInfo)
            && (int) $fileInfo['mtime'] > (time() - 30)
        ) {
            $this->updateCacheControlDirectivesForRemoteObject($processedFile);
        }
    }

    /**
     * @param Core\Resource\AbstractFile $file
     *
     * @return void
     */
    protected function updateCacheControlDirectivesForRemoteObject(Core\Resource\AbstractFile $file)
    {
        $cacheControl = null;
        $currentResource = null;

        $client = FalS3\Utility\RemoteObjectUtility::resolveClientForStorage($file->getStorage());
        $driverConfiguration = FalS3\Utility\RemoteObjectUtility::resolveDriverConfigurationForStorage($file->getStorage());

        $key = '';

        if (array_key_exists('basePath', $driverConfiguration) && !empty($driverConfiguration['basePath'])) {
            $key .= trim($driverConfiguration['basePath'], '/') . '/';
        }

        $key .= ltrim($file->getIdentifier(), '/');

        if (is_array($driverConfiguration)
            && array_key_exists('bucket', $driverConfiguration)
            && $client instanceof Aws\S3\S3Client
        ) {
            try {
                $currentResource = $client->headObject(array(
                  'Bucket' => $driverConfiguration['bucket'],
                  'Key' => $key
                ));
            } catch (\Exception $e) {
              // fail silently if a file doesn't exist
            }
        }

        if ($file instanceof Core\Resource\ProcessedFile) {
            $cacheControl = FalS3\Utility\RemoteObjectUtility::resolveCacheControlDirectivesForFile(
                $file->getOriginalFile(),
                true
            );
        } else {
            $cacheControl = FalS3\Utility\RemoteObjectUtility::resolveCacheControlDirectivesForFile($file);
        }

        if (!empty($cacheControl)
            && $currentResource instanceof Aws\Result
            && $currentResource->hasKey('Metadata')
            && is_array($currentResource->get('Metadata'))
            && $currentResource->hasKey('CacheControl')
            && strcmp($currentResource->get('CacheControl'), $cacheControl) !== 0
        ) {
            $client->copyObject(array(
                'Bucket' => $driverConfiguration['bucket'],
                'CacheControl' => $cacheControl,
                'ContentType' => $currentResource->get('ContentType'),
                'CopySource' => $driverConfiguration['bucket'] . '/' . Aws\S3\S3Client::encodeKey($key),
                'Key' => $key,
                'Metadata' => $currentResource->get('Metadata'),
                'MetadataDirective' => 'REPLACE'
            ));
        }
    }
}
