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

use TYPO3;

/**
 * Class ImagePreviewConfiguration
 *
 * @package MaxServ\FalS3\Processing
 */
class ImagePreviewConfiguration
{

    /**
     * @var int
     */
    const DEFAULT_PREVIEW_WIDTH = 64;

    /**
     * @var int
     */
    const DEFAULT_PREVIEW_HEIGHT = 64;

    /**
     * To avoid a miss when looking up a processed (preview) file try again with default configuration.
     *
     * In case of a preview image the repository is queried for a file without configuration
     * while is stores both `with` and `height`, this results in an additional processed file
     * and unnecessary bytes being transfered to and from the storage.
     *
     * @param TYPO3\CMS\Core\Resource\Service\FileProcessingService $fileProcessingService
     * @param TYPO3\CMS\Core\Resource\Driver\DriverInterface $driver
     * @param TYPO3\CMS\Core\Resource\ProcessedFile $processedFile
     * @param TYPO3\CMS\Core\Resource\FileInterface $fileObject
     * @param string $taskType
     * @param array $configuration
     * @return void
     */
    public function onPreFileProcess(
        TYPO3\CMS\Core\Resource\Service\FileProcessingService $fileProcessingService,
        TYPO3\CMS\Core\Resource\Driver\DriverInterface $driver,
        TYPO3\CMS\Core\Resource\ProcessedFile $processedFile,
        TYPO3\CMS\Core\Resource\FileInterface $fileObject,
        $taskType,
        array $configuration
    ) {
        if ($taskType === TYPO3\CMS\Core\Resource\ProcessedFile::CONTEXT_IMAGEPREVIEW
            && $processedFile->isNew()
            && empty($configuration)
            && self::isDefaultPreviewConfiguration($processedFile->getProcessingConfiguration())
        ) {
            /** @var $processedFileRepository TYPO3\CMS\Core\Resource\ProcessedFileRepository */
            $processedFileRepository = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                TYPO3\CMS\Core\Resource\ProcessedFileRepository::class
            );

            // try to fetch an existing processed file for the preview with the default dimensions
            $existingProcessedFile = $processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration(
                $fileObject,
                $taskType,
                array(
                    'width' => self::DEFAULT_PREVIEW_WIDTH,
                    'height' => self::DEFAULT_PREVIEW_HEIGHT
                )
            );

            // if an existing file exists update the processed file passed to this slot
            if ($existingProcessedFile instanceof TYPO3\CMS\Core\Resource\ProcessedFile
                && $existingProcessedFile->isProcessed()
                && $fileObject instanceof TYPO3\CMS\Core\Resource\File
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
        return (count($configuration) === 2
            && $configuration['width'] === self::DEFAULT_PREVIEW_WIDTH
            && $configuration['height'] === self::DEFAULT_PREVIEW_HEIGHT
        );
    }
}