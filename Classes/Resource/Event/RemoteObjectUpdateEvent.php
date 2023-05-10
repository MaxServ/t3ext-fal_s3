<?php

declare(strict_types=1);

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

use MaxServ\FalS3\CacheControl\RemoteObjectUpdateCacheControl;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\Event\AfterFileMetaDataCreatedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileMetaDataUpdatedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Service\FileProcessingService;

/**
 * Class RemoteObjectUpdateEvent
 */
class RemoteObjectUpdateEvent
{
    /**
     * @var RemoteObjectUpdateCacheControl
     */
    protected $remoteObjectUpdateCacheControl;

    public function __construct(RemoteObjectUpdateCacheControl $remoteObjectUpdateCacheControl)
    {
        $this->remoteObjectUpdateCacheControl = $remoteObjectUpdateCacheControl;
    }

    /**
     * PSR-14 Event listener
     * @param AfterFileMetaDataCreatedEvent $event
     */
    public function afterFileMetaDataCreated(AfterFileMetaDataCreatedEvent $event): void
    {
        $this->remoteObjectUpdateCacheControl->updateRemoteCacheDirectiveForFile($event->getFileUid());
    }

    /**
     * PSR-14 Event listener
     * @param AfterFileMetaDataUpdatedEvent $event
     */
    public function afterFileMetaDataUpdated(AfterFileMetaDataUpdatedEvent $event): void
    {
        $this->remoteObjectUpdateCacheControl->updateRemoteCacheDirectiveForFile($event->getFileUid());
    }

    /**
     * PSR-14 Event listener
     * @param AfterFileProcessingEvent $event
     */
    public function afterFileProcessing(AfterFileProcessingEvent $event): void
    {
        if ($event->getProcessedFile()->exists()) {
            $fileInfo = $event->getProcessedFile()->getStorage()->getFileInfoByIdentifier(
                $event->getProcessedFile()->getIdentifier()
            );

            if ($this->remoteObjectUpdateCacheControl->remoteObjectNeedsUpdate($fileInfo)) {
                $this->remoteObjectUpdateCacheControl->updateCacheControlDirectivesForRemoteObject($event->getProcessedFile());
            }
        }
    }

    /**
     * Update the dimension of an image directly after creation
     *
     * @param array $data
     * @return array Array of passed arguments, single item in it which is unmodified $data
     * @deprecated Since TYPO3 v10.2, core uses PSR-14 events. This signal slot is only being used in TYPO3 v8 and v9.
     */
    public function onLocalMetadataRecordUpdatedOrCreated(array $data): array
    {
        $fileUid = (int)($data['file'] ?? 0);
        if ($fileUid > 0) {
            $this->remoteObjectUpdateCacheControl->updateRemoteCacheDirectiveForFile($fileUid);
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
     * @param FileProcessingService $fileProcessingService
     * @param DriverInterface $driver
     * @param ProcessedFile $processedFile
     * @param FileInterface $fileObject
     * @param string $taskType
     * @param array $configuration
     * @deprecated Since TYPO3 v10.2, core uses PSR-14 events. This signal slot is only being used in TYPO3 v8 and v9.
     */
    public function onPostFileProcess(
        FileProcessingService $fileProcessingService,
        DriverInterface $driver,
        ProcessedFile $processedFile,
        FileInterface $fileObject,
        string $taskType,
        array $configuration
    ): void {
        $fileInfo = $driver->getFileInfoByIdentifier($processedFile->getIdentifier());
        if ($this->remoteObjectUpdateCacheControl->remoteObjectNeedsUpdate($fileInfo)) {
            $this->remoteObjectUpdateCacheControl->updateCacheControlDirectivesForRemoteObject($processedFile);
        }
    }
}
