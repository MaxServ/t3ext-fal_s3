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
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
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
     * @param string $signal
     *
     * @return void
     *
     * @throws FileDoesNotExistException
     */
    public function recordUpdatedOrCreated(array $data, $signal = null)
    {
        $file = null;

        // fetch the file using the $file property in case of a MetaData record
        if ($signal !== null
            && strpos($signal, 'MetaDataRepository') !== false
            && array_key_exists('file', $data)
            && !empty($data['file'])
        ) {
            $file = ResourceFactory::getInstance()->getFileObject($data['file'], []);
        } else if (array_key_exists('uid', $data)
            && !empty($data['uid'])
        ) {
            $file = ResourceFactory::getInstance()->getFileObject($data['uid'], $data);
        }

        // break if the file couldn't be fetched or is not an image stored on S3
        if ($file === null
            || (
                $file !== null
                && $file->getType() !== AbstractFile::FILETYPE_IMAGE
                || $file->getStorage()->getDriverType() !== AmazonS3Driver::DRIVER_KEY
            )
        ) {
            return;
        }

        // in order to avoid circular updates check if the file really needs dimensions
        // in case of a trigger coming from the MetaDataRepository
        // default is TRUE because most of the times this will be triggered from the context of a file.
        $needsIdentification = true;

        // with or height is set skip identification
        if ($signal !== null
            && strpos($signal, 'MetaDataRepository') !== false
            && array_key_exists('width', $data)
            && !empty($data['width'])
            && array_key_exists('height', $data)
            && !empty($data['height'])
        ) {
            $needsIdentification = false;
        }

        if ($needsIdentification) {
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
}
