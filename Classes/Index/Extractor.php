<?php

namespace MaxServ\FalS3\Index;

use MaxServ\FalS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Index\ExtractorInterface;
use TYPO3\CMS\Core\Type\File\ImageInfo;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Extractor
 */
class Extractor implements ExtractorInterface
{

    /**
     * @inheritDoc
     */
    public function getFileTypeRestrictions(): array
    {
        return [File::FILETYPE_IMAGE];
    }

    /**
     * @inheritDoc
     */
    public function getDriverRestrictions(): array
    {
        return [AmazonS3Driver::DRIVER_KEY];
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): int
    {
        return 30;
    }

    /**
     * @inheritDoc
     */
    public function getExecutionPriority(): int
    {
        return 30;
    }

    /**
     * @inheritDoc
     */
    public function canProcess(File $file): bool
    {
        return $file->getType() === File::FILETYPE_IMAGE
            && $file->getStorage()->getDriverType() === AmazonS3Driver::DRIVER_KEY;
    }

    /**
     * @inheritDoc
     */
    public function extractMetaData(File $file, array $previousExtractedData = []): array
    {
        if (empty($previousExtractedData['width']) || empty($previousExtractedData['height'])) {
            $imageDimensions = $this->getImageDimensions($file);
            if (!empty($imageDimensions)) {
                $previousExtractedData['width'] = $imageDimensions[0];
                $previousExtractedData['height'] = $imageDimensions[1];
            }
        }

        return $previousExtractedData;
    }

    /**
     * @param FileInterface $file
     * @return array
     */
    public function getImageDimensions(FileInterface $file): ?array
    {
        $fileNameAndPath = $file->getForLocalProcessing(false);
        $imageInfo = GeneralUtility::makeInstance(ImageInfo::class, $fileNameAndPath);
        return [
            $imageInfo->getWidth(),
            $imageInfo->getHeight(),
        ];
    }
}
