<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Service\Extraction;

use MaxServ\FalS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Resource\Index\ExtractorInterface;
use TYPO3\CMS\Core\Type\File\ImageInfo;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ImageDimensionsExtraction implements ExtractorInterface
{
    /**
     * @inheritDoc
     * @return array<int>
     */
    public function getFileTypeRestrictions(): array
    {
        return [FileType::IMAGE->value];
    }

    /**
     * @inheritDoc
     * @return array<string>
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
        return $file->getType() === FileType::IMAGE->value
            && $file->getStorage()->getDriverType() === AmazonS3Driver::DRIVER_KEY;
    }

    /**
     * @inheritDoc
     * @param array<string, mixed> $previousExtractedData
     * @return array<string, mixed>
     */
    public function extractMetaData(File $file, array $previousExtractedData = []): array
    {
        if (!array_key_exists('width', $previousExtractedData) || !array_key_exists('height', $previousExtractedData)) {
            $imageDimensions = $this->getImageDimensions($file);
            $previousExtractedData['width'] = $imageDimensions['width'];
            $previousExtractedData['height'] = $imageDimensions['height'];
        }

        return $previousExtractedData;
    }

    /**
     * @return array<string, int>
     */
    public function getImageDimensions(FileInterface $file): array
    {
        $fileNameAndPath = $file->getForLocalProcessing(false);
        $imageInfo = GeneralUtility::makeInstance(ImageInfo::class, $fileNameAndPath);
        return [
            'width' => $imageInfo->getWidth(),
            'height' => $imageInfo->getHeight(),
        ];
    }
}
