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

use MaxServ\FalS3;
use TYPO3;

/**
 * Class RecordMonitor
 *
 * @package MaxServ\FalS3\MetaData
 */
class RecordMonitor {

	/**
	 * Update the dimension of an image directly after creation
	 *
	 * @param array $data
	 *
	 * @return void
	 */
	public function recordUpdatedOrCreated(array $data) {
		$metaDataRepository = NULL;
		$storage = NULL;

		if ($data['type'] === TYPO3\CMS\Core\Resource\File::FILETYPE_IMAGE) {
			/* @var $storage \TYPO3\CMS\Core\Resource\ResourceStorage */
			$storage = TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->getStorageObject($data['storage']);

			if ($storage->getDriverType() === FalS3\Driver\AmazonS3Driver::DRIVER_KEY) {
				$storage->setEvaluatePermissions(FALSE);

				$metaDataRepository = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
					'TYPO3\\CMS\\Core\\Resource\\Index\\MetaDataRepository'
				);
			}
		}

		if ($metaDataRepository instanceof TYPO3\CMS\Core\Resource\Index\MetaDataRepository) {
			$file = $storage->getFile($data['identifier']);
			$record = $metaDataRepository->findByFileUid($data['uid']);

				// directly after creation of a file the dimensions are not set,
				// but must be added for the system to function properly
			if (is_array($record) && !array_key_exists('width', $record) && $file instanceof TYPO3\CMS\Core\Resource\File) {
				/* @var $graphicalFunctionsObject \TYPO3\CMS\Core\Imaging\GraphicalFunctions */
				$graphicalFunctionsObject = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
					'TYPO3\\CMS\\Core\\Imaging\\GraphicalFunctions'
				);

				$temporaryFilePath = $file->getForLocalProcessing(FALSE);

				$imageDimensions = $graphicalFunctionsObject->getImageDimensions($temporaryFilePath);

				if (is_array($imageDimensions) && array_key_exists(0, $imageDimensions) && array_key_exists(1, $imageDimensions)) {
					$record['width'] = $imageDimensions[0];
					$record['height'] = $imageDimensions[1];

					$metaDataRepository->update($file->getUid(), $record);
				}

				unlink($temporaryFilePath);
			}
		}
	}

}