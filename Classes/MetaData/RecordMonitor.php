<?php
namespace MaxServ\FalS3\MetaData;

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

use MaxServ\FalS3\Driver\AmazonS3Driver;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @package MaxServ\FalS3\MetaData
 */
class RecordMonitor
{

    /**
     * Update the dimension of an image directly after creation
     *
     * @param array $data
     *
     * @return void
     */
    public function recordUpdatedOrCreated(array $data)
    {
        if ((int)$data['type'] !== AbstractFile::FILETYPE_IMAGE) {
            return;
        }
        $file = ResourceFactory::getInstance()->getFileObject($data['uid'], $data);
        if ($file->getStorage()->getDriverType() !== AmazonS3Driver::DRIVER_KEY) {
            return;
        }
        $temporaryFilePath = $file->getForLocalProcessing(false);
        $metaDataRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\Index\\MetaDataRepository');
        $imageInfo = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Type\\File\\ImageInfo', $temporaryFilePath);
        $additionalMetaInformation = [
            'width' => $imageInfo->getWidth(),
            'height' => $imageInfo->getHeight(),
        ];
        $metaDataRepository->update($file->getUid(), $additionalMetaInformation);
    }
}
