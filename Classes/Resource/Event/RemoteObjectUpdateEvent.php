<?php

declare(strict_types=1);

// phpcs:ignore
namespace MaxServ\FalS3\Resource\Event;

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

use Aws\Result;
use Aws\S3\S3Client;
use MaxServ\FalS3\Driver\AmazonS3Driver;
use MaxServ\FalS3\Utility\RemoteObjectUtility;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\Event\AfterFileMetaDataCreatedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileMetaDataUpdatedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class RemoteObjectUpdateEvent
 */
class RemoteObjectUpdateEvent
{
    /**
     * PSR-14 Event listener
     * @param AfterFileMetaDataCreatedEvent $event
     */
    public function afterFileMetaDataCreated(AfterFileMetaDataCreatedEvent $event): void
    {
        $this->updateRemoteCacheDirectiveForFile($event->getFileUid());
    }

    /**
     * PSR-14 Event listener
     * @param AfterFileMetaDataUpdatedEvent $event
     */
    public function afterFileMetaDataUpdated(AfterFileMetaDataUpdatedEvent $event): void
    {
        $this->updateRemoteCacheDirectiveForFile($event->getFileUid());
    }

    /**
     * PSR-14 Event listener
     * @param AfterFileProcessingEvent $event
     */
    public function afterFileProcessing(AfterFileProcessingEvent $event): void
    {
        if ($event->getProcessedFile()->usesOriginalFile() || !$event->getProcessedFile()->exists()) {
            return;
        }

        $fileInfo = $event->getProcessedFile()->getStorage()->getFileInfoByIdentifier(
            $event->getProcessedFile()->getIdentifier(),
            ['mtime']
        );

        if ($this->remoteObjectNeedsUpdate($fileInfo)) {
            $this->updateCacheControlDirectivesForRemoteObject($event->getProcessedFile());
        }
    }

    /**
     * @param array $fileInfo
     *
     * @return bool
     */
    protected function remoteObjectNeedsUpdate(array $fileInfo): bool
    {
        return array_key_exists('mtime', $fileInfo) && (int)$fileInfo['mtime'] > (time() - 30);
    }

    /**
     * @param int $fileUid
     */
    protected function updateRemoteCacheDirectiveForFile(int $fileUid): void
    {
        try {
            $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($fileUid);
        } catch (\Exception $e) {
            return;
        }

        if (!$file instanceof File) {
            return;
        }

        if ($file->getStorage()->getDriverType() !== AmazonS3Driver::DRIVER_KEY) {
            return;
        }

        $this->updateCacheControlDirectivesForRemoteObject($file);
        $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
        if ($processedFileRepository instanceof ProcessedFileRepository) {
            $processedFiles = $processedFileRepository->findAllByOriginalFile($file);
            array_walk(
                $processedFiles,
                function (ProcessedFile $processedFile) {
                    $this->updateCacheControlDirectivesForRemoteObject($processedFile);
                }
            );
        }
    }

    /**
     * @param AbstractFile $file
     */
    protected function updateCacheControlDirectivesForRemoteObject(AbstractFile $file): void
    {
        $currentResource = null;

        $client = RemoteObjectUtility::resolveClientForStorage($file->getStorage());
        $driverConfiguration = RemoteObjectUtility::resolveDriverConfigurationForStorage($file->getStorage());

        $key = '';

        if (array_key_exists('basePath', $driverConfiguration) && !empty($driverConfiguration['basePath'])) {
            $key .= trim($driverConfiguration['basePath'], '/') . '/';
        }

        $key .= ltrim($file->getIdentifier(), '/');

        if (
            array_key_exists('bucket', $driverConfiguration)
            && $client instanceof S3Client
        ) {
            try {
                $currentResource = $client->headObject(
                    [
                        'Bucket' => $driverConfiguration['bucket'],
                        'Key' => $key
                    ]
                );
            } catch (\Exception $e) {
                // fail silently if a file doesn't exist
            }
        }

        if ($file instanceof ProcessedFile) {
            $cacheControl = RemoteObjectUtility::resolveCacheControlDirectivesForFile(
                $file->getOriginalFile(),
                true
            );
        } else {
            $cacheControl = RemoteObjectUtility::resolveCacheControlDirectivesForFile($file);
        }

        if (
            !empty($cacheControl)
            && $currentResource instanceof Result
            && $currentResource->hasKey('Metadata')
            && is_array($currentResource->get('Metadata'))
            && $currentResource->hasKey('CacheControl')
            && strcmp($currentResource->get('CacheControl'), $cacheControl) !== 0
        ) {
            $client->copyObject(
                [
                    'Bucket' => $driverConfiguration['bucket'],
                    'CacheControl' => $cacheControl,
                    'ContentType' => $currentResource->get('ContentType'),
                    'CopySource' => $driverConfiguration['bucket'] . '/' . S3Client::encodeKey($key),
                    'Key' => $key,
                    'Metadata' => $currentResource->get('Metadata'),
                    'MetadataDirective' => 'REPLACE'
                ]
            );
        }
    }
}
