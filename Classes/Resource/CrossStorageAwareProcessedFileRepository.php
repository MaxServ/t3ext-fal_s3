<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Resource;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;

readonly class CrossStorageAwareProcessedFileRepository extends ProcessedFileRepository
{
    /**
     * @param array<string, mixed> $configuration
     */
    protected function createNewProcessedFileObject(
        File $originalFile,
        string $taskType,
        array $configuration
    ): ProcessedFile {
        return new CrossStorageAwareProcessedFile($originalFile, $taskType, $configuration);
    }

    /**
     * @param array<string, mixed> $databaseRow
     */
    protected function createDomainObject(array $databaseRow): ProcessedFile
    {
        // Reuse the parent's reconstitution path, then upgrade the type.
        // Cleaner than copying the parent body: the parent does the unserialize() etc.
        $existing = parent::createDomainObject($databaseRow);
        return new CrossStorageAwareProcessedFile(
            $existing->getOriginalFile(),
            $existing->getTaskIdentifier(),
            $existing->getProcessingConfiguration(),
            $databaseRow
        );
    }
}
