<?php

namespace MaxServ\FalS3\Processing;

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

use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Service\FileProcessingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ImagePreviewConfiguration
 */
class ImagePreviewConfiguration
{
    /**
     * @var int
     */
    public const DEFAULT_PREVIEW_WIDTH = 64;

    /**
     * @var int
     */
    public const DEFAULT_PREVIEW_HEIGHT = 64;

    /**
     * To avoid a miss when looking up a processed (preview) file try again with default configuration.
     *
     * In case of a preview image the repository is queried for a file without configuration
     * while is stores both `with` and `height`, this results in an additional processed file
     * and unnecessary bytes being transferred to and from the storage.
     *
     * @param FileProcessingService $fileProcessingService
     * @param DriverInterface $driver
     * @param ProcessedFile $processedFile
     * @param FileInterface $fileObject
     * @param string $taskType
     * @param array $configuration
     */
    public function onPreFileProcess(
        FileProcessingService $fileProcessingService,
        DriverInterface $driver,
        ProcessedFile $processedFile,
        FileInterface $fileObject,
        $taskType,
        array $configuration
    ) {
        if (
            $taskType === ProcessedFile::CONTEXT_IMAGEPREVIEW
            && $processedFile->isNew()
            && empty($configuration)
            && self::isDefaultPreviewConfiguration($processedFile->getProcessingConfiguration())
        ) {
            /** @var ProcessedFileRepository $processedFileRepository */
            $processedFileRepository = GeneralUtility::makeInstance(
                ProcessedFileRepository::class
            );

            // try to fetch an existing processed file for the preview with the default dimensions
            $existingProcessedFile = $processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration(
                $fileObject,
                $taskType,
                [
                    'width' => self::DEFAULT_PREVIEW_WIDTH,
                    'height' => self::DEFAULT_PREVIEW_HEIGHT
                ]
            );

            // if an existing file exists update the processed file passed to this slot
            if (
                $existingProcessedFile instanceof ProcessedFile
                && $existingProcessedFile->isProcessed()
                && $fileObject instanceof File
            ) {
                // basically $processedFile->reconstituteFromDatabaseRecord($databaseRow) would be sufficient,
                // but since that method is protected use the next best (and dirtiest) thing
                $processedFile->__construct(
                    $fileObject,
                    $taskType,
                    $existingProcessedFile->getProcessingConfiguration(),
                    $existingProcessedFile->toArray()
                );
            }
        }
    }

    /**
     * @param array $configuration
     * @return bool
     */
    protected static function isDefaultPreviewConfiguration(array $configuration)
    {
        return count($configuration) === 2
            && $configuration['width'] === self::DEFAULT_PREVIEW_WIDTH
            && $configuration['height'] === self::DEFAULT_PREVIEW_HEIGHT
        ;
    }
}
